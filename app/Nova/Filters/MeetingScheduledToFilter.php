<?php

namespace App\Nova\Filters;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Laravel\Nova\Filters\DateFilter;
use Laravel\Nova\Http\Requests\NovaRequest;

class MeetingScheduledToFilter extends DateFilter
{
    /**
     * @var string
     */
    public $name = 'Scheduled To';

    public function apply(NovaRequest $request, Builder $query, mixed $value): Builder
    {
        return $query->where('scheduled_at', '<=', $value.' 23:59:59');
    }
}
