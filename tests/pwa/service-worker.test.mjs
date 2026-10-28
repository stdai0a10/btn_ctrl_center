import assert from 'node:assert/strict';
import { readFileSync, existsSync } from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';

function worker(fetch = async () => { throw new TypeError('offline'); }) {
    const listeners = {};
    const stores = new Map([['unrelated-cache', new Map()], ['bcc-offline-v0', new Map()]]);
    const caches = {
        async open(name) {
            if (!stores.has(name)) stores.set(name, new Map());
            const store = stores.get(name);
            return {
                async add(url) { store.set(url, new Response('offline document')); },
                async match(url) { return store.get(url)?.clone(); },
            };
        },
        async keys() { return [...stores.keys()]; },
        async delete(name) { return stores.delete(name); },
    };
    const self = { location: new URL('https://example.test/sw.js'), addEventListener: (name, fn) => { listeners[name] = fn; } };
    vm.runInNewContext(existsSync('public/sw.js') ? readFileSync('public/sw.js', 'utf8') : '', { self, caches, fetch, URL });
    async function lifecycle(name) {
        assert.equal(typeof listeners[name], 'function', `${name} handler exists`);
        let pending;
        listeners[name]({ waitUntil(promise) { pending = promise; } });
        await pending;
    }
    function request(path, options = {}) {
        let response;
        assert.equal(typeof listeners.fetch, 'function', 'fetch handler exists');
        listeners.fetch({ request: { url: `https://example.test${path}`, method: 'GET', mode: 'navigate', headers: new Headers(), ...options }, respondWith(promise) { response = promise; } });
        return response;
    }
    return { lifecycle, request, stores };
}

test('failed user document navigation gets the self-contained offline page', async () => {
    const sw = worker();
    await sw.lifecycle('install');
    assert.equal(await (await sw.request('/buttons')).text(), 'offline document');
});

for (const [path, options] of [
    ['/api/buttons', {}], ['/api', {}], ['/manage', {}], ['/manage/users', {}],
    ['/reauth', {}], ['/forgot-password', {}], ['/reset-password', {}], ['/verify-email/result', {}], ['/login', {}], ['/register', {}], ['/logout', {}], ['/auth/callback', {}],
    ['/buttons', { headers: new Headers({ 'X-Inertia': 'true' }) }],
    ['/buttons', { mode: 'cors' }],
    ...['POST', 'PUT', 'PATCH', 'DELETE'].map(method => ['/buttons', { method }]),
]) {
    test(`passes through ${options.method || 'GET'} ${path} ${options.mode || ''} ${options.headers ? 'Inertia' : ''}`, () => {
        assert.equal(worker().request(path, options), undefined);
    });
}
for (const status of [200, 401, 403, 404, 500]) {
    test(`preserves HTTP ${status} without caching the document`, async () => {
        const sw = worker(async () => new Response('original', { status }));
        await sw.lifecycle('install');
        const response = await sw.request('/buttons');
        assert.equal(response.status, status);
        assert.equal(await response.text(), 'original');
        assert.deepEqual([...sw.stores.values()].flatMap(store => [...store.keys()]), ['/offline.html']);
    });
}
test('activation removes only obsolete owned caches', async () => {
    const sw = worker();
    await sw.lifecycle('install');
    await sw.lifecycle('activate');
    assert.equal(sw.stores.has('unrelated-cache'), true);
    assert.equal(sw.stores.has('bcc-offline-v0'), false);
    assert.equal([...sw.stores.values()].some(store => store.has('/offline.html')), true);
});
