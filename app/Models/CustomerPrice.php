<?php

namespace App\Models;

use App\Models\Traits\BelongsToStore;
use App\Models\Traits\Loggable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A price negotiated with one customer for one service or package. */
class CustomerPrice extends Model
{
    use BelongsToStore;
    use Loggable;

    protected $fillable = ["customer_id", "service_id", "package_id", "price"];

    protected function casts(): array
    {
        return ["price" => "decimal:2"];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    public function item(): Service|Package|null
    {
        return $this->service ?? $this->package;
    }

    /** Catalog price of the item, to show the negotiated difference. */
    public function catalogPrice(): float
    {
        return (float) ($this->item()?->price ?? 0);
    }
}
