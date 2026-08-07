<?php

namespace App\Livewire\Platform;

use App\Actions\Plans\UpdatePlan;
use App\Livewire\Concerns\InteractsWithToast;
use App\Models\Feature;
use App\Models\Module;
use App\Models\Plan;
use App\Support\Plans\PlanLimitCatalog;
use Illuminate\Contracts\View\View;
use InvalidArgumentException;
use Livewire\Component;

class PlansPage extends Component
{
    use InteractsWithToast;

    public bool   $showModal      = false;
    public ?int   $editingId      = null;
    public string $editName       = '';
    public string $editPrice      = '';
    public string $editPeriod     = 'monthly';
    public string $editStatus     = 'active';
    public array  $editModuleIds  = [];
    public array  $editFeatureIds = [];
    public array  $editLimits     = [];

    public function mount(): void
    {
        abort_unless(auth()->user()?->is_platform_admin, 403);
    }

    public function startEdit(int $id): void
    {
        $plan = Plan::with(['modules', 'features', 'limits'])->findOrFail($id);

        $this->editingId  = $plan->id;
        $this->editName   = $plan->name;
        $this->editPrice  = (string) $plan->base_price;
        $this->editPeriod = $plan->billing_period;
        $this->editStatus = $plan->status;

        $this->editModuleIds = $plan->modules->where('pivot.enabled', true)->pluck('id')->all();
        $this->editFeatureIds = $plan->features->where('pivot.enabled', true)->pluck('id')->all();

        $limitValues = $plan->limits->pluck('limit_value', 'limit_key');
        $this->editLimits = collect(PlanLimitCatalog::keys())
            ->mapWithKeys(fn (string $key) => [$key => (string) ($limitValues[$key] ?? 0)])
            ->all();

        $this->showModal = true;
    }

    public function toggleModule(int $moduleId): void
    {
        if (in_array($moduleId, $this->editModuleIds, true)) {
            $this->editModuleIds = array_values(array_diff($this->editModuleIds, [$moduleId]));

            $ownedFeatureIds = Feature::query()->where('module_id', $moduleId)->pluck('id')->all();
            $this->editFeatureIds = array_values(array_diff($this->editFeatureIds, $ownedFeatureIds));

            return;
        }

        $this->editModuleIds[] = $moduleId;
    }

    public function toggleFeature(int $featureId): void
    {
        $feature = Feature::query()->find($featureId);

        if (! $feature || ! in_array($feature->module_id, $this->editModuleIds, true)) {
            return;
        }

        if (in_array($featureId, $this->editFeatureIds, true)) {
            $this->editFeatureIds = array_values(array_diff($this->editFeatureIds, [$featureId]));

            return;
        }

        $this->editFeatureIds[] = $featureId;
    }

    public function saveEdit(UpdatePlan $updatePlan): void
    {
        abort_unless(auth()->user()?->is_platform_admin, 403);

        $this->validate([
            'editName'    => ['required', 'string', 'max:255'],
            'editPrice'   => ['required', 'numeric', 'min:0'],
            'editPeriod'  => ['required', 'in:monthly,yearly,one_time'],
            'editStatus'  => ['required', 'in:active,inactive'],
            'editLimits.*' => ['required', 'integer', 'min:0'],
        ]);

        try {
            $updatePlan->handle(Plan::findOrFail($this->editingId), [
                'name' => $this->editName,
                'base_price' => $this->editPrice,
                'billing_period' => $this->editPeriod,
                'status' => $this->editStatus,
                'module_ids' => $this->editModuleIds,
                'feature_ids' => $this->editFeatureIds,
                'limits' => $this->editLimits,
            ]);
        } catch (InvalidArgumentException $exception) {
            $this->addError('editName', $exception->getMessage());

            return;
        }

        $this->closeModal();
        $this->toast('Plan actualizado.');
    }

    public function closeModal(): void
    {
        $this->showModal      = false;
        $this->editingId      = null;
        $this->editName       = '';
        $this->editPrice      = '';
        $this->editPeriod     = 'monthly';
        $this->editStatus     = 'active';
        $this->editModuleIds  = [];
        $this->editFeatureIds = [];
        $this->editLimits     = [];
    }

    public function render(): View
    {
        $plans = Plan::with(['modules', 'limits'])->orderBy('id')->get();
        $modules = Module::with('features')->orderBy('code')->get();
        $limitDefinitions = PlanLimitCatalog::definitions();

        return view('livewire.platform.plans-page', compact('plans', 'modules', 'limitDefinitions'))
            ->layout('layouts.platform');
    }
}
