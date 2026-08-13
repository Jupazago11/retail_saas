<?php

namespace App\Livewire\Admin;

use App\Actions\Plans\ApplyCompanyFeatureOverride;
use App\Actions\Plans\ApplyCompanyLimitOverride;
use App\Actions\Plans\ApplyCompanyModuleOverride;
use App\Enums\RecordStatus;
use App\Livewire\Concerns\InteractsWithToast;
use App\Models\Company;
use App\Models\CompanyFeatureOverride;
use App\Models\CompanyLimitOverride;
use App\Models\CompanyModuleOverride;
use App\Models\Feature;
use App\Models\Module;
use App\Models\PlanLimit;
use App\Services\Plans\CompanyPlanResolver;
use App\Services\Tenancy\CurrentCompany;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use Livewire\Component;

class PlanOverridesPage extends Component
{
    use InteractsWithToast;

    public ?int $editingModuleOverrideId = null;
    public string $moduleId = '';
    public bool $moduleEnabled = true;
    public string $moduleStartsAt = '';
    public string $moduleEndsAt = '';

    public ?int $editingFeatureOverrideId = null;
    public string $featureId = '';
    public bool $featureEnabled = true;
    public string $featureStartsAt = '';
    public string $featureEndsAt = '';

    public ?int $editingLimitOverrideId = null;
    public string $limitKey = '';
    public string $limitValue = '';
    public string $limitStartsAt = '';
    public string $limitEndsAt = '';

    public function mount(): void
    {
        $this->ensurePermission('settings.manage');
        $this->resetModuleForm();
        $this->resetFeatureForm();
        $this->resetLimitForm();
    }

    public function saveModuleOverride(ApplyCompanyModuleOverride $applyCompanyModuleOverride): void
    {
        $this->ensurePermission('settings.manage');

        $validated = $this->validate([
            'moduleId' => ['required', 'integer', Rule::exists('modules', 'id')->where(fn ($query) => $query->where('status', RecordStatus::Active->value))],
            'moduleEnabled' => ['required', 'boolean'],
            'moduleStartsAt' => ['nullable', 'date'],
            'moduleEndsAt' => ['nullable', 'date', 'after_or_equal:moduleStartsAt'],
        ]);

        try {
            $applyCompanyModuleOverride->handle(
                $this->currentCompany(),
                $this->editingModuleOverrideId ? CompanyModuleOverride::query()->findOrFail($this->editingModuleOverrideId) : null,
                [
                    'module_id' => (int) $validated['moduleId'],
                    'enabled' => (bool) $validated['moduleEnabled'],
                    'starts_at' => $this->blankToNull($validated['moduleStartsAt'] ?? null),
                    'ends_at' => $this->blankToNull($validated['moduleEndsAt'] ?? null),
                ],
                auth()->user(),
            );
        } catch (InvalidArgumentException $exception) {
            $this->addError('moduleId', $exception->getMessage());

            return;
        }

        $this->resetModuleForm();
        $this->toast('Override de modulo guardado correctamente.');
    }

    public function saveFeatureOverride(ApplyCompanyFeatureOverride $applyCompanyFeatureOverride): void
    {
        $this->ensurePermission('settings.manage');

        $validated = $this->validate([
            'featureId' => ['required', 'integer', Rule::exists('features', 'id')->where(fn ($query) => $query->where('status', RecordStatus::Active->value))],
            'featureEnabled' => ['required', 'boolean'],
            'featureStartsAt' => ['nullable', 'date'],
            'featureEndsAt' => ['nullable', 'date', 'after_or_equal:featureStartsAt'],
        ]);

        try {
            $applyCompanyFeatureOverride->handle(
                $this->currentCompany(),
                $this->editingFeatureOverrideId ? CompanyFeatureOverride::query()->findOrFail($this->editingFeatureOverrideId) : null,
                [
                    'feature_id' => (int) $validated['featureId'],
                    'enabled' => (bool) $validated['featureEnabled'],
                    'starts_at' => $this->blankToNull($validated['featureStartsAt'] ?? null),
                    'ends_at' => $this->blankToNull($validated['featureEndsAt'] ?? null),
                ],
                auth()->user(),
            );
        } catch (InvalidArgumentException $exception) {
            $this->addError('featureId', $exception->getMessage());

            return;
        }

        $this->resetFeatureForm();
        $this->toast('Override de feature guardado correctamente.');
    }

    public function saveLimitOverride(ApplyCompanyLimitOverride $applyCompanyLimitOverride): void
    {
        $this->ensurePermission('settings.manage');

        $validated = $this->validate([
            'limitKey' => ['required', 'string'],
            'limitValue' => ['required', 'integer', 'min:1'],
            'limitStartsAt' => ['nullable', 'date'],
            'limitEndsAt' => ['nullable', 'date', 'after_or_equal:limitStartsAt'],
        ]);

        try {
            $applyCompanyLimitOverride->handle(
                $this->currentCompany(),
                $this->editingLimitOverrideId ? CompanyLimitOverride::query()->findOrFail($this->editingLimitOverrideId) : null,
                [
                    'limit_key' => $validated['limitKey'],
                    'limit_value' => (int) $validated['limitValue'],
                    'starts_at' => $this->blankToNull($validated['limitStartsAt'] ?? null),
                    'ends_at' => $this->blankToNull($validated['limitEndsAt'] ?? null),
                ],
                auth()->user(),
            );
        } catch (InvalidArgumentException $exception) {
            $this->addError('limitKey', $exception->getMessage());

            return;
        }

        $this->resetLimitForm();
        $this->toast('Override de limite guardado correctamente.');
    }

    public function startEditingModuleOverride(int $overrideId): void
    {
        $override = CompanyModuleOverride::query()->with('module')->findOrFail($overrideId);
        $this->editingModuleOverrideId = $override->id;
        $this->moduleId = (string) $override->module_id;
        $this->moduleEnabled = (bool) $override->enabled;
        $this->moduleStartsAt = optional($override->starts_at)->format('Y-m-d\TH:i') ?? '';
        $this->moduleEndsAt = optional($override->ends_at)->format('Y-m-d\TH:i') ?? '';
        $this->resetValidation();
    }

    public function startEditingFeatureOverride(int $overrideId): void
    {
        $override = CompanyFeatureOverride::query()->with(['feature.module'])->findOrFail($overrideId);
        $this->editingFeatureOverrideId = $override->id;
        $this->featureId = (string) $override->feature_id;
        $this->featureEnabled = (bool) $override->enabled;
        $this->featureStartsAt = optional($override->starts_at)->format('Y-m-d\TH:i') ?? '';
        $this->featureEndsAt = optional($override->ends_at)->format('Y-m-d\TH:i') ?? '';
        $this->resetValidation();
    }

    public function startEditingLimitOverride(int $overrideId): void
    {
        $override = CompanyLimitOverride::query()->findOrFail($overrideId);
        $this->editingLimitOverrideId = $override->id;
        $this->limitKey = $override->limit_key;
        $this->limitValue = (string) $override->limit_value;
        $this->limitStartsAt = optional($override->starts_at)->format('Y-m-d\TH:i') ?? '';
        $this->limitEndsAt = optional($override->ends_at)->format('Y-m-d\TH:i') ?? '';
        $this->resetValidation();
    }

    public function resetModuleForm(): void
    {
        $this->editingModuleOverrideId = null;
        $this->moduleId = '';
        $this->moduleEnabled = true;
        $this->moduleStartsAt = '';
        $this->moduleEndsAt = '';
        $this->resetValidation();
    }

    public function resetFeatureForm(): void
    {
        $this->editingFeatureOverrideId = null;
        $this->featureId = '';
        $this->featureEnabled = true;
        $this->featureStartsAt = '';
        $this->featureEndsAt = '';
        $this->resetValidation();
    }

    public function resetLimitForm(): void
    {
        $this->editingLimitOverrideId = null;
        $this->limitKey = '';
        $this->limitValue = '';
        $this->limitStartsAt = '';
        $this->limitEndsAt = '';
        $this->resetValidation();
    }

    public function availableModules(): Collection
    {
        return Module::query()
            ->where('status', RecordStatus::Active->value)
            ->orderBy('name')
            ->get();
    }

    public function availableFeatures(): Collection
    {
        return Feature::query()
            ->where('status', RecordStatus::Active->value)
            ->with('module')
            ->orderBy('name')
            ->get();
    }

    public function availableLimitKeys(): array
    {
        return PlanLimit::query()
            ->distinct()
            ->orderBy('limit_key')
            ->pluck('limit_key')
            ->all();
    }

    public function moduleOverrides(): Collection
    {
        return CompanyModuleOverride::query()
            ->where('company_id', $this->currentCompany()->id)
            ->with('module')
            ->latest('id')
            ->get();
    }

    public function featureOverrides(): Collection
    {
        return CompanyFeatureOverride::query()
            ->where('company_id', $this->currentCompany()->id)
            ->with(['feature.module'])
            ->latest('id')
            ->get();
    }

    public function limitOverrides(): Collection
    {
        return CompanyLimitOverride::query()
            ->where('company_id', $this->currentCompany()->id)
            ->latest('id')
            ->get();
    }

    public function effectiveSnapshot(): array
    {
        return app(CompanyPlanResolver::class)->snapshot($this->currentCompany());
    }

    public function render(): View
    {
        return view('livewire.admin.plan-overrides-page', [
            'availableModules' => $this->availableModules(),
            'availableFeatures' => $this->availableFeatures(),
            'availableLimitKeys' => $this->availableLimitKeys(),
            'moduleOverrides' => $this->moduleOverrides(),
            'featureOverrides' => $this->featureOverrides(),
            'limitOverrides' => $this->limitOverrides(),
            'snapshot' => $this->effectiveSnapshot(),
        ])->layout('layouts.app', [
            'header' => view('components.page-title', [
                'title' => 'Excepciones',
                'description' => 'Administra excepciones manuales por empresa sobre modulos, funciones y limites con vigencia controlada.',
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

    protected function blankToNull(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
