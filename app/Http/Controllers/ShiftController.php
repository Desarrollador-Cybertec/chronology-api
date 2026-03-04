<?php

namespace App\Http\Controllers;

use App\Http\Requests\Shift\StoreShiftRequest;
use App\Http\Requests\Shift\UpdateShiftRequest;
use App\Http\Resources\ShiftResource;
use App\Models\Shift;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ShiftController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = min((int) $request->integer('per_page', 15), 100);

        $shifts = Shift::query()
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();

        return ShiftResource::collection($shifts);
    }

    public function store(StoreShiftRequest $request): JsonResponse
    {
        $shift = Shift::create($request->validated());

        return (new ShiftResource($shift))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Shift $shift): ShiftResource
    {
        return new ShiftResource($shift);
    }

    public function update(UpdateShiftRequest $request, Shift $shift): ShiftResource
    {
        $shift->update($request->validated());

        return new ShiftResource($shift);
    }

    public function destroy(Shift $shift): JsonResponse
    {
        $shift->delete();

        return response()->json(['message' => 'Turno eliminado correctamente.']);
    }
}
