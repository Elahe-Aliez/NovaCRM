<?php

namespace App\Nova;

use App\Enums\UserRole;
use App\Models\User as UserModel;
use App\Nova\Filters\UserReportsToFilter;
use App\Nova\Filters\UserRoleFilter;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Laravel\Nova\Auth\PasswordValidationRules;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\FormData;
use Laravel\Nova\Fields\HasMany;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Password;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\UiAvatar;
use Laravel\Nova\Http\Requests\NovaRequest;

class User extends Resource
{
    use PasswordValidationRules;

    /**
     * The model the resource corresponds to.
     *
     * @var class-string<\App\Models\User>
     */
    public static $model = \App\Models\User::class;

    /**
     * The single value that should be used to represent the resource when being displayed.
     *
     * @var string
     */
    public static $title = 'name';

    /**
     * The columns that should be searched.
     *
     * @var array
     */
    public static $search = [
        'id', 'name', 'email',
    ];

    /**
     * Get the fields displayed by the resource.
     *
     * @return array<int, \Laravel\Nova\Fields\Field|\Laravel\Nova\Panel|\Laravel\Nova\ResourceTool|\Illuminate\Http\Resources\MergeValue>
     */
    public function fields(NovaRequest $request): array
    {
        $fields = [
            ID::make()->sortable(),

            UiAvatar::make()->maxWidth(50),

            Text::make('Name')
                ->sortable()
                ->rules('required', 'max:255'),

            Text::make('Email')
                ->sortable()
                ->rules('required', 'email', 'max:254')
                ->creationRules('unique:users,email')
                ->updateRules('unique:users,email,{{resourceId}}'),

            Select::make('Role')
                ->options(UserRole::options())
                ->displayUsingLabels()
                ->rules('required'),

            BelongsTo::make('Reports To', 'manager', self::class)
                ->nullable()
                ->readonly(fn (NovaRequest $novaRequest) => $novaRequest->user()?->isSalesperson() ?? false)
                ->help('Team Lead -> choose a Manager. Salesperson -> choose a Team Lead.')
                ->dependsOn('role', function (BelongsTo $field, NovaRequest $novaRequest, FormData $formData): void {
                    $selectedRole = $formData->role;

                    $field->relatableQueryUsing(function (NovaRequest $request, Builder $query) use ($selectedRole): Builder {
                        $user = $request->user();

                        if ($user === null) {
                            return $query->whereRaw('1 = 0');
                        }

                        $query->whereIn('id', $user->visibleUserDirectoryIds());

                        if ($selectedRole === UserRole::TeamLeader->value) {
                            return $query->where('role', UserRole::Manager->value);
                        }

                        if ($selectedRole === UserRole::Salesperson->value) {
                            return $query->where('role', UserRole::TeamLeader->value);
                        }

                        if ($selectedRole === UserRole::Manager->value) {
                            return $query->whereRaw('1 = 0');
                        }

                        return $query->whereIn('role', [UserRole::Manager->value, UserRole::TeamLeader->value]);
                    });
                })
                ->rules('nullable', function (string $attribute, mixed $value, \Closure $fail) use ($request): void {
                    $roleValue = $request->input('role');

                    if ($roleValue === null && $request->resourceId !== null) {
                        $roleValue = UserModel::query()->whereKey($request->resourceId)->value('role');
                    }

                    if ($roleValue === UserRole::Manager->value) {
                        return;
                    }

                    if (empty($value)) {
                        $fail('Manager field is required for team leaders and salespersons.');

                        return;
                    }

                    $manager = UserModel::query()->find($value);

                    if ($manager === null) {
                        $fail('Selected manager is invalid.');

                        return;
                    }

                    if ($roleValue === UserRole::TeamLeader->value && ! $manager->isManager()) {
                        $fail('Team leaders must be assigned to a manager.');
                    }

                    if ($roleValue === UserRole::Salesperson->value && ! $manager->isTeamLeader()) {
                        $fail('Salespersons must be assigned to a team leader.');
                    }
                }),

            Password::make('Password')
                ->onlyOnForms()
                ->creationRules($this->passwordRules())
                ->updateRules($this->optionalPasswordRules()),
        ];

        if ($this->resource->exists && $this->resource->isTeamLeader()) {
            $fields[] = HasMany::make('Assigned Salespersons', 'teamMembers', self::class)
                ->onlyOnDetail();
        }

        if ($this->resource->exists && $this->resource->isSalesperson()) {
            $fields[] = HasMany::make('Upcoming Meetings', 'upcomingMeetings', \App\Nova\UpcomingMeeting::class)
                ->onlyOnDetail();

            $fields[] = HasMany::make('Done Meetings', 'doneMeetings', \App\Nova\DoneMeeting::class)
                ->collapsedByDefault()
                ->onlyOnDetail();
        }

        return $fields;
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

        return $query->whereIn('id', $user->visibleUserDirectoryIds());
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
        $user = $request->user();

        if ($user === null) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn('id', $user->visibleUserDirectoryIds());
    }

    /**
     * Build a "relatable" query for the manager relation.
     */
    public static function relatableManagers(NovaRequest $request, Builder $query): Builder
    {
        $user = $request->user();

        if ($user === null) {
            return $query->whereRaw('1 = 0');
        }

        $roleValue = static::resolveRoleValue($request);

        $query->whereIn('id', $user->visibleUserDirectoryIds());

        if ($roleValue === UserRole::TeamLeader->value) {
            return $query->where('role', UserRole::Manager->value);
        }

        if ($roleValue === UserRole::Salesperson->value) {
            return $query->where('role', UserRole::TeamLeader->value);
        }

        return $query->whereIn('role', [UserRole::Manager->value, UserRole::TeamLeader->value]);
    }

    protected static function resolveRoleValue(NovaRequest $request): ?string
    {
        $roleValue = $request->input('role');

        if ($roleValue === null && $request->resourceId !== null) {
            $roleValue = UserModel::query()->whereKey($request->resourceId)->value('role');
        }

        if ($roleValue === null && $request->query('dependsOn')) {
            $dependsOn = json_decode(base64_decode((string) $request->query('dependsOn')), true);

            if (is_array($dependsOn) && isset($dependsOn['role'])) {
                $roleValue = $dependsOn['role'];
            }
        }

        return $roleValue;
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
     * Get the filters available for the request.
     *
     * @return array<int, \Laravel\Nova\Filters\Filter>
     */
    public function filters(NovaRequest $request): array
    {
        if (! ($request->user()?->isManager() ?? false)) {
            return [];
        }

        return [
            new UserRoleFilter,
            new UserReportsToFilter,
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
        return [];
    }
}
