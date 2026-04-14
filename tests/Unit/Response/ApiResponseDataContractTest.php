<?php

declare(strict_types=1);

namespace Zolta\Tests\Unit\Response;

use PHPUnit\Framework\TestCase;
use Zolta\Http\Response\Contracts\ApiResponseData;

final class ApiResponseDataContractTest extends TestCase
{
    public function test_interface_requires_to_array(): void
    {
        $ref = new \ReflectionClass(ApiResponseData::class);

        $this->assertTrue($ref->isInterface());
        $this->assertTrue($ref->hasMethod('toArray'));

        $method = $ref->getMethod('toArray');
        $this->assertSame('array', (string) $method->getReturnType());
    }

    public function test_anonymous_implementation_works(): void
    {
        $resource = new class implements ApiResponseData
        {
            /** @return array<string, mixed> */
            public function toArray(): array
            {
                return ['id' => 1, 'name' => 'Test'];
            }
        };

        $this->assertSame(['id' => 1, 'name' => 'Test'], $resource->toArray());
        $this->assertInstanceOf(ApiResponseData::class, $resource);
    }
}
