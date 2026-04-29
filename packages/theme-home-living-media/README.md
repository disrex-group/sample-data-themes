# Home & Living — Media bundle

Companion package to [`disrex/sample-data-theme-home-living`](../theme-home-living)
that ships AI-generated product photography for the catalog.

> Status: **skeleton**. The image set lives in `_files/images/`. Run
> `tools/generate-images.php` (Phase 3 deliverable, not yet on this branch)
> to populate it from the prompt library, or drop in your own photography
> matching the SKU-based filenames listed in the theme's
> `simple_products.csv` / `configurable_variations.csv`.

## Why a separate package?

* The theme code package stays small (~5 MB) so it works in CI and on
  resource-constrained dev environments.
* The image bundle (~50–150 MB once populated) is opt-in: install it on
  demo / staging environments, skip it in tests.
* Updating images doesn't require a code release.

## License

MIT. AI-generated images included in this package are released under the
same licence as the rest of the project — feel free to use them in your
own demos.
