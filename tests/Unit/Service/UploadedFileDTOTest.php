<?php

declare(strict_types=1);

namespace Zolta\Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use Zolta\Http\Service\DTO\UploadedFileDTO;

final class UploadedFileDTOTest extends TestCase
{
    public function test_constructs_with_all_fields(): void
    {
        $dto = new UploadedFileDTO(
            clientOriginalName: 'photo.jpg',
            clientMimeType: 'image/jpeg',
            size: 204800,
            tmpPath: '/tmp/php_upload_123',
            error: null,
        );

        $this->assertSame('photo.jpg', $dto->clientOriginalName);
        $this->assertSame('image/jpeg', $dto->clientMimeType);
        $this->assertSame(204800, $dto->size);
        $this->assertSame('/tmp/php_upload_123', $dto->tmpPath);
        $this->assertNull($dto->error);
    }

    public function test_constructs_with_only_required_field(): void
    {
        $dto = new UploadedFileDTO(clientOriginalName: 'document.pdf');

        $this->assertSame('document.pdf', $dto->clientOriginalName);
        $this->assertNull($dto->clientMimeType);
        $this->assertNull($dto->size);
        $this->assertNull($dto->tmpPath);
        $this->assertNull($dto->error);
    }

    public function test_to_array_returns_all_fields(): void
    {
        $dto = new UploadedFileDTO(
            clientOriginalName: 'report.xlsx',
            clientMimeType: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            size: 51200,
            tmpPath: '/tmp/php_upload_456',
            error: null,
        );

        $array = $dto->toArray();

        $this->assertSame('report.xlsx', $array['clientOriginalName']);
        $this->assertSame(51200, $array['size']);
        $this->assertArrayHasKey('clientMimeType', $array);
        $this->assertArrayHasKey('tmpPath', $array);
        $this->assertArrayHasKey('error', $array);
    }

    public function test_to_array_with_error(): void
    {
        $dto = new UploadedFileDTO(
            clientOriginalName: 'broken.zip',
            error: 'Upload failed: file too large',
        );

        $array = $dto->toArray();

        $this->assertSame('Upload failed: file too large', $array['error']);
        $this->assertNull($array['size']);
    }

    public function test_is_readonly(): void
    {
        $ref = new \ReflectionClass(UploadedFileDTO::class);

        $this->assertTrue($ref->isReadOnly());
    }
}
