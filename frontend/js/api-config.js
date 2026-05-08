/* Resolves the absolute URL of the backend/api/ directory regardless of
 * which page (or sub-folder) loaded the script. The script itself lives at
 *   <site-root>/frontend/js/api-config.js
 * so backend/api/ is two levels up. */
(function () {
    const me = document.currentScript || (function () {
        const list = document.getElementsByTagName('script');
        return list[list.length - 1];
    })();
    const url = me ? me.src : '';
    window.TATTU_API = new URL('../../backend/api/', url).href;
})();
