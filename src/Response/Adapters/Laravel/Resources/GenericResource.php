<?php

declare(strict_types=1);

namespace Zolta\Http\Response\Laravel\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * GenericResource
 *
 * Used as a fallback when no specific #[Response(...)] attribute is defined.
 * Ensures every endpoint still returns a uniform API response structure.
 */
final class GenericResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        // Preserve raw scalar/array data
        if (is_array($this->resource)) {
            return $this->resource;
        }

        if (is_object($this->resource)) {
            return method_exists($this->resource, 'toArray')
                ? $this->resource->toArray()
                : (array) $this->resource;
        }

        // Scalars or null
        return ['value' => $this->resource];
    }
}
