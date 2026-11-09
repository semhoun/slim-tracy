import { test, expect } from '@playwright/test';

const api = 'http://127.0.0.1:8781';
const frontend = 'http://127.0.0.1:8782';
const base = '/_tracy/api';

test('separate frontend CSP permits configured API calls and blocks other connect destinations', async ({ page }) => {
  await page.goto(frontend);
  const result = await page.evaluate(async (api) => {
    const allowed = await (await fetch(`${api}/echo`)).json();
    const violation = new Promise((resolve) => document.addEventListener('securitypolicyviolation',
      (event) => resolve(event.effectiveDirective), { once: true }));
    let errorName;
    try { await fetch('http://localhost:8781/echo'); } catch (error) { errorName = error.name; }
    return { method: allowed.method, errorName, directive: await violation };
  }, api);
  expect(result).toEqual({ method: 'GET', errorName: 'TypeError', directive: 'connect-src' });
});

test('HTTP profiles preserve JSON, Slim errors, host CORS and shared unauthenticated history', async ({ request }) => {
  const response = await request.post(`${api}/echo?secret=never-store-this`, {
    data: 'application body', headers: { Origin: frontend, Authorization: 'Bearer not-a-profile-field' },
  });
  expect(response.status()).toBe(200);
  expect(await response.json()).toEqual({ method: 'POST', body: 'application body', nativeHeader: '', authorization: 'Bearer not-a-profile-field' });
  expect(response.headers()['x-host-cors']).toBe('preserved');
  expect(response.headers()['access-control-allow-origin']).toBe(frontend);
  const id = response.headers()['x-slimtracy-profile'];
  expect(id).toBeTruthy();
  const detail = await request.get(`${api}${base}/profiles/${id}`);
  expect(detail.status()).toBe(200);
  expect(detail.headers()['cache-control']).toContain('no-store');
  const profile = await detail.json();
  expect(profile.id).toBe(id);
  expect(JSON.stringify(profile)).not.toMatch(/never-store-this|application body|not-a-profile-field/);
  const error = await request.get(`${api}/error`, { headers: { Accept: 'application/json' } });
  expect(error.status()).toBe(500);
  expect(await error.json()).toEqual({ error: 'Slim handled' });
  expect(error.headers()['x-slimtracy-profile']).toBeTruthy();
  const history = await (await request.get(`${api}${base}/profiles`)).json();
  expect(history.profiles.some((entry) => entry.id === id)).toBe(true);
});

test('disabled and production modes cannot access residual enabled profiles or native debug dispatch', async ({ request }) => {
  const active = await request.get(`${api}/echo`);
  const id = active.headers()['x-slimtracy-profile'];
  for (const port of [8783, 8784]) {
    const origin = `http://127.0.0.1:${port}`;
    const echo = await request.get(`${origin}/echo`);
    expect(echo.headers()['x-slimtracy-profile']).toBeUndefined();
    expect(await echo.json()).toMatchObject({ nativeHeader: '' });
    for (const path of [base, `${base}/profiles`, `${base}/profiles/${id}`, `${base}?_tracy_bar=js`, `${base}?_tracy_bar=content-ajax`, '/%5ftracy/api?_tracy_bar=content.private']) {
      const response = await request.get(`${origin}${path}`);
      expect(response.status(), `${origin}${path}`).toBe(404);
      expect(await response.text()).not.toContain(id);
    }
    expect((await request.post(`${origin}${base}/browser`, { data: { id, durationMs: 1 }, headers: { Origin: frontend } })).status()).toBe(404);
  }
});

test('profiler CORS accepts exact origins only and rejects untrusted browser writes', async ({ request }) => {
  const allowed = await request.get(`${api}${base}/profiles`, { headers: { Origin: frontend } });
  expect(allowed.headers()['access-control-allow-origin']).toBe(frontend);
  expect(allowed.headers().vary).toContain('Origin');
  const rejected = await request.post(`${api}${base}/browser`, {
    headers: { Origin: 'http://127.0.0.1:8782.attacker.invalid' }, data: { id: 'invalid', durationMs: 2 },
  });
  expect(rejected.status()).toBe(403);
  expect(rejected.headers()['access-control-allow-origin']).toBeUndefined();
  const preflight = await request.fetch(`${api}${base}/browser`, {
    method: 'OPTIONS', headers: { Origin: frontend, 'Access-Control-Request-Method': 'POST', 'Access-Control-Request-Headers': 'content-type' },
  });
  expect(preflight.status()).toBe(204);
  expect(preflight.headers()['x-slimtracy-profile']).toBeUndefined();
});

test('real cross-origin fetch, Request, POST, XHR and concurrent responses are not modified', async ({ page, request }) => {
  const requests = [];
  page.on('request', (req) => requests.push(req));
  await page.goto(frontend);
  await page.evaluate((api) => {
    window.fixtureClient = SlimTracyAjaxProfiler.install({ baseUrl: `${api}/_tracy/api`, allowedOrigins: [api] });
  }, api);
  const results = await page.evaluate(async (api) => {
    const request = new Request(`${api}/echo?private=query`, { method: 'POST', body: 'request payload' });
    const posted = await fetch(request);
    const urlResponse = await fetch(new URL(`${api}/echo`));
    const xhr = await new Promise((resolve, reject) => {
      const xhr = new XMLHttpRequest();
      xhr.open('POST', `${api}/echo`);
      xhr.onload = () => resolve({ body: JSON.parse(xhr.responseText), id: xhr.getResponseHeader('X-SlimTracy-Profile') });
      xhr.onerror = reject;
      xhr.send('xhr payload');
    });
    const concurrent = await Promise.all(Array.from({ length: 3 }, async () => {
      const response = await fetch(`${api}/echo`);
      return response.headers.get('X-SlimTracy-Profile');
    }));
    return { posted: await posted.json(), url: await urlResponse.json(), xhr, concurrent, cookies: document.cookie };
  }, api);
  expect(results.posted).toMatchObject({ method: 'POST', body: 'request payload', nativeHeader: '' });
  expect(results.url).toMatchObject({ method: 'GET', nativeHeader: '' });
  expect(results.xhr.body).toMatchObject({ method: 'POST', body: 'xhr payload', nativeHeader: '' });
  expect(new Set(results.concurrent).size).toBe(3);
  expect(results.cookies).toBe('');
  await expect.poll(async () => {
    const profile = await (await request.get(`${api}${base}/profiles/${results.xhr.id}`)).json();
    return profile.browser?.source;
  }).toBe('client');
  for (const req of requests) expect(req.headers()['x-tracy-ajax']).toBeUndefined();
});

test('fetch and XHR aborts and network failures keep native browser semantics', async ({ page }) => {
  await page.goto(frontend);
  const result = await page.evaluate(async (api) => {
    SlimTracyAjaxProfiler.install({ baseUrl: `${api}/_tracy/api`, allowedOrigins: [api, 'http://127.0.0.1:1'] });
    const controller = new AbortController();
    controller.abort();
    let abortName;
    try { await fetch(`${api}/slow`, { signal: controller.signal }); } catch (error) { abortName = error.name; }
    let networkName;
    try { await fetch('http://127.0.0.1:1/unreachable'); } catch (error) { networkName = error.name; }
    const xhrAborted = await new Promise((resolve) => {
      const xhr = new XMLHttpRequest();
      xhr.open('GET', `${api}/slow`);
      xhr.onabort = () => resolve(true);
      xhr.send();
      xhr.abort();
    });
    return { abortName, networkName, xhrAborted };
  }, api);
  expect(result).toEqual({ abortName: 'AbortError', networkName: 'TypeError', xhrAborted: true });
});

test('real Twig Tracy boot disables native capture, including when new profiling is disabled', async ({ page }) => {
  for (const [port, path] of [[8781, '/twig'], [8781, '/loader'], [8783, '/twig'], [8783, '/loader']]) {
    await page.goto(`http://127.0.0.1:${port}${path}`);
    await expect(page.locator('#tracy-debug')).toBeAttached();
    await expect.poll(() => page.evaluate(() => typeof window.Tracy?.Debug?.captureAjax)).toBe('function');
    const state = await page.evaluate(async () => {
      const before = window.fetch;
      const xhrBefore = XMLHttpRequest.prototype.open;
      Tracy.Debug.captureAjax();
      const body = await (await fetch('/echo')).json();
      return { unchanged: window.fetch === before && XMLHttpRequest.prototype.open === xhrBefore, nativeHeader: body.nativeHeader };
    });
    expect(state).toEqual({ unchanged: true, nativeHeader: '' });
    expect(await page.content()).toContain('SECRET_HOST_PANEL');
    if (port === 8783) {
      await expect(page.locator('[data-slim-tracy-api]')).toHaveCount(0);
      expect(await page.evaluate(() => typeof window.SlimTracyAjaxProfiler)).toBe('undefined');
    }
  }
});

test('direct debug page is unauthenticated and only exposes the API panel', async ({ page }) => {
  await page.goto(`${api}${base}`);
  await expect(page.locator('#tracy-debug')).toBeAttached();
  await page.locator('[data-slim-tracy-client]').click();
  await expect(page.locator('[data-slim-tracy-api]')).toBeAttached();
  const html = await page.content();
  expect(html).not.toMatch(/<title>.*phpinfo/i);
  expect(html).not.toContain('tracy-debug-panel-SlimTracy-Panels-Console');
  expect(html).not.toContain('tracy-debug-panel-SlimTracy-Panels-PhpInfo');
  expect(html).not.toContain('SECRET_HOST_PANEL');
  expect(await page.locator('.tracy-panel-main').count()).toBe(1);
  const blocked = await page.request.get(`${api}${base}?_tracy_bar=content-ajax`);
  expect(blocked.status()).toBe(404);
});

test('mounted React and Vue applications share the client; Vue exposes timeline and inspector callbacks', async ({ page }) => {
  await page.goto(`${frontend}/react`);
  await page.getByRole('button', { name: 'React Ready' }).click();
  await expect(page.getByRole('button', { name: 'React GET' })).toBeVisible();
  expect(await page.evaluate(() => fixtureClient.getEntries().length)).toBe(1);
  expect(await page.evaluate(() => fixtureDevtools.layers.length)).toBe(0);
  await page.goto(`${frontend}/vue`);
  await page.getByRole('button', { name: 'Vue Ready' }).click();
  await expect(page.getByRole('button', { name: 'Vue GET' })).toBeVisible();
  const devtools = await page.evaluate(async () => {
    const state = fixtureDevtools;
    const payload = { app: state.app, inspectorId: state.inspectors[0].id, filter: '' };
    state.callbacks.tree(payload);
    const detail = { app: state.app, inspectorId: state.inspectors[0].id, nodeId: payload.rootNodes[0].id };
    await state.callbacks.state(detail);
    return { layers: state.layers.length, events: state.events.length, nodes: payload.rootNodes.length, detail: detail.state };
  });
  expect(devtools).toMatchObject({ layers: 1, events: 1, nodes: 1 });
  expect(devtools.detail['Client-Reported Request']).toBeTruthy();
  expect(devtools.detail['Server Profile']).toBeTruthy();
  await page.evaluate(() => fixtureStop());
  await page.goto(`${frontend}/vue?disabled`);
  await page.getByRole('button', { name: 'Vue Ready' }).click();
  await expect(page.getByRole('button', { name: 'Vue GET' })).toBeVisible();
  expect(await page.evaluate(() => fixtureDevtools.layers.length)).toBe(0);
  expect(await page.evaluate(() => fixtureClient.getEntries().length)).toBe(0);
});

test('same-cookie API concurrency avoids native Tracy session serialization', async ({ page, context }) => {
  await page.goto(`${api}/loader`);
  await expect(page.locator('#tracy-debug')).toBeAttached();
  const cookies = await context.cookies(api);
  const tracyCookie = cookies.find((cookie) => cookie.name === 'tracy-session');
  expect(tracyCookie?.value).toMatch(/^\w{10}$/);
  await page.goto(frontend);
  const result = await page.evaluate(async (origins) => {
    const start = performance.now();
    // Independent listeners avoid relying on PHP prefork or a single browser connection pool.
    const responses = await Promise.all(origins.map((origin) => fetch(`${origin}/slow?request=${crypto.randomUUID()}`, {
      method: 'GET', credentials: 'include', cache: 'no-store',
    })));
    return {
      elapsed: performance.now() - start,
      ids: responses.map((response) => response.headers.get('X-SlimTracy-Profile')),
      handlers: await Promise.all(responses.map((response) => response.json())),
    };
  }, [api, 'http://127.0.0.1:8785']);
  expect(result.ids).toHaveLength(2);
  expect(result.ids.every(Boolean)).toBe(true);
  expect(new Set(result.ids).size).toBe(2);
  expect(result.handlers).toHaveLength(2);
  expect(result.handlers.every((handler) => handler.hasTracyCookie)).toBe(true);
  expect(new Set(result.handlers.map((handler) => handler.processId)).size).toBe(2);
  expect((await context.cookies(api)).find((cookie) => cookie.name === 'tracy-session')?.value).toBe(tracyCookie.value);
  // Compare actual server-handler intervals, not a fragile CI wall-clock performance budget.
  const overlaps = result.handlers.some((handler, index) => result.handlers.some((other, otherIndex) =>
    index !== otherIndex && handler.started < other.finished && other.started < handler.finished));
  expect(overlaps, JSON.stringify(result)).toBe(true);
});

test('real client installation, exclusions, stop and reentrant XHR reuse do not duplicate interception', async ({ page }) => {
  await page.goto(frontend);
  const result = await page.evaluate(async (api) => {
    const originalFetch = window.fetch;
    const originalOpen = XMLHttpRequest.prototype.open;
    const options = { baseUrl: `${api}/_tracy/api`, allowedOrigins: [api] };
    const client = SlimTracyAjaxProfiler.install(options);
    const same = SlimTracyAjaxProfiler.install(options) === client;
    await fetch('/health'); // Origin outside the exact destination allowlist.
    await fetch(`${api}/_tracy/api/profiles`); // Profiler internal request.
    const excludedCount = client.getEntries().length;
    await new Promise((resolve, reject) => {
      const xhr = new XMLHttpRequest();
      let count = 0;
      xhr.onloadend = () => {
        count += 1;
        if (count === 2) return resolve();
        xhr.open('GET', `${api}/echo`);
        xhr.send();
      };
      xhr.onerror = reject;
      xhr.open('GET', `${api}/echo`);
      xhr.send();
    });
    await new Promise((resolve) => setTimeout(resolve, 0));
    const entries = client.getEntries();
    client.stop();
    const restored = window.fetch === originalFetch && XMLHttpRequest.prototype.open === originalOpen;
    const restarted = SlimTracyAjaxProfiler.install(options);
    await fetch(`${api}/echo`);
    const restartedCount = restarted.getEntries().length;
    restarted.stop();
    return { same, excludedCount, count: entries.length, ids: entries.map((entry) => entry.id), restored, restartedCount };
  }, api);
  expect(result).toMatchObject({ same: true, excludedCount: 0, count: 2, restored: true, restartedCount: 1 });
  expect(new Set(result.ids).size).toBe(2);
});

test('API panel polls incrementally only while open and renders untrusted details as text', async ({ page, request }) => {
  const response = await request.get(`${api}/untrusted`);
  const id = response.headers()['x-slimtracy-profile'];
  const malicious = '<img src=x onerror="window.fixtureXss=true">';
  expect((await request.post(`${api}${base}/browser`, { headers: { Origin: frontend }, data: { id, durationMs: 1, path: malicious } })).status()).toBe(202);
  const polls = [];
  page.on('request', (request) => { if (new URL(request.url()).pathname === `${base}/profiles`) polls.push(request.url()); });
  await page.goto(`${api}${base}`);
  await expect(page.locator('[data-slim-tracy-client]')).toBeAttached();
  expect(polls).toHaveLength(0);
  await page.locator('[data-slim-tracy-client]').click();
  const panel = page.locator('[data-slim-tracy-api]');
  await panel.getByRole('button', { name: /GET \/untrusted/ }).click();
  await expect.poll(async () => JSON.parse(await panel.locator('pre').textContent()).browser?.path).toBe(malicious);
  expect(await panel.locator('pre img').count()).toBe(0);
  expect(await page.evaluate(() => window.fixtureXss)).toBeUndefined();
  await expect.poll(() => polls.some((url) => new URL(url).searchParams.has('after')), { timeout: 6000 }).toBe(true);
  await page.locator('#tracy-debug-panel-SlimTracy-Helpers-ApiPanel [data-tracy-action="close"]').click();
  const count = polls.length;
  await page.waitForTimeout(2200); // Cross one actual panel polling period while hidden.
  expect(polls).toHaveLength(count);
});
