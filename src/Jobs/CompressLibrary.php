<?php

namespace ThijsDeZoete\TinifyStatamic\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Statamic\Facades\AssetContainer;
use Statamic\Facades\User;
use ThijsDeZoete\TinifyStatamic\Support\Images;

/**
 * Walks every container off the request thread and queues one OptimizeAsset job per image,
 * so large libraries do not time out the Control Panel request.
 */
class CompressLibrary implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public function __construct(public string $userId, public bool $force = false) {}

    public function handle(): void
    {
        if (! $user = User::find($this->userId)) {
            return;
        }

        foreach (AssetContainer::all() as $container) {
            foreach ($container->queryAssets()->get() as $asset) {
                if (! $asset->extensionIsOneOf(Images::EXTENSIONS)
                    || ! $user->can('edit', $asset)
                    || (! $this->force && ($asset->get('tinify')['hash'] ?? null))) {
                    continue;
                }

                OptimizeAsset::dispatch($asset->id(), force: $this->force, ignoreContainerFilter: true);
            }
        }
    }
}
