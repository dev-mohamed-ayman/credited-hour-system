<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdditionalFee extends Model
{
    protected $fillable = [
        'name',
        'gender',
        'amount',
        'is_one_time',
    ];

    public function items()
    {
        return $this->hasMany(AdditionalFeeItem::class);
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
