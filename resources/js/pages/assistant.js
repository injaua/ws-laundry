// AI assistant: sends the question over AJAX, shows it at once, then the answer (HTML made safe
// on the server); the first answer of a new conversation gives it its address. "New" starts an
// empty conversation without reloading; an example question is sent as if typed.
import { csrfToken, esc } from '../lib/dom';
import { on } from '../lib/page';

const form = document.getElementById('aiForm');
const box = document.getElementById('aiMessages');
const question = document.getElementById('aiQuestion');
const send = document.getElementById('aiSend');
const ready = form?.dataset.ready === '1';
let busy = false;

const scrollDown = () => box && (box.scrollTop = box.scrollHeight);

function bubble(role, html, extra = '') {
    document.getElementById('aiWelcome')?.remove();
    const el = document.createElement('div');
    el.className = `ai-msg ai-msg-${role} ${extra}`;
    el.innerHTML = `<div class="ai-bubble">${html}</div>`;
    box.appendChild(el);
    scrollDown();

    return el;
}

/** Not configured: point at the warning instead of doing nothing. */
function showNotReady() {
    const warning = document.getElementById('aiNotReady');
    if (!warning) return;
    warning.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
    warning.classList.remove('ai-attention');
    void warning.offsetWidth; // restart the animation
    warning.classList.add('ai-attention');
}

function setBusy(value) {
    busy = value;
    send.disabled = value;
    question.disabled = value;
}

async function ask(text) {
    if (!ready) return showNotReady();
    text = text.trim();
    if (!text || busy) return;

    bubble('user', esc(text).replace(/\n/g, '<br>'));
    const waiting = bubble('assistant', `<span class="ai-typing"><span></span><span></span><span></span></span> ${esc(form.dataset.thinking)}`, 'ai-msg-wait');
    question.value = '';
    setBusy(true);

    try {
        const response = await fetch(form.action, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken() },
            body: JSON.stringify({ question: text, conversation: form.dataset.conversation || null }),
        });
        const data = await response.json().catch(() => ({}));
        waiting.remove();
        if (!response.ok) {
            const message = data.error ?? data.message ?? Object.values(data.errors ?? {})[0]?.[0] ?? form.dataset.error;
            bubble('assistant', esc(message), 'ai-msg-failed');
            return;
        }
        bubble('assistant', data.html, data.failed ? 'ai-msg-failed' : '');
        if (!form.dataset.conversation && data.conversation) {
            form.dataset.conversation = data.conversation;
            history.replaceState(history.state, '', data.url);
        }
    } catch {
        waiting.remove();
        bubble('assistant', esc(form.dataset.error), 'ai-msg-failed');
    } finally {
        setBusy(false);
        question.focus();
    }
}

/** Empty conversation, on this page. */
function startNew() {
    form.dataset.conversation = '';
    box.innerHTML = document.getElementById('aiWelcomeTemplate')?.innerHTML ?? '';
    document.querySelectorAll('.ai-conversations .list-group-item.active').forEach((el) => el.classList.remove('active'));
    history.replaceState(history.state, '', form.dataset.newUrl);
    question.value = '';
    if (ready) question.focus();
}

if (form) {
    on(form, 'submit', (e) => {
        e.preventDefault();
        ask(question.value);
    });
    // Enter sends, Shift+Enter breaks the line.
    on(question, 'keydown', (e) => {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            ask(question.value);
        }
    });
    on(document, 'click', (e) => {
        const suggestion = e.target.closest('.ai-suggestion');
        if (suggestion) {
            e.preventDefault();
            ask(suggestion.dataset.question);
        }
    });
    // On the link itself: it runs before the page navigation (listening on document).
    const newButton = document.getElementById('aiNew');
    if (newButton) {
        on(newButton, 'click', (e) => {
            e.preventDefault();
            if (!busy) startNew();
        });
    }
    scrollDown();
    if (ready && question.value) question.focus();
}
