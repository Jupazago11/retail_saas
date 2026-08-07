<?php

namespace Tests\Feature;

use App\Actions\Companies\CreateCompany;
use App\Actions\Customers\CreateCustomer;
use App\Actions\Customers\EnableCustomerCredit;
use App\Models\User;
use App\Services\Settings\CompanySettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class EnableCustomerCreditTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_enables_credit_for_a_customer_without_an_account(): void
    {
        $company = $this->creditCompanyFixture();
        $customer = app(CreateCustomer::class)->handle($company, [
            'first_name' => 'Nato',
        ]);

        $this->assertFalse($customer->credit_enabled);
        $this->assertNull($customer->creditAccount);

        $updated = app(EnableCustomerCredit::class)->handle($company, $customer, '150000');

        $this->assertTrue($updated->credit_enabled);
        $this->assertNotNull($updated->creditAccount);
        $this->assertSame('150000.00', $updated->creditAccount->credit_limit);
        $this->assertSame('150000.00', $updated->creditAccount->available_credit);
        $this->assertSame('0.00', $updated->creditAccount->balance_due);
    }

    public function test_it_rejects_enabling_credit_twice(): void
    {
        $company = $this->creditCompanyFixture();
        $customer = app(CreateCustomer::class)->handle($company, [
            'first_name' => 'Nato',
            'credit_enabled' => true,
            'credit_limit' => '50000',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Este cliente ya tiene una cuenta de credito.');

        app(EnableCustomerCredit::class)->handle($company, $customer, '150000');
    }

    public function test_it_rejects_customer_from_another_company(): void
    {
        $companyA = $this->creditCompanyFixture();
        $companyB = $this->creditCompanyFixture();
        $customer = app(CreateCustomer::class)->handle($companyB, [
            'first_name' => 'Nato',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('El cliente no pertenece a la empresa indicada.');

        app(EnableCustomerCredit::class)->handle($companyA, $customer, '150000');
    }

    protected function creditCompanyFixture(): \App\Models\Company
    {
        $owner = User::factory()->create();
        $company = app(CreateCompany::class)->handle($owner, [
            'legal_name' => 'Credito Habilitacion SAS '.uniqid(),
        ]);
        app(CompanySettings::class)->set($company, 'credit', 'credit_enabled', true);

        return $company;
    }
}
