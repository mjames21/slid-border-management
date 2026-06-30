<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\MobileLoginRequest;
use App\Models\MobileDevice;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\CountryBrandingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;

class MobileAuthController extends Controller
{
    public function login(MobileLoginRequest $request, AuditLogger $audit, CountryBrandingService $branding): JsonResponse
    {
        $credentials = $request->validated();
        $user = User::query()->with(['borderPost.country', 'country'])->where('email', $credentials['email'])->first();

        if (!$user || !Hash::check($credentials['password'], $user->password)) {
            $audit->record('mobile.login_failed', metadata: ['email' => $credentials['email']], request: $request);
            return response()->json(['message' => 'Invalid credentials.'], 401);
        }

        if (!$user->canUseMobileApp()) {
            $audit->record('mobile.login_blocked', $user, $user->borderPost, metadata: ['reason' => 'inactive_or_unassigned'], request: $request);
            return response()->json(['message' => 'This user is not active for mobile border reporting.'], 403);
        }

        $device = MobileDevice::query()->where('device_id', $credentials['device_name'])->first();

        if ($device && $device->user_id !== $user->id) {
            $audit->record('mobile.login_blocked', $user, $user->borderPost, $device, ['reason' => 'device_assigned_to_another_user'], $request);
            return response()->json(['message' => 'This mobile device is assigned to another user.'], 403);
        }

        $device ??= new MobileDevice(['device_id' => $credentials['device_name']]);
        $device->forceFill([
            'user_id' => $user->id,
            'border_post_id' => $user->border_post_id,
            'country_code' => $user->operationalCountryCode(),
            'name' => $credentials['device_name'],
            'last_seen_at' => now(),
        ])->save();

        if ($device->isBlocked()) {
            $audit->record('mobile.login_blocked', $user, $user->borderPost, $device, ['reason' => 'device_blocked'], $request);
            return response()->json(['message' => 'This mobile device is blocked.'], 403);
        }

        // A device login refreshes its session, so replace older tokens for the same device.
        $user->tokens()->where('name', $credentials['device_name'])->delete();

        // Issue the narrowest token the mobile app needs instead of a full-access token.
        $token = $user->createToken($credentials['device_name'], ['mobile:read', 'mobile:sync'])->plainTextToken;
        $audit->record('mobile.login_success', $user, $user->borderPost, $device, request: $request);

        return response()->json([
            'token' => $token,
            'token_type' => 'Bearer',
            'offline_login_allowed' => true,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
            'assignment' => $this->assignmentPayload($user),
            'branding' => $branding->payload($user->borderPost?->country ?: $user->country),
        ]);
    }

    public function me(CountryBrandingService $branding): JsonResponse
    {
        $user = request()->user()?->load(['borderPost.country', 'country']);

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
            'assignment' => $this->assignmentPayload($user),
            'branding' => $branding->payload($user->borderPost?->country ?: $user->country),
        ]);
    }

    public function logout(AuditLogger $audit): JsonResponse
    {
        $user = request()->user()?->load('borderPost');
        $audit->record('mobile.logout', $user, $user?->borderPost, request: request());
        $user?->currentAccessToken()?->delete();

        return response()->json(['message' => 'Logged out successfully.']);
    }

    private function assignmentPayload(User $user): ?array
    {
        if (!$user->borderPost) {
            return null;
        }

        return [
            'role' => $user->role,
            'borderPost' => [
                'id' => $user->borderPost->id,
                'countryCode' => $user->borderPost->country_code,
                'code' => $user->borderPost->code,
                'digitalAddress' => $user->borderPost->digital_address,
                'name' => $user->borderPost->name,
                'region' => $user->borderPost->region,
                // Coordinates let the offline app understand the assigned border point.
                'latitude' => $user->borderPost->latitude !== null ? (float) $user->borderPost->latitude : null,
                'longitude' => $user->borderPost->longitude !== null ? (float) $user->borderPost->longitude : null,
                'allowedRadiusMeters' => $user->borderPost->allowed_radius_meters,
            ],
        ];
    }
}
