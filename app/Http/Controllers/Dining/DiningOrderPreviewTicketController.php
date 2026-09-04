<?php

namespace App\Http\Controllers\Dining;

use App\Http\Controllers\Controller;
use App\Models\DiningTable;
use App\Services\Printing\BuildDiningOrderPreviewTicketData;
use App\Services\Tenancy\CurrentCompany;
use Illuminate\Contracts\View\View;

class DiningOrderPreviewTicketController extends Controller
{
    public function __invoke(
        DiningTable $table,
        CurrentCompany $currentCompany,
        BuildDiningOrderPreviewTicketData $buildDiningOrderPreviewTicketData,
    ): View {
        $company = $currentCompany->company()
            ?? abort(404, 'No hay una empresa activa seleccionada.');

        abort_unless($table->company_id === $company->id, 404);
        abort_unless(
            auth()->user()?->hasAnyCurrentCompanyPermission(['dining.manage', 'dining.orders']),
            403,
            'No tienes permiso para acceder a este modulo.'
        );
        abort_unless($table->openFrozenSale(), 404, 'Esta mesa no tiene una comanda abierta.');

        return view('printing.dining.order-preview-ticket', $buildDiningOrderPreviewTicketData->handle($company, $table));
    }
}
