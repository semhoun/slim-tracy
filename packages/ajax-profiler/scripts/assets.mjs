import { readFile, writeFile } from 'node:fs/promises';

async function output(destination, content) {
    if (process.argv.includes('--check')) {
        if (!Buffer.from(content).equals(await readFile(destination))) {
            throw new Error(destination.pathname + ' is stale; run npm run build');
        }
    } else {
        await writeFile(destination, content);
    }
}

for (const [source, asset] of [['client.cjs', 'ajax-profiler.js'], ['panel.cjs', 'api-panel.js']]) {
    const content = await readFile(new URL('../' + source, import.meta.url));
    const destination = new URL('../../../web/' + asset, import.meta.url);
    await output(destination, content);
}

// The browser and native ESM exports use the exact same factory, without a bundler dependency.
const umd = await readFile(new URL('../client.cjs', import.meta.url), 'utf8');
const marker = "}(typeof globalThis === 'object' ? globalThis : this, function () {\n";
if (!umd.includes(marker) || !umd.endsWith('}));\n')) throw new Error('Unexpected UMD factory boundary');
const body = umd.slice(umd.indexOf(marker) + marker.length, -5);
await output(new URL('../client.js', import.meta.url), '// Generated from client.cjs; run npm run build.\n'
    + 'const client = (function () {\n' + body + '})();\nexport const install = client.install;\nexport default client;\n');
