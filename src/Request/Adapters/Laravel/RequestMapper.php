<?php

declare(strict_types=1);

namespace Zolta\Http\Request\Laravel;

use Illuminate\Foundation\Http\FormRequest;
use Zolta\Exceptions\ValidationException;
use Zolta\Http\Request\RequestMapper as CoreRequestMapper;
use Zolta\Support\Application\DTO\Interfaces\InputDTO;

final class RequestMapper
{
    /**
     * Map a Laravel request to a DTO or array.
     *
     * @template T of InputDTO
     *
     * @param  FormRequest|BaseRequest  $formRequest
     * @param  class-string<T>|null  $dtoClass
     * @return T|array<string, mixed>
     *
     * @throws ValidationException
     */
    public static function map(FormRequest $formRequest, ?string $dtoClass = null, ?callable $callback = null): InputDTO|array
    {
        $data = $formRequest->validated();

        // Inject the parsed query-options payload so that DTOs with an `options`
        // constructor parameter (e.g. ListRolesDTO) receive the structured options
        // built from the request query string (include, filter, sort, page, …).
        // Only injected when the request exposes optionsPayload() and the key is
        // not already present in the validated data.
        if (! array_key_exists('options', $data) && method_exists($formRequest, 'optionsPayload')) {
            $payload = $formRequest->optionsPayload();
            if ($payload !== null) {
                $data['options'] = $payload;
            }
        }

        // Merge additional contextual data declared by the request (e.g. authenticated
        // user ID, tenant). Validated values take precedence — withData() only fills
        // keys that are not already present.
        if (method_exists($formRequest, 'withData')) {
            $extra = $formRequest->withData();
            if (is_array($extra) && $extra !== []) {
                $data = array_merge($extra, $data);
            }
        }

        if ($callback) {
            $data = $callback($data);
        }

        // No DTO? Return raw array
        if ($dtoClass === null) {
            return $data;
        }

        return CoreRequestMapper::map($data, $dtoClass);
    }
}
