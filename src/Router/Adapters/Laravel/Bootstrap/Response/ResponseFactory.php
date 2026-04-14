<?php

declare(strict_types=1);

namespace Zolta\Http\Router\Laravel\Bootstrap\Response;

use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Zolta\Http\Response\HttpResponse;
use Zolta\Http\Response\ResponsePayload;

/**
 * Normalizes successful outcomes into framework-agnostic HttpResponse payloads.
 */
final class ResponseFactory
{
    public function success(mixed $data, int $status = 200, string $message = 'Success.'): mixed
    {
        if ($data instanceof SymfonyResponse) {
            return $data;
        }

        if ($data instanceof ResponsePayload) {
            return HttpResponse::fromPayload($data, $status);
        }

        if (is_object($data) && method_exists($data, 'toArray')) {
            $data = $data->toArray();
        } elseif (! is_array($data)) {
            $data = (array) $data;
        }

        return HttpResponse::fromPayload(
            new ResponsePayload(true, $message, $data),
            $status
        );
    }
}
