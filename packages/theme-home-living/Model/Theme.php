<?php

declare(strict_types=1);

namespace Disrex\SampleDataThemeHomeLiving\Model;

use Disrex\SampleDataThemeHomeLiving\Setup\Fixtures\AttributeFixture;
use Disrex\SampleDataThemeHomeLiving\Setup\Fixtures\AttributeSetFixture;
use Disrex\SampleDataThemeHomeLiving\Setup\Fixtures\BundleProductFixture;
use Disrex\SampleDataThemeHomeLiving\Setup\Fixtures\CategoryFixture;
use Disrex\SampleDataThemeHomeLiving\Setup\Fixtures\ConfigurableProductFixture;
use Disrex\SampleDataThemeHomeLiving\Setup\Fixtures\GroupedProductFixture;
use Disrex\SampleDataThemeHomeLiving\Setup\Fixtures\ProductLinksFixture;
use Disrex\SampleDataThemeHomeLiving\Setup\Fixtures\ProductReviewsFixture;
use Disrex\SampleDataThemeHomeLiving\Setup\Fixtures\SimpleProductFixture;
use Disrex\SampleDataThemeHomeLiving\Setup\Fixtures\VirtualProductFixture;
use Disrex\SampleDataThemesCore\Model\Theme\AbstractTheme;

/**
 * Scandinavian-inspired Home & Living theme.
 *
 * Six top-level categories (Living Room, Bedroom, Dining, Lighting, Decor,
 * Outdoor), ~28 products across simple / configurable / grouped / bundle
 * types, full EN_US + NL_NL translations.
 *
 * The fixture order is significant: attributes must exist before attribute
 * sets reference them; categories before products link to them; child
 * products before configurable / grouped / bundle parents wrap them; and
 * product-links last because they reference both ends.
 */
class Theme extends AbstractTheme
{
    protected const CODE = 'home-living';
    protected const NAME = 'Home & Living';
    protected const DESCRIPTION = 'Scandinavian-inspired furniture, lighting and decor.';
    protected const VERSION = '1.0.0';
    protected const MODULE_NAME = 'Disrex_SampleDataThemeHomeLiving';

    protected const FIXTURES = [
        AttributeFixture::class,
        AttributeSetFixture::class,
        CategoryFixture::class,
        SimpleProductFixture::class,
        VirtualProductFixture::class,
        ConfigurableProductFixture::class,
        GroupedProductFixture::class,
        BundleProductFixture::class,
        ProductLinksFixture::class,
        ProductReviewsFixture::class,
    ];

    protected const LOCALES = ['en_US', 'nl_NL'];
    protected const DEFAULT_LOCALE = 'en_US';
    protected const SKU_PREFIX = 'DRX-HL-';

    protected const OPTIONAL_DEPENDENCIES = [
        'Disrex_SampleDataThemeHomeLivingMedia',
    ];
}
