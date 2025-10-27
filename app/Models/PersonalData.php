<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\GeneratesCustomId;

class PersonalData extends Model
{
    protected $primaryKey = 'personal_data_id';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'personal_data_id', 'first_name', 'last_name', 'mid_name',
        'suffix', 'birth_date', 'address_id', 'contact_id'
    ];

    //creating a custom primary key generator for user_id
    use GeneratesCustomId;

    // Customize trait settings
    protected $customIdColumn = 'personal_data_id';
    protected $customIdPrefix = 'PDS_';
    protected $customIdPadLength = 4;

    // Boot the trait
    protected static function boot()
    {
        parent::boot();
        static::bootGeneratesCustomId();
    }

    public function address()
    {
        return $this->belongsTo(Address::class, 'address_id', 'address_id');
    }

    public function contact()
    {
        return $this->belongsTo(Contact::class, 'contact_id', 'contact_id');
    }
 
    public function user()
    {
        return $this->hasOne(User::class, 'personal_data_id', 'personal_data_id');
    }
}
