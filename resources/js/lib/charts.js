import Chart from 'chart.js/auto';
import { fmtMoney } from './money';

/** A Chart.js chart that follows the light / dark theme. */
export function chart(canvas, config) {
    const el = typeof canvas === 'string' ? document.getElementById(canvas) : canvas;
    if (!el) return null;

    const isDark = document.documentElement.getAttribute('data-bs-theme') === 'dark';
    Chart.defaults.color = isDark ? '#adb5bd' : '#6c757d';
    Chart.defaults.borderColor = isDark ? 'rgba(255,255,255,0.1)' : 'rgba(0,0,0,0.05)';

    return new Chart(el, config);
}

export const isDarkTheme = () => document.documentElement.getAttribute('data-bs-theme') === 'dark';

/** Y axis ticks in the configured currency. */
export const moneyTicks = { callback: (value) => fmtMoney(value) };
