(function () {
  "use strict";
  if (
    !globalThis.Tracy ||
    !globalThis.Tracy.Debug ||
    typeof globalThis.Tracy.Debug.captureAjax !== "function"
  ) {
    throw new Error(
      "SlimTracy: incompatible Tracy AJAX API; native capture cannot be disabled."
    );
  }
  globalThis.Tracy.Debug.captureAjax = function () {};
})();
