<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdditionalFee extends Model
{
    protected $fillable = [
        'parent_id',
        'name',
        'gender',
        'amount',
        'is_one_time',
    ];

    public function parent()
    {
        return $this->belongsTo(AdditionalFee::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(AdditionalFee::class, 'parent_id');
    }

    public function departments()
    {
        return $this->belongsToMany(Department::class, 'additional_fee_department');
    }

    public function levels()
    {
        return $this->belongsToMany(Level::class, 'additional_fee_level');
    }

    public function sections()
    {
        return $this->belongsToMany(Section::class, 'additional_fee_section');
    }
}
