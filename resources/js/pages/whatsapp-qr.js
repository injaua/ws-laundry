// WhatsApp Web pairing (admin/notifications): QR code and connection state, refreshed every 3 s.
import { pageData } from '../lib/dom';
import { every } from '../lib/page';

(function () {
    const box = document.getElementById('waQr');
    const img = document.getElementById('waQrImg'),
        badge = document.getElementById('waQrState');
    const me = document.getElementById('waQrMe'),
        help = document.getElementById('waQrHelp'),
        logout = document.getElementById('waQrLogout');
    const startForm = document.getElementById('waQrStart'),
        log = document.getElementById('waQrLog');
    const { labels } = pageData('whatsapp-qr');
    async function refresh() {
        try {
            const s = await (await fetch(box.dataset.status, { headers: { Accept: 'application/json' } })).json();
            const [text, cls, hint] = labels[s.status] || labels.offline;
            badge.className = 'badge ' + cls;
            badge.textContent = text;
            help.textContent = hint;
            me.textContent = s.status === 'open' && s.me ? '+' + s.me : '';
            img.classList.toggle('d-none', !(s.status === 'qr' && s.qr));
            if (s.qr) img.src = s.qr;
            logout.classList.toggle('d-none', s.status !== 'open');
            // Service not answering: offer to start it, and show why it stopped (end of its log).
            startForm.classList.toggle('d-none', !(s.status === 'offline' && s.can_start));
            log.classList.toggle('d-none', !s.log);
            log.textContent = s.log || '';
        } catch (e) {
            /* try again */
        }
    }
    refresh();
    every(() => {
        if (!document.hidden) refresh();
    }, 3000);
})();
