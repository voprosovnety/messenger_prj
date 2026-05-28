<?php

namespace App\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\KernelInterface;

final class ExceptionListener implements EventSubscriberInterface
{
    public function __construct(private readonly KernelInterface $kernel)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => ['onKernelException', -1],
        ];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $request = $event->getRequest();
        $accept  = $request->headers->get('Accept', '');
        $uri     = $request->getRequestUri();

        // Only handle requests expecting JSON or hitting /api paths.
        if (!str_contains($accept, 'application/json') && !str_starts_with($uri, '/api')) {
            return;
        }

        $throwable = $event->getThrowable();
        $isDev     = $this->kernel->isDebug() || $this->kernel->getEnvironment() === 'dev';

        if ($throwable instanceof HttpExceptionInterface) {
            $status  = $throwable->getStatusCode();
            $message = $isDev
                ? $throwable->getMessage()
                : $throwable->getMessage(); // HttpException messages are safe to show in any env
        } else {
            $status  = 500;
            $message = $isDev
                ? sprintf('%s: %s', $throwable::class, $throwable->getMessage())
                : 'An unexpected error occurred';
        }

        $event->setResponse(new JsonResponse(['error' => $message], $status));
        $event->stopPropagation();
    }
}
