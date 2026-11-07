<?php

namespace App\Models;

use App\Enums\Semester;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A term whose end-of-term procedures (warnings, or promotion after the
 * summer) have run. One per year + semester, so they never run twice.
 */
class TermClosure extends Model
{
    /** @use HasFactory<\Database\Factories\TermClosureFactory> */
    use HasFactory;

    protected $fillable = [
        'year_id',
        'semester',
        'closed_by',
    ];

    protected function casts(): array
    {
        return [
            'semester' => Semester::class,
        ];
    }

    public function year(): BelongsTo
    {
        return $this->belongsTo(Year::class);
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function records(): HasMany
    {
        return $this->hasMany(StudentTermRecord::class);
    }

    public function warnings(): HasMany
    {
        return $this->hasMany(StudentWarning::class);
    }

    public function isPromotion(): bool
    {
        return $this->semester === Semester::SUMMER;
    }
}
