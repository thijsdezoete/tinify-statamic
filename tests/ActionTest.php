<?php

namespace Tinify\Statamic\Tests;

use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Mockery;
use Statamic\Facades\AssetContainer;
use Statamic\Facades\User;
use Tinify\Statamic\Actions\CreateThumbnail;
use Tinify\Statamic\Actions\Optimize;
use Tinify\Statamic\Api\Client;
use Tinify\Statamic\Api\Optimized;
use Tinify\Statamic\Jobs\OptimizeAsset;

class ActionTest extends TestCase
{
    public function test_actions_are_available_only_for_supported_assets_and_require_edit_permission(): void
    {
        $asset = $this->makeAsset();
        $container = $asset->container();
        $user = User::make()->id('reader')->email('reader@example.test');
        $admin = User::make()->id('admin')->email('admin@example.test')->makeSuper();

        foreach ([new Optimize, new CreateThumbnail] as $action) {
            $this->assertTrue($action->visibleTo($asset));
            $this->assertFalse($action->visibleTo($container->makeAsset('vector.svg')));
            $this->assertFalse($action->visibleTo($container->makeAsset('movie.mp4')));
            $this->assertFalse($action->authorize($user, $asset));
            $this->assertTrue($action->authorize($admin, $asset));
        }
    }

    public function test_bulk_optimization_queues_each_selected_image_with_force(): void
    {
        $first = $this->makeAsset('first.png');
        $second = $this->makeAsset('second.png');
        Queue::fake();

        (new Optimize)->run(collect([$first, $second]), ['force' => true]);

        Queue::assertPushed(OptimizeAsset::class, 2);
        Queue::assertPushed(OptimizeAsset::class, fn ($job) => $job->assetId === $first->id() && $job->force);
        Queue::assertPushed(OptimizeAsset::class, fn ($job) => $job->assetId === $second->id() && $job->force);
    }

    public function test_thumbnail_creation_preserves_source_and_uniquifies_siblings(): void
    {
        $source = $this->makeAsset('photos/source.png');
        $client = Mockery::mock(Client::class);
        $client->shouldReceive('optimize')->twice()
            ->andReturn(new Optimized($this->fixture('optimized.png'), 'image/png'));
        $this->app->instance(Client::class, $client);
        $action = new CreateThumbnail;
        $values = ['width' => 16, 'height' => 16, 'method' => 'thumb'];

        $action->run(collect([$source]), $values);
        $action->run(collect([$source]), $values);

        $this->assertSame($this->fixture('unoptimized.png'), $source->contents());
        foreach (['photos/source-16x16.png', 'photos/source-16x16-1.png'] as $path) {
            $thumbnail = AssetContainer::find('assets')->asset($path);
            $this->assertSame($this->fixture('optimized.png'), $thumbnail->contents());
            $this->assertSame(sha1($thumbnail->contents()), $thumbnail->get('tinify')['hash']);
            $this->assertSame([16, 16], $thumbnail->dimensions());
        }
    }

    public function test_invalid_thumbnail_dimensions_do_not_spend_an_api_request(): void
    {
        $source = $this->makeAsset();
        $client = Mockery::mock(Client::class);
        $client->shouldNotReceive('optimize');
        $this->app->instance(Client::class, $client);
        $this->expectException(ValidationException::class);

        (new CreateThumbnail)->run(collect([$source]), ['width' => 0, 'height' => 16, 'method' => 'thumb']);
    }
}
