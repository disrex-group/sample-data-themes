<?php

declare(strict_types=1);

namespace Disrex\SampleDataThemesCore\Helper\Fixture;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\Data\ProductInterfaceFactory;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Type as ProductType;
use Magento\Catalog\Model\Product\Visibility;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\Eav\Api\AttributeSetRepositoryInterface;
use Magento\Eav\Api\Data\AttributeSetInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\Store;
use Psr\Log\LoggerInterface;

/**
 * Imports catalog products. Handles simple, configurable, grouped, and
 * bundle types via dispatch on the row's `type_id` (defaulting to simple).
 *
 * The importer is opinionated about a few things:
 *
 *  * Multi-value attributes are encoded as comma-separated codes in the
 *    base CSV (e.g. `living,bedroom`); they are resolved via
 *    {@see AttributeImporter::resolveOptionId()}.
 *  * Stock data assumes single-source by default. Themes targeting MSI
 *    deployments should subclass and override `applyStock()`.
 *  * Translations are written in a second pass through
 *    {@see setTranslatedFields()} once the base product exists.
 */
class ProductImporter
{
    /** @var array<string, int> attribute set name => id */
    private array $attributeSetCache = [];

    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
        private readonly ProductInterfaceFactory $productFactory,
        private readonly Product $productModel,
        private readonly AttributeSetRepositoryInterface $attributeSetRepository,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        private readonly StockRegistryInterface $stockRegistry,
        private readonly CategoryImporter $categoryImporter,
        private readonly AttributeImporter $attributeImporter,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Create or update a base product (admin scope, locale-independent
     * fields only). Names / descriptions are written separately via
     * {@see setTranslatedFields()}.
     *
     * @param array<string, string> $row
     */
    public function createOrUpdateBase(array $row): ProductInterface
    {
        $sku = $this->require($row, 'sku');
        $type = $row['type_id'] ?? ProductType::TYPE_SIMPLE;

        try {
            $product = $this->productRepository->get($sku, true, Store::DEFAULT_STORE_ID, true);
        } catch (NoSuchEntityException) {
            $product = $this->productFactory->create();
            $product->setSku($sku);
        }

        $product->setStoreId(Store::DEFAULT_STORE_ID);
        $product->setTypeId($type);
        $product->setAttributeSetId($this->resolveAttributeSetId($row['attribute_set'] ?? 'Default'));
        $product->setVisibility((int) ($row['visibility'] ?? Visibility::VISIBILITY_BOTH));
        $product->setStatus((int) ($row['status'] ?? 1));
        $product->setWebsiteIds($this->resolveWebsiteIds($row));

        if (isset($row['price']) && $row['price'] !== '') {
            $product->setPrice((float) $row['price']);
        }
        if (isset($row['weight']) && $row['weight'] !== '') {
            $product->setWeight((float) $row['weight']);
        }

        // A throwaway placeholder name keeps the save valid; per-locale
        // translations overwrite this immediately afterwards.
        if (!$product->getName()) {
            $product->setName($sku);
        }

        $this->applyCustomAttributes($product, $row);

        if (isset($row['categories']) && $row['categories'] !== '') {
            $paths = array_filter(array_map('trim', explode(',', $row['categories'])));
            $product->setCategoryIds($this->categoryImporter->resolvePathsToIds($paths));
        }

        $product = $this->productRepository->save($product);

        $this->applyStock($product, $row);

        return $product;
    }

    /**
     * Apply translated, storeview-scoped fields.
     *
     * @param array<string, ?string> $fields
     * @param array<int, int> $storeIds
     */
    public function setTranslatedFields(string $sku, array $storeIds, array $fields): void
    {
        if ($storeIds === []) {
            return;
        }
        foreach ($storeIds as $storeId) {
            try {
                $product = $this->productRepository->get($sku, true, $storeId, true);
            } catch (NoSuchEntityException) {
                $this->logger->warning(sprintf(
                    '[disrex/sample-data-themes] Cannot translate %s — not found.',
                    $sku
                ));
                continue;
            }
            $product->setStoreId($storeId);
            foreach ($fields as $field => $value) {
                if ($value === null || $value === '') {
                    continue;
                }
                $product->setData($field, $value);
            }
            $this->productRepository->save($product);
        }
    }

    /**
     * @param array<int, string> $skus
     */
    public function deleteBySkus(array $skus): void
    {
        foreach ($skus as $sku) {
            try {
                $this->productRepository->deleteById($sku);
            } catch (NoSuchEntityException) {
                // Already gone.
            }
        }
    }

    public function resolveAttributeSetId(string $name): int
    {
        if (isset($this->attributeSetCache[$name])) {
            return $this->attributeSetCache[$name];
        }

        $criteria = $this->searchCriteriaBuilder
            ->addFilter('attribute_set_name', $name)
            ->create();
        $list = $this->attributeSetRepository->getList($criteria);
        foreach ($list->getItems() as $set) {
            /** @var AttributeSetInterface $set */
            $this->attributeSetCache[$name] = (int) $set->getAttributeSetId();
            return $this->attributeSetCache[$name];
        }

        // Fall back to the default attribute set so imports never hard-fail.
        $defaultId = (int) $this->productModel->getDefaultAttributeSetId();
        $this->attributeSetCache[$name] = $defaultId;
        return $defaultId;
    }

    /**
     * @param array<string, string> $row
     * @return array<int, int>
     */
    private function resolveWebsiteIds(array $row): array
    {
        if (!empty($row['website_ids'])) {
            return array_map('intval', array_filter(array_map('trim', explode(',', $row['website_ids']))));
        }
        return [1];
    }

    /**
     * @param array<string, string> $row
     */
    private function applyCustomAttributes(ProductInterface $product, array $row): void
    {
        $reservedKeys = [
            'sku', 'type_id', 'attribute_set', 'price', 'weight', 'qty', 'visibility', 'status',
            'categories', 'images', 'website_ids', 'name', 'description', 'short_description',
            'url_key', 'meta_title', 'meta_description', 'meta_keyword', 'meta_keywords',
        ];

        foreach ($row as $key => $value) {
            if (in_array($key, $reservedKeys, true)) {
                continue;
            }
            if ($value === '') {
                continue;
            }

            $resolved = $this->resolveAttributeValue($key, $value);
            $product->setCustomAttribute($key, $resolved);
        }
    }

    /**
     * Translate a CSV cell to the value Magento expects: integer option id
     * for selects/swatches, comma-separated option ids for multiselects,
     * raw string for text-typed attributes.
     */
    private function resolveAttributeValue(string $attributeCode, string $value): mixed
    {
        if (str_contains($value, ',')) {
            $codes = array_filter(array_map('trim', explode(',', $value)));
            $ids = [];
            foreach ($codes as $code) {
                $id = $this->attributeImporter->resolveOptionId($attributeCode, $code);
                if ($id !== null) {
                    $ids[] = $id;
                }
            }
            if ($ids !== []) {
                return implode(',', $ids);
            }
            return $value;
        }

        $id = $this->attributeImporter->resolveOptionId($attributeCode, $value);
        return $id !== null ? $id : $value;
    }

    /**
     * @param array<string, string> $row
     */
    private function applyStock(ProductInterface $product, array $row): void
    {
        $qty = isset($row['qty']) && $row['qty'] !== '' ? (float) $row['qty'] : 100.0;
        $stockItem = $this->stockRegistry->getStockItemBySku($product->getSku());
        $stockItem->setQty($qty);
        $stockItem->setIsInStock($qty > 0);
        $stockItem->setUseConfigManageStock(true);
        $this->stockRegistry->updateStockItemBySku($product->getSku(), $stockItem);
    }

    /**
     * @param array<string, string> $row
     */
    private function require(array $row, string $key): string
    {
        if (!isset($row[$key]) || $row[$key] === '') {
            throw new \InvalidArgumentException(sprintf('Required column "%s" is empty.', $key));
        }
        return $row[$key];
    }
}
