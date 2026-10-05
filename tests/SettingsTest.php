<?php

namespace Tinify\Statamic\Tests;

use Illuminate\Support\Facades\Log;
use Mockery;
use Statamic\Facades\AssetContainer;
use Statamic\Facades\Glide;
use Tinify\Statamic\Api\Client;
use Tinify\Statamic\Jobs\OptimizeAsset;
use Tinify\Statamic\Jobs\OptimizeGlideImage;
use Tinify\Statamic\Support\Settings;

class SettingsTest extends TestCase
{
    public function test_saved_key_overrides_environment_and_blank_key_falls_back(): void
    {
        config(['tinify.key' => 'environment-key']);
        $settings = app(Settings::class);
        $this->configureSettings(['api_key' => 'settings-key']);
        $this->assertSame('settings-key', $settings->apiKey());

        $this->configureSettings(['api_key' => '']);
        $this->assertSame('environment-key', $settings->apiKey());

        config(['tinify.key' => null]);
        $this->assertNull($settings->apiKey());
    }

    public function test_explicit_disabled_uploads_and_container_restrictions_are_respected(): void
    {
        $settings = app(Settings::class);
        $container = AssetContainer::find('assets');
        $this->configureSettings(['optimize_on_upload' => false, 'containers' => ['other']]);
        $this->assertFalse($settings->optimizeOnUpload());
        $this->assertFalse($settings->appliesTo($container));

        $this->configureSettings(['containers' => []]);
        $this->assertTrue($settings->appliesTo($container));
    }

    public function test_missing_key_skips_both_job_types_and_logs_only_once(): void
    {
        config(['tinify.key' => null]);
        $this->configureSettings(['api_key' => '', 'optimize_glide' => true]);
        $asset = $this->makeAsset();
        Glide::cacheDisk()->put('image.png', $this->fixture('unoptimized.png'));
        $client = Mockery::mock(Client::class);
        $client->shouldNotReceive('optimize');
        Log::spy();

        (new OptimizeAsset($asset->id()))->handle($client, app(Settings::class));
        (new OptimizeGlideImage('image.png'))->handle($client, app(Settings::class));

        Log::shouldHaveReceived('notice')->once();
        $this->assertSame($this->fixture('unoptimized.png'), $asset->contents());
        $this->assertNull($asset->get('tinify'));
        $this->assertSame($this->fixture('unoptimized.png'), Glide::cacheDisk()->get('image.png'));
    }
}
