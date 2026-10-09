<?php

namespace ThijsDeZoete\TinifyStatamic;

use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Statamic\Events\AssetReuploaded;
use Statamic\Events\AssetUploaded;
use Statamic\Events\GlideImageGenerated;
use Statamic\Facades\Addon;
use Statamic\Facades\AssetContainer;
use Statamic\Facades\Utility;
use Statamic\Providers\AddonServiceProvider;
use ThijsDeZoete\TinifyStatamic\Api\Client;
use ThijsDeZoete\TinifyStatamic\Commands\OptimizeCommand;
use ThijsDeZoete\TinifyStatamic\Http\Controllers\OptimizeAllController;
use ThijsDeZoete\TinifyStatamic\Listeners\OptimizeGeneratedGlideImage;
use ThijsDeZoete\TinifyStatamic\Listeners\OptimizeUploadedAsset;
use ThijsDeZoete\TinifyStatamic\Support\Images;
use ThijsDeZoete\TinifyStatamic\Support\Settings;

class ServiceProvider extends AddonServiceProvider
{
    protected $config = false;

    protected $viewNamespace = 'tinify';

    protected $vite = [
        'input' => ['resources/js/addon.js'],
        'publicDirectory' => 'resources/dist',
    ];

    protected $commands = [OptimizeCommand::class];

    protected $listen = [
        AssetUploaded::class => [OptimizeUploadedAsset::class],
        AssetReuploaded::class => [OptimizeUploadedAsset::class],
        GlideImageGenerated::class => [OptimizeGeneratedGlideImage::class],
    ];

    public function register()
    {
        parent::register();

        $this->mergeConfigFrom(__DIR__.'/../config/tinify.php', 'tinify');

        $this->app->singleton(Client::class);
    }

    public function bootAddon()
    {
        Utility::extend(function () {
            Utility::register('tinify')
                ->title('Tinify')
                ->icon('media-image-picture-gallery')
                ->description(__('Optimize images with TinyPNG'))
                ->view('tinify::utility', fn (Request $request) => $this->utilityData($request))
                ->routes(function (Router $router) {
                    $router->post('/', OptimizeAllController::class)->name('optimize');
                });
        });
    }

    private function utilityData(Request $request): array
    {
        $usage = app(Client::class)->accountUsage();
        $settings = app(Settings::class);

        $containers = [];
        $bytesSaved = 0;

        foreach (AssetContainer::all() as $container) {
            if (! $settings->appliesTo($container) || ! $request->user()->can('view', $container)) {
                continue;
            }

            $optimized = $pending = 0;

            foreach ($container->queryAssets()->get() as $asset) {
                if (! $asset->extensionIsOneOf(Images::EXTENSIONS)) {
                    continue;
                }

                $stats = $asset->get('tinify', []);

                if ($stats['hash'] ?? null) {
                    $optimized++;
                    $bytesSaved += ($stats['original_size'] ?? 0) - ($stats['size'] ?? 0);
                } else {
                    $pending++;
                }
            }

            $containers[] = [
                'title' => $container->title(),
                'handle' => $container->handle(),
                'optimized' => $optimized,
                'pending' => $pending,
            ];
        }

        return [
            ...$usage,
            'containers' => $containers,
            'bytesSaved' => $bytesSaved,
            'settingsUrl' => Addon::get('thijsdezoete/tinify-statamic')->settingsUrl(),
        ];
    }
}
