import { appConfig } from './dom';

const DEFAULTS = { symbol: 'MT', position: 'after', decimals: 2, decimal: ',', thousands: '.' };

/** Money in the browser, with the same settings as the server (Admin → System settings → Localization). */
export function fmtMoney(value, decimals) {
    const c = { ...DEFAULTS, ...(appConfig().money ?? {}) };
    const d = decimals === undefined ? c.decimals : decimals;
    const parts = Math.abs(Number(value) || 0)
        .toFixed(d)
        .split('.');
    parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, c.thousands);
    const text = parts.join(c.decimal);
    const sign = Number(value) < 0 ? '-' : '';

    return sign + (c.position === 'before' ? `${c.symbol} ${text}` : `${text} ${c.symbol}`);
}

/** Round to cents, half away from zero, like App\Support\Money::round() on the server. */
export function roundMoney(value) {
    const n = Number(value) || 0;

    return (Math.sign(n) * Math.round(Math.abs(n) * 100 + 1e-7)) / 100;
}

export const currencySymbol = () => appConfig().money?.symbol ?? DEFAULTS.symbol;
