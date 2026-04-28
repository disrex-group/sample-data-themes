<?php

declare(strict_types=1);

namespace Disrex\SampleDataThemesCore\Model\Fixture;

use Disrex\SampleDataThemesCore\Api\FixtureInterface;
use Disrex\SampleDataThemesCore\Helper\Fixture\CsvParser;
use Magento\Framework\Module\Dir\Reader as ModuleDirReader;
use Psr\Log\LoggerInterface;

/**
 * Convenience base class for CSV-driven fixtures. Subclasses implement
 * processRow() and declare which CSV file (relative to the theme's
 * `_files/` directory) and module they belong to.
 *
 * Row-level errors are logged but do not abort the fixture — partial imports
 * are usually more useful than a hard failure on the first bad row.
 */
abstract class AbstractCsvFixture implements FixtureInterface
{
    public function __construct(
        protected readonly CsvParser $csvParser,
        protected readonly ModuleDirReader $moduleReader,
        protected readonly LoggerInterface $logger
    ) {
    }

    /**
     * CSV path relative to the theme module's `_files/` directory.
     * e.g. `base/simple_products.csv`.
     */
    abstract protected function getCsvFilename(): string;

    /**
     * Magento module name owning the fixture file, e.g.
     * `Disrex_SampleDataThemeHomeLiving`.
     */
    abstract protected function getModuleName(): string;

    /**
     * Apply a single parsed row.
     *
     * @param array<string, string> $row
     */
    abstract protected function processRow(array $row): void;

    public function execute(): void
    {
        $path = $this->getFixtureFilePath();
        if (!is_readable($path)) {
            $this->logger->warning(sprintf(
                '[disrex/sample-data-themes] Skipping %s — file not readable: %s',
                static::class,
                $path
            ));
            return;
        }

        foreach ($this->csvParser->parse($path) as $row) {
            try {
                $this->processRow($row);
            } catch (\Throwable $e) {
                $this->logger->warning(sprintf(
                    '[disrex/sample-data-themes] Row failed in %s: %s — %s',
                    static::class,
                    json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    $e->getMessage()
                ), ['exception' => $e]);
            }
        }
    }

    /**
     * Default rollback is a no-op. Subclasses that can identify their own
     * rows (e.g. by SKU) should override this.
     */
    public function rollback(): void
    {
    }

    protected function getFixtureFilePath(): string
    {
        $base = $this->moduleReader->getModuleDir('', $this->getModuleName());
        return rtrim($base, '/') . '/_files/' . ltrim($this->getCsvFilename(), '/');
    }
}
