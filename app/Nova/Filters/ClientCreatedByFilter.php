<?php

namespace App\Nova\Filters;

use App\Models\User;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Laravel\Nova\Filters\Filter;
use Laravel\Nova\Http\Requests\NovaRequest;

class ClientCreatedByFilter extends Filter
{
    /**
     * @var string
     */
    public $name = 'Created By';

    public function apply(NovaRequest $request, Builder $query, mixed $value): Builder
    {
        return $query->where('created_by_id', $value);
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
            ->orderBy('name')
            ->get(['id', 'name', 'email'])
            ->mapWithKeys(fn (User $model) => [sprintf('%s (%s)', $model->name, $model->email) => $model->id])
            ->all();
    }
}
