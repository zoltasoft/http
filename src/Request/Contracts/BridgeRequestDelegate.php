<?php

declare(strict_types=1);

namespace Zolta\Http\Request\Contracts;

/**
 * Contract implemented by framework-specific bridge requests so the core
 * bridge adapter can delegate behavior in a framework-agnostic way.
 */
interface BridgeRequestDelegate
{
    /**
     * Public resolver for DTO class (avoids protected visibility issues).
     */
    public function resolveDtoClass(): ?string;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array;

    /**
     * @return array<string, mixed>
     */
    public function options(): array;

    /**
     * @return array<string, mixed>
     */
    public function queryOptions(): array;

    // /**
    //  * @return array<string, mixed>
    //  */
    // public function queryParams(): array;

    // /**
    //  * @return array<string, mixed>
    //  */
    // public function routeParams(): array;

    /**
     * @return array<string, mixed>|null
     */
    public function optionsPayload(): ?array;

    /**
     * Map the validated request data to an InputDTO instance.
     *
     * The adapter is responsible for extracting validated data from the
     * framework-specific request and delegating to the core RequestMapper.
     *
     * @template T
     *
     * @param  class-string<T>|null  $dtoClass
     * @return T|null
     */
    public function mapToInputDto(?string $dtoClass = null): mixed;

    /**
     * Forward method calls the delegate knows how to handle.
     *
     * @param  list<mixed>  $arguments
     */
    public function forwardCall(string $name, array $arguments): mixed;
}
