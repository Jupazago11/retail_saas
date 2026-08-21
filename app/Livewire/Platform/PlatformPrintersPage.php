<?php

namespace App\Livewire\Platform;

use App\Enums\RecordStatus;
use App\Livewire\Concerns\InteractsWithToast;
use App\Models\PrinterGuide;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithFileUploads;

class PlatformPrintersPage extends Component
{
    use InteractsWithToast, WithFileUploads;

    public bool $showModal = false;
    public ?int $editingId = null;
    public string $title = '';
    public string $instructions = '';
    public string $status = 'active';

    /** @var \Livewire\Features\SupportFileUploads\TemporaryUploadedFile|null */
    public $file = null;

    public function mount(): void
    {
        abort_unless(auth()->user()?->is_platform_admin, 403);
    }

    public function openCreate(): void
    {
        $this->reset(['editingId', 'title', 'instructions', 'file']);
        $this->status = 'active';
        $this->showModal = true;
    }

    public function startEdit(int $id): void
    {
        $guide = PrinterGuide::findOrFail($id);
        $this->editingId = $guide->id;
        $this->title = $guide->title;
        $this->instructions = $guide->instructions;
        $this->status = $guide->status->value;
        $this->file = null;
        $this->showModal = true;
    }

    /**
     * Sin credenciales R2 configuradas, un intento de subida fallaria con un
     * error crudo del SDK de S3 — mismo guard que AttachPaymentProof, solo
     * repetido aqui: un solo caller no justifica una Action class nueva.
     */
    public function isStorageConfigured(): bool
    {
        return Config::get('filesystems.disks.r2.key')
            && Config::get('filesystems.disks.r2.secret')
            && Config::get('filesystems.disks.r2.bucket')
            && Config::get('filesystems.disks.r2.endpoint');
    }

    public function save(): void
    {
        abort_unless(auth()->user()?->is_platform_admin, 403);

        $validated = $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'instructions' => ['required', 'string'],
            'file' => ['nullable', 'file', 'max:20480'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);

        $payload = [
            'title' => $validated['title'],
            'instructions' => $validated['instructions'],
            'status' => $validated['status'],
        ];

        if ($this->file) {
            if (! $this->isStorageConfigured()) {
                $this->addError('file', 'Todavia no se configuraron las credenciales de Cloudflare R2 (variables R2_* en el .env).');

                return;
            }

            $oldPath = $this->editingId ? PrinterGuide::findOrFail($this->editingId)->path : null;

            // Sin scoping por empresa: esto no es dato de un tenant, es un
            // catalogo compartido que administra solo superadmin.
            $path = 'printer-guides/'.Str::uuid().'.'.$this->file->getClientOriginalExtension();

            Storage::disk('r2')->putFileAs('', $this->file, $path);

            $payload['disk'] = 'r2';
            $payload['path'] = $path;
            $payload['url'] = Storage::disk('r2')->url($path);
            $payload['original_filename'] = $this->file->getClientOriginalName();

            if ($oldPath) {
                Storage::disk('r2')->delete($oldPath);
            }
        }

        if ($this->editingId) {
            $guide = PrinterGuide::findOrFail($this->editingId);
            $guide->update($payload);
        } else {
            $payload['created_by'] = auth()->id();
            $guide = PrinterGuide::create($payload);
        }

        $this->showModal = false;
        $this->reset(['file']);
        $this->toast($this->editingId ? 'Guia actualizada.' : 'Guia creada.');
    }

    public function toggleStatus(int $id): void
    {
        abort_unless(auth()->user()?->is_platform_admin, 403);

        $guide = PrinterGuide::findOrFail($id);
        $guide->update([
            'status' => $guide->status === RecordStatus::Active
                ? RecordStatus::Inactive->value
                : RecordStatus::Active->value,
        ]);

        $this->toast('Estado de la guia actualizado.');
    }

    public function closeModal(): void
    {
        $this->showModal = false;
    }

    public function render(): View
    {
        $guides = PrinterGuide::latest('id')->get();

        return view('livewire.platform.platform-printers-page', [
            'guides' => $guides,
        ])->layout('layouts.platform');
    }
}
