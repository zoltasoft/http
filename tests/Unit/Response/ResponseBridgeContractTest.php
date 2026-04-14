<?php

declare(strict_types=1);

namespace Zolta\Tests\Unit\Response;

use PHPUnit\Framework\TestCase;
use Zolta\Http\Response\Contracts\ResponseBridge;
use Zolta\Http\Response\ResponsePayload;

final class ResponseBridgeContractTest extends TestCase
{
    public function test_interface_defines_respond_method(): void
    {
        $ref = new \ReflectionClass(ResponseBridge::class);

        $this->assertTrue($ref->isInterface());
        $this->assertTrue($ref->hasMethod('respond'));

        $method = $ref->getMethod('respond');
        $params = $method->getParameters();

        $this->assertCount(2, $params);
        $this->assertSame('responsePayload', $params[0]->getName());
        $this->assertSame('status', $params[1]->getName());
        $this->assertTrue($params[1]->isOptional());
        $this->assertSame(200, $params[1]->getDefaultValue());
    }

    public function test_bridge_implementation_receives_payload(): void
    {
        $captured = new \stdClass;
        $captured->payload = null;
        $captured->status = null;

        $bridge = new class($captured) implements ResponseBridge
        {
            public function __construct(private readonly \stdClass $captured) {}

            public function respond(ResponsePayload $payload, int $status = 200): mixed
            {
                $this->captured->payload = $payload;
                $this->captured->status = $status;

                return ['success' => $payload->success];
            }
        };

        $payload = new ResponsePayload(success: true, message: 'ok', data: ['key' => 'value']);
        $result = $bridge->respond($payload, 201);

        $this->assertSame($payload, $captured->payload);
        $this->assertSame(201, $captured->status);
        $this->assertSame(['success' => true], $result);
    }
}
