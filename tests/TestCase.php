<?php

namespace Tinify\Statamic\Tests;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Statamic\Contracts\Addons\Settings as AddonSettings;
use Statamic\Contracts\Addons\SettingsRepository;
use Statamic\Contracts\Assets\Asset;
use Statamic\Facades\Addon;
use Statamic\Facades\AssetContainer;
use Statamic\Testing\AddonTestCase;
use Tinify\Statamic\ServiceProvider;

abstract class TestCase extends AddonTestCase
{
    protected string $addonServiceProvider = ServiceProvider::class;

    protected string $fixtureRoot;

    protected AddonSettings $addonSettings;

    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        $this->fixtureRoot = sys_get_temp_dir().'/tinify-tests-'.bin2hex(random_bytes(8));

        foreach ($app['config']->get('statamic.stache.stores') as $handle => $store) {
            $app['config']->set("statamic.stache.stores.$handle.directory", $this->fixtureRoot.'/content/'.$handle);
        }

        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('t', 32)));
        $app['config']->set('cache.default', 'array');
        $app['config']->set('queue.default', 'sync');
        $app['config']->set('tinify.key', 'offline-test-key');
        $app['config']->set('statamic.assets.image_manipulation.cache_path', $this->fixtureRoot.'/glide');
        $app['config']->set('statamic.assets.image_manipulation.generate_presets_on_upload', false);
    }

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('assets');
        Storage::fake('local');
        AssetContainer::make('assets')->disk('assets')->title('Assets')->save();

        $this->addonSettings = app(SettingsRepository::class)->make(Addon::get('thijsdezoete/tinify-statamic'));
        $repository = Mockery::mock(SettingsRepository::class);
        $repository->shouldReceive('find')->with('thijsdezoete/tinify-statamic')->andReturn($this->addonSettings);
        $this->app->instance(SettingsRepository::class, $repository);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->fixtureRoot);

        parent::tearDown();
    }

    protected function configureSettings(array $values): void
    {
        $this->addonSettings->set($values);
    }

    protected function fixture(string $name): string
    {
        return file_get_contents(__DIR__.'/__fixtures__/'.$name);
    }

    protected function makeAsset(string $path = 'unoptimized.png', ?string $bytes = null, array $data = []): Asset
    {
        $asset = AssetContainer::find('assets')->makeAsset($path);
        $asset->disk()->put($path, $bytes ?? $this->fixture('unoptimized.png'));
        $asset->data($data);
        $asset->save();

        return $asset;
    }
}
