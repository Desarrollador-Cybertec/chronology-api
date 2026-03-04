<?php

namespace App\Services;

use App\Models\Employee;

class EmployeeResolverService
{
    /**
     * Find an employee by internal_id, or create one if not found.
     *
     * @param  array{external_employee_id: string, full_name: string, department: string|null}  $normalizedRow
     */
    public function resolve(array $normalizedRow): Employee
    {
        [$firstName, $lastName] = $this->splitFullName(
            $normalizedRow['full_name'],
            $normalizedRow['external_employee_id'],
        );

        return Employee::firstOrCreate(
            ['internal_id' => $normalizedRow['external_employee_id']],
            [
                'first_name' => $firstName,
                'last_name' => $lastName,
                'department' => $normalizedRow['department'],
                'is_active' => true,
            ]
        );
    }

    /**
     * Split a full name string into first name and last name.
     * The biometric device exports names in ALL CAPS as: LAST1 LAST2 FIRST1 FIRST2...
     * We store the first word as first_name and the rest as last_name.
     *
     * @return array{string, string}
     */
    private function splitFullName(string $fullName, string $fallback): array
    {
        $fullName = trim($fullName);

        if ($fullName === '' || $fullName === '-') {
            return [$fallback, ''];
        }

        $parts = explode(' ', $fullName, 2);

        return [
            $parts[0],
            $parts[1] ?? '',
        ];
    }
}
