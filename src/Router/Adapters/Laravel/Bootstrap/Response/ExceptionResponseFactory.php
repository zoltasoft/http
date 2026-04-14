<?php

declare(strict_types=1);

namespace Zolta\Http\Router\Laravel\Bootstrap\Response;

use Throwable;
use Zolta\Exceptions\Contracts\RenderableExceptionInterface;
use Zolta\Http\Response\HttpResponse;
use Zolta\Http\Response\ResponsePayload;

/**
 * Wraps exceptions into a consistent ResponsePayload for HTTP responses.
 */
final class ExceptionResponseFactory
{
    public function fromException(Throwable $throwable): mixed
    {
        if ($throwable instanceof RenderableExceptionInterface) {
            $context = $throwable->context();
            $message = $throwable->getMessage();
            $status = $throwable->status();

            return HttpResponse::fromPayload(
                new ResponsePayload(
                    success: false,
                    message: $message,
                    data: [],
                    errors: isset($context['public'])
                        ? ['public' => $context['public']]
                        : ['public' => ['code' => 'server.error', 'message' => $message]],
                    debug: config('app.debug')
                        ? array_merge(
                            ['exception_class' => $throwable::class],
                            $context['debug'] ?? [],
                        )
                        : []
                ),
                $status
            );
        }

        return HttpResponse::fromPayload(
            new ResponsePayload(
                success: false,
                message: 'Internal server error',
                data: [],
                errors: [
                    'public' => [
                        'code' => 'server.error',
                        'message' => config('app.debug')
                            ? $throwable->getMessage()
                            : 'Unexpected error',
                    ],
                ],
                debug: config('app.debug')
                    ? [
                        'exception_class' => $throwable::class,
                        'trace' => $throwable->getTraceAsString(),
                    ]
                    : []
            ),
            500
        );
    }
}
