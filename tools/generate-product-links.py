#!/usr/bin/env python3
"""Generate algorithmic related / upsell / crosssell links across the
home-living catalog.

Goal: every visible product (simple, configurable parent, virtual,
bundle, grouped) has ~3 related, ~2 upsell, ~2 crosssell entries so
PDPs feel populated instead of showing empty "you may also like"
slots.

Algorithm:
  - Related   : 3 random same-leaf-category SKUs (price within +-30%)
  - Upsell    : 2 same-leaf SKUs with higher price
  - Crosssell : 2 SKUs from a *complementary* leaf
                (e.g. sofas → coffee tables / lounge chairs / lamps;
                 beds → nightstands / wardrobes; vase → wall-art etc)

Reads:
  packages/theme-home-living/_files/base/simple_products.csv
  packages/theme-home-living/_files/base/configurable_products.csv
  packages/theme-home-living/_files/base/virtual_products.csv
  packages/theme-home-living/_files/base/bundle_products.csv
  packages/theme-home-living/_files/base/grouped_products.csv

Writes:
  packages/theme-home-living/_files/base/product_links.csv

Idempotent: re-running with the same inputs produces an identical CSV
(seeded random for reproducibility).
"""
from __future__ import annotations
import csv
import random
from collections import defaultdict
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
BASE = ROOT / 'packages/theme-home-living/_files/base'
OUT = BASE / 'product_links.csv'

random.seed(42)

# Complementary-leaf mapping for crosssell. Keys are the source leaf;
# values list compatible target leaves.
COMPLEMENTS = {
    'living-room/sofas-couches': ['living-room/coffee-tables', 'living-room/lounge-chairs', 'lighting/floor-lamps', 'decor/wall-art'],
    'living-room/coffee-tables': ['living-room/sofas-couches', 'living-room/lounge-chairs', 'decor/vases', 'lighting/table-lamps'],
    'living-room/lounge-chairs': ['lighting/floor-lamps', 'living-room/coffee-tables', 'decor/vases', 'living-room/sofas-couches'],
    'bedroom/beds': ['bedroom/nightstands', 'bedroom/wardrobes', 'lighting/table-lamps', 'decor/wall-art'],
    'bedroom/nightstands': ['bedroom/beds', 'lighting/table-lamps', 'decor/vases', 'bedroom/wardrobes'],
    'bedroom/wardrobes': ['bedroom/beds', 'bedroom/nightstands', 'decor/mirrors', 'lighting/floor-lamps'],
    'dining/dining-tables': ['dining/dining-chairs', 'lighting/pendant-lights', 'decor/vases', 'decor/wall-art'],
    'dining/dining-chairs': ['dining/dining-tables', 'lighting/pendant-lights', 'decor/vases'],
    'lighting/pendant-lights': ['dining/dining-tables', 'living-room/sofas-couches', 'decor/wall-art'],
    'lighting/floor-lamps': ['living-room/lounge-chairs', 'living-room/sofas-couches', 'decor/wall-art'],
    'lighting/table-lamps': ['bedroom/nightstands', 'living-room/coffee-tables', 'decor/vases'],
    'decor/vases': ['decor/wall-art', 'decor/mirrors', 'living-room/coffee-tables'],
    'decor/mirrors': ['bedroom/wardrobes', 'decor/wall-art', 'decor/vases'],
    'decor/wall-art': ['decor/vases', 'decor/mirrors', 'living-room/sofas-couches', 'bedroom/beds'],
    'outdoor/garden-furniture': ['outdoor/outdoor-lighting', 'decor/vases'],
    'outdoor/outdoor-lighting': ['outdoor/garden-furniture'],
    'cadeaubonnen': ['decor/vases', 'decor/wall-art', 'living-room/sofas-couches', 'bedroom/beds'],
}


def parse_csv(path: Path) -> list[dict]:
    if not path.exists():
        return []
    with path.open() as f:
        return list(csv.DictReader(f))


def first_leaf_path(categories: str) -> str | None:
    """Pick the first non-product-types leaf from a categories cell."""
    if not categories:
        return None
    for c in categories.split(','):
        c = c.strip()
        if c and not c.startswith('product-types/'):
            return c
    return None


def main():
    catalog: list[dict] = []  # {sku, price, leaf, type}
    for fname, ptype in [
        ('simple_products.csv', 'simple'),
        ('configurable_products.csv', 'configurable'),
        ('virtual_products.csv', 'virtual'),
        ('bundle_products.csv', 'bundle'),
        ('grouped_products.csv', 'grouped'),
    ]:
        for row in parse_csv(BASE / fname):
            sku = row.get('sku', '').strip()
            if not sku:
                continue
            try:
                price = float(row.get('price', '0') or 0)
            except ValueError:
                price = 0.0
            leaf = first_leaf_path(row.get('categories', ''))
            if leaf is None:
                continue
            catalog.append({
                'sku': sku,
                'price': price,
                'leaf': leaf,
                'type': ptype,
            })

    print(f'Catalog: {len(catalog)} visible products')

    # Group by leaf for fast same-leaf lookups
    by_leaf: dict[str, list[dict]] = defaultdict(list)
    for p in catalog:
        by_leaf[p['leaf']].append(p)

    rows_out: list[dict] = []

    for product in catalog:
        sku = product['sku']
        price = product['price']
        leaf = product['leaf']
        siblings = [p for p in by_leaf[leaf] if p['sku'] != sku]

        # ---- Related: 3 same-leaf, prefer price within +-30%
        if siblings:
            close_priced = [p for p in siblings if 0.7 * price <= p['price'] <= 1.3 * price]
            pool = close_priced if len(close_priced) >= 3 else siblings
            related = random.sample(pool, min(3, len(pool)))
            if related:
                rows_out.append({
                    'sku': sku,
                    'link_type': 'related',
                    'linked_skus': ','.join(p['sku'] for p in related),
                })

        # ---- Upsell: 2 same-leaf with higher price
        higher = [p for p in siblings if p['price'] > price]
        if higher:
            higher.sort(key=lambda p: p['price'])
            # take items 1.5-3x the price preferentially, fall back to nearest above
            preferred = [p for p in higher if 1.5 * price <= p['price'] <= 3 * price]
            pool = preferred if preferred else higher
            upsells = random.sample(pool, min(2, len(pool)))
            if upsells:
                rows_out.append({
                    'sku': sku,
                    'link_type': 'upsell',
                    'linked_skus': ','.join(p['sku'] for p in upsells),
                })

        # ---- Crosssell: 2 from a complementary leaf
        complement_leaves = COMPLEMENTS.get(leaf, [])
        candidates = []
        for comp_leaf in complement_leaves:
            candidates.extend(by_leaf.get(comp_leaf, []))
        if candidates:
            cross = random.sample(candidates, min(2, len(candidates)))
            rows_out.append({
                'sku': sku,
                'link_type': 'crosssell',
                'linked_skus': ','.join(p['sku'] for p in cross),
            })

    # Write CSV
    with OUT.open('w', newline='') as f:
        w = csv.DictWriter(f, fieldnames=['sku', 'link_type', 'linked_skus'], quoting=csv.QUOTE_MINIMAL)
        w.writeheader()
        for r in rows_out:
            w.writerow(r)

    by_type = defaultdict(int)
    for r in rows_out:
        by_type[r['link_type']] += 1

    print(f'Wrote {len(rows_out)} rows to {OUT.relative_to(ROOT)}')
    for t in ('related', 'upsell', 'crosssell'):
        print(f'  {t}: {by_type[t]}')


if __name__ == '__main__':
    main()
