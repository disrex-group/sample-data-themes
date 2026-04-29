<?php

declare(strict_types=1);

namespace Disrex\SampleDataThemeHomeLiving\Setup\Fixtures;

use Disrex\SampleDataThemeHomeLiving\Model\Theme;
use Disrex\SampleDataThemesCore\Helper\Fixture\CsvParser;
use Disrex\SampleDataThemesCore\Helper\Fixture\GroupedProductBuilder;
use Disrex\SampleDataThemesCore\Helper\Fixture\LocaleResolver;
use Disrex\SampleDataThemesCore\Helper\Fixture\ProductImporter;
use Disrex\SampleDataThemesCore\Helper\Fixture\TranslationLoader;
use Disrex\SampleDataThemesCore\Model\Fixture\AbstractCsvFixture;
use Magento\Framework\Module\Dir\Reader as ModuleDirReader;
use Psr\Log\LoggerInterface;

/**
 * Imports the two grouped products. Each parent is created as a simple
 * shell first, then the GroupedProductBuilder attaches the associated
 * SKU/qty pairs and promotes the type.
 *
 * The associated SKUs are encoded as `SKU:qty,SKU:qty` in the
 * `associated_skus` column.
 */
class GroupedProductFixture extends AbstractCsvFixture
{
    public function __construct(
        CsvParser $csvParser,
        ModuleDirReader $moduleReader,
        LoggerInterface $logger,
        private readonly ProductImporter $productImporter,
        private readonly GroupedProductBuilder $builder,
        private readonly LocaleResolver $localeResolver,
        private readonly TranslationLoader $translationLoader,
        private readonly Theme $theme
    ) {
        parent::__construct($csvParser, $moduleReader, $logger);
    }

    protected function getCsvFilename(): string
    {
        return 'base/grouped_products.csv';
    }

    protected function getModuleName(): string
    {
        return 'Disrex_SampleDataThemeHomeLiving';
    }

    public function getLabel(): string
    {
        return 'Home & Living grouped products (2)';
    }

    protected function processRow(array $row): void
    {
        $sku = $row['sku'] ?? '';
        if ($sku === '') {
            return;
        }
        // Step 1: insert the parent as a placeholder simple product.
        $row['type_id'] = 'simple';
        $this->productImporter->createOrUpdateBase($row);

        // Step 2: attach associated children.
        $associations = $this->parseAssociations($row['associated_skus'] ?? '');
        if ($associations !== []) {
            $this->builder->link($sku, $associations);
        }

        $this->applyTranslationsForSku($sku);
    }

    public function rollback(): void
    {
        $skus = $this->csvParser->extractColumn($this->getFixtureFilePath(), 'sku');
        $this->productImporter->deleteBySkus($skus);
    }

    /**
     * @return array<int, array{sku: string, qty: float}>
     */
    private function parseAssociations(string $raw): array
    {
        $out = [];
        foreach (array_filter(array_map('trim', explode(',', $raw))) as $entry) {
            if (!str_contains($entry, ':')) {
                continue;
            }
            [$sku, $qty] = explode(':', $entry, 2);
            $out[] = ['sku' => trim($sku), 'qty' => (float) $qty];
        }
        return $out;
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
