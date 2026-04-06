<?php

namespace App\Nova\Filters;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Laravel\Nova\Filters\Filter;
use Laravel\Nova\Http\Requests\NovaRequest;

class MeetingAssignedSalespersonFilter extends Filter
{
    /**
     * @var string
     */
    public $name = 'Assigned Salesperson';

    public function apply(NovaRequest $request, Builder $query, mixed $value): Builder
    {
        return $query->where('user_id', $value);
    }

    /**
     * @return array<string, int>
     */
    public function options(NovaRequest $request): array
    {
        $user = $request->user();

        if ($user === null) {
            return [];
        }

        return User::query()
            ->whereIn('id', $user->visibleFilterUserIds())
            ->where('role', UserRole::Salesperson->value)
            ->orderBy('name')
            ->get(['id', 'name', 'email'])
            ->mapWithKeys(fn (User $model) => [sprintf('%s (%s)', $model->name, $model->email) => $model->id])
            ->all();
    }
}
