<?php

namespace Tinify\Statamic\Tests;

use Illuminate\Support\Facades\Queue;
use Mockery;
use Statamic\Events\GlideImageGenerated;
use Statamic\Facades\Glide;
use Tinify\ConnectionException;
use Tinify\Statamic\Api\Client;
use Tinify\Statamic\Api\Optimized;
use Tinify\Statamic\Jobs\OptimizeGlideImage;
use Tinify\Statamic\Support\Settings;

class GlideImageOptimizationTest extends TestCase
{
    public function test_generated_image_is_queued_and_compressed_without_changing_its_dimensions(): void
    {
        $disk = Glide::cacheDisk();
        $disk->put('generated/image.png', $this->fixture('unoptimized.png'));
        Queue::fake();

        event(new GlideImageGenerated('generated/image.png', ['w' => 16]));

        Queue::assertPushed(OptimizeGlideImage::class, fn ($job) => $job->path === 'generated/image.png');
        $this->assertSame($this->fixture('unoptimized.png'), $disk->get('generated/image.png'));

        $client = Mockery::mock(Client::class);
        $client->shouldReceive('optimize')->once()->with($this->fixture('unoptimized.png'))
            ->andReturn(new Optimized($this->fixture('optimized.png'), 'image/png'));

        (new OptimizeGlideImage('generated/image.png'))->handle($client, app(Settings::class));

        $this->assertSame($this->fixture('optimized.png'), $disk->get('generated/image.png'));
        $this->assertSame([16, 16], array_slice(getimagesizefromstring($disk->get('generated/image.png')), 0, 2));
        Queue::assertPushed(OptimizeGlideImage::class, 1);
    }

    public function test_disabled_glide_optimization_and_unsupported_formats_do_not_queue(): void
    {
        Queue::fake();
        $this->configureSettings(['optimize_glide' => false]);
        event(new GlideImageGenerated('generated/image.png', []));
        $this->configureSettings(['optimize_glide' => true]);
        event(new GlideImageGenerated('generated/animation.gif', []));
        event(new GlideImageGenerated('generated/document.pdf', []));

        Queue::assertNotPushed(OptimizeGlideImage::class);
    }

    public function test_larger_output_does_not_replace_the_cached_image(): void
    {
        $this->configureSettings(['optimize_glide' => true]);
        $disk = Glide::cacheDisk();
        $disk->put('image.png', $this->fixture('optimized.png'));
        $client = Mockery::mock(Client::class);
        $client->shouldReceive('optimize')->once()
            ->andReturn(new Optimized($this->fixture('unoptimized.png'), 'image/png'));

        (new OptimizeGlideImage('image.png'))->handle($client, app(Settings::class));

        $this->assertSame($this->fixture('optimized.png'), $disk->get('image.png'));
    }

    public function test_a_different_media_type_cannot_overwrite_the_existing_cache_url(): void
    {
        $this->configureSettings(['optimize_glide' => true]);
        $disk = Glide::cacheDisk();
        $disk->put('image.png', $this->fixture('unoptimized.png'));
        $client = Mockery::mock(Client::class);
        $client->shouldReceive('optimize')->once()
            ->andReturn(new Optimized($this->fixture('image.webp'), 'image/webp'));

        (new OptimizeGlideImage('image.png'))->handle($client, app(Settings::class));

        $this->assertSame($this->fixture('unoptimized.png'), $disk->get('image.png'));
    }

    public function test_a_cleared_cache_does_not_spend_an_api_request(): void
    {
        $this->configureSettings(['optimize_glide' => true]);
        $client = Mockery::mock(Client::class);
        $client->shouldNotReceive('optimize');

        (new OptimizeGlideImage('cleared.png'))->handle($client, app(Settings::class));

        $this->assertFalse(Glide::cacheDisk()->exists('cleared.png'));
    }

    public function test_a_connection_failure_releases_the_job_without_altering_the_cache(): void
    {
        $this->configureSettings(['optimize_glide' => true]);
        $disk = Glide::cacheDisk();
        $disk->put('image.png', $this->fixture('unoptimized.png'));
        $client = Mockery::mock(Client::class);
        $client->shouldReceive('optimize')->once()->andThrow(new ConnectionException('Offline'));
        $job = (new OptimizeGlideImage('image.png'))->withFakeQueueInteractions();

        $job->handle($client, app(Settings::class));

        $job->assertReleased(10);
        $this->assertSame($this->fixture('unoptimized.png'), $disk->get('image.png'));
    }
}
