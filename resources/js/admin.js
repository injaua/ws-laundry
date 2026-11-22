// Back-office layout (layouts/admin): navigation without reloads, theme, sidebar, upload limit, unread chat badge.
// The <body> is swapped on every visit (lib/navigation.js): handlers here are delegated
// to document, and elements are looked up when used, never kept from the first page.
import 'bootstrap'; // data-bs-* components (dropdowns, modals, collapse…), from npm
import './behaviors';
import { appConfig, getJson, storage } from './lib/dom';
import { startNavigation } from './lib/navigation';

startNavigation();

// ---- Light / dark theme
function applyTheme(theme) {
    document.documentElement.setAttribute('data-bs-theme', theme);
    const icon = document.getElementById('darkModeIcon');
    if (icon) icon.className = theme === 'dark' ? 'bi bi-sun' : 'bi bi-moon-stars';
}
applyTheme(storage.get('theme') || 'light');
document.addEventListener('page:load', () => applyTheme(document.documentElement.getAttribute('data-bs-theme') || 'light'));
document.addEventListener('click', (e) => {
    if (!e.target.closest('#darkModeToggle')) return;
    const next = document.documentElement.getAttribute('data-bs-theme') === 'dark' ? 'light' : 'dark';
    applyTheme(next);
    storage.set('theme', next);
});

// ---- Sidebar on phones
function toggleSidebar(force) {
    const sidebar = document.getElementById('sidebar');
    if (!sidebar) return;
    const open = force !== undefined ? force : !sidebar.classList.contains('show');
    sidebar.classList.toggle('show', open);
    document.body.classList.toggle('sidebar-open', open);
    document.getElementById('sidebarToggle')?.setAttribute('aria-expanded', String(open));
}
document.addEventListener('click', (e) => {
    if (e.target.closest('#sidebarToggle')) toggleSidebar();
    else if (e.target.closest('#sidebarClose') || e.target.id === 'sidebarBackdrop') toggleSidebar(false);
    else if (e.target.closest('#sidebar .nav-link:not([data-bs-toggle])')) toggleSidebar(false);
});
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') toggleSidebar(false);
});

// ---- Files above the PHP upload limit are rejected as soon as they are chosen:
// PHP silently drops them, which otherwise looks like the form did nothing.
const { maxUploadBytes, texts = {} } = appConfig();
const formatSize = (bytes) => (bytes >= 1048576 ? `${(bytes / 1048576).toFixed(1)} MB` : `${Math.ceil(bytes / 1024)} KB`);
document.addEventListener('change', (e) => {
    const input = e.target;
    if (!(input instanceof HTMLInputElement) || input.type !== 'file' || !input.files || !maxUploadBytes) return;

    input.parentNode.querySelector('.js-upload-limit')?.remove();
    input.classList.remove('is-invalid');

    const tooBig = [...input.files].find((file) => file.size > maxUploadBytes);
    if (!tooBig) return;

    input.value = '';
    input.classList.add('is-invalid');
    const feedback = document.createElement('div');
    feedback.className = 'invalid-feedback d-block js-upload-limit';
    feedback.textContent = (texts.fileTooLarge ?? '')
        .replace(':name', tooBig.name)
        .replace(':size', formatSize(tooBig.size))
        .replace(':max', formatSize(maxUploadBytes));
    input.insertAdjacentElement('afterend', feedback);
});

// ---- Unread internal chat messages (the badge is only there for chat users)
const chatBadge = () => document.getElementById('chatUnreadBadge');
if (chatBadge()?.dataset.url) {
    setInterval(async () => {
        const badge = chatBadge();
        if (!badge?.dataset.url || document.hidden) return;
        try {
            const data = await getJson(badge.dataset.url);
            badge.textContent = data.count;
            badge.classList.toggle('d-none', !data.count);
        } catch {
            /* transient */
        }
    }, 15000);
}
