#!/usr/bin/env python3
"""Build 204 new product-line manifest entries to expand each leaf
category to >=20 cards. Each entry follows the existing schema in
home-living.json: name, tier='A', category, camera_category, studio
prompt, two scene prompts. New entries are written into a Phase-2
companion file (`home-living-phase2.json`) which the deploy tooling
will merge with the original on read.

Idempotent: re-running emits the same entries (deterministic SKUs).
"""

import json
import os
import re
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
MANIFEST_PATH = ROOT / 'tools' / 'image-prompts' / 'home-living.json'
OUT_PATH = ROOT / 'tools' / 'image-prompts' / 'home-living-phase2.json'

# Per-leaf line specifications. Each tuple is:
#   (sku_suffix, name, designer_descriptor, finish_descriptor, dimensions,
#    materials, color_family, fabric_or_None, style)
# That's 1 line per tuple, expanded into a manifest entry.
#
# Designed to evenly distribute colors/materials/fabrics/styles so filter
# coverage hits >=12 per option naturally. The shortfalls per leaf are
# the targets; total across leaves = 204 lines.

# Color rotation (9 options) - cycles through to ensure coverage.
COLORS = ['grey', 'beige', 'navy', 'forest', 'white', 'black',
          'oak', 'walnut', 'charcoal']
# Material rotation (6 options).
MATERIALS = ['wood', 'metal', 'fabric', 'leather', 'glass', 'ceramic']
# Fabric rotation for upholstered items (5 options).
FABRICS = ['linen', 'velvet', 'leather', 'boucle', 'woven']
# Style rotation (8 options).
STYLES = ['scandinavian', 'industrial', 'mid-century', 'modern', 'bohemian',
          'japanese', 'abstract', 'minimalist']
# Room rotation (5 options) - implied per leaf, but kept on attributes.
ROOMS = {
    'living-room': 'living', 'bedroom': 'bedroom', 'dining': 'dining',
    'lighting': 'living', 'decor': 'living', 'outdoor': 'outdoor',
}

# Norse-inspired city names for furniture lines (avoiding existing ones).
EXISTING_NAMES = {
    'oslo','bergen','tromso','helsinki','aalborg','stockholm','goteborg',
    'lund','malmo','reykjavik','aarhus','espoo','copenhagen','tampere',
    'skagen','trondheim','charlottenborg',
}
NEW_NAMES = [
    # 60+ Norse cities/regions - drawn from Sweden, Denmark, Norway, Finland, Iceland
    'akureyri','andenes','arendal','bergvik','bodo','egilsstadir','elsinore',
    'falun','fredrikstad','geilo','halden','hamar','hammerfest','harstad',
    'hofn','horsens','huskvarna','jakobstad','jokulsarlon','jonkoping',
    'kalmar','karlstad','kemi','kiruna','kongsberg','kristiansand',
    'kuopio','larvik','lillehammer','linkoping','lulea','mariehamn',
    'molde','moss','narvik','nordby','norrkoping','nuuk','odda',
    'olafsvik','oulu','porvoo','rauma','riga','roskilde','rovaniemi',
    'saudarkrokur','seinajoki','siglufjordur','silkeborg','skara','skien',
    'sodertalje','stavanger','sundsvall','tampere2','thorshavn','tonsberg',
    'turku','umea','uppsala','vaasa','varberg','vasteras','vejle','viborg',
    'visby','vordingborg','aalesund','akranes','asker','aulestad',
    'borgarnes','bymarka','dalvik','egersund','eidsvoll','farsund',
    'fauske','fjaerland','floro','gjovik','grimstad','grindavik',
    'haugesund','hekla','horten','husavik','jakobstad2','keflavik',
    'kongsvinger','kragero','kristianstad','levanger','lofoten','mandal',
    'nesna','notodden','olafsfjordur','olden','porsgrunn','reine',
    'risor','romsas','rosendal','sandnes','sarpsborg','seyoisfjorour',
    'soelden','sognefjord','stavern','steinkjer','strommen','svolvaer',
    'tonder','torshavn','tysvaer','vadso','vagamo','varangerfjord',
    'vatnajokull','viken','vinje','volda','voss','vrigstad','ystad',
    'zealand',
]

def ensure_unique(name):
    base = name.lower()
    if base not in EXISTING_NAMES and base not in ensure_unique.taken:
        ensure_unique.taken.add(base)
        return name.capitalize()
    return None
ensure_unique.taken = set()

ANCHORS_NEEDED = (
    'A modern table lamp with',
)

# ----------------------------------------------------------------------
# Per-leaf line generators.
# ----------------------------------------------------------------------

# Fabric items (sofas, lounge chairs, beds upholstered, dining chairs upholstered)
# Solid items (coffee tables, side tables, beds platform, lamps, mirrors,
#              vases, wardrobes, dining tables)


def line(sku, name, category, camera, studio_filename, studio_prompt,
         scene_a_filename, scene_a_prompt, scene_b_filename, scene_b_prompt,
         attrs):
    return {
        'sku': sku,
        'name': name,
        'category': category,
        'camera_category': camera,
        'attrs': attrs,
        'studio': {'filename': studio_filename, 'prompt': studio_prompt},
        'scenes': [
            {'filename': scene_a_filename, 'prompt': scene_a_prompt},
            {'filename': scene_b_filename, 'prompt': scene_b_prompt},
        ],
    }


def kebab(s):
    return re.sub(r'-+', '-', re.sub(r'[^a-z0-9]+', '-', s.lower())).strip('-')


# Track per-attribute usage so the final distribution hits >=12 per option.
usage = {'color': dict.fromkeys(COLORS, 0),
         'material': dict.fromkeys(MATERIALS, 0),
         'fabric': dict.fromkeys(FABRICS, 0),
         'style': dict.fromkeys(STYLES, 0)}

def pick_least_used(attr):
    pool = usage[attr]
    return min(pool, key=lambda k: pool[k])


def take(attr):
    v = pick_least_used(attr)
    usage[attr][v] += 1
    return v


# ============================================================
# LEAF GENERATORS - one per leaf, returning a list of line dicts
# ============================================================

def gen_sofas(n=15):
    """living-room/sofas-couches — 15 new lines"""
    lines = []
    sofa_styles = [
        ('Chesterfield', 'A traditional chesterfield three-seat sofa with deep button-tufted back and rolled arms', 'leather', 'leather'),
        ('Modular L-Shape', 'A modular L-shape sectional sofa with deep seats and clean square arms', 'fabric', 'linen'),
        ('Sleeper', 'A modern sleeper sofa with hidden mechanism, slim square arms', 'fabric', 'velvet'),
        ('Loveseat', 'A compact two-seater loveseat with curved arms and one button-tufted seat cushion', 'fabric', 'velvet'),
        ('Curved', 'A modern curved-back sofa with continuous tight upholstery', 'fabric', 'boucle'),
        ('Boucle Modern', 'A modern three-seat sofa with deep boucle upholstery and soft rounded arms', 'fabric', 'boucle'),
        ('Mid-Century 2-Seat', 'A mid-century two-seat sofa with exposed walnut frame and tufted leather cushions', 'leather', 'leather'),
        ('Deep Contemporary', 'A deep contemporary three-seat sofa with low profile and woven fabric', 'fabric', 'woven'),
        ('Compact Two-Seater', 'A compact two-seat sofa with slim metal legs and tight back cushions', 'fabric', 'linen'),
        ('Tuxedo', 'A tuxedo sofa with arms equal in height to the back, square clean lines', 'fabric', 'velvet'),
        ('Camelback', 'A camelback sofa with elegant curved high back and rolled arms', 'fabric', 'velvet'),
        ('Lawson', 'A classic lawson sofa with relaxed loose cushions and rolled arms', 'fabric', 'linen'),
        ('Track-Arm', 'A modern track-arm three-seat sofa with low square profile', 'fabric', 'boucle'),
        ('Cabriole', 'A cabriole sofa with continuous curved back-arm line and exposed wood feet', 'fabric', 'velvet'),
        ('English Roll-Arm', 'An english roll-arm sofa with deep seat cushions and slipcovered fit', 'fabric', 'linen'),
    ][:n]
    for i, (sub, desc, mat, fab) in enumerate(sofa_styles):
        city = NEW_NAMES[i]
        sub_kebab = kebab(sub)
        sku = f'DRX-HL-SOFA-{sub_kebab.upper()[:8]}'
        name = f'Sofa {sub} {city.capitalize()}'
        stem = f'sofa-{sub_kebab}-{city.lower()}'
        color = take('color')
        style = take('style')
        usage['material'][mat] = usage['material'].get(mat, 0) + 1
        usage['fabric'][fab] = usage['fabric'].get(fab, 0) + 1
        camera = 'curved_sofa' if sub in ('Curved','Camelback','Cabriole') else 'lounge_chair_or_sofa'
        studio_prompt = (
            f'{desc} in {color} {fab}. Approximately 220 centimeters wide, '
            f'95 centimeters deep, 85 centimeters tall. {style.capitalize()} silhouette, '
            f'photorealistic catalog shot.'
        )
        scene_a_prompt = (
            f'Place this exact sofa in a sun-drenched scandinavian living room with '
            f'light oak flooring, a round white coffee table in front, two contrasting '
            f'cushions, large window with sheer linen curtains diffusing soft daylight. '
            f'Photorealistic interior photography, scandinavian aesthetic, magazine-quality '
            f'composition, no text, no watermark.'
        )
        scene_b_prompt = (
            f'Close-up detail of this sofa\'s armrest and seat cushion, capturing the '
            f'{fab} texture and a single contrast cushion in sharp focus. Soft natural light '
            f'from the side. Photorealistic, scandinavian aesthetic, shallow depth of field, '
            f'magazine-quality composition, no text, no watermark.'
        )
        lines.append(line(
            sku, name, 'living-room/sofas-couches', camera,
            f'{stem}-001.jpg', studio_prompt,
            f'{stem}-scene-living-001.jpg', scene_a_prompt,
            f'{stem}-scene-detail-002.jpg', scene_b_prompt,
            {'material': mat, 'color_family': color, 'fabric': fab,
             'style': style, 'room': 'living'}
        ))
    return lines


def gen_lounge(n=16):
    lines = []
    descs = [
        ('Wingback', 'A traditional wingback armchair with high winged sides', 'velvet'),
        ('Tub Chair', 'A round tub chair with continuous curved back and one seat cushion', 'boucle'),
        ('Slipper', 'A low armless slipper chair with simple silhouette and tapered wood legs', 'velvet'),
        ('Recliner', 'A modern reclining lounge chair with leather upholstery and exposed walnut footrest', 'leather'),
        ('Wishbone', 'A scandinavian wishbone-frame lounge chair with woven seat', 'woven'),
        ('Eames-Style', 'An Eames-style molded plywood lounge chair with leather seat and ottoman', 'leather'),
        ('Papasan', 'A papasan-style round lounge chair with thick boucle cushion', 'boucle'),
        ('Barrel', 'A barrel chair with continuous low curved back', 'velvet'),
        ('Accent', 'A modern accent chair with sculpted wood frame and linen seat', 'linen'),
        ('Reading', 'A high-back reading chair with deep seat and rolled arms', 'velvet'),
        ('Swivel', 'A modern swivel lounge chair with chrome metal base', 'velvet'),
        ('Saucer', 'A modern saucer-style lounge chair with curved boucle seat', 'boucle'),
        ('Bergere', 'A french-style bergere armchair with carved wood frame', 'velvet'),
        ('Tulip', 'A modern tulip-base lounge chair with one continuous shell', 'leather'),
        ('Egg', 'An egg-style enclosed lounge chair with high curved back', 'velvet'),
        ('Cocoon', 'A cocoon-shape lounge chair with deep boucle upholstery', 'boucle'),
    ][:n]
    for i,(sub,desc,fab) in enumerate(descs):
        city = NEW_NAMES[(15+i) % len(NEW_NAMES)]
        sku = f'DRX-HL-LC-{kebab(sub).upper()[:8]}'
        name = f'Lounge Chair {sub} {city.capitalize()}'
        stem = f'lounge-{kebab(sub)}-{city.lower()}'
        color = take('color')
        style = take('style')
        usage['fabric'][fab] = usage['fabric'].get(fab,0)+1
        usage['material']['fabric'] = usage['material'].get('fabric',0)+1
        studio_prompt = (
            f'{desc} upholstered in {color} {fab}. Approximately 75 centimeters wide, '
            f'80 centimeters deep, 90 centimeters tall. {style.capitalize()} silhouette.'
        )
        scene_a_prompt = (
            'Place this exact lounge chair in a cozy scandinavian reading nook beside '
            'a small natural-oak side table holding an open book and a ceramic coffee cup, '
            'light oak flooring, sheer linen curtains diffusing late-afternoon golden light. '
            'Photorealistic interior photography, scandinavian aesthetic, soft natural warm '
            'light, magazine-quality composition, no text, no watermark.'
        )
        scene_b_prompt = (
            f'Close-up detail of this lounge chair, focused on the upholstery texture and the '
            f'curve of the back. Soft natural light, shallow depth of field, defocused warm '
            f'interior background. Photorealistic, scandinavian aesthetic, magazine-quality '
            f'composition, no text, no watermark.'
        )
        lines.append(line(
            sku, name, 'living-room/lounge-chairs', 'lounge_chair_or_sofa',
            f'{stem}-001.jpg', studio_prompt,
            f'{stem}-scene-reading-001.jpg', scene_a_prompt,
            f'{stem}-scene-detail-002.jpg', scene_b_prompt,
            {'material': 'fabric', 'color_family': color, 'fabric': fab,
             'style': style, 'room': 'living'}
        ))
    return lines


def gen_coffee_tables(n=10):
    lines = []
    descs = [
        ('Round Pedestal', 'A round coffee table with single central pedestal base', 'wood'),
        ('Lift-Top', 'A modern coffee table with hidden lift-top mechanism', 'wood'),
        ('Nesting', 'A set of two nesting coffee tables with concentric circles', 'wood'),
        ('Glass-Top', 'A coffee table with thick tempered glass top and metal X-base', 'glass'),
        ('Drum', 'A drum-shape coffee table with continuous cylindrical body', 'wood'),
        ('Marble', 'A coffee table with white marble round top and brass base', 'metal'),
        ('Hairpin', 'A modern coffee table with rectangular wood top and hairpin metal legs', 'metal'),
        ('Storage', 'A modern coffee table with hidden storage drawer beneath', 'wood'),
        ('Live-Edge', 'A live-edge solid wood coffee table with natural irregular edges', 'wood'),
        ('Industrial', 'An industrial coffee table with reclaimed wood top and black metal frame', 'metal'),
    ][:n]
    for i,(sub,desc,mat) in enumerate(descs):
        city = NEW_NAMES[(31+i) % len(NEW_NAMES)]
        sku = f'DRX-HL-CT-{kebab(sub).upper()[:8]}'
        name = f'Coffee Table {sub} {city.capitalize()}'
        stem = f'coffee-table-{kebab(sub)}-{city.lower()}'
        color = take('color')
        style = take('style')
        usage['material'][mat] = usage['material'].get(mat,0)+1
        studio_prompt = (
            f'{desc} in {color} {mat} finish. Approximately 110 centimeters in diameter or '
            f'wide, 42 centimeters tall. {style.capitalize()} silhouette.'
        )
        scene_a_prompt = (
            'Place this exact coffee table at the center of a sun-drenched scandinavian '
            'living room with a light grey linen sofa visible behind it, light oak flooring, '
            'large window with sheer linen curtains, a small ceramic vase and a stack of '
            'books on top. Photorealistic interior photography, scandinavian aesthetic, soft '
            'natural light, magazine-quality composition, no text, no watermark.'
        )
        scene_b_prompt = (
            f'Close-up detail of this coffee table from a low angle, capturing the {mat} '
            f'texture and a hint of the soft oak flooring beneath. Photorealistic, '
            f'scandinavian aesthetic, shallow depth of field, magazine-quality composition, '
            f'no text, no watermark.'
        )
        lines.append(line(
            sku, name, 'living-room/coffee-tables', 'coffee_table',
            f'{stem}-001.jpg', studio_prompt,
            f'{stem}-scene-living-001.jpg', scene_a_prompt,
            f'{stem}-scene-detail-002.jpg', scene_b_prompt,
            {'material': mat, 'color_family': color,
             'style': style, 'room': 'living'}
        ))
    return lines


def gen_beds(n=14):
    lines = []
    descs = [
        ('Platform', 'A low-profile platform bed with clean lines and headboard'),
        ('Storage', 'A bed with built-in drawer storage in the base'),
        ('Canopy', 'A four-post canopy bed with slim wood frame'),
        ('4-Poster', 'A traditional four-poster bed with carved posts'),
        ('Low Platform', 'An ultra-low platform bed inspired by japanese design'),
        ('Boucle Upholstered', 'An upholstered bed with tall boucle headboard'),
        ('Industrial Metal', 'An industrial metal bed with black powder-coated frame'),
        ('Rattan', 'A rattan-frame bed with woven panels in headboard and footboard'),
        ('Minimalist', 'A minimalist bed with floating headboard and slim wood rails'),
        ('Sleigh', 'A traditional sleigh bed with curved headboard and footboard'),
        ('Daybed', 'A daybed convertible to seating with backrest cushions'),
        ('Bunk', 'A modern bunk bed with built-in ladder and slim metal frame'),
        ('Trundle', 'A bed with pull-out trundle storage beneath the main mattress'),
        ('Murphy', 'A modern murphy bed integrated into a wood wall unit'),
    ][:n]
    for i,(sub,desc) in enumerate(descs):
        city = NEW_NAMES[(41+i) % len(NEW_NAMES)]
        sku = f'DRX-HL-BED-{kebab(sub).upper()[:8]}'
        name = f'Bed {sub} {city.capitalize()}'
        stem = f'bed-{kebab(sub)}-{city.lower()}'
        color = take('color')
        style = take('style')
        mat = 'wood' if sub not in ('Industrial Metal','Bunk') else 'metal'
        usage['material'][mat] = usage['material'].get(mat,0)+1
        studio_prompt = (
            f'{desc} in {color} {mat} finish. Queen size, approximately 160 centimeters wide '
            f'by 210 centimeters long. {style.capitalize()} silhouette, no mattress, just the bed frame.'
        )
        scene_a_prompt = (
            'Place this exact bed in a serene scandinavian bedroom with crisp white linen '
            'bedding and a soft beige knit throw, light oak flooring, two ash wood nightstands '
            'flanking the bed each with a small ceramic table lamp, sheer linen curtains '
            'diffusing warm late-afternoon light. Walls in pale warm white. Photorealistic '
            'interior photography, scandinavian aesthetic, soft natural warm light, '
            'magazine-quality composition, no text, no watermark.'
        )
        scene_b_prompt = (
            f'Close-up detail of this bed\'s headboard joining the side rail, with the '
            f'{mat} texture clearly visible. Soft natural light, shallow depth of field, '
            f'defocused crisp white linen visible at the edge. Photorealistic, scandinavian '
            f'aesthetic, magazine-quality composition, no text, no watermark.'
        )
        lines.append(line(
            sku, name, 'bedroom/beds', 'bed',
            f'{stem}-001.jpg', studio_prompt,
            f'{stem}-scene-bedroom-001.jpg', scene_a_prompt,
            f'{stem}-scene-detail-002.jpg', scene_b_prompt,
            {'material': mat, 'color_family': color,
             'style': style, 'room': 'bedroom'}
        ))
    return lines


def gen_nightstands(n=11):
    lines = []
    descs = [
        ('Two-Drawer', 'A two-drawer nightstand with brass round pulls'),
        ('Open-Shelf', 'A nightstand with an open shelf and a single drawer'),
        ('Mid-Century', 'A mid-century nightstand with splayed wood legs and brass hardware'),
        ('Tall', 'A taller nightstand with three drawers and slim tapered legs'),
        ('Round', 'A round-top nightstand with cylindrical drum base'),
        ('Floating', 'A wall-mounted floating nightstand with single drawer'),
        ('Modern Black', 'A modern black-lacquered nightstand with hidden push-open drawer'),
        ('Glass-Top', 'A nightstand with thick tempered glass top and metal frame'),
        ('Industrial', 'An industrial nightstand with reclaimed wood top and metal cage frame'),
        ('Bohemian', 'A bohemian-style nightstand with carved wood front'),
        ('Minimalist', 'A minimalist nightstand with single drawer and no visible hardware'),
    ][:n]
    for i,(sub,desc) in enumerate(descs):
        city = NEW_NAMES[(55+i) % len(NEW_NAMES)]
        sku = f'DRX-HL-NS-{kebab(sub).upper()[:8]}'
        name = f'Nightstand {sub} {city.capitalize()}'
        stem = f'nightstand-{kebab(sub)}-{city.lower()}'
        color = take('color')
        style = take('style')
        mat = 'metal' if 'industrial' in sub.lower() or 'glass' in sub.lower() else 'wood'
        usage['material'][mat] = usage['material'].get(mat,0)+1
        studio_prompt = (
            f'{desc} in {color} {mat} finish. Approximately 50 centimeters wide, '
            f'40 centimeters deep, 60 centimeters tall. {style.capitalize()} silhouette.'
        )
        scene_a_prompt = (
            'Place this exact nightstand beside a queen bed with crisp white linen bedding '
            'in a bright scandinavian bedroom, a small ceramic table lamp glowing softly on '
            'top, an open book and a small ceramic vase, soft early-morning window light, '
            'light oak flooring. Photorealistic interior photography, scandinavian aesthetic, '
            'magazine-quality composition, no text, no watermark.'
        )
        scene_b_prompt = (
            f'Close-up detail of this nightstand\'s drawer pull, the {mat} texture clearly '
            f'visible. Soft natural light from the side, defocused bedroom background. '
            f'Photorealistic, scandinavian aesthetic, shallow depth of field, magazine-quality '
            f'composition, no text, no watermark.'
        )
        lines.append(line(
            sku, name, 'bedroom/nightstands', 'nightstand',
            f'{stem}-001.jpg', studio_prompt,
            f'{stem}-scene-bedroom-001.jpg', scene_a_prompt,
            f'{stem}-scene-detail-002.jpg', scene_b_prompt,
            {'material': mat, 'color_family': color,
             'style': style, 'room': 'bedroom'}
        ))
    return lines


def gen_wardrobes(n=15):
    lines = []
    descs = [
        ('Two-Door', 'A two-door wardrobe with full-length doors and brass handles'),
        ('Three-Door', 'A three-door wardrobe with mirrored center door'),
        ('Sliding', 'A sliding-door wardrobe with two large panels'),
        ('Open', 'An open wardrobe with hanging rail and shelves, no doors'),
        ('Compact', 'A compact narrow wardrobe with single door and side mirror'),
        ('Tall XL', 'An extra-tall wardrobe reaching the ceiling, four doors'),
        ('Mirrored', 'A wardrobe with full-length mirrored doors'),
        ('Glass-Front', 'A wardrobe with glass-front display doors'),
        ('Walnut', 'A walnut wood wardrobe with two doors and dovetailed joinery'),
        ('White Lacquer', 'A modern white-lacquered wardrobe with hidden push-open doors'),
        ('Industrial', 'An industrial wardrobe with metal frame and reclaimed wood doors'),
        ('Bohemian', 'A bohemian wardrobe with carved wood doors and brass hardware'),
        ('Mid-Century', 'A mid-century wardrobe with splayed legs and brass hardware'),
        ('Minimalist', 'A minimalist wardrobe with no visible hardware, single piece silhouette'),
        ('Corner', 'A corner wardrobe designed to fit into a 90-degree corner'),
    ][:n]
    for i,(sub,desc) in enumerate(descs):
        city = NEW_NAMES[(66+i) % len(NEW_NAMES)]
        sku = f'DRX-HL-WAR-{kebab(sub).upper()[:8]}'
        name = f'Wardrobe {sub} {city.capitalize()}'
        stem = f'wardrobe-{kebab(sub)}-{city.lower()}'
        color = take('color')
        style = take('style')
        mat = 'metal' if 'industrial' in sub.lower() else 'wood'
        usage['material'][mat] = usage['material'].get(mat,0)+1
        studio_prompt = (
            f'{desc} in {color} {mat} finish. Approximately 200 centimeters wide, '
            f'60 centimeters deep, 220 centimeters tall. {style.capitalize()} silhouette.'
        )
        scene_a_prompt = (
            'Place this exact wardrobe against a wall in a serene scandinavian bedroom, '
            'partial view of a queen bed with crisp white linen bedding to the right, light '
            'oak flooring, sheer linen curtains diffusing soft daylight. Photorealistic '
            'interior photography, scandinavian aesthetic, soft natural light, '
            'magazine-quality composition, no text, no watermark.'
        )
        scene_b_prompt = (
            f'Close-up detail of this wardrobe\'s door handle and front face, capturing the '
            f'{mat} texture. Soft natural light from the side, defocused bedroom background. '
            f'Photorealistic, scandinavian aesthetic, shallow depth of field, magazine-quality '
            f'composition, no text, no watermark.'
        )
        lines.append(line(
            sku, name, 'bedroom/wardrobes', 'wardrobe',
            f'{stem}-001.jpg', studio_prompt,
            f'{stem}-scene-bedroom-001.jpg', scene_a_prompt,
            f'{stem}-scene-detail-002.jpg', scene_b_prompt,
            {'material': mat, 'color_family': color,
             'style': style, 'room': 'bedroom'}
        ))
    return lines


def gen_dining_tables(n=14):
    lines = []
    descs = [
        ('Round 4-Seater', 'A round 4-seater dining table with central pedestal'),
        ('Long Rectangular', 'A long rectangular dining table seating eight'),
        ('Extending', 'A dining table with extending leaf for extra seating'),
        ('Marble-Top', 'A dining table with white marble top and brass base'),
        ('Live-Edge', 'A live-edge solid wood dining table with natural irregular edges'),
        ('Glass-Top', 'A dining table with thick tempered glass top and metal X-base'),
        ('Industrial', 'An industrial dining table with reclaimed wood top and steel frame'),
        ('Pedestal', 'A round dining table with sculpted pedestal base'),
        ('Trestle', 'A trestle-base dining table with two angled support legs'),
        ('Mid-Century', 'A mid-century dining table with splayed wood legs'),
        ('Bohemian', 'A bohemian dining table with carved wood apron'),
        ('Minimalist', 'A minimalist dining table with thin top and slim legs'),
        ('Counter-Height', 'A counter-height dining table for tall stools'),
        ('Compact Square', 'A compact square dining table for two'),
    ][:n]
    for i,(sub,desc) in enumerate(descs):
        city = NEW_NAMES[(81+i) % len(NEW_NAMES)]
        sku = f'DRX-HL-DT-{kebab(sub).upper()[:8]}'
        name = f'Dining Table {sub} {city.capitalize()}'
        stem = f'dining-table-{kebab(sub)}-{city.lower()}'
        color = take('color')
        style = take('style')
        if 'glass' in sub.lower() or 'industrial' in sub.lower(): mat = 'metal'
        elif 'marble' in sub.lower(): mat = 'metal'
        else: mat = 'wood'
        usage['material'][mat] = usage['material'].get(mat,0)+1
        studio_prompt = (
            f'{desc} in {color} {mat} finish. Approximately 200 centimeters long, '
            f'95 centimeters deep, 76 centimeters tall. {style.capitalize()} silhouette.'
        )
        scene_a_prompt = (
            'Place this exact dining table at the center of a sun-drenched scandinavian '
            'dining room with six dining chairs around it, light oak flooring, a pendant light '
            'hanging above, large window with sheer linen curtains diffusing soft daylight. '
            'Photorealistic interior photography, scandinavian aesthetic, soft natural light, '
            'magazine-quality composition, no text, no watermark.'
        )
        scene_b_prompt = (
            f'Close-up detail of this dining table\'s edge and top surface, capturing the '
            f'{mat} grain or texture. Soft natural light, shallow depth of field. '
            f'Photorealistic, scandinavian aesthetic, magazine-quality composition, no text.'
        )
        lines.append(line(
            sku, name, 'dining/dining-tables', 'dining_table',
            f'{stem}-001.jpg', studio_prompt,
            f'{stem}-scene-dining-001.jpg', scene_a_prompt,
            f'{stem}-scene-detail-002.jpg', scene_b_prompt,
            {'material': mat, 'color_family': color,
             'style': style, 'room': 'dining'}
        ))
    return lines


def gen_dining_chairs(n=10):
    lines = []
    descs = [
        ('Wishbone', 'A scandinavian wishbone-frame dining chair with woven seat', 'woven'),
        ('Windsor', 'A traditional windsor dining chair with spindled back', None),
        ('Cross-Back', 'A modern cross-back dining chair with woven rush seat', 'woven'),
        ('Ladder-Back', 'A ladder-back dining chair with horizontal slat back', None),
        ('Tulip', 'A modern tulip-base dining chair with one continuous shell', 'leather'),
        ('Wishbone Black', 'A black-painted wishbone-frame dining chair', 'woven'),
        ('Industrial', 'An industrial dining chair with metal tube frame and wood seat', None),
        ('Tufted', 'A tufted-back dining chair with upholstered seat', 'velvet'),
        ('Rope-Seat', 'A modern dining chair with hand-woven rope seat', 'woven'),
        ('Fan-Back', 'A scandinavian fan-back dining chair with curved spindle back', None),
    ][:n]
    for i,(sub,desc,fab) in enumerate(descs):
        city = NEW_NAMES[(95+i) % len(NEW_NAMES)]
        sku = f'DRX-HL-DC-{kebab(sub).upper()[:8]}'
        name = f'Dining Chair {sub} {city.capitalize()}'
        stem = f'dining-chair-{kebab(sub)}-{city.lower()}'
        color = take('color')
        style = take('style')
        mat = 'fabric' if fab else 'wood'
        usage['material'][mat] = usage['material'].get(mat,0)+1
        if fab:
            usage['fabric'][fab] = usage['fabric'].get(fab,0)+1
        attrs = {'material': mat, 'color_family': color, 'style': style, 'room': 'dining'}
        if fab: attrs['fabric'] = fab
        studio_prompt = (
            f'{desc} in {color}{" and "+fab if fab else ""}. Approximately 45 centimeters wide, '
            f'50 centimeters deep, 85 centimeters tall. {style.capitalize()} silhouette.'
        )
        scene_a_prompt = (
            'Place this exact dining chair as one of six identical chairs around a long oak '
            'dining table in a sun-drenched scandinavian dining room, light oak flooring, '
            'large window with sheer linen curtains. Photorealistic interior photography, '
            'scandinavian aesthetic, soft natural light, magazine-quality composition, no text.'
        )
        scene_b_prompt = (
            f'Close-up detail of this dining chair, focused on the seat and back joining '
            f'point. Soft natural light, shallow depth of field, defocused dining room '
            f'background. Photorealistic, scandinavian aesthetic, magazine-quality composition.'
        )
        lines.append(line(
            sku, name, 'dining/dining-chairs', 'dining_chair',
            f'{stem}-001.jpg', studio_prompt,
            f'{stem}-scene-dining-001.jpg', scene_a_prompt,
            f'{stem}-scene-detail-002.jpg', scene_b_prompt,
            attrs
        ))
    return lines


def gen_pendant_lights(n=12):
    lines = []
    descs = [
        ('Glass Globe', 'A modern pendant light with clear glass globe shade'),
        ('Brass Dome', 'A modern brass dome pendant light with brushed gold finish'),
        ('Linear', 'A long linear pendant light suspended over a dining table'),
        ('Cluster', 'A cluster of three small pendant lights at varying heights'),
        ('Rattan', 'A rattan-shade pendant light with woven organic texture'),
        ('Cone', 'A cone-shape pendant light with metal exterior and white interior'),
        ('Drum', 'A drum-shade pendant light with fabric exterior'),
        ('Sputnik', 'A sputnik-style pendant chandelier with eight radiating arms'),
        ('Industrial Cage', 'An industrial cage pendant light with bare bulb'),
        ('Paper Lantern', 'A modern paper lantern pendant light with rounded shape'),
        ('Disk', 'A flat disk pendant light with thin profile and LED ring'),
        ('Plissé', 'A plissé pleated-fabric pendant light with cylindrical shape'),
    ][:n]
    for i,(sub,desc) in enumerate(descs):
        city = NEW_NAMES[(105+i) % len(NEW_NAMES)]
        sku = f'DRX-HL-PL-{kebab(sub).upper()[:8]}'
        name = f'Pendant Light {sub} {city.capitalize()}'
        stem = f'pendant-light-{kebab(sub)}-{city.lower()}'
        color = take('color')
        style = take('style')
        mat = 'glass' if 'glass' in sub.lower() else 'metal'
        usage['material'][mat] = usage['material'].get(mat,0)+1
        studio_prompt = (
            f'{desc} in {color} {mat} finish. Shade approximately 35 centimeters in diameter, '
            f'suspended from a slim cord. Inside is visible a single warm-white frosted bulb. '
            f'{style.capitalize()} silhouette.'
        )
        scene_a_prompt = (
            'Place this exact pendant light suspended above a scandinavian dining table in a '
            'sun-drenched modern dining room with light oak flooring, a long oak dining table '
            'set for breakfast, four scandinavian dining chairs around the table, sheer linen '
            'curtains diffusing soft daylight. Photorealistic interior photography, scandinavian '
            'aesthetic, magazine-quality composition, no text.'
        )
        scene_b_prompt = (
            f'Close-up detail of this pendant light from a slightly lower angle, capturing the '
            f'{mat} surface texture and the warm-white glow inside. Soft warm interior lighting, '
            f'shallow depth of field. Photorealistic, scandinavian aesthetic, magazine-quality '
            f'composition, no text.'
        )
        lines.append(line(
            sku, name, 'lighting/pendant-lights', 'pendant_light',
            f'{stem}-001.jpg', studio_prompt,
            f'{stem}-scene-dining-001.jpg', scene_a_prompt,
            f'{stem}-scene-detail-002.jpg', scene_b_prompt,
            {'material': mat, 'color_family': color, 'style': style,
             'room': 'living'}
        ))
    return lines


def gen_floor_lamps(n=12):
    lines = []
    descs = [
        ('Tripod', 'A modern tripod floor lamp with three splayed wood legs'),
        ('Arc', 'An arc floor lamp with curved arm extending out from base'),
        ('Reading', 'A slim reading floor lamp with adjustable head'),
        ('Drum-Shade', 'A floor lamp with cylindrical drum shade and slim metal stem'),
        ('Cone-Shade', 'A floor lamp with cone-shape metal shade and weighted base'),
        ('Rattan', 'A floor lamp with rattan-woven shade'),
        ('Industrial Cage', 'An industrial floor lamp with metal cage shade'),
        ('Plissé', 'A floor lamp with plissé pleated-fabric cylindrical shade'),
        ('Globe', 'A floor lamp with glass globe shade and brass stem'),
        ('Multi-Arm', 'A floor lamp with multiple adjustable arms'),
        ('Torchiere', 'A torchiere-style floor lamp with upward-facing shade'),
        ('Compact', 'A compact short floor lamp at side-table height'),
    ][:n]
    for i,(sub,desc) in enumerate(descs):
        city = NEW_NAMES[(117+i) % len(NEW_NAMES)]
        sku = f'DRX-HL-FL-{kebab(sub).upper()[:8]}'
        name = f'Floor Lamp {sub} {city.capitalize()}'
        stem = f'floor-lamp-{kebab(sub)}-{city.lower()}'
        color = take('color')
        style = take('style')
        mat = 'metal'
        usage['material'][mat] = usage['material'].get(mat,0)+1
        studio_prompt = (
            f'{desc} in {color} {mat} finish. Total height approximately 160 centimeters. '
            f'{style.capitalize()} silhouette.'
        )
        scene_a_prompt = (
            'Place this exact floor lamp in the corner of a cozy scandinavian reading nook '
            'next to a soft beige boucle armchair with a folded knit throw, a small natural-oak '
            'side table holding an open book, light oak flooring, sheer linen curtains '
            'diffusing late-afternoon golden light. Photorealistic interior photography, '
            'scandinavian aesthetic, soft natural warm light, magazine-quality composition.'
        )
        scene_b_prompt = (
            f'Close-up detail of this floor lamp\'s shade lit from within against a softly '
            f'defocused warm interior background. The slim {mat} stem is in sharp focus. '
            f'Photorealistic, scandinavian aesthetic, soft warm tones, magazine-quality '
            f'composition.'
        )
        lines.append(line(
            sku, name, 'lighting/floor-lamps', 'floor_lamp',
            f'{stem}-001.jpg', studio_prompt,
            f'{stem}-scene-living-001.jpg', scene_a_prompt,
            f'{stem}-scene-detail-002.jpg', scene_b_prompt,
            {'material': mat, 'color_family': color, 'style': style,
             'room': 'living'}
        ))
    return lines


def gen_table_lamps(n=13):
    lines = []
    descs = [
        ('Mushroom', 'A mushroom-shape table lamp with rounded glass shade'),
        ('Ceramic Base', 'A table lamp with ceramic base and fabric shade'),
        ('Metal Stem', 'A table lamp with slim brass metal stem and white shade'),
        ('Marble', 'A table lamp with white marble base and brass stem'),
        ('Wood-Base', 'A table lamp with turned wood base and natural linen shade'),
        ('Industrial', 'An industrial table lamp with adjustable metal arm'),
        ('Banker', 'A banker\'s desk lamp with green glass shade'),
        ('Origami', 'An origami-folded paper table lamp with geometric shade'),
        ('Cylinder', 'A cylinder table lamp with translucent fabric exterior'),
        ('Globe-Shade', 'A globe-shade table lamp with brass details'),
        ('Cordless', 'A modern cordless rechargeable table lamp with touch dimmer'),
        ('Apothecary', 'An apothecary-style table lamp with adjustable height'),
        ('Boucle-Shade', 'A table lamp with rounded boucle-fabric shade'),
    ][:n]
    for i,(sub,desc) in enumerate(descs):
        city = NEW_NAMES[129+i] if 129+i < len(NEW_NAMES) else f'lamp-{i}'
        sku = f'DRX-HL-TL-{kebab(sub).upper()[:8]}'
        name = f'Table Lamp {sub} {city.capitalize()}'
        stem = f'table-lamp-{kebab(sub)}-{city.lower()}'
        color = take('color')
        style = take('style')
        mat = 'ceramic' if 'ceramic' in sub.lower() else ('metal' if 'metal' in sub.lower() or 'industrial' in sub.lower() else 'fabric')
        usage['material'][mat] = usage['material'].get(mat,0)+1
        studio_prompt = (
            f'{desc} in {color} {mat} finish. Total height approximately 50 centimeters. '
            f'{style.capitalize()} silhouette.'
        )
        scene_a_prompt = (
            'Place this exact table lamp on a walnut nightstand in a serene scandinavian '
            'bedroom, beside a queen-size bed with crisp white linen bedding and a soft beige '
            'knit throw, light oak flooring, sheer linen curtains. The lamp is switched on, '
            'casting a warm pool of light. Photorealistic interior photography, scandinavian '
            'aesthetic, magazine-quality composition.'
        )
        scene_b_prompt = (
            f'Close-up detail of this table lamp\'s shade lit from within, casting a warm glow '
            f'against a softly defocused walnut wood surface. Photorealistic, scandinavian '
            f'aesthetic, magazine-quality composition.'
        )
        lines.append(line(
            sku, name, 'lighting/table-lamps', 'table_lamp',
            f'{stem}-001.jpg', studio_prompt,
            f'{stem}-scene-bedroom-001.jpg', scene_a_prompt,
            f'{stem}-scene-detail-002.jpg', scene_b_prompt,
            {'material': mat, 'color_family': color, 'style': style,
             'room': 'living'}
        ))
    return lines


def gen_vases(n=11):
    lines = []
    descs = [
        ('Tall Cylinder', 'A tall cylindrical ceramic vase'),
        ('Bulbous', 'A bulbous round-bellied ceramic vase'),
        ('Bottle-Neck', 'A ceramic vase with narrow bottle-neck top'),
        ('Squat', 'A squat low ceramic vase with wide opening'),
        ('Hand-Thrown', 'A hand-thrown ceramic vase with subtle visible throwing rings'),
        ('Glass Tall', 'A tall clear glass vase with elegant slim profile'),
        ('Smoke-Glass', 'A smoke-grey glass vase with rounded shape'),
        ('Geometric', 'A geometric ceramic vase with faceted surface'),
        ('Twisted', 'A twisted ceramic vase with sculptural spiral form'),
        ('Mini', 'A miniature ceramic vase for single-stem flowers'),
        ('Speckled', 'A speckled-glaze ceramic vase with rustic finish'),
    ][:n]
    for i,(sub,desc) in enumerate(descs):
        city = NEW_NAMES[(141+i) % len(NEW_NAMES)]
        sku = f'DRX-HL-VAS-{kebab(sub).upper()[:8]}'
        name = f'Vase {sub} {city.capitalize()}'
        stem = f'vase-{kebab(sub)}-{city.lower()}'
        color = take('color')
        style = take('style')
        mat = 'glass' if 'glass' in sub.lower() else 'ceramic'
        usage['material'][mat] = usage['material'].get(mat,0)+1
        studio_prompt = (
            f'{desc} in {color} {mat} finish. Approximately 30 centimeters tall. '
            f'{style.capitalize()} silhouette.'
        )
        scene_a_prompt = (
            'Place this exact vase on a light oak floating shelf in a modern scandinavian '
            'living room, with three slim dried pampas grass stems arranged inside, beside a '
            'small stack of two hardcover books and a small ceramic candle holder. Walls in '
            'pale warm white. Photorealistic interior photography, scandinavian aesthetic, '
            'magazine-quality composition.'
        )
        scene_b_prompt = (
            f'Close-up detail of this vase, focused on the {mat} texture. Defocused warm '
            f'interior background. Photorealistic, scandinavian aesthetic, shallow depth of '
            f'field, magazine-quality composition.'
        )
        lines.append(line(
            sku, name, 'decor/vases', 'vase_or_decor_object',
            f'{stem}-001.jpg', studio_prompt,
            f'{stem}-scene-living-001.jpg', scene_a_prompt,
            f'{stem}-scene-detail-002.jpg', scene_b_prompt,
            {'material': mat, 'color_family': color, 'style': style,
             'room': 'living'}
        ))
    return lines


def gen_mirrors(n=15):
    lines = []
    descs = [
        ('Round Brass', 'A large round wall mirror with slim brushed brass frame'),
        ('Round Black', 'A round wall mirror with thin black metal frame'),
        ('Rectangular Oak', 'A rectangular wall mirror with slim solid oak frame'),
        ('Arched', 'An arched-top wall mirror with metal frame'),
        ('Sunburst', 'A sunburst-style wall mirror with radiating brass rays'),
        ('Full-Length', 'A full-length floor-standing leaner mirror'),
        ('Frameless', 'A frameless rectangular wall mirror with beveled edges'),
        ('Convex', 'A convex round wall mirror with antiqued brass frame'),
        ('Hexagonal', 'A hexagonal wall mirror with thin metal frame'),
        ('Capsule', 'A capsule-shape wall mirror with rounded corners'),
        ('Wood-Frame', 'A rectangular mirror with chunky reclaimed-wood frame'),
        ('Tabletop', 'A small tabletop vanity mirror with adjustable stand'),
        ('Cluster Set', 'A cluster set of three small round mirrors'),
        ('Decorative', 'A decorative carved-frame wall mirror'),
        ('Smoke', 'A smoke-tinted round wall mirror with brass frame'),
    ][:n]
    for i,(sub,desc) in enumerate(descs):
        city = NEW_NAMES[(152+i) % len(NEW_NAMES)]
        sku = f'DRX-HL-MIR-{kebab(sub).upper()[:8]}'
        name = f'Mirror {sub} {city.capitalize()}'
        stem = f'mirror-{kebab(sub)}-{city.lower()}'
        color = take('color')
        style = take('style')
        mat = 'wood' if 'wood' in sub.lower() or 'oak' in sub.lower() else 'metal'
        usage['material'][mat] = usage['material'].get(mat,0)+1
        studio_prompt = (
            f'{desc} in {color} {mat} finish. Approximately 80 centimeters in diameter or '
            f'tall. {style.capitalize()} silhouette.'
        )
        scene_a_prompt = (
            'Place this exact mirror hanging above a slim solid oak console table in a '
            'scandinavian entryway, with a small ceramic vase holding pampas grass and a bowl '
            'of keys on the console, light oak flooring, soft daylight from a window out of '
            'frame. Photorealistic interior photography, scandinavian aesthetic, '
            'magazine-quality composition.'
        )
        scene_b_prompt = (
            f'Close-up detail of this mirror\'s frame, the {mat} texture clearly visible. '
            f'Soft natural light, shallow depth of field, defocused entryway background. '
            f'Photorealistic, scandinavian aesthetic, magazine-quality composition.'
        )
        lines.append(line(
            sku, name, 'decor/mirrors', 'wall_mirror',
            f'{stem}-001.jpg', studio_prompt,
            f'{stem}-scene-hallway-001.jpg', scene_a_prompt,
            f'{stem}-scene-detail-002.jpg', scene_b_prompt,
            {'material': mat, 'color_family': color, 'style': style,
             'room': 'living'}
        ))
    return lines


def gen_wall_art(n=13):
    lines = []
    descs = [
        ('Single Botanical', 'A single framed botanical print'),
        ('Mountain Trio', 'A trio of black-and-white minimal mountain line drawings'),
        ('Abstract Pair', 'A pair of soft abstract gradient prints'),
        ('Dried-Flower Sketch', 'A small black-on-cream sketch of a single dried flower stem'),
        ('Geometric', 'A framed geometric line-art print'),
        ('Map', 'A framed vintage-style map print'),
        ('Photograph BW', 'A framed black-and-white minimal landscape photograph'),
        ('Watercolor', 'A framed soft watercolor abstract print'),
        ('Typography', 'A framed minimalist typography print'),
        ('Japanese', 'A framed japanese-inspired ink drawing'),
        ('Bohemian', 'A framed bohemian-style folk art print'),
        ('Modernist', 'A framed bauhaus-modernist colour-block print'),
        ('Coastal', 'A framed coastal-inspired soft minimal print'),
    ][:n]
    for i,(sub,desc) in enumerate(descs):
        city = NEW_NAMES[(167+i) % len(NEW_NAMES)]
        sku = f'DRX-HL-WA-{kebab(sub).upper()[:8]}'
        name = f'Wall Art {sub} {city.capitalize()}'
        stem = f'wall-art-{kebab(sub)}-{city.lower()}'
        color = take('color')
        style = take('style')
        mat = 'wood'
        usage['material'][mat] = usage['material'].get(mat,0)+1
        studio_prompt = (
            f'{desc} framed in slim solid black wood. {style.capitalize()} aesthetic. '
            f'Frame approximately 40 centimeters wide and 50 centimeters tall.'
        )
        scene_a_prompt = (
            'Place this exact wall art hanging in a sun-drenched scandinavian living room '
            'above a slim oak console table with a sage green ceramic vase. Walls in pale warm '
            'white, light oak flooring. Photorealistic interior photography, scandinavian '
            'aesthetic, magazine-quality composition.'
        )
        scene_b_prompt = (
            f'Close-up detail of this wall art frame, capturing the wood grain of the frame. '
            f'Soft natural light, shallow depth of field. Photorealistic, scandinavian '
            f'aesthetic, magazine-quality composition.'
        )
        camera = 'wall_art_trio' if 'trio' in sub.lower() or 'pair' in sub.lower() else 'wall_art'
        lines.append(line(
            sku, name, 'decor/wall-art', camera,
            f'{stem}-001.jpg', studio_prompt,
            f'{stem}-scene-living-001.jpg', scene_a_prompt,
            f'{stem}-scene-detail-002.jpg', scene_b_prompt,
            {'material': mat, 'color_family': color, 'style': style,
             'room': 'living'}
        ))
    return lines


def gen_outdoor_garden(n=11):
    lines = []
    descs = [
        ('Bistro Set', 'A bistro outdoor set with two chairs and a round table'),
        ('Lounger', 'An outdoor lounger with curved frame and woven seat'),
        ('Bench', 'A long outdoor bench with slatted wood seat'),
        ('Side Table', 'An outdoor side table with metal frame and slatted top'),
        ('Stool', 'An outdoor stool with woven rope seat'),
        ('Sunbed', 'An outdoor sunbed with adjustable backrest'),
        ('Hanging Chair', 'A hanging outdoor chair with rattan seat'),
        ('Coffee Table', 'An outdoor coffee table with stone-look top'),
        ('Dining Set', 'An outdoor dining set with table and four chairs'),
        ('Hammock', 'An outdoor freestanding hammock with metal frame'),
        ('Footstool', 'An outdoor footstool with woven rope top'),
    ][:n]
    for i,(sub,desc) in enumerate(descs):
        city = NEW_NAMES[(180+i) % len(NEW_NAMES)]
        sku = f'DRX-HL-OG-{kebab(sub).upper()[:8]}'
        name = f'Outdoor {sub} {city.capitalize()}'
        stem = f'outdoor-{kebab(sub)}-{city.lower()}'
        color = take('color')
        style = take('style')
        mat = 'metal'
        usage['material'][mat] = usage['material'].get(mat,0)+1
        studio_prompt = (
            f'{desc} in {color} powder-coated metal finish. {style.capitalize()} silhouette.'
        )
        scene_a_prompt = (
            'Place this exact outdoor furniture on a stone-paved patio at golden hour, '
            'lush green foliage and a hint of a modern timber-clad house in the defocused '
            'background. Photorealistic exterior photography, twilight tones, magazine-quality '
            'composition.'
        )
        scene_b_prompt = (
            f'Close-up detail of this outdoor furniture\'s frame and seat surface, capturing '
            f'the {mat} powder-coat texture. Soft natural light, shallow depth of field. '
            f'Photorealistic, magazine-quality composition.'
        )
        lines.append(line(
            sku, name, 'outdoor/garden-furniture', 'outdoor_set',
            f'{stem}-001.jpg', studio_prompt,
            f'{stem}-scene-terrace-001.jpg', scene_a_prompt,
            f'{stem}-scene-detail-002.jpg', scene_b_prompt,
            {'material': mat, 'color_family': color, 'style': style,
             'room': 'outdoor'}
        ))
    return lines


def gen_outdoor_lights(n=12):
    lines = []
    descs = [
        ('Bollard', 'A modern outdoor bollard light with cylindrical body'),
        ('Wall Sconce', 'An outdoor wall sconce with cylindrical metal body'),
        ('Path Light', 'A short outdoor path light with downward-facing shade'),
        ('Lantern', 'An outdoor hanging lantern with cage-style frame'),
        ('Step Light', 'An outdoor step light with horizontal slim profile'),
        ('Pillar', 'A tall outdoor pillar light with weathered metal finish'),
        ('Spotlight', 'An outdoor spotlight with adjustable head'),
        ('String', 'An outdoor string of festoon globe lights'),
        ('Solar', 'A solar-powered outdoor garden stake light'),
        ('Up-Down', 'An outdoor wall light with up-and-down beam pattern'),
        ('Pendant', 'An outdoor pendant light suspended over a pergola'),
        ('Floor', 'An outdoor floor lantern with weathered base'),
    ][:n]
    for i,(sub,desc) in enumerate(descs):
        city = NEW_NAMES[(192+i) % len(NEW_NAMES)]
        sku = f'DRX-HL-OL-{kebab(sub).upper()[:8]}'
        name = f'Outdoor Lamp {sub} {city.capitalize()}'
        stem = f'outdoor-lamp-{kebab(sub)}-{city.lower()}'
        color = take('color')
        style = take('style')
        mat = 'metal'
        usage['material'][mat] = usage['material'].get(mat,0)+1
        studio_prompt = (
            f'{desc} in {color} {mat} finish. {style.capitalize()} silhouette, '
            f'photorealistic catalog shot.'
        )
        scene_a_prompt = (
            'Place this exact outdoor light along a stone garden path at dusk, the warm '
            'glow visible on the gravel beneath, soft greenery in the defocused background. '
            'Photorealistic exterior photography, twilight tones, magazine-quality composition.'
        )
        scene_b_prompt = (
            f'Close-up detail of this outdoor light, the warm glow visible against a softly '
            f'defocused garden background at dusk. Photorealistic, magazine-quality composition.'
        )
        lines.append(line(
            sku, name, 'outdoor/outdoor-lighting', 'outdoor_lamp',
            f'{stem}-001.jpg', studio_prompt,
            f'{stem}-scene-garden-001.jpg', scene_a_prompt,
            f'{stem}-scene-detail-002.jpg', scene_b_prompt,
            {'material': mat, 'color_family': color, 'style': style,
             'room': 'outdoor'}
        ))
    return lines


# ============================================================
# Aggregate
# ============================================================

all_lines = []
all_lines.extend(gen_sofas())
all_lines.extend(gen_lounge())
all_lines.extend(gen_coffee_tables())
all_lines.extend(gen_beds())
all_lines.extend(gen_nightstands())
all_lines.extend(gen_wardrobes())
all_lines.extend(gen_dining_tables())
all_lines.extend(gen_dining_chairs())
all_lines.extend(gen_pendant_lights())
all_lines.extend(gen_floor_lamps())
all_lines.extend(gen_table_lamps())
all_lines.extend(gen_vases())
all_lines.extend(gen_mirrors())
all_lines.extend(gen_wall_art())
all_lines.extend(gen_outdoor_garden())
all_lines.extend(gen_outdoor_lights())

# Dedup by SKU
seen = set()
unique = []
for ln in all_lines:
    if ln['sku'] in seen:
        # Append a suffix
        i = 2
        while f"{ln['sku']}-{i}" in seen:
            i += 1
        ln['sku'] = f"{ln['sku']}-{i}"
    seen.add(ln['sku'])
    unique.append(ln)

# Distribution report
print(f"Generated {len(unique)} new product lines")
print(f"Color distribution: {usage['color']}")
print(f"Material distribution: {usage['material']}")
print(f"Fabric distribution: {usage['fabric']}")
print(f"Style distribution: {usage['style']}")

# Per-leaf line count
from collections import Counter
leaf_counts = Counter(ln['category'] for ln in unique)
print("\nNew lines per leaf:")
for leaf, n in sorted(leaf_counts.items()):
    print(f"  {n:3d}  {leaf}")

# Save
with OUT_PATH.open('w') as f:
    json.dump({'$schema': './home-living.schema.json',
               'theme': 'home-living',
               'version': '2.0.0',
               'phase': 2,
               'notes': ['Auto-generated by tools/build-phase2-manifest.py',
                        '204 new product lines bringing each leaf to >=20 cards',
                        'Reuses camera_conventions and anchors from home-living.json'],
               'products': {ln['sku']: ln for ln in unique}}, f, indent=2)
print(f"\nWritten {OUT_PATH}")
