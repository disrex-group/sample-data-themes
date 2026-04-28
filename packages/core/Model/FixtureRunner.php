<?php

declare(strict_types=1);

namespace Disrex\SampleDataThemesCore\Model;

use Disrex\SampleDataThemesCore\Api\FixtureInterface;
use Disrex\SampleDataThemesCore\Api\ThemeInterface;
use Magento\Framework\ObjectManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Executes a theme's fixtures in declared order (and reverse order on
 * rollback). Failures in one fixture do not abort the run — the runner logs
 * the error and continues, since a partial install is usually more useful
 * than nothing.
 */
class FixtureRunner
{
    public function __construct(
        private readonly ObjectManagerInterface $objectManager,
        private readonly LoggerInterface $logger
    ) {
    }

    public function run(ThemeInterface $theme, ?OutputInterface $output = null): RunResult
    {
        $result = new RunResult($theme->getCode());

        foreach ($theme->getFixtures() as $fixtureClass) {
            $output?->writeln(sprintf('  <comment>→</comment> %s', $fixtureClass));
            try {
                $fixture = $this->createFixture($fixtureClass);
                $fixture->execute();
                $result->addSuccess($fixtureClass, $fixture->getLabel());
                $output?->writeln(sprintf('    <info>✓ %s</info>', $fixture->getLabel()));
            } catch (\Throwable $e) {
                $this->logger->error(
                    sprintf(
                        '[disrex/sample-data-themes] Fixture %s failed: %s',
                        $fixtureClass,
                        $e->getMessage()
                    ),
                    ['exception' => $e, 'theme' => $theme->getCode()]
                );
                $result->addFailure($fixtureClass, $e->getMessage());
                $output?->writeln(sprintf('    <error>✗ %s</error>', $e->getMessage()));
            }
        }

        return $result;
    }

    public function rollback(ThemeInterface $theme, ?OutputInterface $output = null): RunResult
    {
        $result = new RunResult($theme->getCode());

        foreach (array_reverse($theme->getFixtures()) as $fixtureClass) {
            $output?->writeln(sprintf('  <comment>↶</comment> %s', $fixtureClass));
            try {
                $fixture = $this->createFixture($fixtureClass);
                $fixture->rollback();
                $result->addSuccess($fixtureClass, 'rollback: ' . $fixture->getLabel());
                $output?->writeln(sprintf('    <info>✓ rolled back: %s</info>', $fixture->getLabel()));
            } catch (\Throwable $e) {
                $this->logger->error(
                    sprintf(
                        '[disrex/sample-data-themes] Rollback of %s failed: %s',
                        $fixtureClass,
                        $e->getMessage()
                    ),
                    ['exception' => $e, 'theme' => $theme->getCode()]
                );
                $result->addFailure($fixtureClass, $e->getMessage());
                $output?->writeln(sprintf('    <error>✗ %s</error>', $e->getMessage()));
            }
        }

        return $result;
    }

    /**
     * Indirected so tests can override fixture instantiation without
     * requiring a real ObjectManager.
     */
    protected function createFixture(string $fixtureClass): FixtureInterface
    {
        /** @var FixtureInterface $instance */
        $instance = $this->objectManager->create($fixtureClass);

        if (!$instance instanceof FixtureInterface) {
            throw new \LogicException(sprintf(
                'Fixture class "%s" must implement %s.',
                $fixtureClass,
                FixtureInterface::class
            ));
        }
        return $instance;
    }
}
