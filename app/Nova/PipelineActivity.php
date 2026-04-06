<?php

namespace App\Nova;

use App\Enums\ClosingResult;
use App\Enums\PipelineStage;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;

class PipelineActivity extends Resource
{
    /**
     * @var bool
     */
    public static $displayInNavigation = false;

    /**
     * The model the resource corresponds to.
     *
     * @var class-string<\App\Models\PipelineActivity>
     */
    public static $model = \App\Models\PipelineActivity::class;

    /**
     * The single value that should be used to represent the resource when being displayed.
     *
     * @var string
     */
    public static $title = 'action';

    /**
     * The columns that should be searched.
     *
     * @var array<int, string>
     */
    public static $search = [
        'id',
        'action',
    ];

    public static function authorizedToCreate(Request $request): bool
    {
        return false;
    }

    public function authorizedToUpdate(Request $request): bool
    {
        return false;
    }

    public function authorizedToDelete(Request $request): bool
    {
        return false;
    }

    public function authorizedToRestore(Request $request): bool
    {
        return false;
    }

    public function authorizedToForceDelete(Request $request): bool
    {
        return false;
    }

    public function authorizedToReplicate(Request $request): bool
    {
        return false;
    }

    /**
     * @return array<int, \Laravel\Nova\Fields\Field|\Laravel\Nova\Panel|\Laravel\Nova\ResourceTool|\Illuminate\Http\Resources\MergeValue>
     */
    public function fields(NovaRequest $request): array
    {
        return [
            ID::make()->sortable(),

            Text::make('Action', fn (): string => match ($this->action) {
                'created' => 'Created (Lead)',
                'stage_changed' => 'Stage Changed',
                'closing_result_updated' => 'Closing Result Updated',
                default => ucfirst(str_replace('_', ' ', (string) $this->action)),
            })->sortable(),

            DateTime::make('Date & Time', 'occurred_at')
                ->sortable(),

            BelongsTo::make('Updated By', 'actor', User::class)
                ->nullable(),

            Text::make('Closing Result', fn (): string => $this->closingResultBadge())
                ->asHtml()
                ->onlyOnIndex(),

            Text::make('Comment', fn (): string => $this->comment ?? '—')
                ->onlyOnIndex(),

            Text::make('From Stage', fn (): string => $this->stageLabel($this->from_stage))
                ->onlyOnDetail(),

            Text::make('To Stage', fn (): string => $this->stageLabel($this->to_stage))
                ->onlyOnDetail(),

            Text::make('From Closing Result', fn (): string => $this->closingResultLabel($this->from_closing_result))
                ->onlyOnDetail(),

            Text::make('To Closing Result', fn (): string => $this->closingResultLabel($this->to_closing_result))
                ->onlyOnDetail(),

            Text::make('Comment', fn (): string => $this->comment ?? '—')
                ->onlyOnDetail(),
        ];
    }

    public static function indexQuery(NovaRequest $request, Builder $query): Builder
    {
        $user = $request->user();

        if ($user === null) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereHas('client', function (Builder $clientQuery) use ($user): void {
            $clientQuery->whereIn('owner_id', $user->visibleDataOwnerIds());
        });
    }

    public static function detailQuery(NovaRequest $request, Builder $query): Builder
    {
        return static::indexQuery($request, $query);
    }

    public static function relatableQuery(NovaRequest $request, Builder $query): Builder
    {
        return static::indexQuery($request, $query);
    }

    /**
     * @return array<int, \Laravel\Nova\Card>
     */
    public function cards(NovaRequest $request): array
    {
        return [];
    }

    /**
     * @return array<int, \Laravel\Nova\Filters\Filter>
     */
    public function filters(NovaRequest $request): array
    {
        return [];
    }

    /**
     * @return array<int, \Laravel\Nova\Lenses\Lens>
     */
    public function lenses(NovaRequest $request): array
    {
        return [];
    }

    /**
     * @return array<int, \Laravel\Nova\Actions\Action>
     */
    public function actions(NovaRequest $request): array
    {
        return [];
    }

    protected function stageLabel(?string $stage): string
    {
        if ($stage === null || $stage === '') {
            return '—';
        }

        $options = PipelineStage::options();

        return $options[$stage] ?? ucfirst(str_replace('_', ' ', $stage));
    }

    protected function closingResultLabel(?string $result): string
    {
        if ($result === null || $result === '') {
            return '—';
        }

        $options = ClosingResult::options();

        return $options[$result] ?? ucfirst(str_replace('_', ' ', $result));
    }

    protected function closingResultBadge(): string
    {
        $result = $this->to_closing_result ?? $this->from_closing_result;

        if ($result === null || $result === '') {
            return '—';
        }

        $labels = ClosingResult::options();
        $label = $labels[$result] ?? ucfirst(str_replace('_', ' ', $result));
        $class = $result === ClosingResult::Won->value
            ? 'bg-emerald-100 text-emerald-800'
            : 'bg-rose-100 text-rose-800';

        return sprintf(
            '<span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold %s">%s</span>',
            $class,
            e($label)
        );
    }
}
