<?php

namespace App\Http\Controllers;

use App\Http\Requests\Report\StoreReportRequest;
use App\Http\Resources\ReportResource;
use App\Jobs\GenerateReportJob;
use App\Models\Report;
use App\Services\LicenseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
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

    public function destroy(Report $report): JsonResponse
    {
        $report->delete();

        return response()->json(['message' => 'Reporte eliminado correctamente.']);
    }
}
