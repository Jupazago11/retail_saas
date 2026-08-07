<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Services\Printing\BuildSaleTicketViewData;
use App\Services\Tenancy\CurrentCompany;
use Illuminate\Contracts\View\View;

class SaleTicketController extends Controller
{
    public function __invoke(
        Sale $sale,
        CurrentCompany $currentCompany,
        BuildSaleTicketViewData $buildSaleTicketViewData,
    ): View {
        $company = $currentCompany->company()
            ?? abort(404, 'No hay una empresa activa seleccionada.');

        abort_unless($sale->company_id === $company->id, 404);

        return view('printing.sales.ticket', $buildSaleTicketViewData->handle($company, $sale));
    }
}
