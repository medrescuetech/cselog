import os
import sys
import time
from playwright.sync_api import sync_playwright

BASE_URL = os.environ.get("BASE_URL", "http://127.0.0.1:8000")
SCREENSHOT_DIR = os.environ.get("SCREENSHOT_DIR", "/home/jules/verification/screenshots")
VIDEO_DIR = os.environ.get("VIDEO_DIR", "/home/jules/verification/videos")

def run_tests():
    os.makedirs(SCREENSHOT_DIR, exist_ok=True)
    os.makedirs(VIDEO_DIR, exist_ok=True)

    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        context = browser.new_context(
            viewport={"width": 1280, "height": 800},
            record_video_dir=VIDEO_DIR
        )
        page = context.new_page()

        console_errors = []
        page.on("console", lambda msg: console_errors.append(msg.text) if msg.type == "error" else None)

        try:
            # 1. Login
            print("Logging in...")
            page.goto(f"{BASE_URL}/login")
            page.wait_for_timeout(500)
            page.fill("input[name='email']", "admin@example.com")
            page.fill("input[name='password']", "changeme")
            page.click("button:has-text('Sign in')")
            page.wait_for_timeout(1000)

            # Assert logged in
            assert page.url.rstrip("/") != f"{BASE_URL}/login", f"Failed to log in, stayed at {page.url}"

            # 2. Map Page Test
            print("Testing /map page rendering...")
            page.goto(f"{BASE_URL}/map")
            page.wait_for_timeout(2000) # allow Leaflet & images to load

            map_box = page.locator("#map").bounding_box()
            assert map_box is not None, "#map element not found"
            assert map_box["width"] > 400 and map_box["height"] > 300, "#map element dimensions too small"

            # Verify Leaflet container
            leaflet_container = page.locator(".leaflet-container")
            assert leaflet_container.count() > 0, ".leaflet-container not found"

            # Verify raster overlay loaded
            raster_img = page.locator(".leaflet-image-layer").first
            assert raster_img.count() > 0, "No .leaflet-image-layer found"

            img_state = page.evaluate("""() => {
                const img = document.querySelector('.leaflet-image-layer');
                if (!img) return null;
                const rect = img.getBoundingClientRect();
                return {
                    complete: img.complete,
                    naturalWidth: img.naturalWidth,
                    naturalHeight: img.naturalHeight,
                    width: rect.width,
                    height: rect.height
                };
            }""")
            print("Raster image state:", img_state)
            assert img_state is not None and img_state["complete"], "Raster image is not complete"
            assert img_state["naturalWidth"] > 0, "Raster image naturalWidth is 0"

            # Take primary map rendering screenshot
            map_render_path = os.path.join(SCREENSHOT_DIR, "map-render.png")
            page.screenshot(path=map_render_path)
            print(f"Saved {map_render_path}")

            # 3. Pin-Drop & Alignment Workflow Test
            print("Testing Pin-Drop and Imagery/Plan spatial alignment...")
            page.goto(f"{BASE_URL}/log")
            page.wait_for_timeout(500)

            # Click "+ New location (drop a pin)"
            page.get_by_role("button", name="New location (drop a pin)").click()
            page.wait_for_timeout(1000)

            # Drop pin at known Control Room MGA50 coords: Easting 476000, Northing 7718500
            page.evaluate("""() => {
                const e = 476000, n = 7718500;
                const el = document.querySelector('[x-data]');
                if (el && el._x_dataStack) {
                    const data = el._x_dataStack[0];
                    data.place({ e, n });
                    data.mapObj.focus(e, n, 2);
                }
            }""")
            page.wait_for_timeout(1500)

            # Save Pin Imagery Screenshot
            pin_imagery_path = os.path.join(SCREENSHOT_DIR, "map-pin-imagery.png")
            page.screenshot(path=pin_imagery_path)
            print(f"Saved {pin_imagery_path}")

            # Toggle Layer Control / Plan overlay
            page.evaluate("""() => {
                const el = document.querySelector('[x-data]');
                if (el && el._x_dataStack) {
                    const data = el._x_dataStack[0];
                    // Turn on plan overlay if present
                    if (data.mapObj.overlays['Site C/F Detailed Plot Plan']) {
                        data.mapObj.overlays['Site C/F Detailed Plot Plan'].addTo(data.mapObj.map);
                    }
                }
            }""")
            page.wait_for_timeout(1000)

            # Save Pin Plan Screenshot
            pin_plan_path = os.path.join(SCREENSHOT_DIR, "map-pin-plan.png")
            page.screenshot(path=pin_plan_path)
            print(f"Saved {pin_plan_path}")

            page.wait_for_timeout(1000) # Hold final state for video

            print("Browser test completed successfully!")

        finally:
            context.close()
            browser.close()

if __name__ == "__main__":
    run_tests()
