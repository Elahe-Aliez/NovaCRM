<?php

namespace App\Models;

use App\Enums\UserRole;
// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Validation\ValidationException;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    protected static function booted(): void
    {
        static::saving(function (User $user): void {
            if ($user->role === UserRole::Manager) {
                $user->manager_id = null;

                return;
            }

            if ($user->manager_id === null) {
                throw ValidationException::withMessages([
                    'manager_id' => 'A reports-to user is required for team leaders and salespersons.',
                ]);
            }

            $assignedManager = self::query()->find($user->manager_id);

            if ($assignedManager === null) {
                throw ValidationException::withMessages([
                    'manager_id' => 'The selected reports-to user is invalid.',
                ]);
            }

            if ($user->exists && $assignedManager->id === $user->id) {
                throw ValidationException::withMessages([
                    'manager_id' => 'A user cannot report to themselves.',
                ]);
            }

            if ($user->role === UserRole::TeamLeader && ! $assignedManager->isManager()) {
                throw ValidationException::withMessages([
                    'manager_id' => 'Team leads must report to a manager.',
                ]);
            }

            if ($user->role === UserRole::Salesperson && ! $assignedManager->isTeamLeader()) {
                throw ValidationException::withMessages([
                    'manager_id' => 'Salespersons must report to a team lead.',
                ]);
            }
        });
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'manager_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
        ];
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(self::class, 'manager_id');
    }

    public function teamMembers(): HasMany
    {
        return $this->hasMany(self::class, 'manager_id');
    }

    public function clients(): HasMany
    {
        return $this->hasMany(Client::class, 'owner_id');
    }

    public function meetings(): HasMany
    {
        return $this->hasMany(Meeting::class);
    }

    public function upcomingMeetings(): HasMany
    {
        return $this->hasMany(Meeting::class)
            ->where('scheduled_at', '>', now());
    }

    public function doneMeetings(): HasMany
    {
        return $this->hasMany(Meeting::class)
            ->where('scheduled_at', '<=', now());
    }

    public function isSalesperson(): bool
    {
        return $this->role === UserRole::Salesperson;
    }

    public function isTeamLeader(): bool
    {
        return $this->role === UserRole::TeamLeader;
    }

    public function isManager(): bool
    {
        return $this->role === UserRole::Manager;
    }

    public function canManageTeam(): bool
    {
        return in_array($this->role, [UserRole::TeamLeader, UserRole::Manager], true);
    }

    /**
     * @return array<int, int>
     */
    public function visibleDataOwnerIds(): array
    {
        if ($this->isManager()) {
            return self::query()->pluck('id')->all();
        }

        if ($this->isTeamLeader()) {
            $ownerIds = [$this->id];

            if ($this->manager_id !== null) {
                $ownerIds[] = $this->manager_id;
            }

            $teamSalespersonIds = self::query()
                ->where('manager_id', $this->id)
                ->where('role', UserRole::Salesperson->value)
                ->pluck('id')
                ->all();

            return array_values(array_unique([...$ownerIds, ...$teamSalespersonIds]));
        }

        if ($this->isSalesperson()) {
            $ownerIds = [$this->id];

            $teamLeaderId = $this->manager_id;

            if ($teamLeaderId !== null) {
                $ownerIds[] = $teamLeaderId;

                $managerId = self::query()->whereKey($teamLeaderId)->value('manager_id');

                if ($managerId !== null) {
                    $ownerIds[] = $managerId;
                }

                $teamSalespersonIds = self::query()
                    ->where('manager_id', $teamLeaderId)
                    ->where('role', UserRole::Salesperson->value)
                    ->pluck('id')
                    ->all();

                $ownerIds = [...$ownerIds, ...$teamSalespersonIds];
            }

            return array_values(array_unique($ownerIds));
        }

        return [$this->id];
    }

    /**
     * @return array<int, int>
     */
    public function visibleUserDirectoryIds(): array
    {
        if ($this->isManager()) {
            return self::query()->pluck('id')->all();
        }

        if ($this->isTeamLeader()) {
            $teamSalespersonIds = self::query()
                ->where('manager_id', $this->id)
                ->where('role', UserRole::Salesperson->value)
                ->pluck('id')
                ->all();

            $directoryIds = [$this->id, ...$teamSalespersonIds];

            if ($this->manager_id !== null) {
                $directoryIds[] = $this->manager_id;
            }

            return array_values(array_unique($directoryIds));
        }

        if ($this->isSalesperson()) {
            return self::query()
                ->where('role', UserRole::Salesperson->value)
                ->pluck('id')
                ->all();
        }

        return [$this->id];
    }

    /**
     * @return array<int, int>
     */
    public function visibleFilterUserIds(): array
    {
        if (! $this->isSalesperson()) {
            return $this->visibleUserDirectoryIds();
        }

        if ($this->manager_id === null) {
            return [$this->id];
        }

        $teamSalespersonIds = self::query()
            ->where('manager_id', $this->manager_id)
            ->where('role', UserRole::Salesperson->value)
            ->pluck('id')
            ->all();

        return array_values(array_unique([$this->manager_id, ...$teamSalespersonIds]));
    }

    public function canAccessClient(Client $client): bool
    {
        return in_array($client->owner_id, $this->visibleDataOwnerIds(), true);
    }

    public function canAccessMeeting(Meeting $meeting): bool
    {
        if (in_array($meeting->user_id, $this->visibleDataOwnerIds(), true)) {
            return true;
        }

        return $this->canAccessClient($meeting->client);
    }

    public function canAccessUser(User $otherUser): bool
    {
        return in_array($otherUser->id, $this->visibleUserDirectoryIds(), true);
    }
}
