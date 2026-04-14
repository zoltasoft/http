<?php

declare(strict_types=1);

namespace Zolta\Http\Symfony\Bootstrap\Execution;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Zolta\Http\Authorization\Symfony\AuthenticationGuard;

final readonly class RouteInvoker
{
    public function __construct(
        private AuthenticationGuard $authenticationGuard,
        private AttributeResolver $attributeResolver,
        private ConfigurationValidator $configurationValidator,
        private RequestDtoFactory $requestDtoFactory,
        private ControllerCaller $controllerCaller,
        private CqrsExecutor $cqrsExecutor,
        private ResponseFactory $responseFactory
    ) {}

    public function execute(Request $request): Response
    {
        $target = (string) $request->attributes->get('target');
        $method = (string) $request->attributes->get('target_method', '__invoke');
        $middleware = (array) $request->attributes->get('middleware', []);

        // Enforce middleware
        $this->authenticationGuard->enforce($middleware);

        // Resolve attributes & validate controller configuration
        $attributeMetadata = $this->attributeResolver->resolve($target, $method);
        $this->configurationValidator->validate($target, $method, $attributeMetadata);

        if ($attributeMetadata->isDirectController()) {
            // Simply call the controller method
            // The ControllerCaller will handle it directly
            $result = $this->controllerCaller->call($target, $method);

            return $this->responseFactory->fromControllerResult($result, $attributeMetadata);
        }

        // CQRS execution path
        $dto = $this->requestDtoFactory->create($attributeMetadata->requestAttr);
        $result = $this->cqrsExecutor->execute($attributeMetadata->serviceAttr, $dto);

        return $this->responseFactory->fromServiceResult($result, $attributeMetadata);
    }
}
