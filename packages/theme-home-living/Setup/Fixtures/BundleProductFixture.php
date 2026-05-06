<?php

declare(strict_types=1);

namespace Disrex\SampleDataThemeHomeLiving\Setup\Fixtures;

use Disrex\SampleDataThemeHomeLiving\Model\Theme;
use Disrex\SampleDataThemesCore\Helper\Fixture\BundleProductBuilder;
use Disrex\SampleDataThemesCore\Helper\Fixture\CsvParser;
use Disrex\SampleDataThemesCore\Helper\Fixture\LocaleResolver;
use Disrex\SampleDataThemesCore\Helper\Fixture\ProductImporter;
use Disrex\SampleDataThemesCore\Helper\Fixture\TranslationLoader;
use Disrex\SampleDataThemesCore\Model\Fixture\AbstractCsvFixture;
use Magento\Framework\Module\Dir\Reader as ModuleDirReader;
use Psr\Log\LoggerInterface;

/**
 * Imports the two bundle products. The `options` column on each row uses a
 * compact, two-level encoding:
 *
 *     OptionTitle|type|required=SELSKU:qty;SELSKU:qty || ...
 *
 * - Options are separated by `||`
 * - Within an option, fields are pipe-delimited: `title | type | required-flag`
 *   followed by `=SKU:qty;SKU:qty` listing selectable products
 * - `type` is one of `select`, `radio`, `checkbox`, `multi`
 * - `required` is `1` or `0`
 *
 * Names / descriptions come from the i18n CSVs as for any other product.
 */
class BundleProductFixture extends AbstractCsvFixture
{
    public function __construct(
        CsvParser $csvParser,
        ModuleDirReader $moduleReader,
        LoggerInterface $logger,
        private readonly ProductImporter $productImporter,
        private readonly BundleProductBuilder $builder,
        private readonly LocaleResolver $localeResolver,
        private readonly TranslationLoader $translationLoader,
        private readonly Theme $theme
    ) {
        parent::__construct($csvParser, $moduleReader, $logger);
    }

    protected function getCsvFilename(): string
    {
        return 'base/bundle_products.csv';
    }

    protected function getModuleName(): string
    {
        return 'Disrex_SampleDataThemeHomeLiving';
    }

    public static function alias(): ?string
    {
        return 'bundle';
    }

    public function getLabel(): string
    {
        return 'Home & Living bundle products (2)';
    }

    protected function processRow(array $row): void
    {
        $sku = $row['sku'] ?? '';
        if ($sku === '') {
            return;
        }

        // Step 1: insert the bundle parent as a simple placeholder.
        $shellRow = $row;
        $shellRow['type_id'] = 'simple';
        unset($shellRow['options']);
        $this->productImporter->createOrUpdateBase($shellRow);

        // Step 2: parse options and let the builder promote + compose.
        $options = $this->parseOptions($row['options'] ?? '');
        if ($options !== []) {
            $this->builder->compose($sku, $options);
        }

        $this->applyTranslationsForSku($sku);
    }

    public function rollback(): void
    {
        $skus = $this->csvParser->extractColumn($this->getFixtureFilePath(), 'sku');
        $this->productImporter->deleteBySkus($skus);
    }

    /**
     * @return array<int, array{
     *     title: string,
     *     type: string,
     *     required: bool,
     *     selections: array<int, array{sku: string, qty: float}>
     * }>
     */
    private function parseOptions(string $raw): array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return [];
        }
        $options = [];
        foreach (explode('||', $raw) as $optionRaw) {
            $optionRaw = trim($optionRaw);
            if ($optionRaw === '') {
                continue;
            }
            // title|type|required=selections
            $parts = explode('=', $optionRaw, 2);
            if (count($parts) !== 2) {
                continue;
            }
            [$header, $selectionsRaw] = $parts;
            $headerParts = explode('|', $header);
            $title = trim($headerParts[0] ?? '');
            $type = trim($headerParts[1] ?? 'select');
            $required = (int) trim($headerParts[2] ?? '1') === 1;
            if ($title === '') {
                continue;
            }

            $selections = [];
            foreach (explode(';', $selectionsRaw) as $selRaw) {
                $selRaw = trim($selRaw);
                if ($selRaw === '' || !str_contains($selRaw, ':')) {
                    continue;
                }
                [$selSku, $qty] = explode(':', $selRaw, 2);
                $selections[] = ['sku' => trim($selSku), 'qty' => (float) $qty];
            }
            if ($selections === []) {
                continue;
            }
            $options[] = [
                'title' => $title,
                'type' => $type,
                'required' => $required,
                'selections' => $selections,
            ];
        }
        return $options;
    }

    private function applyTranslationsForSku(string $sku): void
    {
        $i18nDir = $this->theme->getFixturesPath() . '/i18n';
        foreach ($this->theme->getSupportedLocales() as $locale) {
            $storeIds = $this->localeResolver->resolveStoreviewIds($locale);
            if ($storeIds === []) {
                continue;
            }
            $rows = $this->translationLoader->load($i18nDir, $locale, 'products', 'sku');
            if (!isset($rows[$sku])) {
                continue;
            }
            $t = $rows[$sku];
            $this->productImporter->setTranslatedFields($sku, $storeIds, [
                'name' => $t['name'] ?? null,
                'description' => $t['description'] ?? null,
                'short_description' => $t['short_description'] ?? null,
                'url_key' => $t['url_key'] ?? null,
                'meta_title' => $t['meta_title'] ?? null,
                'meta_description' => $t['meta_description'] ?? null,
                'meta_keyword' => $t['meta_keyword'] ?? null,
            ]);
        }
    }
}
