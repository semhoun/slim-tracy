import { test } from "node:test";
import assert from "node:assert/strict";
import { installVueDevtools } from "../vue.js";

function client() {
  const listeners = new Set();
  const entries = [];
  return {
    active: true,
    entries,
    getEntries: () => entries.slice(),
    subscribe(fn) {
      listeners.add(fn);
      return () => listeners.delete(fn);
    },
    emit(entry) {
      entries.push(entry);
      listeners.forEach((fn) => fn(entry));
    },
    count: () => listeners.size,
  };
}
function api() {
  const calls = {
    layers: [],
    inspectors: [],
    events: [],
    trees: [],
    states: [],
  };
  const hooks = {};
  return {
    calls,
    hooks,
    now: () => 42,
    addTimelineLayer: (value) => calls.layers.push(value),
    addInspector: (value) => calls.inspectors.push(value),
    addTimelineEvent: (value) => calls.events.push(value),
    sendInspectorTree: (value) => calls.trees.push(value),
    sendInspectorState: (value) => calls.states.push(value),
    on: {
      getInspectorTree: (fn) => {
        hooks.tree = fn;
      },
      getInspectorState: (fn) => {
        hooks.state = fn;
      },
    },
  };
}
const event = (localId) =>
  Object.freeze({
    schemaVersion: 1,
    localId,
    id: "abc",
    method: "GET",
    path: "/api",
    status: 200,
    durationMs: 5,
    startedAt: "2026-11-09T00:00:00Z",
    outcome: "complete",
    source: "browser",
  });

test("SSR import and disabled client do not register a Vue plugin", () => {
  delete globalThis.__VUE_DEVTOOLS_PLUGINS__;
  installVueDevtools({}, { active: false }).stop();
  assert.equal(globalThis.__VUE_DEVTOOLS_PLUGINS__, undefined);
});

test("real public API deferred setup replays same stream in timeline and inspector, HMR cleanup", () => {
  delete globalThis.__VUE_DEVTOOLS_GLOBAL_HOOK__;
  globalThis.__VUE_DEVTOOLS_PLUGINS__ = [];
  const app = {};
  const source = client();
  source.emit(event(1));
  const handle = installVueDevtools(app, source);
  assert.equal(installVueDevtools(app, source), handle);
  assert.equal(source.count(), 1);
  assert.equal(globalThis.__VUE_DEVTOOLS_PLUGINS__.length, 1);
  source.emit(event(2));
  const publicApi = api();
  globalThis.__VUE_DEVTOOLS_PLUGINS__[0].setupFn(publicApi);
  assert.equal(publicApi.calls.layers.length, 1);
  assert.equal(publicApi.calls.inspectors.length, 1);
  assert.deepEqual(
    publicApi.calls.events.map((item) => item.event.data),
    source.entries
  );
  const inspectorId = publicApi.calls.inspectors[0].id;
  const tree = { app, inspectorId, filter: "/api" };
  publicApi.hooks.tree(tree);
  assert.deepEqual(tree.rootNodes, [
    { id: "1", label: "GET /api" },
    { id: "2", label: "GET /api" },
  ]);
  const state = { app, inspectorId, nodeId: "2" };
  publicApi.hooks.state(state);
  assert.deepEqual(
    Object.fromEntries(
      state.state["Client-Reported Request"].map((item) => [
        item.key,
        item.value,
      ])
    ),
    event(2)
  );
  assert.ok(
    state.state["Client-Reported Request"].every(
      (item) => item.editable === false
    )
  );
  source.emit(event(3));
  assert.equal(publicApi.calls.events.length, 3);
  handle.stop();
  handle.stop();
  assert.equal(source.count(), 0);
  source.emit(event(4));
  assert.equal(publicApi.calls.events.length, 3);
  publicApi.hooks.tree(tree);
  assert.deepEqual(tree.rootNodes, []);
  const next = installVueDevtools(app, source);
  assert.notEqual(next, handle);
  assert.equal(publicApi.calls.layers.length, 1);
  assert.equal(publicApi.calls.inspectors.length, 1);
  assert.equal(globalThis.__VUE_DEVTOOLS_PLUGINS__.length, 1);
  handle.stop();
  assert.equal(source.count(), 1);
  next.stop();
  delete globalThis.__VUE_DEVTOOLS_PLUGINS__;
});

test("late devtools connection after stop creates no UI or events; later install reuses registration", () => {
  globalThis.__VUE_DEVTOOLS_PLUGINS__ = [];
  const app = {};
  const source = client();
  const handle = installVueDevtools(app, source);
  source.emit(event(1));
  handle.stop();
  const publicApi = api();
  globalThis.__VUE_DEVTOOLS_PLUGINS__[0].setupFn(publicApi);
  assert.equal(publicApi.calls.layers.length, 0);
  assert.equal(publicApi.calls.events.length, 0);
  const next = installVueDevtools(app, source);
  assert.equal(publicApi.calls.layers.length, 1);
  assert.equal(publicApi.calls.events.length, 1);
  next.stop();
  delete globalThis.__VUE_DEVTOOLS_PLUGINS__;
});

test("connected public devtools hook gets bounded inspector stream without adding instrumentation", () => {
  const publicApi = api();
  let setups = 0;
  globalThis.__VUE_DEVTOOLS_GLOBAL_HOOK__ = {
    emit(name, descriptor, setup) {
      assert.equal(name, "devtools-plugin:setup");
      assert.equal(descriptor.packageName, "@slim-tracy/ajax-profiler");
      setups++;
      setup(publicApi);
    },
  };
  const source = client();
  const app = {};
  const handle = installVueDevtools(app, source);
  for (let i = 1; i <= 110; i++) source.emit(event(i));
  const tree = { app, inspectorId: publicApi.calls.inspectors[0].id };
  publicApi.hooks.tree(tree);
  assert.equal(tree.rootNodes.length, 100);
  assert.equal(tree.rootNodes[0].id, "11");
  assert.equal(setups, 1);
  const unrelated = { app: {}, inspectorId: tree.inspectorId };
  publicApi.hooks.tree(unrelated);
  assert.equal(unrelated.rootNodes, undefined);
  handle.stop();
  delete globalThis.__VUE_DEVTOOLS_GLOBAL_HOOK__;
});

test("inspector retrieves correlated server profile using shared client and ignores late completion after stop", async () => {
  globalThis.__VUE_DEVTOOLS_PLUGINS__ = [];
  const source = client();
  source.emit(event(1));
  const profile = {
    schemaVersion: 1,
    id: "abc",
    collectors: { manual: { available: true, profiles: [] } },
  };
  let calls = 0;
  source.getProfile = async (id) => {
    calls++;
    assert.equal(id, "abc");
    return profile;
  };
  const app = {};
  const handle = installVueDevtools(app, source);
  const publicApi = api();
  globalThis.__VUE_DEVTOOLS_PLUGINS__[0].setupFn(publicApi);
  const payload = {
    app,
    inspectorId: publicApi.calls.inspectors[0].id,
    nodeId: "1",
  };
  await publicApi.hooks.state(payload);
  assert.equal(calls, 1);
  assert.deepEqual(
    Object.fromEntries(
      payload.state["Server Profile"].map((item) => [item.key, item.value])
    ),
    profile
  );
  let resolve;
  source.getProfile = () =>
    new Promise((done) => {
      resolve = done;
    });
  const late = publicApi.hooks.state(payload);
  handle.stop();
  resolve(profile);
  await late;
  assert.deepEqual(payload.state, {});
  delete globalThis.__VUE_DEVTOOLS_PLUGINS__;
});

test("adapter module HMR reload reuses app registration and existing hooks", async () => {
  globalThis.__VUE_DEVTOOLS_PLUGINS__ = [];
  const source = client();
  const app = {};
  const handle = installVueDevtools(app, source);
  const publicApi = api();
  globalThis.__VUE_DEVTOOLS_PLUGINS__[0].setupFn(publicApi);
  handle.stop();
  const reloaded = await import("../vue.js?hmr=1");
  const next = reloaded.installVueDevtools(app, source);
  assert.equal(globalThis.__VUE_DEVTOOLS_PLUGINS__.length, 1);
  assert.equal(publicApi.calls.layers.length, 1);
  source.emit(event(1));
  assert.equal(publicApi.calls.events.length, 1);
  next.stop();
  delete globalThis.__VUE_DEVTOOLS_PLUGINS__;
});
