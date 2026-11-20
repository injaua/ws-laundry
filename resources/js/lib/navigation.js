/*
 * Back-office navigation without full page reloads.
 *
 * A click on an internal link (or a GET form: filters, searches) fetches the page and
 * swaps the <body>: the sidebar, the top bar and Bootstrap stay loaded, only the content
 * changes. The address bar and the back / forward buttons work as usual.
 *
 * The browser loads the page itself (full reload) when:
 *   - the link has target, download or data-no-nav (on it or on a parent);
 *   - the address is a document, print-out or export (…/document, …/print, …/export,
 *     ?format=pdf…), the language switch, or a public page;
 *   - the answer is not a back-office page (login after the session expired, error,
 *     another layout) or not HTML (a download is saved, nothing is requested twice).
 * POST forms are not touched: their redirects, validation errors and uploads keep the
 * normal browser behaviour.
 *
 * Page scripts run again on every visit; see lib/page.js for what they must clean up.
 */
import { resetPageData } from './dom';
import { endPage, setVisitor } from './page';

const ROOT = 'data-nav-root';
const DOCUMENT_PATH = /\/(export|document|print|receipt|label|labels|payslip|statement|backups|tags)(\/|$)/i;
const DOCUMENT_QUERY = /^(pdf|xlsx|xls|csv|print)$/i;
const PUBLIC_PATH = /^\/(|order-status|pagar(\/.*)?|language\/.*|webhooks\/.*|logout|login|register)$/;

let inflight = null;
let runs = 0;
let current = location.pathname + location.search;
const loadedScripts = new Set([...document.scripts].map((s) => s.src).filter(Boolean));

/** Whether the URL can be shown without a full reload. */
function navigable(url) {
    if (url.origin !== location.origin || !/^https?:$/.test(url.protocol)) return false;
    if (PUBLIC_PATH.test(url.pathname) || DOCUMENT_PATH.test(url.pathname)) return false;

    return !DOCUMENT_QUERY.test(url.searchParams.get('format') ?? '');
}

// ---- Progress bar (outside <body>, so it survives the swap)
const bar = document.createElement('div');
bar.className = 'nav-progress';
bar.setAttribute('aria-hidden', 'true');
document.documentElement.appendChild(bar);
let barTimer = null;
function progress(on) {
    clearTimeout(barTimer);
    document.documentElement.toggleAttribute('aria-busy', on);
    if (on) {
        // Fast pages show no bar at all.
        barTimer = setTimeout(() => bar.classList.add('is-loading'), 150);
    } else {
        bar.classList.remove('is-loading');
        bar.classList.add('is-done');
        barTimer = setTimeout(() => bar.classList.remove('is-done'), 300);
    }
}

// ---- <head>: title, CSRF token, layout data and the page stylesheets
async function mergeHead(head) {
    document.title = head.querySelector('title')?.textContent ?? document.title;

    const token = head.querySelector('meta[name="csrf-token"]')?.content;
    if (token) document.querySelector('meta[name="csrf-token"]')?.setAttribute('content', token);

    document.head.querySelectorAll('script[type="application/json"][data-page]').forEach((el) => el.remove());
    head.querySelectorAll('script[type="application/json"][data-page]').forEach((el) => document.head.appendChild(el.cloneNode(true)));

    const wanted = [...head.querySelectorAll('link[rel="stylesheet"]')].map((l) => l.href);
    const present = [...document.head.querySelectorAll('link[rel="stylesheet"]')];
    // New stylesheets first (waiting for them avoids a flash of unstyled content)…
    await Promise.all(
        wanted
            .filter((href) => !present.some((l) => l.href === href))
            .map(
                (href) =>
                    new Promise((resolve) => {
                        const link = document.createElement('link');
                        link.rel = 'stylesheet';
                        link.href = href;
                        link.onload = link.onerror = resolve;
                        setTimeout(resolve, 3000);
                        document.head.appendChild(link);
                    }),
            ),
    );
    // …then the previous page's ones go.
    present.filter((l) => !wanted.includes(l.href)).forEach((l) => l.remove());
}

// ---- Scripts of the new body: page modules run again, the layout's run once.
async function runScripts(body) {
    for (const old of [...body.querySelectorAll('script')]) {
        const type = old.type || 'text/javascript';
        if (type === 'application/json') continue;

        if (type === 'module' && old.src) {
            // Same file, new instance: its top-level code runs again; shared imports (lib/…) don't.
            const url = new URL(old.src);
            url.searchParams.set('nav', String(++runs));
            try {
                await import(/* @vite-ignore */ url.href);
            } catch (e) {
                console.error(e);
            }
            continue;
        }
        if (old.src && loadedScripts.has(old.src)) continue; // Bootstrap and other layout scripts

        await new Promise((resolve) => {
            const script = document.createElement('script');
            [...old.attributes].forEach((a) => script.setAttribute(a.name, a.value));
            if (old.src) {
                loadedScripts.add(old.src);
                script.onload = script.onerror = resolve;
            } else {
                script.textContent = old.textContent;
            }
            old.replaceWith(script);
            if (!old.src) resolve();
        });
    }
}

async function swap(doc) {
    await mergeHead(doc.head);

    endPage(); // timers, listeners, camera and charts of the page being left
    resetPageData();

    const sidebarScroll = document.getElementById('sidebar')?.scrollTop ?? 0;
    const body = document.adoptNode(doc.body);
    document.body.replaceWith(body);
    const sidebar = document.getElementById('sidebar');
    if (sidebar) sidebar.scrollTop = sidebarScroll;

    await runScripts(body);
    document.dispatchEvent(new CustomEvent('page:load'));
}

/** Download a file answer without asking the server again. */
async function save(res) {
    const name = /filename\*?=(?:UTF-8'')?"?([^";]+)"?/i.exec(res.headers.get('content-disposition') ?? '')?.[1];
    const blob = await res.blob();
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = name ? decodeURIComponent(name) : '';
    document.body.appendChild(a);
    a.click();
    a.remove();
    setTimeout(() => URL.revokeObjectURL(a.href), 10000);
}

function scrollAfter(url, { keepScroll, scroll, previousScroll }) {
    if (scroll !== undefined && scroll !== null) return window.scrollTo(0, scroll);
    if (keepScroll) return window.scrollTo(0, previousScroll);
    const target = url.hash && document.getElementById(decodeURIComponent(url.hash.slice(1)));
    if (target) target.scrollIntoView();
    else window.scrollTo(0, 0);
}

/**
 * Show url. action: "push" (link), "replace" (same entry: reload, redirect) or "none"
 * (back / forward: the browser already moved).
 */
async function navigate(href, { action = 'push', keepScroll = false, scroll = null } = {}) {
    const url = new URL(href, location.href);
    // Full reload; after back / forward the address is already there: no new history entry.
    const hardLoad = (to) => (action === 'push' ? location.assign(to) : location.replace(to));
    if (!navigable(url)) {
        hardLoad(url.href);
        return;
    }

    inflight?.abort();
    const request = (inflight = new AbortController());
    const previousScroll = window.scrollY;
    if (action === 'push') history.replaceState({ ...(history.state ?? {}), nav: true, scroll: previousScroll }, '');
    progress(true);

    try {
        const res = await fetch(url.href, {
            headers: { Accept: 'text/html,application/xhtml+xml', 'X-Soft-Navigation': '1' },
            credentials: 'same-origin',
            signal: request.signal,
        });
        const type = res.headers.get('content-type') ?? '';
        if (res.ok && (!type.includes('text/html') || /attachment/i.test(res.headers.get('content-disposition') ?? ''))) {
            if (/attachment/i.test(res.headers.get('content-disposition') ?? '')) await save(res);
            else hardLoad(res.url); // inline PDF, image…: let the browser show it
            return;
        }

        const doc = new DOMParser().parseFromString(await res.text(), 'text/html');
        const sameShell =
            doc.body?.hasAttribute(ROOT) && doc.documentElement.getAttribute('dir') === document.documentElement.getAttribute('dir');
        if (!sameShell) {
            // Login (session expired), error page, other layout or language: the real page.
            hardLoad(res.url || url.href);
            return;
        }
        if (request.signal.aborted) return;

        const final = new URL(res.url || url.href);
        if (final.pathname === url.pathname && final.search === url.search) final.hash = url.hash;
        document.documentElement.lang = doc.documentElement.lang;

        await swap(doc);
        if (action === 'push') history.pushState({ nav: true, scroll: 0 }, '', final.href);
        else if (action === 'replace' || final.href !== location.href) history.replaceState({ nav: true, scroll: 0 }, '', final.href);
        current = location.pathname + location.search;

        scrollAfter(final, { keepScroll, scroll, previousScroll });
        if (!keepScroll) {
            const heading = document.querySelector('.topbar-title');
            heading?.setAttribute('tabindex', '-1');
            heading?.focus({ preventScroll: true });
        }
    } catch (e) {
        if (e.name === 'AbortError') return;
        hardLoad(url.href); // offline, server down…: the browser shows what happened
    } finally {
        if (inflight === request) {
            inflight = null;
            progress(false);
        }
    }
}

/** The link a click opens here, if it may be followed without a full reload. */
function linkOf(e) {
    if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return null;
    const a = e.target.closest('a[href]');
    if (!a || a.closest('[data-no-nav]') || a.hasAttribute('download') || a.hasAttribute('data-bs-toggle')) return null;
    if (a.target && a.target !== '_self') return null;
    const raw = a.getAttribute('href');
    if (!raw || raw.startsWith('#') || /^(javascript|mailto|tel|sms|whatsapp):/i.test(raw)) return null;

    const url = new URL(a.href, location.href);
    // Anchor on the same page: the browser just scrolls.
    if (url.pathname + url.search === current && url.hash) return null;

    return navigable(url) ? url : null;
}

/** GET form (filters, searches) → the URL it would open. */
function formUrl(e) {
    const form = e.target;
    if (e.defaultPrevented || !(form instanceof HTMLFormElement) || form.closest('[data-no-nav]')) return null;
    const submitter = e.submitter;
    const method = (submitter?.getAttribute('formmethod') || form.getAttribute('method') || 'get').toLowerCase();
    const target = submitter?.getAttribute('formtarget') || form.getAttribute('target');
    if (method !== 'get' || (target && target !== '_self')) return null;

    const url = new URL(submitter?.getAttribute('formaction') || form.getAttribute('action') || location.href, location.href);
    let data;
    try {
        data = new FormData(form, submitter ?? undefined);
    } catch {
        data = new FormData(form);
    }
    const query = new URLSearchParams();
    for (const [name, value] of data) if (typeof value === 'string') query.append(name, value);
    url.search = query.toString();
    url.hash = '';

    return navigable(url) ? url : null;
}

export function startNavigation() {
    if (!document.body.hasAttribute(ROOT) || !window.fetch || !window.DOMParser || !history.pushState) return;

    history.scrollRestoration = 'manual';
    history.replaceState({ ...(history.state ?? {}), nav: true, scroll: window.scrollY }, '');
    setVisitor(navigate);

    // Bubble phase: confirmations (capture, behaviors.js) have already had their say.
    document.addEventListener('click', (e) => {
        const url = linkOf(e);
        if (!url) return;
        e.preventDefault();
        navigate(url.href);
    });
    document.addEventListener('submit', (e) => {
        const url = formUrl(e);
        if (!url) return;
        e.preventDefault();
        navigate(url.href);
    });
    window.addEventListener('popstate', (e) => {
        if (!e.state?.nav) return;
        if (location.pathname + location.search === current) {
            window.scrollTo(0, e.state.scroll ?? 0); // only the #anchor changed
            return;
        }
        navigate(location.href, { action: 'none', scroll: e.state.scroll ?? 0 });
    });
}
