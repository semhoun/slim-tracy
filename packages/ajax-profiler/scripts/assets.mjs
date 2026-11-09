import { readFile, writeFile } from "node:fs/promises";
import { dirname, resolve } from "node:path";
import { fileURLToPath } from "node:url";
import { format, resolveConfig } from "prettier";

// Every input/output is fixed relative to this script, never supplied by callers.
const directory = dirname(fileURLToPath(import.meta.url));
const clientSource = resolve(directory, "../client.cjs");
const panelSource = resolve(directory, "../panel.cjs");
const clientAsset = resolve(directory, "../../../web/ajax-profiler.js");
const panelAsset = resolve(directory, "../../../web/api-panel.js");
const esmAsset = resolve(directory, "../client.js");
const umd = await readFile(clientSource, "utf8");
const panel = await readFile(panelSource, "utf8");

// The browser and native ESM exports use the exact same factory, without a bundler dependency.
const marker =
  '})(typeof globalThis === "object" ? globalThis : this, function () {\n';
if (!umd.includes(marker) || !umd.endsWith("});\n")) {
  throw new Error("Unexpected UMD factory boundary");
}
const body = umd.slice(umd.indexOf(marker) + marker.length, -4);
const esm =
  "// Generated from client.cjs; run npm run build.\n" +
  "const client = (function () {\n" +
  body +
  "})();\nexport const install = client.install;\nexport default client;\n";
const formatting = { ...(await resolveConfig(clientAsset)), parser: "babel" };
const [clientOutput, panelOutput, esmOutput] = await Promise.all(
  [umd, panel, esm].map((content) => format(content, formatting))
);

if (process.argv.includes("--check")) {
  const actual = await Promise.all([
    readFile(clientAsset, "utf8"),
    readFile(panelAsset, "utf8"),
    readFile(esmAsset, "utf8"),
  ]);
  if (
    actual[0] !== clientOutput ||
    actual[1] !== panelOutput ||
    actual[2] !== esmOutput
  ) {
    throw new Error("Generated assets are stale; run npm run build");
  }
} else {
  await Promise.all([
    writeFile(clientAsset, clientOutput),
    writeFile(panelAsset, panelOutput),
    writeFile(esmAsset, esmOutput),
  ]);
}
