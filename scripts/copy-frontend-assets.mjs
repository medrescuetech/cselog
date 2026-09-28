import { cp, mkdir } from 'node:fs/promises';

const assets = [
  ['node_modules/alpinejs/dist/cdn.min.js', 'public/vendor/alpinejs/alpine.min.js'],
  ['node_modules/leaflet/dist/leaflet.js', 'public/vendor/leaflet/leaflet.js'],
  ['node_modules/leaflet/dist/leaflet.css', 'public/vendor/leaflet/leaflet.css'],
  ['node_modules/leaflet/dist/images', 'public/vendor/leaflet/images'],
];

for (const [source, destination] of assets) {
  const slash = destination.lastIndexOf('/');
  await mkdir(destination.slice(0, slash), { recursive: true });
  await cp(source, destination, { recursive: true });
}
