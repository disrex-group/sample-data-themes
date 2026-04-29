<?php

declare(strict_types=1);

namespace Disrex\SampleDataThemeHomeLiving\Setup\Fixtures;

use Disrex\SampleDataThemesCore\Helper\Fixture\CsvParser;
use Disrex\SampleDataThemesCore\Helper\Fixture\ProductLinker;
use Disrex\SampleDataThemesCore\Model\Fixture\AbstractCsvFixture;
use Magento\Framework\Module\Dir\Reader as ModuleDirReader;
use Psr\Log\LoggerInterface;

/**
 * Sets related / upsell / crosssell links between products. Runs last
 * because every link target needs to exist by the time we connect them.
 */
class ProductLinksFixture extends AbstractCsvFixture
{
    public function __construct(
        CsvParser $csvParser,
        ModuleDirReader $moduleReader,
        LoggerInterface $logger,
        private readonly ProductLinker $linker
    ) {
        parent::__construct($csvParser, $moduleReader, $logger);
    }

    protected function getCsvFilename(): string
    {
        return 'base/product_links.csv';
    }

    protected function getModuleName(): string
    {
        return 'Disrex_SampleDataThemeHomeLiving';
    }

    public function getLabel(): string
    {
        return 'Home & Living related/upsell/crosssell links';
    }

    protected function processRow(array $row): void
    {
        $sku = $row['sku'] ?? '';
        $type = $row['link_type'] ?? '';
        $linked = array_filter(array_map('trim', explode(',', $row['linked_skus'] ?? '')));
        if ($sku === '' || $type === '' || $linked === []) {
            return;
        }
        $this->linker->setLinks($sku, $type, $linked);
    }

    public function rollback(): void
    {
        // Removing the source products removes their links too.
    }
}
