#!/usr/bin/env python3
"""Phase-2 CSV expansion: write 204 new product-line entries into the
home-living theme's CSVs as configurable parents with 3-4 variants each,
plus i18n entries for EN and NL.

Idempotent: re-running is a no-op (skips rows whose SKU already exists).

Run from sample-data-themes root:
    python3 tools/expand-catalog-phase2.py
"""

from __future__ import annotations
import csv
import json
import re
from pathlib import Path
from collections import Counter

ROOT = Path(__file__).resolve().parents[1]
BASE = ROOT / 'packages' / 'theme-home-living' / '_files' / 'base'
I18N_EN = ROOT / 'packages' / 'theme-home-living' / '_files' / 'i18n' / 'en_US'
I18N_NL = ROOT / 'packages' / 'theme-home-living' / '_files' / 'i18n' / 'nl_NL'
MANIFEST_P2 = ROOT / 'tools' / 'image-prompts' / 'home-living-phase2.json'


def read_csv(path: Path):
    with path.open() as f:
        rows = list(csv.DictReader(f))
    if not rows:
        header = path.read_text().splitlines()[0].split(',')
        return header, []
    return list(rows[0].keys()), rows


def write_csv(path: Path, header, rows):
    with path.open('w', newline='') as f:
        w = csv.DictWriter(f, fieldnames=header, quoting=csv.QUOTE_MINIMAL)
        w.writeheader()
        for r in rows:
            w.writerow({k: r.get(k, '') for k in header})


def append_unique(path: Path, new_rows, sku_col):
    header, existing = read_csv(path)
    skus = {r[sku_col] for r in existing}
    added = 0
    for r in new_rows:
        if r[sku_col] in skus:
            continue
        existing.append(r)
        skus.add(r[sku_col])
        added += 1
    if added:
        write_csv(path, header, existing)
    return added


# Per-leaf variant axis
LEAF_AXIS = {
    'living-room/sofas-couches':   ('color_family', 4),
    'living-room/lounge-chairs':   ('fabric', 4),
    'living-room/coffee-tables':   ('color_family', 3),
    'bedroom/beds':                ('color_family', 3),
    'bedroom/nightstands':         ('color_family', 3),
    'bedroom/wardrobes':           ('color_family', 3),
    'dining/dining-tables':        ('color_family', 3),
    'dining/dining-chairs':        ('color_family', 4),
    'lighting/pendant-lights':     ('color_family', 3),
    'lighting/floor-lamps':        ('color_family', 3),
    'lighting/table-lamps':        ('color_family', 3),
    'decor/vases':                 ('color_family', 3),
    'decor/mirrors':               ('color_family', 3),
    'decor/wall-art':              ('style', 3),
    'outdoor/garden-furniture':    ('color_family', 3),
    'outdoor/outdoor-lighting':    ('color_family', 3),
}

COLORS = ['grey', 'beige', 'navy', 'forest', 'white', 'black',
          'oak', 'walnut', 'charcoal']
FABRICS = ['linen', 'velvet', 'leather', 'boucle', 'woven']
STYLES_FOR_ART = ['scandinavian', 'mid-century', 'minimalist',
                  'bohemian', 'japanese', 'abstract']


def variants_for_leaf(leaf, parent_color, parent_fabric, parent_style):
    axis, count = LEAF_AXIS.get(leaf, ('color_family', 3))
    if axis == 'color_family':
        pool = COLORS.copy()
        primary = parent_color
    elif axis == 'fabric':
        pool = FABRICS.copy()
        primary = parent_fabric
    elif axis == 'style':
        pool = STYLES_FOR_ART.copy()
        primary = parent_style
    else:
        pool, primary = COLORS.copy(), parent_color
    if primary in pool:
        pool.remove(primary)
    variants = [primary] + pool[:count - 1]
    return axis, variants


def price_for(leaf, sub_seed):
    bands = {
        'living-room/sofas-couches':   (799, 2499),
        'living-room/lounge-chairs':   (399, 1199),
        'living-room/coffee-tables':   (179, 699),
        'bedroom/beds':                (499, 1799),
        'bedroom/nightstands':         (89, 349),
        'bedroom/wardrobes':           (599, 2199),
        'dining/dining-tables':        (449, 1599),
        'dining/dining-chairs':        (69, 299),
        'lighting/pendant-lights':     (89, 449),
        'lighting/floor-lamps':        (99, 449),
        'lighting/table-lamps':        (49, 249),
        'decor/vases':                 (19.95, 89.95),
        'decor/mirrors':               (49, 349),
        'decor/wall-art':              (39, 199),
        'outdoor/garden-furniture':    (149, 1199),
        'outdoor/outdoor-lighting':    (39, 249),
    }
    lo, hi = bands.get(leaf, (49, 199))
    t = (sub_seed * 0.137) % 1.0
    t = t ** 1.4
    p = lo + t * (hi - lo)
    return round(p / 5) * 5 if hi > 100 else round(p, 2)


NL_NOUN = {
    'Sofa': 'Bank', 'Bed': 'Bed', 'Coffee Table': 'Salontafel',
    'Nightstand': 'Nachtkastje', 'Wardrobe': 'Kledingkast',
    'Dining Table': 'Eettafel', 'Dining Chair': 'Eetkamerstoel',
    'Pendant Light': 'Hanglamp', 'Floor Lamp': 'Vloerlamp',
    'Table Lamp': 'Tafellamp', 'Vase': 'Vaas', 'Mirror': 'Spiegel',
    'Wall Art': 'Wandkunst', 'Outdoor': 'Tuin', 'Lounge Chair': 'Loungestoel',
    'Outdoor Lamp': 'Buitenlamp',
}


def translate_to_nl(en_name):
    parts = en_name.split()
    two_word_nouns = ('Coffee Table', 'Dining Table', 'Dining Chair',
                      'Pendant Light', 'Floor Lamp', 'Table Lamp',
                      'Wall Art', 'Outdoor Lamp', 'Lounge Chair')
    nl_parts = []
    if len(parts) >= 2 and ' '.join(parts[:2]) in two_word_nouns:
        nl_parts.append(NL_NOUN.get(' '.join(parts[:2]), ' '.join(parts[:2])))
        rest = parts[2:]
    else:
        nl_parts.append(NL_NOUN.get(parts[0], parts[0]))
        rest = parts[1:]
    nl_parts.extend(rest)
    return ' '.join(nl_parts)


def slug(name):
    s = name.lower()
    for a, b in (('ä','a'),('ë','e'),('ï','i'),('ö','o'),('ü','u'),
                 ('é','e'),('è','e'),('ç','c'),('ñ','n')):
        s = s.replace(a, b)
    s = re.sub(r'[^a-z0-9]+', '-', s).strip('-')
    return s


def make_description_en(name, color, fabric, material, style):
    pieces = [f'{name} brings {style} character to your home.',
              f'Crafted from {color} {material}.']
    if fabric and fabric != 'linen':
        pieces.append(f'Upholstered in {fabric}.')
    pieces.append('Built to last, designed for everyday comfort.')
    return ' '.join(pieces)


def make_description_nl(color, fabric, material, style):
    color_nl = {'grey':'grijs','beige':'beige','navy':'marineblauw','forest':'bosgroen',
                'white':'wit','black':'zwart','oak':'eiken','walnut':'walnoot',
                'charcoal':'antraciet'}.get(color, color)
    mat_nl = {'wood':'hout','metal':'metaal','fabric':'stof','leather':'leer',
              'glass':'glas','ceramic':'keramiek'}.get(material, material)
    fab_nl = {'linen':'linnen','velvet':'fluweel','leather':'leer',
              'boucle':'boucle','woven':'geweven'}.get(fabric, fabric)
    style_nl = {'scandinavian':'Scandinavisch','industrial':'industrieel',
                'mid-century':'mid-century','modern':'modern','bohemian':'bohemian',
                'japanese':'Japans','abstract':'abstract','minimalist':'minimalistisch'
                }.get(style, style)
    pieces = [f'Brengt {style_nl} karakter naar uw huis.',
              f'Gemaakt van {color_nl} {mat_nl}.']
    if fabric and fabric != 'linen':
        pieces.append(f'Bekleed met {fab_nl}.')
    pieces.append('Duurzaam gebouwd, ontworpen voor dagelijks comfort.')
    return ' '.join(pieces)


def main():
    manifest = json.loads(MANIFEST_P2.read_text())
    products = manifest['products']

    new_parents = []
    new_variants = []
    new_en = []
    new_nl = []

    leaf_idx = Counter()
    for sku, p in products.items():
        leaf = p['category']
        leaf_idx[leaf] += 1
        seed = leaf_idx[leaf]
        attrs = p.get('attrs', {})
        parent_color = attrs.get('color_family', 'grey')
        parent_fabric = attrs.get('fabric', 'linen')
        parent_style = attrs.get('style', 'scandinavian')
        material = attrs.get('material', 'wood')
        room = attrs.get('room', 'living')

        axis, variant_values = variants_for_leaf(
            leaf, parent_color, parent_fabric, parent_style
        )
        if leaf.startswith('lighting/') or leaf == 'outdoor/outdoor-lighting':
            attr_set = 'lighting'
        elif leaf.startswith('decor/'):
            attr_set = 'decor'
        else:
            attr_set = 'furniture'

        base_price = price_for(leaf, seed)

        new_parents.append({
            'sku': sku,
            'attribute_set': attr_set,
            'price': f'{base_price:.2f}',
            'visibility': '4',
            'status': '1',
            'categories': leaf,
            'configurable_attributes': axis,
        })

        studio_fn = p['studio']['filename']
        scene_fns = ','.join(s['filename'] for s in p.get('scenes', []))
        images_csv = f'{studio_fn},{scene_fns}' if scene_fns else studio_fn

        for v_idx, value in enumerate(variant_values):
            v_price = base_price * (0.95 + 0.05 * v_idx)
            v_qty = 8 + (v_idx * 3)
            child_sku = f'{sku}-V{v_idx + 1}'
            row = {
                'parent_sku': sku, 'child_sku': child_sku,
                'attribute_set': attr_set,
                'price': f'{v_price:.2f}', 'qty': str(v_qty),
                'visibility': '1', 'status': '1',
                'material': material, 'style': parent_style, 'room': room,
                'color_family': '', 'fabric': '', 'images': images_csv,
            }
            if axis == 'color_family':
                row['color_family'] = value
            elif axis == 'fabric':
                row['fabric'] = value
                row['color_family'] = parent_color
            elif axis == 'style':
                row['style'] = value
                row['color_family'] = parent_color
            new_variants.append(row)

        name = p['name']
        nl_name = translate_to_nl(name)
        slug_en = slug(name)
        slug_nl = slug(nl_name)
        desc_en = make_description_en(name, parent_color, parent_fabric, material, parent_style)
        desc_nl = make_description_nl(parent_color, parent_fabric, material, parent_style)

        new_en.append({
            'sku': sku, 'name': name,
            'description': desc_en,
            'short_description': desc_en[:120],
            'url_key': slug_en,
            'meta_title': name,
            'meta_description': desc_en[:155],
            'meta_keyword': '',
        })
        new_nl.append({
            'sku': sku, 'name': nl_name,
            'description': desc_nl,
            'short_description': desc_nl[:120],
            'url_key': slug_nl,
            'meta_title': nl_name,
            'meta_description': desc_nl[:155],
            'meta_keyword': '',
        })

    p_added = append_unique(BASE / 'configurable_products.csv', new_parents, 'sku')
    v_added = append_unique(BASE / 'configurable_variations.csv', new_variants, 'child_sku')
    en_added = append_unique(I18N_EN / 'products.csv', new_en, 'sku')
    nl_added = append_unique(I18N_NL / 'products.csv', new_nl, 'sku')

    print(f'Configurable parents added: {p_added}')
    print(f'Configurable variants added: {v_added}')
    print(f'EN i18n added: {en_added}')
    print(f'NL i18n added: {nl_added}')


if __name__ == '__main__':
    main()
