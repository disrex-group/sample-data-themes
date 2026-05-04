<?php

declare(strict_types=1);

namespace Disrex\SampleDataThemeHomeLiving\Test\Unit;

use Disrex\SampleDataThemesCore\Helper\Fixture\CsvParser;
use PHPUnit\Framework\TestCase;

/**
 * Integration-style sanity check that runs the framework's CsvParser
 * against the actual CSVs shipped by the home-living theme. This catches
 * regressions in the data files (mismatched columns, missing keys, broken
 * encoding) without requiring a Magento install.
 */
final class CsvIntegrityTest extends TestCase
{
    private CsvParser $parser;
    private string $filesDir;

    protected function setUp(): void
    {
        $this->parser = new CsvParser();
        $this->filesDir = realpath(__DIR__ . '/../../_files') ?: '';
    }

    public function testEveryBaseCsvParsesCleanly(): void
    {
        $required = [
            'base/attributes.csv',
            'base/attribute_sets.csv',
            'base/categories.csv',
            'base/simple_products.csv',
            'base/configurable_products.csv',
            'base/configurable_variations.csv',
            'base/grouped_products.csv',
            'base/bundle_products.csv',
            'base/product_links.csv',
        ];

        foreach ($required as $relative) {
            $path = $this->filesDir . '/' . $relative;
            self::assertFileExists($path, "Missing base CSV: $relative");
            $rows = iterator_to_array($this->parser->parse($path), false);
            self::assertNotEmpty($rows, "Empty base CSV: $relative");
        }
    }

    public function testEveryLocaleCsvParsesCleanly(): void
    {
        foreach (['en_US', 'nl_NL'] as $locale) {
            foreach (['categories', 'products', 'attributes'] as $entity) {
                $path = $this->filesDir . "/i18n/$locale/$entity.csv";
                self::assertFileExists($path, "Missing i18n CSV: $locale/$entity.csv");
                $rows = iterator_to_array($this->parser->parse($path), false);
                self::assertNotEmpty($rows, "Empty i18n CSV: $locale/$entity.csv");
            }
        }
    }

    public function testProductCountsMatchSpec(): void
    {
        $simple = iterator_to_array(
            $this->parser->parse($this->filesDir . '/base/simple_products.csv'),
            false
        );
        $configurable = iterator_to_array(
            $this->parser->parse($this->filesDir . '/base/configurable_products.csv'),
            false
        );
        $grouped = iterator_to_array(
            $this->parser->parse($this->filesDir . '/base/grouped_products.csv'),
            false
        );
        $bundle = iterator_to_array(
            $this->parser->parse($this->filesDir . '/base/bundle_products.csv'),
            false
        );

        self::assertCount(110, $simple);
        self::assertCount(210, $configurable);
        self::assertCount(2, $grouped);
        self::assertCount(2, $bundle);
    }

    public function testEveryConfigurableVariantReferencesAKnownParent(): void
    {
        $parents = array_flip($this->parser->extractColumn(
            $this->filesDir . '/base/configurable_products.csv',
            'sku'
        ));

        foreach ($this->parser->parse($this->filesDir . '/base/configurable_variations.csv') as $row) {
            self::assertArrayHasKey(
                $row['parent_sku'],
                $parents,
                'Variant ' . ($row['child_sku'] ?? '?')
                . ' references unknown parent ' . ($row['parent_sku'] ?? '?')
            );
        }
    }

    public function testProductLinkTargetsExist(): void
    {
        $known = array_flip(array_merge(
            $this->parser->extractColumn($this->filesDir . '/base/simple_products.csv', 'sku'),
            $this->parser->extractColumn($this->filesDir . '/base/configurable_products.csv', 'sku'),
            $this->parser->extractColumn($this->filesDir . '/base/configurable_variations.csv', 'child_sku'),
            $this->parser->extractColumn($this->filesDir . '/base/grouped_products.csv', 'sku'),
            $this->parser->extractColumn($this->filesDir . '/base/bundle_products.csv', 'sku'),
            $this->parser->extractColumn($this->filesDir . '/base/virtual_products.csv', 'sku')
        ));

        foreach ($this->parser->parse($this->filesDir . '/base/product_links.csv') as $row) {
            self::assertArrayHasKey($row['sku'], $known, 'Link source missing: ' . $row['sku']);
            foreach (array_filter(array_map('trim', explode(',', $row['linked_skus']))) as $linked) {
                self::assertArrayHasKey($linked, $known, "Link target missing: $linked");
            }
        }
    }

    public function testTranslationKeysCoverEveryProductSku(): void
    {
        $allBaseSkus = array_unique(array_merge(
            $this->parser->extractColumn($this->filesDir . '/base/simple_products.csv', 'sku'),
            $this->parser->extractColumn($this->filesDir . '/base/configurable_products.csv', 'sku'),
            $this->parser->extractColumn($this->filesDir . '/base/grouped_products.csv', 'sku'),
            $this->parser->extractColumn($this->filesDir . '/base/bundle_products.csv', 'sku')
        ));

        foreach (['en_US', 'nl_NL'] as $locale) {
            $localeSkus = $this->parser->extractColumn(
                $this->filesDir . "/i18n/$locale/products.csv",
                'sku'
            );
            $missing = array_diff($allBaseSkus, $localeSkus);
            self::assertSame([], $missing, "$locale/products.csv missing: " . implode(',', $missing));
        }
    }
}
