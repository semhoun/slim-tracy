import React, { useState } from 'react';
import { createRoot } from 'react-dom/client';
import { createApp, h, ref } from 'vue';
import { install } from '../../packages/ajax-profiler/index.js';
import { installVueDevtools } from '../../packages/ajax-profiler/vue.js';

const api = 'http://127.0.0.1:8781';
const enabled = !location.search.includes('disabled');
const client = install({ enabled, baseUrl: `${api}/_tracy/api`, allowedOrigins: [api] });
window.fixtureClient = client;
const load = async () => (await fetch(`${api}/echo`)).json();

function ReactFixture() {
  const [result, setResult] = useState('Ready');
  return React.createElement('button', { onClick: async () => setResult((await load()).method) }, `React ${result}`);
}

if (location.pathname === '/react') {
  createRoot(document.querySelector('#app')).render(React.createElement(ReactFixture));
} else {
  const app = createApp({ setup() {
    const result = ref('Ready');
    return () => h('button', { onClick: async () => { result.value = (await load()).method; } }, `Vue ${result.value}`);
  } });
  const adapter = installVueDevtools(app, client);
  app.mount('#app');
  window.fixtureStop = () => { adapter.stop(); client.stop(); app.unmount(); };
}
