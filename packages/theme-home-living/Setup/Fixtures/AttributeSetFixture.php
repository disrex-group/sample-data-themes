<?php

declare(strict_types=1);

namespace Disrex\SampleDataThemeHomeLiving\Setup\Fixtures;

use Disrex\SampleDataThemesCore\Helper\Fixture\AttributeSetImporter;
use Disrex\SampleDataThemesCore\Helper\Fixture\CsvParser;
use Disrex\SampleDataThemesCore\Model\Fixture\AbstractCsvFixture;
use Magento\Framework\Module\Dir\Reader as ModuleDirReader;
use Psr\Log\LoggerInterface;

/**
 * Creates the four Home & Living attribute sets (furniture, lighting,
 * textiles, decor) and assigns the relevant attributes to each.
 */
class AttributeSetFixture extends AbstractCsvFixture
{
    public function __construct(
        CsvParser $csvParser,
        ModuleDirReader $moduleReader,
        LoggerInterface $logger,
        private readonly AttributeSetImporter $importer
    ) {
        parent::__construct($csvParser, $moduleReader, $logger);
    }

    protected function getCsvFilename(): string
    {
        return 'base/attribute_sets.csv';
    }

    protected function getModuleName(): string
    {
        return 'Disrex_SampleDataThemeHomeLiving';
    }

    public function getLabel(): string
    {
        return 'Home & Living attribute sets';
    }

    protected function processRow(array $row): void
    {
        $name = $row['name'] ?? '';
        $group = $row['group'] ?? 'Home & Living';
        $codes = array_filter(array_map('trim', explode(',', $row['attribute_codes'] ?? '')));
        if ($name === '' || $codes === []) {
            return;
        }
        $this->importer->getOrCreate($name);
        $this->importer->assignAttributes($name, $group, $codes);
    }
}
