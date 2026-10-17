<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class PaymentAttachment extends Model
{
    protected $fillable = [
        'payment_id', 'disk', 'path', 'original_name', 'mime', 'size', 'uploaded_by',
    ];

    /**
     * @return BelongsTo<Payment, $this>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function isImage(): bool
    {
        return str_starts_with((string) $this->mime, 'image/');
    }

    public function deleteFile(): void
    {
        Storage::disk($this->disk)->delete($this->path);
    }
}
