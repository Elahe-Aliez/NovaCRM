<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;

class Contact extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (Contact $contact): void {
            if ($contact->created_by_id === null && Auth::check()) {
                $contact->created_by_id = Auth::id();
            }
        });
    }

    /**
     * @var list<string>
     */
    protected $fillable = [
        'client_id',
        'created_by_id',
        'name',
        'email',
        'position',
        'phone',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function meetings(): HasMany
    {
        return $this->hasMany(Meeting::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }
}
