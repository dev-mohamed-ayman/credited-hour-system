<?php

namespace App\Models;

use App\Enums\DiscountMode;
use App\Enums\DiscountScope;
use App\Enums\DiscountStatus;
use App\Enums\Semester;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StudentDiscount extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id',
        'scope',
        'fee_id',
        'year_id',
        'semester',
        'mode',
        'value',
        'remaining_amount',
        'status',
        'reason',
        'decision_number',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'scope' => DiscountScope::class,
            'mode' => DiscountMode::class,
            'status' => DiscountStatus::class,
            'semester' => Semester::class,
            'value' => 'decimal:2',
            'remaining_amount' => 'decimal:2',
            'revoked_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function year(): BelongsTo
    {
        return $this->belongsTo(Year::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function revoker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by');
    }

    public function usages(): HasMany
    {
        return $this->hasMany(StudentDiscountUsage::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(StudentDiscountEvent::class);
    }

    /**
     * Does this discount's scope (fee category, fee, year, semester) cover the given ticket parameters?
     * Status is checked by the caller — this is pure scope matching.
     */
    public function appliesTo(string $feeType, ?int $feeId = null, ?int $yearId = null, Semester|string|null $semester = null): bool
    {
        $scopeMatches = match ($this->scope) {
            DiscountScope::Registration => $feeType === 'registration',
            DiscountScope::Additional => $feeType === 'additional' && ($this->fee_id === null || $this->fee_id === $feeId),
            DiscountScope::Any => in_array($feeType, ['registration', 'additional', 'military_education', 'other'], true),
        };

        if (! $scopeMatches) {
            return false;
        }

        if ($this->year_id !== null && $this->year_id !== $yearId) {
            return false;
        }

        $discountSemester = $this->semester instanceof Semester ? $this->semester->value : $this->semester;
        $ticketSemester = $semester instanceof Semester ? $semester->value : $semester;

        if ($discountSemester !== null && $discountSemester !== $ticketSemester) {
            return false;
        }

        return true;
    }

    public function isExhausted(): bool
    {
        return $this->status === DiscountStatus::Exhausted;
    }

    public function isRevoked(): bool
    {
        return $this->status === DiscountStatus::Revoked;
    }

    public function isEditable(): bool
    {
        return $this->status === DiscountStatus::Active && $this->usages()->doesntExist();
    }
}
