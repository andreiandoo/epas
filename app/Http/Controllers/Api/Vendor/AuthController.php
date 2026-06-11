<?php

namespace App\Http\Controllers\Api\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Vendor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email'     => 'required|email',
            'password'  => 'required|string',
            'tenant_id' => 'required|integer',
        ]);

        $vendor = Vendor::where('email', $request->email)
            ->where('tenant_id', $request->tenant_id)
            ->first();

        if (!$vendor || !Hash::check($request->password, $vendor->password)) {
            return response()->json(['message' => 'Invalid credentials.'], 401);
        }

        if (!$vendor->isActive()) {
            return response()->json(['message' => 'Account is suspended.'], 403);
        }

        // Generate API token for stateless auth
        $plainToken = $vendor->generateApiToken();

        return response()->json([
            'vendor' => [
                'id'             => $vendor->id,
                'name'           => $vendor->name,
                'email'          => $vendor->email,
                'company_name'   => $vendor->company_name,
                'contact_person' => $vendor->contact_person,
                'logo_url'       => $vendor->logo_url,
            ],
            'token' => $plainToken,
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $vendor = Auth::guard('vendor')->user();

        return response()->json([
            'vendor' => [
                'id'             => $vendor->id,
                'name'           => $vendor->name,
                'email'          => $vendor->email,
                'company_name'   => $vendor->company_name,
                'contact_person' => $vendor->contact_person,
                'phone'          => $vendor->phone,
                'logo_url'       => $vendor->logo_url,
                'tenant_id'      => $vendor->tenant_id,
            ],
            'editions' => $vendor->editions()->with('edition:id,name,slug,year,status')->get()->map(fn ($ve) => [
                'edition'         => $ve->edition,
                'location'        => $ve->location,
                'vendor_type'     => $ve->vendor_type,
                'commission_rate' => $ve->commission_rate,
                'status'          => $ve->status,
            ]),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $vendor = Auth::guard('vendor')->user();
        $vendor->update(['api_token' => null]);

        return response()->json(['message' => 'Logged out.']);
    }
}
