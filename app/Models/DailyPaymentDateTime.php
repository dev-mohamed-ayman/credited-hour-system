<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DailyPaymentDateTime extends Model
{
    protected $table = 'daily_payments_datetime';

    protected $fillable = [
        'date',
        'start_date',
        'end_date',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'start_date' => 'datetime',
            'end_date' => 'datetime',
        ];
    }
}
