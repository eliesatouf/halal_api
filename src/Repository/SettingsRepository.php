<?php
// src/Repository/SettingsRepository.php

namespace App\Repository;

use App\Entity\Settings;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Contracts\Cache\CacheInterface;

class SettingsRepository extends ServiceEntityRepository
{
    public function __construct(
        ManagerRegistry $registry,
        private CacheInterface $settingsCache
    ) {
        parent::__construct($registry, Settings::class);
    }

    /**
     * Get a setting value with caching
     */
    public function getValue(string $key, mixed $default = null): mixed
    {
        error_log('SettingsRepository::getValue called for key: ' . $key . ' at ' . microtime(true));
        
        $cacheKey = 'setting_' . $key;
        $cached = $this->settingsCache->get($cacheKey, function($item) use ($key, $default) {
            error_log('Cache MISS for key: ' . $key . ' at ' . microtime(true));
            $item->expiresAfter(0);
            
            $setting = $this->findOneBy(['settingKey' => $key]);
            $value = $setting ? $setting->getValue() : $default;
            error_log('Database query completed for key: ' . $key . ' at ' . microtime(true));
            return $value;
        });
        
        error_log('SettingsRepository::getValue returning for key: ' . $key . ' at ' . microtime(true));
        return $cached;
    }

    /**
     * Get all settings as key-value array with caching
     */
    public function getAllValues(): array
    {
        return $this->settingsCache->get('all_settings', function($item) {
            $item->expiresAfter(0); // 1 hour
            
            $settings = $this->findAll();
            $values = [];
            
            foreach ($settings as $setting) {
                $values[$setting->getSettingKey()] = $setting->getValue();
            }
            
            return $values;
        });
    }

    /**
     * Set a setting value and invalidate cache
     */
    public function setValue(string $key, string $value): void
    {
        $setting = $this->findOneBy(['settingKey' => $key]);
        
        if (!$setting) {
            $setting = new Settings();
            $setting->setSettingKey($key);
        }
        
        $setting->setValue($value);
        
        $this->_em->persist($setting);
        $this->_em->flush();
        
        // Invalidate cache
        $this->settingsCache->delete('setting_' . $key);
        $this->settingsCache->delete('all_settings');
    }

    /**
     * Delete a setting and invalidate cache
     */
    public function delete(string $key): void
    {
        $setting = $this->findOneBy(['settingKey' => $key]);
        
        if ($setting) {
            $this->_em->remove($setting);
            $this->_em->flush();
            
            // Invalidate cache
            $this->settingsCache->delete('setting_' . $key);
            $this->settingsCache->delete('all_settings');
        }
    }

    /**
     * Clear all settings cache
     */
    public function clearCache(): void
    {
        $this->settingsCache->clear();
    }
}