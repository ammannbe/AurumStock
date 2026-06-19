# AI Development Instructions

## Project

This is a Laravel precious metals inventory application.

The app manages:
- products
- depositories
- product sharing
- depository sharing
- stock prices
- stock price history
- exchange rates
- exports

## Tech Stack

- Laravel
- DDEV
- MariaDB
- Filament
- Laravel Livewire
- Pest
- Laravel Pint
- Larastan

## Command Rules

Use DDEV commands only:

```bash
ddev artisan
ddev composer
ddev npm
````

After code changes, run:

```bash
ddev artisan test
ddev composer pint
ddev composer phpstan
```

## General Rules

* Use Laravel conventions unless explicitly told otherwise.
* Prefer simple code over abstraction.
* Do not introduce new packages without asking first.
* Do not edit vendor files.
* Use Form Requests for validation in custom controllers.
* Use Policies for authorization.
* Use Pest for tests.
* Use factories for test data.
* Use Filament for main CRUD interfaces.
* Use Livewire only where interactive UI behavior is needed.
* Only authenticated users can access the app.

## Application Rules

* Users own products.
* Users own depositories.
* Products belong to exactly one user.
* Products belong to exactly one depository.
* Depositories belong to exactly one user.
* Products can be shared directly with other users.
* Depositories can be shared with other users.
* Sharing a depository gives access to all products inside it.
* Shared users may edit only with Edit permission.
* Shared users may not delete products or depositories.
* Guests must not access the app.

## Stock Price Rules

* Product stock price is not stored directly on products.
* Store metal stock prices internally as USD per gram.
* Calculate raw material value dynamically.
* One StockPrice row exists per material.
* Whenever prices are fetched, update StockPrice and create StockPriceHistory.
* Preferred stock API: MetalpriceAPI.
* Do not call real APIs unless API provider and key setup are confirmed.

## Exchange Rules

* Exchanges are based on USD.
* Meaning: 1 USD = exchange.value in the target currency.
* Preferred currencies: USD, CHF, EUR.

## Export Rules

* Start with CSV export if no package is approved.
* Preferred spreadsheet export package: PHPOffice/PhpSpreadsheet.
* Preferred ODT report export package: petecoop/odt.
* Do not install export packages without asking first.

## Authentication Rules

* Use Filament authentication unless the project already uses another auth system.
* No public signup in v1 unless explicitly requested.
* Users should be created through an authenticated user-management/admin flow.
* Do not implement real 2FA/passkeys unless explicitly requested.

## Testing Rules

Add or update Pest tests for every functional change.

Important test areas:

* authentication
* product CRUD
* depository CRUD
* product sharing
* depository sharing
* policies
* raw material value calculation
* stock price history
* profile update
* shares overview

## Before Finishing

Report:

* what changed
* tests result
* Pint result
* open follow-ups
