<?php

declare(strict_types=1);

namespace Zolta\Http\Request\Bridge;

use Zolta\Http\Authorization\Interfaces\AuthorizationServiceInterface;
use Zolta\Http\Request\BaseRequest;
use Zolta\Http\Request\Contracts\BridgeRequestDelegate;
use Zolta\Http\Request\Contracts\RequestPort;
use Zolta\Http\Request\Interfaces\ValidatorInterface;

/**
 * Core bridge that adapts a framework-specific request (delegate) into the
 * framework-agnostic BaseRequest pipeline. Allows different frameworks to plug
 * in without changing host request classes.
 *
 * Because BaseRequest is framework-bound at runtime (e.g. extends LaravelBridgeRequest
 * which extends FormRequest), BridgeRequest inherits Symfony Request properties
 * and LaravelBridgeRequest methods that reference $this->core.
 *
 * To avoid infinite constructor recursion AND uninitialised-property errors:
 *  1. We call $this->initialize() (Symfony Request) instead of parent::__construct().
 *  2. We override every LaravelBridgeRequest method that delegates through $this->core
 *     so calls on THIS instance never touch the unset $core property.
 */
final class BridgeRequest extends BaseRequest
{
    public function __construct(
        RequestPort $requestPort,
        ValidatorInterface $validator,
        private readonly AuthorizationServiceInterface $authorizationService,
        private readonly BridgeRequestDelegate $bridgeRequestDelegate
    ) {
        // Initialize Symfony Request properties (headers, query, request, …)
        // WITHOUT calling the PHP constructor chain (which would re-enter
        // LaravelBridgeRequest::__construct → makeCore → new BridgeRequest → ∞).
        $this->initialize();
    }

    // ------------------------------------------------------------------
    // Override every LaravelBridgeRequest method that uses $this->core
    // so calls on THIS instance never touch the unset $core property.
    // These are terminal implementations — they break the circular
    // delegation chain (LaravelBridgeRequest → $this->core → back).
    // ------------------------------------------------------------------

    public function toInputDto(?string $dtoClass = null): mixed
    {
        $dtoClass ??= $this->dtoClass();

        return $this->bridgeRequestDelegate->mapToInputDto($dtoClass);
    }

    /**
     * @param  string|array<int|string, mixed>  $action
     */
    public function authorizeAction(string|array $action, mixed $subject = null): void
    {
        if (is_array($action)) {
            foreach ($action as $entry) {
                $this->authorizationService->ensureAuthorized((string) $entry, $subject);
            }

            return;
        }

        $this->authorizationService->ensureAuthorized((string) $action, $subject);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function merge(array $data): static
    {
        // State lives in the delegate (the actual FormRequest).
        // No-op here to avoid accessing unset $this->core or duplicating merges.
        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function all($keys = null): array
    {
        return $this->bridgeRequestDelegate->forwardCall('all', [$keys]);
    }

    /**
     * @noRector PrivatizeFinalClassMethodRector
     */
    protected function dtoClass(): ?string
    {
        return $this->bridgeRequestDelegate->resolveDtoClass();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->bridgeRequestDelegate->rules();
    }

    /**
     * @return array<string, mixed>
     */
    public function options(): array
    {
        return $this->bridgeRequestDelegate->queryOptions();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function optionsPayload(): ?array
    {
        return $this->bridgeRequestDelegate->optionsPayload();
    }

    /**
     * @return array<string, mixed>
     *
     * @noRector PrivatizeFinalClassMethodRector
     */
    public function queryParams(): array
    {
        if (method_exists($this->bridgeRequestDelegate, 'queryParams')) {
            return $this->bridgeRequestDelegate->queryParams();
        }

        return [];
    }

    /**
     * @return array<string, mixed>
     */
    public function routeParams(): array
    {
        if (method_exists($this->bridgeRequestDelegate, 'routeParams')) {
            return $this->bridgeRequestDelegate->routeParams();
        }

        return [];
    }

    /**
     * Allow delegates to handle additional methods (e.g. FormRequest API).
     */
    public function __call($method, $parameters): mixed
    {
        if (method_exists($this->bridgeRequestDelegate, $method)) {
            return $this->bridgeRequestDelegate->{$method}(...$parameters);
        }

        return $this->bridgeRequestDelegate->forwardCall($method, $parameters);
    }
}
