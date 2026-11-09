// Test bridge for the public Devtools plugin API, not a browser extension emulator.
window.fixtureDevtools = { layers: [], inspectors: [], events: [], callbacks: {} };
window.__VUE_DEVTOOLS_PLUGIN_API_AVAILABLE__ = true;
window.__VUE_DEVTOOLS_GLOBAL_HOOK__ = {
  enabled: true,
  emit(event, descriptor, setup) {
    if (event !== 'devtools-plugin:setup') return;
    const state = window.fixtureDevtools;
    state.app = descriptor.app;
    setup({
      addTimelineLayer: (layer) => state.layers.push(layer),
      addInspector: (inspector) => state.inspectors.push(inspector),
      addTimelineEvent: (entry) => state.events.push(entry),
      sendInspectorTree() {}, sendInspectorState() {}, now: () => performance.now(),
      on: {
        getInspectorTree: (fn) => { state.callbacks.tree = fn; },
        getInspectorState: (fn) => { state.callbacks.state = fn; },
      },
    });
  },
};
