# AurumStock

AurumStock is a precious metals inventory application for tracking products, depositories, stock prices, and exchange rates.

Written with the PHP framework [Laravel](https://laravel.com/) and built primarily with [Filament](https://filamentphp.com/) and [Livewire](https://livewire.laravel.com/).

## Getting Started

Clone the repository:

```bash
git clone <your-repository-url>
cd AurumStock
```

### Prerequisites

#### Development requirements

This project is built around [DDEV](https://ddev.com/). For local development you only need DDEV (and Docker) on your machine.

Use DDEV wrappers for project commands:

```bash
ddev artisan
ddev composer
ddev npm
ddev <custom command>
```

#### Production requirements

- PHP 8.3+
- Composer 2+
- Node.js + npm
- Database (MariaDB recommended)
- Web server (Nginx or Apache)
- Standard Laravel runtime requirements

## Features

- Product management for precious metals (material, type, fineness, weight units, purchase data, images, notes, and more)
- Depository management
- Live material valuation based on stock prices
- Exchange rates based on USD (`1 USD = exchange.value`)
- Stock price history tracking
- User profile preferences (currency and locale)
- Filament user panel authentication (`/app/login`)

## Installation and Update

### Development installation

```bash
# start containers
ddev start

# php dependencies
ddev composer install

# create local env file
cp .env.example .env

# application setup
ddev artisan key:generate
ddev artisan migrate
ddev artisan storage:link

# frontend dependencies and build
ddev npm install
ddev npm run build
```

Launch the app:

```bash
ddev launch
```

Start frontend watch mode during development:

```bash
ddev npm run dev
```

### API configuration

The project can fetch external market data using these environment variables:

- `METALPRICE_API_KEY`
- `EXCHANGE_RATE_API_KEY`
- `METALPRICE_BASE_URL` (optional override)
- `EXCHANGE_RATE_BASE_URL` (optional override)
- `METALPRICE_FETCH_INTERVAL` (`daily` or `hourly`, defaults to `daily`)

Set them in `.env` if you want live rates and prices.

`METALPRICE_FETCH_INTERVAL=hourly` records one price snapshot per material per hour instead of
per day. It needs roughly 720 MetalpriceAPI requests per month, which exceeds the free plan's
100 requests per month, so keep it on `daily` unless a paid plan is active.

### Fetching market data

Run these commands manually if needed:

```bash
ddev artisan app:fetch-exchange-rates
ddev artisan app:fetch-stock-prices
```

The scheduler is configured to run both tasks daily.

### Development update flow

```bash
ddev start
ddev composer install
ddev artisan migrate
ddev npm install
ddev npm run build
```

Clear caches if required:

```bash
ddev artisan cache:clear
ddev artisan config:clear
ddev artisan route:clear
ddev artisan view:clear
```

### Production deployment/update

```bash
composer install --optimize-autoloader --no-dev
php artisan migrate --force
npm install --no-save
npm run build
php artisan storage:link
```

Scheduler cron entry:

```bash
* * * * * www-data cd /path-to-project && php artisan schedule:run >> /dev/null 2>&1
```

## Authentication

- Root path `/` redirects to `/app/login`.
- Application access is handled by Filament user panel auth.
- Legacy Laravel auth routes such as `/login` and `/register` are intentionally unavailable.

## MCP (AI import)

AurumStock exposes an [MCP](https://modelcontextprotocol.io/) server for importing orders and products with an AI client (e.g. from a receipt PDF).

- Endpoint: `https://<host>/mcp/aurum` (Streamable HTTP, rate limited to 60 requests per minute)
- Auth: OAuth 2 with [Laravel Passport](https://laravel.com/docs/passport). AI clients register themselves (dynamic client registration), the user signs in and ticks the permissions on the approval screen. Permissions are stored per client in `mcp_grants` and can be changed or revoked in the app under **Connected AI clients**, which also shows how to connect each client.
- Permissions: `read` (orders, products, suppliers, depositories), `create` (orders, products, suppliers), `receipts` (attach receipts), `images` (add missing front/back images, additional images/videos and missing inscriptions to products; existing media and texts are never replaced or removed), `replace_media` (replace images and inscriptions with `replace=true`, remove additional media). Tools without permission are neither listed nor callable.

Example (Claude Code):

```bash
claude mcp add --transport http aurumstock https://<host>/mcp/aurum
# then run /mcp in Claude Code and choose "Authenticate"
```

Tools (all scoped to the signed-in user):

| Tool | Permission | Purpose |
|---|---|---|
| `list-suppliers`, `list-depositories`, `search-product-templates`, `search-products`, `list-orders`, `get-order` | read | Look up data (e.g. duplicate check, templates for line items) |
| `create-supplier` | create | Find or create a supplier |
| `create-order` | create | Order with supplier and product line items, atomic, supports `dry_run` |
| `create-product` | create | Single product, optionally linked to an order, supports `dry_run` |
| `attach-order-receipt` | receipts | Attach a PDF/image receipt (base64, max 10 MB) to an order |
| `set-product-image` | images | Add the front (image side) or back (value side) image to a product without one: `image_url` (public https only, downloaded by the server with SSRF protection) or base64, max 10 MB |
| `add-product-media` | images | Append an image or video to the additional media (`media_url` up to 40 MB or base64 up to 10 MB, optional label; identical files are skipped) |
| `set-product-texts` | images | Set `text_front` / `text_back` (inscriptions) when empty |
| `remove-product-media` | replace_media | Remove an additional media entry by filename (file deleted unless still referenced) |

`set-product-image` and `set-product-texts` replace existing values only with `replace=true` and the `replace_media` permission; the old image file is deleted unless a template or another product still uses it.

Deployment: after `php artisan migrate --force`, create the OAuth keys once with `php artisan passport:keys` (stored in `storage/oauth-*.key`, git-ignored, must persist across deployments) or set `PASSPORT_PRIVATE_KEY` / `PASSPORT_PUBLIC_KEY` in the environment.

## Translations

Application translations are provided via JSON language files:

- `lang/en.json`
- `lang/de.json`

After changing translations, clear caches when needed:

```bash
ddev artisan cache:clear
ddev artisan config:clear
```

## IDE Helpers

Generate IDE helper files:

```bash
ddev composer run ide-helper
```

## Testing / Code Quality

Run these commands after code changes:

```bash
ddev composer test
ddev composer pint
ddev composer phpstan
```

## Tech Stack

- Laravel 13
- Filament 5
- Laravel Passport + Laravel MCP
- Livewire 4
- DDEV
- MariaDB
- Pest
- Laravel Pint
- Larastan

## Authors

- Benjamin Ammann

## License

AGPLv3 or later, see [LICENSE](LICENSE).
