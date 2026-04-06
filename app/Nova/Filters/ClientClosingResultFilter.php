<?php

namespace App\Nova\Filters;

use App\Enums\ClosingResult;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Laravel\Nova\Filters\Filter;
use Laravel\Nova\Http\Requests\NovaRequest;

class ClientClosingResultFilter extends Filter
{
    /**
     * @var string
     */
    public $name = 'Closing Result';

    public function apply(NovaRequest $request, Builder $query, mixed $value): Builder
    {
        return $query->where('closing_result', $value);
    }

    /**
     * @return array<string, string>
     */
    public function options(NovaRequest $request): array
    {
        return array_flip(ClosingResult::options());
    }
}
