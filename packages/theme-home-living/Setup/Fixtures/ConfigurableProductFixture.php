<?php

declare(strict_types=1);

namespace Disrex\SampleDataThemeHomeLiving\Setup\Fixtures;

use Disrex\SampleDataThemeHomeLiving\Model\Theme;
use Disrex\SampleDataThemesCore\Api\FixtureInterface;
use Disrex\SampleDataThemesCore\Helper\Fixture\AttributeImporter;
use Disrex\SampleDataThemesCore\Helper\Fixture\BulkVariantInserter;
use Disrex\SampleDataThemesCore\Helper\Fixture\ConfigurableProductBuilder;
use Disrex\SampleDataThemesCore\Helper\Fixture\CsvParser;
use Disrex\SampleDataThemesCore\Helper\Fixture\LocaleResolver;
use Disrex\SampleDataThemesCore\Helper\Fixture\ProductImporter;
use Disrex\SampleDataThemesCore\Helper\Fixture\TranslationLoader;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Framework\App\ResourceConnection;
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
        private readonly Theme $theme,
        private readonly BulkVariantInserter $bulkInserter,
        private readonly AttributeImporter $attributeImporter,
        private readonly ResourceConnection $resourceConnection
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

        // 1. Import variants via the bulk inserter — direct EAV INSERT
        // batches that bypass ProductRepository::save(). Variants are
        // hidden (visibility=1) so they don't need URL rewrites of their
        // own and don't need per-variant translations either; the parent
        // owns the storefront URL and the configurable PDP renders
        // "<parent name> — <swatch label>" using the parent's localized
        // name. End result: 750 variants in ~5-10 seconds instead of
        // ~37 minutes.
        //
        // We resolve attribute_set_id and axis option_id once each via
        // ProductImporter / AttributeImporter caches, then hand the
        // inserter a flat list of "ready to insert" payloads.
        $existingSkus = $this->fetchExistingSkus();
        $variantPayloads = [];
        $skippedVariants = 0;
        $websiteIds = $this->fetchAllWebsiteIds();

        foreach ($this->csvParser->parse($variationsCsv) as $row) {
            $childSku = trim((string) ($row['child_sku'] ?? ''));
            if ($childSku === '') {
                continue;
            }
            if (in_array($childSku, $existingSkus, true)) {
                // Idempotent re-run: skip variants that already exist.
                $skippedVariants++;
                continue;
            }
            $parentSku = trim((string) ($row['parent_sku'] ?? ''));
            $configCodes = $this->configurableAttributesForParent($parentSku, $variationsCsv, $parentsCsv);
            // Use the FIRST declared configurable axis as the variant's
            // distinguishing attribute. (Most parents have a single axis;
            // for multi-axis parents we still pick one to write here, and
            // ConfigurableProductBuilder later figures out the rest from
            // each variant's full attribute set.)
            $axisCode = $configCodes[0] ?? null;
            if (!$axisCode) {
                $skippedVariants++;
                continue;
            }
            $axisValue = trim((string) ($row[$axisCode] ?? ''));
            if ($axisValue === '') {
                $skippedVariants++;
                continue;
            }
            $optionId = $this->attributeImporter->resolveOptionId($axisCode, $axisValue);
            if ($optionId === null) {
                $skippedVariants++;
                continue;
            }
            $attrSetName = $row['attribute_set'] ?? 'furniture';
            $attrSetId = $this->productImporter->resolveAttributeSetId($attrSetName);
            $variantPayloads[] = [
                'sku' => $childSku,
                'attribute_set_id' => $attrSetId,
                'name' => $childSku, // placeholder; configurable PDP renders parent name
                'price' => (float) ($row['price'] ?? 0),
                'qty' => (int) ($row['qty'] ?? 0),
                'axis_code' => $axisCode,
                'axis_option_id' => (int) $optionId,
                'website_ids' => $websiteIds,
            ];
        }

        if ($variantPayloads !== []) {
            $this->bulkInserter->insert($variantPayloads);
        }
        $this->logger->info(sprintf(
            '[disrex/sample-data-themes] ConfigurableProductFixture: %d variants inserted, %d skipped',
            count($variantPayloads),
            $skippedVariants
        ));

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

    /**
     * @return array<int, string>  All DRX-HL- SKUs already in the catalog.
     */
    private function fetchExistingSkus(): array
    {
        $conn = $this->resourceConnection->getConnection();
        return $conn->fetchCol(
            $conn->select()
                ->from(
                    $this->resourceConnection->getTableName('catalog_product_entity'),
                    'sku'
                )
                ->where('sku LIKE ?', 'DRX-HL-%')
        );
    }

    /**
     * @return array<int, int>  Active website ids the variants should belong to.
     */
    private function fetchAllWebsiteIds(): array
    {
        $conn = $this->resourceConnection->getConnection();
        return array_map('intval', $conn->fetchCol(
            $conn->select()
                ->from(['w' => $this->resourceConnection->getTableName('store_website')], ['website_id'])
                ->joinInner(
                    ['s' => $this->resourceConnection->getTableName('store')],
                    's.website_id = w.website_id',
                    []
                )
                ->where('w.website_id != ?', 0)
                ->where('s.store_id != ?', 0)
                ->distinct()
        ));
    }

    /**
     * @return array<int, string>  Configurable attribute codes for the given parent SKU.
     */
    private function configurableAttributesForParent(string $parentSku, string $variationsCsv, string $parentsCsv): array
    {
        // Cache per-fixture-run (set on first call); avoids re-parsing the
        // parents CSV for every variant row.
        if (!isset($this->configAttrCache)) {
            $this->configAttrCache = [];
            foreach ($this->csvParser->parse($parentsCsv) as $r) {
                $sku = trim((string) ($r['sku'] ?? ''));
                if ($sku === '') {
                    continue;
                }
                $codes = array_filter(array_map('trim', explode(',', $r['configurable_attributes'] ?? '')));
                $this->configAttrCache[$sku] = array_values($codes);
            }
        }
        return $this->configAttrCache[$parentSku] ?? [];
    }

    /** @var array<string, array<int, string>>|null */
    private ?array $configAttrCache = null;

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
