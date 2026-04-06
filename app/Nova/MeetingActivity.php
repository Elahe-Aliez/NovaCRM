<?php

namespace App\Nova;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;

class MeetingActivity extends Resource
{
    /**
     * @var bool
     */
    public static $displayInNavigation = false;

    /**
     * The model the resource corresponds to.
     *
     * @var class-string<\App\Models\MeetingActivity>
     */
    public static $model = \App\Models\MeetingActivity::class;

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
                'rescheduled' => 'Rescheduled',
                'updated_as_done' => 'Updated As Done',
                default => ucfirst(str_replace('_', ' ', (string) $this->action)),
            })->sortable(),

            DateTime::make('Date & Time', 'occurred_at')
                ->sortable(),

            BelongsTo::make('Updated By', 'actor', User::class)
                ->nullable(),

            Text::make('From Date & Time', fn (): string => $this->from_scheduled_at?->format('Y-m-d H:i') ?? '—')
                ->onlyOnDetail(),

            Text::make('To Date & Time', fn (): string => $this->to_scheduled_at?->format('Y-m-d H:i') ?? '—')
                ->onlyOnDetail(),

            Text::make('From Result', 'from_result')
                ->nullable()
                ->onlyOnDetail(),

            Text::make('To Result', 'to_result')
                ->nullable()
                ->onlyOnDetail(),
        ];
    }

    public static function indexQuery(NovaRequest $request, Builder $query): Builder
    {
        $user = $request->user();

        if ($user === null) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereHas('meeting', function (Builder $meetingQuery) use ($user): void {
            $meetingQuery->whereIn('user_id', $user->visibleDataOwnerIds());
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
}
