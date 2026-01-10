<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MemberId extends Model
{
    use HasFactory;

    protected $fillable = [
        'tin',
        'sss',
        'philhealth',
        'barangay_id'
    ];

    // Relationships
    public function personalData()
    {
        return $this->hasOne(PersonalData::class, 'member_id');
    }
}
