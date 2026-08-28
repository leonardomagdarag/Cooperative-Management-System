<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LoanDocument extends Model
{
    use HasFactory;

    protected $fillable = [
        'loan_id',
        'original_name',
        'storage_path',
        'mime_type',
        'file_size',
        'uploaded_by',
    ];

    protected $casts = [
        'file_size' => 'integer',
    ];

    public function loan()
    {
        return $this->belongsTo(Loan::class);
    }

    public function uploadedBy()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
