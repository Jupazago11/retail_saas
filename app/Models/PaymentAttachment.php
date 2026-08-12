<?php

namespace App\Models;

use App\Enums\RecordStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class PaymentAttachment extends Model
{
    protected $fillable = [
        'subscription_id',
        'disk',
        'path',
        'url',
        'original_filename',
        'status',
        'uploaded_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => RecordStatus::class,
        ];
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    /**
     * El bucket es privado a proposito (son comprobantes de pago, pueden
     * traer numeros de cuenta). En vez de una URL publica permanente,
     * cada vez que se muestra se genera un link firmado que vence solo.
     */
    public function temporaryUrl(int $minutes = 60): string
    {
        return Storage::disk($this->disk)->temporaryUrl($this->path, now()->addMinutes($minutes));
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
