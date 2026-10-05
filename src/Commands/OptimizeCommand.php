<?php

namespace Tinify\Statamic\Commands;

use Illuminate\Console\Command;
use Statamic\Console\RunsInPlease;
use Statamic\Facades\AssetContainer;
use Tinify\Statamic\Jobs\OptimizeAsset;
use Tinify\Statamic\Support\Images;
use Tinify\Statamic\Support\Settings;

class OptimizeCommand extends Command
{
    use RunsInPlease;

    protected $signature = 'statamic:tinify:optimize
        {container? : Container handle, omit for all}
        {--force : Re-optimize everything}
        {--sync : Run jobs immediately instead of queueing}';

    protected $description = 'Optimize supported assets with Tinify';

    public function handle(Settings $settings): int
    {
        $handle = $this->argument('container');

        if ($handle !== null) {
            if (! $container = AssetContainer::find($handle)) {
                $this->error("Container [{$handle}] not found.");

                return self::FAILURE;
            }

            $containers = collect([$container]);
        } else {
            $containers = AssetContainer::all();
        }

        $rows = [];
        $force = (bool) $this->option('force');

        foreach ($containers as $container) {
            $queued = $skipped = 0;

            foreach ($container->queryAssets()->get() as $asset) {
                if (! $asset->extensionIsOneOf(Images::EXTENSIONS)) {
                    continue;
                }

                // Bulk selection uses the marker; jobs verify actual content hashes.
                if (! $settings->appliesTo($container) || (! $force && ($asset->get('tinify')['hash'] ?? null))) {
                    $skipped++;

                    continue;
                }

                if ($this->option('sync')) {
                    OptimizeAsset::dispatchSync($asset->id(), $force);
                } else {
                    OptimizeAsset::dispatch($asset->id(), $force);
                }

                $queued++;
            }

            $rows[] = [$container->handle(), $queued, $skipped];
        }

        $this->table(['Container', $this->option('sync') ? 'Processed' : 'Queued', 'Skipped'], $rows);

        return self::SUCCESS;
    }
}
