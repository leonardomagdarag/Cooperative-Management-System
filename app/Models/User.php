<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use App\Traits\GeneratesCustomId;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    use HasFactory, Notifiable;

    protected $primaryKey = 'user_id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'personal_data_id',
        'email',
        'password',
        'role'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    //creating a custom primary key generator for user_id
    use GeneratesCustomId;

    // Customize trait settings
    protected $customIdColumn = 'user_id';
    protected $customIdPrefix = 'USR_';
    protected $customIdPadLength = 4;

    // Boot the trait
    protected static function boot()
    {
        parent::boot();
        static::bootGeneratesCustomId();
    }

    // eloquent relationships
    public function personalData()
    {
        return $this->belongsTo(PersonalData::class, 'personal_data_id', 'personal_data_id');
    }
    /*
    Disabled Contact relationship to avoid confusion
    public function contact()
    {
        return $this->belongsTo(Contact::class, 'contact_id', 'contact_id');
    }
    */ 

    public function accounts()
    {
        return $this->hasMany(Account::class, 'user_id', 'user_id');
    }

    public function logs()
    {
        return $this->hasMany(Log::class, 'user_id', 'user_id');
    }
}
