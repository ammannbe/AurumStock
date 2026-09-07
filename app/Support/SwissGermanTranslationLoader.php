<?php

namespace App\Support;

use Illuminate\Contracts\Translation\Loader;

/**
 * Filament and Laravel ship no `de_CH` translations, so Swiss German would
 * otherwise fall straight back to English. This layers `de_CH` on top of `de`
 * and rewrites the remaining "ß" to the Swiss "ss".
 */
class SwissGermanTranslationLoader implements Loader
{
    public function __construct(private Loader $loader) {}

    /**
     * @return array<mixed>
     */
    public function load($locale, $group, $namespace = null): array
    {
        $lines = $this->loader->load($locale, $group, $namespace);

        if ($locale !== 'de_CH') {
            return $lines;
        }

        return self::toSwiss(array_replace_recursive(
            $this->loader->load('de', $group, $namespace),
            $lines,
        ));
    }

    /**
     * @param  array<mixed>  $lines
     * @return array<mixed>
     */
    private static function toSwiss(array $lines): array
    {
        foreach ($lines as $key => $line) {
            if (is_array($line)) {
                $lines[$key] = self::toSwiss($line);
            } elseif (is_string($line)) {
                $lines[$key] = str_replace(['ß', 'ẞ'], ['ss', 'SS'], $line);
            }
        }

        return $lines;
    }

    public function addNamespace($namespace, $hint): void
    {
        $this->loader->addNamespace($namespace, $hint);
    }

    public function addJsonPath($path): void
    {
        $this->loader->addJsonPath($path);
    }

    /**
     * @return array<string, string>
     */
    public function namespaces(): array
    {
        return $this->loader->namespaces();
    }
}
