<?php

namespace App\Http\Controllers;

use App\Actions\Import\ImportCsvAction;
use App\Http\Requests\Import\StoreImportRequest;
use App\Http\Resources\ImportBatchResource;
use App\Models\ImportBatch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ImportController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $batches = ImportBatch::query()
            ->with('uploadedBy')
            ->orderByDesc('created_at')
            ->paginate(20);

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
}
