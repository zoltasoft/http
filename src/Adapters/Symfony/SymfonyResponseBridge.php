<?php

declare(strict_types=1);

namespace Zolta\Http\Symfony;

use Symfony\Component\HttpFoundation\JsonResponse;
use Zolta\Http\Response\Contracts\ResponseBridge;
use Zolta\Http\Response\ResponsePayload;

final class SymfonyResponseBridge implements ResponseBridge
{
    public function respond(
        ResponsePayload $responsePayload,
        int $status = 200
    ): JsonResponse {
        return new JsonResponse([
            'success' => $responsePayload->success,
            'message' => $responsePayload->message,
            'data' => $responsePayload->data,
            'errors' => $responsePayload->errors,
            'debug' => $responsePayload->debug,
        ], $status);
    }
}
