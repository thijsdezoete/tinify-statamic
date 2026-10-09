<?php

namespace ThijsDeZoete\TinifyStatamic\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Statamic\Assets\ReplacementFile;
use Statamic\Facades\Asset;
use Statamic\Support\Svg;
use Tinify\AccountException;
use Tinify\ClientException;
use Tinify\ConnectionException;
use Tinify\ServerException;
use ThijsDeZoete\TinifyStatamic\Api\Client;
use ThijsDeZoete\TinifyStatamic\Support\Images;
use ThijsDeZoete\TinifyStatamic\Support\Settings;

class OptimizeAsset implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    public bool $ignoreContainerFilter = false;

    /**
     * False for upload-triggered jobs: converting changes the asset path and deletes the original,
     * which breaks unsaved entries still holding the uploaded path.
     */
    public bool $convert = true;

    // Non-promoted so jobs serialized before these flags existed unserialize with the defaults above.
    public function __construct(public string $assetId, public bool $force = false, bool $ignoreContainerFilter = false, bool $convert = true)
    {
        $this->ignoreContainerFilter = $ignoreContainerFilter;
        $this->convert = $convert;
    }

    public function backoff(): array
    {
        return [10, 60, 180];
    }

    public function handle(Client $client, Settings $settings): void
    {
        $asset = Asset::find($this->assetId);

        if (! $asset || ! $asset->extensionIsOneOf(Images::EXTENSIONS)) {
            return;
        }

        if (! $this->ignoreContainerFilter && ! $settings->appliesTo($asset->container())) {
            return;
        }

        if (! $settings->apiKey()) {
            if (Cache::add('tinify.missing-key-notice', true, 3600)) {
                Log::notice('[tinify] Set TINIFY_API_KEY or an API key in addon settings to optimize images.');
            }

            return;
        }

        $bytes = $asset->contents();
        $hash = sha1($bytes);

        if (! $this->force && $hash === ($asset->get('tinify')['hash'] ?? null)) {
            return;
        }

        $isSvg = $asset->isSvg();
        $convert = $isSvg || ! $this->convert ? null : $settings->convertTypes();
        $mediaType = $asset->mimeType();

        if ($convert === [$mediaType]) {
            $convert = null;
        }

        try {
            $result = $client->optimize($bytes, convert: $convert, preserve: $isSvg ? [] : $settings->preserve());
            $optimizedBytes = $result->bytes;

            if ($isSvg && config('statamic.assets.svg_sanitization_on_upload', true)) {
                // Match reupload's sanitization before hashing to prevent a second paid request.
                $optimizedBytes = Svg::sanitize($optimizedBytes);
            }

            $stats = [
                'hash' => sha1($optimizedBytes),
                'optimized_at' => now()->timestamp,
                'original_size' => strlen($bytes),
                'size' => strlen($optimizedBytes),
            ];

            if ($convert === null && strlen($optimizedBytes) >= strlen($bytes)) {
                $stats['hash'] = $hash;
                $stats['size'] = strlen($bytes);
                $asset->set('tinify', $stats)->saveQuietly();

                return;
            }

            if ($result->mediaType === $mediaType) {
                // Persist before reupload: its synchronous event may dispatch this job again.
                $asset->set('tinify', $stats)->saveQuietly();
                $disk = Storage::disk(config('statamic.system.file_uploads_disk', 'local'));
                $path = 'statamic/tinify/'.Str::uuid().'.'.$asset->extension();

                try {
                    if (! $disk->put($path, $optimizedBytes)) {
                        throw new RuntimeException('Could not write the Tinify replacement file.');
                    }

                    $asset->reupload(new ReplacementFile($path));
                } finally {
                    $disk->delete($path);
                }

                return;
            }

            $path = Images::uniqueSiblingPath($asset, $asset->filename(), $result->extension());
            $new = $asset->container()->makeAsset($path);
            if (! $new->disk()->put($path, $optimizedBytes)) {
                throw new RuntimeException('Could not write the converted Tinify asset.');
            }
            $new->data($asset->data()->all());
            $new->set('tinify', $stats);
            if (! $new->save()) {
                throw new RuntimeException('Could not save the converted Tinify asset.');
            }
            $new->replace($asset, true);
        } catch (AccountException|ClientException $exception) {
            Log::warning('[tinify] '.$exception->getMessage(), ['asset' => $this->assetId]);
        } catch (ConnectionException|ServerException $exception) {
            Log::warning('[tinify] '.$exception->getMessage(), ['asset' => $this->assetId]);
            $this->release($this->backoff()[$this->attempts() - 1] ?? 180);
        }
    }
}
