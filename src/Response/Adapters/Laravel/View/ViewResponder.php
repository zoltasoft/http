<?php

declare(strict_types=1);

namespace Zolta\Http\Response\Laravel\View;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as IlluminateResponse;
use Inertia\Inertia;
use Zolta\Http\Response\Attributes\Views\ViewDefinition;

final class ViewResponder
{
    public function respond(
        Request $request,
        ViewDefinition $viewDefinition,
        array $payload,
        int $status
    ): IlluminateResponse|JsonResponse {
        $engine = $viewDefinition->engine;

        if ($this->shouldUseInertia($request, $engine)) {
            return $this->renderInertia($request, $viewDefinition->view, $payload, $status);
        }

        return response()->view($viewDefinition->view, $payload, $status);
    }

    private function shouldUseInertia(Request $request, ?string $engine): bool
    {
        if ($engine !== null && strcasecmp($engine, 'inertia') === 0) {
            return true;
        }

        if (method_exists($request, 'inertia') && $request->inertia()) {
            return true;
        }

        return $request->header('X-Inertia') !== null;
    }

    private function renderInertia(
        Request $request,
        string $view,
        array $payload,
        int $status
    ): IlluminateResponse|JsonResponse {
        if (! class_exists(Inertia::class)) {
            return response()->view($view, $payload, $status);
        }

        return Inertia::render($view, $payload)
            ->toResponse($request)
            ->setStatusCode($status);
    }
}
