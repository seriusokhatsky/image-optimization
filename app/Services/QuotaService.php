<?php

namespace App\Services;

use App\Models\LicenseQuota;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class QuotaService
{
    private ?string $apiUrl;
    private ?string $apiKey;

    public function __construct()
    {
        $this->apiUrl = config('services.xtemos.api_url');
        $this->apiKey = config('services.xtemos.api_key');
    }

    public function getUserQuota(string $token): array
    {
        // If API is not configured, return a default quota
        if (!$this->apiUrl || !$this->apiKey) {
            return [
                'valid' => true,
                'quota_mb' => 1000, // Default 1GB quota
                'subscription_type' => 'default',
            ];
        }
        
        $cacheKey = "user_quota_{$token}";
        
        $cachedQuota = Cache::get($cacheKey);
        if ($cachedQuota) {
            return $cachedQuota;
        }

        try {
            $response = Http::timeout(10)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type' => 'application/json',
                ])
                ->post($this->apiUrl . '/user-quota', [
                    'token' => $token,
                ]);

            if ($response->successful()) {
                $data = $response->json();
                
                $quotaInfo = [
                    'valid' => $data['valid'] ?? false,
                    'quota_mb' => $data['quota_mb'] ?? 0,
                    'subscription_type' => $data['subscription_type'] ?? 'basic',
                    'expires_at' => $data['expires_at'] ?? null,
                ];

                Cache::put($cacheKey, $quotaInfo, 300);
                
                return $quotaInfo;
            }

            Log::warning('Quota API request failed', [
                'token' => $token,
                'status' => $response->status(),
                'response' => $response->body(),
            ]);

            return ['valid' => false, 'quota_mb' => 0];

        } catch (\Exception $e) {
            Log::error('Quota API error', [
                'token' => $token,
                'error' => $e->getMessage(),
            ]);

            $localQuota = LicenseQuota::where('token', $token)->first();
            if ($localQuota && $localQuota->current_quota_mb > 0) {
                Log::info('Using cached quota due to API unavailability', [
                    'token' => $token,
                    'cached_quota' => $localQuota->current_quota_mb,
                ]);
                
                return [
                    'valid' => true,
                    'quota_mb' => $localQuota->current_quota_mb,
                    'subscription_type' => 'cached',
                ];
            }

            return ['valid' => false, 'quota_mb' => 0];
        }
    }
} 