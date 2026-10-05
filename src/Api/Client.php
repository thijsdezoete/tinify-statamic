<?php

namespace Tinify\Statamic\Api;

use Tinify\AccountException;
use Tinify\Source;
use Tinify\Statamic\Support\Settings;

class Client
{
    private bool $initialized = false;

    public function __construct(private Settings $settings) {}

    public function optimize(string $bytes, ?array $resize = null, ?array $convert = null, array $preserve = []): Optimized
    {
        $this->initialize();

        $source = Source::fromBuffer($bytes);

        if ($resize !== null) {
            $source = $source->resize($resize);
        }

        if ($convert !== null) {
            $source = $source->convert(['type' => $convert]);
        }

        if ($preserve !== []) {
            $source = $source->preserve(...$preserve);
        }

        $result = $source->result();

        return new Optimized($result->toBuffer(), $result->mediaType());
    }

    public function validate(): bool
    {
        $this->initialize();

        return (bool) \Tinify\validate();
    }

    public function compressionCount(): ?int
    {
        return \Tinify\compressionCount();
    }

    private function initialize(): void
    {
        if ($this->initialized) {
            return;
        }

        $key = $this->settings->apiKey();

        if (! $key) {
            throw new AccountException('No Tinify API key configured.');
        }

        \Tinify\setKey($key);
        $this->initialized = true;
    }
}
