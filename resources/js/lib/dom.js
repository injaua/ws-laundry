/** Escape text before putting it into innerHTML. */
export const esc = (value) =>
    String(value ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);

const cache = new Map();

/**
 * Server data for the page, printed by <x-page-data name="..." :data="..."/>
 * as a non-executable JSON block.
 */
export function pageData(name) {
    if (!cache.has(name)) {
        const el = document.querySelector(`script[type="application/json"][data-page="${name}"]`);
        cache.set(name, el ? JSON.parse(el.textContent) : {});
    }

    return cache.get(name);
}

/** Forget the data read so far: the navigator calls it when another page is shown. */
export const resetPageData = () => cache.clear();

/** Global settings of the layout (money format, texts, limits). */
export const appConfig = () => pageData('app');

export const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';

export const getJson = (url) => fetch(url, { headers: { Accept: 'application/json' } }).then((r) => r.json());

/** Run once the DOM is parsed (module scripts usually already are). */
export const ready = (fn) => (document.readyState === 'loading' ? document.addEventListener('DOMContentLoaded', fn) : fn());

/** localStorage that never throws (private windows, blocked storage). */
export const storage = {
    get(key) {
        try {
            return localStorage.getItem(key);
        } catch {
            return null;
        }
    },
    set(key, value) {
        try {
            localStorage.setItem(key, value);
        } catch {
            /* storage unavailable */
        }
    },
};
