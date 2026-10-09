<?php

namespace ThijsDeZoete\TinifyStatamic\Tests;

use Illuminate\Support\Facades\Event;
use Mockery;
use RuntimeException;
use Statamic\Events\AssetSaving;
use Statamic\Facades\Asset;
use ThijsDeZoete\TinifyStatamic\Api\Client;
use ThijsDeZoete\TinifyStatamic\Api\Optimized;
use ThijsDeZoete\TinifyStatamic\Jobs\OptimizeAsset;
use ThijsDeZoete\TinifyStatamic\Support\Settings;

class AssetWriteFailureTest extends TestCase
{
    public function test_a_vetoed_conversion_save_never_deletes_the_original(): void
    {
        $this->configureSettings(['convert' => 'webp']);
        $asset = $this->makeAsset();
        Event::listen(AssetSaving::class, fn ($event) => $event->asset->extension() === 'webp' ? false : null);
        $client = Mockery::mock(Client::class);
        $client->shouldReceive('optimize')->once()
            ->andReturn(new Optimized($this->fixture('image.webp'), 'image/webp'));

        try {
            (new OptimizeAsset($asset->id()))->handle($client, app(Settings::class));
            $this->fail('A vetoed converted-asset save must not be treated as success.');
        } catch (RuntimeException) {
            $this->assertSame($this->fixture('unoptimized.png'), Asset::find($asset->id())->contents());
        }
    }
}
