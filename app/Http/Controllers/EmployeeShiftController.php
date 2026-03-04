<?php

namespace App\Http\Controllers;

use App\Http\Requests\EmployeeShift\StoreEmployeeShiftRequest;
use App\Http\Requests\EmployeeShift\UpdateEmployeeShiftRequest;
use App\Http\Resources\EmployeeShiftAssignmentResource;
use App\Models\Employee;
use App\Models\EmployeeShiftAssignment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class EmployeeShiftController extends Controller
{
    public function index(Employee $employee): AnonymousResourceCollection
    {
        $assignments = $employee->shiftAssignments()
            ->with('shift')
            ->orderByDesc('effective_date')
            ->paginate(20);

        return EmployeeShiftAssignmentResource::collection($assignments);
    }

    public function store(StoreEmployeeShiftRequest $request): JsonResponse
    {
        $assignment = EmployeeShiftAssignment::create($request->validated());
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
}
