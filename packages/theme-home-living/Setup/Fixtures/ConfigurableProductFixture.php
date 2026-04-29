<?php

declare(strict_types=1);

namespace Disrex\SampleDataThemeHomeLiving\Setup\Fixtures;

use Disrex\SampleDataThemeHomeLiving\Model\Theme;
use Disrex\SampleDataThemesCore\Helper\Fixture\ConfigurableProductBuilder;
use Disrex\SampleDataThemesCore\Helper\Fixture\CsvParser;
use Disrex\SampleDataThemesCore\Helper\Fixture\LocaleResolver;
use Disrex\SampleDataThemesCore\Helper\Fixture\ProductImporter;
use Disrex\SampleDataThemesCore\Helper\Fixture\TranslationLoader;
use Disrex\SampleDataThemesCore\Api\FixtureInterface;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Framework\Module\Dir\Reader as ModuleDirReader;
use Psr\Log\LoggerInterface;

/**
 * Three-pass import:
 *
 *  1. Insert each variation row as a hidden simple product.
 *  2. Insert each parent row, also as a simple product (placeholder type).
 *  3. Promote each parent to type=configurable, declare its configurable
 *     attributes, and link the children.
 *
 * Translations are applied to parents only — variants stay nameless on the
 * frontend (Magento renders the parent name + selected attribute labels).
 */
class ConfigurableProductFixture implements FixtureInterface
{
    public function __construct(
        private readonly CsvParser $csvParser,
        private readonly ModuleDirReader $moduleReader,
        private readonly LoggerInterface $logger,
        private readonly ProductImporter $productImporter,
        private readonly ConfigurableProductBuilder $builder,
        private readonly LocaleResolver $localeResolver,
        private readonly TranslationLoader $translationLoader,
        private readonly Theme $theme
    ) {
    }

    public function getLabel(): string
    {
        return 'Home & Living configurable products (6, with variants)';
    }

    public function execute(): void
    {
        $base = rtrim($this->moduleReader->getModuleDir('', 'Disrex_SampleDataThemeHomeLiving'), '/');
        $parentsCsv = $base . '/_files/base/configurable_products.csv';
        $variationsCsv = $base . '/_files/base/configurable_variations.csv';

        if (!is_readable($variationsCsv) || !is_readable($parentsCsv)) {
            $this->logger->warning(
                '[disrex/sample-data-themes] Configurable CSVs missing — skipping.'
            );
            return;
        }

        // 1. Import variations as hidden simple products. The CSV uses
        // `child_sku` but the importer wants `sku` — translate before passing.
        foreach ($this->csvParser->parse($variationsCsv) as $row) {
            $childSku = $row['child_sku'] ?? '';
            try {
                $row['sku'] = $childSku;
                $row['type_id'] = 'simple';
                $row['visibility'] = (string) Visibility::VISIBILITY_NOT_VISIBLE;
                $this->productImporter->createOrUpdateBase($row);
            } catch (\Throwable $e) {
                $this->logger->warning(sprintf(
                    '[disrex/sample-data-themes] Variant %s failed: %s',
                    $childSku ?: '?',
                    $e->getMessage()
                ));
            }
        }

        // 2. Import parents (still as simple — promoted in step 3).
        $parents = [];
        foreach ($this->csvParser->parse($parentsCsv) as $row) {
            try {
                $row['type_id'] = 'simple';
                $this->productImporter->createOrUpdateBase($row);
                $parents[$row['sku']] = $row;
            } catch (\Throwable $e) {
                $this->logger->warning(sprintf(
                    '[disrex/sample-data-themes] Configurable parent %s failed: %s',
                    $row['sku'] ?? '?',
                    $e->getMessage()
                ));
            }
        }

        // 3. Group children by parent and promote.
        $childrenByParent = [];
        foreach ($this->csvParser->parse($variationsCsv) as $row) {
            if (!isset($row['parent_sku'], $row['child_sku'])) {
                continue;
            }
            $childrenByParent[$row['parent_sku']][] = $row['child_sku'];
        }

        foreach ($parents as $parentSku => $row) {
            $codes = array_filter(array_map(
                'trim',
                explode(',', $row['configurable_attributes'] ?? '')
            ));
            $children = $childrenByParent[$parentSku] ?? [];
            if ($codes === [] || $children === []) {
                continue;
            }
            try {
                $this->builder->link($parentSku, $children, $codes);
            } catch (\Throwable $e) {
                $this->logger->warning(sprintf(
                    '[disrex/sample-data-themes] Linking %s failed: %s',
                    $parentSku,
                    $e->getMessage()
                ));
            }
            $this->applyTranslations($parentSku);
        }
    }

    public function rollback(): void
    {
        $base = rtrim($this->moduleReader->getModuleDir('', 'Disrex_SampleDataThemeHomeLiving'), '/');

        $variationsCsv = $base . '/_files/base/configurable_variations.csv';
        $parentsCsv = $base . '/_files/base/configurable_products.csv';
        $childSkus = is_readable($variationsCsv)
            ? $this->csvParser->extractColumn($variationsCsv, 'child_sku')
            : [];
        $parentSkus = is_readable($parentsCsv)
            ? $this->csvParser->extractColumn($parentsCsv, 'sku')
            : [];

        // Parents first, then children — deleting the parent first detaches
        // the configurable product correctly before children are dropped.
        $this->productImporter->deleteBySkus(array_merge($parentSkus, $childSkus));
    }

    private function applyTranslations(string $sku): void
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
