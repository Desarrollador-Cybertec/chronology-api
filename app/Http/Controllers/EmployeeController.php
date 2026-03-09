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

        $query = Employee::query()
            ->with('shiftAssignments.shift');

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('internal_id', 'like', "%{$search}%")
                    ->orWhere('department', 'like', "%{$search}%");
            });
        }

        $employees = $query
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate($perPage)
            ->withQueryString();

        return EmployeeResource::collection($employees);
    }

    public function show(Employee $employee): EmployeeResource
    {
        $employee->load('shiftAssignments.shift');

        $employee->loadCount([
            'attendanceDays as total_days_worked' => function ($query) {
                $query->where('status', 'present');
            },
            'attendanceDays as total_days_absent' => function ($query) {
                $query->where('status', 'absent');
            },
            'attendanceDays as total_days_incomplete' => function ($query) {
                $query->where('status', 'incomplete');
            },
        ]);

        $employee->loadSum('attendanceDays as total_worked_minutes', 'worked_minutes');
        $employee->loadSum('attendanceDays as total_overtime_minutes', 'overtime_minutes');
        $employee->loadSum('attendanceDays as total_overtime_diurnal_minutes', 'overtime_diurnal_minutes');
        $employee->loadSum('attendanceDays as total_overtime_nocturnal_minutes', 'overtime_nocturnal_minutes');
        $employee->loadSum('attendanceDays as total_late_minutes', 'late_minutes');
        $employee->loadSum('attendanceDays as total_early_departure_minutes', 'early_departure_minutes');

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
