<?php

declare(strict_types=1);

namespace Zolta\Http\Router\Laravel\Bootstrap;

use Illuminate\Http\Request;
use Throwable;
use Zolta\Http\Authorization\Interfaces\AuthorizationServiceInterface;
use Zolta\Http\Router\Laravel\Bootstrap\Invocation\ControllerInvoker;
use Zolta\Http\Router\Laravel\Bootstrap\Invocation\ServiceInvoker;
use Zolta\Http\Router\Laravel\Bootstrap\Metadata\RouteMetadataResolver;
use Zolta\Http\Router\Laravel\Bootstrap\Request\RequestDtoFactory;
use Zolta\Http\Router\Laravel\Bootstrap\Response\ExceptionResponseFactory;
use Zolta\Http\Router\Laravel\Bootstrap\Response\ResponseFactory;
use Zolta\Http\Router\Laravel\Bootstrap\Transformation\ResourceTransformer;
use Zolta\Http\Router\Laravel\Bootstrap\Validation\RouteConfigurationValidator;

/**
 * Orchestrates the Laravel execution pipeline for attribute-driven routes:
 * resolve metadata, validate configuration, invoke controller or service, then
 * normalize resources and wrap them into a response.
 */
final readonly class RouteInvoker
{
    public function __construct(
        private RouteMetadataResolver $routeMetadataResolver,
        private RouteConfigurationValidator $routeConfigurationValidator,
        private RequestDtoFactory $requestDtoFactory,
        private ControllerInvoker $controllerInvoker,
        private ServiceInvoker $serviceInvoker,
        private ResourceTransformer $resourceTransformer,
        private ResponseFactory $responseFactory,
        private ExceptionResponseFactory $exceptionResponseFactory,
        private AuthorizationServiceInterface $authorizationService,
    ) {}

    public function execute(Request $request): mixed
    {
        try {
            $controllerClass = $request->route('target');
            $method = $request->route('target_method', '__invoke');

            $meta = $this->routeMetadataResolver->resolve($controllerClass, $method);

            $this->routeConfigurationValidator->validate($controllerClass, $method, $meta);

            // Route-declared authorization (defaults emitted by AttributeRouteLoader)
            $routeGates = $request->route('authorized');
            if ($routeGates !== null && $routeGates !== []) {
                foreach ((array) $routeGates as $gate) {
                    $this->authorizationService->ensureAuthorized((string) $gate);
                }
            }

            $result = $meta->hasService()
                ? $this->serviceInvoker->invoke($meta, $request, $this->requestDtoFactory)
                : $this->controllerInvoker->invoke($meta, $request, $this->requestDtoFactory);

            $result = $this->resourceTransformer->transform($meta, $result, $request);

            return $this->responseFactory->success($result, $meta->status, $meta->message);
        } catch (Throwable $e) {
            return $this->exceptionResponseFactory->fromException($e);
        }
    }
}
