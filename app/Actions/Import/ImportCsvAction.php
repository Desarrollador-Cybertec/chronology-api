<?php

namespace App\Actions\Import;

use App\Models\ImportBatch;
use App\Models\User;
use App\Services\ImportService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class ImportCsvAction
{
    public function __construct(private ImportService $importService) {}

    /**
     * Handle the CSV import: create batch, store file, delegate to ImportService.
     *
     * @return array{batch: ImportBatch, success: bool, errors: array<int, string>}
     */
    public function execute(UploadedFile $file, User $user): array
    {
        $fileHash = hash_file('sha256', $file->getRealPath());

        $existingBatch = ImportBatch::where('file_hash', $fileHash)->first();
        if ($existingBatch) {
            return [
                'batch' => $existingBatch,
                'success' => false,
                'errors' => ['Este archivo ya fue importado anteriormente.'],
            ];
        }

        $storedPath = $file->store('', 'csv_imports');

        $batch = ImportBatch::create([
            'uploaded_by' => $user->id,
            'original_filename' => $file->getClientOriginalName(),
            'stored_path' => $storedPath,
            'file_hash' => $fileHash,
            'status' => 'pending',
            'total_rows' => 0,
        ]);

        $csvContent = Storage::disk('csv_imports')->get($storedPath);

        $result = $this->importService->process($batch, $csvContent);

        return [
            'batch' => $batch->fresh(),
            'success' => $result['success'],
            'errors' => $result['errors'],
        ];
    }
}
