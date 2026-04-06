<?php

namespace App\Nova\Dashboards;

use App\Nova\Metrics\BusinessesCount;
use App\Nova\Metrics\CompletedMeetingsCount;
use App\Nova\Metrics\ContactsCount;
use App\Nova\Metrics\MeetingsTrend;
use App\Nova\Metrics\UpcomingMeetingsCount;
use App\Nova\Metrics\UsersByRole;
use Laravel\Nova\Dashboards\Main as Dashboard;

class Main extends Dashboard
{
    /**
     * @var string
     */
    public $name = 'Metrics';

    /**
     * Get the cards for the dashboard.
     *
     * @return array<int, \Laravel\Nova\Card>
     */
    public function cards(): array
    {
        return [
            (new BusinessesCount)
                ->icon('building-office')
                ->width('1/4'),
            (new ContactsCount)
                ->icon('user-group')
                ->width('1/4'),
            (new UpcomingMeetingsCount)
                ->icon('calendar-days')
                ->width('1/4'),
            (new CompletedMeetingsCount)
                ->icon('check-circle')
                ->width('1/4'),
            (new UsersByRole)
                ->width('1/2'),
            (new MeetingsTrend)
                ->width('1/2'),
        ];
    }
}
