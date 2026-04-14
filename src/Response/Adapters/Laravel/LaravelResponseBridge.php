<?php

declare(strict_types=1);

namespace Zolta\Http\Response\Laravel;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response as IlluminateResponse;
use Zolta\Http\Response\Attributes\Views\ViewDefinition;
use Zolta\Http\Response\Contracts\ResponseBridge;
use Zolta\Http\Response\Laravel\Api\ApiJsonResponder;
use Zolta\Http\Response\Laravel\View\ViewResolver;
use Zolta\Http\Response\Laravel\View\ViewResponder;
use Zolta\Http\Response\ResponsePayload;

final readonly class LaravelResponseBridge implements ResponseBridge
{
    public function __construct(
        private ViewResolver $viewResolver,
        private ViewResponder $viewResponder,
        private ApiJsonResponder $apiJsonResponder,
    ) {}

    public function respond(ResponsePayload $responsePayload, int $status = 200): IlluminateResponse|JsonResponse
    {
        $request = request();

        if (! $request->is('api/*') && ! $request->expectsJson()) {
            $view = $this->viewResolver->resolve($request);

            if ($view instanceof ViewDefinition) {
                return $this->viewResponder->respond(
                    $request,
                    $view,
                    [
                        'resources' => $responsePayload->data,
                        'message' => $responsePayload->message,
                        'errors' => $responsePayload->errors,
                    ],
                    $status
                );
            }
        }

        return $this->apiJsonResponder->respond($responsePayload, $status);
    }
}
