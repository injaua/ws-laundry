// Promotion form: the value is a percentage or an amount in the currency.
import { currencySymbol } from '../lib/money';

const type = document.getElementById('discount_type');
type?.addEventListener('change', () => {
    document.getElementById('valueUnit').textContent = type.value === 'percent' ? '%' : currencySymbol();
});
