import { test } from "node:test";
import assert from "node:assert/strict";
import panel from "../panel.cjs";
import { install } from "../index.js";

class Element extends EventTarget {
  constructor(tag = "div") {
    super();
    this.tag = tag;
    this.children = [];
    this.dataset = {};
  }
  isConnected = true;
  visible = false;
  value = "";
  set innerHTML(_) {
    throw new Error("Untrusted HTML rendering is forbidden");
  }
  set textContent(value) {
    this.value = String(value);
    this.children = [];
  }
  get textContent() {
    return this.value + this.children.map((item) => item.textContent).join("");
  }
  setAttribute() {}
  appendChild(node) {
    this.children.push(node);
  }
  replaceChildren(...nodes) {
    this.value = "";
    this.children = nodes.flatMap((node) =>
      node.tag === "fragment" ? node.children : node
    );
  }
  getClientRects() {
    return this.visible ? [{}] : [];
  }
}
function environment({ root = true, marker = true, visible = false } = {}) {
  const document = new EventTarget();
  const view = new Element();
  view.visible = visible;
  view.dataset = {
    clientEnabled: "true",
    allowedOrigins: "[]",
    baseUrl: "/_tracy/api",
  };
  const tab = new Element("span");
  tab.dataset = view.dataset;
  document.hidden = false;
  document.documentElement = new Element();
  document.createElement = (tag) => new Element(tag);
  document.createDocumentFragment = () => new Element("fragment");
  document.querySelectorAll = (selector) =>
    selector === "[data-slim-tracy-api]"
      ? root
        ? [view]
        : []
      : marker
        ? [tab]
        : [];
  const timers = new Map();
  const calls = [];
  const observers = [];
  let timerId = 0;
  const target = new EventTarget();
  Object.assign(target, {
    document,
    SlimTracyAjaxProfiler: { install },
    location: { href: "https://app.test/" },
    AbortController,
    performance,
    getComputedStyle: () => ({ visibility: "visible" }),
    setTimeout: (fn) => {
      timers.set(++timerId, fn);
      return timerId;
    },
    clearTimeout: (id) => timers.delete(id),
    MutationObserver: class {
      constructor(fn) {
        this.fn = fn;
        observers.push(this);
      }
      observe() {}
      disconnect() {}
    },
    fetch: async (url, options) => {
      calls.push([String(url), options]);
      return Response.json({
        schemaVersion: 1,
        nextCursor: "cursor&1",
        profiles: [
          {
            schemaVersion: 1,
            id: "a".repeat(32),
            method: "GET",
            path: "/<img src=x onerror=alert(1)>",
            status: 200,
            durationMs: 3,
            collectors: {},
          },
        ],
      });
    },
  });
  // Production install finds window itself. Tests inject their isolated browser target.
  target.SlimTracyAjaxProfiler.install = (options) =>
    install({ ...options, target });
  return {
    target,
    view,
    document,
    calls,
    timers,
    scan: () => observers.forEach((item) => item.fn()),
  };
}
const settle = () => new Promise((resolve) => setImmediate(resolve));

test("panel SSR start and cached assets without current server markers install nothing", () => {
  panel.start().stop();
  const env = environment({ root: false, marker: false });
  const original = env.target.fetch;
  const handle = panel.start(env.target);
  assert.equal(env.target.fetch, original);
  assert.equal(env.calls.length, 0);
  handle.stop();
});

test("tab marker installs before lazy panel exists and owned client is restored by stop", () => {
  const env = environment({ root: false });
  const original = env.target.fetch;
  const handle = panel.start(env.target);
  assert.notEqual(env.target.fetch, original);
  assert.equal(panel.start(env.target), handle);
  assert.equal(env.calls.length, 0);
  handle.stop();
  assert.equal(env.target.fetch, original);
});

test("poll only active view, incremental cursor, text-only untrusted render, visibility cancellation", async () => {
  const env = environment();
  const original = env.target.fetch;
  const handle = panel.start(env.target);
  assert.equal(env.calls.length, 0);
  assert.equal(env.timers.size, 0);
  env.view.visible = true;
  env.scan();
  await settle();
  assert.equal(env.calls.length, 1);
  assert.equal(new URL(env.calls[0][0]).pathname, "/_tracy/api/profiles");
  assert.equal(env.calls[0][1].credentials, "omit");
  assert.equal(env.timers.size, 1);
  assert.ok(env.view.textContent.includes("/<img src=x onerror=alert(1)>"));
  const tick = env.timers.values().next().value;
  env.timers.clear();
  tick();
  await settle();
  assert.equal(new URL(env.calls[1][0]).searchParams.get("after"), "cursor&1");
  env.document.hidden = true;
  env.document.dispatchEvent(new Event("visibilitychange"));
  assert.equal(env.timers.size, 0);
  env.scan();
  await settle();
  assert.equal(env.calls.length, 2);
  env.document.hidden = false;
  env.document.dispatchEvent(new Event("visibilitychange"));
  await settle();
  assert.equal(env.calls.length, 3);
  handle.stop();
  assert.equal(env.target.fetch, original);
  assert.equal(env.timers.size, 0);
});

test("panel cleanup does not stop an external SPA client; BFCache pagehide preserves owner", () => {
  const env = environment({ root: false });
  const external = install({ target: env.target });
  const wrapper = env.target.fetch;
  const handle = panel.start(env.target);
  handle.stop();
  assert.equal(external.active, true);
  assert.equal(env.target.fetch, wrapper);
  external.stop();
  const owned = panel.start(env.target);
  const event = new Event("pagehide");
  event.persisted = true;
  env.target.dispatchEvent(event);
  assert.equal(panel.start(env.target), owned);
  const next = new Event("pagehide");
  next.persisted = false;
  env.target.dispatchEvent(next);
  assert.notEqual(panel.start(env.target), owned);
  panel.start(env.target).stop();
});

test("invalid configuration is rendered once without installing a client or retry loops", () => {
  const env = environment({ marker: false });
  env.view.dataset.allowedOrigins = "{not JSON";
  const original = env.target.fetch;
  const handle = panel.start(env.target);
  assert.equal(env.target.fetch, original);
  assert.equal(env.view.textContent, "Invalid API profiler configuration.");
  env.scan();
  assert.equal(env.view.textContent, "Invalid API profiler configuration.");
  handle.stop();
});
