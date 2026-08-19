<?php
// src/DataFixtures/SettingsFixtures.php

namespace App\DataFixtures;

use App\Entity\Settings;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class SettingsFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        // First Order Discount Settings
        $firstOrderDiscount = new Settings();
        $firstOrderDiscount->setSettingKey('first_order_discount.enabled');
        $firstOrderDiscount->setValue('1');
        $firstOrderDiscount->updateTimestamps(); // Set timestamp
        $manager->persist($firstOrderDiscount);
        
        $firstOrderPercentage = new Settings();
        $firstOrderPercentage->setSettingKey('first_order_discount.percentage');
        $firstOrderPercentage->setValue('10.0');
        $firstOrderPercentage->updateTimestamps(); // Set timestamp
        $manager->persist($firstOrderPercentage);
        
        $manager->flush();
    }
}