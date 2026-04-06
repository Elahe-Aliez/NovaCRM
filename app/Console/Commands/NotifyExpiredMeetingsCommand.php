<?php

namespace App\Console\Commands;

use App\Enums\MeetingResult;
use App\Enums\UserRole;
use App\Models\Meeting;
use App\Notifications\MeetingExpiredNotification;
use Illuminate\Console\Command;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class NotifyExpiredMeetingsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'meetings:notify-expired';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Notify salespersons when scheduled meetings expire and move to done';

    public function handle(): int
    {
        $processedMeetings = 0;

        Meeting::query()
            ->where('scheduled_at', '<=', now())
            ->where('result', MeetingResult::InProgress->value)
            ->whereNull('expired_notification_sent_at')
            ->whereHas('user', function (Builder $query): void {
                $query->where('role', UserRole::Salesperson->value);
            })
            ->with(['user', 'client'])
            ->chunkById(100, function (Collection $meetings) use (&$processedMeetings): void {
                foreach ($meetings as $meeting) {
                    if ($meeting->user === null) {
                        continue;
                    }

                    $meeting->user->notify(new MeetingExpiredNotification($meeting));
                    $meeting->forceFill(['expired_notification_sent_at' => now()])->saveQuietly();
                    $processedMeetings++;
                }
            });

        $this->info("Processed expired meetings: {$processedMeetings}");

        return self::SUCCESS;
    }
}
