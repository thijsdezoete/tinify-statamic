<?php

namespace Tinify\Statamic\Tests;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Statamic\Facades\AssetContainer;
use Statamic\Facades\User;
use Tinify\Statamic\Api\Client;
use Tinify\Statamic\Api\Optimized;
use Tinify\Statamic\Jobs\OptimizeAsset;

class CompressLibraryTest extends TestCase
{
    public function test_library_compression_requires_addon_settings_permission(): void
    {
        $this->actingAs(User::make()->id('reader')->email('reader@example.test'));
        Gate::before(fn ($user, $ability) => $ability === 'access cp' ? true : null);
        Queue::fake();

        $this->postJson(cp_route('tinify.compress-library'))->assertForbidden();

        Queue::assertNotPushed(OptimizeAsset::class);
    }

    public function test_library_compression_reaches_excluded_containers_but_respects_asset_permissions(): void
    {
        $pending = $this->makeAsset('pending.svg', $this->fixture('unoptimized.svg'));
        $marked = $this->makeAsset('marked.png');
        $marked->set('tinify', ['hash' => sha1($marked->contents())])->saveQuietly();
        $denied = $this->makeAsset('restricted.png');
        $this->makeAsset('unsupported.txt', 'Not an image.');

        Storage::fake('secondary');
        $container = AssetContainer::make('secondary')->disk('secondary')->title('Secondary');
        $container->save();
        $excluded = $container->makeAsset('other.png');
        $excluded->disk()->put($excluded->path(), $this->fixture('unoptimized.png'));
        $excluded->save();
        $this->configureSettings(['containers' => ['assets']]);

        $this->actingAs(User::make()->id('editor')->email('editor@example.test'));
        Gate::before(function ($user, $ability, $arguments) use ($denied) {
            if (in_array($ability, ['access cp', 'editSettings'], true)) {
                return true;
            }

            return $ability === 'edit' ? $arguments[0]->id() !== $denied->id() : null;
        });
        Queue::fake();

        $this->postJson(cp_route('tinify.compress-library'), ['force' => false])
            ->assertSuccessful()
            ->assertJsonPath('queued', 2)
            ->assertJsonPath('skipped', 1);

        Queue::assertPushed(OptimizeAsset::class, 2);
        Queue::assertPushed(OptimizeAsset::class, fn ($job) => $job->assetId === $pending->id());
        Queue::assertPushed(OptimizeAsset::class, fn ($job) => $job->assetId === $excluded->id());

        $client = Mockery::mock(Client::class);
        $client->shouldReceive('optimize')->once()
            ->andReturn(new Optimized($this->fixture('optimized.png'), 'image/png'));
        $this->app->instance(Client::class, $client);
        $job = Queue::pushed(OptimizeAsset::class, fn ($job) => $job->assetId === $excluded->id())->first();
        $job = unserialize(serialize($job));
        $this->app->call([$job, 'handle']);

        $this->assertSame($this->fixture('optimized.png'), $excluded->contents());
        $this->assertSame($this->fixture('unoptimized.png'), $denied->contents());
    }

    public function test_force_reprocesses_an_already_checked_asset(): void
    {
        $asset = $this->makeAsset('checked.png');
        $asset->set('tinify', ['hash' => sha1($asset->contents())])->saveQuietly();
        $this->actingAs(User::make()->id('admin')->email('admin@example.test')->makeSuper());
        Queue::fake();

        $this->postJson(cp_route('tinify.compress-library'), ['force' => true])
            ->assertSuccessful()
            ->assertJsonPath('queued', 1)
            ->assertJsonPath('skipped', 0);

        $client = Mockery::mock(Client::class);
        $client->shouldReceive('optimize')->once()
            ->andReturn(new Optimized($this->fixture('optimized.png'), 'image/png'));
        $this->app->instance(Client::class, $client);
        $job = Queue::pushed(OptimizeAsset::class)->first();
        $this->app->call([$job, 'handle']);

        $this->assertSame($this->fixture('optimized.png'), $asset->contents());
    }

    public function test_preexisting_queued_jobs_keep_their_container_restrictions(): void
    {
        $asset = $this->makeAsset();
        $this->configureSettings(['containers' => ['other']]);
        $client = Mockery::mock(Client::class);
        $client->shouldNotReceive('optimize');
        $this->app->instance(Client::class, $client);

        $job = new OptimizeAsset($asset->id());
        unset($job->ignoreContainerFilter);
        $job = unserialize(serialize($job));
        $this->app->call([$job, 'handle']);

        $this->assertSame($this->fixture('unoptimized.png'), $asset->contents());
    }

    public function test_invalid_force_and_missing_api_key_do_not_queue_work(): void
    {
        $this->makeAsset();
        $this->actingAs(User::make()->id('admin')->email('admin@example.test')->makeSuper());
        Queue::fake();

        $this->postJson(cp_route('tinify.compress-library'), ['force' => 'unexpected'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('force');

        config(['tinify.key' => null]);
        $this->configureSettings(['api_key' => '']);
        $this->postJson(cp_route('tinify.compress-library'), ['force' => false])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('api_key');

        Queue::assertNotPushed(OptimizeAsset::class);
    }
}
