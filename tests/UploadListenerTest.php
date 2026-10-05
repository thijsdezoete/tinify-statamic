<?php

namespace Tinify\Statamic\Tests;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Statamic\Contracts\Assets\Asset;
use Statamic\Facades\AssetContainer;
use Tinify\Statamic\Jobs\OptimizeAsset;

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
        $this->upload('photo.svg', '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16"></svg>');

        Queue::assertNotPushed(OptimizeAsset::class);
    }
}
