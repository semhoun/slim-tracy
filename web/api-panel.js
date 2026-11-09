/* Shared Tracy browser panel source; web/api-panel.js is generated from this file. */
(function (root, factory) {
    if (typeof module === 'object' && module.exports) module.exports = factory();
    else {
        root.SlimTracyApiPanel = factory();
        root.SlimTracyApiPanel.start(root);
    }
}(typeof globalThis === 'object' ? globalThis : this, function () {
    'use strict';
    var key = Symbol.for('slim-tracy.api-panel.v1');

    function configure(root, target) {
        if (root.dataset.clientEnabled !== 'true' || !target.SlimTracyAjaxProfiler) return null;
        var origins = root.dataset.allowedOrigins ? JSON.parse(root.dataset.allowedOrigins) : [];
        return target.SlimTracyAjaxProfiler.install({ baseUrl: root.dataset.baseUrl || '/_tracy/api',
            allowedOrigins: origins.length ? origins : undefined });
    }

    function mount(root, target, configureClient) {
        var document = target.document;
        var base = new URL(root.dataset.baseUrl || '/_tracy/api', target.location.href);
        base.pathname = base.pathname.replace(/\/+$/, '');
        var client = configureClient(root);
        var stopped = false;
        var timer = null;
        var inflight = null;
        var detailController = null;
        var cursor = null;
        var selected = null;
        var entries = new Map();
        function element(tag, text) {
            var node = document.createElement(tag);
            if (text !== undefined) node.textContent = text;
            return node;
        }
        var heading = element('h1', 'API Profiles');
        var warning = element('p', 'Shared development history. Browser timings are client-reported.');
        var status = element('p', 'Waiting for active view.');
        status.setAttribute('role', 'status');
        var list = element('ol');
        var details = element('pre', 'Select a request to inspect server collectors and browser timings.');
        root.replaceChildren(heading, warning, status, list, details);

        function visible() {
            return !stopped && root.isConnected && !document.hidden && root.getClientRects().length > 0
                && target.getComputedStyle(root).visibility !== 'hidden';
        }
        function entryKey(entry) { return entry.id || 'local-' + entry.localId; }
        function add(entry) {
            if (!entry || entry.schemaVersion !== 1 || typeof entry.path !== 'string') return;
            var id = entryKey(entry);
            var previous = entries.get(id);
            // A browser event never replaces trusted server detail; keep timing separate.
            if (previous && entry.source === 'browser') entries.set(id, Object.assign({}, previous, { browser: entry }));
            else entries.set(id, Object.assign({}, entry, previous && previous.source === 'browser'
                ? { browser: previous } : previous && previous.browser ? { browser: previous.browser } : {}));
            while (entries.size > 100) entries.delete(entries.keys().next().value);
        }
        function render() {
            var fragment = document.createDocumentFragment();
            Array.from(entries.entries()).reverse().forEach(function (pair) {
                var id = pair[0];
                var entry = pair[1];
                var button = element('button', entry.method + ' ' + entry.path + ' | '
                    + (entry.status === null ? 'no response' : entry.status) + ' | '
                    + Number(entry.durationMs).toFixed(1) + ' ms '
                    + (entry.source === 'browser' ? '(browser)' : '(server)'));
                button.type = 'button';
                button.setAttribute('aria-pressed', String(selected === id));
                button.addEventListener('click', function () { select(id); });
                var item = element('li');
                item.appendChild(button);
                fragment.appendChild(item);
            });
            list.replaceChildren(fragment);
        }
        function show(entry) { details.textContent = JSON.stringify(entry, null, 2); }
        async function select(id) {
            selected = id;
            render();
            var entry = entries.get(id);
            show(entry);
            if (detailController) detailController.abort();
            if (!entry.id || !visible()) return;
            var controller = new target.AbortController();
            detailController = controller;
            try {
                var response = await target.fetch(base.href + '/profiles/' + encodeURIComponent(entry.id), {
                    credentials: 'omit', cache: 'no-store', signal: controller.signal
                });
                if (!response.ok) throw new Error('Profile unavailable');
                var detail = await response.json();
                if (visible() && selected === id && !controller.signal.aborted) {
                    add(detail);
                    show(entries.get(id));
                }
            } catch (_) {
                if (!controller.signal.aborted && visible()) status.textContent = 'Profile detail unavailable.';
            } finally {
                if (detailController === controller) detailController = null;
            }
        }
        async function poll() {
            timer = null;
            if (!visible() || inflight) return;
            var controller = new target.AbortController();
            inflight = controller;
            try {
                var url = new URL(base.href + '/profiles');
                url.searchParams.set('limit', '100');
                if (cursor) url.searchParams.set('after', cursor);
                var response = await target.fetch(url.href, {
                    credentials: 'omit', cache: 'no-store', signal: controller.signal
                });
                if (!response.ok) throw new Error('History unavailable');
                var data = await response.json();
                if (!visible() || controller.signal.aborted) return;
                if (data.schemaVersion !== 1 || !Array.isArray(data.profiles)) throw new Error('Unsupported schema');
                data.profiles.forEach(add);
                if (typeof data.nextCursor === 'string') cursor = data.nextCursor;
                status.textContent = entries.size + ' recent requests. Server and browser clocks are separate.';
                render();
            } catch (_) {
                if (!controller.signal.aborted && visible()) status.textContent = 'API history unavailable.';
            } finally {
                if (inflight === controller) inflight = null;
                if (visible()) timer = target.setTimeout(poll, 2000);
            }
        }
        function refresh() {
            if (!visible()) {
                if (timer !== null) target.clearTimeout(timer);
                timer = null;
                if (inflight) inflight.abort();
                if (detailController) detailController.abort();
            } else if (!inflight && timer === null) poll();
        }
        var unsubscribe = function () {};
        if (client) {
            client.getEntries().forEach(add);
            unsubscribe = client.subscribe(function (entry) { add(entry); if (visible()) render(); });
        }
        document.addEventListener('visibilitychange', refresh);
        refresh();
        return {
            refresh: refresh,
            stop: function () {
                stopped = true;
                refresh();
                unsubscribe();
                document.removeEventListener('visibilitychange', refresh);
            }
        };
    }

    function start(target) {
        if (!target || !target.document) return { stop: function () {} };
        if (target[key]) return target[key];
        var panels = new Map();
        var configured = new WeakSet();
        var ownedClient = null;
        function configureClient(root) {
            var existing = target[Symbol.for('slim-tracy.ajax-profiler.v1')];
            var client = configure(root, target);
            if (client && !existing) ownedClient = client;
            return client;
        }
        function scan() {
            target.document.querySelectorAll('[data-slim-tracy-client]').forEach(function (root) {
                if (!configured.has(root)) {
                    configured.add(root);
                    try { configureClient(root); } catch (_) { /* Invalid config stays inactive. */ }
                }
            });
            target.document.querySelectorAll('[data-slim-tracy-api]').forEach(function (root) {
                if (!panels.has(root)) {
                    try { panels.set(root, mount(root, target, configureClient)); }
                    catch (_) {
                        panels.set(root, { refresh: function () {}, stop: function () {} });
                        root.textContent = 'Invalid API profiler configuration.';
                    }
                }
            });
            panels.forEach(function (panel, root) {
                if (!root.isConnected) { panel.stop(); panels.delete(root); }
                else panel.refresh();
            });
        }
        var observer = new target.MutationObserver(scan);
        observer.observe(target.document.documentElement, {
            childList: true, subtree: true, attributes: true,
            attributeFilter: ['style', 'class', 'hidden', 'open', 'aria-expanded']
        });
        var handle = { stop: function () {
            observer.disconnect();
            panels.forEach(function (panel) { panel.stop(); });
            panels.clear();
            if (ownedClient) ownedClient.stop();
            target.removeEventListener('pagehide', pagehide);
            if (target[key] === handle) delete target[key];
        } };
        function pagehide(event) {
            // Keep observers/subscriptions for a BFCache restore; document visibility pauses polling.
            if (!event.persisted) handle.stop();
        }
        target[key] = handle;
        target.addEventListener('pagehide', pagehide);
        scan();
        return handle;
    }
    return { start: start };
}));
