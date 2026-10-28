import assert from 'node:assert/strict';
const base = process.argv[2];
assert.ok(base, 'Usage: node tests/pwa/nginx-headers.mjs http://127.0.0.1:18083');
const assets = {
    '/manifest.webmanifest': 'application/manifest+json',
    '/sw.js': 'application/javascript',
    '/offline.html': 'text/html',
    '/icons/icon-192.png': 'image/png',
    '/icons/icon-512.png': 'image/png',
    '/icons/apple-touch-icon.png': 'image/png',
    '/icons/pwa-icon.svg': 'image/svg+xml',
};
for (const [path, mime] of Object.entries(assets)) {
    const response = await fetch(new URL(path, base));
    assert.equal(response.status, 200, path);
    assert.equal(response.headers.get('content-type').split(';')[0], mime, path);
    assert.equal(response.headers.get('cache-control'), 'no-cache, must-revalidate', path);
    assert.equal(response.headers.get('x-content-type-options'), 'nosniff', path);
    assert.equal(response.headers.get('x-frame-options'), 'SAMEORIGIN', path);
    const etag = response.headers.get('etag');
    assert.ok(etag, `${path}: ETag`);
    await response.arrayBuffer();
    const revalidated = await fetch(new URL(path, base), { headers: { 'If-None-Match': etag } });
    assert.equal(revalidated.status, 304, `${path}: revalidation`);
    assert.equal(revalidated.headers.get('cache-control'), 'no-cache, must-revalidate', path);
    console.log(`${path}: 200 ${mime}; no-cache, must-revalidate; ETag -> 304; security headers retained`);
}
