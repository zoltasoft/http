<?php

declare(strict_types=1);

namespace Zolta\Tests\Unit\Request;

use PHPUnit\Framework\TestCase;
use Zolta\Http\Request\Contracts\HttpRequestContract;

final class HttpRequestContractTest extends TestCase
{
    public function test_interface_defines_all_input_methods(): void
    {
        $ref = new \ReflectionClass(HttpRequestContract::class);

        $expected = [
            'all',
            'input',
            'only',
            'except',
            'has',
            'filled',
            'query',
            'post',
            'json',
            'header',
            'bearerToken',
            'file',
            'hasFile',
            'cookie',
            'route',
            'method',
            'isMethod',
            'url',
            'fullUrl',
            'path',
            'ip',
            'isJson',
            'wantsJson',
            'isAjax',
            'user',
        ];

        foreach ($expected as $method) {
            $this->assertTrue(
                $ref->hasMethod($method),
                "HttpRequestContract must define {$method}()"
            );
        }
    }

    public function test_has_and_filled_accept_string_or_array(): void
    {
        $ref = new \ReflectionClass(HttpRequestContract::class);

        foreach (['has', 'filled'] as $methodName) {
            $method = $ref->getMethod($methodName);
            $param = $method->getParameters()[0];
            $type = $param->getType();

            $this->assertInstanceOf(\ReflectionUnionType::class, $type);
        }
    }

    public function test_bearer_token_is_nullable_string(): void
    {
        $ref = new \ReflectionClass(HttpRequestContract::class);
        $method = $ref->getMethod('bearerToken');
        $returnType = $method->getReturnType();

        $this->assertInstanceOf(\ReflectionNamedType::class, $returnType);
        $this->assertSame('string', $returnType->getName());
        $this->assertTrue($returnType->allowsNull());
    }
}
