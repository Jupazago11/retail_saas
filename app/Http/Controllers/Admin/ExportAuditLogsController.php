<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Audit\ListAuditLogs;
use App\Http\Controllers\Controller;
use App\Services\Tenancy\CurrentCompany;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportAuditLogsController extends Controller
{
    public function __invoke(Request $request, ListAuditLogs $listAuditLogs, CurrentCompany $currentCompany): StreamedResponse
    {
        abort_unless(auth()->user()?->hasCurrentCompanyPermission('settings.manage'), 403);

        $company = $currentCompany->company()
            ?? abort(404, 'No hay una empresa activa seleccionada.');

        $logs = $listAuditLogs->handle($company, [
            'action' => $request->query('action'),
            'actor_user_id' => $request->query('actor_user_id'),
            'auditable_type' => $request->query('auditable_type'),
            'auditable_id' => $request->query('auditable_id'),
            'date_from' => $request->query('date_from'),
            'date_to' => $request->query('date_to'),
        ]);

        return response()->streamDownload(function () use ($logs) {
            $stream = fopen('php://output', 'wb');
            fputcsv($stream, ['ID', 'Accion', 'Entidad', 'Entidad ID', 'Actor', 'Usuario', 'IP', 'Fecha']);

            foreach ($logs as $log) {
                fputcsv($stream, [
                    $log->id,
                    $log->action,
                    $log->auditable_type,
                    $log->auditable_id,
                    $log->actor?->name ?? 'Sistema',
                    $log->actor?->username ?? '',
                    $log->ip_address ?? '',
                    optional($log->created_at)->format('Y-m-d H:i:s'),
                ]);
            }

            fclose($stream);
        }, 'audit-logs.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
