(function () {
    'use strict';
    if (!globalThis.Tracy || !Tracy.Debug || typeof Tracy.Debug.captureAjax !== 'function') {
        throw new Error('SlimTracy: incompatible Tracy AJAX API; native capture cannot be disabled.');
    }
    Tracy.Debug.captureAjax = function () {};
}());
