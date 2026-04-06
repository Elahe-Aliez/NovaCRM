<?php

namespace App\Nova;

use App\Models\Meeting as MeetingModel;
use App\Nova\Actions\ExportToCsv;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Laravel\Nova\Http\Requests\NovaRequest;

class UpcomingMeeting extends Meeting
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
            ->where('scheduled_at', '>', now());
    }

    /**
     * Build a "detail" query for the given resource.
     */
    public static function detailQuery(NovaRequest $request, Builder $query): Builder
    {
        $meetingId = $request->resourceId;
        $userId = $request->user()?->id;

        if (is_numeric($meetingId) && is_numeric($userId)) {
            MeetingModel::markRescheduledNotificationsAsRead((int) $meetingId, (int) $userId);
        }

        return parent::detailQuery($request, $query)
            ->where('scheduled_at', '>', now());
    }

    /**
     * @return array<int, \Laravel\Nova\Actions\Action>
     */
    public function actions(NovaRequest $request): array
    {
        return [
            (new ExportToCsv(
                'upcoming-meetings-'.now()->format('Ymd-His'),
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
