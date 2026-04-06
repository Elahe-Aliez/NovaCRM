<?php

namespace App\Enums;

enum UserRole: string
{
    case Salesperson = 'salesperson';
    case TeamLeader = 'team_leader';
    case Manager = 'manager';

    public function label(): string
    {
        return match ($this) {
            self::Salesperson => 'Salesperson',
            self::TeamLeader => 'Team Leader',
            self::Manager => 'Manager',
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
