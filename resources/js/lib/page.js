/*
 * Life cycle of the page shown in the back office (layouts/admin).
 *
 * Links open without a full reload (lib/navigation.js): the <body> is swapped and the
 * page scripts run again. Whatever a page script leaves behind outside its own elements
 * (listeners on document / window, timers, camera, charts) must end with the page, or it
 * would keep running on the next one. Page scripts therefore use:
 *
 *   on(document, 'input', handler)   instead of document.addEventListener(...)
 *   every(fn, ms) / later(fn, ms)    instead of setInterval / setTimeout
 *   onLeave(fn)                      anything else to undo (camera, chart…)
 *   reloadPage() / visit(url)        instead of location.reload() / location.href = url
 */

let controller = new AbortController();
let visitor = null;

/** Aborted when the user leaves the current page. */
export const pageSignal = () => controller.signal;

/** Run fn when the user leaves the current page. */
export function onLeave(fn) {
    controller.signal.addEventListener('abort', fn, { once: true });
}

/** addEventListener that ends with the page. */
export function on(target, type, handler, options = {}) {
    target.addEventListener(type, handler, { ...(typeof options === 'boolean' ? { capture: options } : options), signal: pageSignal() });
}

/** setInterval that ends with the page. */
export function every(fn, ms) {
    const id = setInterval(fn, ms);
    onLeave(() => clearInterval(id));

    return id;
}

/** setTimeout that ends with the page. */
export function later(fn, ms) {
    const id = setTimeout(fn, ms);
    onLeave(() => clearTimeout(id));

    return id;
}

/** Called by the navigator before the next page replaces this one. */
export function endPage() {
    controller.abort();
    controller = new AbortController();
}

/** The navigator registers itself here (no import cycle with the page scripts). */
export function setVisitor(fn) {
    visitor = fn;
}

/** Open url like a link click: without a full reload when possible. */
export function visit(url, options = {}) {
    if (visitor) return visitor(url, options);
    location.href = url;

    return Promise.resolve();
}

/** Show the current page again with fresh data, keeping the scroll position. */
export function reloadPage() {
    if (visitor) return visitor(location.href, { action: 'replace', keepScroll: true });
    location.reload();

    return Promise.resolve();
}
