<?php

namespace ThijsDeZoete\TinifyStatamic\Tests;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Mockery\MockInterface;
use Statamic\Events\AssetReplaced;
use Statamic\Events\AssetReuploaded;
use Statamic\Facades\Asset;
use Statamic\Facades\YAML;
use Tinify\AccountException;
use ThijsDeZoete\TinifyStatamic\Api\Client;
use ThijsDeZoete\TinifyStatamic\Api\Optimized;
use ThijsDeZoete\TinifyStatamic\Jobs\OptimizeAsset;

class OptimizeAssetTest extends TestCase
{
    private function bindFakeClient(callable $optimize): MockInterface
    {
        $client = Mockery::mock(Client::class);
        $client->shouldReceive('optimize')->andReturnUsing($optimize);
        $this->app->instance(Client::class, $client);

        return $client;
    }

    private function assetMeta(string $metaPath): array
    {
        return YAML::parse(Storage::disk('assets')->get($metaPath));
    }

    public function test_in_place_optimization_replaces_bytes_and_refreshes_meta()
    {
        $asset = $this->makeAsset('photo.png');
        $originalBytes = $asset->contents();
        $optimizedBytes = $this->fixture('optimized.png');
        $this->assertLessThan(strlen($originalBytes), strlen($optimizedBytes));

        $calls = 0;
        $this->bindFakeClient(function () use (&$calls, $optimizedBytes) {
            $calls++;

            return new Optimized($optimizedBytes, 'image/png');
        });

        $reuploaded = [];
        $this->app['events']->listen(AssetReuploaded::class, function ($event) use (&$reuploaded) {
            $reuploaded[] = $event;
        });

        OptimizeAsset::dispatch($asset->id());

        // One API call only: the re-entrant job fired by the real AssetReuploaded
        // listener must be stopped by the persisted hash before touching the client.
        $this->assertSame(1, $calls);
        $this->assertCount(1, $reuploaded);
        $this->assertSame($asset->id(), $reuploaded[0]->asset->id());

        $this->assertSame($optimizedBytes, Storage::disk('assets')->get('photo.png'));

        $meta = $this->assetMeta($asset->metaPath());
        $this->assertSame(strlen($optimizedBytes), $meta['size']);
        $this->assertSame(sha1($optimizedBytes), $meta['data']['tinify']['hash']);
        $this->assertSame(strlen($originalBytes), $meta['data']['tinify']['original_size']);
        $this->assertIsInt($meta['data']['tinify']['optimized_at']);

        $refreshed = Asset::find($asset->id());
        $this->assertSame(strlen($optimizedBytes), $refreshed->size());
        $this->assertSame(sha1($optimizedBytes), $refreshed->get('tinify')['hash']);
    }

    public function test_second_run_with_unchanged_file_makes_no_api_call()
    {
        $asset = $this->makeAsset('photo.png');
        $contents = $asset->contents();
        $asset->set('tinify', ['hash' => sha1($contents), 'size' => strlen($contents)])->saveQuietly();

        $client = Mockery::mock(Client::class);
        $client->shouldNotReceive('optimize');
        $this->app->instance(Client::class, $client);

        OptimizeAsset::dispatch($asset->id());

        $this->assertSame($contents, Storage::disk('assets')->get('photo.png'));
    }

    public function test_conversion_replaces_asset_with_new_extension_copying_data()
    {
        $this->configureSettings(['convert' => 'webp']);

        $asset = $this->makeAsset('photo.png', $this->fixture('unoptimized.png'), ['alt' => 'A nice photo']);
        $webpBytes = $this->fixture('image.webp');

        $this->bindFakeClient(fn () => new Optimized($webpBytes, 'image/webp'));

        $replaced = [];
        $this->app['events']->listen(AssetReplaced::class, function ($event) use (&$replaced) {
            $replaced[] = $event;
        });

        OptimizeAsset::dispatch($asset->id());

        $this->assertCount(1, $replaced);
        $this->assertSame('photo.png', $replaced[0]->originalAsset->path());
        $this->assertSame('photo.webp', $replaced[0]->newAsset->path());

        $this->assertNull(Asset::find($asset->id()));
        $this->assertFalse(Storage::disk('assets')->exists('photo.png'));

        $converted = Asset::find('assets::photo.webp');
        $this->assertNotNull($converted);
        $this->assertSame($webpBytes, $converted->contents());
        $this->assertSame('A nice photo', $converted->get('alt'));
        $this->assertSame(sha1($webpBytes), $converted->get('tinify')['hash']);
        $this->assertSame(strlen($this->fixture('unoptimized.png')), $converted->get('tinify')['original_size']);
    }

    public function test_conversion_uniquifies_an_existing_destination_preserving_the_folder()
    {
        $this->configureSettings(['convert' => 'webp']);

        $source = $this->makeAsset('0/photo.png', $this->fixture('unoptimized.png'));
        $this->makeAsset('0/photo.webp', $this->fixture('image.webp'));

        $convertedBytes = $this->fixture('image.webp');
        $this->bindFakeClient(fn () => new Optimized($convertedBytes, 'image/webp'));

        $replaced = [];
        $this->app['events']->listen(AssetReplaced::class, function ($event) use (&$replaced) {
            $replaced[] = [$event->originalAsset->path(), $event->newAsset->path()];
        });

        OptimizeAsset::dispatch($source->id());

        $this->assertSame([['0/photo.png', '0/photo-1.webp']], $replaced);
        $this->assertNull(Asset::find('assets::0/photo.png'));

        $destination = Asset::find('assets::0/photo.webp');
        $this->assertNotNull($destination);
        $this->assertSame($this->fixture('image.webp'), $destination->contents());

        $moved = Asset::find('assets::0/photo-1.webp');
        $this->assertNotNull($moved);
        $this->assertSame('0/photo-1.webp', $moved->path());
        $this->assertSame($convertedBytes, $moved->contents());
        $this->assertSame(sha1($convertedBytes), $moved->get('tinify')['hash']);
    }

    public function test_output_larger_than_input_keeps_original_bytes_and_records_marker()
    {
        $asset = $this->makeAsset('photo.png');
        $originalBytes = $asset->contents();
        $largerBytes = str_repeat('x', strlen($originalBytes) + 100);

        $calls = 0;
        $this->bindFakeClient(function () use (&$calls, $largerBytes) {
            $calls++;

            return new Optimized($largerBytes, 'image/png');
        });

        $reuploaded = 0;
        $this->app['events']->listen(AssetReuploaded::class, function () use (&$reuploaded) {
            $reuploaded++;
        });

        OptimizeAsset::dispatch($asset->id());

        $this->assertSame(1, $calls);
        $this->assertSame(0, $reuploaded, 'A larger result must not rewrite the file.');

        $this->assertSame($originalBytes, Storage::disk('assets')->get('photo.png'));

        $meta = $this->assetMeta($asset->metaPath());
        $this->assertSame(sha1($originalBytes), $meta['data']['tinify']['hash']);
        $this->assertSame(strlen($originalBytes), $meta['data']['tinify']['size']);
        $this->assertSame(strlen($originalBytes), $meta['data']['tinify']['original_size']);

        // The marker marks the asset as checked: a second run must not query again.
        OptimizeAsset::dispatch($asset->id());
        $this->assertSame(1, $calls);
    }

    public function test_changed_file_is_optimized_again()
    {
        $asset = $this->makeAsset('photo.png');

        $first = $this->fixture('optimized.png');
        $responses = [
            new Optimized($first, 'image/png'),
            new Optimized($first, 'image/png'),
        ];
        $calls = 0;
        $this->bindFakeClient(function () use (&$calls, &$responses) {
            $calls++;

            return array_shift($responses);
        });

        OptimizeAsset::dispatch($asset->id());
        $this->assertSame(1, $calls);
        $this->assertSame($first, Storage::disk('assets')->get('photo.png'));

        // External change: a file arriving with new bytes must be optimized again.
        Storage::disk('assets')->put('photo.png', $changed = $this->fixture('unoptimized.png'));

        OptimizeAsset::dispatch($asset->id());

        $this->assertSame(2, $calls);
        $this->assertSame($first, Storage::disk('assets')->get('photo.png'));
        $refreshed = Asset::find($asset->id());
        $this->assertSame(sha1($first), $refreshed->get('tinify')['hash']);
        $this->assertSame(strlen($changed), $refreshed->get('tinify')['original_size']);
    }

    public function test_account_exception_leaves_asset_untouched_and_does_not_retry()
    {
        $asset = $this->makeAsset('photo.png');
        $originalBytes = $asset->contents();

        $calls = 0;
        $this->bindFakeClient(function () use (&$calls) {
            $calls++;

            throw new AccountException('No credit left');
        });

        Log::spy();

        $reuploaded = 0;
        $this->app['events']->listen(AssetReuploaded::class, function () use (&$reuploaded) {
            $reuploaded++;
        });

        $job = (new OptimizeAsset($asset->id()))->withFakeQueueInteractions();
        $this->app->call([$job, 'handle']);
        $job->assertNotReleased();

        Log::shouldHaveReceived('warning')->once();
        $this->assertSame(1, $calls, 'Account errors must not be retried.');
        $this->assertSame(0, $reuploaded);
        $this->assertSame($originalBytes, Storage::disk('assets')->get('photo.png'));

        $meta = $this->assetMeta($asset->metaPath());
        $this->assertArrayNotHasKey('tinify', $meta['data'] ?? []);
    }
}
