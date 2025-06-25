<?php

namespace App\Http\Middleware;

use App\Models\LicenseQuota;
use App\Services\QuotaService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class QuotaValidationMiddleware
{
    public function __construct(
        private QuotaService $quotaService
    ) {}

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->header('X-Token') ?? $request->input('token');

        if (!$token) {
            return response()->json([
                'success' => false,
                'error' => 'Token required',
                'code' => 'TOKEN_REQUIRED'
            ], 401);
        }

        // Check if quota record exists for this token
        $quota = LicenseQuota::where('token', $token)->first();
        
        if (!$quota) {
            // First request for this token - fetch quota from API
            $quotaInfo = $this->quotaService->getUserQuota($token);
            
            if (!$quotaInfo['valid']) {
                return response()->json([
                    'success' => false,
                    'error' => 'Invalid token or quota service unavailable',
                    'code' => 'TOKEN_INVALID'
                ], 403);
            }
            
            // Create quota record with fetched quota
            $quota = LicenseQuota::create([
                'token' => $token,
                'used_kb' => 0,
                'current_quota_kb' => $quotaInfo['quota_mb'] * 1024,
                'last_quota_check' => now(),
            ]);
        }

        // Validate quota availability for operations that consume quota
        if ($quota->current_quota_kb <= 0) {
            return response()->json([
                'success' => false,
                'error' => 'No quota available. Please refresh your quota or check your subscription.',
                'code' => 'QUOTA_UNAVAILABLE'
            ], 403);
        }

        if ($request->hasFile('file')) {
            $fileSizeKb = ceil($request->file('file')->getSize() / 1024);
            
            if (!$quota->hasQuotaAvailable($fileSizeKb)) {
                return response()->json([
                    'success' => false,
                    'error' => 'Quota exceeded',
                    'quota_info' => [
                        'limit_mb' => $quota->current_quota_mb,
                        'used_mb' => $quota->used_mb,
                        'remaining_mb' => $quota->remaining_quota_mb,
                        'requested_mb' => round($fileSizeKb / 1024, 2),
                    ],
                    'code' => 'QUOTA_EXCEEDED'
                ], 429);
            }
        }

        $request->merge([
            'token' => $token,
            'quota' => $quota
        ]);

        return $next($request);
    }
}
