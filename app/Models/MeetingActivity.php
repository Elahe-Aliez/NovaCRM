<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeetingActivity extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'meeting_id',
        'actor_user_id',
        'action',
        'occurred_at',
        'from_scheduled_at',
        'to_scheduled_at',
        'from_result',
        'to_result',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'from_scheduled_at' => 'datetime',
            'to_scheduled_at' => 'datetime',
        ];
    }

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
