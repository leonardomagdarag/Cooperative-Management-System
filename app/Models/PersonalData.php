<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PersonalData extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'personal_data_number',
        'title',
        'first_name',
        'last_name',
        'mid_name',
        'suffix',
        'birth_date',
        'birthplace',
        'gender',
        'education',
        'civil_status',
        'religion',
        'address_id',
        'contact_id',
        'employment_id',
        'member_id'
    ];

    protected $casts = [
        'birth_date' => 'date',
    ];

    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($personalData) {
            if (empty($personalData->personal_data_number)) {
                // Get the latest personal_data_number and extract the numeric part
                $latest = static::withTrashed()
                    ->whereNotNull('personal_data_number')
                    ->orderByRaw('CAST(SUBSTRING(personal_data_number, 4) AS UNSIGNED) DESC')
                    ->first();
                
                if ($latest && preg_match('/PDS(\d+)/', $latest->personal_data_number, $matches)) {
                    $nextNumber = intval($matches[1]) + 1;
                } else {
                    $nextNumber = 1;
                }
                
                $personalData->personal_data_number = 'PDS' . str_pad($nextNumber, 6, '0', STR_PAD_LEFT);
            }
        });
    }

    public function address()
    {
        return $this->belongsTo(Address::class);
    }

    public function contact()
    {
        return $this->belongsTo(Contact::class);
    }

    public function employment()
    {
        return $this->belongsTo(Employment::class);
    }

    public function memberId()
    {
        return $this->belongsTo(MemberId::class, 'member_id');
    }

    public function user()
    {
        return $this->hasOne(User::class);
    }
}
