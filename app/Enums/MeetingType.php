<?php

namespace App\Enums;

enum MeetingType: string
{
    case PhysicalVisit = 'physical_visit';
    case PhoneCall = 'phone_call';
    case OnlineMeeting = 'online_meeting';

    public function label(): string
    {
        return match ($this) {
            self::PhysicalVisit => 'Physical Visit',
            self::PhoneCall => 'Phone Call',
            self::OnlineMeeting => 'Online Meeting',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }
}
