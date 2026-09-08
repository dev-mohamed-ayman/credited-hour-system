<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExamCommittee extends Model
{
    use HasFactory;

    protected $fillable = [
        'exam_session_id',
        'venue_id',
        'name',
        'capacity',
    ];

    protected function casts(): array
    {
        return [
            'capacity' => 'integer',
        ];
    }

    public function examSession(): BelongsTo
    {
        return $this->belongsTo(ExamSession::class);
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(ExamSeatAssignment::class, 'exam_committee_id');
    }

    public function assignedCount(): int
    {
        return $this->assignments()->count();
    }

    public function isFull(): bool
    {
        return $this->assignedCount() >= $this->capacity;
    }
}
