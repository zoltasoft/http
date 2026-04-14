<?php

declare(strict_types=1);

namespace Zolta\Http\Response\Laravel\Resources;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as IlluminateResponse;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Zolta\Http\Response\ResponsePayload;

final class Response extends IlluminateResponse
{
    /**
     * Create a response from a ResponsePayload.
     */
    public static function fromPayload(
        ResponsePayload $responsePayload,
        int $status = SymfonyResponse::HTTP_OK
    ): IlluminateResponse|JsonResponse {
        $request = request();

        // ----- HTML / View responses -----
        if (! $request->is('api/*') && ! $request->expectsJson()) {
            $view = self::resolveViewFromRequest($request);

            if ($view !== null) {
                $viewPayload = [
                    'resources' => $responsePayload->data,
                    'message' => $responsePayload->message,
                    'errors' => $responsePayload->errors,
                ];

                $isInertia =
                    self::engineIndicatesInertia($view->engine)
                    || self::isInertiaRequest($request);

                if ($isInertia) {
                    // Inertia owns its response lifecycle
                    return self::renderInertiaResponse(
                        $request,
                        $view->view,
                        $viewPayload,
                        $status
                    );
                }

                // Standard HTML response (wrapped in our Response)
                return new self(
                    view($view->view, $viewPayload)->render(),
                    $status
                );
            }
        }

        // ----- JSON response (API / expectsJson) -----
        return new JsonResponse(
            self::buildJsonPayload($responsePayload),
            $status
        );
    }

    /**
     * Build the JSON response payload.
     */
    private static function buildJsonPayload(ResponsePayload $responsePayload): array
    {
        return [
            'success' => $responsePayload->success,
            'message' => $responsePayload->message,
            'data' => $responsePayload->data,
            'errors' => $responsePayload->errors,
        ];
    }

    /**
     * Determine if the request is an Inertia request.
     */
    private static function isInertiaRequest(Request $request): bool
    {
        return $request->headers->has('X-Inertia');
    }

    /**
     * Render an Inertia response.
     */
    private static function renderInertiaResponse(
        Request $request,
        string $component,
        array $props,
        int $status
    ): IlluminateResponse {
        return Inertia::render($component, $props)
            ->toResponse($request)
            ->setStatusCode($status);
    }

    /**
     * Detect inertia usage based on engine metadata.
     */
    private static function engineIndicatesInertia(?string $engine): bool
    {
        return $engine === 'inertia';
    }

    /**
     * Resolve the view from the current request.
     *
     * This method is assumed to exist already in your system
     * and return an object like:
     *   ->view   (string)
     *   ->engine (string|null)
     */
    private static function resolveViewFromRequest(Request $request): ?object
    {
        // existing implementation
        return null;
    }
}
