<?php

declare(strict_types=1);

namespace Zolta\Http\Exceptions\Laravel;

use Illuminate\Contracts\Debug\ExceptionHandler as ExceptionHandlerContract;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response as IlluminateResponse;
use Throwable;
use Zolta\Http\Response\HttpResponse;
use Zolta\Http\Response\ResponsePayload;

final readonly class ExceptionHandler implements ExceptionHandlerContract
{
    public function __construct(
        private ExceptionMapper $exceptionMapper
    ) {}

    /**
     * Report or log an exception.
     */
    public function report(Throwable $e): void
    {
        // Let the mapper decide logging / reporting
        $this->exceptionMapper->map($e);
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @param  mixed  $request
     */
    public function render(
        $request,
        Throwable $e
    ): IlluminateResponse|JsonResponse {
        $renderableException = $this->exceptionMapper->map($e);

        // Get normalized payload from the exception
        $payloadArray = $renderableException->toErrorArray();

        $message = $payloadArray['message'] ?? 'Internal server error';
        $errors = $payloadArray['errors'] ?? [
            'public' => ['code' => 'server.error', 'message' => $message],
        ];

        $debug = $payloadArray['debug'] ?? [];
        if (! is_array($debug)) {
            $debug = [$debug];
        }

        // Normalize errors to always have nested structure
        if (! empty($errors) && array_is_list($errors) === false) {
            $normalizedErrors = [];
            foreach ($errors as $key => $value) {
                $normalizedErrors[$key] = is_array($value) ? $value : [
                    'code' => $key,
                    'message' => (string) $value,
                ];
            }
            $errors = $normalizedErrors;
        }

        // Create ResponsePayload directly (no static method)
        $responsePayload = new ResponsePayload(
            success: false,
            message: $message,
            data: [],
            errors: $errors,
            debug: $debug
        );

        return HttpResponse::fromPayload(
            $responsePayload,
            $renderableException->status()
        );
    }

    /**
     * Determine if the exception should be reported.
     */
    public function shouldReport(Throwable $e): bool
    {
        return true;
    }

    /**
     * Render an exception to the console.
     *
     * @param  mixed  $output
     */
    public function renderForConsole($output, Throwable $e): void
    {
        $output->writeln('<error>'.$e->getMessage().'</error>');

        if (config('app.debug')) {
            $output->writeln($e->getTraceAsString());
        }
    }
}
