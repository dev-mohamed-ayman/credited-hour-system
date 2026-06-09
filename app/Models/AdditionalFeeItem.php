<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdditionalFeeItem extends Model
{
    protected $fillable = [
        'additional_fee_id',
        'name',
        'amount',
    ];

    public function additionalFee()
    {
        return $this->belongsTo(AdditionalFee::class);
    }
}
