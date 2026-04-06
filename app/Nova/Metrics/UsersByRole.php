<?php

namespace App\Nova\Metrics;

use App\Enums\UserRole;
use App\Models\User;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Metrics\Partition;
use Laravel\Nova\Metrics\PartitionResult;

class UsersByRole extends Partition
{
    /**
     * @var string
     */
    public $name = 'Users by Role';

    /**
     * Calculate the value of the metric.
     */
    public function calculate(NovaRequest $request): PartitionResult
    {
        $user = $request->user();

        if ($user === null) {
            return $this->result([]);
        }

        return $this->count(
            $request,
            User::query()->whereIn('id', $user->visibleUserDirectoryIds()),
            'role'
        )->label(function (?string $value): string {
            if ($value === null) {
                return 'Unknown';
            }

            $role = UserRole::tryFrom($value);

            return $role?->label() ?? ucfirst(str_replace('_', ' ', $value));
        })->colors([
            UserRole::Manager->value => '#15803d',
            UserRole::TeamLeader->value => '#0f766e',
            UserRole::Salesperson->value => '#1d4ed8',
        ]);
    }

    /**
     * Get the URI key for the metric.
     */
    public function uriKey(): string
    {
        return 'users-by-role';
    }
}
