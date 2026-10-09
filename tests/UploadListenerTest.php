<?php

namespace ThijsDeZoete\TinifyStatamic\Tests;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Statamic\Contracts\Assets\Asset;
use Statamic\Facades\AssetContainer;
use Statamic\Support\Svg;
use ThijsDeZoete\TinifyStatamic\Api\Client;
use ThijsDeZoete\TinifyStatamic\Api\Optimized;
use ThijsDeZoete\TinifyStatamic\Jobs\OptimizeAsset;

class UploadListenerTest extends TestCase
{
    private function upload(string $path, ?string $content = null): Asset
    {
        $asset = AssetContainer::find('assets')->makeAsset($path);

        $file = $content === null
            ? UploadedFile::fake()->image($path, 16, 16)
            : UploadedFile::fake()->createWithContent($path, $content);

        $asset->upload($file);

        return $asset;
    }

    public function test_uploading_a_png_queues_an_optimization_job()
    {
        Queue::fake();

        $png = $this->upload('photo.png');

        $this->assertSame('assets::photo.png', $png->id());
        Queue::assertPushed(OptimizeAsset::class, fn ($job) => $job->assetId === 'assets::photo.png');
    }

    public function test_svg_upload_is_compressed_without_raster_transforms_or_repeat_requests(): void
    {
        $this->configureSettings(['convert' => 'webp', 'preserve' => ['copyright']]);
        $client = Mockery::mock(Client::class);
        $client->shouldReceive('optimize')->once()
            ->withArgs(fn ($bytes, $resize = null, $convert = null, $preserve = []) => $resize === null && $convert === null && $preserve === [])
            ->andReturn(new Optimized($this->fixture('optimized.svg'), 'image/svg+xml'));
        $this->app->instance(Client::class, $client);

        $asset = $this->upload('vector.svg', $this->fixture('unoptimized.svg'));
        $contents = Storage::disk('assets')->get('vector.svg');
        $optimized = \Statamic\Facades\Asset::find($asset->id());
        $stats = $optimized->get('tinify');

        $this->assertSame(Svg::sanitize($this->fixture('optimized.svg')), $contents);
        $this->assertEquals([16, 16], $optimized->dimensions());
        $this->assertSame('image/svg+xml', $optimized->mimeType());
        $this->assertSame(sha1($contents), $stats['hash']);
        $this->assertSame(strlen($contents), $stats['size']);
        $this->assertLessThan($stats['original_size'], $stats['size']);

        OptimizeAsset::dispatch($asset->id());
    }

    public function test_uploading_with_optimization_disabled_queues_nothing()
    {
        Queue::fake();
        $this->configureSettings(['optimize_on_upload' => false]);

        $this->upload('photo.png');

        Queue::assertNotPushed(OptimizeAsset::class);
    }

    public function test_uploading_unsupported_formats_queues_nothing()
    {
        Queue::fake();

        $this->upload('photo.gif');

        Queue::assertNotPushed(OptimizeAsset::class);
    }

    public function test_upload_triggered_jobs_compress_in_place_and_never_convert(): void
    {
        $this->configureSettings(['convert' => 'webp']);
        $client = Mockery::mock(Client::class);
        $client->shouldReceive('optimize')->once()
            ->withArgs(fn ($bytes, $resize = null, $convert = null) => $convert === null)
            ->andReturn(new Optimized($this->fixture('optimized.png'), 'image/png'));
        $this->app->instance(Client::class, $client);

        $png = $this->upload('photo.png', $this->fixture('unoptimized.png'));

        $this->assertSame($this->fixture('optimized.png'), Storage::disk('assets')->get('photo.png'));
        $this->assertFalse(Storage::disk('assets')->exists('photo.webp'));
        $this->assertNotNull(\Statamic\Facades\Asset::find('assets::photo.png'));
    }
}
