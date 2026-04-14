<?php

declare(strict_types=1);

namespace Zolta\Http\Response\Contracts;

use Zolta\Http\Response\ResponsePayload;

interface ResponseBridge
{
    public function respond(
        ResponsePayload $responsePayload,
        int $status = 200
    ): mixed;
}
