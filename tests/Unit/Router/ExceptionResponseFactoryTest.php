<?php

declare(strict_types=1);

namespace Zolta\Tests\Unit\Router;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Orchestra\Testbench\TestCase;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Zolta\Framework\FrameworkRegistry;
use Zolta\Http\Response\Laravel\LaravelResponseAdapter;
use Zolta\Http\Router\Laravel\Bootstrap\Response\ExceptionResponseFactory;
use Zolta\Http\Service\Laravel\Providers\ZoltaHttpServiceProvider;

final class ExceptionResponseFactoryTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [ZoltaHttpServiceProvider::class];
    }

    public function test_http_exceptions_preserve_their_status_and_public_message(): void
    {
        FrameworkRegistry::register(LaravelResponseAdapter::class);
        $request = Request::create('/api/jobs/missing', 'GET', server: [
            'HTTP_ACCEPT' => 'application/json',
        ]);
        $this->app->instance('request', $request);

        $response = $this->app->make(ExceptionResponseFactory::class)
            ->fromException(new NotFoundHttpException('Job not found.'));

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame('Job not found.', $response->getData(true)['message']);
        $this->assertSame('http.404', $response->getData(true)['errors']['public']['code']);
    }
}
