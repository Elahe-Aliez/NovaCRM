<?php

namespace App\Nova\Filters;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Laravel\Nova\Filters\DateFilter;
use Laravel\Nova\Http\Requests\NovaRequest;

class ClientCreatedFromFilter extends DateFilter
{
    /**
     * @var string
     */
    public $name = 'Created From';

    public function apply(NovaRequest $request, Builder $query, mixed $value): Builder
    {
        return $query->whereDate('created_at', '>=', $value);
    }
}
