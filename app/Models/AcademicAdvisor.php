<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AcademicAdvisor extends Model
{
    protected $fillable = ['name', 'username', 'max_students', 'current_students'];

    public function assignments(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(AcademicAdvisorAssignment::class);
    }

    public function students(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Student::class);
    }
}
