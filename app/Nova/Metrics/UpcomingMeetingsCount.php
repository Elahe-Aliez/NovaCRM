<?php

namespace App\Nova\Metrics;

use App\Models\Meeting;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Metrics\Value;
use Laravel\Nova\Metrics\ValueResult;

class UpcomingMeetingsCount extends Value
{
    /**
     * @var string
     */
    public $name = 'Upcoming Meetings';

    /**
     * Calculate the value of the metric.
     */
    public function calculate(NovaRequest $request): ValueResult
    {
        $user = $request->user();

        if ($user === null) {
            return $this->result(0)->allowZeroResult();
        }

        $query = Meeting::query()
            ->where(function (Builder $query) use ($user): void {
                $query
                    ->whereIn('user_id', $user->visibleDataOwnerIds())
                    ->orWhereHas('client', function (Builder $clientQuery) use ($user): void {
                        $clientQuery->whereIn('owner_id', $user->visibleDataOwnerIds());
                    });
            })
            ->where('scheduled_at', '>=', now());
        $range = $request->range;

        if ($range !== 'ALL') {
            $days = (int) $range;

            if ($days > 0) {
                $query->where('scheduled_at', '<=', now()->addDays($days));
            }
        }

        $count = $query->count();

        return $this->result($count)->allowZeroResult();
    }

    /**
     * @return array<int|string, string>
     */
    public function ranges(): array
    {
        return [
            3 => '3 Days',
            7 => '7 Days',
            14 => '2 Weeks',
            30 => '1 Month',
            'ALL' => 'All Time',
        ];
    }

    /**
     * Get the URI key for the metric.
     */
    public function uriKey(): string
    {
        return 'upcoming-meetings-count';
    }
}
