<?php

declare(strict_types=1);

namespace Zolta\Http\Service\DTO;

/**
 * Neutral representation of an uploaded file.
 */
final readonly class UploadedFileDTO
{
    public function __construct(
        public string $clientOriginalName,
        public ?string $clientMimeType = null,
        public ?int $size = null,
        public ?string $tmpPath = null,
        public ?string $error = null
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'clientOriginalName' => $this->clientOriginalName,
            'clientMimeType' => $this->clientMimeType,
            'size' => $this->size,
            'tmpPath' => $this->tmpPath,
            'error' => $this->error,
        ];
    }
}
