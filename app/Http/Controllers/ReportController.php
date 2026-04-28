<?php

namespace App\Http\Controllers;

use App\Http\Requests\Report\StoreReportRequest;
use App\Http\Resources\ReportResource;
use App\Jobs\DispatchBatchReportEmailsJob;
use App\Jobs\GenerateReportJob;
use App\Jobs\SendReportEmailJob;
use App\Models\Employee;
use App\Models\Report;
use App\Services\LicenseService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

class ReportController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = min((int) $request->integer('per_page', 15), 100);

        $query = Report::query()
            ->with('employee', 'generatedBy')
            ->orderByDesc('created_at');

        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $reports = $query->paginate($perPage)->withQueryString();

        return ReportResource::collection($reports);
    }

    public function store(StoreReportRequest $request, LicenseService $license): JsonResponse
    {
        $license->authorize('run_report', 1, true, 'report_'.Str::uuid());

        $data = $request->validated();
        $data['generated_by'] = $request->user()->id;
        $data['status'] = 'pending';

        if (in_array($data['type'], ['general', 'tardanzas', 'incompletas', 'informe_total', 'horas_laborales'])) {
            $data['employee_id'] = null;
        }

        $report = Report::create($data);

        GenerateReportJob::dispatch($report);

        $report->load('employee', 'generatedBy');

        return (new ReportResource($report))
            ->response()
            ->setStatusCode(202);
    }

    public function show(Report $report): ReportResource
    {
        $report->load('employee', 'generatedBy');

        return new ReportResource($report);
    }

    public function download(Report $report): Response
    {
        abort_unless($report->type === 'individual' && $report->status === 'completed', 422, 'El reporte debe ser de tipo individual y estar completado.');

        $report->load('employee');

        $pdf = Pdf::loadView('reports.individual', [
            'report'  => $report,
            'summary' => $report->summary,
            'rows'    => $report->rows,
        ]);

        $filename = 'reporte_' . str_replace(' ', '_', strtolower($report->employee->full_name)) . '_' . $report->date_from->format('Y-m-d') . '.pdf';

        return $pdf->download($filename);
    }

    public function sendBatchEmails(Request $request, Report $report): JsonResponse
    {
        abort_unless($report->type === 'general' && $report->status === 'completed', 422, 'El reporte debe ser de tipo general y estar completado.');

        $totalEmployees = Employee::query()
            ->where('is_active', true)
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->count();

        abort_if($totalEmployees === 0, 422, 'No hay empleados activos con correo registrado.');

        DispatchBatchReportEmailsJob::dispatch(
            $report->date_from->toDateString(),
            $report->date_to->toDateString(),
            $request->user()->id,
        );

        $hoursNeeded = ceil($totalEmployees / 15);

        return response()->json([
            'message'         => "Se programó el envío para {$totalEmployees} empleados a razón de 15 por hora.",
            'total_employees' => $totalEmployees,
            'estimated_hours' => $hoursNeeded,
        ]);
    }

    public function sendEmail(Report $report): JsonResponse
    {
        abort_unless($report->type === 'individual' && $report->status === 'completed', 422, 'El reporte debe ser de tipo individual y estar completado.');

        $report->load('employee');

        abort_unless($report->employee && $report->employee->email, 422, 'El empleado no tiene correo electrónico registrado.');

        SendReportEmailJob::dispatch($report);

        return response()->json(['message' => 'El reporte será enviado al correo del empleado en breve.']);
    }

    public function destroy(Report $report): JsonResponse
    {
        $report->delete();

        return response()->json(['message' => 'Reporte eliminado correctamente.']);
    }
}
