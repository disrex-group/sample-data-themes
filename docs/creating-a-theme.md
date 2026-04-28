# Creating a sample-data theme

A theme is a regular Magento module whose `etc/di.xml` adds a class to the
core `ThemeRegistry`. Once registered, the theme shows up in
`bin/magento sampledata:theme:list` and can be deployed via
`bin/magento sampledata:theme:deploy --theme=<code>`.

Below is the minimum skeleton.

## 1. composer.json

```json
{
    "name": "vendor/sample-data-theme-coffee",
    "type": "magento2-module",
    "license": "MIT",
    "require": {
        "php": "~8.2.0 || ~8.3.0 || ~8.4.0",
        "disrex/sample-data-themes-core": "^1.0",
        "magento/framework": "^103.0"
    },
    "autoload": {
        "files": ["registration.php"],
        "psr-4": { "Vendor\\SampleDataThemeCoffee\\": "" }
    }
}
```

## 2. registration.php and etc/module.xml

```php
<?php
use Magento\Framework\Component\ComponentRegistrar;

ComponentRegistrar::register(
    ComponentRegistrar::MODULE,
    'Vendor_SampleDataThemeCoffee',
    __DIR__
);
```

```xml
<?xml version="1.0"?>
<config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:noNamespaceSchemaLocation="urn:magento:framework:Module/etc/module.xsd">
    <module name="Vendor_SampleDataThemeCoffee">
        <sequence>
            <module name="Disrex_SampleDataThemesCore"/>
        </sequence>
    </module>
</config>
```

## 3. The Theme class

Extend `AbstractTheme` and declare the constants:

```php
<?php
namespace Vendor\SampleDataThemeCoffee\Model;

use Disrex\SampleDataThemesCore\Model\Theme\AbstractTheme;
use Vendor\SampleDataThemeCoffee\Setup\Fixtures\AttributeFixture;
use Vendor\SampleDataThemeCoffee\Setup\Fixtures\CategoryFixture;
use Vendor\SampleDataThemeCoffee\Setup\Fixtures\SimpleProductFixture;

class Theme extends AbstractTheme
{
    protected const CODE = 'coffee';
    protected const NAME = 'Speciality Coffee';
    protected const DESCRIPTION = 'Single-origin beans, gear, accessories.';
    protected const VERSION = '1.0.0';
    protected const MODULE_NAME = 'Vendor_SampleDataThemeCoffee';
    protected const FIXTURES = [
        AttributeFixture::class,
        CategoryFixture::class,
        SimpleProductFixture::class,
    ];
    protected const LOCALES = ['en_US', 'nl_NL'];
    protected const DEFAULT_LOCALE = 'en_US';
}
```

## 4. Register the theme in di.xml

```xml
<?xml version="1.0"?>
<config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:noNamespaceSchemaLocation="urn:magento:framework:ObjectManager/etc/config.xsd">
    <type name="Disrex\SampleDataThemesCore\Model\ThemeRegistry">
        <arguments>
            <argument name="themes" xsi:type="array">
                <item name="coffee" xsi:type="object">
                    Vendor\SampleDataThemeCoffee\Model\Theme
                </item>
            </argument>
        </arguments>
    </type>
</config>
```

## 5. Fixtures

Each fixture is a class that implements `FixtureInterface`. The convenient
base class is `AbstractCsvFixture`:

```php
<?php
namespace Vendor\SampleDataThemeCoffee\Setup\Fixtures;

use Disrex\SampleDataThemesCore\Helper\Fixture\ProductImporter;
use Disrex\SampleDataThemesCore\Model\Fixture\AbstractCsvFixture;

class SimpleProductFixture extends AbstractCsvFixture
{
    public function __construct(
        \Disrex\SampleDataThemesCore\Helper\Fixture\CsvParser $csvParser,
        \Magento\Framework\Module\Dir\Reader $moduleReader,
        \Psr\Log\LoggerInterface $logger,
        private readonly ProductImporter $importer
    ) {
        parent::__construct($csvParser, $moduleReader, $logger);
    }

    protected function getCsvFilename(): string { return 'base/simple_products.csv'; }
    protected function getModuleName(): string  { return 'Vendor_SampleDataThemeCoffee'; }
    public function getLabel(): string          { return 'Simple products (Coffee)'; }

    protected function processRow(array $row): void
    {
        $this->importer->createOrUpdateBase($row);
    }

    public function rollback(): void
    {
        $skus = $this->csvParser->extractColumn($this->getFixtureFilePath(), 'sku');
        $this->importer->deleteBySkus($skus);
    }
}
```

## 6. CSV files

Place fixtures under `_files/`. The on-disk layout is documented in
[csv-format.md](csv-format.md):

```
_files/
├── base/
│   ├── attributes.csv
│   ├── categories.csv
│   └── simple_products.csv
└── i18n/
    ├── en_US/
    │   ├── attributes.csv
    │   ├── categories.csv
    │   └── products.csv
    └── nl_NL/
        ├── attributes.csv
        ├── categories.csv
        └── products.csv
```

## 7. Validation

Run `php tools/theme-validator.php` (when available) to confirm that every
locale CSV has a row for every SKU/path/attribute_code in the base layer.

## 8. Publishing

Tag a SemVer release on GitHub and submit to Packagist. Users can then run:

```bash
composer require vendor/sample-data-theme-coffee
bin/magento module:enable Vendor_SampleDataThemeCoffee
bin/magento setup:upgrade
bin/magento sampledata:theme:deploy --theme=coffee
```
