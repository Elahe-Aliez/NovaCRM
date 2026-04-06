<?php

namespace App\Enums;

enum PipelineStage: string
{
    case Lead = 'lead';
    case Interested = 'interested';
    case Negotiation = 'negotiation';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Lead => 'Lead',
            self::Interested => 'Interested',
            self::Negotiation => 'Negotiation',
            self::Closed => 'Closed',
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
