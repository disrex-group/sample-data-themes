#!/usr/bin/env php
<?php

/**
 * Theme validator.
 *
 * Walks a theme's `_files/` tree and reports drift between the base CSVs
 * (canonical SKUs / paths / attribute codes) and the per-locale i18n
 * CSVs.
 *
 *   - "missing" rows in i18n are warnings (the framework falls back, but
 *     the source language will leak through)
 *   - "extra" rows in i18n are errors (they would never apply, almost
 *     certainly typos)
 *
 * Usage:
 *
 *   php tools/theme-validator.php packages/theme-home-living
 *
 * Exits 0 on success, 1 on any error.
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Disrex\SampleDataThemesCore\Helper\Fixture\CsvParser;

function fail(string $msg): never
{
    fwrite(STDERR, "[error] $msg\n");
    exit(1);
}

$themeDir = $argv[1] ?? null;
if ($themeDir === null) {
    fail('Usage: php tools/theme-validator.php <path-to-theme-package>');
}

$themeDir = rtrim($themeDir, '/');
$baseDir = $themeDir . '/_files/base';
$i18nDir = $themeDir . '/_files/i18n';

if (!is_dir($baseDir)) {
    fail("Theme base directory not found: $baseDir");
}
if (!is_dir($i18nDir)) {
    fail("Theme i18n directory not found: $i18nDir");
}

$parser = new CsvParser();

/**
 * @return array<int, string>
 */
function readKeys(CsvParser $parser, string $file, string $key): array
{
    if (!is_file($file)) {
        return [];
    }
    return $parser->extractColumn($file, $key);
}

// Every catalog SKU we can possibly translate, so the validator
// won't flag i18n rows for variants or virtuals as orphans. Variant
// translations land via the BulkVariantInserter / parent name-fallback
// path rather than per-storeview EAV rows, but they're still legitimate
// SKU keys to author translations against.
$baseProducts = array_unique(array_merge(
    readKeys($parser, "$baseDir/simple_products.csv", 'sku'),
    readKeys($parser, "$baseDir/configurable_products.csv", 'sku'),
    readKeys($parser, "$baseDir/configurable_variations.csv", 'child_sku'),
    readKeys($parser, "$baseDir/grouped_products.csv", 'sku'),
    readKeys($parser, "$baseDir/bundle_products.csv", 'sku'),
    readKeys($parser, "$baseDir/virtual_products.csv", 'sku')
));
// Variant SKUs (children of configurable parents). These don't need
// per-storeview translations; the parent's name plus the selected
// swatch label is what renders on the storefront.
$variantSkus = array_flip(readKeys(
    $parser,
    "$baseDir/configurable_variations.csv",
    'child_sku'
));
$baseCategories = readKeys($parser, "$baseDir/categories.csv", 'path');
$baseAttributes = readKeys($parser, "$baseDir/attributes.csv", 'attribute_code');

$totalErrors = 0;
$totalWarnings = 0;

foreach (glob("$i18nDir/*", GLOB_ONLYDIR) ?: [] as $localeDir) {
    $locale = basename($localeDir);
    echo "Locale: $locale\n";

    $checks = [
        ['products', 'sku', $baseProducts],
        ['categories', 'path', $baseCategories],
        ['attributes', 'attribute_code', $baseAttributes],
    ];

    foreach ($checks as [$entity, $keyCol, $baseKeys]) {
        $file = "$localeDir/$entity.csv";
        if (!is_file($file)) {
            echo "  [warn]  $entity.csv missing — falls back to default locale\n";
            $totalWarnings++;
            continue;
        }
        $localeKeys = array_unique($parser->extractColumn($file, $keyCol));
        $missing = array_values(array_diff($baseKeys, $localeKeys));
        $extra = array_values(array_diff($localeKeys, $baseKeys));

        // Configurable-product variants render as "<parent name> —
        // <swatch label>" using the parent's translated name, so the
        // theme intentionally ships no per-variant translation rows.
        // Don't warn about them. (Variant SKUs are the rows present in
        // configurable_variations.csv but not in any other product CSV.)
        if ($entity === 'products' && isset($variantSkus) && $variantSkus !== []) {
            $missing = array_values(array_filter(
                $missing,
                static fn (string $sku): bool => !isset($variantSkus[$sku])
            ));
        }

        if ($missing !== []) {
            echo "  [warn]  $entity.csv missing " . count($missing) . " row(s):\n";
            foreach ($missing as $m) {
                echo "          - $m\n";
            }
            $totalWarnings++;
        }
        if ($extra !== []) {
            echo "  [error] $entity.csv has " . count($extra) . " extra row(s):\n";
            foreach ($extra as $e) {
                echo "          + $e\n";
            }
            $totalErrors++;
        }
        if ($missing === [] && $extra === []) {
            echo "  [ok]    $entity.csv (" . count($localeKeys) . " rows)\n";
        }
    }
}

echo "\n";
if ($totalErrors > 0) {
    echo "Failed with $totalErrors error(s) and $totalWarnings warning(s).\n";
    exit(1);
}

echo "Passed with $totalWarnings warning(s).\n";
exit(0);
