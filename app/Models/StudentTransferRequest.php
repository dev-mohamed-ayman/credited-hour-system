<?php

namespace App\Models;

use App\Enums\Semester;
use App\Enums\TransferRequestStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentTransferRequest extends Model
{
    protected $fillable = [
        'student_id',
        'from_department_id',
        'from_section_id',
        'from_level_id',
        'to_department_id',
        'to_section_id',
        'to_level_id',
        'year_id',
        'semester',
        'status',
        'reason',
        'rejection_reason',
        'refunded_amount',
        'reversal_snapshot',
        'created_by_user_id',
        'decided_by_user_id',
        'decided_at',
    ];

    protected function casts(): array
    {
        return [
            'semester' => Semester::class,
            'status' => TransferRequestStatus::class,
            'refunded_amount' => 'decimal:2',
            'reversal_snapshot' => 'array',
            'decided_at' => 'datetime',
        ];
    }

    public function isPending(): bool
    {
        return $this->status === TransferRequestStatus::PENDING;
    }

    public function scopePending($query)
    {
        return $query->where('status', TransferRequestStatus::PENDING->value);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function fromDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'from_department_id');
    }

    public function fromSection(): BelongsTo
    {
        return $this->belongsTo(Section::class, 'from_section_id');
    }

    public function fromLevel(): BelongsTo
    {
        return $this->belongsTo(Level::class, 'from_level_id');
    }

    public function toDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'to_department_id');
    }

    public function toSection(): BelongsTo
    {
        return $this->belongsTo(Section::class, 'to_section_id');
    }

    public function toLevel(): BelongsTo
    {
        return $this->belongsTo(Level::class, 'to_level_id');
    }

    public function year(): BelongsTo
    {
        return $this->belongsTo(Year::class);
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function decidedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by_user_id');
    }
}
