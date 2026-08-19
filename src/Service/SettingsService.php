<?php

// src/Service/SettingsService.php
namespace App\Service;

use App\Repository\SettingsRepository;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class SettingsService
{
    public function __construct(
        private readonly SettingsRepository $settingsRepository,
        private readonly CacheInterface $cache
    ) {
    }

    /**
     * Get a setting value with caching
     */
    public function get(string $key, ?string $default = null): ?string
    {
        $cacheKey = 'setting_' . md5($key);
        
        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($key) {
            $item->expiresAfter(10); // Cache for 1 hour
            
            return $this->settingsRepository->getValue($key);
        }) ?? $default;
    }

    /**
     * Get multiple settings
     */
    public function getMultiple(array $keys): array
    {
        $result = [];
        foreach ($keys as $key) {
            $result[$key] = $this->get($key);
        }
        return $result;
    }

    /**
     * Get all settings (cached)
     */
    public function getAll(): array
    {
        return $this->cache->get('all_settings', function (ItemInterface $item) {
            $item->expiresAfter(10);
            
            return $this->settingsRepository->getAll();
        });
    }
    
    /**
     * Get boolean setting
     */
    public function getBool(string $key, bool $default = false): bool
    {
        $value = $this->get($key);
        return filter_var($value, FILTER_VALIDATE_BOOLEAN) ?? $default;
    }
    
    /**
     * Get integer setting
     */
    public function getInt(string $key, int $default = 0): int
    {
        $value = $this->get($key);
        return $value !== null ? (int)$value : $default;
    }
}