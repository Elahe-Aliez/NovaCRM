<?php

namespace App\Models;

use App\Enums\ClosingResult;
use App\Enums\PipelineStage;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class Client extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (Client $client): void {
            if ($client->owner_id === null && Auth::check()) {
                $client->owner_id = Auth::id();
            }

            if ($client->created_by_id === null && Auth::check()) {
                $client->created_by_id = Auth::id();
            }

            static::ensureUniqueBusinessName($client);
        });

        static::updating(function (Client $client): void {
            if ($client->isDirty('business_name')) {
                static::ensureUniqueBusinessName($client);
            }

            if (! $client->isDirty('pipeline_stage')) {
                return;
            }

            $nextStage = $client->pipeline_stage?->value ?? (string) $client->getAttribute('pipeline_stage');

            if ($nextStage !== PipelineStage::Closed->value) {
                return;
            }

            if (static::resolvePipelineComment() !== null) {
                return;
            }

            throw ValidationException::withMessages([
                'pipeline_comment' => 'A closing comment is required when moving to Closed.',
            ]);
        });

        static::created(function (Client $client): void {
            $initialStage = $client->pipeline_stage?->value ?? PipelineStage::Lead->value;
            $initialResult = $client->closing_result?->value ?? null;

            $client->pipelineActivities()->create([
                'actor_user_id' => $client->created_by_id ?? Auth::id(),
                'action' => 'created',
                'occurred_at' => $client->created_at ?? now(),
                'from_stage' => null,
                'to_stage' => $initialStage,
                'from_closing_result' => null,
                'to_closing_result' => $initialResult,
                'comment' => null,
            ]);
        });

        static::updated(function (Client $client): void {
            if (! $client->wasChanged(['pipeline_stage', 'closing_result'])) {
                return;
            }

            $previousStage = (string) $client->getRawOriginal('pipeline_stage');
            $currentStage = $client->pipeline_stage?->value ?? (string) $client->getAttribute('pipeline_stage');
            $previousResult = (string) $client->getRawOriginal('closing_result');
            $currentResult = $client->closing_result?->value ?? (string) $client->getAttribute('closing_result');

            $action = $client->wasChanged('pipeline_stage') ? 'stage_changed' : 'closing_result_updated';
            $comment = $currentStage === PipelineStage::Closed->value
                ? static::resolvePipelineComment()
                : null;

            $client->pipelineActivities()->create([
                'actor_user_id' => Auth::id(),
                'action' => $action,
                'occurred_at' => now(),
                'from_stage' => $previousStage !== '' ? $previousStage : null,
                'to_stage' => $currentStage !== '' ? $currentStage : null,
                'from_closing_result' => $previousResult !== '' ? $previousResult : null,
                'to_closing_result' => $currentResult !== '' ? $currentResult : null,
                'comment' => $comment,
            ]);
        });
    }

    /**
     * @var list<string>
     */
    protected $fillable = [
        'business_name',
        'address',
        'pipeline_stage',
        'closing_result',
        'owner_id',
        'created_by_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'pipeline_stage' => PipelineStage::class,
            'closing_result' => ClosingResult::class,
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class);
    }

    public function meetings(): HasMany
    {
        return $this->hasMany(Meeting::class);
    }

    public function pipelineActivities(): HasMany
    {
        return $this->hasMany(PipelineActivity::class)->latest('occurred_at');
    }

    protected static function resolvePipelineComment(): ?string
    {
        $rawComment = request()->input('pipeline_comment');

        if (! is_string($rawComment)) {
            return null;
        }

        $trimmedComment = trim($rawComment);

        return $trimmedComment !== '' ? $trimmedComment : null;
    }

    protected static function ensureUniqueBusinessName(Client $client): void
    {
        $normalizedBusinessName = mb_strtolower(trim((string) $client->business_name));

        if ($normalizedBusinessName === '') {
            return;
        }

        $existingClientQuery = static::query()
            ->whereRaw('LOWER(TRIM(business_name)) = ?', [$normalizedBusinessName]);

        if ($client->exists) {
            $existingClientQuery->whereKeyNot($client->getKey());
        }

        if (! $existingClientQuery->exists()) {
            return;
        }

        throw ValidationException::withMessages([
            'business_name' => 'This client exists in our records, please contact your team lead or manager to get more details.',
        ]);
    }
}
