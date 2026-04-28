# CSV format

All CSV files use:

- `,` separator
- `"` enclosure
- UTF-8 encoding (BOM tolerated and stripped)
- one header row
- one logical record per data row

The on-disk layout for a theme is:

```
_files/
├── base/                       locale-independent data
│   ├── attributes.csv
│   ├── attribute_sets.csv
│   ├── categories.csv
│   ├── simple_products.csv
│   ├── configurable_products.csv
│   ├── configurable_variations.csv
│   ├── grouped_products.csv
│   ├── bundle_products.csv
│   └── product_links.csv
├── i18n/                       per-locale translations
│   ├── en_US/
│   │   ├── attributes.csv
│   │   ├── categories.csv
│   │   └── products.csv
│   └── nl_NL/
│       ├── attributes.csv
│       ├── categories.csv
│       └── products.csv
└── images/                     placeholder images (real ones live in a media package)
```

The base layer holds **stable identifiers** (SKUs, attribute codes, URL-key
paths). The i18n layer holds **translatable strings** (names, descriptions,
labels). Lookups against the i18n layer fall back to the theme's default
locale field-by-field.

## base/attributes.csv

| column | type | notes |
|---|---|---|
| `attribute_code` | string (snake_case) | required, unique |
| `frontend_input` | enum: `text`, `textarea`, `select`, `multiselect`, `swatch_visual`, `swatch_text`, `price`, `weight`, `date` | |
| `is_required` | bool 0/1 | |
| `is_searchable` | bool 0/1 | |
| `is_filterable` | int 0/1/2 | 0=no, 1=filterable, 2=filterable in search |
| `is_visible_on_front` | bool 0/1 | |
| `used_in_product_listing` | bool 0/1 | |
| `is_global` | int 0/1/2 | scope: 0=store, 1=global, 2=website |
| `option_codes` | string | `code\|code\|code` for selects, `code:#hex\|code:#hex` for visual swatches |

## base/categories.csv

| column | type | notes |
|---|---|---|
| `path` | url-key path | required, unique. e.g. `living-room/sofas-couches` |
| `is_active` | bool 0/1 | |
| `include_in_menu` | bool 0/1 | |
| `position` | int | |
| `is_anchor` | bool 0/1 | |

## base/simple_products.csv

| column | type | notes |
|---|---|---|
| `sku` | string | required, unique |
| `attribute_set` | name | falls back to "Default" |
| `price` | decimal | |
| `qty` | decimal | |
| `visibility` | int | Magento visibility constant (1, 2, 3, 4) |
| `status` | int | 1=enabled, 2=disabled |
| `categories` | comma-separated url-key paths | |
| `weight` | decimal | optional |
| `images` | comma-separated filenames | optional, files are looked up in `_files/images/` (or in a media package) |
| any custom attribute_code | string | resolved to option ids when the attribute exists |

For multi-select attributes, encode multiple option codes as
`"living,bedroom"` (note the quotes).

## base/configurable_products.csv

| column | type | notes |
|---|---|---|
| `sku` | string | parent SKU |
| `attribute_set` | name | |
| `price` | decimal | starting / lowest variant price |
| `visibility` | int | |
| `status` | int | |
| `categories` | comma-separated paths | |
| `configurable_attributes` | comma-separated attribute codes | e.g. `color_family,fabric` |

## base/configurable_variations.csv

| column | type | notes |
|---|---|---|
| `parent_sku` | string | must exist in `configurable_products.csv` |
| `child_sku` | string | unique |
| `price` | decimal | |
| `qty` | decimal | |
| any configurable attribute code | string | the variant value (e.g. `grey`, `linen`) |
| `images` | comma-separated filenames | per-variant images |

## base/grouped_products.csv

| column | type | notes |
|---|---|---|
| `sku` | string | |
| `attribute_set` | name | |
| `visibility` | int | |
| `status` | int | |
| `categories` | comma-separated paths | |
| `associated_skus` | string | `SKU:qty,SKU:qty,...` |

## base/product_links.csv

| column | type | notes |
|---|---|---|
| `sku` | string | source product |
| `link_type` | enum: `related`, `upsell`, `crosssell` | |
| `linked_skus` | comma-separated SKUs | |

## i18n/&lt;locale&gt;/categories.csv

| column | type | notes |
|---|---|---|
| `path` | base path | join key into `base/categories.csv` |
| `name` | string | required for the source locale |
| `description` | HTML | |
| `url_key` | string | localized slug; per-storeview |
| `meta_title` | string | |
| `meta_description` | string | |
| `meta_keywords` | string | |

## i18n/&lt;locale&gt;/products.csv

| column | type | notes |
|---|---|---|
| `sku` | string | join key |
| `name` | string | required for the source locale |
| `description` | HTML | |
| `short_description` | HTML | |
| `url_key` | string | localized slug |
| `meta_title` | string | |
| `meta_description` | string | |
| `meta_keyword` | string | (Magento's column name) |

## i18n/&lt;locale&gt;/attributes.csv

One row per option, with the first row per `attribute_code` carrying the
attribute label.

| column | type | notes |
|---|---|---|
| `attribute_code` | string | join key |
| `frontend_label` | string | per-row, but only the first occurrence per code is used for the attribute label |
| `option_code` | string | join key for the option (matches `option_codes` from base) |
| `option_label` | string | localized option label |
