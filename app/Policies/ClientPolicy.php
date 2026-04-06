<?php

namespace App\Policies;

use App\Models\Client;
use App\Models\User;

class ClientPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Client $client): bool
    {
        return $user->canAccessClient($client);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Client $client): bool
    {
        return $user->canAccessClient($client);
    }

    public function delete(User $user, Client $client): bool
    {
        if ($user->isSalesperson()) {
            return false;
        }

        return $user->canAccessClient($client);
    }

    public function restore(User $user, Client $client): bool
    {
        if ($user->isSalesperson()) {
            return false;
        }

        return $user->canAccessClient($client);
    }

    public function forceDelete(User $user, Client $client): bool
    {
        if ($user->isSalesperson()) {
            return false;
        }

        return $user->canAccessClient($client);
    }
}
