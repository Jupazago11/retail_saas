<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Services\Printing\BuildPublicSaleReceiptViewData;
use Illuminate\Contracts\View\View;

// Ruta publica (sin login, fuera del grupo 'auth') que abre el QR del
// ticket. La firma de la URL (middleware 'signed') ES la autorizacion: no
// hay forma de armar un link valido para una venta ajena sin la clave de
// la app, asi que no hace falta ningun scoping adicional por empresa aqui.
class PublicSaleReceiptController extends Controller
{
    public function __invoke(Sale $sale, BuildPublicSaleReceiptViewData $buildPublicSaleReceiptViewData): View
    {
        return view('printing.sales.public-receipt', $buildPublicSaleReceiptViewData->handle($sale));
    }
}
