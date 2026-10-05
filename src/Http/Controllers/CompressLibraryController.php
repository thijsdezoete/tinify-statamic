<?php

namespace Tinify\Statamic\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Statamic\Facades\Addon;
use Statamic\Facades\AssetContainer;
use Statamic\Http\Controllers\CP\CpController;
use Tinify\Statamic\Jobs\OptimizeAsset;
use Tinify\Statamic\Support\Images;
use Tinify\Statamic\Support\Settings;

class CompressLibraryController extends CpController
{
    public function __invoke(Request $request, Settings $settings): array
    {
        $this->authorize('editSettings', Addon::get('tinify/statamic'));

        $request->validate(['force' => 'sometimes|boolean']);
        $force = $request->boolean('force');

        if (! $settings->apiKey()) {
            throw ValidationException::withMessages([
                'api_key' => __('Save a Tinify API key in the addon settings or configure TINIFY_API_KEY before compressing the library.'),
            ]);
        }

        $user = $request->user();

        $queued = 0;
        $skipped = 0;

        foreach (AssetContainer::all() as $container) {
            foreach ($container->queryAssets()->get() as $asset) {
                if (! $asset->extensionIsOneOf(Images::EXTENSIONS) || ! $user->can('edit', $asset)) {
                    continue;
                }

                if (! $force && ($asset->get('tinify')['hash'] ?? null)) {
                    $skipped++;

                    continue;
                }

                OptimizeAsset::dispatch($asset->id(), force: $force, ignoreContainerFilter: true);
                $queued++;
            }
        }

        return [
            'message' => trans_choice('Submitted :count image for compression.|Submitted :count images for compression.', $queued, ['count' => $queued]),
            'queued' => $queued,
            'skipped' => $skipped,
        ];
    }
}
