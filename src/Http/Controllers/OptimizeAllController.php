<?php

namespace ThijsDeZoete\TinifyStatamic\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Statamic\Facades\AssetContainer;
use Statamic\Http\Controllers\CP\CpController;
use ThijsDeZoete\TinifyStatamic\Jobs\OptimizeAsset;
use ThijsDeZoete\TinifyStatamic\Support\Images;
use ThijsDeZoete\TinifyStatamic\Support\Settings;

class OptimizeAllController extends CpController
{
    public function __invoke(Request $request, Settings $settings): RedirectResponse
    {
        $count = 0;

        foreach (AssetContainer::all() as $container) {
            if (! $settings->appliesTo($container)) {
                continue;
            }

            foreach ($container->queryAssets()->get() as $asset) {
                if (! $asset->extensionIsOneOf(Images::EXTENSIONS)
                    || ($asset->get('tinify')['hash'] ?? null)
                    || ! $request->user()->can('edit', $asset)) {
                    continue;
                }

                OptimizeAsset::dispatch($asset->id());
                $count++;
            }
        }

        return redirect()->back()->with('success', __(':count images queued.', ['count' => $count]));
    }
}
