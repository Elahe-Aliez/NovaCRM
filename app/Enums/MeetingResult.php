<?php

namespace App\Enums;

enum MeetingResult: string
{
    case Successful = 'successful';
    case InProgress = 'in_progress';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Successful => 'Successful',
            self::InProgress => 'In Progress',
            self::Failed => 'Failed',
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
