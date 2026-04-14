<?php

declare(strict_types=1);

namespace Zolta\Http\Response\Laravel\Api;

use Illuminate\Http\JsonResponse;
use Zolta\Http\Response\ResponsePayload;

final class ApiJsonResponder
{
    public function respond(ResponsePayload $responsePayload, int $status): JsonResponse
    {
        return response()->json([
            'success' => $responsePayload->success,
            'message' => $responsePayload->message,
            'data' => $this->unwrapResources($responsePayload->data),
            'errors' => $responsePayload->errors ?: [],
            'debug' => config('app.debug') ? ($responsePayload->debug ?: []) : [],
        ], $status);
    }

    private function unwrapResources(array $data): array
    {
        if (! isset($data['response']) || ! is_array($data['response'])) {
            return $data;
        }

        $inner = $data['response'];

        if ($this->isSingleRootAssoc($inner)) {
            return [array_key_first($inner) => current($inner)];
        }

        if ($this->isAssociative($inner)) {
            return array_merge([], $inner);
        }

        return $inner;
    }

    private function isAssociative(array $array): bool
    {
        return array_keys($array) !== range(0, count($array) - 1);
    }

    private function isSingleRootAssoc(array $array): bool
    {
        return $this->isAssociative($array) && count($array) === 1;
    }
}
