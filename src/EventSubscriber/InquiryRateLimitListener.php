<?php
namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsEventListener(event: 'kernel.request', priority: 20)]
class InquiryRateLimitListener
{
    public function __construct(
        #[Autowire(service: 'limiter.inquiry_public_minute')]
        private RateLimiterFactory $minuteLimiter,
        #[Autowire(service: 'limiter.inquiry_public_hour')]
        private RateLimiterFactory $hourLimiter,
    ) {}

    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $path = $request->getPathInfo();

        // Only guard the public submission endpoint
        if ($path !== '/api/public/inquiries' || $request->getMethod() !== 'POST') {
            return;
        }

        $ip = $request->getClientIp() ?? 'unknown';

        $minuteLimit = $this->minuteLimiter->create('ip:' . $ip)->consume(1);
        if (!$minuteLimit->isAccepted()) {
            throw new TooManyRequestsHttpException(
                $minuteLimit->getRetryAfter()->getTimestamp() - time(),
                'Too many submissions. Please try again in a minute.'
            );
        }

        $hourLimit = $this->hourLimiter->create('ip:' . $ip)->consume(1);
        if (!$hourLimit->isAccepted()) {
            throw new TooManyRequestsHttpException(
                $hourLimit->getRetryAfter()->getTimestamp() - time(),
                'Too many submissions from this address. Please try again later.'
            );
        }
    }
}