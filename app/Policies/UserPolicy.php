<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, User $model): bool
    {
        return $user->canAccessUser($model);
    }

    public function create(User $user): bool
    {
        return $user->canManageTeam();
    }

    public function update(User $user, User $model): bool
    {
        if ($user->isManager()) {
            return true;
        }

        if ($user->isTeamLeader()) {
            return $user->id === $model->id || ($model->manager_id === $user->id && $model->isSalesperson());
        }

        return $user->id === $model->id;
    }

    public function delete(User $user, User $model): bool
    {
        if ($user->id === $model->id) {
            return false;
        }

        if ($user->isManager()) {
            return true;
        }

        if ($user->isTeamLeader()) {
            return $model->manager_id === $user->id && $model->isSalesperson();
        }

        return false;
    }

    public function restore(User $user, User $model): bool
    {
        return $this->delete($user, $model);
    }

    public function forceDelete(User $user, User $model): bool
    {
        return $this->delete($user, $model);
    }
}
