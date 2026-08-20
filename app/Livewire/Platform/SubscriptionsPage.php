<?php

namespace App\Livewire\Platform;

use App\Actions\Equipment\ManageEquipmentRental;
use App\Actions\Plans\ReconcileCompanyInventoryTracking;
use App\Actions\Plans\ReconcileCompanyStructureLimits;
use App\Actions\Subscriptions\AttachPaymentProof;
use App\Actions\Subscriptions\ChangeCompanySubscription;
use App\Enums\EquipmentRentalStatus;
use App\Enums\EquipmentType;
use App\Enums\RecordStatus;
use App\Livewire\Concerns\HasResponsivePageSize;
use App\Livewire\Concerns\InteractsWithToast;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\EquipmentRental;
use App\Models\PaymentAttachment;
use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use InvalidArgumentException;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

class SubscriptionsPage extends Component
{
    use HasResponsivePageSize, InteractsWithToast, WithFileUploads, WithPagination;

    public int $perPage = 20;

    public string $search   = '';
    public string $filter   = 'all';
    public bool   $showModal = false;

    public ?int   $editingId  = null;
    public string $editPlanId = '';
    public string $editEndsAt = '';

    public bool   $showActivateModal       = false;
    public ?int   $activateCompanyId       = null;
    public string $activatePlanId          = '';
    public string $activateStartsAt        = '';
    public string $activateEndsAt          = '';
    public string $activatePaymentReference = '';

    public bool   $showEquipmentModal = false;
    public ?int   $equipmentCompanyId = null;
    public string $equipmentNotes     = '';

    public bool   $showAttachmentsModal    = false;
    public ?int   $attachmentsSubscriptionId = null;
    /** @var array<int, \Livewire\Features\SupportFileUploads\TemporaryUploadedFile> */
    public array  $newAttachments         = [];

    public function mount(): void
    {
        abort_unless(auth()->user()?->is_platform_admin, 403);
    }

    public function updatedSearch(): void { $this->resetPage(); }

    public function setFilter(string $filter): void
    {
        if (! in_array($filter, ['all', 'pending', 'active', 'trialing', 'vencida'], true)) {
            return;
        }

        $this->filter = $filter;
        $this->resetPage();
    }

    public function startEdit(int $id): void
    {
        $sub = Subscription::with('plan')->findOrFail($id);
        $this->editingId  = $sub->id;
        $this->editPlanId = (string) ($sub->plan_id ?? '');
        $this->editEndsAt = $sub->ends_at?->format('Y-m-d') ?? '';
        $this->showModal  = true;
    }

    public function saveEdit(ReconcileCompanyStructureLimits $reconcileCompanyStructureLimits, ReconcileCompanyInventoryTracking $reconcileCompanyInventoryTracking): void
    {
        abort_unless(auth()->user()?->is_platform_admin, 403);

        $this->validate([
            'editPlanId' => ['required', 'exists:plans,id'],
            'editEndsAt' => ['nullable', 'date'],
        ]);

        $sub = Subscription::findOrFail($this->editingId);
        $sub->update([
            'plan_id'  => (int) $this->editPlanId,
            'ends_at'  => $this->editEndsAt !== '' ? $this->editEndsAt : null,
        ]);

        $reconcileCompanyStructureLimits->handle($sub->company, auth()->user());
        $reconcileCompanyInventoryTracking->handle($sub->company, auth()->user());

        $this->showModal = false;
        $this->toast('Suscripción actualizada.');
    }

    public function closeModal(): void
    {
        $this->showModal  = false;
        $this->editingId  = null;
        $this->editPlanId = '';
        $this->editEndsAt = '';
    }

    public function toggleAutoRenew(int $companyId): void
    {
        abort_unless(auth()->user()?->is_platform_admin, 403);

        $company = Company::findOrFail($companyId);
        $company->update(['auto_renew' => ! $company->auto_renew]);

        $this->toast($company->auto_renew
            ? 'Renovación automática encendida.'
            : 'Renovación automática apagada.');
    }

    public function startActivate(int $companyId): void
    {
        $this->activateCompanyId = $companyId;
        $this->activatePlanId    = '';
        $this->activateStartsAt  = now()->format('Y-m-d');
        // Vencimiento sugerido: 31 dias desde hoy. El admin puede cambiarla
        // o dejarla en blanco a proposito para una suscripcion sin vencimiento.
        $this->activateEndsAt    = now()->addDays(31)->format('Y-m-d');
        $this->activatePaymentReference = '';
        $this->showActivateModal = true;
    }

    /**
     * Activar un plan desde aqui es, por definicion, el momento en que ya
     * verificaste el comprobante de pago (WhatsApp) — por eso queda
     * marcado como pagado de una vez, con la referencia que escribas.
     */
    public function saveActivate(ChangeCompanySubscription $changeCompanySubscription): void
    {
        abort_unless(auth()->user()?->is_platform_admin, 403);

        $this->validate([
            'activatePlanId'   => ['required', 'exists:plans,id'],
            'activateStartsAt' => ['nullable', 'date'],
            'activateEndsAt'   => ['nullable', 'date'],
            'activatePaymentReference' => ['nullable', 'string', 'max:255'],
        ]);

        $company = Company::findOrFail($this->activateCompanyId);

        try {
            $changeCompanySubscription->handle($company, [
                'plan_id'   => (int) $this->activatePlanId,
                'status'    => 'active',
                'starts_at' => $this->activateStartsAt !== '' ? $this->activateStartsAt : now(),
                'ends_at'   => $this->activateEndsAt !== '' ? $this->activateEndsAt : null,
                'paid_at'   => now(),
                'payment_reference' => $this->blankToNull($this->activatePaymentReference),
            ], auth()->user());
        } catch (InvalidArgumentException $exception) {
            $this->addError('activatePlanId', $exception->getMessage());

            return;
        }

        $this->closeActivateModal();
        $this->toast('Nuevo plan activado y marcado como pagado.');
    }

    public function closeActivateModal(): void
    {
        $this->showActivateModal = false;
        $this->activateCompanyId = null;
        $this->activatePlanId    = '';
        $this->activateStartsAt  = '';
        $this->activateEndsAt    = '';
        $this->activatePaymentReference = '';
    }

    /**
     * Confirma retroactivamente el pago de una suscripcion que ya existe
     * (por ejemplo, una que se auto-reno vo sola por auto_renew y todavia
     * nadie ha verificado el comprobante).
     */
    public function markPaid(int $subscriptionId): void
    {
        abort_unless(auth()->user()?->is_platform_admin, 403);

        $subscription = Subscription::findOrFail($subscriptionId);
        $subscription->update(['paid_at' => now()]);

        $this->toast('Suscripción marcada como pagada.');
    }

    public function openAttachmentsModal(int $subscriptionId): void
    {
        abort_unless(auth()->user()?->is_platform_admin, 403);

        $this->attachmentsSubscriptionId = $subscriptionId;
        $this->newAttachments = [];
        $this->resetValidation();
        $this->showAttachmentsModal = true;
    }

    public function closeAttachmentsModal(): void
    {
        $this->showAttachmentsModal = false;
        $this->attachmentsSubscriptionId = null;
        $this->newAttachments = [];
    }

    public function attachmentsSubscription(): ?Subscription
    {
        return $this->attachmentsSubscriptionId
            ? Subscription::with('company')->find($this->attachmentsSubscriptionId)
            : null;
    }

    public function attachmentsForModal(): Collection
    {
        if (! $this->attachmentsSubscriptionId) {
            return new Collection;
        }

        return PaymentAttachment::query()
            ->where('subscription_id', $this->attachmentsSubscriptionId)
            ->where('status', RecordStatus::Active->value)
            ->latest('id')
            ->get();
    }

    public function isR2Configured(): bool
    {
        return app(AttachPaymentProof::class)->isStorageConfigured();
    }

    public function uploadAttachments(AttachPaymentProof $action): void
    {
        abort_unless(auth()->user()?->is_platform_admin, 403);

        $this->validate([
            'newAttachments'   => ['required', 'array', 'min:1'],
            'newAttachments.*' => ['image', 'max:20480'],
        ], [
            'newAttachments.required' => 'Elige al menos una foto para subir.',
            'newAttachments.*.image' => 'Ese archivo no es una imagen valida (debe ser jpg, png o similar).',
            'newAttachments.*.max' => 'Esa foto pesa mas de 20MB. Comprimela o recortala e intenta de nuevo.',
        ]);

        $subscription = $this->attachmentsSubscription();
        abort_unless($subscription, 404);

        if (! $action->isStorageConfigured()) {
            $this->addError('newAttachments', 'Todavia no se configuraron las credenciales de Cloudflare R2 (variables R2_* en el .env).');

            return;
        }

        foreach ($this->newAttachments as $file) {
            $action->handle($subscription, $file, auth()->user());
        }

        $this->newAttachments = [];
        $this->toast('Comprobante(s) adjuntado(s).');
    }

    public function archiveAttachment(int $attachmentId, AttachPaymentProof $action): void
    {
        abort_unless(auth()->user()?->is_platform_admin, 403);

        $attachment = PaymentAttachment::query()
            ->where('subscription_id', $this->attachmentsSubscriptionId)
            ->findOrFail($attachmentId);

        $action->archive($attachment, auth()->user());
        $this->toast('Comprobante archivado.');
    }

    public function plans(): Collection
    {
        return Plan::orderBy('id')->get();
    }

    /**
     * Estado de alquiler de equipos por empresa, para las empresas visibles
     * en la pagina actual: company_id -> equipment_type -> [status -> cantidad].
     * Se agrupa en una sola consulta para pintar la columna "Equipos" sin
     * una consulta por fila.
     */
    public function equipmentByCompany(iterable $companyIds): array
    {
        return EquipmentRental::query()
            ->whereIn('company_id', $companyIds)
            ->whereIn('status', [
                EquipmentRentalStatus::Requested->value,
                EquipmentRentalStatus::Active->value,
                EquipmentRentalStatus::PendingReturn->value,
            ])
            ->get()
            ->groupBy('company_id')
            ->map(fn ($rentals) => $rentals
                ->groupBy(fn (EquipmentRental $rental) => $rental->equipment_type->value)
                ->map(fn ($group) => $group->countBy(fn (EquipmentRental $rental) => $rental->status->value)))
            ->all();
    }

    /**
     * El alquiler de equipos es una relacion con la empresa, no con un
     * periodo de plan puntual — mostrar el mismo resumen en cada fila
     * historica de esa empresa se veia como si se hubiera duplicado.
     * Este mapa dice, por empresa, en cual fila (la suscripcion activa, o
     * si no hay ninguna activa la mas reciente) va la columna "Equipos".
     */
    protected function equipmentDisplaySubscriptionIds(iterable $companyIds): array
    {
        return Subscription::query()
            ->whereNull('bundle_id')
            ->whereIn('company_id', $companyIds)
            ->orderByDesc('id')
            ->get(['id', 'company_id', 'status'])
            ->groupBy('company_id')
            ->map(fn ($group) => $group->firstWhere('status', 'active')?->id ?? $group->first()->id)
            ->all();
    }

    public function openEquipmentModal(int $companyId): void
    {
        abort_unless(auth()->user()?->is_platform_admin, 403);

        $this->equipmentCompanyId = $companyId;
        $this->equipmentNotes = '';
        $this->showEquipmentModal = true;
    }

    public function closeEquipmentModal(): void
    {
        $this->showEquipmentModal = false;
        $this->equipmentCompanyId = null;
        $this->equipmentNotes = '';
    }

    public function equipmentCompany(): ?Company
    {
        return $this->equipmentCompanyId ? Company::find($this->equipmentCompanyId) : null;
    }

    public function equipmentRentalsForModal(): Collection
    {
        if (! $this->equipmentCompanyId) {
            return new Collection;
        }

        return EquipmentRental::query()
            ->where('company_id', $this->equipmentCompanyId)
            ->with('replaces')
            ->whereIn('status', [
                EquipmentRentalStatus::Requested->value,
                EquipmentRentalStatus::Active->value,
                EquipmentRentalStatus::PendingReturn->value,
            ])
            ->orderBy('equipment_type')
            ->orderByDesc('id')
            ->get();
    }

    public function equipmentAuditForModal(): Collection
    {
        if (! $this->equipmentCompanyId) {
            return new Collection;
        }

        return AuditLog::query()
            ->where('company_id', $this->equipmentCompanyId)
            ->where('auditable_type', EquipmentRental::class)
            ->with('actor')
            ->latest('id')
            ->limit(15)
            ->get();
    }

    /**
     * "Impresora termica #2 agregada" en vez de solo "Unidad agregada": sin
     * decir cual equipo y cual numero, dos movimientos seguidos (uno por
     * impresora, otro por lector) se ven identicos en el historial.
     */
    public function equipmentAuditLabel(AuditLog $log): string
    {
        $snapshot = $log->after_snapshot ?? $log->before_snapshot ?? [];
        $typeCode = $snapshot['equipment_type'] ?? null;
        $sequence = $snapshot['company_sequence'] ?? null;

        $typeLabel = match ($typeCode) {
            'thermal_printer' => 'Impresora termica',
            'barcode_scanner' => 'Lector de codigo de barras',
            default => 'Equipo',
        };

        $identifier = $sequence ? "{$typeLabel} #{$sequence}" : $typeLabel;

        $actionLabel = match ($log->action) {
            'equipment.requested' => 'solicitada',
            'equipment.added' => 'agregada',
            'equipment.fulfilled' => 'entregada',
            'equipment.return_requested' => 'con devolucion solicitada',
            'equipment.returned' => 'marcada como devuelta',
            'equipment.replaced' => 'reemplazada',
            'equipment.replacement_created' => 'creada como reemplazo',
            default => $log->action,
        };

        return "{$identifier} {$actionLabel}";
    }

    public function requestEquipment(string $type, ManageEquipmentRental $action): void
    {
        abort_unless(auth()->user()?->is_platform_admin, 403);

        $company = $this->equipmentCompany();
        abort_unless($company, 404);

        $action->requestUnit($company, EquipmentType::from($type), auth()->user(), $this->blankToNull($this->equipmentNotes));
        $this->equipmentNotes = '';
        $this->toast('Solicitud registrada.');
    }

    public function addEquipment(string $type, ManageEquipmentRental $action): void
    {
        abort_unless(auth()->user()?->is_platform_admin, 403);

        $company = $this->equipmentCompany();
        abort_unless($company, 404);

        $action->addUnit($company, EquipmentType::from($type), auth()->user(), $this->blankToNull($this->equipmentNotes));
        $this->equipmentNotes = '';
        $this->toast('Unidad agregada, se factura desde hoy.');
    }

    public function fulfillEquipment(int $rentalId, ManageEquipmentRental $action): void
    {
        abort_unless(auth()->user()?->is_platform_admin, 403);

        $rental = EquipmentRental::query()->where('company_id', $this->equipmentCompanyId)->findOrFail($rentalId);
        $action->fulfillRequest($rental, auth()->user());
        $this->toast('Unidad entregada, empieza a facturarse.');
    }

    public function requestEquipmentReturn(int $rentalId, ManageEquipmentRental $action): void
    {
        abort_unless(auth()->user()?->is_platform_admin, 403);

        $rental = EquipmentRental::query()->where('company_id', $this->equipmentCompanyId)->findOrFail($rentalId);
        $action->requestReturn($rental, auth()->user(), $this->blankToNull($this->equipmentNotes));
        $this->equipmentNotes = '';
        $this->toast('Devolucion solicitada, se dejo de facturar.');
    }

    public function markEquipmentReturned(int $rentalId, ManageEquipmentRental $action): void
    {
        abort_unless(auth()->user()?->is_platform_admin, 403);

        $rental = EquipmentRental::query()->where('company_id', $this->equipmentCompanyId)->findOrFail($rentalId);
        $action->markReturned($rental, auth()->user());
        $this->toast('Marcado como devuelto.');
    }

    public function replaceEquipment(int $rentalId, ManageEquipmentRental $action): void
    {
        abort_unless(auth()->user()?->is_platform_admin, 403);

        $rental = EquipmentRental::query()->where('company_id', $this->equipmentCompanyId)->findOrFail($rentalId);
        $action->markReplaced($rental, auth()->user(), $this->blankToNull($this->equipmentNotes));
        $this->equipmentNotes = '';
        $this->toast('Reemplazo registrado.');
    }

    public function formatMoney(mixed $value): string
    {
        return \App\Support\Money::format((float) $value);
    }

    protected function blankToNull(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    public function render(): View
    {
        $subscriptions = Subscription::query()
            ->with(['company.owner', 'plan'])
            ->whereNull('bundle_id')
            ->when($this->filter === 'vencida', fn ($q) => $q->dueForExpiration(now()))
            ->when(! in_array($this->filter, ['all', 'vencida'], true), fn ($q) => $q->where('status', $this->filter))
            ->when($this->search !== '', function ($q) {
                $s = '%'.trim($this->search).'%';
                $q->whereHas('company', fn ($cq) => $cq
                    ->whereLike('display_name', $s)
                    ->orWhereLike('legal_name', $s));
            })
            ->orderByDesc('id')
            ->paginate($this->perPage);

        $latestSubscriptionIds = Subscription::query()
            ->whereNull('bundle_id')
            ->selectRaw('MAX(id) as id')
            ->groupBy('company_id')
            ->pluck('id')
            ->all();

        $companyIds = $subscriptions->pluck('company_id')->unique();

        return view('livewire.platform.subscriptions-page', [
            'subscriptions'          => $subscriptions,
            'plans'                  => $this->plans(),
            'latestSubscriptionIds'  => $latestSubscriptionIds,
            'equipmentTypes'         => EquipmentType::cases(),
            'equipmentByCompany'     => $this->equipmentByCompany($companyIds),
            'equipmentDisplaySubscriptionIds' => $this->equipmentDisplaySubscriptionIds($companyIds),
        ])->layout('layouts.platform');
    }
}
