<?php

namespace App\Nova;

use App\Nova\Filters\ContactCreatedByFilter;
use App\Nova\Filters\ContactCreatedFromFilter;
use App\Nova\Filters\ContactCreatedToFilter;
use App\Nova\Actions\ExportToCsv;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;

class Contact extends Resource
{
    /**
     * The model the resource corresponds to.
     *
     * @var class-string<\App\Models\Contact>
     */
    public static $model = \App\Models\Contact::class;

    /**
     * The single value that should be used to represent the resource when being displayed.
     *
     * @var string
     */
    public static $title = 'name';

    /**
     * The columns that should be searched.
     *
     * @var array<int, string>
     */
    public static $search = [
        'id',
        'client.business_name',
        'name',
        'email',
        'phone',
        'position',
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

            BelongsTo::make('Client')
                ->rules('required'),

            Text::make('Name')
                ->sortable()
                ->rules('required', 'max:255'),

            Text::make('Email')
                ->sortable()
                ->rules('nullable', 'email', 'max:254'),

            Text::make('Position')
                ->sortable()
                ->rules('nullable', 'max:255'),

            Text::make('Phone')
                ->sortable()
                ->rules('required', 'max:255'),

            BelongsTo::make('Created By', 'creator', User::class)
                ->exceptOnForms(),
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

        return $query->whereHas('client', function (Builder $clientQuery) use ($user) {
            $clientQuery->whereIn('owner_id', $user->visibleDataOwnerIds());
        });
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
            new ContactCreatedByFilter,
            new ContactCreatedFromFilter,
            new ContactCreatedToFilter,
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
        return [
            (new ExportToCsv(
                'contacts-'.now()->format('Ymd-His'),
                [
                    'ID' => 'id',
                    'Client' => fn (\App\Models\Contact $contact): string => $contact->client?->business_name ?? '',
                    'Name' => 'name',
                    'Position' => 'position',
                    'Phone' => 'phone',
                    'Created By' => fn (\App\Models\Contact $contact): string => $contact->creator?->name ?? '',
                    'Created At' => 'created_at',
                ],
                ['client', 'creator']
            ))->onlyOnIndex(),
        ];
    }
}
