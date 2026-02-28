<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CertificateType extends Model
{
    protected $fillable = ['name', 'total_score'];

    public function requirements()
    {
        return $this->belongsToMany(DepartmentRequirement::class, 'certificate_type_requirement', 'certificate_type_id', 'department_requirement_id');
    }
}
