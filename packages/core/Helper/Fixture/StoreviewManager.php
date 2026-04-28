<?php

declare(strict_types=1);

namespace Disrex\SampleDataThemesCore\Helper\Fixture;

use Disrex\SampleDataThemesCore\Exception\MissingStoreviewException;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\Storage\WriterInterface as ConfigWriter;
use Magento\Store\Api\Data\GroupInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Api\Data\WebsiteInterface;
use Magento\Store\Api\GroupRepositoryInterface;
use Magento\Store\Api\WebsiteRepositoryInterface;
use Magento\Store\Model\GroupFactory;
use Magento\Store\Model\StoreFactory;
use Magento\Store\Model\WebsiteFactory;

/**
 * Detects whether a storeview exists for a given locale and, when asked,
 * creates a sane default (one website + one group + one store) per missing
 * locale.
 *
 * The defaults derived from a locale code `xx_YY` are:
 *
 *   website code: lower-case `yy`     name: upper-case `YY`
 *   group   code: `yy_main`           name: `YY Store`
 *   store   code: lower-case `yy`     name: derived from country code
 *
 * Production-style demos should configure storeviews manually; the
 * auto-create flow exists to make `bin/magento sampledata:theme:deploy
 * --auto-create-storeviews` Just Work on a fresh dev install.
 */
class StoreviewManager
{
    private const CONFIG_LOCALE = 'general/locale/code';

    public function __construct(
        private readonly LocaleResolver $localeResolver,
        private readonly WebsiteRepositoryInterface $websiteRepository,
        private readonly GroupRepositoryInterface $groupRepository,
        private readonly WebsiteFactory $websiteFactory,
        private readonly GroupFactory $groupFactory,
        private readonly StoreFactory $storeFactory,
        private readonly ConfigWriter $configWriter,
        private readonly TypeListInterface $cacheTypeList
    ) {
    }

    /**
     * Ensure at least one storeview exists for $locale.
     *
     * @return array<int, int> The storeview IDs that match.
     *
     * @throws MissingStoreviewException If the locale has no storeview and
     *                                   $autoCreate is false.
     */
    public function ensureStoreviewForLocale(string $locale, bool $autoCreate = false): array
    {
        $existing = $this->localeResolver->resolveStoreviewIds($locale);
        if ($existing !== []) {
            return $existing;
        }

        if (!$autoCreate) {
            throw MissingStoreviewException::forLocale($locale);
        }

        return [$this->createStoreviewForLocale($locale)];
    }

    private function createStoreviewForLocale(string $locale): int
    {
        if (!preg_match('/^([a-z]{2})_([A-Z]{2})$/', $locale, $m)) {
            throw new \InvalidArgumentException(sprintf(
                'Locale "%s" is not in the expected ICU "xx_YY" form.',
                $locale
            ));
        }
        $langCode = $m[1];
        $countryCode = $m[2];

        $website = $this->getOrCreateWebsite($countryCode);
        $group = $this->getOrCreateGroup($website, $countryCode);
        $store = $this->createStore($group, $website, $langCode, $locale);

        $this->configWriter->save(
            self::CONFIG_LOCALE,
            $locale,
            'stores',
            (int) $store->getId()
        );

        $this->cacheTypeList->cleanType('config');

        return (int) $store->getId();
    }

    private function getOrCreateWebsite(string $countryCode): WebsiteInterface
    {
        $code = strtolower($countryCode);
        try {
            return $this->websiteRepository->get($code);
        } catch (\Magento\Framework\Exception\NoSuchEntityException) {
            // Fall through and create.
        }

        $website = $this->websiteFactory->create();
        $website->setCode($code);
        $website->setName(strtoupper($countryCode));
        $website->setDefaultGroupId(0);
        $website->save();

        return $this->websiteRepository->get($code);
    }

    private function getOrCreateGroup(WebsiteInterface $website, string $countryCode): GroupInterface
    {
        $code = strtolower($countryCode) . '_main';
        foreach ($this->groupRepository->getList() as $group) {
            if ($group->getCode() === $code) {
                return $group;
            }
        }

        $group = $this->groupFactory->create();
        $group->setCode($code);
        $group->setName(strtoupper($countryCode) . ' Store');
        $group->setWebsiteId((int) $website->getId());
        $group->setRootCategoryId(2);  // The default root category in stock Magento.
        $group->save();

        // Re-fetch via the repository to have the canonical instance.
        foreach ($this->groupRepository->getList() as $reloaded) {
            if ($reloaded->getCode() === $code) {
                return $reloaded;
            }
        }
        return $group;
    }

    private function createStore(
        GroupInterface $group,
        WebsiteInterface $website,
        string $langCode,
        string $locale
    ): StoreInterface {
        $store = $this->storeFactory->create();
        $store->setCode($langCode);
        $store->setName(sprintf('%s (%s)', strtoupper($langCode), $locale));
        $store->setWebsiteId((int) $website->getId());
        $store->setGroupId((int) $group->getId());
        $store->setIsActive(1);
        $store->save();

        return $store;
    }
}
