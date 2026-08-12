<?php

namespace App\Livewire\Admin;

use App\Actions\Audit\ListAuditLogs;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\User;
use App\Services\Tenancy\CurrentCompany;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
use Livewire\Component;

class AuditLogsPage extends Component
{
    public string $action = '';
    public ?int $actorUserId = null;
    public string $auditableType = '';
    public string $auditableId = '';
    public string $dateFrom = '';
    public string $dateTo = '';
    public string $ipAddress = '';
    public string $search = '';
    public ?int $expandedLogId = null;

    public function mount(): void
    {
        $this->ensurePermission('settings.manage');
    }

    public function toggleLog(int $logId): void
    {
        $this->expandedLogId = $this->expandedLogId === $logId
            ? null
            : $logId;
    }

    public function logs(ListAuditLogs $listAuditLogs): SupportCollection
    {
        return $listAuditLogs->handle($this->currentCompany(), [
            'action' => $this->action,
            'actor_user_id' => $this->actorUserId,
            'auditable_type' => $this->auditableType,
            'auditable_id' => $this->auditableId,
            'date_from' => $this->dateFrom,
            'date_to' => $this->dateTo,
            'ip_address' => $this->ipAddress,
            'search' => $this->search,
        ]);
    }

    public function exportUrl(): string
    {
        return route('admin.audit-logs.export', array_filter([
            'action' => $this->action,
            'actor_user_id' => $this->actorUserId,
            'auditable_type' => $this->auditableType,
            'auditable_id' => $this->auditableId,
            'date_from' => $this->dateFrom,
            'date_to' => $this->dateTo,
            'ip_address' => $this->ipAddress,
            'search' => $this->search,
        ], fn ($value) => $value !== null && $value !== ''));
    }

    public function actors(): Collection
    {
        return $this->currentCompany()
            ->users()
            ->orderBy('name')
            ->get();
    }

    public function actionOptions(): SupportCollection
    {
        return AuditLog::query()
            ->where('company_id', $this->currentCompany()->id)
            ->select('action')
            ->distinct()
            ->orderBy('action')
            ->pluck('action');
    }

    public function auditableTypeOptions(): SupportCollection
    {
        return AuditLog::query()
            ->where('company_id', $this->currentCompany()->id)
            ->select('auditable_type')
            ->distinct()
            ->orderBy('auditable_type')
            ->pluck('auditable_type');
    }

    public function auditableTypeLabel(?string $type): string
    {
        if (! $type) {
            return 'Sin entidad';
        }

        return match ($type) {
            'App\\Models\\Sale' => 'Venta',
            'App\\Models\\Purchase' => 'Compra',
            'App\\Models\\Payment' => 'Pago',
            'App\\Models\\CashSession' => 'Sesion de caja',
            'App\\Models\\InventoryAdjustment' => 'Ajuste de inventario',
            'App\\Models\\InventoryTransfer' => 'Traslado',
            'App\\Models\\FrozenSale' => 'Venta congelada',
            'App\\Models\\Promotion' => 'Promocion',
            'App\\Models\\EquipmentRental' => 'Alquiler de equipo',
            default => class_basename($type),
        };
    }

    public function snapshotJson(?array $snapshot): string
    {
        if ($snapshot === null || $snapshot === []) {
            return '{}';
        }

        return json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            ?: '{}';
    }

    public function render(): View
    {
        return view('livewire.admin.audit-logs-page', [
            'logs' => $this->logs(app(ListAuditLogs::class)),
            'actors' => $this->actors(),
            'actionOptions' => $this->actionOptions(),
            'auditableTypeOptions' => $this->auditableTypeOptions(),
        ])->layout('layouts.app', [
            'header' => view('components.page-title', [
                'title' => 'Auditoria',
                'description' => 'Consulta acciones criticas por empresa, filtra eventos y revisa snapshots before/after sin salir del backoffice.',
            ]),
        ]);
    }

    protected function currentCompany(): Company
    {
        return app(CurrentCompany::class)->company()
            ?? abort(404, 'No hay una empresa activa seleccionada.');
    }

    protected function ensurePermission(string $permissionCode): void
    {
        abort_unless(
            auth()->user()?->hasCurrentCompanyPermission($permissionCode),
            403,
            'No tienes permiso para acceder a este modulo.'
        );
    }
}
