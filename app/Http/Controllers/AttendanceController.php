<?php

namespace App\Http\Controllers;

use App\Http\Requests\Attendance\UpdateAttendanceDayRequest;
use App\Http\Resources\AttendanceDayResource;
use App\Models\AttendanceDay;
use App\Models\AttendanceEdit;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AttendanceController extends Controller
{
    private const SORTABLE_COLUMNS = [
        'date_reference',
        'first_check_in',
        'last_check_out',
        'worked_minutes',
        'late_minutes',
        'overtime_minutes',
        'early_departure_minutes',
        'status',
    ];

    /**
     * List attendance days with filters and pagination.
     *
     * Filters: employee_id, date, date_from, date_to, status, has_overtime, has_late
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = min($request->integer('per_page', 15), 100);

        $query = AttendanceDay::query()
            ->with(['employee', 'shift']);

        if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->input('employee_id'));
        }

        if ($request->filled('date')) {
            $query->whereDate('date_reference', $request->input('date'));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('date_reference', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('date_reference', '<=', $request->input('date_to'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->boolean('has_overtime')) {
            $query->where('overtime_minutes', '>', 0);
        }

        if ($request->boolean('has_late')) {
            $query->where('late_minutes', '>', 0);
        }

        $this->applySorting($query, $request);

        $results = $query
            ->paginate($perPage)
            ->withQueryString();

        return AttendanceDayResource::collection($results);
    }

    /**
     * Show a single attendance day with edit history.
     */
    public function show(AttendanceDay $attendanceDay): AttendanceDayResource
    {
        $attendanceDay->load(['employee', 'shift', 'edits.editedBy']);

        return new AttendanceDayResource($attendanceDay);
    }

    /**
     * List attendance days for a specific employee.
     */
    public function byEmployee(Request $request, Employee $employee): AnonymousResourceCollection
    {
        $perPage = min($request->integer('per_page', 15), 100);

        $query = AttendanceDay::query()
            ->with(['shift'])
            ->where('employee_id', $employee->id);

        if ($request->filled('date_from')) {
            $query->whereDate('date_reference', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('date_reference', '<=', $request->input('date_to'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $this->applySorting($query, $request);

        $results = $query
            ->paginate($perPage)
            ->withQueryString();

        return AttendanceDayResource::collection($results);
    }

    /**
     * List attendance days for a specific date.
     */
    public function byDate(Request $request, string $date): AnonymousResourceCollection
    {
        $perPage = min($request->integer('per_page', 15), 100);

        $query = AttendanceDay::query()
            ->with(['employee', 'shift'])
            ->whereDate('date_reference', $date);

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $this->applySorting($query, $request);

        $results = $query
            ->paginate($perPage)
            ->withQueryString();

        return AttendanceDayResource::collection($results);
    }

    /**
     * Manually edit an attendance day. Logs each field change in attendance_edits.
     */
    public function update(UpdateAttendanceDayRequest $request, AttendanceDay $attendanceDay): JsonResponse
    {
        $validated = $request->validated();
        $reason = $validated['reason'];
        unset($validated['reason']);

        $edits = [];

        foreach ($validated as $field => $newValue) {
            $oldValue = $attendanceDay->getAttribute($field);

            $oldForComparison = $oldValue instanceof \DateTimeInterface
                ? $oldValue->format('Y-m-d H:i:s')
                : (string) $oldValue;

            if ($oldForComparison === (string) $newValue) {
                continue;
            }

            $edits[] = AttendanceEdit::create([
                'attendance_day_id' => $attendanceDay->id,
                'edited_by' => $request->user()->id,
                'field_changed' => $field,
                'old_value' => $oldForComparison,
                'new_value' => (string) $newValue,
                'reason' => $reason,
            ]);
        }

        if (count($edits) > 0) {
            $attendanceDay->update(array_merge($validated, [
                'is_manually_edited' => true,
            ]));
        }

        $attendanceDay->load(['employee', 'shift', 'edits.editedBy']);

        return response()->json([
            'data' => new AttendanceDayResource($attendanceDay),
            'edits_created' => count($edits),
        ]);
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<AttendanceDay>  $query
     */
    private function applySorting($query, Request $request): void
    {
        $sortBy = $request->input('sort_by', 'date_reference');
        $direction = strtolower($request->input('order', 'desc')) === 'asc' ? 'asc' : 'desc';

        if ($sortBy === 'employee') {
            $query->orderBy(
                Employee::select('last_name')
                    ->whereColumn('employees.id', 'attendance_days.employee_id')
                    ->limit(1),
                $direction
            );
        } elseif (in_array($sortBy, self::SORTABLE_COLUMNS, true)) {
            $query->orderBy($sortBy, $direction);
        } else {
            $query->orderBy('date_reference', $direction);
        }

        if ($sortBy !== 'date_reference') {
            $query->orderByDesc('date_reference');
        }
    }
}
