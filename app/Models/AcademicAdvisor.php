<?php

namespace App\Models;

use App\Traits\HasDeletionGuards;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class AcademicAdvisor extends Authenticatable
{
    use SoftDeletes, HasDeletionGuards;

    protected $fillable = ['name', 'username', 'password', 'max_students', 'current_students', 'is_active'];
    protected $blockingRelations = ['students', 'assignments'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'password' => 'hashed',
        ];
    }

    protected $hidden = [
        'password',
    ];

    public function assignments(): HasMany
    {
        return $this->hasMany(AcademicAdvisorAssignment::class);
    }

    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }
}
