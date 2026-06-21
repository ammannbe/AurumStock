<?php

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @throws NotFoundHttpException
 */
function user(): \App\Models\User
{
    abort_unless(auth()->check(), 403);

    /** @var \App\Models\User */
    return auth()->user();
}

function emdash(): string
{
    return '—';
}

function weight(float|int|null $amount = null, string|false|null $unit = 'g', int $decimals = 3): string
{
    if ($amount === null) {
        return emdash();
    }

    if ($unit) {
        return str_replace('.000', '', number_format($amount, $decimals, '.', "'").' '.$unit);
    }

    return str_replace('.000', '', number_format($amount, $decimals, '.', "'"));
}

function money(float|int|null $amount = null, string|false|null $unit = 'USD', int $decimals = 2): string
{
    if ($amount === null) {
        return emdash();
    }

    if ($unit) {
        return $unit.' '.number_format($amount, $decimals, '.', "'");
    }

    return number_format($amount, $decimals, '.', "'");
}

function percent(float|int|null $amount = null, bool|string $unit = true, int $decimals = 2): string
{
    if ($amount === null) {
        return emdash();
    }

    $amount = round($amount, $decimals);

    $formatted = number_format($amount, $decimals, '.', "'");
    $formatted = rtrim(rtrim($formatted, '0'), '.');

    if ($unit === true) {
        return $formatted.' %';
    }

    if ($unit) {
        return $formatted.' '.$unit;
    }

    return $formatted;
}

function moneyOrPercent(float|int|null $amount = null, ?string $symbol = null, bool $showUnit = true): string
{
    if (! $symbol) {
        $symbol = 'CHF';
    }

    if ($symbol === '%') {
        return percent($amount, $showUnit);
    }

    return money($amount, $showUnit ? $symbol : false);
}

function formatDate(?\Carbon\Carbon $date = null, string $format = 'L'): ?string
{
    return $date?->isoFormat($format);
}

function formatTime(int $seconds = 0): string
{
    $hours = floor($seconds / 3600);
    $minutes = floor($seconds / 60) % 60;

    return sprintf('%02d:%02d', $hours, $minutes);
}

function parseTime(string $time): int
{
    [$h, $m] = explode(':', $time);

    return (int) $h * 3600 + (int) $m * 60;
}

function hours(?int $seconds = 0): int
{
    return (int) floor(($seconds ?? 0) / 3600);
}

function minutes(?int $seconds = 0): int
{
    return floor(($seconds ?? 0) / 60) % 60;
}

/**
 * @param  iterable<mixed>|string  $abilities
 * @param  array<array>|array<mixed>  $argumentsArray
 */
function canAnyPolicy($abilities, $argumentsArray = []): bool
{
    foreach ($argumentsArray as $arguments) {
        if (user()->can($abilities, $arguments)) {
            return true;
        }
    }

    return false;
}

/**
 * @param  iterable<mixed>|string  $abilities
 * @param  array<array>|array<mixed>  $argumentsArray
 */
function firstPolicy($abilities, $argumentsArray = []): ?string
{
    foreach ($argumentsArray as $arguments) {
        if (user()->can($abilities, $arguments)) {
            return $arguments;
        }
    }

    return null;
}

function slugifyClass(string $class): string
{
    return \Str::kebab((new ReflectionClass(new $class))->getShortName());
}
