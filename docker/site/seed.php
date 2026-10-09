<?php

// Seeds ASSET_COUNT (default 5000) tiny distinct PNGs into the `assets` container,
// writes their metadata, and marks every other one as optimized by Tinify.
// Run inside the container: docker compose -f docker/site/compose.yaml exec site php /seed.php

use Illuminate\Contracts\Console\Kernel;
use Statamic\Facades\AssetContainer;

require '/site/vendor/autoload.php';
$app = require '/site/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$count = (int) (getenv('ASSET_COUNT') ?: 5000);
$container = AssetContainer::findByHandle('assets');
$disk = $container->disk();

for ($i = 1; $i <= $count; $i++) {
    $image = imagecreatetruecolor(8, 8);
    imagefill($image, 0, 0, imagecolorallocate($image, $i % 256, ($i >> 8) % 256, ($i >> 16) % 256));
    ob_start();
    imagepng($image);
    $bytes = ob_get_clean();
    imagedestroy($image);

    $disk->put($path = sprintf('seed/%05d.png', $i), $bytes);

    $asset = $container->makeAsset($path);

    if ($i % 2) {
        $asset->set('tinify', [
            'hash' => sha1($bytes),
            'optimized_at' => time(),
            'original_size' => strlen($bytes) * 3,
            'size' => strlen($bytes),
        ]);
    }

    $asset->saveQuietly();

    if ($i % 500 === 0) {
        echo "$i/$count\n";
    }
}

echo "Seeded $count assets into public/assets/seed\n";
