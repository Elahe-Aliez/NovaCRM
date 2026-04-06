<?php

namespace App\Nova\Filters;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Laravel\Nova\Filters\DateFilter;
use Laravel\Nova\Http\Requests\NovaRequest;

class MeetingScheduledFromFilter extends DateFilter
{
    /**
     * @var string
     */
    public $name = 'Scheduled From';

    public function apply(NovaRequest $request, Builder $query, mixed $value): Builder
    {
        return $query->where('scheduled_at', '>=', $value.' 00:00:00');
    }
}
