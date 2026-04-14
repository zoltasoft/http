<?php

declare(strict_types=1);

namespace Zolta\Http\Symfony\Exceptions;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final readonly class ExceptionSubscriber implements EventSubscriberInterface
{
    public function __construct(private ExceptionMapper $exceptionMapper) {}

    public static function getSubscribedEvents(): array
    {
        return [
            // High priority so we short-circuit the default HTML error handling.
            KernelEvents::EXCEPTION => ['onKernelException', 255],
        ];
    }

    public function onKernelException(ExceptionEvent $exceptionEvent): void
    {
        $throwable = $exceptionEvent->getThrowable();
        $renderableException = $this->exceptionMapper->map($throwable);
        $payload = $renderableException->toErrorArray();

        $message = 'Error';
        if (isset($payload['message']) && is_string($payload['message'])) {
            $message = $payload['message'];
        } elseif (isset($payload[0]) && is_array($payload[0]) && isset($payload[0]['message'])) {
            $message = $payload[0]['message'];
        }
        $context = $payload['context'] ?? $payload[0] ?? $payload;

        // Normalize validation-style payloads (context[errors]) to the same shape Laravel returns.
        $errors = $context;
        if (is_array($context) && array_key_exists('errors', $context) && is_array($context['errors'])) {
            $errors = $context['errors'];
        }

        // Hide debug details only when APP_DEBUG is falsy; otherwise surface them.
        $debugValue = $_SERVER['APP_DEBUG'] ?? getenv('APP_DEBUG');
        $isDebug = filter_var($debugValue ?: false, FILTER_VALIDATE_BOOL);
        $debug = null;
        if (is_array($errors) && array_key_exists('debug', $errors)) {
            $debug = $errors['debug'];
        }
        if (! $isDebug && is_array($errors)) {
            unset($errors['debug']);
        }

        // Flatten "public" wrapper to match Laravel shape.
        if (is_array($errors) && array_key_exists('public', $errors) && is_array($errors['public'])) {
            $flattened = $errors['public'];
            if ($isDebug && $debug !== null) {
                $flattened['debug'] = $debug;
            }
            $errors = $flattened;
        }

        $jsonResponse = new JsonResponse([
            'success' => false,
            'message' => $message,
            'errors' => $errors,
        ], $renderableException->status());

        $exceptionEvent->setResponse($jsonResponse);
        $exceptionEvent->stopPropagation();
    }
}
