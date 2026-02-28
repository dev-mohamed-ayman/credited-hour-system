<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentScore extends Model
{
    protected $fillable = ['student_id', 'department_requirement_id', 'score'];

    public function requirement()
    {
        return $this->belongsTo(DepartmentRequirement::class, 'department_requirement_id');
    }
}
