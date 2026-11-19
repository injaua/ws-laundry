// Credit note form: refund method box and the maximum amount per settlement.
import { pageData } from '../lib/dom';

(function () {
    const box = document.getElementById('methodBox'),
        amount = document.getElementById('amount');
    if (!box) return;
    const { limits } = pageData('credit-note-form');
    function sync() {
        const s = document.querySelector('input[name="settlement"]:checked')?.value;
        box.classList.toggle('d-none', s !== 'refund');
        if (s) amount.max = limits[s];
    }
    document.querySelectorAll('input[name="settlement"]').forEach((r) => r.addEventListener('change', sync));
    sync();
})();
