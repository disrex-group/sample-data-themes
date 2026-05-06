<?php

declare(strict_types=1);

namespace Disrex\SampleDataThemesCore\Helper\Fixture;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Review\Model\RatingFactory;
use Magento\Review\Model\Review;
use Magento\Review\Model\ReviewFactory;
use Psr\Log\LoggerInterface;

/**
 * Creates approved customer reviews on products for sample-data themes.
 *
 * Behaviour:
 *   - Reviews are saved through Magento's Review model so its
 *     `aggregate()` step recomputes review_entity_summary — that's what
 *     the storefront reads to render the average-stars bar on PDPs and
 *     category cards.
 *   - Rating votes are applied per detected rating dimension (Quality,
 *     Price, Value on a stock install). The same star-count is applied
 *     to every dimension for sample data; that mirrors how casual
 *     customers fill the form anyway.
 *   - All reviews land approved (status=1) and visible on every store
 *     id passed to addReview().
 *
 * Idempotency: each call to addReview() inserts a new review row. The
 * fixture using this helper is responsible for skipping products that
 * already have reviews — see ProductReviewsFixture for the existence
 * check.
 */
class ProductReviewer
{
    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
        private readonly ReviewFactory $reviewFactory,
        private readonly RatingFactory $ratingFactory,
        private readonly ResourceConnection $resource,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Append a single review to a product.
     *
     * @param string $sku       The product to review.
     * @param int $stars        1-5; applied to every rating dimension.
     * @param string $nickname  Author display name.
     * @param string $title     Review headline.
     * @param string $body      Review body / detail text.
     * @param array<int> $storeIds  Store ids the review should be visible on.
     */
    public function addReview(
        string $sku,
        int $stars,
        string $nickname,
        string $title,
        string $body,
        array $storeIds
    ): void {
        if ($stars < 1 || $stars > 5) {
            throw new \InvalidArgumentException("stars must be 1-5, got $stars");
        }
        if ($storeIds === []) {
            return;
        }

        try {
            $product = $this->productRepository->get($sku);
        } catch (NoSuchEntityException) {
            $this->logger->warning(sprintf(
                '[disrex/sample-data-themes] ProductReviewer: SKU "%s" not found.',
                $sku
            ));
            return;
        }

        /** @var Review $review */
        $review = $this->reviewFactory->create();
        $review->setEntityId($review->getEntityIdByCode(Review::ENTITY_PRODUCT_CODE));
        $review->setEntityPkValue((int) $product->getId());
        $review->setStatusId(Review::STATUS_APPROVED);
        $review->setTitle($title);
        $review->setDetail($body);
        $review->setNickname($nickname);
        $review->setStoreId($storeIds[0]);
        $review->setStores($storeIds);
        $review->save();

        // Apply the same star count to every rating dimension on the
        // entity. On a fresh Magento install that's Quality, Price and
        // Value (3 ratings); custom-attribute installs may have more.
        $ratings = $this->loadRatingsForEntity('product');
        foreach ($ratings as $rating) {
            $optionId = $this->findOptionIdForStars($rating['rating_id'], $stars);
            if ($optionId === null) {
                continue;
            }
            $ratingModel = $this->ratingFactory->create();
            $ratingModel->setRatingId((int) $rating['rating_id']);
            $ratingModel->setReviewId((int) $review->getId());
            $ratingModel->addOptionVote((int) $optionId, (int) $product->getId());
        }

        // Aggregate refreshes review_entity_summary — the storefront's
        // source of truth for average stars and review count on the PDP.
        $review->aggregate();
    }

    /**
     * Quick existence check so the calling fixture can skip products
     * that already have reviews on a re-run.
     */
    public function hasReviews(string $sku): bool
    {
        try {
            $product = $this->productRepository->get($sku);
        } catch (NoSuchEntityException) {
            return false;
        }
        $conn = $this->resource->getConnection();
        $count = $conn->fetchOne(
            $conn->select()
                ->from(['rdv' => $this->resource->getTableName('review_entity_summary')], ['reviews_count'])
                ->where('rdv.entity_pk_value = ?', (int) $product->getId())
                ->limit(1)
        );
        return $count !== false && (int) $count > 0;
    }

    /**
     * @return array<int, array{rating_id: int}>
     */
    private function loadRatingsForEntity(string $entityCode): array
    {
        $conn = $this->resource->getConnection();
        return $conn->fetchAll(
            $conn->select()
                ->from(
                    ['r' => $this->resource->getTableName('rating')],
                    ['rating_id']
                )
                ->joinInner(
                    ['re' => $this->resource->getTableName('rating_entity')],
                    're.entity_id = r.entity_id',
                    []
                )
                ->where('re.entity_code = ?', $entityCode)
        );
    }

    private function findOptionIdForStars(int $ratingId, int $stars): ?int
    {
        $conn = $this->resource->getConnection();
        $optionId = $conn->fetchOne(
            $conn->select()
                ->from(
                    ['ro' => $this->resource->getTableName('rating_option')],
                    ['option_id']
                )
                ->where('ro.rating_id = ?', $ratingId)
                ->where('ro.value = ?', $stars)
                ->limit(1)
        );
        return $optionId === false ? null : (int) $optionId;
    }
}
