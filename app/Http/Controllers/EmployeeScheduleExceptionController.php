<?php

namespace App\Http\Controllers;

use App\Http\Requests\ScheduleException\BatchScheduleExceptionRequest;
use App\Http\Requests\ScheduleException\StoreScheduleExceptionRequest;
use App\Http\Resources\EmployeeScheduleExceptionResource;
use App\Models\Employee;
use App\Models\EmployeeScheduleException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class EmployeeScheduleExceptionController extends Controller
{
    public function index(Employee $employee, Request $request): AnonymousResourceCollection
    {
        $perPage = min($request->integer('per_page', 15), 100);

        $exceptions = $employee->scheduleExceptions()
            ->with('shift')
            ->orderBy('id')
            ->paginate($perPage)
            ->withQueryString();

        return EmployeeScheduleExceptionResource::collection($exceptions);
    }

    public function store(StoreScheduleExceptionRequest $request): JsonResponse
    {
        $exception = EmployeeScheduleException::query()
            ->where('employee_id', $request->validated('employee_id'))
            ->whereDate('date', $request->validated('date'))
            ->first();

        if ($exception) {
            $exception->update($request->validated());
        } else {
            $exception = EmployeeScheduleException::create($request->validated());
        }

        $exception->load('shift', 'employee');

        return (new EmployeeScheduleExceptionResource($exception))
            ->response()
            ->setStatusCode(201);
    }

    public function show(EmployeeScheduleException $scheduleException): EmployeeScheduleExceptionResource
    {
        $scheduleException->load('shift', 'employee');

        return new EmployeeScheduleExceptionResource($scheduleException);
    }

    public function destroy(EmployeeScheduleException $scheduleException): JsonResponse
    {
        $scheduleException->delete();

        return response()->json(['message' => 'Excepción de horario eliminada correctamente.']);
    }

    public function batch(BatchScheduleExceptionRequest $request): JsonResponse
    {
        $created = [];

        foreach ($request->validated('exceptions') as $data) {
            $exception = EmployeeScheduleException::query()
                ->where('employee_id', $data['employee_id'])
                ->whereDate('date', $data['date'])
                ->first();

            if ($exception) {
                $exception->update($data);
                $created[] = $exception;
            } else {
                $created[] = EmployeeScheduleException::create($data);
            }
        }

        $exceptions = collect($created)->each(fn ($e) => $e->load('shift', 'employee'));

        return response()->json([
            'message' => 'Excepciones de horario procesadas correctamente.',
            'count' => count($created),
            'data' => EmployeeScheduleExceptionResource::collection($exceptions),
        ], 201);
    }
}
