// Printed documents: thermal tickets, labels, A4 documents.
import './behaviors';
import { storage } from './lib/dom';

// Thermal tickets open in the paper width the cashier picked last time.
const ticket = document.querySelector('[data-thermal-width]');
if (ticket) {
    const url = new URL(location.href);
    const saved = storage.get('thermalWidth');
    if (!url.searchParams.get('w') && saved && saved !== ticket.dataset.thermalWidth) {
        url.searchParams.set('w', saved);
        location.replace(url.toString());
    }
}
