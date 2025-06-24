<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LicenseQuota extends Model
{
    protected $fillable = [
        'token',
        'used_kb',
        'current_quota_kb',
        'last_used_at',
        'last_quota_check',
    ];

    protected $casts = [
        'last_used_at' => 'datetime',
        'last_quota_check' => 'datetime',
    ];

    public function getRemainingQuotaKbAttribute(): int
    {
        return max(0, $this->current_quota_kb - $this->used_kb);
    }

    public function getRemainingQuotaMbAttribute(): float
    {
        return round($this->remaining_quota_kb / 1024, 2);
    }

    public function getCurrentQuotaMbAttribute(): float
    {
        return round($this->current_quota_kb / 1024, 2);
    }

    public function getUsedMbAttribute(): float
    {
        return round($this->used_kb / 1024, 2);
    }

    public function hasQuotaAvailable(int $sizeKb): bool
    {
        return $this->remaining_quota_kb >= $sizeKb;
    }

    public function addUsage(int $sizeKb): void
    {
        $this->increment('used_kb', $sizeKb);
        $this->update(['last_used_at' => now()]);
    }

    public function updateQuota(int $quotaMb): void
    {
        $quotaKb = $quotaMb * 1024; // Convert MB to KB
        $this->update([
            'current_quota_kb' => $quotaKb,
            'last_quota_check' => now(),
        ]);
    }

    public static function getOrCreate(string $token): self
    {
        return self::firstOrCreate(
            ['token' => $token],
            [
                'used_kb' => 0,
                'current_quota_kb' => 0, // Will be fetched from API
            ]
        );
    }
}
