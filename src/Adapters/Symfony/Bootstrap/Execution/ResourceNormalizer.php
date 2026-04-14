<?php

declare(strict_types=1);

namespace Zolta\Http\Symfony\Bootstrap\Execution;

use Zolta\Http\Response\Contracts\ApiResponseData;

final class ResourceNormalizer
{
    public function normalize(mixed $result, ?array $responseAttr): mixed
    {
        $resourceClass = $responseAttr['arguments'][0] ?? null;

        if ($resourceClass && is_subclass_of($resourceClass, ApiResponseData::class)) {
            return (new $resourceClass($result))->toArray();
        }

        return is_object($result) && method_exists($result, 'toArray')
            ? $result->toArray()
            : $result;
    }
}
