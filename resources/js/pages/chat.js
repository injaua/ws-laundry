// Internal chat (messages/index): direct / meeting choice, new messages every 4 s.
import { esc } from '../lib/dom';

// Direct = single choice; meeting = multi choice
const checks = document.querySelectorAll('.participant-check');
document.querySelectorAll('input[name="type"]').forEach((radio) =>
    radio.addEventListener('change', function () {
        const isMeeting = this.value === 'meeting';
        document.getElementById('meetingTitleWrap').classList.toggle('d-none', !isMeeting);
    }),
);
document.querySelectorAll('.user-list-item').forEach((label) => {
    label.addEventListener('click', function (ev) {
        if (!document.getElementById('typeDirect').checked) return;
        ev.preventDefault();
        const box = this.querySelector('input');
        const wasChecked = box.checked;
        checks.forEach((c) => {
            c.checked = false;
        });
        box.checked = !wasChecked;
    });
});

const body = document.getElementById('threadBody');
if (body) {
    let lastId = Number(body.dataset.lastId || 0);
    const nearBottom = () => body.scrollHeight - body.scrollTop - body.clientHeight < 120;

    setInterval(async function () {
        if (document.hidden) return;
        try {
            const res = await fetch(body.dataset.pollUrl + '?since=' + lastId, {
                headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '' },
            });
            if (!res.ok) return;
            const data = await res.json();
            if (!data.messages.length) return;
            const stick = nearBottom();
            for (const m of data.messages) {
                lastId = Math.max(lastId, m.id);
                const div = document.createElement('div');
                div.className = 'bubble ' + (m.mine ? 'bubble-mine' : 'bubble-other');
                div.innerHTML =
                    (m.mine ? '' : '<div class="fw-semibold small">' + esc(m.user || '') + '</div>') +
                    '<div>' +
                    esc(m.body) +
                    '</div><div class="meta">' +
                    esc(m.at) +
                    '</div>';
                body.appendChild(div);
            }
            if (stick) body.scrollTop = body.scrollHeight;
        } catch (e) {
            /* transient network error */
        }
    }, 4000);

    body.scrollTop = body.scrollHeight;
}
