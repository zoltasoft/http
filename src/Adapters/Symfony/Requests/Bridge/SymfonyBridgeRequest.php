<?php

declare(strict_types=1);

namespace Zolta\Http\Symfony\Requests\Bridge;

use LogicException;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Zolta\Exceptions\Rest\UnauthorizedException;
use Zolta\Http\Authorization\Interfaces\AuthorizationServiceInterface;
use Zolta\Http\Request\Bridge\BridgeRequest;
use Zolta\Http\Request\Contracts\BridgeRequestDelegate;
use Zolta\Http\Request\Contracts\CoreRequestContract;
use Zolta\Http\Request\Contracts\RequestPort;
use Zolta\Http\Request\Interfaces\ValidatorInterface;
use Zolta\Http\Symfony\Requests\HttpQueryOptions;

/**
 * Bridge request for Symfony.
 *
 * Mirrors the Laravel bridge: host request classes extend this and implement
 * rules/routeParams/queryParams while core validation, DTO mapping and
 * authorization are delegated to {@see BridgeRequest}.
 */
abstract class SymfonyBridgeRequest implements BridgeRequestDelegate, CoreRequestContract
{
    protected ?BridgeRequest $core = null;

    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly ?RequestPort $bridgeRequestPort,
        private readonly ?ValidatorInterface $bridgeValidator,
        private readonly ?AuthorizationServiceInterface $bridgeAuthorizationService,
    ) {
        $this->core = new BridgeRequest($this->bridgeRequestPort, $this->bridgeValidator, $this->bridgeAuthorizationService, $this);
    }

    public function toInputDto(?string $dtoClass = null): mixed
    {
        return $this->bridge()->toInputDto($dtoClass);
    }

    public function validate(?string $dtoClass = null): mixed
    {
        $this->runAuthorizationHook();

        return $this->bridge()->validate($dtoClass);
    }

    /**
     * @param  string|array<int,string>  $action
     */
    public function authorizeAction(string|array $action, mixed $subject = null): void
    {
        $this->bridge()->authorizeAction($action, $subject);
    }

    /**
     * Mirror Laravel FormRequest::authorize; can be overridden by host requests.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function merge(array $data): void
    {
        $this->bridge()->merge($data);
    }

    /**
     * Provide access to the current Symfony request.
     */
    protected function request(): ?Request
    {
        return $this->requestStack->getCurrentRequest();
    }

    /**
     * Route parameters configuration (compatible with UseRequestRouteParams trait).
     *
     * @return array<string, mixed>
     */
    public function routeParams(): array
    {
        return [];
    }

    /**
     * Route parameters helper (compatible with UseRequestRouteParams trait).
     *
     * @return array<string, mixed>
     */
    public function route(): array
    {
        $request = $this->request();

        return $request instanceof Request ? $request->attributes->all() : [];
    }

    /**
     * Query helper (compatible with UseRequestQueryParams trait).
     *
     * @return bool|float|int|string|array<string,mixed>|null
     */
    public function query(?string $key = null, bool|float|int|string|null $default = null): mixed
    {
        $request = $this->request();
        if (! $request instanceof Request) {
            return $key === null ? [] : $default;
        }

        if ($key === null) {
            /** @var array<string,mixed> */
            $all = $request->query->all();

            return $all;
        }

        try {
            /** @var bool|float|int|string|null $value */
            $value = $request->query->get($key, $default);

            return $value;
        } catch (BadRequestException) {
            // InputBag::get throws on array values; fall back to all() to allow array inputs.
            $value = $request->query->all($key);

            return $value === [] ? $default : $value;
        }
    }

    /** -------- Delegate hooks -------- */
    public function resolveDtoClass(): ?string
    {
        return $this->dtoClass();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function optionsPayload(): ?array
    {
        $request = $this->request();
        if ($request instanceof Request) {
            return HttpQueryOptions::payload($request, $this->queryOptions());
        }

        return $this->queryOptions();
    }

    /**
     * Forward dynamic calls to the core bridge (parity with Laravel implementation).
     *
     * @param  list<mixed>  $arguments
     */
    public function forwardCall(string $name, array $arguments): mixed
    {
        if (method_exists($this, $name)) {
            return $this->{$name}(...$arguments);
        }

        return $this->bridge()->{$name}(...$arguments);
    }

    /** -------- Overridables for concrete requests -------- */
    protected function dtoClass(): ?string
    {
        return null;
    }

    /**
     * @return array<string, mixed>
     */
    abstract public function rules(): array;

    /**
     * @return array<string, mixed>
     */
    public function options(): array
    {
        return $this->queryOptions();
    }

    /**
     * @return array<string, mixed>
     */
    public function queryOptions(): array
    {
        return [];
    }

    /**
     * Ensure bridge core is initialized.
     */
    private function bridge(): BridgeRequest
    {
        if ($this->core instanceof BridgeRequest) {
            return $this->core;
        }

        if (
            ! $this->bridgeRequestPort instanceof RequestPort
            || ! $this->bridgeValidator instanceof ValidatorInterface
            || ! $this->bridgeAuthorizationService instanceof AuthorizationServiceInterface
        ) {
            throw new LogicException('SymfonyBridgeRequest not initialized; ensure dependency injection is configured.');
        }

        $this->core = new BridgeRequest(
            $this->bridgeRequestPort,
            $this->bridgeValidator,
            $this->bridgeAuthorizationService,
            $this
        );

        return $this->core;
    }

    /**
     * Run the authorize() hook before validation (parity with Laravel FormRequest).
     */
    private function runAuthorizationHook(): void
    {
        // Execute gates from route attributes first
        $routeGates = $this->getRouteGates();
        if ($routeGates !== []) {
            foreach ($routeGates as $routeGate) {
                $this->authorizeAction($routeGate);
            }
        }

        // Still call authorize() for custom logic
        $allowed = $this->authorize();

        if ($allowed === false) {
            throw new UnauthorizedException;
        }
    }

    /**
     * Get gates from the current route's attributes.
     *
     * @return array<int|string,string>
     */
    private function getRouteGates(): array
    {
        $request = $this->request();
        if (! $request instanceof Request) {
            return [];
        }

        $gates = $request->attributes->get('authorized');
        if ($gates === null) {
            $gates = $request->attributes->get('gate', []);
        }

        return (array) $gates;
    }
}
