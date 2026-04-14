<?php

declare(strict_types=1);

namespace Zolta\Http\Response\Resources;

use InvalidArgumentException;
use Zolta\Http\Response\Contracts\ApiResponseData;
use Zolta\Http\Router\Services\RouterService;
use Zolta\Support\Application\DTO\Interfaces\ResponseDTO as ResponseDTOInterface;
use Zolta\Support\Application\DTO\Output\ResponseDTO as BaseResponseDTO;

/**
 * Base API Resource wrapper.
 *
 * Provides a bridge between the ApplicationResponse (containing a ResponseDTO)
 * and the array format required for API responses.
 */
abstract class Resource implements ApiResponseData
{
    protected ResponseDTOInterface $response;

    /**
     * @param  array<string, mixed>|ResponseDTOInterface  $response
     */
    public function __construct(ResponseDTOInterface|array $response, private readonly ?RouterService $routerService = null)
    {
        if (is_array($response)) {
            $response = $this->wrapArrayResponse($response);
        }

        if (! $response instanceof ResponseDTOInterface) {
            $type = get_debug_type($response);

            throw new InvalidArgumentException("Expected ResponseDTO, ApplicationResponse or array. Received {$type}.");
        }

        $this->response = $response;
    }

    /**
     * Get a value from the underlying DTO.
     *
     * @param  string  $key  The DTO property name.
     * @return mixed|null The value if it exists, otherwise null.
     */
    protected function get(string $key): mixed
    {
        return $this->response->{$key} ?? null;
    }

    /**
     * Set a value on the underlying DTO.
     *
     * @param  string  $key  The property name to set.
     * @param  mixed  $value  The value to assign.
     */
    protected function set(string $key, mixed $value): void
    {
        $this->response->{$key} = $value;
    }

    /**
     * Check if the DTO has a given property set.
     *
     * @param  string  $key  The DTO property name.
     */
    protected function has(string $key): bool
    {
        return isset($this->response->{$key});
    }

    /**
     * Gets the current router.
     */
    protected function router(): mixed
    {
        return $this->routerService?->router() ?? null;
    }

    /**
     * Return the entire DTO as an array.
     *
     * @return array<string, mixed>
     */
    protected function all(): array
    {
        return $this->response->toArray();
    }

    /**
     * Convert the DTO to an array format for API responses.
     *
     * @return array<string, mixed>
     */
    abstract public function toArray(): array;

    /**
     * @param  array<string, mixed>  $payload
     */
    private function wrapArrayResponse(array $payload): ResponseDTOInterface
    {
        return new class($payload) extends BaseResponseDTO
        {
            /** @param array<string, mixed> $payload */
            public function __construct(private array $payload) {}

            public function __get(string $name): mixed
            {
                return $this->payload[$name] ?? null;
            }

            public function __set(string $name, mixed $value): void
            {
                $this->payload[$name] = $value;
            }

            public function __isset(string $name): bool
            {
                return isset($this->payload[$name]);
            }

            public function toArray(): array
            {
                return $this->payload;
            }
        };
    }
}
