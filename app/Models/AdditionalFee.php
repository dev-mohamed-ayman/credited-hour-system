<?php

namespace App\Models;

use App\Enums\Semester;
use App\Traits\HasDeletionGuards;
use Illuminate\Database\Eloquent\Model;

class AdditionalFee extends Model
{
    use HasDeletionGuards;

    protected $fillable = [
        'name',
        'gender',
        'amount',
        'is_one_time',
        'year_id',
        'semester',
    ];

    protected $blockingRelations = ['items'];

    protected function casts(): array
    {
        return [
            'semester' => Semester::class,
        ];
    }

    public function year()
    {
        return $this->belongsTo(Year::class);
    }

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
