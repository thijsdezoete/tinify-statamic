# Statamic 6 test site for the Tinify addon

Fresh `statamic/statamic` site (Solo mode) with this checkout installed as `tinify/statamic` from `/addon`.
Serves on http://localhost:8080 via `php artisan serve`. CP login: `admin@example.com` / `password`.

Run from the repo root:

```sh
docker compose -f docker/site/compose.yaml up --build -d                    # build + start
docker compose -f docker/site/compose.yaml exec site php /seed.php          # seed ASSET_COUNT (default 5000) PNGs, half marked optimized
open http://localhost:8080/cp/utilities/tinify                              # the utility page
```

Set `ASSET_COUNT=20000` before `up` to change the seed size. The site lives only inside the container; `down` discards it.
