<?php

declare(strict_types=1);

namespace Disrex\SampleDataThemesCore\Helper\Fixture;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\ConfigurableProduct\Helper\Product\Options\Factory as ConfigurableOptionsFactory;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable as ConfigurableType;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Framework\Exception\NoSuchEntityException;
use Psr\Log\LoggerInterface;

/**
 * Wires up a configurable product: declares its configurable attributes,
 * binds the variant child SKUs, and saves. Idempotent — re-running with
 * the same set of children leaves the product unchanged.
 */
class ConfigurableProductBuilder
{
    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
        private readonly ConfigurableOptionsFactory $optionsFactory,
        private readonly EavConfig $eavConfig,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @param array<int, string> $childSkus
     * @param array<int, string> $configurableAttributeCodes
     */
    public function link(string $parentSku, array $childSkus, array $configurableAttributeCodes): void
    {
        try {
            $parent = $this->productRepository->get($parentSku, true);
        } catch (NoSuchEntityException) {
            $this->logger->warning(sprintf(
                '[disrex/sample-data-themes] Configurable parent "%s" not found.',
                $parentSku
            ));
            return;
        }

        $parent->setTypeId(ConfigurableType::TYPE_CODE);

        $attributeData = $this->buildAttributeData($configurableAttributeCodes);
        if ($attributeData === []) {
            $this->logger->warning(sprintf(
                '[disrex/sample-data-themes] No valid configurable attributes for "%s".',
                $parentSku
            ));
            return;
        }

        $configurableOptions = $this->optionsFactory->create($attributeData);
        $extension = $parent->getExtensionAttributes();
        $extension->setConfigurableProductOptions($configurableOptions);

        $childIds = $this->resolveChildIds($childSkus);
        $extension->setConfigurableProductLinks($childIds);
        $parent->setExtensionAttributes($extension);

        $this->productRepository->save($parent);
    }

    /**
     * @param array<int, string> $codes
     *
     * @return array<int, array{
     *     attribute_id: int, code: string, label: string,
     *     position: int, values: array<int, mixed>
     * }>
     */
    private function buildAttributeData(array $codes): array
    {
        $data = [];
        foreach ($codes as $position => $code) {
            $attribute = $this->eavConfig->getAttribute('catalog_product', $code);
            if (!$attribute || !$attribute->getId()) {
                continue;
            }
            $data[] = [
                'attribute_id' => (int) $attribute->getId(),
                'code' => $code,
                'label' => $attribute->getStoreLabel() ?: ucfirst($code),
                'position' => (int) $position,
                'values' => [],
            ];
        }
        return $data;
    }

    /**
     * @param array<int, string> $skus
     * @return array<int, int>
     */
    private function resolveChildIds(array $skus): array
    {
        $ids = [];
        foreach ($skus as $sku) {
            try {
                $child = $this->productRepository->get(trim($sku));
                $ids[] = (int) $child->getId();
            } catch (NoSuchEntityException) {
                $this->logger->warning(sprintf(
                    '[disrex/sample-data-themes] Configurable child "%s" not found, skipped.',
                    $sku
                ));
            }
        }
        return $ids;
    }
}
