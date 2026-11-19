<?php

namespace App\View\Composers;

use App\Models\DeliveryZone;
use Illuminate\View\View;

/** Delivery zones for the customer's delivery fields (customers._delivery). */
class DeliveryFieldsComposer
{
    public function compose(View $view): void
    {
        $view->with([
            "customer" => $view->getData()["customer"] ?? null,
            "deliveryZones" => DeliveryZone::active()->get(),
        ]);
    }
}
