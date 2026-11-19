// Scan station (tracking/index): one stage, a barcode reader (keyboard or camera), instant feedback.
// Server data: <x-page-data name="tracking">.
import { csrfToken, esc, pageData, storage } from '../lib/dom';

const { texts: T, urls } = pageData('tracking');
const scanUrl = urls.scan,
    lookupUrl = urls.lookup;
const csrf = csrfToken();
const input = document.getElementById('scanCode'),
    result = document.getElementById('scanResult');
const recent = document.getElementById('recent');
const buttons = document.querySelectorAll('[data-stage]');

let stage = null;
stage = storage.get('tracking.stage');
function selectStage(value) {
    stage = value;
    buttons.forEach((b) => {
        const on = b.dataset.stage === value;
        b.classList.toggle('active', on);
        b.setAttribute('aria-pressed', on ? 'true' : 'false');
    });
    storage.set('tracking.stage', value);
    input.focus();
}
buttons.forEach((b) => b.addEventListener('click', () => selectStage(b.dataset.stage)));
if (stage && document.querySelector('[data-stage="' + stage + '"]')) selectStage(stage);

// Short beeps so the operator does not need to look at the screen.
let audio;
function beep(ok) {
    try {
        audio = audio || new (window.AudioContext || window.webkitAudioContext)();
        const tones = ok ? [880] : [220, 180];
        tones.forEach((f, i) => {
            const o = audio.createOscillator(),
                g = audio.createGain();
            o.frequency.value = f;
            o.connect(g);
            g.connect(audio.destination);
            g.gain.value = 0.08;
            o.start(audio.currentTime + i * 0.18);
            o.stop(audio.currentTime + i * 0.18 + 0.12);
        });
    } catch (e) {}
}

function progressHtml(p) {
    if (!p || !p.total) return '';
    const pct = Math.round((p.ready / p.total) * 100);
    const stages = p.stages
        .map((s) => '<span class="badge bg-light text-dark border me-1">' + esc(s.label) + ': ' + s.count + '</span>')
        .join('');
    return (
        '<div class="mt-2"><div class="small mb-1">' +
        esc(T.readyOf.replace(':ready', p.ready).replace(':total', p.total)) +
        '</div>' +
        '<div class="progress mb-1" style="height:8px"><div class="progress-bar bg-success" style="width:' +
        pct +
        '%"></div></div>' +
        stages +
        '</div>'
    );
}

function show(ok, html) {
    result.className = 'card scan-result mb-3 border-2 ' + (ok ? 'border-success' : 'border-danger');
    result.innerHTML = '<div class="card-body">' + html + '</div>';
}

function addRecent(ok, code, text) {
    document.getElementById('recentEmpty')?.remove();
    const li = document.createElement('li');
    li.className = 'list-group-item d-flex gap-2';
    li.innerHTML =
        '<i class="bi ' +
        (ok ? 'bi-check-circle text-success' : 'bi-x-circle text-danger') +
        '"></i>' +
        '<span class="font-monospace text-nowrap">' +
        esc(code) +
        '</span><span class="text-muted text-truncate">' +
        esc(text) +
        '</span>' +
        '<span class="ms-auto text-muted text-nowrap">' +
        new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) +
        '</span>';
    recent.prepend(li);
    while (recent.children.length > 20) recent.lastChild.remove();
}

async function submit(code) {
    code = code.trim().toUpperCase();
    if (!code) return;
    if (!stage) {
        show(false, '<div class="big text-danger">' + esc(T.chooseStage) + '</div>');
        beep(false);
        return;
    }
    input.value = '';

    try {
        if (stage === 'lookup') {
            const res = await fetch(lookupUrl + '?code=' + encodeURIComponent(code), { headers: { Accept: 'application/json' } });
            const d = await res.json();
            if (!res.ok) {
                show(false, '<div class="big text-danger">' + esc(d.message) + '</div>');
                beep(false);
                return;
            }
            const events = d.events
                .map(
                    (e) =>
                        '<li>' +
                        esc(e.at) +
                        ' — <strong>' +
                        esc(e.stage) +
                        '</strong>' +
                        (e.user ? ' · ' + esc(e.user) : '') +
                        (e.note ? ' <span class="text-muted">(' + esc(e.note) + ')</span>' : '') +
                        '</li>',
                )
                .join('');
            show(
                true,
                '<div class="big"><span class="font-monospace">' +
                    esc(d.code) +
                    '</span> · ' +
                    esc(d.description) +
                    ' <span class="badge bg-primary">' +
                    esc(d.status) +
                    '</span></div>' +
                    '<div class="small">' +
                    esc(T.order) +
                    ' <a href="' +
                    esc(d.order.url) +
                    '">' +
                    esc(d.order.number) +
                    '</a> · ' +
                    esc(d.order.customer) +
                    '</div>' +
                    (d.intake_notes
                        ? '<div class="small mt-1"><strong>' + esc(T.intake) + ':</strong> ' + esc(d.intake_notes) + '</div>'
                        : '') +
                    (d.issue
                        ? '<div class="small text-danger mt-1"><strong>' + esc(T.issue) + ':</strong> ' + esc(d.issue) + '</div>'
                        : '') +
                    progressHtml(d.order.progress) +
                    '<div class="small fw-semibold mt-2">' +
                    esc(T.history) +
                    '</div><ul class="small mb-0">' +
                    events +
                    '</ul>',
            );
            beep(true);
            return;
        }

        const res = await fetch(scanUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
            body: JSON.stringify({ code, stage }),
        });
        const d = await res.json();
        const message = d.message || (d.errors ? Object.values(d.errors).flat().join(' ') : T.error);
        const warnings = (d.warnings || [])
            .map((w) => '<div class="alert alert-warning py-1 px-2 small mb-1 mt-2">' + esc(w) + '</div>')
            .join('');
        const order = d.order
            ? '<div class="small mt-1">' +
              esc(T.order) +
              ' <a href="' +
              esc(d.order.url) +
              '">' +
              esc(d.order.number) +
              '</a> · ' +
              esc(d.order.customer) +
              ' · <span class="badge bg-secondary">' +
              esc(d.order.status) +
              '</span></div>' +
              progressHtml(d.order.progress)
            : '';
        show(
            d.ok,
            '<div class="big ' +
                (d.ok ? 'text-success' : 'text-danger') +
                '"><i class="bi ' +
                (d.ok ? 'bi-check-circle' : 'bi-x-circle') +
                '"></i> ' +
                esc(message) +
                '</div>' +
                order +
                warnings,
        );
        addRecent(d.ok, code, message);
        beep(d.ok && !(d.warnings || []).length);
    } catch (e) {
        show(false, '<div class="big text-danger">' + esc(T.network) + '</div>');
        beep(false);
    } finally {
        input.focus();
    }
}

document.getElementById('scanForm').addEventListener('submit', (e) => {
    e.preventDefault();
    submit(input.value);
});

// Phone/tablet camera, where the browser can read barcodes natively.
if ('BarcodeDetector' in window && navigator.mediaDevices?.getUserMedia) {
    const btn = document.getElementById('cameraBtn'),
        video = document.getElementById('scanVideo');
    btn.classList.remove('d-none');
    let stream = null,
        last = '',
        lastAt = 0;
    btn.addEventListener('click', async () => {
        if (stream) {
            stream.getTracks().forEach((t) => t.stop());
            stream = null;
            video.classList.add('d-none');
            return;
        }
        try {
            const detector = new BarcodeDetector({ formats: ['code_128', 'qr_code'] });
            stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } });
            video.srcObject = stream;
            video.classList.remove('d-none');
            await video.play();
            const tick = async () => {
                if (!stream) return;
                try {
                    const codes = await detector.detect(video);
                    const value = codes[0]?.rawValue;
                    // The same tag stays in front of the camera: read it once.
                    if (value && (value !== last || Date.now() - lastAt > 4000)) {
                        last = value;
                        lastAt = Date.now();
                        submit(value);
                    }
                } catch (e) {}
                setTimeout(tick, 300);
            };
            tick();
        } catch (e) {
            stream = null;
        }
    });
}
