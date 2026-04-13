<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'card_show_photo'      => 'boolean',
            'card_show_name'       => 'boolean',
            'card_show_code'       => 'boolean',
            'card_show_barcode'    => 'boolean',
            'card_show_department' => 'boolean',
            'card_show_section'    => 'boolean',
            'card_show_level'      => 'boolean',
            'card_show_national_id' => 'boolean',
        ];
    }
}
