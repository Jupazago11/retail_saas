<?php

namespace Tests\Feature;

use App\Actions\Companies\CreateCompany;
use App\Enums\RecordStatus;
use App\Livewire\Admin\PrintersPage;
use App\Models\PrinterGuide;
use App\Models\User;
use App\Services\Tenancy\CurrentCompany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\InteractsWithCompanyPlans;
use Tests\TestCase;

class PrintersPageTest extends TestCase
{
    use InteractsWithCompanyPlans;
    use RefreshDatabase;

    public function test_it_only_shows_active_guides_with_a_download_link_when_a_file_is_attached(): void
    {
        $owner = User::factory()->create();
        $company = app(CreateCompany::class)->handle($owner, ['legal_name' => 'Impresoras SAS']);
        $this->assignCompanyPlan($company, 'premium');

        PrinterGuide::create([
            'title' => 'Xprinter XP-58',
            'instructions' => 'Cambiar el puerto de LPT1 a USB.',
            'status' => RecordStatus::Active->value,
        ]);
        PrinterGuide::create([
            'title' => 'Impresora descontinuada',
            'instructions' => 'Ya no aplica.',
            'status' => RecordStatus::Inactive->value,
        ]);
        PrinterGuide::create([
            'title' => 'Con instalador',
            'instructions' => 'Trae driver.',
            'disk' => 'r2',
            'path' => 'printer-guides/fake.exe',
            'original_filename' => 'driver.exe',
            'status' => RecordStatus::Active->value,
        ]);

        $this->actingAs($owner);
        session([CurrentCompany::SESSION_KEY => $company->id]);

        Livewire::test(PrintersPage::class)
            ->assertSee('Xprinter XP-58')
            ->assertSee('Con instalador')
            ->assertSee('Descargar archivo')
            ->assertDontSee('Impresora descontinuada');
    }

    public function test_route_is_forbidden_without_settings_manage_permission(): void
    {
        $owner = User::factory()->create();
        $company = app(CreateCompany::class)->handle($owner, ['legal_name' => 'Restriccion SAS']);
        $this->assignCompanyPlan($company, 'premium');
        $viewer = User::factory()->create();

        $company->users()->attach($viewer->id, [
            'company_role' => 'custom',
            'company_role_id' => $this->companyRolePreset($company, 'seller')->id,
            'status' => RecordStatus::Active->value,
            'joined_at' => now(),
        ]);

        $this->actingAs($viewer)
            ->withSession([CurrentCompany::SESSION_KEY => $company->id])
            ->get(route('admin.printers'))
            ->assertForbidden();
    }
}
