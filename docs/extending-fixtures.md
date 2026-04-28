# Adding a new fixture type

The framework ships with importers for the entities most themes care about
(attributes, categories, products of every type, links). If a theme needs
to import something else — CMS pages, customers, reviews, Flex Builder
zones — write a new fixture class.

## 1. Implement `FixtureInterface`

```php
<?php
namespace Vendor\SampleDataThemeCoffee\Setup\Fixtures;

use Disrex\SampleDataThemesCore\Api\FixtureInterface;

class CmsBlockFixture implements FixtureInterface
{
    public function execute(): void
    {
        // ... import CMS blocks
    }

    public function rollback(): void
    {
        // ... remove the CMS blocks this fixture created
    }

    public function getLabel(): string
    {
        return 'CMS blocks';
    }
}
```

## 2. Or extend `AbstractCsvFixture`

If your fixture maps a CSV file 1-to-1 to entities, extend `AbstractCsvFixture`
and implement just `processRow()`. You get free row-level error handling
and logging.

## 3. Register it on the theme

Append to the theme class's `FIXTURES` constant:

```php
protected const FIXTURES = [
    AttributeFixture::class,
    CategoryFixture::class,
    SimpleProductFixture::class,
    CmsBlockFixture::class,        // new
];
```

Order matters — fixtures run sequentially. Place new ones after their
dependencies (e.g. CMS blocks that reference categories must run after
categories are imported).

## 4. Idempotency

`execute()` must be safe to run twice. The convention: look up the entity
by stable identifier (sku, identifier, url_key, ...), update it if it
exists, create it otherwise. Never insert blindly.

## 5. Rollback

Track what you created so `rollback()` can clean up. The simplest pattern:
read the CSV again on rollback and delete by identifier (this is what
`SimpleProductFixture::rollback()` does in the reference theme).

For entities that legitimately cannot be tracked (e.g. EAV attribute
options shared with other themes), a no-op rollback is acceptable — but
say so in a code comment so the reader knows it's intentional.

## 6. When to ship a helper

If your fixture pattern is likely to be reused by other themes, extract
the entity manipulation into a `Helper/Fixture/*Importer.php` class in the
core package (see `ProductImporter` for the shape) and contribute it
upstream. Helpers are easier to keep DI-friendly and unit-testable than
inlining the Magento API calls into each theme.
