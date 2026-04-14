<?php

declare(strict_types=1);

namespace Zolta\Http\Symfony\Exceptions;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Exception\AuthenticationException as SymfonyAuthenticationException;
use Throwable;
use Zolta\Exceptions\Contracts\RenderableExceptionInterface;
use Zolta\Exceptions\Rest\ForbiddenException;
use Zolta\Exceptions\Rest\InternalServerErrorException;
use Zolta\Exceptions\Rest\UnauthorizedException;

class ExceptionMapper
{
    public function __construct(private readonly ?LoggerInterface $logger = null) {}

    /**
     * Map any Throwable to a RenderableExceptionInterface instance.
     */
    public function map(Throwable $throwable): RenderableExceptionInterface
    {
        $logger = $this->logger ?? new NullLogger;

        if ($throwable instanceof RenderableExceptionInterface) {
            if ($throwable->status() >= 500) {
                $logger->error(sprintf('[%s] %s', $throwable->type(), $throwable->getMessage()), [
                    'context' => $throwable->context(),
                    'exception' => $throwable,
                ]);
            }

            return $throwable;
        }

        if ($throwable instanceof AccessDeniedHttpException) {
            return new ForbiddenException(
                previous: $throwable,
                errorCode: 'AUTH_FORBIDDEN',
                context: [
                    'public' => [
                        'code' => 'auth.forbidden',
                        'hint' => 'You do not have permission to perform this action.',
                    ],
                    'debug' => [
                        'message' => $throwable->getMessage(),
                    ],
                ],
                status: 403
            );
        }

        if ($throwable instanceof AccessDeniedException) {
            return new ForbiddenException(
                previous: $throwable,
                errorCode: 'AUTH_FORBIDDEN',
                context: [
                    'public' => [
                        'code' => 'auth.forbidden',
                        'hint' => 'You do not have permission to perform this action.',
                    ],
                    'debug' => [
                        'attributes' => $throwable->getAttributes(),
                        'message' => $throwable->getMessage(),
                    ],
                ],
                status: 403
            );
        }

        if ($throwable instanceof SymfonyAuthenticationException) {
            return new UnauthorizedException(
                previous: $throwable,
                errorCode: 'AUTH_UNAUTHORIZED',
                context: [
                    'public' => [
                        'code' => 'auth.unauthorized',
                        'hint' => 'Authentication is required for this endpoint.',
                    ],
                    'debug' => [
                        'token' => $throwable->getToken(),
                    ],
                ],
                status: 401
            );
        }

        $logger->error('Unexpected throwable: '.$throwable->getMessage(), [
            'exception' => $throwable,
        ]);

        return new InternalServerErrorException($throwable);
    }
}
