<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DepartmentRequirement extends Model
{
    protected $fillable = ['department_id', 'subject_name', 'min_score'];

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function certificateTypes()
    {
        return $this->belongsToMany(CertificateType::class, 'certificate_type_requirement', 'department_requirement_id', 'certificate_type_id');
    }
}
