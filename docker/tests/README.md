# Matrix tests (Docker)

Runs the PHPUnit suite on a throwaway copy of the repo (host `vendor/` and `composer.lock` are never touched).

    docker/tests/run.sh <php> <laravel-major> [--prefer-lowest]   # one combination
    docker/tests/run.sh 8.3 12 --prefer-lowest                     # lowest Statamic 6.x / Laravel 12
    for p in 8.3 8.4; do for l in 12 13; do docker/tests/run.sh $p $l; done; done     # all four (add --prefer-lowest to taste)

Laravel 12 => orchestra/testbench ^10, Laravel 13 => ^11. Composer cache is kept in the `tinify-composer-cache` Docker volume.
