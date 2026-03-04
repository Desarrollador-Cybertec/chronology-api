<?php

namespace App\Http\Controllers;

use App\Http\Requests\Employee\UpdateEmployeeRequest;
use App\Http\Resources\EmployeeResource;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class EmployeeController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = min((int) $request->integer('per_page', 15), 100);

        $employees = Employee::query()
            ->with('shiftAssignments.shift')
            ->orderBy('last_name')
            ->paginate($perPage)
            ->withQueryString();

        return EmployeeResource::collection($employees);
    }

    public function show(Employee $employee): EmployeeResource
    {
        $employee->load('shiftAssignments.shift');

        return new EmployeeResource($employee);
    }

    public function update(UpdateEmployeeRequest $request, Employee $employee): EmployeeResource
    {
        $employee->update($request->validated());

        return new EmployeeResource($employee);
    }

    public function toggleActive(Employee $employee): JsonResponse
    {
        $employee->update(['is_active' => ! $employee->is_active]);

        $status = $employee->is_active ? 'activado' : 'desactivado';

        return response()->json([
            'message' => "Empleado {$status} correctamente.",
            'is_active' => $employee->is_active,
        ]);
    }
}
