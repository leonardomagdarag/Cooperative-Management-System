<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Log extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'activity',
        'description',
        'ip_address'
    ];

    //Eloquent Relationships

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
