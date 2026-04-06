<?php

namespace App\Nova;

use App\Enums\ClosingResult;
use App\Enums\PipelineStage;
use App\Nova\Filters\ClientClosingResultFilter;
use App\Nova\Filters\ClientCreatedByFilter;
use App\Nova\Filters\ClientCreatedFromFilter;
use App\Nova\Filters\ClientCreatedToFilter;
use App\Nova\Filters\ClientPipelineStageFilter;
use App\Nova\Actions\ExportToCsv;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\HasMany;
use Laravel\Nova\Fields\Hidden;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\Textarea;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Panel;

class Client extends Resource
{
    /**
     * The model the resource corresponds to.
     *
     * @var class-string<\App\Models\Client>
     */
    public static $model = \App\Models\Client::class;

    /**
     * The single value that should be used to represent the resource when being displayed.
     *
     * @var string
     */
    public static $title = 'business_name';

    /**
     * The columns that should be searched.
     *
     * @var array<int, string>
     */
    public static $search = [
        'id',
        'business_name',
        'address',
    ];

    /**
     * Get the fields displayed by the resource.
     *
     * @return array<int, \Laravel\Nova\Fields\Field|\Laravel\Nova\Panel|\Laravel\Nova\ResourceTool|\Illuminate\Http\Resources\MergeValue>
     */
    public function fields(NovaRequest $request): array
    {
        return [
            ID::make()->sortable(),

            Text::make('Business Name', 'business_name')
                ->sortable()
                ->rules('required', 'max:255'),

            Text::make('Address')
                ->hideFromIndex()
                ->rules('nullable', 'max:255'),

            Select::make('Pipeline Stage', 'pipeline_stage')
                ->options(PipelineStage::options())
                ->displayUsingLabels()
                ->sortable()
                ->rules('required')
                ->hideFromDetail(),

            Select::make('Closing Result', 'closing_result')
                ->options(ClosingResult::options())
                ->displayUsingLabels()
                ->nullable()
                ->rules('nullable', 'required_if:pipeline_stage,'.PipelineStage::Closed->value)
                ->hideFromDetail(),

            Textarea::make('Closing Comment', 'pipeline_comment')
                ->rules('required_if:pipeline_stage,'.PipelineStage::Closed->value)
                ->fillUsing(fn () => null)
                ->onlyOnForms(),

            Panel::make('Pipeline', [
                Text::make('Stage', fn () => $this->pipelineTimelineHtml())
                    ->asHtml()
                    ->onlyOnDetail(),
                HasMany::make('Pipeline Activity Log', 'pipelineActivities', \App\Nova\PipelineActivity::class)
                    ->onlyOnDetail(),
            ]),

            ...$this->ownerFields($request),

            BelongsTo::make('Created By', 'creator', User::class)
                ->exceptOnForms(),

            HasMany::make('Contacts')
                ->onlyOnDetail(),
        ];
    }

    protected function pipelineTimelineHtml(): string
    {
        $currentStage = $this->pipeline_stage?->value ?? PipelineStage::Lead->value;
        $closingResult = $this->closing_result?->value ?? '';
        $stageOrder = [
            PipelineStage::Lead->value,
            PipelineStage::Interested->value,
            PipelineStage::Negotiation->value,
            PipelineStage::Closed->value,
        ];

        $currentIndex = array_search($currentStage, $stageOrder, true);
        if ($currentIndex === false) {
            $currentIndex = 0;
        }

        $labels = PipelineStage::options();
        $html = sprintf(
            '<ol class="crm-pipeline" data-crm-pipeline-client="%s" data-crm-pipeline-current="%s" data-crm-pipeline-closing-result="%s" data-crm-pipeline-business-name="%s" data-crm-pipeline-address="%s" data-crm-pipeline-owner-id="%s">',
            e((string) $this->getKey()),
            e($currentStage),
            e($closingResult),
            e((string) $this->business_name),
            e((string) ($this->address ?? '')),
            e((string) ($this->owner_id ?? ''))
        );

        foreach ($stageOrder as $index => $value) {
            $stateClass = 'crm-pipeline__item--disabled';

            if ($index < $currentIndex) {
                $stateClass = 'crm-pipeline__item--complete';
            } elseif ($index === $currentIndex) {
                $stateClass = 'crm-pipeline__item--current';
            }

            $label = $labels[$value] ?? $value;
            $html .= sprintf(
                '<li class="crm-pipeline__item %s"><button type="button" class="crm-pipeline__button" data-crm-pipeline-stage="%s">%s</button></li>',
                $stateClass,
                e($value),
                e($label)
            );
        }

        $html .= '</ol>';

        return $html;
    }

    /**
     * @return array<int, \Laravel\Nova\Fields\Field>
     */
    protected function ownerFields(NovaRequest $request): array
    {
        if ($request->user()?->isSalesperson()) {
            return [
                Hidden::make('Owner', 'owner_id')
                    ->default(fn (NovaRequest $novaRequest) => $novaRequest->user()?->id)
                    ->rules('required'),
            ];
        }

        return [
            BelongsTo::make('Owner', 'owner', User::class)
                ->default(fn (NovaRequest $novaRequest) => $novaRequest->user()?->id)
                ->rules('required'),
        ];
    }

    /**
     * Build an "index" query for the given resource.
     */
    public static function indexQuery(NovaRequest $request, Builder $query): Builder
    {
        $user = $request->user();

        if ($user === null) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn('owner_id', $user->visibleDataOwnerIds());
    }

    /**
     * Build a "detail" query for the given resource.
     */
    public static function detailQuery(NovaRequest $request, Builder $query): Builder
    {
        return static::indexQuery($request, $query);
    }

    /**
     * Build a "relatable" query for the given resource.
     */
    public static function relatableQuery(NovaRequest $request, Builder $query): Builder
    {
        return static::indexQuery($request, $query);
    }

    /**
     * Build a "relatable" query for the owner relation.
     */
    public static function relatableOwners(NovaRequest $request, Builder $query): Builder
    {
        $user = $request->user();

        if ($user === null) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn('id', $user->visibleDataOwnerIds());
    }

    /**
     * Get the cards available for the request.
     *
     * @return array<int, \Laravel\Nova\Card>
     */
    public function cards(NovaRequest $request): array
    {
        return [];
    }

    /**
     * Get the filters available for the resource.
     *
     * @return array<int, \Laravel\Nova\Filters\Filter>
     */
    public function filters(NovaRequest $request): array
    {
        return [
            new ClientPipelineStageFilter,
            new ClientClosingResultFilter,
            new ClientCreatedByFilter,
            new ClientCreatedFromFilter,
            new ClientCreatedToFilter,
        ];
    }

    /**
     * Get the lenses available for the resource.
     *
     * @return array<int, \Laravel\Nova\Lenses\Lens>
     */
    public function lenses(NovaRequest $request): array
    {
        return [];
    }

    /**
     * Get the actions available for the resource.
     *
     * @return array<int, \Laravel\Nova\Actions\Action>
     */
    public function actions(NovaRequest $request): array
    {
        $stageLabels = PipelineStage::options();
        $closingLabels = ClosingResult::options();

        return [
            (new ExportToCsv(
                'businesses-'.now()->format('Ymd-His'),
                [
                    'ID' => 'id',
                    'Business Name' => 'business_name',
                    'Address' => 'address',
                    'Pipeline Stage' => fn (\App\Models\Client $client): string => $stageLabels[$client->pipeline_stage?->value ?? ''] ?? '',
                    'Closing Result' => fn (\App\Models\Client $client): string => $closingLabels[$client->closing_result?->value ?? ''] ?? '',
                    'Owner' => fn (\App\Models\Client $client): string => $client->owner?->name ?? '',
                    'Created At' => 'created_at',
                ],
                ['owner']
            ))->onlyOnIndex(),
        ];
    }
}
