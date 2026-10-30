# Free cloud VM deployment

This deployment runs the Laravel app, WordPress, and MariaDB on one Docker VM. Caddy handles HTTPS certificates automatically. It is intended for a small deployment; free-tier VM availability, resource limits, and terms depend on the provider.

## Before starting

- Create an Ubuntu VM with a public IPv4 address and install Docker Engine with the Compose plugin.
- Choose a free VM with at least 2 GB of RAM if the provider has capacity; running the database, Laravel, WordPress, and proxy together is heavier than a single static site.
- Configure the VM firewall and provider network rules to allow inbound TCP ports 22, 80, and 443. Do not expose the database or app container ports.
- Point two DNS hostnames to the VM's public IP, for example `app.example.com` and `www.example.com`. Caddy cannot issue certificates until DNS points to the VM and ports 80/443 are reachable.
- Clone this repository onto the VM. Keep `.env.production` private and never commit it.

## Configure and launch

1. Copy `.env.production.example` to `.env.production`; replace `APP_DOMAIN`, `WP_DOMAIN`, and `APP_URL` with the DNS hostnames you configured.
2. Set `DB_ROOT_PASSWORD`, `DB_APP_PASSWORD`, and `DTC_API_KEY` to separate long random values. For example, on Ubuntu:
   ```sh
   openssl rand -hex 32
   ```
   Run it separately for each value. Laravel and WordPress use a restricted database account; only the database container uses the root password. Configure `APP_KEY` in the next step.
3. Build the Laravel image and print a fresh Laravel key:
   ```sh
   docker compose --env-file .env.production -f compose.production.yaml build laravel
   docker compose --env-file .env.production -f compose.production.yaml run --rm --no-deps laravel php artisan key:generate --show
   ```
   Copy the printed key into `APP_KEY` in `.env.production`.
4. Configure working SMTP details in `.env.production`. Without SMTP, registration and claim emails will not be delivered.
5. Restrict access to the environment file and start the services:
   ```sh
   chmod 600 .env.production
   docker compose --env-file .env.production -f compose.production.yaml up -d --build
   ```
   The Laravel service applies database migrations on startup. The scheduler runs separately. The database, Laravel private uploads, WordPress files, and HTTPS certificates use persistent Docker volumes.
6. Create the first admin account:
   ```sh
   docker compose --env-file .env.production -f compose.production.yaml exec laravel php artisan dtc:create-admin admin@example.com
   ```
7. Open the WordPress hostname, finish its initial setup, activate the DTC Connector plugin, and add `[dtc_register]` and `[dtc_check]` to the required pages.

Useful operations:

```sh
docker compose --env-file .env.production -f compose.production.yaml ps
docker compose --env-file .env.production -f compose.production.yaml logs -f laravel
docker compose --env-file .env.production -f compose.production.yaml pull
docker compose --env-file .env.production -f compose.production.yaml up -d --build
```

Back up the MariaDB data and Laravel's private storage volume off the VM. Docker volumes on the same VM are persistent across container rebuilds, but are not backups and can be lost with the VM. See [SETUP.md](./SETUP.md) for the application's backup and restore checks.

## Updating

Fetch the intended code revision on the VM, then run the `up -d --build` command above. Keep `.env.production` and the Docker volumes intact. Do not use `docker compose down -v` on a live deployment; `-v` deletes persistent application data.
