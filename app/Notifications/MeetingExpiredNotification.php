<?php

namespace App\Notifications;

use App\Models\Meeting;
use Laravel\Nova\Notifications\NovaNotification;

class MeetingExpiredNotification extends NovaNotification
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
        $this->meeting->loadMissing('client');

        $clientName = $this->meeting->client?->business_name ?? 'your client';

        return NovaNotification::make()
            ->icon('clock')
            ->type(self::WARNING_TYPE)
            ->message(sprintf(
                'Meeting time expired for %s. Please update result/comments, or reschedule to reopen.',
                $clientName
            ))
            ->action('Update Meeting', '/resources/meetings/'.$this->meeting->id.'/edit')
            ->toArray();
    }
}
