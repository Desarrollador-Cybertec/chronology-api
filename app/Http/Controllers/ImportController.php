<?php

namespace App\Http\Controllers;

use App\Actions\Attendance\ReprocessBatchAction;
use App\Actions\Import\ImportCsvAction;
use App\Http\Requests\Import\StoreImportRequest;
use App\Http\Resources\ImportBatchResource;
use App\Models\ImportBatch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ImportController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = min((int) $request->integer('per_page', 15), 100);

        $batches = ImportBatch::query()
            ->with('uploadedBy')
            ->orderBy('id')
            ->paginate($perPage)
            ->withQueryString();

        return ImportBatchResource::collection($batches);
    }

    public function store(StoreImportRequest $request, ImportCsvAction $action): JsonResponse
    {
        $result = $action->execute(
            $request->file('file'),
            $request->user(),
        );

        $status = $result['success'] ? 201 : 422;

        $response = [
            'data' => new ImportBatchResource($result['batch']),
        ];

        if (! $result['success']) {
            $response['errors'] = $result['errors'];
        }

        return response()->json($response, $status);
    }

    public function show(ImportBatch $importBatch): ImportBatchResource
    {
        $importBatch->load('uploadedBy');

        return new ImportBatchResource($importBatch);
    }

    public function reprocess(ImportBatch $importBatch, ReprocessBatchAction $action): JsonResponse
    {
        $result = $action->execute($importBatch);

        return response()->json([
            'message' => 'Batch reprocessing started.',
            'deleted_attendance_days' => $result['deleted'],
            'groups_to_process' => $result['groups'],
        ]);
    }
}
