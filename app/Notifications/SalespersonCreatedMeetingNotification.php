<?php

namespace App\Notifications;

use App\Models\Meeting;
use Laravel\Nova\Notifications\NovaNotification;

class SalespersonCreatedMeetingNotification extends NovaNotification
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

        $salespersonName = $this->meeting->creator?->name ?? 'A salesperson';
        $clientName = $this->meeting->client?->business_name ?? 'a business';

        return NovaNotification::make()
            ->icon('calendar-days')
            ->type(self::INFO_TYPE)
            ->message(sprintf('%s created a new meeting for %s.', $salespersonName, $clientName))
            ->action('View Meeting', '/resources/meetings/'.$this->meeting->id)
            ->toArray();
    }
}
