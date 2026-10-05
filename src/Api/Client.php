<?php

namespace Tinify\Statamic\Api;

use Tinify\AccountException;
use Tinify\Exception;
use Tinify\Source;
use Tinify\Statamic\Support\Settings;
use Tinify\Tinify;

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

    public function accountUsage(): array
    {
        $unavailable = [
            'keyValid' => false,
            'compressionCount' => null,
            'remainingCredits' => null,
        ];

        $key = $this->settings->apiKey();

        if (! $key) {
            return $unavailable;
        }

        try {
            $this->initialize();

            // The WordPress integration uses this endpoint for assigned credits.
            $response = Tinify::getClient()->request('get', '/keys/'.rawurlencode($key));

            return [
                'keyValid' => true,
                'compressionCount' => filter_var($response->headers['compression-count'] ?? null, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE),
                'remainingCredits' => filter_var($response->headers['compression-count-remaining'] ?? null, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE),
            ];
        } catch (Exception) {
            // Do not expose the key-bearing request URL or a stale balance.
            return $unavailable;
        }
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
