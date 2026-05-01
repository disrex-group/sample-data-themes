#!/usr/bin/env python3
"""Build manifest entries for the 69 SKUs whose simple-product image is
shared with another simple. Each entry gets its own studio prompt
varying the descriptor (size/finish/era/material) so Flux generates a
visually distinct version of the same product family.

Output: tools/image-prompts/home-living-dedup.json — companion manifest
that the batch runner reads alongside home-living.json and home-living-phase2.json.
"""

from __future__ import annotations
import csv
import json
import re
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
PLAN_PATH = Path('/tmp/dedup-plan.json')
OUT_PATH = ROOT / 'tools' / 'image-prompts' / 'home-living-dedup.json'

# Per-stem variant-prompt templates. The "i" index lets us pick a
# distinct descriptor for the 2nd, 3rd, 4th occurrence so the resulting
# images vary meaningfully — not just "the same thing again."

VARIANTS = {
    # Coffee tables
    'coffee-table-oslo': {
        2: 'A solid oak coffee table with a curved live-edge tabletop and four straight tapered oak legs. Approximately 110 centimeters long.',
        3: 'A solid oak coffee table with a circular tabletop and three splayed turned-oak legs. Approximately 90 centimeters in diameter.',
    },
    'coffee-table-bergen': {
        2: 'A walnut coffee table with a curved organic-shape tabletop and a single sculptural pedestal base in matching walnut.',
        3: 'A walnut coffee table with a slim rectangular top and tapered metal-tipped wood legs. Mid-century slim profile.',
    },
    'coffee-table-tromso': {
        2: 'A modern glass coffee table with a thick smoke-grey glass top and a curved chrome metal base.',
        3: 'A modern glass coffee table with a clear glass top and a sculptural matte-black metal base in geometric form.',
    },
    # Beds
    'bed-stockholm': {
        2: 'A queen-size scandinavian bed with a slatted oak headboard and slim tapered oak legs. No mattress, just the frame.',
        3: 'A king-size scandinavian bed with a tall solid oak headboard and storage drawers in the base. No mattress.',
    },
    # Nightstands
    'nightstand-malmo': {
        2: 'A scandinavian nightstand with a single drawer and an open lower shelf, in solid oak with brass round pulls.',
        3: 'A scandinavian nightstand with three drawers in solid oak with slim brass handles and tapered legs.',
        4: 'A scandinavian nightstand with a single drawer and an enclosed cabinet door, in light oak.',
    },
    'nightstand-lund': {
        2: 'A mid-century nightstand with two drawers in walnut, brass round pulls, splayed walnut legs.',
        3: 'A mid-century nightstand with one drawer and an open shelf, in walnut, with slim brass handles.',
        4: 'A mid-century nightstand with three drawers in walnut, splayed legs, no visible hardware.',
    },
    # Wardrobes
    'wardrobe-goteborg': {
        2: 'A minimalist mid-century wardrobe with three doors in matte walnut, slim brass vertical handles.',
        3: 'A minimalist mid-century wardrobe with two doors and a top drawer, in light oak with brass handles.',
    },
    # Dining tables
    'dining-table-reykjavik': {
        2: 'A scandinavian dining table with a round oak top and a single sculptural pedestal base. Seats six.',
        3: 'A scandinavian dining table with an extending butterfly-leaf rectangular top in oak, four slim tapered legs.',
    },
    # Dining chairs
    'dining-chair-helsinki': {
        2: 'A scandinavian dining chair with a curved oak backrest and a woven rope seat. Tapered oak legs.',
        3: 'A scandinavian dining chair with a slatted oak backrest and an upholstered linen seat.',
        4: 'A scandinavian dining chair with a continuous one-piece bent oak frame and a thin upholstered seat pad.',
    },
    'dining-chair-helsinki-002': {
        2: 'A modern dining chair with a wide curved upholstered back in deep grey wool and exposed walnut frame.',
        3: 'A modern dining chair with a tufted leather seat and back in caramel tan, slim oak legs.',
        4: 'A modern dining chair with a polished plywood molded shell on a swivel chrome base.',
    },
    # Pendant lights
    'pendant-light-espoo': {
        2: 'A modern pendant light with a brushed brass dome shade and a thin black cord.',
        3: 'A modern pendant light with a frosted glass dome and a slim black metal stem.',
    },
    'pendant-light-espoo-002': {
        2: 'A modern pendant light with a clear glass globe shade and brushed brass hardware.',
        3: 'A modern pendant light with a smoke-grey glass cylinder shade.',
        4: 'A modern pendant light with a fluted milk-glass shade and brass details.',
    },
    # Floor lamps
    'floor-lamp-stockholm': {
        2: 'A scandinavian floor lamp with a slim oak stem and a tilted linen cone shade in white.',
        3: 'A scandinavian floor lamp with a tripod oak base and a drum-shape linen shade.',
        4: 'A scandinavian floor lamp with a curved arc oak stem and a fabric drum shade.',
    },
    'floor-lamp-trondheim': {
        2: 'An industrial floor lamp with a black metal tripod base and an adjustable head with brass detailing.',
        3: 'An industrial floor lamp with a slim black stem and a wide metal dome shade with a copper interior.',
    },
    # Table lamps
    'table-lamp-bergen': {
        2: 'A modern table lamp with a turned-walnut base and a fabric drum shade in soft beige.',
        3: 'A modern table lamp with a marble base and a brass stem with an opaque white glass globe.',
    },
    # Vases
    'vase-copenhagen': {
        2: 'A tall ceramic vase in a soft sage green matte glaze. Cylindrical with a slight inward curve at the neck.',
        3: 'A bulbous round-bellied ceramic vase with a narrow neck, soft matte cream glaze.',
        4: 'A geometric faceted ceramic vase with a textured finish in dusty pink.',
    },
    # Wall art
    'wall-art-trio': {
        2: 'A trio of botanical line-drawing prints framed in slim solid black wood, hung side by side.',
        3: 'A trio of geometric abstract prints in muted earth tones, framed in matte oak.',
    },
    # Mirrors
    'mirror-aalborg-round': {
        2: 'A round wall mirror with a slim brushed brass frame, large-diameter.',
        3: 'A round wall mirror with a slim matte black frame and small brass hanging-loop accent.',
    },
    # Outdoor lamps
    'outdoor-lamp-skagen': {
        2: 'An outdoor pillar light with a square matte-black metal body and a frosted glass top.',
        3: 'An outdoor pillar light with a tall cylindrical aluminum body in dark grey, perforated detail near the top.',
        4: 'An outdoor wall sconce in matte black with a long downward-facing slim profile.',
    },
    'outdoor-lamp-skagen-002': {
        2: 'An outdoor path light with a short stem and a circular dome head in matte black, casting a downward beam.',
        3: 'An outdoor lantern hanging from a curved metal hook, with a clear-glass body and warm-white interior.',
        4: 'An outdoor step light with a horizontal slim profile in matte black.',
    },
    # Outdoor garden furniture
    'outdoor-garden-grey': {
        2: 'A modern outdoor side table with a stone-look composite top and a powder-coated grey aluminum base.',
        3: 'A modern outdoor lounge chair with a powder-coated grey aluminum frame and a hand-woven natural rope seat.',
        4: 'A modern outdoor dining bench with a slatted teak seat and a powder-coated grey metal frame.',
        5: 'A modern outdoor coffee table with a teak top and a powder-coated grey metal base.',
    },
    # Wardrobe alts
    'wardrobe-goteborg-002': {
        2: 'A modern white-lacquered wardrobe with three doors, slim vertical brass handles, and a plinth base.',
        3: 'A modern white-lacquered wardrobe with two sliding doors and an internal mirror.',
    },
}

# Camera convention per stem (matches what the original studio shot used)
STEM_TO_CAMERA = {
    'coffee-table': 'coffee_table',
    'bed': 'bed',
    'nightstand': 'nightstand',
    'wardrobe': 'wardrobe',
    'dining-table': 'dining_table',
    'dining-chair': 'dining_chair',
    'pendant-light': 'pendant_light',
    'floor-lamp': 'floor_lamp',
    'table-lamp': 'table_lamp',
    'vase': 'vase_or_decor_object',
    'wall-art': 'wall_art_trio',
    'mirror': 'wall_mirror',
    'outdoor-lamp': 'outdoor_lamp',
    'outdoor-garden': 'outdoor_set',
}

def camera_for_stem(stem):
    for prefix, cam in STEM_TO_CAMERA.items():
        if stem.startswith(prefix):
            return cam
    return 'vase_or_decor_object'  # safe default


def main():
    plan = json.loads(PLAN_PATH.read_text())
    products = {}
    skipped = []

    for entry in plan:
        sku = entry['sku']
        stem = entry['stem']
        new_image = entry['new_image']
        idx = entry['index']

        prompt_body = (
            VARIANTS.get(stem, {}).get(idx)
            or VARIANTS.get(stem, {}).get(2)  # fallback to '2' template if specific idx missing
        )
        if not prompt_body:
            skipped.append(sku)
            continue

        camera = camera_for_stem(stem)

        # Scenes: just one scene per dedup line (cheaper); use "living" or category-appropriate
        if 'bed' in stem or 'nightstand' in stem or 'wardrobe' in stem:
            scene_context = 'bedroom'
            scene_prompt = (
                f'Place this exact piece in a serene scandinavian bedroom with crisp white linen bedding, '
                f'light oak flooring, sheer linen curtains diffusing soft daylight. Walls in pale warm white. '
                f'Photorealistic interior photography, scandinavian aesthetic, magazine-quality composition.'
            )
        elif 'dining' in stem:
            scene_context = 'dining'
            scene_prompt = (
                f'Place this exact piece in a sun-drenched scandinavian dining room with light oak flooring, '
                f'a long oak dining table, sheer linen curtains. Photorealistic interior photography, '
                f'scandinavian aesthetic, magazine-quality composition.'
            )
        elif 'outdoor' in stem:
            scene_context = 'garden'
            scene_prompt = (
                f'Place this exact piece on a stone-paved patio at golden hour, lush green foliage in the '
                f'defocused background. Photorealistic exterior photography, twilight tones, '
                f'magazine-quality composition.'
            )
        else:
            scene_context = 'living'
            scene_prompt = (
                f'Place this exact piece in a sun-drenched scandinavian living room, light oak flooring, '
                f'a soft beige boucle armchair visible behind, sheer linen curtains diffusing soft daylight. '
                f'Photorealistic interior photography, scandinavian aesthetic, magazine-quality composition.'
            )

        scene_filename = new_image.replace('-alt.jpg', f'-scene-{scene_context}-001.jpg')

        products[sku] = {
            'name': sku,  # placeholder
            'tier': 'A',
            'category': '',  # not needed for image gen
            'camera_category': camera,
            'studio': {
                'filename': new_image,
                'prompt': prompt_body,
            },
            'scenes': [
                {'filename': scene_filename, 'prompt': scene_prompt},
            ],
        }

    OUT_PATH.write_text(json.dumps({
        '$schema': './home-living.schema.json',
        'theme': 'home-living',
        'version': '3.0.0-dedup',
        'phase': 3,
        'notes': [
            'Auto-generated by tools/build-dedup-manifest.py',
            f'{len(products)} unique-imagery replacements for SKUs that share studio shots with siblings',
            'Reuses camera_conventions and anchors from home-living.json',
        ],
        'products': products,
    }, indent=2))

    print(f'Generated dedup manifest: {len(products)} entries')
    print(f'Skipped (no template): {len(skipped)}')
    if skipped:
        for s in skipped[:10]:
            print(f'  - {s}')
    print(f'Output: {OUT_PATH}')


if __name__ == '__main__':
    main()
