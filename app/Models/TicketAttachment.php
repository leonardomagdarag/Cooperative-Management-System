<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketAttachment extends Model
{
    use HasFactory;

    protected $fillable = [
        'ticket_id',
        'ticket_message_id',
        'file_path',
        'file_name',
        'file_size',
        'mime_type',
    ];

    protected $appends = [
        'url',
    ];

    protected function url(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->file_path ? asset('storage/'.$this->file_path) : null,
        );
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(TicketMessage::class, 'ticket_message_id');
    }
}
