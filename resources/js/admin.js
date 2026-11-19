// Back-office layout (layouts/admin): theme, sidebar, upload limit, unread chat badge.
import './behaviors';
import { appConfig, getJson, storage } from './lib/dom';

// ---- Light / dark theme
const themeIcon = document.getElementById('darkModeIcon');
function applyTheme(theme) {
    document.documentElement.setAttribute('data-bs-theme', theme);
    if (themeIcon) themeIcon.className = theme === 'dark' ? 'bi bi-sun' : 'bi bi-moon-stars';
}
applyTheme(storage.get('theme') || 'light');
document.getElementById('darkModeToggle')?.addEventListener('click', () => {
    const next = document.documentElement.getAttribute('data-bs-theme') === 'dark' ? 'light' : 'dark';
    applyTheme(next);
    storage.set('theme', next);
});

// ---- Sidebar on phones
const sidebar = document.getElementById('sidebar');
const sidebarToggle = document.getElementById('sidebarToggle');
function toggleSidebar(force) {
    if (!sidebar) return;
    const open = force !== undefined ? force : !sidebar.classList.contains('show');
    sidebar.classList.toggle('show', open);
    document.body.classList.toggle('sidebar-open', open);
    sidebarToggle?.setAttribute('aria-expanded', String(open));
}
sidebarToggle?.addEventListener('click', () => toggleSidebar());
document.addEventListener('click', (e) => {
    if (e.target.closest('#sidebarClose') || e.target.id === 'sidebarBackdrop') toggleSidebar(false);
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

// ---- Unread internal chat messages
const chatBadge = document.getElementById('chatUnreadBadge');
if (chatBadge?.dataset.url) {
    setInterval(async () => {
        try {
            const data = await getJson(chatBadge.dataset.url);
            chatBadge.textContent = data.count;
            chatBadge.classList.toggle('d-none', !data.count);
        } catch {
            /* transient */
        }
    }, 15000);
}
