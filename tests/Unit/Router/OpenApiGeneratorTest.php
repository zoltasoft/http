<?php

declare(strict_types=1);

namespace Zolta\Tests\Unit\Router;

use PHPUnit\Framework\TestCase;
use Zolta\Http\Request\Attributes\Request as RequestAttr;
use Zolta\Http\Response\Attributes\Response as ResponseAttr;
use Zolta\Http\Router\Attributes\Route;
use Zolta\Http\Router\Laravel\Bootstrap\OpenApiGenerator;
use Zolta\Http\Service\Attributes\Doc;
use Zolta\Http\Service\Attributes\Service;

final class OpenApiGeneratorTest extends TestCase
{
    public function test_it_generates_request_body_for_class_level_controller_without_internal_fields(): void
    {
        $fragment = OpenApiGenerator::generateForController(OpenApiBodyController::class);

        $this->assertCount(1, $fragment['operations']);
        $operation = $fragment['operations'][0];

        $this->assertSame('/api/interview/sessions', $operation['path']);
        $this->assertSame('post', $operation['method']);
        $this->assertSame('Start interview session', $operation['operation']['summary']);
        $this->assertSame([['bearerAuth' => []]], $operation['operation']['security']);

        $requestSchema = $operation['operation']['requestBody']['content']['application/json']['schema'];
        $this->assertArrayHasKey('title', $requestSchema['properties']);
        $this->assertArrayNotHasKey('user_id', $requestSchema['properties']);
        $this->assertContains('title', $requestSchema['required']);

        $responses = $operation['operation']['responses'];
        $this->assertArrayHasKey('201', $responses);
        $this->assertSame(
            '#/components/schemas/OpenApiBodyResource',
            $responses['201']['content']['application/json']['schema']['properties']['data']['$ref'],
        );
        $this->assertArrayHasKey('OpenApiBodyResource', $fragment['schemas']);
    }

    public function test_it_infers_query_parameters_from_input_mapped_rules_for_safe_methods(): void
    {
        $fragment = OpenApiGenerator::generateForController(OpenApiQueryController::class);

        $this->assertCount(1, $fragment['operations']);
        $operation = $fragment['operations'][0]['operation'];

        $parameters = $operation['parameters'];
        $names = array_map(static fn (array $parameter): string => $parameter['name'], $parameters);

        $this->assertContains('filter', $names);
        $this->assertContains('page', $names);
        $this->assertNotContains('user_id', $names);
        $this->assertNotContains('options', $names);
        $this->assertArrayNotHasKey('requestBody', $operation);
    }

    public function test_it_uses_explicit_route_parameter_configuration_for_method_level_routes(): void
    {
        $fragment = OpenApiGenerator::generateForController(OpenApiPathController::class);

        $this->assertCount(1, $fragment['operations']);
        $operation = $fragment['operations'][0];

        $this->assertSame('/api/roles/{id}', $operation['path']);
        $this->assertSame('get', $operation['method']);
        $this->assertSame('Get role by id', $operation['operation']['summary']);

        $parameters = $operation['operation']['parameters'];
        $this->assertCount(1, $parameters);
        $this->assertSame('id', $parameters[0]['name']);
        $this->assertSame('path', $parameters[0]['in']);
        $this->assertSame('string', $parameters[0]['schema']['type']);
    }
}

final class OpenApiBodyRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'user_id' => (string) $this->user()?->getAuthIdentifier(),
        ]);
    }

    public function rules(): array
    {
        return [
            'user_id' => 'required|uuid',
            'title' => 'required|string|max:255',
            'language' => 'nullable|string|max:100',
            'metadata' => 'sometimes|array',
        ];
    }
}

final class OpenApiBodyResource
{
    public function __construct(
        public string $id,
        public string $title,
    ) {}
}

#[Route(path: 'interview/sessions', methods: ['POST'], middleware: ['api', 'auth:sanctum'], name: 'interview.sessions.store')]
#[RequestAttr(OpenApiBodyRequest::class)]
#[Service(OpenApiBodyService::class, 'Interview session started successfully.', 201)]
#[ResponseAttr(OpenApiBodyResource::class)]
#[Doc(summary: 'Start interview session', description: 'Create and start an interview session.', tags: ['Interview Sessions'])]
final class OpenApiBodyController
{
    public function __invoke(): void {}
}

final class OpenApiBodyService {}

final class OpenApiQueryRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'user_id' => (string) $this->user()?->getAuthIdentifier(),
            'filter' => $this->input('filter', []),
            'page' => $this->input('page'),
            'options' => array_filter([
                'filter' => $this->input('filter', []),
                'page' => $this->input('page'),
            ]),
        ]);
    }

    public function rules(): array
    {
        return [
            'user_id' => 'required|uuid',
            'filter' => 'sometimes|array',
            'page' => 'sometimes|integer|min:1',
            'options' => 'sometimes|array',
        ];
    }
}

final class OpenApiQueryResource
{
    public function __construct(public array $items = []) {}
}

final class OpenApiQueryController
{
    #[Route(path: 'reports', methods: ['GET'], middleware: ['api'], name: 'reports.index')]
    #[RequestAttr(OpenApiQueryRequest::class)]
    #[Service(OpenApiQueryService::class, 'Reports retrieved successfully.', 200)]
    #[ResponseAttr(OpenApiQueryResource::class)]
    #[Doc(summary: 'List reports', description: 'List reports with pagination.', tags: ['Reports'])]
    public function __invoke(): void {}
}

final class OpenApiQueryService {}

final class OpenApiPathRequest
{
    public function routeParams(): array
    {
        return [
            'id' => [
                'type' => 'string',
                'required' => true,
            ],
        ];
    }

    public function rules(): array
    {
        return [
            'id' => 'required|string',
        ];
    }
}

final class OpenApiPathResource
{
    public function __construct(public string $id) {}
}

final class OpenApiPathController
{
    #[Route(path: 'roles/{id}', methods: ['GET'], middleware: ['api'], name: 'roles.show')]
    #[RequestAttr(OpenApiPathRequest::class)]
    #[Service(OpenApiPathService::class, 'Role retrieved successfully.', 200)]
    #[ResponseAttr(OpenApiPathResource::class)]
    #[Doc(summary: 'Get role by id', description: 'Retrieve one role.', tags: ['Roles'])]
    public function __invoke(): void {}
}

final class OpenApiPathService {}
