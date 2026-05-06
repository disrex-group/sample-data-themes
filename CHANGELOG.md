# Sample Data Themes — Changelog

Theme-based demo data for Magento 2 / MageOS. All notable changes to
this project are documented here.


## [1.0.1](https://github.com/disrex-group/sample-data-themes/compare/v1.0.0..v1.0.1) - 2026-05-06

### 🚀 Features

- *(home-living)* Generate algorithmic product reviews - ([9cdce0f](https://github.com/disrex-group/sample-data-themes/commit/9cdce0f077ee46cf4260ef693ecf10a5f4eb594e))

### 🐛 Bug Fixes

- *(core)* Map rating dimensions to all stores before generating reviews - ([67962b9](https://github.com/disrex-group/sample-data-themes/commit/67962b9b5d18842f93fd95c0a91ea01bf7e82ac0))
- *(core)* Cast rating_id to int before findOptionIdForStars - ([c5533f3](https://github.com/disrex-group/sample-data-themes/commit/c5533f30a615bc58a12c310d79d0222220908d87))

### 📚 Documentation

- *(readme)* Rewrite as install-focused quick-start - ([da7bc87](https://github.com/disrex-group/sample-data-themes/commit/da7bc873d839cc84959d085263bab8d895ac5e11))
## [1.0.0] - 2026-05-04

### 🚀 Features

- *(core)* Virtual products + custom options; add 5 giftcards - ([ceeced7](https://github.com/disrex-group/sample-data-themes/commit/ceeced7006be0ab1adb94298ad073fa48f8f7638))
- *(core)* --primary-locale promotes a non-default locale onto store 1 - ([acfb8b7](https://github.com/disrex-group/sample-data-themes/commit/acfb8b7e78d433701fe533f46a7887d7e40274d9))
- *(core)* Apply locale + currency to every storeview each deploy - ([f5aa743](https://github.com/disrex-group/sample-data-themes/commit/f5aa74327050e7db798c412b0ca41fb9a1e093e8))
- *(home-living)* Populate related/upsell/crosssell across catalog - ([5e09102](https://github.com/disrex-group/sample-data-themes/commit/5e091024785b8953bb9a52c64232492c46121ad0))
- *(home-living)* Add Product Types cross-cut category - ([67fe557](https://github.com/disrex-group/sample-data-themes/commit/67fe55739381626ff911e738d1845bf98866696b))
- *(home-living)* Cadeaubonnen as top-level + SOFA-HEL imagery - ([88bf1f0](https://github.com/disrex-group/sample-data-themes/commit/88bf1f08916adb17d03c459585720779f3e69390))
- *(home-living)* Expand catalog with 204 new configurable lines - ([e3bdd97](https://github.com/disrex-group/sample-data-themes/commit/e3bdd9714e4b5867b6fa6f6f4629f88e574325da))
- *(home-living)* Phase-2 manifest with 204 new product-line entries - ([798cd3e](https://github.com/disrex-group/sample-data-themes/commit/798cd3e0f1434db5a4041cc55bcabd69029aa564))
- *(home-living)* Diversify imagery for heaviest-reused product lines - ([9428b52](https://github.com/disrex-group/sample-data-themes/commit/9428b520ea544c8ca5fcd0d0f5e95f28e70f3bee))
- *(home-living)* Add 8 new product lines with unique AI-generated imagery - ([9b74da9](https://github.com/disrex-group/sample-data-themes/commit/9b74da9c0d9d83ab3746fc83948fbddffd0b7cc0))
- *(home-living)* Expand catalog to ~20 products per top-level category - ([744a6c3](https://github.com/disrex-group/sample-data-themes/commit/744a6c370bf4015eca96d0f2ad1dec76b6a9c74a))
- *(home-living)* Expand catalog to 96 products across all categories - ([3012196](https://github.com/disrex-group/sample-data-themes/commit/30121963a1cad110819437f6943811df3e0dfa8e))
- *(tools)* Dedup-manifest builder and runner support for unique-imagery backfill - ([f23e789](https://github.com/disrex-group/sample-data-themes/commit/f23e789e5fbef4f54120f7ea1af9be9e7d29e7b7))
- *(tools)* Standalone Replicate batch runner for imagery generation - ([5f05703](https://github.com/disrex-group/sample-data-themes/commit/5f0570361b3beb3f5a913ec60fdf0d168cce6ace))

### 🐛 Bug Fixes

- *(ci)* Pin monorepo-split-github-action to v2.3.4 - ([a27dbd3](https://github.com/disrex-group/sample-data-themes/commit/a27dbd35bcbf02630c5add9a324df9e4a13feb59))
- *(core)* Flush page/block caches at end of theme:deploy - ([6450ae5](https://github.com/disrex-group/sample-data-themes/commit/6450ae5575688a540923bbf198376c46bc1fe740))
- *(core)* Direct EAV lookup for findChildByUrlKey to prevent duplicate trees - ([d39e581](https://github.com/disrex-group/sample-data-themes/commit/d39e581cfd3228a4a7e75aa52e501bdda5c474da))
- *(core)* Regenerate URL rewrites even when source==target storeview - ([674a13b](https://github.com/disrex-group/sample-data-themes/commit/674a13b1c466d344ee230c9f59a8f1dec8268e51))
- *(core)* Invalidate indexers before reindexAll() in post-deploy pass - ([98597a2](https://github.com/disrex-group/sample-data-themes/commit/98597a288fc41e1b56fed4e05d09cac04e8c9d23))
- *(core)* Wipe stale category url_rewrites on every deploy - ([e285a4e](https://github.com/disrex-group/sample-data-themes/commit/e285a4ef68884a2b04834a467a5ac8eb8e6da0e5))
- *(core)* Assign products to every active website by default - ([f846283](https://github.com/disrex-group/sample-data-themes/commit/f846283c34a5c0b934b148ef11fe0c3473af4ac8))
- *(core)* Make NL (and any non-default locale) translations actually land - ([c786293](https://github.com/disrex-group/sample-data-themes/commit/c786293ceeddf32921edcc150db72fca418477f8))
- *(core)* Write admin-scope attribute labels for the default locale - ([9af5338](https://github.com/disrex-group/sample-data-themes/commit/9af5338078cadd429a37e89a396992810ca16463))
- *(core)* Inherit hero image on bundle and grouped parents too - ([417a917](https://github.com/disrex-group/sample-data-themes/commit/417a917b85dd2641bd2fffb9c34b30512839fdbe))
- *(core)* Scene images, configurable parent images, idempotent re-runs - ([daf7bf8](https://github.com/disrex-group/sample-data-themes/commit/daf7bf86e3bfe221dbd5f57a957136b018304231))
- *(core)* Link products to ancestor categories and auto-reindex catalog - ([65ad7ea](https://github.com/disrex-group/sample-data-themes/commit/65ad7ea7fa5d9c1e3b89d55d35e2a5a35ea47866))
- *(home-living)* Wire imagery to all 210 configurable parents - ([fa8c1ca](https://github.com/disrex-group/sample-data-themes/commit/fa8c1ca3ed19800955e82008322b764751967570))
- *(home-living)* Remap 53 SKUs to unique alt imagery + verify SQL - ([8d0e06f](https://github.com/disrex-group/sample-data-themes/commit/8d0e06f5bc71d064c88d2fed316a45929bc7674a))
- *(packages)* Require media as a hard dep, drop circular media→theme - ([2e52873](https://github.com/disrex-group/sample-data-themes/commit/2e528734b7d46d203bb7ce9c14e600aa54f54493))
- *(tools)* Validator now recognises variants + virtuals as base SKUs - ([4576e17](https://github.com/disrex-group/sample-data-themes/commit/4576e17fe681cdab69d9c8aa0c29e33ebc206b8e))
- Resolve CI lint/static-analysis/test failures - ([7f0d3f5](https://github.com/disrex-group/sample-data-themes/commit/7f0d3f52da66d00b0b75b9162eac47e8b06b2311))

### 💼 Other

- Add Replicate prompt manifest for home-living image generation - ([e5ef0c4](https://github.com/disrex-group/sample-data-themes/commit/e5ef0c4ae781f8a70dc02afcc7a70926683910e7))
- CSV fixes for configurable + bundle catalog - ([38a31c6](https://github.com/disrex-group/sample-data-themes/commit/38a31c64c9e687ee2bc85bf33878565eb7900d17))
- Map child_sku to sku before passing variants to importer - ([32bae0c](https://github.com/disrex-group/sample-data-themes/commit/32bae0cf2e6c73fffa205ec786246ffa3e18b426))
- Refresh EAV cache between fixtures and surface row failure counts - ([f63904c](https://github.com/disrex-group/sample-data-themes/commit/f63904c310c0f0fb5ca064696cda253e61bf0821))
- Populate configurable option values from variant children - ([9468238](https://github.com/disrex-group/sample-data-themes/commit/94682389f7448ca615ef5d6464d8d870ecf96d0a))
- Fix bundle option API misuse in BundleProductBuilder - ([eb3fc14](https://github.com/disrex-group/sample-data-themes/commit/eb3fc14e199ceaecd785ac06ba5d595b6f86007c))
- ProductImporter end-to-end fixes (price, custom attributes, images, URL rewrites) - ([1f4b9cf](https://github.com/disrex-group/sample-data-themes/commit/1f4b9cf26e9796cdd40571e421ca5ef5778e373c))
- Fix swatch_text payload format and stop duplicating options - ([6c99f47](https://github.com/disrex-group/sample-data-themes/commit/6c99f473a08e299f2bfd246a0af73b86ffe7d085))
- Align Magento module version constraints with 2.4.7+ reality - ([f37d60e](https://github.com/disrex-group/sample-data-themes/commit/f37d60efbe932c3d35d0bce86755b3c4b0327a60))

### 🚜 Refactor

- *(home-living)* Consolidate 8 top-levels into 5 - ([ac4b005](https://github.com/disrex-group/sample-data-themes/commit/ac4b0057603a91e2efca16b231959ae1786eb12a))

### ⚡ Performance

- *(core)* Bulk-insert configurable variants directly to EAV (~50× faster) - ([fae76ec](https://github.com/disrex-group/sample-data-themes/commit/fae76ec6e206ba8bea5d8d17cdac1e259e8c81ef))

### ⚙️ Miscellaneous Tasks

- *(media)* Add 100 unique-imagery alts for dedup pass - ([0d5bdf6](https://github.com/disrex-group/sample-data-themes/commit/0d5bdf68b86a13d173a7e4fa707d57d7ef62c7a1))
- *(media)* Add 482 scene shots from Phase 1 + Phase 2 imagery generation - ([98142ba](https://github.com/disrex-group/sample-data-themes/commit/98142baa77255d1c91b317aea52c7e8abb9909e4))
- *(media)* Add 265 studio shots from Phase 1 + Phase 2 imagery generation - ([d287448](https://github.com/disrex-group/sample-data-themes/commit/d287448a52433729b4efa46c66d17e695c68e562))
- *(tools)* Drop generate-product-links.py - ([7707d64](https://github.com/disrex-group/sample-data-themes/commit/7707d649728bc8fa1b7df1fe2a5e942d970f892d))
- *(tools)* Drop one-shot imagery generation pipeline - ([6044553](https://github.com/disrex-group/sample-data-themes/commit/6044553f12ac64c5202a31549d6db8a0fb5af3b7))
- *(tools)* Drop one-shot catalog-bootstrap scripts - ([96c7bcc](https://github.com/disrex-group/sample-data-themes/commit/96c7bcc1274832ba1c5375cedd3af5023888d52f))
<!-- Generated by git-cliff -->
