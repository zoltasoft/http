<?php

declare(strict_types=1);

namespace Zolta\Http\Exceptions\Laravel;

use Illuminate\Auth\Access\AuthorizationException as FrameworkAuthorizationException;
use Illuminate\Auth\AuthenticationException as FrameworkAuthenticationException;
use Psr\Log\LoggerInterface;
use Throwable;
use Zolta\Exceptions\Contracts\RenderableExceptionInterface;
use Zolta\Exceptions\Rest\ForbiddenException;
use Zolta\Exceptions\Rest\InternalServerErrorException;
use Zolta\Exceptions\Rest\UnauthorizedException;

class ExceptionMapper
{
    public function __construct(private readonly LoggerInterface $logger) {}

    /**
     * Map any Throwable to a RenderableExceptionInterface instance.
     *
     * This method is responsible for logging (PSR-3) and for preserving the
     * original throwable chain so logs will contain full stack traces for host app and packages.
     */
    public function map(Throwable $throwable): RenderableExceptionInterface
    {
        // If the exception already implements the renderable contract,
        // infrastructure will decide whether/how to log it.
        if ($throwable instanceof RenderableExceptionInterface) {
            if ($throwable->status() >= 500) {
                $this->logger->error(sprintf('[%s] %s', $throwable->type(), $throwable->getMessage()), [
                    'context' => $throwable->context(),
                    'exception' => $throwable,
                ]);
            }

            return $throwable;
        }

        // Normalize common framework auth exceptions before treating them as unexpected
        if ($throwable instanceof FrameworkAuthenticationException) {
            return new UnauthorizedException(
                previous: $throwable,
                errorCode: 'AUTH_UNAUTHORIZED',
                context: [
                    'public' => [
                        'code' => 'auth.unauthorized',
                        'hint' => 'Authentication is required for this endpoint.',
                    ],
                    'debug' => [
                        'guards' => $throwable->guards(),
                    ],
                ],
                status: 401
            );
        }

        if ($throwable instanceof FrameworkAuthorizationException) {
            return new ForbiddenException(
                previous: $throwable,
                errorCode: 'AUTH_FORBIDDEN',
                context: [
                    'public' => [
                        'code' => 'auth.forbidden',
                        'hint' => 'You do not have permission to perform this action.',
                    ],
                    'debug' => [
                        'guards' => method_exists($throwable, 'guards') ? $throwable->guards() : null,
                        'message' => $throwable->getMessage(),
                    ],
                ],
                status: 403
            );
        }

        // Unexpected throwable -> log full details and wrap for rendering
        $this->logger->error('Unexpected throwable: '.$throwable->getMessage(), [
            'exception' => $throwable,
        ]);

        return new InternalServerErrorException($throwable);
    }
}
