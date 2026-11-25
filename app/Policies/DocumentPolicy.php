<?php

namespace App\Policies;

use App\Models\Document;
use App\Models\User;
use App\Policies\Concerns\OwnsStoreRecords;

/**
 * Document archive: admin, owner and accountant upload and consult every document of the
 * store; an accountant changes or deletes only the documents they uploaded. A private document
 * (sent through the exchange) is seen only by its uploader and the colleagues it was sent to,
 * and only its uploader changes or deletes it.
 */
class DocumentPolicy
{
    use OwnsStoreRecords;

    private const ROLES = ["admin", "owner", "accountant"];

    public function viewAny(User $user): bool
    {
        return $user->hasRole(self::ROLES);
    }

    public function view(User $user, Document $document): bool
    {
        return $user->hasRole(self::ROLES) && $this->inCurrentStore($document) && $document->isVisibleTo($user);
    }

    public function create(User $user): bool
    {
        return $user->hasRole(self::ROLES);
    }

    public function update(User $user, Document $document): bool
    {
        if ($document->is_private) {
            return $user->hasRole(self::ROLES) && $this->inCurrentStore($document) && $this->isAuthor($user, $document, "uploaded_by");
        }

        return $this->managesOrOwns($user, $document, "uploaded_by", self::ROLES);
    }

    /** Send (or forward) the document to colleagues: anyone who can see it. */
    public function send(User $user, Document $document): bool
    {
        return $this->view($user, $document);
    }

    public function delete(User $user, Document $document): bool
    {
        return $this->update($user, $document);
    }
}
