<?php

namespace Tests\Feature;

use App\Actions\Companies\CreateCompany;
use App\Actions\Suppliers\CreateSupplier;
use App\Enums\RecordStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class SuppliersTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_supplier_and_underlying_person_record(): void
    {
        $owner = User::factory()->create();
        $company = app(CreateCompany::class)->handle($owner, [
            'legal_name' => 'Proveedores Retail SAS',
        ]);

        $supplier = app(CreateSupplier::class)->handle($company, [
            'document_type' => 'NIT',
            'document_number' => '900123456',
            'first_name' => 'Comercializadora',
            'last_name' => 'Central',
            'phone' => '3001234567',
            'email' => 'proveedor@example.com',
            'payment_term_days' => 30,
            'notes' => 'Proveedor principal',
        ]);

        $this->assertSame($company->id, $supplier->company_id);
        $this->assertSame(30, $supplier->payment_term_days);
        $this->assertSame('Comercializadora', $supplier->person->first_name);
        $this->assertSame('Central', $supplier->person->last_name);

        $this->assertDatabaseHas('suppliers', [
            'id' => $supplier->id,
            'company_id' => $company->id,
            'status' => RecordStatus::Active->value,
            'payment_term_days' => 30,
        ]);
        $this->assertDatabaseHas('people', [
            'id' => $supplier->person_id,
            'document_number' => '900123456',
            'email' => 'proveedor@example.com',
        ]);
    }

    public function test_it_rejects_negative_payment_term_days(): void
    {
        $owner = User::factory()->create();
        $company = app(CreateCompany::class)->handle($owner, [
            'legal_name' => 'Validaciones Proveedor SAS',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('El plazo del proveedor no puede ser negativo.');

        app(CreateSupplier::class)->handle($company, [
            'first_name' => 'Proveedor',
            'payment_term_days' => -5,
        ]);
    }
}
