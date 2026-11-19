// New pickup / delivery (logistics/create): picking a customer fills in their saved address and zone.

// Picking a customer fills in their saved address and zone.
document.getElementById('customer_id').addEventListener('change', function () {
    const opt = this.selectedOptions[0];
    if (!opt || !opt.value) return;
    const set = (id, value) => {
        const el = document.getElementById(id);
        if (value) el.value = value;
    };
    set('contact_name', opt.dataset.name);
    set('contact_phone', opt.dataset.phone);
    set('address', opt.dataset.address);
    set('address_reference', opt.dataset.reference);
    set('delivery_zone_id', opt.dataset.zone);
});
