<?php

namespace App\Nova\Filters;

use App\Enums\UserRole;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Laravel\Nova\Filters\Filter;
use Laravel\Nova\Http\Requests\NovaRequest;

class UserRoleFilter extends Filter
{
    /**
     * @var string
     */
    public $name = 'Role';

    public function apply(NovaRequest $request, Builder $query, mixed $value): Builder
    {
        return $query->where('role', $value);
    }

    /**
     * @return array<string, string>
     */
    public function options(NovaRequest $request): array
    {
        return array_flip(UserRole::options());
    }
}
