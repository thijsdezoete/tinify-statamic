#!/usr/bin/env sh
# Usage: run.sh <php-version> <laravel-major> [--prefer-lowest]
# Example: run.sh 8.3 12 --prefer-lowest
set -eu
PHP=${1:?php version}; LARAVEL=${2:?laravel major (12|13)}; LOWEST=${3:-}
case "$LARAVEL" in 12) TB='^10.0';; 13) TB='^11.0';; *) echo "laravel major must be 12 or 13" >&2; exit 1;; esac
ROOT=$(cd "$(dirname "$0")/../.." && pwd)
IMAGE="tinify-statamic-test:php$PHP"
docker build -q --build-arg "PHP_VERSION=$PHP" -t "$IMAGE" "$ROOT/docker/tests" >/dev/null
docker run --rm -v "$ROOT:/src:ro" -v tinify-composer-cache:/root/.composer/cache "$IMAGE" sh -c "
  set -e
  cp -r /src/. /app && rm -rf /app/vendor /app/composer.lock /app/node_modules /app/.phpunit.cache
  composer require --dev --no-update --quiet 'orchestra/testbench:$TB'
  composer update --prefer-dist --no-interaction --no-progress $LOWEST
  for p in laravel/framework statamic/cms orchestra/testbench phpunit/phpunit; do composer show \$p | grep -E '^(name |versions)' | paste - -; done
  vendor/bin/phpunit
"
