<?php

declare(strict_types=1);

namespace Zolta\Http\Symfony\Bootstrap\Execution;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Zolta\Http\Symfony\Bootstrap\Metadata\AttributeMetadata;

final readonly class ResponseFactory
{
    public function __construct(
        private ResourceNormalizer $resourceNormalizer,
        private ViewRenderer $viewRenderer
    ) {}

    public function fromServiceResult(mixed $result, AttributeMetadata $attributeMetadata): Response
    {
        $payload = $this->resourceNormalizer->normalize($result, $attributeMetadata->responseAttr);

        if ($attributeMetadata->viewAttr) {
            return new Response(
                $this->viewRenderer->render($attributeMetadata->viewAttr['arguments'][0], ['resources' => $payload])
            );
        }

        return new JsonResponse([
            'success' => true,
            'message' => 'Success',
            'resources' => $payload,
        ]);
    }

    public function fromControllerResult(mixed $result, AttributeMetadata $attributeMetadata): Response
    {
        return $result instanceof Response
            ? $result
            : $this->fromServiceResult($result, $attributeMetadata);
    }
}
