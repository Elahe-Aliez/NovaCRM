<?php

namespace App\Notifications;

use App\Models\Meeting;
use Laravel\Nova\Notifications\NovaNotification;

class TeamLeaderAssignedMeetingNotification extends NovaNotification
{
    public function __construct(public Meeting $meeting)
    {
        //
    }

    /**
     * @return array<string, mixed>
     */
    public function toNova(): array
    {
        $this->meeting->loadMissing('creator', 'client');

        $teamLeaderName = $this->meeting->creator?->name ?? 'Your team leader';
        $clientName = $this->meeting->client?->business_name ?? 'a business';

        return NovaNotification::make()
            ->icon('calendar-days')
            ->type(self::INFO_TYPE)
            ->message(sprintf('%s assigned you a new meeting for %s.', $teamLeaderName, $clientName))
            ->action('View Meeting', '/resources/meetings/'.$this->meeting->id)
            ->toArray();
    }
}
