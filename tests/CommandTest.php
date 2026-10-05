<?php

namespace Tinify\Statamic\Tests;

use Illuminate\Support\Facades\Queue;
use Mockery;
use Statamic\Facades\Asset;
use Tinify\Statamic\Api\Client;
use Tinify\Statamic\Api\Optimized;
use Tinify\Statamic\Jobs\OptimizeAsset;

class CommandTest extends TestCase
{
    public function test_command_queues_pending_images_and_force_includes_marked_images(): void
    {
        $pending = $this->makeAsset('pending.png');
        $marked = $this->makeAsset('marked.png', $this->fixture('optimized.png'));
        $marked->set('tinify', ['hash' => sha1($marked->contents())])->saveQuietly();
        $this->makeAsset('vector.svg', '<svg xmlns="http://www.w3.org/2000/svg"/>');
        Queue::fake();

        $this->artisan('statamic:tinify:optimize')->assertSuccessful();

        Queue::assertPushed(OptimizeAsset::class, 1);
        Queue::assertPushed(OptimizeAsset::class, fn ($job) => $job->assetId === $pending->id() && ! $job->force);
        Queue::fake();

        $this->artisan('statamic:tinify:optimize', ['--force' => true])->assertSuccessful();

        Queue::assertPushed(OptimizeAsset::class, 2);
        Queue::assertPushed(OptimizeAsset::class, fn ($job) => $job->assetId === $marked->id() && $job->force);
    }

    public function test_sync_command_rewrites_the_file_without_a_worker(): void
    {
        $asset = $this->makeAsset();
        $client = Mockery::mock(Client::class);
        $client->shouldReceive('optimize')->once()
            ->andReturn(new Optimized($this->fixture('optimized.png'), 'image/png'));
        $this->app->instance(Client::class, $client);

        $this->artisan('statamic:tinify:optimize', ['container' => 'assets', '--sync' => true])->assertSuccessful();

        $this->assertSame($this->fixture('optimized.png'), Asset::find($asset->id())->contents());
    }

    public function test_unknown_and_excluded_containers_do_not_queue_work(): void
    {
        $this->makeAsset();
        Queue::fake();
        $this->artisan('statamic:tinify:optimize', ['container' => 'missing'])->assertExitCode(1);
        $this->configureSettings(['containers' => ['other']]);
        $this->artisan('statamic:tinify:optimize', ['container' => 'assets', '--force' => true])->assertSuccessful();

        Queue::assertNotPushed(OptimizeAsset::class);
    }
}
