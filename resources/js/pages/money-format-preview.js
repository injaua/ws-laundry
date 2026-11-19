// Localization settings: live preview of the price format before saving.
import { pageData } from '../lib/dom';

// Live preview of the price format before saving.
var seps = pageData('money-format').formats;
var form = document.querySelector('form[action$="localization"]');
function preview() {
    var s = seps[form.number_format.value] || ['.', ','];
    var cur = form.currency_code.options[form.currency_code.selectedIndex];
    if (!cur) {
        return;
    }
    var d = parseInt(cur.dataset.decimals, 10) || 0;
    var n = (1234567.5).toFixed(d).split('.');
    n[0] = n[0].replace(/\B(?=(\d{3})+(?!\d))/g, s[1]);
    var text = n.join(s[0]),
        sym = cur.dataset.symbol;
    document.getElementById('money-preview').textContent = cur.dataset.position === 'before' ? sym + ' ' + text : text + ' ' + sym;
}
form.addEventListener('input', preview);
form.addEventListener('change', preview);
