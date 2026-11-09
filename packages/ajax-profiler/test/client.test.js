import { test } from 'node:test';
import assert from 'node:assert/strict';
import { install } from '../index.js';

const ID = 'a'.repeat(32);
function environment(fetch = async () => new Response('body', { headers: { 'X-SlimTracy-Profile': ID } })) {
    class XHR extends EventTarget {
        status = 0;
        response = null;
        readyState = 0;
        open(...args) { this.openArguments = args; this.status = 0; this.readyState = 1; }
        send(...args) { this.sendArguments = args; }
        getResponseHeader() { return this.profileId || null; }
        complete(status = 200, event = 'load') {
            this.status = status;
            this.readyState = 4;
            this.dispatchEvent(new Event(event));
            this.dispatchEvent(new Event('loadend'));
        }
        abort() { this.complete(0, 'abort'); }
    }
    let time = 10;
    return { fetch, XMLHttpRequest: XHR, AbortController, location: { href: 'https://app.test/page?secret=page' },
        performance: { now: () => time += 5 } };
}

test('SSR import/install and explicit disabled mode have no side effects', () => {
    assert.equal(install().active, false);
    const target = environment();
    const original = target.fetch;
    const open = target.XMLHttpRequest.prototype.open;
    assert.equal(install({ target, enabled: false }).active, false);
    assert.equal(target.fetch, original);
    assert.equal(target.XMLHttpRequest.prototype.open, open);
});

test('fetch preserves Request, options, body stream, response, credentials and receiver', async () => {
    const calls = [];
    const response = new Response('stream body', { status: 201, headers: { 'X-SlimTracy-Profile': ID } });
    const target = environment(function (...args) { calls.push({ self: this, args }); return Promise.resolve(response); });
    const original = target.fetch;
    const client = install({ target });
    const request = new Request('https://app.test/api?token=SECRET', { method: 'POST', body: 'BODY_SECRET',
        credentials: 'include', headers: { Authorization: 'AUTH_SECRET' } });
    const options = { credentials: 'same-origin' };
    const returned = await target.fetch(request, options);
    assert.equal(returned, response);
    assert.equal(request.bodyUsed, false);
    assert.equal(calls[0].args[0], request);
    assert.equal(calls[0].args[1], options);
    assert.equal(calls[0].self, target);
    assert.equal(await returned.text(), 'stream body');
    const entry = client.getEntries()[0];
    assert.equal(entry.path, '/api');
    assert.equal(entry.method, 'POST');
    assert.equal(entry.durationMs, 5);
    assert.equal(entry.status, 201);
    assert.equal(entry.id, ID);
    assert.equal(entry.source, 'browser');
    assert.equal(Object.isFrozen(entry), true);
    assert.equal(calls.length, 2);
    assert.equal(calls[1].args[0], 'https://app.test/_tracy/api/browser');
    assert.equal(calls[1].args[1].credentials, 'omit');
    assert.deepEqual(calls[1].args[1].headers, { 'Content-Type': 'application/json' });
    const serialized = JSON.stringify(entry) + calls[1].args[1].body;
    for (const secret of ['SECRET', 'Authorization', 'X-Tracy-Ajax', 'cookie']) assert.ok(!serialized.includes(secret));
    client.stop();
    assert.equal(target.fetch, original);
});

test('exact origins, empty allowlist, debug paths, Tracy assets and no-cors are excluded', async () => {
    const target = environment();
    const client = install({ target, allowedOrigins: ['https://api.test'], telemetry: false });
    for (const url of ['https://api.test.evil/api', 'http://api.test/api', 'https://api.test:444/api',
        'https://app.test/api', 'https://api.test/x?_tracy_bar=js']) await target.fetch(url);
    await target.fetch('https://api.test/api?secret=1', { mode: 'no-cors' });
    await target.fetch(new Request('https://api.test/api', { mode: 'no-cors' }));
    assert.equal(client.getEntries().length, 0);
    await target.fetch(new URL('https://api.test/api?secret=1#secret'));
    assert.equal(client.getEntries().length, 1);
    client.stop();
    const empty = install({ target, allowedOrigins: [] });
    await target.fetch('/api');
    assert.equal(empty.getEntries().length, 0);
    empty.stop();
    const same = install({ target });
    await target.fetch('/_tracy/api');
    await target.fetch('/_tracy/api/profiles?after=x');
    await target.fetch('/_tracy/api/browser');
    assert.equal(same.getEntries().length, 0);
    await target.fetch('/_tracy/apianother');
    assert.equal(same.getEntries().length, 1);
    same.stop();
});

test('invalid origins rejected, telemetry base cannot carry secrets', () => {
    for (const origin of ['*', 'https://api.test/', 'https://api.test/path', 'null', 'https://user:secret@api.test']) {
        assert.throws(() => install({ target: environment(), allowedOrigins: [origin] }), TypeError);
    }
    for (const baseUrl of ['https://user:secret@app.test/debug', '/debug?token=SECRET', '/debug#SECRET', 'data:abc']) {
        assert.throws(() => install({ target: environment(), baseUrl }), TypeError);
    }
});

test('network errors, HTTP errors and aborts preserve exact failures without leaking error messages', async () => {
    const failure = new TypeError('secret request URL');
    const abort = new DOMException('secret abort reason', 'AbortError');
    const target = environment(async (url) => { throw url.includes('abort') ? abort : failure; });
    const client = install({ target });
    await assert.rejects(target.fetch('/failure?token=SECRET'), (error) => error === failure);
    await assert.rejects(target.fetch('/abort'), (error) => error === abort);
    assert.deepEqual(client.getEntries().map((entry) => entry.outcome), ['network-error', 'aborted']);
    assert.ok(client.getEntries().every((entry) => entry.status === null && entry.id === null));
    assert.ok(!JSON.stringify(client.getEntries()).includes('secret'));
    client.stop();
    target.fetch = async () => new Response('error JSON', { status: 500 });
    const http = install({ target });
    assert.equal((await target.fetch('/failure')).status, 500);
    assert.equal(http.getEntries()[0].outcome, 'complete');
    http.stop();
});

test('synchronous fetch failure remains synchronous and records local failure', () => {
    const failure = new Error('failure');
    const target = environment(() => { throw failure; });
    const client = install({ target });
    assert.throws(() => target.fetch('/api'), (error) => error === failure);
    assert.equal(client.getEntries()[0].outcome, 'network-error');
    client.stop();
});

test('idempotent install, HMR stop/reinstall, bounded history and observer isolation', async () => {
    const target = environment();
    const original = target.fetch;
    const client = install({ target, telemetry: false, maxEntries: 2 });
    const wrapper = target.fetch;
    assert.equal(install({ target }), client);
    assert.equal(target.fetch, wrapper);
    let events = 0;
    const unsubscribe = client.subscribe(() => { events++; });
    client.subscribe(() => { throw new Error('observer'); });
    for (let i = 0; i < 3; i++) await target.fetch('/api');
    assert.equal(events, 3);
    assert.equal(client.getEntries().length, 2);
    const copy = client.getEntries();
    copy.length = 0;
    assert.equal(client.getEntries().length, 2);
    unsubscribe();
    client.stop();
    client.stop();
    assert.equal(target.fetch, original);
    const next = install({ target, telemetry: false });
    assert.notEqual(next, client);
    await target.fetch('/api');
    assert.equal(next.getEntries().length, 1);
    assert.equal(events, 3);
    next.stop();
});

test('stop leaves later third-party wrappers intact; stale wrapper is inert', async () => {
    const target = environment();
    const client = install({ target });
    const wrapper = target.fetch;
    const external = (...args) => wrapper.apply(target, args);
    target.fetch = external;
    client.stop();
    assert.equal(target.fetch, external);
    await target.fetch('/api');
    assert.equal(client.getEntries().length, 0);
});

test('concurrent fetch results correlate separately and pending requests are inert after stop', async () => {
    const resolves = [];
    const target = environment(() => new Promise((resolve) => resolves.push(resolve)));
    const client = install({ target, telemetry: false });
    const a = target.fetch('/a');
    const b = target.fetch('/b');
    resolves[1](new Response('', { headers: { 'X-SlimTracy-Profile': 'b'.repeat(32) } }));
    await b;
    resolves[0](new Response('', { headers: { 'X-SlimTracy-Profile': ID } }));
    await a;
    assert.deepEqual(client.getEntries().map((entry) => [entry.path, entry.id]), [['/b', 'b'.repeat(32)], ['/a', ID]]);
    const c = target.fetch('/c');
    client.stop();
    resolves[2](new Response(''));
    await c;
    assert.equal(client.getEntries().length, 2);
});

test('telemetry failures cannot reject successful requests, invalid header IDs not submitted', async () => {
    for (const fail of [() => { throw new Error('telemetry'); }, () => Promise.reject(new Error('telemetry'))]) {
        const target = environment((url) => url.endsWith('/browser') ? fail()
            : Promise.resolve(new Response('', { headers: { 'X-SlimTracy-Profile': ID } })));
        const client = install({ target });
        assert.equal((await target.fetch('/api')).status, 200);
        client.stop();
    }
    let calls = 0;
    const target = environment(async () => { calls++; return new Response('', {
        headers: { 'X-SlimTracy-Profile': 'path/secret?x=1' } }); });
    const client = install({ target });
    await target.fetch('/api');
    assert.equal(calls, 1);
    assert.equal(client.getEntries()[0].id, null);
    client.stop();
});

test('XHR preserves open/send arguments, events, credentials, re-use, abort and errors', () => {
    const target = environment();
    const originalOpen = target.XMLHttpRequest.prototype.open;
    const originalSend = target.XMLHttpRequest.prototype.send;
    const client = install({ target, telemetry: false });
    const xhr = new target.XMLHttpRequest();
    xhr.withCredentials = true;
    const body = { secret: 'BODY_SECRET' };
    xhr.open('POST', '/api?token=SECRET', true, 'user', 'password');
    xhr.send(body);
    assert.deepEqual(xhr.openArguments, ['POST', '/api?token=SECRET', true, 'user', 'password']);
    assert.equal(xhr.sendArguments[0], body);
    assert.equal(xhr.withCredentials, true);
    let loadend = 0;
    xhr.addEventListener('loadend', () => loadend++);
    xhr.profileId = ID;
    xhr.complete(201);
    xhr.open('GET', '/abort');
    xhr.send();
    xhr.profileId = null;
    xhr.abort();
    xhr.open('GET', '/error');
    xhr.send();
    xhr.complete(0, 'error');
    xhr.open('GET', '/timeout');
    xhr.send();
    xhr.complete(0, 'timeout');
    assert.equal(loadend, 4);
    assert.deepEqual(client.getEntries().map((entry) => entry.outcome), ['complete', 'aborted', 'network-error', 'network-error']);
    assert.equal(client.getEntries()[0].path, '/api');
    assert.ok(!JSON.stringify(client.getEntries()).includes('SECRET'));
    client.stop();
    assert.equal(target.XMLHttpRequest.prototype.open, originalOpen);
    assert.equal(target.XMLHttpRequest.prototype.send, originalSend);
});

test('XHR exclusion and stop removes pending event listeners without aborting application request', () => {
    const target = environment();
    const client = install({ target });
    const xhr = new target.XMLHttpRequest();
    xhr.open('GET', 'https://third.test/api'); xhr.send(); xhr.complete();
    xhr.open('GET', '/_tracy/api/profiles'); xhr.send(); xhr.complete();
    assert.equal(client.getEntries().length, 0);
    xhr.open('GET', '/api'); xhr.send();
    client.stop();
    xhr.complete();
    assert.equal(client.getEntries().length, 0);
});

test('XHR reuse inside earlier loadend or load listeners retains both request entries', () => {
    for (const eventName of ['loadend', 'load']) {
        const target = environment();
        const client = install({ target, telemetry: false });
        const xhr = new target.XMLHttpRequest();
        let repeated = false;
        xhr.addEventListener(eventName, () => {
            if (repeated) return;
            repeated = true;
            xhr.open('POST', '/second');
            xhr.send('second body');
        });
        xhr.open('GET', '/first');
        xhr.send();
        xhr.complete(200);
        xhr.complete(202);
        assert.deepEqual(client.getEntries().map((entry) => [entry.path, entry.status]),
            [['/first', 200], ['/second', 202]]);
        client.stop();
    }
});

test('reopening an in-flight XHR records local abort without adding application aborts', () => {
    const target = environment();
    const client = install({ target, telemetry: false });
    const xhr = new target.XMLHttpRequest();
    xhr.open('GET', '/old'); xhr.send();
    xhr.open('GET', '/new'); xhr.send(); xhr.complete();
    assert.deepEqual(client.getEntries().map((entry) => [entry.path, entry.outcome]),
        [['/old', 'aborted'], ['/new', 'complete']]);
    client.stop();
});

test('read-only server lookup uses unwrapped fetch, validates IDs/schema and never records another event', async () => {
    const calls = [];
    const profile = { schemaVersion: 1, id: ID, collectors: { doctrine: { available: true, queries: [] } } };
    const target = environment(async (...args) => { calls.push(args); return Response.json(profile); });
    const client = install({ target });
    assert.equal(await client.getProfile('../secret'), null);
    assert.equal(calls.length, 0);
    assert.deepEqual(await client.getProfile(ID), profile);
    assert.equal(calls.length, 1);
    assert.equal(calls[0][0], 'https://app.test/_tracy/api/profiles/' + ID);
    assert.equal(calls[0][1].credentials, 'omit');
    assert.equal(calls[0][1].cache, 'no-store');
    assert.equal(client.getEntries().length, 0);
    profile.id = 'b'.repeat(32);
    assert.equal(await client.getProfile(ID), null);
    client.stop();
    assert.equal(await client.getProfile(ID), null);
    assert.equal(calls.length, 2);
});

test('stop aborts read-only server lookup and prevents late profile publication', async () => {
    let resolve;
    let signal;
    const target = environment((_url, options) => {
        signal = options.signal;
        return new Promise((done) => { resolve = done; });
    });
    const client = install({ target });
    const pending = client.getProfile(ID);
    client.stop();
    assert.equal(signal.aborted, true);
    resolve(Response.json({ schemaVersion: 1, id: ID }));
    assert.equal(await pending, null);
});
