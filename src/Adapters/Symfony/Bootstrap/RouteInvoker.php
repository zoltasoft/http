<?php

declare(strict_types=1);

namespace Zolta\Http\Symfony\Bootstrap;

use Psr\Container\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Twig\Environment;
use Zolta\Exceptions\Rest\UnauthorizedException;
use Zolta\Http\Exceptions\ControllerConfigurationException;
use Zolta\Http\Request\Attributes\Request as RequestAttr;
use Zolta\Http\Response\Attributes\Response as ResponseAttr;
use Zolta\Http\Response\Attributes\Views\View as ViewAttr;
use Zolta\Http\Response\Contracts\ApiResponseData;
use Zolta\Http\Router\Cache\ReflectionCache;
use Zolta\Http\Service\Attributes\Service as ServiceAttr;
use Zolta\Http\Symfony\Services\CqrsLocator;

/**
 * Symfony analogue to the Laravel RouteInvoker.
 *
 * Executes attribute-driven controllers (Request/Service/Response).
 */
final readonly class RouteInvoker
{
    public function __construct(
        private ContainerInterface $container,
        private CqrsLocator $cqrsLocator
    ) {}

    public function execute(Request $request): Response
    {
        $controllerClass = (string) $request->attributes->get('target');
        $method = (string) $request->attributes->get('target_method', '__invoke');
        $middleware = (array) $request->attributes->get('middleware', []);

        $this->ensureAuthenticatedIfRequired($middleware);

        return $this->runFromAttributes($request, $controllerClass, $method);
    }

    private function runFromAttributes(Request $request, string $controllerClass, string $method): Response
    {
        $attrs = ReflectionCache::getClassAttributes($controllerClass);
        $methodAttrs = ReflectionCache::getMethodAttributes($controllerClass, $method);
        $allAttrs = array_merge($attrs, $methodAttrs);

        $requestAttr = $this->findAttr($allAttrs, RequestAttr::class);
        $serviceAttr = $this->findAttr($allAttrs, ServiceAttr::class);
        $responseAttr = $this->findAttr($allAttrs, ResponseAttr::class);
        $viewAttr = $this->findAttr($allAttrs, ViewAttr::class);

        // Case 1 — No Request/Service/Response: execute controller directly
        if (! $requestAttr && ! $serviceAttr && ! $responseAttr) {
            $controller = $this->container->get($controllerClass);
            $result = $this->callControllerMethod($controller, $method, $request);

            return $this->autoWrapResponse($result, $request, $responseAttr, $viewAttr);
        }

        // Case 2 — Missing service (manual controller logic)
        if (! $serviceAttr) {
            $this->validateCase2Configuration($controllerClass, $method, $requestAttr);
            $controller = $this->container->get($controllerClass);
            $dto = $this->buildRequestDto($requestAttr);
            $result = $controller->{$method}($request, $dto);

            return $this->autoWrapResponse($result, $request, $responseAttr, $viewAttr);
        }

        // Case 3 — Full attribute-driven pipeline
        $dto = $this->buildRequestDto($requestAttr);
        $serviceClass = $serviceAttr['arguments'][0];
        $service = $this->cqrsLocator->get($serviceClass);

        $result = $dto !== null ? $service($dto) : $service();

        return $this->buildResponse($result, $serviceAttr, $responseAttr, $viewAttr);
    }

    /**
     * Build a validated DTO or payload if a Request attribute is defined.
     *
     * @param  array<string, mixed>|null  $requestAttr
     */
    private function buildRequestDto(?array $requestAttr): mixed
    {
        if (! $requestAttr) {
            return null;
        }

        $requestClass = $requestAttr['arguments'][0] ?? null;
        $dtoClass = $requestAttr['arguments']['inputDto'] ?? ($requestAttr['arguments'][1] ?? null);

        if (! $requestClass) {
            return null;
        }

        $instance = $this->container->get($requestClass);

        $result = $instance->validate($dtoClass);

        if (is_object($result) && method_exists($result, 'toArray')) {
            return $result;
        }

        return (array) $result;
    }

    /**
     * Build a standardized API response.
     *
     * @param  array<string, mixed>  $serviceAttr
     * @param  array<string, mixed>|null  $responseAttr
     * @param  array<string, mixed>|null  $viewAttr
     */
    private function buildResponse(
        mixed $result,
        array $serviceAttr,
        ?array $responseAttr,
        ?array $viewAttr
    ): Response {
        $message = $serviceAttr['arguments'][1] ?? 'Success.';
        $status = $serviceAttr['arguments'][2] ?? 200;

        $payload = $this->normalizeResource($result, $responseAttr);

        $body = [
            'success' => true,
            'message' => $message,
            'resources' => $payload,
        ];

        $view = $this->resolveViewFromAttributes($viewAttr);
        if ($view !== null) {
            $html = $this->renderView($view, [
                'resources' => $payload,
                'message' => $message,
            ]);

            return new Response($html, $status, ['Content-Type' => 'text/html']);
        }

        return new JsonResponse($body, $status);
    }

    /**
     * Automatically wrap plain controller return values into a consistent response format.
     *
     * @param  array<string, mixed>|null  $responseAttr
     * @param  array<string, mixed>|null  $viewAttr
     */
    private function autoWrapResponse(
        mixed $result,
        Request $request,
        ?array $responseAttr = null,
        ?array $viewAttr = null
    ): Response {
        if ($result instanceof Response) {
            return $result;
        }

        $payload = $this->normalizeResource($result, $responseAttr);

        $body = [
            'success' => true,
            'message' => 'Success.',
            'resources' => $payload,
        ];

        $view = $this->resolveViewFromAttributes($viewAttr);
        if ($view !== null) {
            $html = $this->renderView($view, [
                'resources' => $payload,
                'message' => 'Success.',
            ]);

            return new Response($html, 200, ['Content-Type' => 'text/html']);
        }

        return new JsonResponse($body);
    }

    /**
     * @param  array<string, mixed>|null  $viewAttr
     */
    private function resolveViewFromAttributes(?array $viewAttr): ?string
    {
        return $viewAttr['arguments'][0] ?? null;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function renderView(string $view, array $context): string
    {
        // Symfony templating requires Twig to be installed/configured. If not available, throw.
        $twigServiceId = $this->container->has('twig') ? 'twig' : Environment::class;

        if (! class_exists(Environment::class) || ! $this->container->has($twigServiceId)) {
            throw new ControllerConfigurationException(
                previous: null,
                errorCode: 'view.renderer.missing',
                context: [
                    'view' => $view,
                    'hint' => 'Twig environment not available to render views.',
                ]
            );
        }

        /** @var Environment $twig */
        $twig = $this->container->get($twigServiceId);

        return $twig->render($view, $context);
    }

    /**
     * Enforce authentication when a route declares auth middleware.
     *
     * @param  array<int|string,mixed>  $middleware
     */
    private function ensureAuthenticatedIfRequired(array $middleware): void
    {
        $requiresAuth = array_filter($middleware, static fn (mixed $mw): bool => is_string($mw) && str_starts_with($mw, 'auth'));

        if ($requiresAuth === []) {
            return;
        }

        if (! interface_exists(AuthorizationCheckerInterface::class) || ! $this->container->has('security.authorization_checker')) {
            throw new ControllerConfigurationException(
                previous: null,
                errorCode: 'auth.missing',
                context: [
                    'hint' => 'Install/configure Symfony Security to use auth middleware.',
                ]
            );
        }

        /** @var AuthorizationCheckerInterface $checker */
        $checker = $this->container->get('security.authorization_checker');

        if (! $checker->isGranted('IS_AUTHENTICATED_FULLY')) {
            throw new UnauthorizedException;
        }
    }

    /**
     * Call controller method with request and optional DTO argument.
     */
    private function callControllerMethod(object $controller, string $method, Request $request): mixed
    {
        if (! method_exists($controller, $method)) {
            return new JsonResponse(['message' => 'Controller method not found.'], 500);
        }

        $reflectionMethod = new \ReflectionMethod($controller, $method);
        $parameters = $reflectionMethod->getParameters();

        if (empty($parameters)) {
            return $controller->{$method}();
        }

        $args = [];
        foreach ($parameters as $parameter) {
            $paramType = $parameter->getType();

            if ($paramType instanceof \ReflectionNamedType && ! $paramType->isBuiltin()) {
                $expectedClass = $paramType->getName();

                if (is_subclass_of($expectedClass, Request::class)) {
                    $args[] = $request;

                    continue;
                }
            }

            $args[] = $parameter->getPosition() === 0 ? $request : null;
        }

        return $controller->{$method}(...$args);
    }

    /**
     * Validate Case 2 controller configuration to prevent conflicts.
     *
     * Case 2 expects controllers to accept (Request $request, array $dto) but having
     * #[Request] attribute with custom request classes in method signature creates conflicts.
     *
     * @param  array<string, mixed>|null  $requestAttr
     */
    private function validateCase2Configuration(string $controllerClass, string $method, ?array $requestAttr): void
    {
        if (! $requestAttr) {
            return; // No request attribute, no conflict possible
        }

        $reflectionMethod = new \ReflectionMethod($controllerClass, $method);
        $parameters = $reflectionMethod->getParameters();

        if (empty($parameters)) {
            return; // No parameters, no conflict
        }

        $firstParam = $parameters[0];
        $paramType = $firstParam->getType();

        if (! ($paramType instanceof \ReflectionNamedType) || $paramType->isBuiltin()) {
            return; // Not a class type or builtin type, no conflict
        }

        $expectedClass = $paramType->getName();

        if ($expectedClass === Request::class) {
            return;
        }

        throw new ControllerConfigurationException(
            previous: null,
            errorCode: 'controller.configuration.conflict',
            context: [
                'controller' => $controllerClass,
                'method' => $method,
                'request_attribute' => $requestAttr['arguments'][0] ?? 'unknown',
                'method_signature' => $expectedClass,
                'conflict_explanation' => 'Found #[Request] attribute (attribute-driven approach) but method expects custom request class (dependency injection approach). These are mutually exclusive.',
            ]
        );
    }

    /**
     * Normalize result into array using Response attribute resource when provided.
     *
     * @param  array<string, mixed>|null  $responseAttr
     * @return array<string, mixed>|array<int, mixed>|null
     */
    private function normalizeResource(mixed $result, ?array $responseAttr): mixed
    {
        $resourceClass = $responseAttr['arguments'][0] ?? null;
        if (is_string($resourceClass) && is_subclass_of($resourceClass, ApiResponseData::class)) {
            /** @var ApiResponseData $resource */
            $resource = new $resourceClass($result);

            return $resource->toArray();
        }

        if ($result instanceof ApiResponseData) {
            return $result->toArray();
        }

        if (is_object($result) && method_exists($result, 'toArray')) {
            $array = $result->toArray();

            return is_array($array) ? $array : $result;
        }

        return $result;
    }

    /**
     * Helper: find a specific attribute in a reflection attribute array.
     *
     * @param  array<int, array<string, mixed>>  $attrs
     * @return array<string, mixed>|null
     */
    private function findAttr(array $attrs, string $target): ?array
    {
        foreach ($attrs as $attr) {
            if ($attr['class'] === $target) {
                return $attr;
            }
        }

        return null;
    }
}
