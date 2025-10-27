<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\GeneratesCustomId;

class Log extends Model
{
    protected $primaryKey = 'log_id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'log_id', 'user_id', 'activity'
    ];

    //creating a custom primary key generator for user_id
    use GeneratesCustomId;

    // Customize trait settings
    protected $customIdColumn = 'log_id';
    protected $customIdPrefix = 'LOG_';
    protected $customIdPadLength = 4;

    // Boot the trait
    protected static function boot()
    {
        parent::boot();
        static::bootGeneratesCustomId();
    }

    //Eloquent Relationships

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }
}
