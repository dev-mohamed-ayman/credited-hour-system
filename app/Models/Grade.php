<?php

namespace App\Models;

use App\Traits\HasDeletionGuards;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Grade extends Model
{
    use HasDeletionGuards;

    protected $fillable = [
        'name',
        'points',
        'min_score',
        'max_score',
        'counts_in_gpa',
        'is_pending_default',
        'order',
    ];

    protected function casts(): array
    {
        return [
            'points' => 'decimal:2',
            'min_score' => 'decimal:2',
            'max_score' => 'decimal:2',
            'counts_in_gpa' => 'boolean',
            'is_pending_default' => 'boolean',
            'order' => 'integer',
        ];
    }

    protected $blockingRelations = ['registrationCourses'];

    public function registrationCourses(): HasMany
    {
        return $this->hasMany(RegistrationCourse::class);
    }

    public static function pendingDefault(): ?self
    {
        return static::query()->where('is_pending_default', true)->first();
    }

    /**
     * Whether the grade enters the GPA (Pending, IC and W do not).
     */
    public function countsInGpa(): bool
    {
        return $this->counts_in_gpa && ! $this->is_pending_default && $this->points !== null;
    }

    /**
     * Whether the grade comes from a total mark; IC, W and FX are assigned directly.
     */
    public function isScored(): bool
    {
        return $this->min_score !== null && $this->max_score !== null;
    }

    /**
     * Another grade whose mark range overlaps the given one, if any.
     */
    public static function overlappingRange(float $minScore, float $maxScore, ?int $ignoreId = null): ?self
    {
        return static::query()
            ->whereNotNull('min_score')
            ->whereNotNull('max_score')
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->where('min_score', '<=', $maxScore)
            ->where('max_score', '>=', $minScore)
            ->first();
    }
}
