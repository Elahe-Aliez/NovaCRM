<?php

namespace App\Nova\Filters;

use App\Models\User;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Laravel\Nova\Filters\Filter;
use Laravel\Nova\Http\Requests\NovaRequest;

class UserReportsToFilter extends Filter
{
    /**
     * @var string
     */
    public $name = 'Reports To';

    public function apply(NovaRequest $request, Builder $query, mixed $value): Builder
    {
        if ($value === 'none') {
            return $query->whereNull('manager_id');
        }

        return $query->where('manager_id', $value);
    }

    /**
     * @return array<string, int|string>
     */
    public function options(NovaRequest $request): array
    {
        $user = $request->user();

        if ($user === null) {
            return [];
        }

        $options = User::query()
            ->whereIn('id', $user->visibleFilterUserIds())
            ->orderBy('name')
            ->get(['id', 'name', 'email'])
            ->mapWithKeys(fn (User $model) => [sprintf('%s (%s)', $model->name, $model->email) => $model->id])
            ->all();

        return ['No Reports To' => 'none', ...$options];
    }
}
