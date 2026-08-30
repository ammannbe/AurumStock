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

Set them in `.env` if you want live rates and prices.

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
- Livewire 4
- DDEV
- MariaDB
- Pest
- Laravel Pint
- Larastan

## Authors

- Benjamin Ammann
