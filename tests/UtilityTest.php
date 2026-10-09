<?php

namespace ThijsDeZoete\TinifyStatamic\Tests;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;
use Statamic\Facades\User;
use ThijsDeZoete\TinifyStatamic\Http\Controllers\OptimizeAllController;
use ThijsDeZoete\TinifyStatamic\Jobs\OptimizeAsset;
use ThijsDeZoete\TinifyStatamic\Support\Settings;

class UtilityTest extends TestCase
{
    public function test_utility_routes_require_the_utility_permission(): void
    {
        $user = User::make()->id('reader')->email('reader@example.test');
        Gate::before(fn ($user, $ability) => $ability === 'access cp' ? true : null);
        $this->actingAs($user);
        Queue::fake();

        $this->getJson(cp_route('utilities.tinify'))->assertForbidden();
        $this->postJson(cp_route('utilities.tinify.optimize'))->assertForbidden();

        Queue::assertNotPushed(OptimizeAsset::class);
    }

    public function test_bulk_endpoint_only_queues_pending_images_the_user_can_edit(): void
    {
        $pending = $this->makeAsset('pending.png');
        $marked = $this->makeAsset('marked.png');
        $marked->set('tinify', ['hash' => sha1($marked->contents())])->saveQuietly();
        $request = Request::create('/cp/utilities/tinify', 'POST');
        $request->setUserResolver(fn () => User::make()->id('reader')->email('reader@example.test'));
        Queue::fake();

        app(OptimizeAllController::class)($request, app(Settings::class));
        Queue::assertNotPushed(OptimizeAsset::class);

        $request->setUserResolver(fn () => User::make()->id('admin')->email('admin@example.test')->makeSuper());
        app(OptimizeAllController::class)($request, app(Settings::class));
        Queue::assertPushed(OptimizeAsset::class, 1);
        Queue::assertPushed(OptimizeAsset::class, fn ($job) => $job->assetId === $pending->id());

        Queue::fake();
        $this->configureSettings(['containers' => ['other']]);
        app(OptimizeAllController::class)($request, app(Settings::class));
        Queue::assertNotPushed(OptimizeAsset::class);
    }
}
