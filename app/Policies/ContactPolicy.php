<?php

namespace App\Policies;

use App\Models\Contact;
use App\Models\User;

class ContactPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Contact $contact): bool
    {
        return $user->canAccessClient($contact->client);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Contact $contact): bool
    {
        return $user->canAccessClient($contact->client);
    }

    public function delete(User $user, Contact $contact): bool
    {
        if ($user->isSalesperson()) {
            return false;
        }

        return $user->canAccessClient($contact->client);
    }

    public function restore(User $user, Contact $contact): bool
    {
        if ($user->isSalesperson()) {
            return false;
        }

        return $user->canAccessClient($contact->client);
    }

    public function forceDelete(User $user, Contact $contact): bool
    {
        if ($user->isSalesperson()) {
            return false;
        }

        return $user->canAccessClient($contact->client);
    }
}
