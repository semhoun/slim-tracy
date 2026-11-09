import { test } from "node:test";
import assert from "node:assert/strict";
import { readFile } from "node:fs/promises";
import vm from "node:vm";

const root = new URL("../../../", import.meta.url);

test("pinned real Tracy exposes captureAjax and custom assets run after core, before initialization", async (t) => {
  let source;
  let loader;
  try {
    source = await readFile(
      new URL("vendor/tracy/tracy/src/Tracy/Bar/assets/bar.js", root),
      "utf8"
    );
    loader = await readFile(
      new URL(
        "vendor/tracy/tracy/src/Tracy/Debugger/DeferredContent.php",
        root
      ),
      "utf8"
    );
  } catch (error) {
    if (error.code !== "ENOENT") throw error;
    t.skip(
      "Composer vendor unavailable; run composer install to verify Tracy compatibility"
    );
    return;
  }
  assert.ok(
    loader.indexOf("../Bar/assets/bar.js") <
      loader.indexOf("Debugger::$customJsFiles")
  );
  assert.match(loader, /implode\('', \$js1\) \. implode\('', \$js2\)/);
  assert.match(source, /static captureAjax\(\)/);
  assert.match(source, /Debug\.captureAjax\(\)/);
  const node = () => ({
    style: {},
    setAttribute() {},
    appendChild() {},
    querySelectorAll: () => [],
  });
  const fetch = () => {};
  function XHR() {}
  XHR.prototype.open = () => {};
  const open = XHR.prototype.open;
  const context = vm.createContext({
    document: {
      currentScript: { dataset: { id: "test" } },
      createElement: node,
      body: node(),
      documentElement: node(),
    },
    navigator: {},
    location: {
      href: "https://app.test/",
      origin: "https://app.test",
      host: "app.test",
    },
    fetch,
    XMLHttpRequest: XHR,
    Request,
    Response,
    URL,
  });
  context.window = context;
  vm.runInContext("(function(){" + source + "\n})();", context);
  const disable = await readFile(
    new URL("web/disable-native-ajax.js", root),
    "utf8"
  );
  vm.runInContext(disable, context);
  // Stub unrelated layout/event machinery, execute the real Debug.init call site.
  context.Tracy.DebugBar.prototype.init = () => {};
  context.Tracy.Debug.captureWindow = () => {};
  context.Tracy.TableSort = { init() {} };
  context.Tracy.Tabs = { init() {} };
  context.Tracy.Debug.init("");
  assert.equal(context.fetch, fetch);
  assert.equal(XHR.prototype.open, open);
});

test("native disable fails explicitly rather than silently allowing dual wrappers", async () => {
  const source = await readFile(
    new URL("web/disable-native-ajax.js", root),
    "utf8"
  );
  assert.throws(
    () => vm.runInNewContext(source, {}),
    /incompatible Tracy AJAX API/
  );
  assert.throws(
    () => vm.runInNewContext(source, { Tracy: { Debug: {} } }),
    /incompatible Tracy AJAX API/
  );
});
