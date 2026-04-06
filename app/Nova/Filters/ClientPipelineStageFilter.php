<?php

namespace App\Nova\Filters;

use App\Enums\PipelineStage;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Laravel\Nova\Filters\Filter;
use Laravel\Nova\Http\Requests\NovaRequest;

class ClientPipelineStageFilter extends Filter
{
    /**
     * @var string
     */
    public $name = 'Pipeline Stage';

    public function apply(NovaRequest $request, Builder $query, mixed $value): Builder
    {
        return $query->where('pipeline_stage', $value);
    }

    /**
     * @return array<string, string>
     */
    public function options(NovaRequest $request): array
    {
        return array_flip(PipelineStage::options());
    }
}
