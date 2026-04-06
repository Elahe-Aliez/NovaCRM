<?php

namespace App\Models;

use App\Enums\MeetingResult;
use App\Enums\MeetingType;
use App\Notifications\MeetingExpiredNotification;
use App\Notifications\SalespersonCreatedMeetingNotification;
use App\Notifications\TeamLeaderAssignedMeetingNotification;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Laravel\Nova\Notifications\NovaNotification;

class Meeting extends Model
{
    use HasFactory;

    public const DEFAULT_UNAVAILABLE_MINUTES = 60;

    protected static function booted(): void
    {
        static::creating(function (Meeting $meeting): void {
            if ($meeting->user_id === null && Auth::check()) {
                $meeting->user_id = Auth::id();
            }

            if ($meeting->created_by_id === null && Auth::check()) {
                $meeting->created_by_id = Auth::id();
            }
        });

        static::created(function (Meeting $meeting): void {
            $meeting->loadMissing('creator.manager', 'user');

            $creator = $meeting->creator;

            if ($creator === null || ! $creator->isSalesperson()) {
                if (! $creator?->isTeamLeader()) {
                    return;
                }

                $assignedSalesperson = $meeting->user;

                if (
                    $assignedSalesperson === null ||
                    ! $assignedSalesperson->isSalesperson() ||
                    $assignedSalesperson->manager_id !== $creator->id
                ) {
                    return;
                }

                $assignedSalesperson->notify(new TeamLeaderAssignedMeetingNotification($meeting));

                return;
            }

            $teamLeader = $creator->manager;

            if ($teamLeader === null || ! $teamLeader->isTeamLeader()) {
                return;
            }

            $teamLeader->notify(new SalespersonCreatedMeetingNotification($meeting));
        });

        static::updated(function (Meeting $meeting): void {
            if (! $meeting->wasChanged(['scheduled_at', 'result', 'comments'])) {
                return;
            }

            $previousScheduledAt = static::asCarbonOrNull($meeting->getRawOriginal('scheduled_at'));
            $currentScheduledAt = $meeting->scheduled_at;
            $previousResult = (string) $meeting->getRawOriginal('result');
            $currentResult = $meeting->result?->value ?? (string) $meeting->getAttribute('result');
            $updatedByUserId = Auth::id();
            $isRescheduled = $meeting->wasChanged('scheduled_at') && ($currentScheduledAt?->isFuture() ?? false);
            $isDoneUpdate = static::isUpdatedAsDone($meeting, $previousScheduledAt, $previousResult, $currentResult);

            if ($isRescheduled) {
                $meeting->forceFill([
                    'result' => MeetingResult::InProgress->value,
                    'expired_notification_sent_at' => null,
                ])->saveQuietly();
            }

            if ($isRescheduled) {
                $meeting->activities()->create([
                    'actor_user_id' => $updatedByUserId,
                    'action' => 'rescheduled',
                    'occurred_at' => now(),
                    'from_scheduled_at' => $previousScheduledAt,
                    'to_scheduled_at' => $currentScheduledAt,
                    'from_result' => $previousResult !== '' ? $previousResult : null,
                    'to_result' => MeetingResult::InProgress->value,
                ]);
            }

            if ($isDoneUpdate) {
                $meeting->activities()->create([
                    'actor_user_id' => $updatedByUserId,
                    'action' => 'updated_as_done',
                    'occurred_at' => now(),
                    'from_scheduled_at' => $previousScheduledAt,
                    'to_scheduled_at' => $currentScheduledAt,
                    'from_result' => $previousResult !== '' ? $previousResult : null,
                    'to_result' => $currentResult !== '' ? $currentResult : null,
                ]);
            }

            if ($meeting->user_id === null) {
                return;
            }

            $isUpcomingMeeting = $meeting->scheduled_at?->isFuture() ?? false;
            $upcomingMeetingPath = '/resources/upcoming-meetings/'.$meeting->id;

            $notificationRows = DB::table('nova_notifications')
                ->where('type', MeetingExpiredNotification::class)
                ->where('notifiable_type', User::class)
                ->where('notifiable_id', $meeting->user_id)
                ->whereNull('deleted_at')
                ->get(['id', 'data']);

            foreach ($notificationRows as $notificationRow) {
                $decodedData = json_decode((string) $notificationRow->data, true);

                if (! is_array($decodedData)) {
                    continue;
                }

                $hasEditAction = static::containsMeetingEditAction($decodedData, $meeting->id);
                $hasUpcomingAction = static::containsNotificationPath($decodedData, $upcomingMeetingPath);

                if (! $hasEditAction && ! $hasUpcomingAction) {
                    continue;
                }

                if ($hasUpcomingAction && ! $isUpcomingMeeting) {
                    $updatedData = static::sanitizeNotificationData($decodedData);

                    DB::table('nova_notifications')
                        ->where('id', $notificationRow->id)
                        ->update([
                            'data' => json_encode($updatedData, JSON_UNESCAPED_SLASHES),
                            'read_at' => now(),
                            'updated_at' => now(),
                        ]);

                    continue;
                }

                if (! $hasEditAction) {
                    continue;
                }

                $updatedData = $isRescheduled && $isUpcomingMeeting
                    ? static::rescheduledNotificationData($meeting)
                    : static::sanitizeNotificationData($decodedData);

                DB::table('nova_notifications')
                    ->where('id', $notificationRow->id)
                    ->update([
                        'data' => json_encode($updatedData, JSON_UNESCAPED_SLASHES),
                        'read_at' => now(),
                        'updated_at' => now(),
                    ]);
            }
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected static function sanitizeNotificationData(array $data): array
    {
        $sanitized = [];

        foreach (['icon', 'type', 'message'] as $allowedKey) {
            if (array_key_exists($allowedKey, $data)) {
                $sanitized[$allowedKey] = $data[$allowedKey];
            }
        }

        if (! array_key_exists('message', $sanitized)) {
            $sanitized['message'] = 'Meeting was updated.';
        }

        if (! array_key_exists('type', $sanitized)) {
            $sanitized['type'] = MeetingExpiredNotification::WARNING_TYPE;
        }

        return $sanitized;
    }

    /**
     * @return array<string, mixed>
     */
    protected static function rescheduledNotificationData(Meeting $meeting): array
    {
        return NovaNotification::make()
            ->icon('calendar')
            ->type(NovaNotification::INFO_TYPE)
            ->message('Meeting was rescheduled and moved back to upcoming meetings.')
            ->action('View Upcoming Meeting', '/resources/upcoming-meetings/'.$meeting->id)
            ->toArray();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected static function containsMeetingEditAction(array $data, int $meetingId): bool
    {
        $meetingEditPath = '/resources/meetings/'.$meetingId.'/edit';

        return static::containsNotificationPath($data, $meetingEditPath);
    }

    public static function markRescheduledNotificationsAsRead(int $meetingId, int $userId): void
    {
        $upcomingMeetingPath = '/resources/upcoming-meetings/'.$meetingId;

        $notificationRows = DB::table('nova_notifications')
            ->where('type', MeetingExpiredNotification::class)
            ->where('notifiable_type', User::class)
            ->where('notifiable_id', $userId)
            ->whereNull('deleted_at')
            ->where('data', 'like', '%'.$upcomingMeetingPath.'%')
            ->get(['id', 'data']);

        foreach ($notificationRows as $notificationRow) {
            $decodedData = json_decode((string) $notificationRow->data, true);

            if (! is_array($decodedData)) {
                continue;
            }

            if (! static::containsNotificationPath($decodedData, $upcomingMeetingPath)) {
                continue;
            }

            DB::table('nova_notifications')
                ->where('id', $notificationRow->id)
                ->update([
                    'read_at' => now(),
                    'updated_at' => now(),
                ]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected static function containsNotificationPath(array $data, string $path): bool
    {
        foreach ($data as $value) {
            if (is_string($value) && str_contains($value, $path)) {
                return true;
            }

            if (is_array($value) && static::containsNotificationPath($value, $path)) {
                return true;
            }
        }

        return false;
    }

    protected static function asCarbonOrNull(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        return Carbon::parse($value);
    }

    protected static function isUpdatedAsDone(
        Meeting $meeting,
        ?Carbon $previousScheduledAt,
        string $previousResult,
        ?string $currentResult
    ): bool {
        $isDoneResultTransition = $meeting->wasChanged('result')
            && $previousResult === MeetingResult::InProgress->value
            && in_array($currentResult, [MeetingResult::Successful->value, MeetingResult::Failed->value], true);

        $isMovedFromUpcomingToDone = $meeting->wasChanged('scheduled_at')
            && ($previousScheduledAt?->isFuture() ?? false)
            && ! ($meeting->scheduled_at?->isFuture() ?? false);

        return $isDoneResultTransition || $isMovedFromUpcomingToDone;
    }

    /**
     * @var list<string>
     */
    protected $fillable = [
        'client_id',
        'contact_id',
        'user_id',
        'created_by_id',
        'scheduled_at',
        'meeting_type',
        'unavailable_minutes',
        'purpose',
        'result',
        'comments',
        'expired_notification_sent_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'expired_notification_sent_at' => 'datetime',
            'meeting_type' => MeetingType::class,
            'result' => MeetingResult::class,
            'unavailable_minutes' => 'integer',
        ];
    }

    public function setScheduledAtAttribute(mixed $value): void
    {
        if ($value === null || $value === '') {
            $this->attributes['scheduled_at'] = null;

            return;
        }

        $this->attributes['scheduled_at'] = Carbon::parse($value)
            ->second(0)
            ->format('Y-m-d H:i:s');
    }

    public function unavailableEnd(): ?Carbon
    {
        if ($this->scheduled_at === null) {
            return null;
        }

        $minutes = $this->unavailable_minutes ?? self::DEFAULT_UNAVAILABLE_MINUTES;

        return $this->scheduled_at->copy()->addMinutes($minutes);
    }

    public static function findSchedulingConflict(
        int $userId,
        Carbon $scheduledAt,
        int $unavailableMinutes,
        ?int $ignoreMeetingId = null
    ): ?self {
        $windowEnd = $scheduledAt->copy()->addMinutes($unavailableMinutes);

        $connection = DB::connection();
        $driver = $connection->getDriverName();

        $query = static::query()
            ->where('user_id', $userId)
            ->where('scheduled_at', '<', $windowEnd);

        if ($driver === 'sqlite') {
            $query->whereRaw("datetime(scheduled_at, '+' || unavailable_minutes || ' minutes') > ?", [$scheduledAt]);
        } else {
            $query->whereRaw('DATE_ADD(scheduled_at, INTERVAL unavailable_minutes MINUTE) > ?', [$scheduledAt]);
        }

        if ($ignoreMeetingId !== null) {
            $query->whereKeyNot($ignoreMeetingId);
        }

        return $query->orderBy('scheduled_at')->first();
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(MeetingActivity::class)->latest('occurred_at');
    }
}
