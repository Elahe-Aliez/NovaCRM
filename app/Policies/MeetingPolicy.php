<?php

namespace App\Policies;

use App\Models\Meeting;
use App\Models\User;

class MeetingPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Meeting $meeting): bool
    {
        return $user->canAccessMeeting($meeting);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Meeting $meeting): bool
    {
        return $user->canAccessMeeting($meeting);
    }

    public function delete(User $user, Meeting $meeting): bool
    {
        if ($user->isSalesperson()) {
            return false;
        }

        return $user->canAccessMeeting($meeting);
    }

    public function restore(User $user, Meeting $meeting): bool
    {
        if ($user->isSalesperson()) {
            return false;
        }

        return $user->canAccessMeeting($meeting);
    }

    public function forceDelete(User $user, Meeting $meeting): bool
    {
        if ($user->isSalesperson()) {
            return false;
        }

        return $user->canAccessMeeting($meeting);
    }
}
