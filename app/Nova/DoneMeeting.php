<?php

namespace App\Nova;

use App\Nova\Actions\ExportToCsv;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Laravel\Nova\Http\Requests\NovaRequest;

class DoneMeeting extends Meeting
{
    /**
     * @var bool
     */
    public static $displayInNavigation = true;

    /**
     * Build an "index" query for the given resource.
     */
    public static function indexQuery(NovaRequest $request, Builder $query): Builder
    {
        return parent::scopeToVisibleMeetings($request, $query)
            ->where('scheduled_at', '<=', now());
    }

    /**
     * @return array<int, \Laravel\Nova\Actions\Action>
     */
    public function actions(NovaRequest $request): array
    {
        return [
            (new ExportToCsv(
                'done-meetings-'.now()->format('Ymd-His'),
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
