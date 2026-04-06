<?php

namespace App\Nova;

use App\Enums\MeetingResult;
use App\Enums\MeetingType;
use App\Enums\UserRole;
use App\Nova\Actions\ExportToCsv;
use App\Nova\Filters\MeetingAssignedSalespersonFilter;
use App\Nova\Filters\MeetingCreatedByFilter;
use App\Nova\Filters\MeetingScheduledFromFilter;
use App\Nova\Filters\MeetingScheduledToFilter;
use App\Nova\Filters\MeetingTypeFilter;
use Carbon\Carbon;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\FormData;
use Laravel\Nova\Fields\HasMany;
use Laravel\Nova\Fields\Hidden;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Textarea;
use Laravel\Nova\Http\Requests\NovaRequest;

class Meeting extends Resource
{
    /**
     * @var bool
     */
    public static $displayInNavigation = false;

    /**
     * The model the resource corresponds to.
     *
     * @var class-string<\App\Models\Meeting>
     */
    public static $model = \App\Models\Meeting::class;

    /**
     * The single value that should be used to represent the resource when being displayed.
     *
     * @var string
     */
    public static $title = 'purpose';

    /**
     * The columns that should be searched.
     *
     * @var array<int, string>
     */
    public static $search = [
        'id',
        'purpose',
        'comments',
    ];

    /**
     * Determine if the current user can create new resources.
     */
    public static function authorizedToCreate(Request $request): bool
    {
        if (
            $request->viaResource === User::uriKey() &&
            in_array((string) $request->viaRelationship, ['meetings', 'upcomingMeetings', 'upcoming_meetings', 'upcoming-meetings', 'doneMeetings', 'done_meetings', 'done-meetings'], true)
        ) {
            return false;
        }

        return parent::authorizedToCreate($request);
    }

    /**
     * Get the fields displayed by the resource.
     *
     * @return array<int, \Laravel\Nova\Fields\Field|\Laravel\Nova\Panel|\Laravel\Nova\ResourceTool|\Illuminate\Http\Resources\MergeValue>
     */
    public function fields(NovaRequest $request): array
    {
        return [
            ID::make()->sortable(),

            DateTime::make('Date & Time', 'scheduled_at')
                ->step(60)
                ->sortable()
                ->rules('required', 'date', function (string $attribute, mixed $value, \Closure $fail) use ($request): void {
                    if ($value === null || $value === '') {
                        return;
                    }

                    try {
                        $scheduledAt = Carbon::parse($value);
                    } catch (\Throwable) {
                        return;
                    }

                    $userId = $request->input('user_id') ?? $request->input('user');
                    $unavailableMinutes = $request->input('unavailable_minutes');

                    if ($userId === null && $request->resourceId !== null) {
                        $existingMeeting = \App\Models\Meeting::query()->find($request->resourceId);

                        if ($existingMeeting !== null) {
                            $userId = $existingMeeting->user_id;
                            $unavailableMinutes ??= $existingMeeting->unavailable_minutes;
                        }
                    }

                    if ($userId === null || ! is_numeric($userId)) {
                        return;
                    }

                    if ($unavailableMinutes === null || ! is_numeric($unavailableMinutes)) {
                        return;
                    }

                    $conflict = \App\Models\Meeting::findSchedulingConflict(
                        (int) $userId,
                        $scheduledAt,
                        (int) $unavailableMinutes,
                        $request->resourceId !== null ? (int) $request->resourceId : null
                    );

                    if ($conflict === null) {
                        return;
                    }

                    $conflictStart = $conflict->scheduled_at;
                    $conflictEnd = $conflict->unavailableEnd();

                    if ($conflictStart === null || $conflictEnd === null) {
                        return;
                    }

                    $fail(sprintf(
                        'Salesperson is already booked from %s to %s.',
                        $conflictStart->format('Y-m-d H:i'),
                        $conflictEnd->format('Y-m-d H:i')
                    ));
                }),

            BelongsTo::make('Client')
                ->creationRules('required')
                ->updateRules('nullable')
                ->readonly(fn (NovaRequest $novaRequest): bool => $novaRequest->isUpdateOrUpdateAttachedRequest()
                    && ($novaRequest->user()?->isSalesperson() ?? false)),

            BelongsTo::make('Contact')
                ->nullable()
                ->readonly(fn (NovaRequest $novaRequest): bool => $novaRequest->isUpdateOrUpdateAttachedRequest()
                    && ($novaRequest->user()?->isSalesperson() ?? false))
                ->dependsOn('client', function (BelongsTo $field, NovaRequest $novaRequest, FormData $formData): void {
                    $selectedClientId = $formData->client;

                    $field->relatableQueryUsing(function (NovaRequest $request, Builder $query) use ($selectedClientId): Builder {
                        if ($selectedClientId !== null) {
                            return static::relatableContacts($request, $query)->where('client_id', $selectedClientId);
                        }

                        return static::relatableContacts($request, $query);
                    });
                })
                ->rules('nullable'),

            ...$this->salespersonFields($request),

            BelongsTo::make('Created By', 'creator', User::class)
                ->exceptOnForms(),

            Select::make('Meeting Type', 'meeting_type')
                ->options(MeetingType::options())
                ->displayUsingLabels()
                ->sortable()
                ->rules('required'),

            Number::make('Unavailable Minutes', 'unavailable_minutes')
                ->default(\App\Models\Meeting::DEFAULT_UNAVAILABLE_MINUTES)
                ->min(1)
                ->step(1)
                ->rules('required', 'integer', 'min:1'),

            Select::make('Purpose', 'purpose')
                ->options([
                    'presentation' => 'Presentation',
                    'follow-up' => 'Follow Up',
                    'negotiation' => 'Negotiation',
                    'closing' => 'Closing',
                ])
                ->displayUsingLabels()
                ->sortable()
                ->rules('required'),

            Select::make('Result', 'result')
                ->options(MeetingResult::options())
                ->displayUsingLabels()
                ->sortable()
                ->rules('required'),

            Textarea::make('Comments', 'comments')
                ->alwaysShow()
                ->rules('nullable', 'max:2000'),

            HasMany::make('Activity Log', 'activities', \App\Nova\MeetingActivity::class)
                ->onlyOnDetail(),
        ];
    }

    /**
     * @return array<int, \Laravel\Nova\Fields\Field>
     */
    protected function salespersonFields(NovaRequest $request): array
    {
        if ($request->user()?->isSalesperson()) {
            return [
                Hidden::make('Salesperson', 'user_id')
                    ->default(fn (NovaRequest $novaRequest) => $novaRequest->user()?->id)
                    ->rules('required'),
            ];
        }

        return [
            BelongsTo::make('Salesperson', 'user', User::class)
                ->default(fn (NovaRequest $novaRequest) => $novaRequest->user()?->id)
                ->rules('required'),
        ];
    }

    /**
     * Build an "index" query for the given resource.
     */
    public static function indexQuery(NovaRequest $request, Builder $query): Builder
    {
        return static::scopeToVisibleMeetings($request, $query);
    }

    /**
     * Build a "detail" query for the given resource.
     */
    public static function detailQuery(NovaRequest $request, Builder $query): Builder
    {
        return static::scopeToVisibleMeetings($request, $query);
    }

    /**
     * Build a "relatable" query for the given resource.
     */
    public static function relatableQuery(NovaRequest $request, Builder $query): Builder
    {
        return static::scopeToVisibleMeetings($request, $query);
    }

    protected static function scopeToVisibleMeetings(NovaRequest $request, Builder $query): Builder
    {
        $user = $request->user();

        if ($user === null) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn('user_id', $user->visibleDataOwnerIds());
    }

    /**
     * Build a "relatable" query for the client relation.
     */
    public static function relatableClients(NovaRequest $request, Builder $query): Builder
    {
        $user = $request->user();

        if ($user === null) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn('owner_id', $user->visibleDataOwnerIds());
    }

    /**
     * Build a "relatable" query for the contact relation.
     */
    public static function relatableContacts(NovaRequest $request, Builder $query): Builder
    {
        $user = $request->user();

        if ($user === null) {
            return $query->whereRaw('1 = 0');
        }

        $query->whereHas('client', function (Builder $clientQuery) use ($user): void {
            $clientQuery->whereIn('owner_id', $user->visibleDataOwnerIds());
        });

        $clientId = static::resolveClientId($request);

        if ($clientId !== null) {
            $query->where('client_id', $clientId);
        }

        return $query;
    }

    protected static function resolveClientId(NovaRequest $request): ?int
    {
        $clientId = $request->input('client');

        if ($clientId === null && $request->resourceId !== null) {
            $clientId = \App\Models\Meeting::query()->whereKey($request->resourceId)->value('client_id');
        }

        if ($clientId === null && $request->query('dependsOn')) {
            $dependsOn = json_decode(base64_decode((string) $request->query('dependsOn')), true);

            if (is_array($dependsOn) && isset($dependsOn['client'])) {
                $clientId = $dependsOn['client'];
            }
        }

        if ($clientId === null || ! is_numeric($clientId)) {
            return null;
        }

        return (int) $clientId;
    }

    /**
     * Build a "relatable" query for the salesperson relation.
     */
    public static function relatableUsers(NovaRequest $request, Builder $query): Builder
    {
        $user = $request->user();

        if ($user === null) {
            return $query->whereRaw('1 = 0');
        }

        return $query
            ->whereIn('id', $user->visibleUserDirectoryIds())
            ->where('role', UserRole::Salesperson->value);
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
            new MeetingScheduledFromFilter,
            new MeetingScheduledToFilter,
            new MeetingAssignedSalespersonFilter,
            new MeetingCreatedByFilter,
            new MeetingTypeFilter,
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
     * Get the actions available for the request.
     *
     * @return array<int, \Laravel\Nova\Actions\Action>
     */
    public function actions(NovaRequest $request): array
    {
        return [
            (new ExportToCsv(
                'meetings-'.now()->format('Ymd-His'),
                [
                    'ID' => 'id',
                    'Client' => fn (\App\Models\Meeting $meeting): string => $meeting->client?->business_name ?? '',
                    'Contact' => fn (\App\Models\Meeting $meeting): string => $meeting->contact?->name ?? '',
                    'Salesperson' => fn (\App\Models\Meeting $meeting): string => $meeting->user?->name ?? '',
                    'Scheduled At' => 'scheduled_at',
                    'Meeting Type' => fn (\App\Models\Meeting $meeting): string => $meeting->meeting_type?->label() ?? '',
                    'Purpose' => 'purpose',
                    'Result' => fn (\App\Models\Meeting $meeting): string => $meeting->result?->label() ?? '',
                    'Comments' => 'comments',
                    'Created At' => 'created_at',
                ],
                ['client', 'contact', 'user']
            ))->onlyOnIndex(),
        ];
    }
}
