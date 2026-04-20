<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'card_show_photo' => 'boolean',
            'card_show_name' => 'boolean',
            'card_show_code' => 'boolean',
            'card_show_barcode' => 'boolean',
            'card_show_department' => 'boolean',
            'card_show_section' => 'boolean',
            'card_show_level' => 'boolean',
            'card_show_national_id' => 'boolean',
            'seat_show_photo' => 'boolean',
            'seat_show_name' => 'boolean',
            'seat_show_code' => 'boolean',
            'seat_show_department' => 'boolean',
            'seat_show_section' => 'boolean',
            'seat_show_level' => 'boolean',
            'seat_show_seat_number' => 'boolean',
            'cert_show_photo' => 'boolean',
            'cert_show_birth_info' => 'boolean',
            'cert_show_national_id' => 'boolean',
            'cert_show_seat_number' => 'boolean',
            'cert_show_specialization' => 'boolean',
            'cert_show_grade' => 'boolean',
            'cert_show_cgpa' => 'boolean',
            'cert_show_semester' => 'boolean',
            'cert_show_extra' => 'boolean',
        ];
    }
}
