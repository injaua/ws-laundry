import { roundMoney } from '../lib/money';

/*
 * Price quotes in the order form: same rules as App\Services\Pricing (the server has the final word).
 * The lowest of the customer's price and the catalogue price less the best running promotion wins;
 * discounts never stack.
 */
export function createPricing({ customerPricing = {}, promotions = [] }) {
    /** Negotiated price, or the catalogue price less the customer's general discount. */
    function customerPrice(customerId, kind, id, base) {
        const rules = customerPricing[customerId];
        if (!rules) return parseFloat(base);
        const negotiated = (kind === 'service' ? rules.services : rules.packages)[id];
        if (negotiated !== undefined) return parseFloat(negotiated);

        return rules.discount > 0 ? roundMoney((parseFloat(base) * (100 - rules.discount)) / 100) : parseFloat(base);
    }

    function promotionRunsOn(p, date) {
        if (!date || date < p.starts || (p.ends && date > p.ends)) return false;
        if (!p.weekdays) return true;

        return p.weekdays.includes(new Date(`${date}T00:00:00`).getDay());
    }

    /** { price, promo, list } for one catalogue line, for a customer on an entry date. */
    function quote({ customerId, date }, kind, id, base) {
        base = parseFloat(base);
        const own = customerPrice(customerId, kind, id, base);
        let best = { price: base, promo: null };
        promotions.forEach((p) => {
            if (!promotionRunsOn(p, date)) return;
            if (!p.all && !(kind === 'service' ? p.services : p.packages).includes(parseInt(id, 10))) return;
            const price = Math.max(0, p.type === 'percent' ? roundMoney((base * (100 - p.value)) / 100) : base - p.value);
            if (price < best.price) best = { price, promo: p.name };
        });

        return best.promo && best.price < own
            ? { price: best.price, promo: best.promo, list: base }
            : { price: own, promo: null, list: base };
    }

    return { quote, hasSpecialPrices: (customerId) => Boolean(customerPricing[customerId]) };
}

/** Express surcharge, pickup trip and VAT on top of the items. */
export function orderTotals({ itemsSubtotal, express, expressOn, pickup, vatRate }) {
    const expressApplies = Boolean(express?.enabled && expressOn && itemsSubtotal > 0);
    const expressFee = expressApplies ? roundMoney(express.type === 'fixed' ? express.value : (itemsSubtotal * express.value) / 100) : 0;
    // The pickup trip is billed on this order, free over the zone threshold (as on the server).
    const pickupFee = pickup && !(pickup.free_from !== null && itemsSubtotal >= pickup.free_from) ? pickup.fee : 0;
    const subtotal = roundMoney(itemsSubtotal + expressFee + pickupFee);
    const vat = roundMoney((subtotal * (vatRate || 0)) / 100);

    return { expressApplies, expressFee, pickupFee, subtotal, vat, total: roundMoney(subtotal + vat) };
}

export const addDays = (date, days) => {
    const d = new Date(`${date}T00:00:00`);
    d.setDate(d.getDate() + days);

    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
};
