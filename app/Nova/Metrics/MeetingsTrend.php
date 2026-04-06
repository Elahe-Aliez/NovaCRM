<?php

namespace App\Nova\Metrics;

use App\Models\Meeting;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Metrics\Trend;
use Laravel\Nova\Metrics\TrendResult;

class MeetingsTrend extends Trend
{
    /**
     * @var string
     */
    public $name = 'Meetings Trend';

    /**
     * Calculate the value of the metric.
     */
    public function calculate(NovaRequest $request): TrendResult
    {
        $user = $request->user();

        if ($user === null) {
            return $this->result([]);
        }

        return $this->count(
            $request,
            Meeting::query()->whereIn('user_id', $user->visibleDataOwnerIds()),
            self::BY_DAYS,
            'scheduled_at'
        );
    }

    /**
     * @return array<int, string>
     */
    public function ranges(): array
    {
        return [
            3 => '3 Days',
            7 => '7 Days',
            14 => '2 Weeks',
            30 => '1 Month',
        ];
    }

    /**
     * Get the URI key for the metric.
     */
    public function uriKey(): string
    {
        return 'meetings-trend';
    }
}
