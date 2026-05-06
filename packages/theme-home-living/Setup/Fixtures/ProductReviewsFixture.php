<?php

declare(strict_types=1);

namespace Disrex\SampleDataThemeHomeLiving\Setup\Fixtures;

use Disrex\SampleDataThemeHomeLiving\Model\Theme;
use Disrex\SampleDataThemesCore\Api\FixtureInterface;
use Disrex\SampleDataThemesCore\Helper\Fixture\LocaleResolver;
use Disrex\SampleDataThemesCore\Helper\Fixture\ProductReviewer;
use Magento\Framework\App\ResourceConnection;
use Psr\Log\LoggerInterface;

/**
 * Generates plausible-looking customer reviews so PDPs and category cards
 * show populated star ratings out of the box. Algorithmic; no CSV input.
 *
 * One pass per visible Home & Living product:
 *   - 2-8 reviews per product (mt_rand seeded for reproducibility)
 *   - star distribution skewed toward 4-5 (matches real e-commerce data)
 *   - locale-appropriate nicknames + bodies (EN on en_US storeviews,
 *     NL on nl_NL storeviews)
 *   - all reviews land approved (status=1) so the storefront aggregates
 *     them into the average-stars bar immediately
 *
 * Idempotent: products that already have reviews are skipped on re-run.
 */
class ProductReviewsFixture implements FixtureInterface
{
    /** Random-seed constant: keeps generated reviews reproducible across deploys. */
    private const SEED = 13373;

    /**
     * Star-count probability bucket. Position N (zero-indexed) is the
     * weight of (N+1)-stars. Heavily skewed toward 4-5 to match how
     * customers actually fill ratings.
     *
     * @var array<int, int>
     */
    private const STAR_WEIGHTS = [
        1 => 1,   // 1★ — rare
        2 => 2,   // 2★ — uncommon
        3 => 7,   // 3★ — sometimes
        4 => 35,  // 4★ — often
        5 => 55,  // 5★ — most common
    ];

    /** @var array<string, list<string>> Locale-keyed nickname pools. */
    private const NICKNAMES = [
        'en_US' => [
            'James M.', 'Sarah K.', 'David L.', 'Emma R.', 'Michael B.',
            'Lisa T.', 'Robert H.', 'Jessica W.', 'Thomas C.', 'Anna P.',
            'Daniel S.', 'Rachel F.', 'Christopher G.', 'Olivia D.', 'Mark J.',
            'Sophie A.', 'Andrew V.', 'Hannah N.', 'Brian Y.', 'Megan E.',
        ],
        'nl_NL' => [
            'S. de Vries', 'J. van den Berg', 'A. Jansen', 'M. Bakker', 'P. Visser',
            'L. de Jong', 'R. Smit', 'K. Mulder', 'E. de Boer', 'T. Hendriks',
            'B. van Dijk', 'F. Vermeulen', 'G. Peters', 'I. de Wit', 'H. Kuipers',
            'Anouk K.', 'Joris M.', 'Femke L.', 'Sven R.', 'Lotte H.',
        ],
    ];

    /**
     * Title and body templates per star rating, per locale. Picked at
     * random; placeholders replaced at generation time. Kept generic so
     * the same pool works across furniture, lighting, and decor without
     * sounding wrong for any specific product type.
     *
     * @var array<string, array<int, list<array{title: string, body: string}>>>
     */
    private const TEMPLATES = [
        'en_US' => [
            5 => [
                ['title' => 'Exactly what I was looking for', 'body' => 'Build quality is excellent and it fits the space perfectly. Would recommend.'],
                ['title' => 'Beautiful piece', 'body' => 'Even nicer in person than in the photos. Solid construction, great finish.'],
                ['title' => 'Worth every euro', 'body' => 'Delivered well-packed and assembled in minutes. Looks great in our living room.'],
                ['title' => 'Highly recommended', 'body' => 'Quality materials and a clean, modern look. Five stars from me.'],
                ['title' => 'Love it', 'body' => 'Exceeded expectations on every front. Will definitely shop here again.'],
            ],
            4 => [
                ['title' => 'Very good', 'body' => 'Solid product, looks great. Took off one star because delivery was a bit slow but the quality is there.'],
                ['title' => 'Happy with the purchase', 'body' => 'Looks lovely and feels well-made. Slightly different shade than expected but still beautiful.'],
                ['title' => 'Good buy', 'body' => 'Comfortable, sturdy and stylish. Minor scuff out of the box but customer service handled it quickly.'],
                ['title' => 'Solid choice', 'body' => 'Quality is great and assembly was straightforward. Would buy again.'],
            ],
            3 => [
                ['title' => 'It is okay', 'body' => 'Does the job but the finish is not quite as premium as I hoped. Fair for the price.'],
                ['title' => 'Average', 'body' => 'Fine for everyday use, nothing remarkable. Looks alright in our space.'],
                ['title' => 'Not bad', 'body' => 'Took longer than expected to assemble. Final result is acceptable.'],
            ],
            2 => [
                ['title' => 'Disappointed', 'body' => 'The colour does not match the website photos and one corner arrived damaged.'],
                ['title' => 'Could be better', 'body' => 'Quality feels lighter than I expected at this price point.'],
            ],
            1 => [
                ['title' => 'Not for me', 'body' => 'Returned it. Quality was not what I expected from the description.'],
            ],
        ],
        'nl_NL' => [
            5 => [
                ['title' => 'Precies wat ik zocht', 'body' => 'De kwaliteit is uitstekend en het past perfect in de ruimte. Aanrader.'],
                ['title' => 'Prachtig stuk', 'body' => 'Nog mooier in het echt dan op de foto. Stevig en een fijne afwerking.'],
                ['title' => 'Elke euro waard', 'body' => 'Goed verpakt geleverd en in een paar minuten in elkaar gezet. Staat geweldig in onze woonkamer.'],
                ['title' => 'Echt een aanrader', 'body' => 'Goede materialen en een strakke, moderne uitstraling. Vijf sterren.'],
                ['title' => 'Helemaal blij mee', 'body' => 'Overtreft de verwachtingen op elk vlak. Bestel hier zeker weer.'],
            ],
            4 => [
                ['title' => 'Erg tevreden', 'body' => 'Stevig product, mooie uitstraling. Eén ster eraf omdat de levering wat langer duurde, maar de kwaliteit is goed.'],
                ['title' => 'Blij met de aankoop', 'body' => 'Ziet er prachtig uit en voelt goed gemaakt aan. Iets andere kleur dan verwacht, maar nog steeds mooi.'],
                ['title' => 'Goede koop', 'body' => 'Comfortabel, stevig en stijlvol. Klein krasje uit de doos maar de klantenservice loste het snel op.'],
                ['title' => 'Goede keuze', 'body' => 'Kwaliteit is prima en montage was eenvoudig. Zou ik weer kopen.'],
            ],
            3 => [
                ['title' => 'Het is oké', 'body' => 'Doet wat het moet doen, maar de afwerking is niet helemaal zoals ik hoopte. Redelijk voor de prijs.'],
                ['title' => 'Gemiddeld', 'body' => 'Prima voor dagelijks gebruik, niets bijzonders. Past wel in ons interieur.'],
                ['title' => 'Niet slecht', 'body' => 'Montage duurde langer dan verwacht. Het eindresultaat is acceptabel.'],
            ],
            2 => [
                ['title' => 'Teleurstellend', 'body' => 'De kleur klopt niet met de foto op de website en één hoek kwam beschadigd aan.'],
                ['title' => 'Kan beter', 'body' => 'Kwaliteit voelt lichter dan ik bij deze prijs verwacht had.'],
            ],
            1 => [
                ['title' => 'Niet wat ik zocht', 'body' => 'Heb het teruggestuurd. Kwaliteit was niet zoals beschreven.'],
            ],
        ],
    ];

    public function __construct(
        private readonly ProductReviewer $reviewer,
        private readonly LocaleResolver $localeResolver,
        private readonly ResourceConnection $resource,
        private readonly Theme $theme,
        private readonly LoggerInterface $logger
    ) {
    }

    public function getLabel(): string
    {
        return 'Home & Living product reviews (algorithmic, EN/NL)';
    }

    public function execute(): void
    {
        // Map locale → store_ids once, so we can route review->stores
        // correctly per generated review.
        $localeStores = [];
        foreach ($this->theme->getSupportedLocales() as $locale) {
            $storeIds = $this->localeResolver->resolveStoreviewIds($locale);
            if ($storeIds !== []) {
                $localeStores[$locale] = $storeIds;
            }
        }
        if ($localeStores === []) {
            $this->logger->warning(
                '[disrex/sample-data-themes] ProductReviewsFixture: no supported locales mapped to storeviews — skipping.'
            );
            return;
        }

        // Make sure every product-entity rating dimension is active and
        // mapped to every store this theme touches. Without this,
        // Magento's review aggregator silently drops votes on
        // unmapped ratings (e.g. on a stock install Quality/Value/Price
        // exist but aren't mapped past store 0), so PDPs render
        // rating_summary=0 even though votes were inserted.
        $allStoreIds = array_unique(array_merge(...array_values($localeStores)));
        $this->reviewer->ensureRatingsAssignedToStores($allStoreIds);

        $skus = $this->fetchVisibleProductSkus();
        $generated = 0;
        $skipped = 0;
        $rng = mt_srand(self::SEED);

        foreach ($skus as $sku) {
            if ($this->reviewer->hasReviews($sku)) {
                $skipped++;
                continue;
            }

            // 2-8 reviews per product. Bias toward 3-5 so most products
            // look "established" without flooding with hundreds of rows.
            $reviewCount = $this->randomInt(2, 8);

            // Each review goes to ONE locale's stores so the body text
            // matches what visitors of that storeview will read. The
            // average shows up across all stores via the shared
            // entity_pk_value in review_entity_summary.
            foreach (range(1, $reviewCount) as $_) {
                $stars = $this->weightedStarPick();
                $locale = $this->randomLocale(array_keys($localeStores));
                $storeIds = $localeStores[$locale];
                $template = $this->randomTemplate($locale, $stars);
                $nickname = $this->randomNickname($locale);

                try {
                    $this->reviewer->addReview(
                        $sku,
                        $stars,
                        $nickname,
                        $template['title'],
                        $template['body'],
                        $storeIds
                    );
                    $generated++;
                } catch (\Throwable $e) {
                    $this->logger->warning(sprintf(
                        '[disrex/sample-data-themes] ProductReviewsFixture: review for "%s" failed: %s',
                        $sku,
                        $e->getMessage()
                    ));
                }
            }
        }

        $this->logger->info(sprintf(
            '[disrex/sample-data-themes] ProductReviewsFixture: %d reviews generated across %d SKUs (%d already had reviews, skipped).',
            $generated,
            count($skus) - $skipped,
            $skipped
        ));
    }

    public function rollback(): void
    {
        // Removing the source products cascades through the review tables
        // via Magento's built-in foreign keys. Nothing extra to do here.
    }

    /**
     * @return array<int, string> All visibility-4 (visible-individually)
     *                            DRX-HL- SKUs, including configurable
     *                            parents and virtual giftcards.
     */
    private function fetchVisibleProductSkus(): array
    {
        $conn = $this->resource->getConnection();
        $select = $conn->select()
            ->from(['cpe' => $this->resource->getTableName('catalog_product_entity')], ['sku'])
            ->joinInner(
                ['v' => $this->resource->getTableName('catalog_product_entity_int')],
                'v.entity_id = cpe.entity_id',
                []
            )
            ->joinInner(
                ['ea' => $this->resource->getTableName('eav_attribute')],
                'ea.attribute_id = v.attribute_id',
                []
            )
            ->where('cpe.sku LIKE ?', $this->theme->getSkuPrefix() . '%')
            ->where('ea.attribute_code = ?', 'visibility')
            ->where('v.value = ?', 4);
        return $conn->fetchCol($select);
    }

    private function weightedStarPick(): int
    {
        $total = array_sum(self::STAR_WEIGHTS);
        $r = $this->randomInt(1, $total);
        $running = 0;
        foreach (self::STAR_WEIGHTS as $stars => $weight) {
            $running += $weight;
            if ($r <= $running) {
                return $stars;
            }
        }
        return 5;
    }

    /**
     * @param array<int, string> $locales
     */
    private function randomLocale(array $locales): string
    {
        return $locales[$this->randomInt(0, count($locales) - 1)];
    }

    private function randomNickname(string $locale): string
    {
        $pool = self::NICKNAMES[$locale] ?? self::NICKNAMES['en_US'];
        return $pool[$this->randomInt(0, count($pool) - 1)];
    }

    /**
     * @return array{title: string, body: string}
     */
    private function randomTemplate(string $locale, int $stars): array
    {
        $pool = self::TEMPLATES[$locale][$stars] ?? self::TEMPLATES['en_US'][$stars] ?? [];
        if ($pool === []) {
            return ['title' => 'Review', 'body' => ''];
        }
        return $pool[$this->randomInt(0, count($pool) - 1)];
    }

    /** Thin wrapper around mt_rand so the SEED actually kicks in. */
    private function randomInt(int $min, int $max): int
    {
        return mt_rand($min, $max);
    }
}
