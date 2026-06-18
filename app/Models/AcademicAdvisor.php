<?php

namespace App\Models;

use App\Traits\HasDeletionGuards;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class AcademicAdvisor extends Model
{
    use SoftDeletes, HasDeletionGuards;

    protected $fillable = ['name', 'username', 'max_students', 'current_students', 'is_active'];
    protected $blockingRelations = ['students', 'assignments'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(AcademicAdvisorAssignment::class);
    }

    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }
}
