<?php

namespace App\Models;

use App\Enums\RecordStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class PrinterGuide extends Model
{
    protected $fillable = [
        'title',
        'instructions',
        'disk',
        'path',
        'url',
        'original_filename',
        'status',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => RecordStatus::class,
        ];
    }

    /**
     * El bucket es privado: en vez de una URL publica permanente, cada vez
     * que se muestra se genera un link firmado que vence solo (mismo patron
     * que PaymentAttachment::temporaryUrl()).
     */
    public function temporaryUrl(int $minutes = 60): string
    {
        return Storage::disk($this->disk)->temporaryUrl($this->path, now()->addMinutes($minutes));
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
