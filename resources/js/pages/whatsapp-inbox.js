// Customer WhatsApp thread (messages/whatsapp): new messages and delivery ticks every 5 s.
import { esc } from '../lib/dom';
import { every, reloadPage } from '../lib/page';

(function () {
    const box = document.getElementById('waThread');
    if (!box) return;
    box.scrollTop = box.scrollHeight;

    const ticks = {
        queued: '<i class="bi bi-clock"></i>',
        sent: '<i class="bi bi-check2"></i>',
        delivered: '<i class="bi bi-check2-all"></i>',
        read: '<i class="bi bi-check2-all tick-read"></i>',
        failed: '<i class="bi bi-exclamation-circle text-danger"></i>',
        manual: '<i class="bi bi-phone"></i>',
    };
    let last = Number(box.dataset.lastId || 0);

    function render(m) {
        const out = m.direction === 'out';
        const el = document.createElement('div');
        el.className = 'wa-bubble ' + (out ? 'wa-out' : 'wa-in') + (m.status === 'failed' ? ' failed' : '');
        el.dataset.id = m.id;
        el.innerHTML =
            (m.automatic
                ? '<div class="small text-muted mb-1"><i class="bi bi-robot"></i> ' + esc(box.dataset.automaticLabel) + '</div>'
                : '') +
            '<div class="text">' +
            esc(m.body ?? '[' + m.type + ']') +
            '</div>' +
            '<div class="meta">' +
            (out && m.sender ? esc(m.sender) + ' · ' : '') +
            esc(m.at) +
            (out ? '<span class="tick ms-1">' + (ticks[m.status] || '') + '</span>' : '') +
            '</div>' +
            (m.status === 'failed' && m.error ? '<div class="small text-danger mt-1">' + esc(m.error) + '</div>' : '');
        return el;
    }

    every(async function () {
        if (document.hidden) return;
        try {
            const res = await fetch(box.dataset.poll + '?since=' + last, { headers: { Accept: 'application/json' } });
            if (!res.ok) return;
            const data = await res.json();
            const atBottom = box.scrollHeight - box.scrollTop - box.clientHeight < 60;
            box.querySelector('.text-center.text-muted')?.remove();
            data.messages.forEach((m) => {
                box.appendChild(render(m));
                last = Math.max(last, m.id);
            });
            Object.entries(data.statuses || {}).forEach(([id, status]) => {
                const t = box.querySelector('[data-id="' + id + '"] .tick');
                if (t) t.innerHTML = ticks[status] || '';
            });
            if (data.messages.length && atBottom) box.scrollTop = box.scrollHeight;
            // The customer just wrote back: the 24h window reopened, show the reply box.
            if (data.canReply && !document.querySelector('form[action$="/reply"]')) reloadPage();
        } catch (e) {
            /* offline: try again on the next tick */
        }
    }, 5000);
})();
