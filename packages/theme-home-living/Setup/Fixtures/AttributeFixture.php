<?php

declare(strict_types=1);

namespace Disrex\SampleDataThemeHomeLiving\Setup\Fixtures;

use Disrex\SampleDataThemeHomeLiving\Model\Theme;
use Disrex\SampleDataThemesCore\Helper\Fixture\AttributeImporter;
use Disrex\SampleDataThemesCore\Helper\Fixture\CsvParser;
use Disrex\SampleDataThemesCore\Helper\Fixture\LocaleResolver;
use Disrex\SampleDataThemesCore\Helper\Fixture\TranslationLoader;
use Disrex\SampleDataThemesCore\Model\Fixture\AbstractCsvFixture;
use Magento\Framework\Module\Dir\Reader as ModuleDirReader;
use Psr\Log\LoggerInterface;

/**
 * Imports the seven Home & Living attributes (material, color_family,
 * fabric, room, style, dimensions, weight_capacity) and applies per-locale
 * labels from `i18n/<locale>/attributes.csv`.
 */
class AttributeFixture extends AbstractCsvFixture
{
    public function __construct(
        CsvParser $csvParser,
        ModuleDirReader $moduleReader,
        LoggerInterface $logger,
        private readonly AttributeImporter $importer,
        private readonly LocaleResolver $localeResolver,
        private readonly TranslationLoader $translationLoader,
        private readonly Theme $theme
    ) {
        parent::__construct($csvParser, $moduleReader, $logger);
    }

    protected function getCsvFilename(): string
    {
        return 'base/attributes.csv';
    }

    protected function getModuleName(): string
    {
        return 'Disrex_SampleDataThemeHomeLiving';
    }

    public function getLabel(): string
    {
        return 'Home & Living attributes (incl. EN/NL translations)';
    }

    protected function processRow(array $row): void
    {
        $this->importer->createOrUpdate($row);
    }

    public function execute(): void
    {
        parent::execute();

        $i18nDir = $this->theme->getFixturesPath() . '/i18n';
        foreach ($this->theme->getSupportedLocales() as $locale) {
            $storeIds = $this->localeResolver->resolveStoreviewIds($locale);
            if ($storeIds === []) {
                continue;
            }
            $rows = $this->translationLoader->loadAllRows(
                $i18nDir,
                $locale,
                $this->theme->getDefaultLocale(),
                'attributes'
            );
            if ($rows === []) {
                continue;
            }
            $this->importer->applyTranslations(
                $rows,
                $storeIds,
                $locale === $this->theme->getDefaultLocale()
            );
        }
    }

    public function rollback(): void
    {
        // Attribute removal is intentionally a no-op: attributes may be
        // shared with other themes or used by entities outside the demo
        // catalog. Operators can drop them manually if they want a clean
        // slate.
    }
}
