<?php

declare(strict_types=1);

namespace Disrex\SampleDataThemeHomeLiving\Setup\Fixtures;

use Disrex\SampleDataThemeHomeLiving\Model\Theme;
use Disrex\SampleDataThemesCore\Helper\Fixture\CategoryImporter;
use Disrex\SampleDataThemesCore\Helper\Fixture\CsvParser;
use Disrex\SampleDataThemesCore\Helper\Fixture\LocaleResolver;
use Disrex\SampleDataThemesCore\Helper\Fixture\TranslationLoader;
use Disrex\SampleDataThemesCore\Model\Fixture\AbstractCsvFixture;
use Magento\Framework\Module\Dir\Reader as ModuleDirReader;
use Psr\Log\LoggerInterface;

/**
 * Creates the 22 Home & Living categories from `base/categories.csv`,
 * then writes per-locale name / description / url_key from the i18n CSVs.
 */
class CategoryFixture extends AbstractCsvFixture
{
    public function __construct(
        CsvParser $csvParser,
        ModuleDirReader $moduleReader,
        LoggerInterface $logger,
        private readonly CategoryImporter $importer,
        private readonly LocaleResolver $localeResolver,
        private readonly TranslationLoader $translationLoader,
        private readonly Theme $theme
    ) {
        parent::__construct($csvParser, $moduleReader, $logger);
    }

    protected function getCsvFilename(): string
    {
        return 'base/categories.csv';
    }

    protected function getModuleName(): string
    {
        return 'Disrex_SampleDataThemeHomeLiving';
    }

    public static function alias(): ?string
    {
        return 'categories';
    }

    public function getLabel(): string
    {
        return 'Home & Living categories (incl. EN/NL translations)';
    }

    protected function processRow(array $row): void
    {
        $this->importer->createOrUpdateBase($row);
    }

    public function execute(): void
    {
        parent::execute();
        $this->applyTranslations();
    }

    public function rollback(): void
    {
        $paths = $this->csvParser->extractColumn($this->getFixtureFilePath(), 'path');
        $this->importer->deleteByPaths($paths);
    }

    private function applyTranslations(): void
    {
        $i18nDir = $this->theme->getFixturesPath() . '/i18n';
        foreach ($this->theme->getSupportedLocales() as $locale) {
            $storeIds = $this->localeResolver->resolveStoreviewIds($locale);
            if ($storeIds === []) {
                continue;
            }
            $rows = $this->translationLoader->load($i18nDir, $locale, 'categories', 'path');
            foreach ($rows as $path => $row) {
                $this->importer->applyTranslation($path, $row, $storeIds);
            }
        }
    }
}
