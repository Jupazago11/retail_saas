<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Services\Reports\OperationalReportService;
use App\Services\Tenancy\CurrentCompany;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportOperationalReportController extends Controller
{
    public function __invoke(Request $request, OperationalReportService $reportService, CurrentCompany $currentCompany): StreamedResponse
    {
        abort_unless(auth()->user()?->hasCurrentCompanyPermission('reports.view'), 403);

        $company = $currentCompany->company()
            ?? abort(404, 'No hay una empresa activa seleccionada.');

        $dataset = (string) $request->query('dataset', 'products');
        $filters = [
            'date_from' => $request->query('date_from'),
            'date_to' => $request->query('date_to'),
            'branch_id' => $request->query('branch_id'),
        ];
        $includeCosts = auth()->user()?->hasCurrentCompanyPermission('reports.view_costs') ?? false;

        [$filename, $headers, $rows] = match ($dataset) {
            'branches' => [
                'reportes-sucursales.csv',
                ['Sucursal', 'Ventas', 'Total ventas', 'Pagos', 'Total pagos'],
                $reportService->branchBreakdown($company, $filters)
                    ->map(fn (array $row) => [
                        $row['branch_name'],
                        $row['sales_count'],
                        $row['sales_total'],
                        $row['payments_count'],
                        $row['payments_total'],
                    ]),
            ],
            'payment-methods' => [
                'reportes-medios-pago.csv',
                ['Medio de pago', 'Pagos', 'Total'],
                $reportService->paymentMethodBreakdown($company, $filters)
                    ->map(fn (array $row) => [
                        $row['payment_method_label'],
                        $row['payments_count'],
                        $row['payments_total'],
                    ]),
            ],
            'credit-aging' => [
                'reportes-cartera-aging.csv',
                ['Bucket', 'Ventas abiertas', 'Saldo'],
                $reportService->creditAging($company, $filters)
                    ->map(fn (array $row) => [
                        $row['bucket_label'],
                        $row['sales_count'],
                        $row['balance_total'],
                    ]),
            ],
            default => [
                'reportes-productos.csv',
                $includeCosts
                    ? ['Producto', 'Cantidad', 'Ingresos', 'Costo', 'Margen']
                    : ['Producto', 'Cantidad', 'Ingresos'],
                $reportService->topProducts($company, $filters, $includeCosts)
                    ->map(fn (array $row) => $includeCosts
                        ? [$row['product_name'], $row['quantity_sum'], $row['revenue_sum'], $row['cost_sum'], $row['margin_sum']]
                        : [$row['product_name'], $row['quantity_sum'], $row['revenue_sum']]),
            ],
        };

        return response()->streamDownload(function () use ($headers, $rows) {
            $stream = fopen('php://output', 'wb');
            fputcsv($stream, $headers);

            foreach ($rows as $row) {
                fputcsv($stream, $row);
            }

            fclose($stream);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
