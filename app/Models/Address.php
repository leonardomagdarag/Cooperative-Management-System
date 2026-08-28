<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Address extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'country',
        'region',
        'province',
        'city_municipality',
        'barangay',
        'street',
        'zip_code',
    ];

    // Eloquent Relationship

    public function personalData()
    {
        return $this->hasOne(PersonalData::class, 'address_id');
    }
}
