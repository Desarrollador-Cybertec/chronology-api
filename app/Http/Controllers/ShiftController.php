<?php

namespace App\Http\Controllers;

use App\Http\Requests\Shift\StoreShiftRequest;
use App\Http\Requests\Shift\UpdateShiftRequest;
use App\Http\Resources\ShiftResource;
use App\Models\EmployeeScheduleException;
use App\Models\Shift;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class ShiftController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = min((int) $request->integer('per_page', 15), 100);

        $shifts = Shift::query()
            ->with('breaks')
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();

        return ShiftResource::collection($shifts);
    }

    public function store(StoreShiftRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $breaks = $validated['breaks'] ?? [];
        unset($validated['breaks']);

        $shift = Shift::create($validated);

        if (! empty($breaks)) {
            $shift->breaks()->createMany($breaks);
        }

        $shift->load('breaks');

        return (new ShiftResource($shift))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Shift $shift): ShiftResource
    {
        $shift->load('breaks');

        return new ShiftResource($shift);
    }

    public function update(UpdateShiftRequest $request, Shift $shift): ShiftResource
    {
        $validated = $request->validated();
        $breaks = $validated['breaks'] ?? null;
        unset($validated['breaks']);

        $shift->update($validated);

        if ($breaks !== null) {
            $shift->breaks()->delete();
            $shift->breaks()->createMany($breaks);
        }

        $shift->load('breaks');

        return new ShiftResource($shift);
    }

    public function destroy(Shift $shift): JsonResponse
    {
        DB::transaction(function () use ($shift) {
            $shift->assignments()->delete();
            EmployeeScheduleException::where('shift_id', $shift->id)->update(['shift_id' => null]);
            $shift->delete();
        });

        return response()->json(['message' => 'Turno eliminado correctamente.']);
    }
}
