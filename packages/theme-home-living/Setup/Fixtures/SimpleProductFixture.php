<?php

declare(strict_types=1);

namespace Disrex\SampleDataThemeHomeLiving\Setup\Fixtures;

use Disrex\SampleDataThemeHomeLiving\Model\Theme;
use Disrex\SampleDataThemesCore\Helper\Fixture\CsvParser;
use Disrex\SampleDataThemesCore\Helper\Fixture\LocaleResolver;
use Disrex\SampleDataThemesCore\Helper\Fixture\ProductImporter;
use Disrex\SampleDataThemesCore\Helper\Fixture\TranslationLoader;
use Disrex\SampleDataThemesCore\Model\Fixture\AbstractCsvFixture;
use Magento\Framework\Module\Dir\Reader as ModuleDirReader;
use Psr\Log\LoggerInterface;

/**
 * Imports the 18 standalone simple products from
 * `base/simple_products.csv`. Translations from
 * `i18n/<locale>/products.csv` are applied per storeview after the base
 * row is saved.
 */
class SimpleProductFixture extends AbstractCsvFixture
{
    public function __construct(
        CsvParser $csvParser,
        ModuleDirReader $moduleReader,
        LoggerInterface $logger,
        private readonly ProductImporter $importer,
        private readonly LocaleResolver $localeResolver,
        private readonly TranslationLoader $translationLoader,
        private readonly Theme $theme
    ) {
        parent::__construct($csvParser, $moduleReader, $logger);
    }

    protected function getCsvFilename(): string
    {
        return 'base/simple_products.csv';
    }

    protected function getModuleName(): string
    {
        return 'Disrex_SampleDataThemeHomeLiving';
    }

    public function getLabel(): string
    {
        return 'Home & Living simple products (18, EN/NL translated)';
    }

    protected function processRow(array $row): void
    {
        $this->importer->createOrUpdateBase($row);
        $this->applyTranslationsForSku($row['sku']);
    }

    public function rollback(): void
    {
        $skus = $this->csvParser->extractColumn($this->getFixtureFilePath(), 'sku');
        $this->importer->deleteBySkus($skus);
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
            $this->importer->setTranslatedFields($sku, $storeIds, [
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
