# Opsome GrowthOps

Supervised AI media-buyer control plane — Laravel + Filament demo scaffold.

## Run locally (Docker)

PHP is not required on the host; everything runs in Docker.

```sh
docker compose up --build
```

Then open http://localhost:8000/admin and log in with the seeded demo user:

- Email: `demo@growthops.test`
- Password: `growthops-demo` (override with the `DEMO_USER_PASSWORD` env var — public demo credential, not a secret)

## Tests

```sh
docker build --target base -t growthops-dev .
docker run --rm --user "$(id -u):$(id -g)" -e HOME=/tmp -v "$PWD":/app -w /app growthops-dev composer install
docker run --rm --user "$(id -u):$(id -g)" -e HOME=/tmp -v "$PWD":/app -w /app growthops-dev ./vendor/bin/pest
```

CI runs the Pest suite on every PR to `main`.
