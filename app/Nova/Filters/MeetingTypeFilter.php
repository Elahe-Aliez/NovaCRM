<?php

namespace App\Nova\Filters;

use App\Enums\MeetingType;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Laravel\Nova\Filters\Filter;
use Laravel\Nova\Http\Requests\NovaRequest;

class MeetingTypeFilter extends Filter
{
    /**
     * @var string
     */
    public $name = 'Meeting Type';

    public function apply(NovaRequest $request, Builder $query, mixed $value): Builder
    {
        return $query->where('meeting_type', $value);
    }

    /**
     * @return array<string, string>
     */
    public function options(NovaRequest $request): array
    {
        return array_flip(MeetingType::options());
    }
}
