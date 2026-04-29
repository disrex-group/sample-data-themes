<?php

declare(strict_types=1);

namespace Disrex\SampleDataThemesCore\Helper\Fixture;

use Magento\Catalog\Api\Data\ProductAttributeMediaGalleryEntryInterfaceFactory;
use Magento\Catalog\Api\ProductAttributeMediaGalleryManagementInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\ConfigurableProduct\Helper\Product\Options\Factory as ConfigurableOptionsFactory;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable as ConfigurableType;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Framework\Api\Data\ImageContentInterfaceFactory;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Filesystem;
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
        private readonly LoggerInterface $logger,
        private readonly ProductAttributeMediaGalleryManagementInterface $galleryManagement,
        private readonly ProductAttributeMediaGalleryEntryInterfaceFactory $galleryEntryFactory,
        private readonly ImageContentInterfaceFactory $imageContentFactory,
        private readonly Filesystem $filesystem
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

        $children = $this->loadChildren($childSkus);
        if ($children === []) {
            $this->logger->warning(sprintf(
                '[disrex/sample-data-themes] Configurable "%s" has no resolvable children.',
                $parentSku
            ));
            return;
        }

        $parent->setTypeId(ConfigurableType::TYPE_CODE);

        $attributeData = $this->buildAttributeData($configurableAttributeCodes, $children);
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

        $childIds = array_map(
            static fn ($child) => (int) $child->getId(),
            $children
        );
        $extension->setConfigurableProductLinks($childIds);
        $parent->setExtensionAttributes($extension);

        $this->productRepository->save($parent);

        // Inherit after the parent save so the parent has a stable id and
        // the gallery API can attach to it.
        $mediaRoot = $this->filesystem
            ->getDirectoryRead(DirectoryList::MEDIA)
            ->getAbsolutePath('catalog/product');
        $this->inheritFirstChildImage($parent, $children, $mediaRoot);
    }

    /**
     * Configurable parents in Magento can ship without their own image —
     * the storefront falls back to a placeholder until the visitor picks a
     * variant. That's a poor first impression on a category page or PDP, so
     * we attach a copy of the first child's main image to the parent.
     *
     * Setting `$parent->setData('image', '/s/o/foo.jpg')` directly would
     * be silently rejected by Magento's image attribute backend model,
     * which only accepts values written through the gallery API. So we
     * read the actual file off disk (using the absolute pub/media path
     * from the child's gallery) and push it through GalleryManagement,
     * setting the `image / small_image / thumbnail` types at the same
     * time. The file is stored under a new dispersion path; storage cost
     * is one extra image per configurable, which is acceptable for
     * sample-data fixtures.
     *
     * @param array<int, \Magento\Catalog\Api\Data\ProductInterface> $children
     */
    private function inheritFirstChildImage(
        \Magento\Catalog\Api\Data\ProductInterface $parent,
        array $children,
        string $mediaCatalogProductRoot
    ): void {
        $existingEntries = (array) $parent->getMediaGalleryEntries();
        if ($existingEntries !== []) {
            // Idempotent re-run: parent already has gallery rows.
            return;
        }

        foreach ($children as $child) {
            try {
                $fresh = $this->productRepository->get(
                    (string) $child->getSku(),
                    false,
                    null,
                    true
                );
            } catch (NoSuchEntityException) {
                continue;
            }
            $entries = (array) $fresh->getMediaGalleryEntries();
            if ($entries === []) {
                continue;
            }
            $first = $entries[0];
            $relative = (string) $first->getFile();
            if ($relative === '') {
                continue;
            }

            $absolute = rtrim($mediaCatalogProductRoot, '/') . '/' . ltrim($relative, '/');
            if (!is_readable($absolute)) {
                $this->logger->warning(sprintf(
                    '[disrex/sample-data-themes] Configurable parent %s: source image %s not readable; parent will have no image.',
                    $parent->getSku(),
                    $absolute
                ));
                continue;
            }

            try {
                $bytes = file_get_contents($absolute);
                if ($bytes === false) {
                    continue;
                }
                $imageContent = $this->imageContentFactory->create();
                $imageContent->setBase64EncodedData(base64_encode($bytes));
                $imageContent->setType($this->guessMimeType($absolute));
                $imageContent->setName(basename($absolute));

                $entry = $this->galleryEntryFactory->create();
                $entry->setMediaType('image');
                $entry->setLabel(pathinfo($absolute, PATHINFO_FILENAME));
                $entry->setPosition(1);
                $entry->setDisabled(false);
                $entry->setTypes(['image', 'small_image', 'thumbnail']);
                $entry->setContent($imageContent);

                $this->galleryManagement->create((string) $parent->getSku(), $entry);
            } catch (\Throwable $e) {
                $this->logger->warning(sprintf(
                    '[disrex/sample-data-themes] Configurable parent %s: failed to inherit child image %s: %s',
                    $parent->getSku(),
                    $absolute,
                    $e->getMessage()
                ), ['exception' => $e]);
            }
            return;
        }
    }

    private function guessMimeType(string $path): string
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        return match ($ext) {
            'png' => 'image/png',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            default => 'image/jpeg',
        };
    }

    /**
     * @param array<int, string> $codes
     * @param array<int, \Magento\Catalog\Api\Data\ProductInterface> $children
     *
     * @return array<int, array{
     *     attribute_id: int, code: string, label: string,
     *     position: int, values: array<int, array{value_index: int}>
     * }>
     */
    private function buildAttributeData(array $codes, array $children): array
    {
        $data = [];
        foreach ($codes as $position => $code) {
            $attribute = $this->eavConfig->getAttribute('catalog_product', $code);
            if (!$attribute || !$attribute->getId()) {
                continue;
            }

            // Collect distinct option IDs that the children actually use for
            // this configurable axis. Magento needs these in the
            // configurable option payload — without them the save fails
            // with "Option values are not specified".
            $valueIds = [];
            foreach ($children as $child) {
                $optionId = $child->getData($code);
                if ($optionId !== null && $optionId !== '' && !in_array((int) $optionId, $valueIds, true)) {
                    $valueIds[] = (int) $optionId;
                }
            }

            $values = [];
            foreach ($valueIds as $valueId) {
                $values[] = ['value_index' => $valueId];
            }

            $data[] = [
                'attribute_id' => (int) $attribute->getId(),
                'code' => $code,
                'label' => $attribute->getStoreLabel() ?: ucfirst($code),
                'position' => (int) $position,
                'values' => $values,
            ];
        }
        return $data;
    }

    /**
     * @param array<int, string> $skus
     * @return array<int, \Magento\Catalog\Api\Data\ProductInterface>
     */
    private function loadChildren(array $skus): array
    {
        $children = [];
        foreach ($skus as $sku) {
            try {
                $children[] = $this->productRepository->get(trim($sku));
            } catch (NoSuchEntityException) {
                $this->logger->warning(sprintf(
                    '[disrex/sample-data-themes] Configurable child "%s" not found, skipped.',
                    $sku
                ));
            }
        }
        return $children;
    }
}
