const { test, expect } = require('@playwright/test');

async function login(page) {
  await page.goto('/login');
  await page.getByLabel('Username or email').fill('admin');
  await page.getByLabel('Password').fill('changeme');
  await Promise.all([
    page.waitForURL('**/board'),
    page.getByRole('button', { name: 'Sign in' }).click(),
  ]);
}

function localPath(response, path) {
  try {
    return new URL(response.url()).pathname === path;
  } catch {
    return false;
  }
}

test('map visibly renders current site imagery', async ({ page }, testInfo) => {
  const consoleErrors = [];
  const pageErrors = [];
  const failedLocalRequests = [];

  page.on('console', msg => {
    if (msg.type() === 'error') consoleErrors.push(msg.text());
  });
  page.on('pageerror', error => pageErrors.push(String(error)));
  page.on('requestfailed', request => {
    const url = request.url();
    if (url.includes('127.0.0.1:8000') || url.includes('/sitemap/')) {
      failedLocalRequests.push(`${url}: ${request.failure()?.errorText || 'failed'}`);
    }
  });

  await login(page);

  const layersResponse = page.waitForResponse(r => localPath(r, '/api/layers'));
  const areasResponse = page.waitForResponse(r => localPath(r, '/api/areas'));
  const landmarksResponse = page.waitForResponse(r => localPath(r, '/api/landmarks'));
  const openResponse = page.waitForResponse(r => localPath(r, '/api/open'));
  const rasterResponse = page.waitForResponse(r => r.url().includes('/sitemap/imagery/site-cf-2026-09-14_L17_0.25m.jpg'));

  await page.goto('/map');

  const responses = await Promise.all([
    layersResponse,
    areasResponse,
    landmarksResponse,
    openResponse,
    rasterResponse,
  ]);
  for (const response of responses) {
    expect(response.ok(), `${response.url()} returned HTTP ${response.status()}`).toBeTruthy();
  }

  const map = page.locator('#map');
  await expect(map).toHaveAttribute('data-hwrt-basemap', 'loaded', { timeout: 45_000 });

  const dimensions = await map.boundingBox();
  expect(dimensions).not.toBeNull();
  expect(dimensions.width).toBeGreaterThan(800);
  expect(dimensions.height).toBeGreaterThan(500);

  const imageState = await page.locator('#map img.leaflet-image-layer').evaluateAll(images => {
    const mapRect = document.getElementById('map').getBoundingClientRect();
    return {
      count: images.length,
      loaded: images.filter(img => img.complete && img.naturalWidth > 0 && img.naturalHeight > 0).length,
      intersecting: images.filter(img => {
        const r = img.getBoundingClientRect();
        return img.complete
          && img.naturalWidth > 0
          && r.right > mapRect.left
          && r.left < mapRect.right
          && r.bottom > mapRect.top
          && r.top < mapRect.bottom;
      }).length,
    };
  });

  expect(imageState.count).toBeGreaterThan(0);
  expect(imageState.loaded).toBeGreaterThan(0);
  expect(imageState.intersecting).toBeGreaterThan(0);

  const centre = await map.evaluate(el => {
    const api = el._hwrtMapApi;
    const c = api.map.getCenter();
    return { easting: c.lng, northing: -c.lat };
  });

  expect(centre.easting).toBeGreaterThanOrEqual(475775.5866);
  expect(centre.easting).toBeLessThanOrEqual(477002.9044);
  expect(centre.northing).toBeGreaterThanOrEqual(7717771.1393);
  expect(centre.northing).toBeLessThanOrEqual(7719644.4138);

  await page.locator('#planop').evaluate(el => {
    el.value = '0';
    el.dispatchEvent(new Event('input', { bubbles: true }));
  });
  await page.waitForTimeout(250);
  await map.screenshot({ path: testInfo.outputPath('map-render.png') });

  expect(failedLocalRequests, failedLocalRequests.join('\n')).toEqual([]);
  expect(pageErrors, pageErrors.join('\n')).toEqual([]);
  expect(consoleErrors, consoleErrors.join('\n')).toEqual([]);
});

test('Control Room pin stays aligned between imagery and plan', async ({ page }, testInfo) => {
  await login(page);

  const controlRoom = await page.evaluate(async () => {
    const response = await fetch('/api/landmarks', { headers: { Accept: 'application/json' } });
    if (!response.ok) throw new Error(`Landmarks HTTP ${response.status}`);
    const rows = await response.json();
    return rows.find(row => /control room/i.test(row.name));
  });

  expect(controlRoom, 'Control Room landmark was not imported from the sitemap').toBeTruthy();

  await page.goto('/log');
  await page.getByRole('button', { name: /New location \(drop a pin\)/ }).click();

  const pinMap = page.locator('#pinmap');
  await expect(pinMap).toHaveAttribute('data-hwrt-basemap', 'loaded', { timeout: 45_000 });

  await pinMap.evaluate((el, landmark) => new Promise(resolve => {
    const api = el._hwrtMapApi;
    let resolved = false;
    const done = () => {
      if (resolved) return;
      resolved = true;
      resolve();
    };
    api.map.once('moveend', done);
    api.focus(Number(landmark.easting), Number(landmark.northing), 2);
    setTimeout(done, 750);
  }), controlRoom);

  const clickPoint = await pinMap.evaluate((el, landmark) => {
    const api = el._hwrtMapApi;
    const point = api.map.latLngToContainerPoint(
      api.toLL(Number(landmark.easting), Number(landmark.northing))
    );
    const rect = el.getBoundingClientRect();
    return { x: rect.left + point.x, y: rect.top + point.y };
  }, controlRoom);

  await page.mouse.click(clickPoint.x, clickPoint.y);

  const marker = page.locator('#pinmap .leaflet-marker-icon').last();
  await expect(marker).toBeVisible();

  const areaAtPin = await page.evaluate(async landmark => {
    const e = encodeURIComponent(landmark.easting);
    const n = encodeURIComponent(landmark.northing);
    const response = await fetch(`/api/locations/nearby?easting=${e}&northing=${n}`, {
      headers: { Accept: 'application/json' },
    });
    if (!response.ok) throw new Error(`Nearby HTTP ${response.status}`);
    return response.json();
  }, controlRoom);

  expect(areaAtPin.area?.name || '').toMatch(/control room/i);

  await pinMap.evaluate(el => el._hwrtMapApi.setPlanOpacity(0));
  await page.waitForTimeout(250);
  const imageryMarker = await marker.boundingBox();
  expect(imageryMarker).not.toBeNull();
  await pinMap.screenshot({ path: testInfo.outputPath('map-pin-imagery.png') });

  await pinMap.evaluate(el => el._hwrtMapApi.setPlanOpacity(1));
  await page.waitForTimeout(250);
  const planMarker = await marker.boundingBox();
  expect(planMarker).not.toBeNull();
  await pinMap.screenshot({ path: testInfo.outputPath('map-pin-plan.png') });

  expect(Math.abs(planMarker.x - imageryMarker.x)).toBeLessThanOrEqual(1);
  expect(Math.abs(planMarker.y - imageryMarker.y)).toBeLessThanOrEqual(1);
});
