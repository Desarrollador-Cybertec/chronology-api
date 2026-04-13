<?php

namespace App\Http\Controllers;

use App\Http\Requests\EmployeeShift\DestroyAllEmployeeShiftsRequest;
use App\Http\Requests\EmployeeShift\StoreEmployeeShiftRequest;
use App\Http\Requests\EmployeeShift\UpdateEmployeeShiftRequest;
use App\Http\Resources\EmployeeShiftAssignmentResource;
use App\Models\Employee;
use App\Models\EmployeeShiftAssignment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class EmployeeShiftController extends Controller
{
    public function index(Employee $employee, Request $request): AnonymousResourceCollection
    {
        $perPage = min((int) $request->integer('per_page', 15), 100);

        $assignments = $employee->shiftAssignments()
            ->with('shift')
            ->orderByDesc('effective_date')
            ->paginate($perPage)
            ->withQueryString();

        return EmployeeShiftAssignmentResource::collection($assignments);
    }

    public function store(StoreEmployeeShiftRequest $request): JsonResponse
    {
        $data = $request->validated();

        $assignment = EmployeeShiftAssignment::create($data);
        $assignment->load('shift', 'employee');

        return (new EmployeeShiftAssignmentResource($assignment))
            ->response()
            ->setStatusCode(201);
    }

    public function show(EmployeeShiftAssignment $employeeShift): EmployeeShiftAssignmentResource
    {
        $employeeShift->load('shift', 'employee');

        return new EmployeeShiftAssignmentResource($employeeShift);
    }

    public function update(UpdateEmployeeShiftRequest $request, EmployeeShiftAssignment $employeeShift): EmployeeShiftAssignmentResource
    {
        $employeeShift->update($request->validated());
        $employeeShift->load('shift', 'employee');

        return new EmployeeShiftAssignmentResource($employeeShift);
    }

    public function destroy(EmployeeShiftAssignment $employeeShift): JsonResponse
    {
        $employeeShift->delete();

        return response()->json(['message' => 'Asignación eliminada correctamente.']);
    }

    public function destroyAll(DestroyAllEmployeeShiftsRequest $request): JsonResponse
    {
        $query = EmployeeShiftAssignment::query();

        if ($request->filled('employee_ids')) {
            $query->whereIn('employee_id', $request->validated('employee_ids'));
        }

        $count = $query->count();
        $query->delete();

        return response()->json([
            'message' => "Se eliminaron {$count} asignaciones de turno.",
            'deleted_count' => $count,
        ]);
    }
}
