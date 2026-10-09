<?php

namespace ThijsDeZoete\TinifyStatamic\Jobs;

use finfo;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Statamic\Facades\Glide;
use Tinify\AccountException;
use Tinify\ClientException;
use Tinify\ConnectionException;
use Tinify\ServerException;
use ThijsDeZoete\TinifyStatamic\Api\Client;
use ThijsDeZoete\TinifyStatamic\Support\Images;
use ThijsDeZoete\TinifyStatamic\Support\Settings;

class OptimizeGlideImage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    public function __construct(public string $path) {}

    public function backoff(): array
    {
        return [10, 60, 180];
    }

    public function handle(Client $client, Settings $settings): void
    {
        if (! $settings->optimizeGlide()
            || ! in_array(strtolower(pathinfo($this->path, PATHINFO_EXTENSION)), Images::EXTENSIONS, true)) {
            return;
        }

        $disk = Glide::cacheDisk();

        if (! $disk->exists($this->path)) {
            return;
        }

        if (! $settings->apiKey()) {
            if (Cache::add('tinify.missing-key-notice', true, 3600)) {
                Log::notice('[tinify] Set TINIFY_API_KEY or an API key in addon settings to optimize images.');
            }

            return;
        }

        $bytes = $disk->get($this->path);

        try {
            $result = $client->optimize($bytes);

            if (strlen($result->bytes) < strlen($bytes)
                && $result->mediaType === (new finfo(FILEINFO_MIME_TYPE))->buffer($bytes)) {
                $disk->put($this->path, $result->bytes);
            }
        } catch (AccountException|ClientException $exception) {
            Log::warning('[tinify] '.$exception->getMessage(), ['glide_path' => $this->path]);
        } catch (ConnectionException|ServerException $exception) {
            Log::warning('[tinify] '.$exception->getMessage(), ['glide_path' => $this->path]);
            $this->release($this->backoff()[$this->attempts() - 1] ?? 180);
        }
    }
}
