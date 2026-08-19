<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MemberId extends Model
{
    use HasFactory;

    protected $fillable = [
        'national_id',
        'national_id_photo',
        'tin',
        'tin_photo',
        'sss',
        'sss_photo',
        'philhealth',
        'philhealth_photo',
        'barangay_id',
        'barangay_id_photo',
        'proof_of_registration_path',
        'other_document_path',
    ];

    // Relationships
    public function personalData()
    {
        return $this->belongsTo(PersonalData::class, 'member_id');
    }
}
