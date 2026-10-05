<?php

namespace Tinify\Statamic\Api;

use UnexpectedValueException;

class Optimized
{
    public function __construct(
        public readonly string $bytes,
        public readonly string $mediaType,
    ) {}

    public function extension(): string
    {
        return match ($this->mediaType) {
            'image/png' => 'png',
            'image/jpeg' => 'jpg',
            'image/webp' => 'webp',
            'image/avif' => 'avif',
            default => throw new UnexpectedValueException("Unsupported Tinify media type [{$this->mediaType}]."),
        };
    }
}
