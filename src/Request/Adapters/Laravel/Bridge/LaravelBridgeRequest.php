<?php

declare(strict_types=1);

namespace Zolta\Http\Request\Laravel\Bridge;

use Illuminate\Foundation\Http\FormRequest;
use Zolta\Http\Authorization\Interfaces\AuthorizationServiceInterface;
use Zolta\Http\Request\BaseRequest as CoreBaseRequest;
use Zolta\Http\Request\Bridge\BridgeRequest;
use Zolta\Http\Request\Contracts\BridgeRequestDelegate;
use Zolta\Http\Request\Contracts\CoreRequestContract;
use Zolta\Http\Request\Contracts\RequestPort;
use Zolta\Http\Request\Interfaces\ValidatorInterface;
use Zolta\Http\Request\Laravel\HttpQueryOptions;
use Zolta\Http\Request\Laravel\RequestMapper;

/**
 * Bridges Laravel FormRequest with Core BaseRequest.
 *
 * Allows DTO mapping to be optional (no dtoClass required).
 */
abstract class LaravelBridgeRequest extends FormRequest implements BridgeRequestDelegate, CoreRequestContract
{
    protected CoreBaseRequest $core;

    public function __construct(
        RequestPort $port,
        ValidatorInterface $validator,
        AuthorizationServiceInterface $authz
    ) {
        parent::__construct();
        $this->core = $this->makeCore($port, $validator, $authz);
        $this->configureDependencies();
    }

    protected function makeCore(
        RequestPort $requestPort,
        ValidatorInterface $validator,
        AuthorizationServiceInterface $authorizationService
    ): CoreBaseRequest {
        return new BridgeRequest($requestPort, $validator, $authorizationService, $this);
    }

    /** -------- Bridge API -------- */
    public function toInputDto(?string $dtoClass = null): mixed
    {
        return $this->core->toInputDto($dtoClass);
    }

    public function mapToInputDto(?string $dtoClass = null): mixed
    {
        $dtoClass ??= $this->dtoClass();

        if ($dtoClass === null) {
            return null;
        }

        return RequestMapper::map($this, $dtoClass);
    }

    /**
     * @param  string|array<int|string, mixed>  $action
     */
    public function authorizeAction(string|array $action, mixed $subject = null): void
    {
        $this->core->authorizeAction($action, $subject);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function merge(array $data): static
    {
        parent::merge($data);
        $this->core->merge($data);

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function all($keys = null): array
    {
        if (method_exists($this, 'mergeQueryParams')) {
            $this->mergeQueryParams();
        }
        if (method_exists($this, 'mergeRouteParams')) {
            $this->mergeRouteParams();
        }

        return parent::all($keys);
    }

    /** -------- Public Resolver (required by Core adapter) -------- */
    public function resolveDtoClass(): ?string
    {
        return $this->dtoClass();
    }

    /** -------- Overridables for concrete requests -------- */
    protected function dtoClass(): ?string
    {
        return null; // optional by default
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
    public function getRouteParameters(): array
    {
        $route = parent::route();

        return $route ? $route->parameters() : [];
    }

    /**
     * Override this in application requests when you need to resolve additional
     * collaborators without touching the constructor.
     */
    protected function configureDependencies(): void {}

    /**
     * @return array<string, mixed>|null
     */
    public function optionsPayload(): ?array
    {
        return HttpQueryOptions::payload($this, $this->queryOptions());
    }

    /** -------- BridgeRequestDelegate API -------- */
    public function forwardCall(string $name, array $arguments): mixed
    {
        if (method_exists($this, $name)) {
            return $this->{$name}(...$arguments);
        }

        return parent::__call($name, $arguments);
    }
}
