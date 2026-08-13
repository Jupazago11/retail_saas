<?php

namespace Tests\Feature;

use App\Actions\Companies\CreateCompany;
use App\Models\User;
use App\Services\Settings\CompanySettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class CompanySettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_catalog_defaults_when_setting_is_missing(): void
    {
        $owner = User::factory()->create();
        $company = app(CreateCompany::class)->handle($owner, [
            'legal_name' => 'Retail Config SAS',
        ]);

        $settings = app(CompanySettings::class);

        $this->assertTrue($settings->get($company, 'pos', 'frozen_sales_enabled'));
        $this->assertSame('weighted_average', $settings->get($company, 'inventory', 'default_cost_method'));
    }

    public function test_it_can_persist_and_read_typed_company_settings(): void
    {
        $owner = User::factory()->create();
        $company = app(CreateCompany::class)->handle($owner, [
            'legal_name' => 'Retail Tipado SAS',
        ]);

        $settings = app(CompanySettings::class);

        $settings->set($company, 'general', 'phone', '3001234567');
        $settings->set($company, 'pos', 'allow_manual_discounts', true);
        $settings->set($company, 'cash', 'default_opening_amount', '150000.50');
        $settings->set($company, 'credit', 'default_term_days', 45);

        $this->assertSame('3001234567', $settings->get($company, 'general', 'phone'));
        $this->assertTrue($settings->get($company, 'pos', 'allow_manual_discounts'));
        $this->assertSame('150000.5000', $settings->get($company, 'cash', 'default_opening_amount'));
        $this->assertSame(45, $settings->get($company, 'credit', 'default_term_days'));
    }

    public function test_it_resolves_group_settings_with_defaults_and_overrides(): void
    {
        $owner = User::factory()->create();
        $company = app(CreateCompany::class)->handle($owner, [
            'legal_name' => 'Retail Grupo SAS',
        ]);

        $settings = app(CompanySettings::class);
        $settings->set($company, 'printing', 'show_logo', false);
        $settings->set($company, 'printing', 'ticket_format', 'letter_a4');

        $group = $settings->group($company, 'printing');

        $this->assertSame('letter_a4', $group['ticket_format']);
        $this->assertFalse($group['show_logo']);
        $this->assertFalse($group['show_saas_branding']);
    }

    public function test_it_keeps_settings_isolated_per_company(): void
    {
        $ownerA = User::factory()->create();
        $ownerB = User::factory()->create();
        $companyA = app(CreateCompany::class)->handle($ownerA, [
            'legal_name' => 'Retail A SAS',
        ]);
        $companyB = app(CreateCompany::class)->handle($ownerB, [
            'legal_name' => 'Retail B SAS',
        ]);

        $settings = app(CompanySettings::class);
        $settings->set($companyA, 'credit', 'credit_enabled', true);

        $this->assertTrue($settings->get($companyA, 'credit', 'credit_enabled'));
        $this->assertFalse($settings->get($companyB, 'credit', 'credit_enabled'));
    }

    public function test_it_rejects_unknown_settings(): void
    {
        $owner = User::factory()->create();
        $company = app(CreateCompany::class)->handle($owner, [
            'legal_name' => 'Retail Invalido SAS',
        ]);

        $this->expectException(InvalidArgumentException::class);

        app(CompanySettings::class)->set($company, 'pos', 'non_existing_flag', true);
    }
}
