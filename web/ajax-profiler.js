/* Shared dependency-free source; web/ajax-profiler.js is generated from this file. */
(function (root, factory) {
    if (typeof module === 'object' && module.exports) module.exports = factory();
    else root.SlimTracyAjaxProfiler = factory();
}(typeof globalThis === 'object' ? globalThis : this, function () {
    'use strict';
    var key = Symbol.for('slim-tracy.ajax-profiler.v1');

    function inactive() {
        return { active: false, stop: function () {}, subscribe: function () { return function () {}; },
            getEntries: function () { return []; }, getProfile: async function () { return null; } };
    }

    function install(options) {
        options = options || {};
        var target = options.target || (typeof window === 'object' ? window : null);
        if (!target || options.enabled === false) return inactive();
        if (target[key]) return target[key];
        var location = new URL(target.location.href);
        var base = new URL(options.baseUrl || '/_tracy/api', location);
        if (!/^https?:$/.test(base.protocol) || base.username || base.password || base.search || base.hash) {
            throw new TypeError('Profiler baseUrl must be an HTTP URL without credentials, query or fragment');
        }
        base.pathname = base.pathname.replace(/\/+$/, '');
        var origins = new Set((options.allowedOrigins === undefined ? [location.origin] : options.allowedOrigins)
            .map(function (origin) {
                var url = new URL(origin);
                if (url.origin !== origin || !/^https?:$/.test(url.protocol)) {
                    throw new TypeError('allowedOrigins must contain exact HTTP origins');
                }
                return origin;
            }));
        var originalFetch = target.fetch;
        var proto = target.XMLHttpRequest && target.XMLHttpRequest.prototype;
        var originalOpen = proto && proto.open;
        var originalSend = proto && proto.send;
        var entries = [];
        var listeners = new Set();
        var pending = new Set();
        var xhrs = new WeakMap();
        var sequence = 0;
        var limit = Number.isInteger(options.maxEntries) ? Math.max(1, Math.min(1000, options.maxEntries)) : 100;
        var clock = function () { return target.performance.now(); };
        var handle = {
            active: true,
            getEntries: function () { return entries.slice(); },
            getProfile: async function (id) {
                if (!handle.active || !profileId(id) || !originalFetch) return null;
                var controller = new target.AbortController();
                var cleanup = function () { controller.abort(); };
                pending.add(cleanup);
                try {
                    var response = await originalFetch.call(target, base.href.replace(/\/$/, '')
                        + '/profiles/' + encodeURIComponent(id), {
                        credentials: 'omit', cache: 'no-store', signal: controller.signal
                    });
                    if (!response.ok) return null;
                    var profile = await response.json();
                    return handle.active && !controller.signal.aborted && profile.schemaVersion === 1
                        && profile.id === id ? profile : null;
                } catch (_) { return null; }
                finally { pending.delete(cleanup); }
            },
            subscribe: function (listener) {
                if (!handle.active) return function () {};
                listeners.add(listener);
                return function () { listeners.delete(listener); };
            },
            stop: function () {
                if (!handle.active) return;
                handle.active = false;
                if (target.fetch === fetchWrapper) target.fetch = originalFetch;
                if (proto && proto.open === openWrapper) proto.open = originalOpen;
                if (proto && proto.send === sendWrapper) proto.send = originalSend;
                pending.forEach(function (cleanup) { cleanup(); });
                pending.clear();
                listeners.clear();
                if (target[key] === handle) delete target[key];
            }
        };

        function metadata(input, init) {
            try {
                var request = input && typeof input === 'object' && 'url' in input;
                var url = new URL(request ? input.url : input, location);
                var mode = init && init.mode !== undefined ? init.mode : (request ? input.mode : undefined);
                if (mode === 'no-cors' || !origins.has(url.origin) || url.username || url.password
                    || url.searchParams.has('_tracy_bar')
                    || (url.origin === base.origin && (url.pathname === base.pathname
                        || url.pathname.startsWith(base.pathname + '/')))) return null;
                var method = (init && init.method) || (request && input.method) || 'GET';
                return { method: String(method).toUpperCase().slice(0, 32), path: url.pathname.slice(0, 2048) };
            } catch (_) { return null; }
        }

        function begin(meta) {
            return { meta: meta, time: clock(), startedAt: new Date().toISOString() };
        }

        function profileId(value) {
            return typeof value === 'string' && /^[a-f0-9]{32}$/.test(value) ? value : null;
        }

        function finish(start, status, id, outcome) {
            if (!handle.active) return;
            var entry = Object.freeze({ schemaVersion: 1, localId: ++sequence, id: profileId(id),
                method: start.meta.method, path: start.meta.path, status: status || null,
                startedAt: start.startedAt, durationMs: Math.max(0, clock() - start.time),
                memoryBytes: null, collectors: null, source: 'browser', outcome: outcome });
            entries.push(entry);
            if (entries.length > limit) entries.shift();
            listeners.forEach(function (listener) { try { listener(entry); } catch (_) { /* Observer isolation. */ } });
            if (entry.id && originalFetch && options.telemetry !== false && handle.active) {
                try {
                    // Use the unwrapped fetch, omit cookies only on our own telemetry, never on app calls.
                    Promise.resolve(originalFetch.call(target, base.href.replace(/\/$/, '') + '/browser', {
                        method: 'POST', credentials: 'omit', headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ schemaVersion: 1, id: entry.id, method: entry.method,
                            path: entry.path, status: entry.status, durationMs: entry.durationMs,
                            startedAt: entry.startedAt, outcome: entry.outcome })
                    })).catch(function () {});
                } catch (_) { /* Telemetry must not affect application requests. */ }
            }
        }

        function fetchWrapper(input, init) {
            if (!handle.active) return originalFetch.apply(this, arguments);
            var meta = metadata(input, init);
            if (!meta) return originalFetch.apply(this, arguments);
            var start = begin(meta);
            var result;
            try { result = originalFetch.apply(this, arguments); }
            catch (error) {
                finish(start, null, null, error && error.name === 'AbortError' ? 'aborted' : 'network-error');
                throw error;
            }
            return result.then(function (response) {
                var id = null;
                try { id = response.headers.get('X-SlimTracy-Profile'); } catch (_) { /* Opaque response. */ }
                finish(start, response.status, id, 'complete');
                return response;
            }, function (error) {
                finish(start, null, null, error && error.name === 'AbortError' ? 'aborted' : 'network-error');
                throw error;
            });
        }

        function openWrapper(method, url) {
            var previous = xhrs.get(this);
            // An app's load/loadend handler can reopen before our loadend listener runs.
            if (previous && previous.cleanup && this.readyState === 4) previous.finish();
            var result = originalOpen.apply(this, arguments);
            if (previous && previous.cleanup) previous.finish('aborted');
            xhrs.set(this, { meta: handle.active ? metadata(url, { method: method }) : null });
            return result;
        }

        function sendWrapper() {
            var xhr = this;
            var state = xhrs.get(xhr);
            if (!handle.active || !state || !state.meta || state.cleanup) return originalSend.apply(xhr, arguments);
            var start = begin(state.meta);
            var outcome = 'complete';
            var done = false;
            function error() { outcome = 'network-error'; }
            function abort() { outcome = 'aborted'; }
            function cleanup() {
                xhr.removeEventListener('error', error);
                xhr.removeEventListener('timeout', error);
                xhr.removeEventListener('abort', abort);
                xhr.removeEventListener('loadend', end);
                pending.delete(cleanup);
                state.cleanup = null;
            }
            function end(forcedOutcome) {
                if (done) return;
                // A reentrant load handler may cause an old loadend to reach newly added listeners.
                if (typeof forcedOutcome !== 'string' && xhr.readyState !== 4 && outcome === 'complete') return;
                done = true;
                if (forcedOutcome === 'aborted') outcome = 'aborted';
                var status = null;
                var id = null;
                try { status = xhr.status; id = xhr.getResponseHeader('X-SlimTracy-Profile'); } catch (_) { /* No response. */ }
                cleanup();
                finish(start, status, id, status ? outcome : (outcome === 'complete' ? 'network-error' : outcome));
            }
            state.cleanup = cleanup;
            state.finish = end;
            pending.add(cleanup);
            xhr.addEventListener('error', error);
            xhr.addEventListener('timeout', error);
            xhr.addEventListener('abort', abort);
            xhr.addEventListener('loadend', end);
            try { return originalSend.apply(xhr, arguments); }
            catch (error) { cleanup(); throw error; }
        }

        if (originalFetch) target.fetch = fetchWrapper;
        if (proto) { proto.open = openWrapper; proto.send = sendWrapper; }
        target[key] = handle;
        return handle;
    }
    return { install: install };
}));
