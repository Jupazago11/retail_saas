<?php

namespace App\Livewire\Admin;

use App\Actions\Audit\ListAuditLogs;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\User;
use App\Services\Tenancy\CurrentCompany;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
use Livewire\Component;
use Livewire\WithPagination;

class AuditLogsPage extends Component
{
    use WithPagination;

    public string $action = '';
    public ?int $actorUserId = null;
    public string $auditableType = '';
    public string $auditableId = '';
    public string $dateFrom = '';
    public string $dateTo = '';
    public string $ipAddress = '';
    public string $search = '';
    public ?int $expandedLogId = null;
    public int $perPage = 20;

    public function mount(): void
    {
        $this->ensurePermission('settings.manage');
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['action', 'actorUserId', 'auditableType', 'auditableId', 'dateFrom', 'dateTo', 'ipAddress', 'search'], true)) {
            $this->resetPage();
        }
    }

    public function toggleLog(int $logId): void
    {
        $this->expandedLogId = $this->expandedLogId === $logId
            ? null
            : $logId;
    }

    public function logs(ListAuditLogs $listAuditLogs): LengthAwarePaginator
    {
        return $listAuditLogs->paginate($this->currentCompany(), [
            'action' => $this->action,
            'actor_user_id' => $this->actorUserId,
            'auditable_type' => $this->auditableType,
            'auditable_id' => $this->auditableId,
            'date_from' => $this->dateFrom,
            'date_to' => $this->dateTo,
            'ip_address' => $this->ipAddress,
            'search' => $this->search,
        ], $this->perPage, $this->getPage());
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

    public function actionLabel(?string $action): string
    {
        if (! $action) {
            return 'Sin accion';
        }

        return match ($action) {
            'sale.created' => 'Venta creada',
            'sale.payment_registered' => 'Pago de venta registrado',
            'sale.cancelled' => 'Venta anulada',
            'sale.returned' => 'Venta devuelta',
            'sale.draft_updated' => 'Borrador de venta actualizado',
            'credit.payment_registered' => 'Abono a credito registrado',
            'purchase.created' => 'Compra creada',
            'purchase.payment_registered' => 'Pago de compra registrado',
            'purchase.supplier_credit_applied' => 'Saldo a favor aplicado a compra',
            'purchase.returned_from_inventory' => 'Compra devuelta desde inventario',
            'inventory.adjustment.created' => 'Ajuste de inventario creado',
            'inventory.transfer.created' => 'Traslado de inventario creado',
            'cash_session.opened' => 'Sesion de caja abierta',
            'cash_session.closed' => 'Sesion de caja cerrada',
            'cash_session_expense.created' => 'Gasto de caja registrado',
            'cash_session_fund.created' => 'Fondo de caja registrado',
            'loyalty.points_expired' => 'Puntos de fidelizacion expirados',
            'loyalty.manual_adjustment' => 'Ajuste manual de puntos',
            'promotion.created' => 'Promocion creada',
            'promotion.updated' => 'Promocion actualizada',
            'branch.created' => 'Sucursal creada',
            'warehouse.created' => 'Bodega creada',
            'cash_register.created' => 'Caja creada',
            'branch.auto_deactivated_on_plan_change' => 'Sucursal desactivada por cambio de plan',
            'warehouse.auto_deactivated_on_plan_change' => 'Bodega desactivada por cambio de plan',
            'cash_register.auto_deactivated_on_plan_change' => 'Caja desactivada por cambio de plan',
            'company_setting.created' => 'Configuracion creada',
            'company_setting.updated' => 'Configuracion actualizada',
            'company_module_override.created' => 'Excepcion de modulo creada',
            'company_module_override.updated' => 'Excepcion de modulo actualizada',
            'company_feature_override.created' => 'Excepcion de funcion creada',
            'company_feature_override.updated' => 'Excepcion de funcion actualizada',
            'company_limit_override.created' => 'Excepcion de limite creada',
            'company_limit_override.updated' => 'Excepcion de limite actualizada',
            'subscription.created' => 'Suscripcion creada',
            'subscription.ended' => 'Suscripcion finalizada',
            'payment_attachment.uploaded' => 'Comprobante de pago subido',
            'payment_attachment.archived' => 'Comprobante de pago archivado',
            'equipment.requested' => 'Equipo solicitado',
            'equipment.added' => 'Equipo agregado',
            'equipment.fulfilled' => 'Entrega de equipo completada',
            'equipment.return_requested' => 'Devolucion de equipo solicitada',
            'equipment.returned' => 'Equipo devuelto',
            'equipment.replaced' => 'Equipo reemplazado',
            'equipment.replacement_created' => 'Reemplazo de equipo creado',
            default => ucfirst(str_replace(['_', '.'], [' ', ' - '], $action)),
        };
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
            'App\\Models\\CashSessionExpense' => 'Gasto de caja',
            'App\\Models\\CashSessionFund' => 'Fondo de caja',
            'App\\Models\\InventoryAdjustment' => 'Ajuste de inventario',
            'App\\Models\\InventoryTransfer' => 'Traslado',
            'App\\Models\\FrozenSale' => 'Venta congelada',
            'App\\Models\\Promotion' => 'Promocion',
            'App\\Models\\EquipmentRental' => 'Alquiler de equipo',
            'App\\Models\\Company' => 'Empresa',
            'App\\Models\\CompanySetting' => 'Configuracion de empresa',
            'App\\Models\\Subscription' => 'Suscripcion',
            'App\\Models\\PaymentAttachment' => 'Comprobante de pago',
            'App\\Models\\Branch' => 'Sucursal',
            'App\\Models\\Warehouse' => 'Bodega',
            'App\\Models\\CashRegister' => 'Caja',
            'App\\Models\\LoyaltyAccount' => 'Cuenta de fidelizacion',
            'App\\Models\\CompanyModuleOverride' => 'Excepcion de modulo',
            'App\\Models\\CompanyFeatureOverride' => 'Excepcion de funcion',
            'App\\Models\\CompanyLimitOverride' => 'Excepcion de limite',
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
                'description' => 'Consulta acciones criticas por empresa, filtra eventos y revisa capturas antes/despues sin salir del panel administrativo.',
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
