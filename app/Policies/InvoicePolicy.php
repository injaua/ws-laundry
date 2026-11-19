<?php

namespace App\Policies;

use App\Models\Invoice;
use App\Models\User;
use App\Policies\Concerns\OwnsStoreRecords;

/**
 * Consolidated invoices: an accountant issues, edits (removes orders) or voids only
 * the invoices they drafted. Receiving a customer's payment against an invoice is
 * a new record, so any role with access may do it.
 */
class InvoicePolicy
{
    use OwnsStoreRecords;

    private const ROLES = ["admin", "owner", "accountant"];

    public function viewAny(User $user): bool
    {
        return $user->hasRole(self::ROLES);
    }

    public function view(User $user, Invoice $invoice): bool
    {
        return $user->hasRole(self::ROLES) && $this->inCurrentStore($invoice);
    }

    public function create(User $user): bool
    {
        return $user->hasRole(self::ROLES);
    }

    /** Issue the draft or remove orders from it. */
    public function update(User $user, Invoice $invoice): bool
    {
        return $this->managesOrOwns($user, $invoice, "created_by", self::ROLES);
    }

    public function void(User $user, Invoice $invoice): bool
    {
        return $this->update($user, $invoice);
    }

    public function pay(User $user, Invoice $invoice): bool
    {
        return $this->view($user, $invoice);
    }
}
