<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\RateLimiter\RateLimiterFactory;

final class LoginRateLimiterSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private RateLimiterFactory $loginLimiterFactory,
    ) {}

    public static function getSubscribedEvents(): array
    {
        // Priority 20 — fires before Symfony's security firewall (priority 8)
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 20],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        if ($request->getPathInfo() !== '/api/auth/login' || !$request->isMethod('POST')) {
            return;
        }

        $ip = $request->getClientIp() ?? 'unknown';
        $limiter = $this->loginLimiterFactory->create('login_' . $ip);
        $limit = $limiter->consume(1);

        if (!$limit->isAccepted()) {
            $retryAfter = $limit->getRetryAfter()->getTimestamp() - time();
            throw new TooManyRequestsHttpException(
                $retryAfter > 0 ? $retryAfter : 1,
                'Too many login attempts. Please try again later.'
            );
        }
    }
}
