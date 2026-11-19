// Edit order: the delivery date follows the estimated / completion date unless typed by hand.

// The delivery date follows the estimated / completion date unless typed by hand
const deliveryInput = document.getElementById('delivery_date');
const completionInput = document.getElementById('completed_at');
let manualDelivery = deliveryInput.value !== '';

deliveryInput.addEventListener('change', () => {
    manualDelivery = deliveryInput.value !== '';
});
completionInput.addEventListener('change', () => {
    if (!manualDelivery && completionInput.value) deliveryInput.value = completionInput.value;
});
