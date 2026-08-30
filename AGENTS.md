# AI Development Instructions

## Project

This is a Laravel precious metals inventory application.

The app manages:
- products
- depositories
- stock prices
- stock price history
- exchange rates
- exports

## Tech Stack

- Laravel v13
- DDEV
- MariaDB
- Filament v5
- Laravel Livewire v4
- Pest
- Laravel Pint
- Larastan

## Command Rules

Use DDEV commands only:

```bash
ddev artisan
ddev composer
ddev npm
ddev <custom command>
````

After code changes, run:

```bash
ddev composer test
ddev composer pint
ddev composer phpstan
```

If any command fails, repeat the process from the beginning.

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
* Only authenticated users can access the app.
* Guests must not access the app.

## Exchange Rules

* Exchanges are based on USD.
* Meaning: 1 USD = exchange.value in the target currency.
* Preferred currencies: USD, CHF, EUR.

## Authentication Rules

* Use Filament authentication.
* Users should be created through an authenticated user-management/admin flow.

## Testing Rules

Add or update Pest tests for every functional change.

Important test areas:

* authentication
* policies
* filament pages
* filament resources
* profile update

## Before Finishing

Report:

* what changed
* tests result
* pint result
* phpstan result
* open follow-ups
