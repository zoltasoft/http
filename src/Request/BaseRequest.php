<?php

declare(strict_types=1);

namespace Zolta\Http\Request;

use LogicException;
use Zolta\Framework\FrameworkRegistry;
use Zolta\Http\Request\Contracts\CoreRequestContract;

/** Resolver that extends the framework binding discovered by the registry. */
class FrameworkBaseRequestFallback implements CoreRequestContract
{
    /** @return array<string,mixed> */
    public function rules(): array
    {
        return [];
    }

    /** @return array<string,mixed> */
    public function options(): array
    {
        return [];
    }

    /**
     * @param  string|array<int|string, mixed>  $action
     */
    public function authorizeAction(string|array $action, mixed $subject = null): void
    {
        throw new LogicException('authorizeAction requires a framework adapter.');
    }

    public function toInputDto(?string $dtoClass = null): mixed
    {
        throw new LogicException('toInputDto requires a framework adapter.');
    }

    public function optionsPayload(): ?array
    {
        throw new LogicException('optionsPayload requires a framework adapter.');
    }

    /**
     * @param  array<int|string,mixed>  $arguments
     */
    public function __call(string $name, array $arguments): mixed
    {
        throw new LogicException("Method '{$name}' requires a framework adapter.");
    }
}

$implementation = FrameworkRegistry::resolveBinding(BaseRequest::class);
$runtimeClass = $implementation !== null && class_exists($implementation)
    ? $implementation
    : FrameworkBaseRequestFallback::class;

$implClass = __NAMESPACE__.'\\FrameworkBaseRequestImplementation';
if (! class_exists($implClass, false)) {
    class_alias($runtimeClass, $implClass);
}

if (! class_exists(__NAMESPACE__.'\\FrameworkBoundBaseRequest', false)) {
    abstract class FrameworkBoundBaseRequest extends FrameworkBaseRequestImplementation implements CoreRequestContract {}
}

// Static-analysis helper
if (false) { // @phpstan-ignore-line
    abstract class FrameworkBaseRequestImplementation implements CoreRequestContract
    {
        /** @return array<string,mixed> */
        public function rules(): array
        {
            return [];
        }

        /** @return array<string,mixed> */
        public function options(): array
        {
            return [];
        }

        /**
         * @param  string|array<int|string,mixed>  $action
         */
        public function authorizeAction(string|array $action, mixed $subject = null): void {}

        public function toInputDto(?string $dtoClass = null): mixed
        {
            return null;
        }

        public function optionsPayload(): ?array
        {
            return null;
        }

        /** @param array<int|string,mixed> $arguments */
        public function __call(string $name, array $arguments): mixed
        {
            return null;
        }
    }
}

class BaseRequest extends FrameworkBoundBaseRequest implements CoreRequestContract
{
    /** @return array<string,mixed> */
    public function rules(): array
    {
        return [];
    }

    /** @return array<string,mixed> */
    public function options(): array
    {
        return [];
    }

    /**
     * Additional data to merge into the validated payload before DTO mapping.
     *
     * Override in concrete requests to inject contextual values (e.g. authenticated
     * user ID, tenant, resolved route parameters) that are not part of the HTTP
     * body but should appear in the DTO.
     *
     * @return array<string,mixed>
     */
    public function withData(): array
    {
        return [];
    }

    /**
     * Proxy to the framework-bound implementation so static analysers see concrete methods.
     *
     * @param  string|array<int|string, mixed>  $action
     */
    public function authorizeAction(string|array $action, mixed $subject = null): void
    {
        // Delegate to bound implementation (runtime); static analysis sees concrete method above.
        parent::authorizeAction($action, $subject);
    }

    public function toInputDto(?string $dtoClass = null): mixed
    {
        return parent::toInputDto($dtoClass);
    }

    public function optionsPayload(): ?array
    {
        return parent::optionsPayload();
    }
}
