<?php

namespace App\Http\Controllers\Api\Vendor;

use App\Http\Controllers\Controller;
use App\Models\VendorEmployee;
use App\Models\VendorShift;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EmployeeController extends Controller
{
    /**
     * List vendor employees.
     */
    public function index(): JsonResponse
    {
        $vendor = Auth::guard('vendor')->user();

        $employees = $vendor->employees()
            ->select('id', 'name', 'phone', 'email', 'role', 'status', 'permissions', 'avatar_url')
            ->orderBy('name')
            ->get();

        return response()->json(['employees' => $employees]);
    }

    /**
     * Create a new employee.
     */
    public function store(Request $request): JsonResponse
    {
        $vendor = Auth::guard('vendor')->user();

        $data = $request->validate([
            'name'        => 'required|string|max:255',
            'phone'       => 'nullable|string|max:50',
            'email'       => 'nullable|email|max:255',
            'pin'         => 'required|string|min:4|max:6',
            'role'        => 'required|in:admin,operator,viewer',
            'permissions' => 'nullable|array',
        ]);

        // Check PIN uniqueness within vendor
        $exists = $vendor->employees()->where('pin', $data['pin'])->exists();
        if ($exists) {
            return response()->json(['message' => 'PIN-ul este deja folosit de alt angajat.'], 422);
        }

        $employee = $vendor->employees()->create(array_merge($data, [
            'tenant_id' => $vendor->tenant_id,
            'status'    => 'active',
        ]));

        return response()->json(['employee' => $employee], 201);
    }

    /**
     * Update an employee.
     */
    public function update(Request $request, int $employeeId): JsonResponse
    {
        $vendor = Auth::guard('vendor')->user();
        $employee = $vendor->employees()->findOrFail($employeeId);

        $data = $request->validate([
            'name'        => 'sometimes|string|max:255',
            'phone'       => 'nullable|string|max:50',
            'email'       => 'nullable|email|max:255',
            'pin'         => 'sometimes|string|min:4|max:6',
            'role'        => 'sometimes|in:admin,operator,viewer',
            'status'      => 'sometimes|in:active,inactive,suspended',
            'permissions' => 'nullable|array',
        ]);

        if (isset($data['pin']) && $data['pin'] !== $employee->pin) {
            $exists = $vendor->employees()
                ->where('pin', $data['pin'])
                ->where('id', '!=', $employee->id)
                ->exists();
            if ($exists) {
                return response()->json(['message' => 'PIN-ul este deja folosit de alt angajat.'], 422);
            }
        }

        $employee->update($data);

        return response()->json(['employee' => $employee]);
    }

    /**
     * Delete an employee.
     */
    public function destroy(int $employeeId): JsonResponse
    {
        $vendor = Auth::guard('vendor')->user();
        $employee = $vendor->employees()->findOrFail($employeeId);
        $employee->delete();

        return response()->json(['message' => 'Angajat sters.']);
    }

    /**
     * Authenticate employee by PIN (quick POS login).
     * Called from POS app — vendor is already authenticated via API token.
     */
    public function authenticateByPin(Request $request): JsonResponse
    {
        $vendor = Auth::guard('vendor')->user();

        $data = $request->validate([
            'pin'        => 'required|string',
            'edition_id' => 'required|integer',
            'device_uid' => 'nullable|string',
        ]);

        $employee = $vendor->employees()
            ->where('pin', $data['pin'])
            ->where('status', 'active')
            ->first();

        if (! $employee) {
            return response()->json(['message' => 'PIN invalid sau angajat inactiv.'], 401);
        }

        // Find POS device if device_uid provided
        $posDeviceId = null;
        if (! empty($data['device_uid'])) {
            $device = $vendor->posDevices()
                ->where('device_uid', $data['device_uid'])
                ->where('status', 'active')
                ->first();
            $posDeviceId = $device?->id;
        }

        // Start shift automatically
        $shift = $employee->startShift($data['edition_id'], $posDeviceId);

        return response()->json([
            'employee' => [
                'id'          => $employee->id,
                'name'        => $employee->name,
                'role'        => $employee->role,
                'permissions' => $employee->permissions,
            ],
            'shift' => [
                'id'         => $shift->id,
                'started_at' => $shift->started_at,
            ],
        ]);
    }

    /**
     * End current shift for employee.
     */
    public function endShift(Request $request, int $employeeId): JsonResponse
    {
        $vendor = Auth::guard('vendor')->user();
        $employee = $vendor->employees()->findOrFail($employeeId);

        $shift = $employee->endShift();

        if (! $shift) {
            return response()->json(['message' => 'Nicio tura activa.'], 404);
        }

        return response()->json([
            'shift' => [
                'id'               => $shift->id,
                'started_at'       => $shift->started_at,
                'ended_at'         => $shift->ended_at,
                'sales_count'      => $shift->sales_count,
                'sales_total_cents'=> $shift->sales_total_cents,
                'duration_minutes' => $shift->durationMinutes(),
            ],
        ]);
    }

    /**
     * List shifts for an edition.
     */
    public function shifts(Request $request, int $editionId): JsonResponse
    {
        $vendor = Auth::guard('vendor')->user();

        $shifts = VendorShift::where('vendor_id', $vendor->id)
            ->where('festival_edition_id', $editionId)
            ->with('employee:id,name,role')
            ->orderByDesc('started_at')
            ->paginate(50);

        return response()->json($shifts);
    }
}
