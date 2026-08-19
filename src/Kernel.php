<?php

namespace App;

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;
    public function boot(): void
    {
        parent::boot();
        #date_default_timezone_set($this->getContainer()->getParameter('timezone'));
        $timezone = $_ENV['APP_TIMEZONE'] ?? 'Asia/Manila';
        date_default_timezone_set($timezone);
    }
}
