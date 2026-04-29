<?php
/**
 * Catalog expansion: bumps each top-level category to ~20 products by
 * (a) adding more variants to existing configurables and (b) adding new
 * simple products to under-stocked leaves. Reuses existing imagery so we
 * don't have to regenerate the full image set.
 *
 * Idempotent — re-running is a no-op (skips rows whose SKU already exists).
 *
 * Run from the sample-data-themes package root:
 *   php tools/expand-catalog.php
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$base = $root . '/packages/theme-home-living/_files/base';
$i18nEn = $root . '/packages/theme-home-living/_files/i18n/en_US';
$i18nNl = $root . '/packages/theme-home-living/_files/i18n/nl_NL';

// =============================================================================
// Helpers
// =============================================================================

function readCsv(string $path): array
{
    $rows = array_map('str_getcsv', file($path));
    $header = array_shift($rows);
    $out = [];
    foreach ($rows as $r) {
        $out[] = array_combine($header, array_pad($r, count($header), ''));
    }
    return ['header' => $header, 'rows' => $out];
}

function writeCsv(string $path, array $header, array $rows): void
{
    $fp = fopen($path, 'w');
    fputcsv($fp, $header);
    foreach ($rows as $r) {
        $line = [];
        foreach ($header as $col) {
            $line[] = $r[$col] ?? '';
        }
        fputcsv($fp, $line);
    }
    fclose($fp);
}

function appendUnique(string $path, array $newRows, string $skuColumn): int
{
    $existing = readCsv($path);
    $skus = array_column($existing['rows'], $skuColumn);
    $added = 0;
    foreach ($newRows as $r) {
        if (in_array($r[$skuColumn], $skus, true)) continue;
        $existing['rows'][] = $r;
        $skus[] = $r[$skuColumn];
        $added++;
    }
    if ($added > 0) {
        writeCsv($path, $existing['header'], $existing['rows']);
    }
    return $added;
}

// =============================================================================
// Phase A: New configurable variants (no new images required — reuse existing)
// =============================================================================

$newVariants = [];

// Sofa Helsinki: add navy linen, forest velvet, beige linen, grey velvet
foreach ([
    ['DRX-HL-SOFA-HEL-NVY-LIN', 949, 8, 'navy', 'linen', 'sofa-helsinki-navy-velvet-001.jpg'],
    ['DRX-HL-SOFA-HEL-FOR-VEL', 999, 6, 'forest', 'velvet', 'sofa-helsinki-forest-linen-001.jpg'],
    ['DRX-HL-SOFA-HEL-BEI-LIN', 899, 9, 'beige', 'linen', 'sofa-helsinki-beige-boucle-001.jpg'],
    ['DRX-HL-SOFA-HEL-GRY-VEL', 949, 7, 'grey', 'velvet', 'sofa-helsinki-grey-linen-001.jpg'],
] as $row) {
    [$sku, $price, $qty, $color, $fabric, $img] = $row;
    $newVariants[] = [
        'parent_sku' => 'DRX-HL-SOFA-HEL', 'child_sku' => $sku,
        'attribute_set' => 'furniture', 'price' => (string) $price, 'qty' => (string) $qty,
        'visibility' => '1', 'status' => '1',
        'material' => 'fabric', 'style' => 'scandinavian', 'room' => 'living',
        'color_family' => $color, 'fabric' => $fabric,
        'images' => "{$img},sofa-helsinki-scene-detail-002.jpg,sofa-helsinki-scene-living-001.jpg",
    ];
}

// Sofa Oslo: add forest, charcoal
foreach ([
    ['DRX-HL-SOFA-OSL-FOR', 1199, 5, 'forest', 'sofa-oslo-grey-001.jpg'],
    ['DRX-HL-SOFA-OSL-CHA', 1249, 4, 'charcoal', 'sofa-oslo-navy-001.jpg'],
] as $row) {
    [$sku, $price, $qty, $color, $img] = $row;
    $newVariants[] = [
        'parent_sku' => 'DRX-HL-SOFA-OSL', 'child_sku' => $sku,
        'attribute_set' => 'furniture', 'price' => (string) $price, 'qty' => (string) $qty,
        'visibility' => '1', 'status' => '1',
        'material' => 'fabric', 'style' => 'modern', 'room' => 'living',
        'color_family' => $color, 'fabric' => '',
        'images' => "{$img},sofa-oslo-scene-detail-002.jpg,sofa-oslo-scene-living-001.jpg",
    ];
}

// Lounge Aarhus: add boucle, woven
foreach ([
    ['DRX-HL-LC-AAR-BOU', 549, 12, 'boucle', 'lounge-aarhus-linen-001.jpg'],
    ['DRX-HL-LC-AAR-WOV', 479, 18, 'woven', 'lounge-aarhus-velvet-001.jpg'],
] as $row) {
    [$sku, $price, $qty, $fabric, $img] = $row;
    $newVariants[] = [
        'parent_sku' => 'DRX-HL-LC-AAR', 'child_sku' => $sku,
        'attribute_set' => 'furniture', 'price' => (string) $price, 'qty' => (string) $qty,
        'visibility' => '1', 'status' => '1',
        'material' => 'fabric', 'style' => 'scandinavian', 'room' => 'living',
        'color_family' => '', 'fabric' => $fabric,
        'images' => "{$img},lounge-aarhus-scene-detail-002.jpg,lounge-aarhus-scene-reading-001.jpg",
    ];
}

// Dining Chair Charlottenborg: add navy, oak, walnut
foreach ([
    ['DRX-HL-DC-CHA-NVY', 129, 50, 'navy', 'dining-chair-charlottenborg-black-001.jpg'],
    ['DRX-HL-DC-CHA-OAK', 139, 55, 'oak', 'dining-chair-charlottenborg-white-001.jpg'],
    ['DRX-HL-DC-CHA-WAL', 149, 40, 'walnut', 'dining-chair-charlottenborg-grey-001.jpg'],
] as $row) {
    [$sku, $price, $qty, $color, $img] = $row;
    $newVariants[] = [
        'parent_sku' => 'DRX-HL-DC-CHA', 'child_sku' => $sku,
        'attribute_set' => 'furniture', 'price' => (string) $price, 'qty' => (string) $qty,
        'visibility' => '1', 'status' => '1',
        'material' => 'wood', 'style' => 'modern', 'room' => 'dining',
        'color_family' => $color, 'fabric' => '',
        'images' => "{$img},dining-chair-charlottenborg-scene-detail-002.jpg,dining-chair-charlottenborg-scene-dining-001.jpg",
    ];
}

// Wall Art Print: add japanese, abstract, minimalist
foreach ([
    ['DRX-HL-WA-PRT-JPN', 99, 30, 'japanese', 'wall-art-scandinavian-001.jpg'],
    ['DRX-HL-WA-PRT-ABS', 99, 25, 'abstract', 'wall-art-midcentury-001.jpg'],
    ['DRX-HL-WA-PRT-MIN', 89, 35, 'minimalist', 'wall-art-bohemian-001.jpg'],
] as $row) {
    [$sku, $price, $qty, $style_val, $img] = $row;
    $newVariants[] = [
        'parent_sku' => 'DRX-HL-WA-PRT', 'child_sku' => $sku,
        'attribute_set' => 'decor', 'price' => (string) $price, 'qty' => (string) $qty,
        'visibility' => '1', 'status' => '1',
        'material' => 'wood', 'style' => $style_val, 'room' => 'living',
        'color_family' => '', 'fabric' => '',
        'images' => $img,
    ];
}

// Outdoor Garden: add white, beige, navy, forest
foreach ([
    ['DRX-HL-OUT-GAR-WHT', 599, 10, 'white', 'outdoor-garden-grey-001.jpg'],
    ['DRX-HL-OUT-GAR-BEI', 619, 8, 'beige', 'outdoor-garden-grey-001.jpg'],
    ['DRX-HL-OUT-GAR-NVY', 649, 6, 'navy', 'outdoor-garden-black-001.jpg'],
    ['DRX-HL-OUT-GAR-FOR', 649, 5, 'forest', 'outdoor-garden-black-001.jpg'],
] as $row) {
    [$sku, $price, $qty, $color, $img] = $row;
    $newVariants[] = [
        'parent_sku' => 'DRX-HL-OUT-GAR', 'child_sku' => $sku,
        'attribute_set' => 'furniture', 'price' => (string) $price, 'qty' => (string) $qty,
        'visibility' => '1', 'status' => '1',
        'material' => 'metal', 'style' => 'modern', 'room' => 'outdoor',
        'color_family' => $color, 'fabric' => '',
        'images' => "{$img},outdoor-garden-scene-evening-002.jpg,outdoor-garden-scene-terrace-001.jpg",
    ];
}

// =============================================================================
// Phase B: New simple products in under-stocked categories — reuse imagery
// from sister products to avoid new image generation.
// =============================================================================

$newSimples = [];

// Helper to build a simple product row
$mkSimple = function (
    string $sku, string $cat, float $price, int $qty,
    string $material, string $style, string $room, string $dimensions, float $weight,
    string $images, string $weight_capacity = ''
): array {
    return [
        'sku' => $sku, 'attribute_set' => 'furniture',
        'price' => number_format($price, 2, '.', ''), 'qty' => (string) $qty,
        'visibility' => '4', 'status' => '1',
        'categories' => $cat,
        'material' => $material, 'style' => $style, 'room' => $room,
        'dimensions' => $dimensions, 'weight_capacity' => $weight_capacity,
        'weight' => number_format($weight, 1, '.', ''),
        'images' => $images,
    ];
};

// Living Room: add side tables, a TV stand, console table
$ctScenes = ',coffee-table-bergen-scene-detail-002.jpg,coffee-table-bergen-scene-living-001.jpg';
$ctScenesO = ',coffee-table-oslo-scene-detail-002.jpg,coffee-table-oslo-scene-living-001.jpg';
$ctScenesT = ',coffee-table-tromso-scene-detail-002.jpg,coffee-table-tromso-scene-living-001.jpg';

$newSimples[] = $mkSimple('DRX-HL-CT-004', 'living-room/coffee-tables', 219.00, 35, 'wood', 'scandinavian', 'living', '90 x 50 x 40 cm', 14.0, "coffee-table-oslo-001.jpg{$ctScenesO}");
$newSimples[] = $mkSimple('DRX-HL-CT-005', 'living-room/coffee-tables', 339.00, 25, 'wood', 'mid-century', 'living', '130 x 70 x 45 cm', 19.5, "coffee-table-bergen-001.jpg{$ctScenes}");
$newSimples[] = $mkSimple('DRX-HL-CT-006', 'living-room/coffee-tables', 269.00, 20, 'glass', 'modern', 'living', '110 x 65 x 42 cm', 13.5, "coffee-table-tromso-001.jpg{$ctScenesT}");

// Bedroom: more nightstands, bedroom-side accents
$nsScenesM = 'nightstand-malmo-scene-bedroom-001.jpg,nightstand-malmo-scene-detail-002.jpg';
$nsScenesL = 'nightstand-lund-scene-bedroom-001.jpg,nightstand-lund-scene-detail-002.jpg';

$newSimples[] = $mkSimple('DRX-HL-NS-003', 'bedroom/nightstands', 159.00, 50, 'wood', 'scandinavian', 'bedroom', '50 x 45 x 60 cm', 8.5, "nightstand-malmo-001.jpg,{$nsScenesM}");
$newSimples[] = $mkSimple('DRX-HL-NS-004', 'bedroom/nightstands', 179.00, 40, 'wood', 'mid-century', 'bedroom', '55 x 45 x 65 cm', 9.0, "nightstand-lund-001.jpg,{$nsScenesL}");
$newSimples[] = $mkSimple('DRX-HL-NS-005', 'bedroom/nightstands', 109.00, 60, 'wood', 'modern', 'bedroom', '40 x 35 x 50 cm', 6.5, "nightstand-malmo-001.jpg,{$nsScenesM}");

// More wardrobes
$wrScenes = 'wardrobe-goteborg-scene-bedroom-001.jpg,wardrobe-goteborg-scene-detail-002.jpg';
$newSimples[] = $mkSimple('DRX-HL-WAR-002', 'bedroom/wardrobes', 999.00, 10, 'wood', 'mid-century', 'bedroom', '200 x 60 x 220 cm', 85.0, "wardrobe-goteborg-001.jpg,{$wrScenes}");
$newSimples[] = $mkSimple('DRX-HL-WAR-003', 'bedroom/wardrobes', 1199.00, 6, 'wood', 'modern', 'bedroom', '220 x 65 x 230 cm', 95.0, "wardrobe-goteborg-001.jpg,{$wrScenes}");

// More beds
$bedScenes = 'bed-stockholm-scene-bedroom-001.jpg,bed-stockholm-scene-detail-002.jpg';
$newSimples[] = $mkSimple('DRX-HL-BED-002', 'bedroom/beds', 749.00, 15, 'wood', 'scandinavian', 'bedroom', '180 x 200 cm', 60.0, "bed-stockholm-001.jpg,{$bedScenes}", 'Max. 220 kg');
$newSimples[] = $mkSimple('DRX-HL-BED-003', 'bedroom/beds', 849.00, 12, 'wood', 'modern', 'bedroom', '200 x 200 cm', 65.0, "bed-stockholm-001.jpg,{$bedScenes}", 'Max. 240 kg');

// Dining: more tables, more chairs
$dtScenes = 'dining-table-reykjavik-scene-detail-002.jpg,dining-table-reykjavik-scene-dining-001.jpg';
$newSimples[] = $mkSimple('DRX-HL-DT-002', 'dining/dining-tables', 949.00, 12, 'wood', 'mid-century', 'dining', '200 x 100 x 75 cm', 48.0, "dining-table-reykjavik-001.jpg,{$dtScenes}");
$newSimples[] = $mkSimple('DRX-HL-DT-003', 'dining/dining-tables', 1199.00, 6, 'wood', 'modern', 'dining', '220 x 100 x 76 cm', 56.0, "dining-table-reykjavik-001.jpg,{$dtScenes}");

$dcScenes = 'dining-chair-helsinki-scene-detail-002.jpg,dining-chair-helsinki-scene-dining-001.jpg';
$newSimples[] = $mkSimple('DRX-HL-DC-002', 'dining/dining-chairs', 89.00, 100, 'wood', 'mid-century', 'dining', '45 x 50 x 85 cm', 5.0, "dining-chair-helsinki-001.jpg,{$dcScenes}", 'Max. 120 kg');
$newSimples[] = $mkSimple('DRX-HL-DC-003', 'dining/dining-chairs', 109.00, 80, 'wood', 'modern', 'dining', '46 x 52 x 86 cm', 5.7, "dining-chair-helsinki-001.jpg,{$dcScenes}", 'Max. 130 kg');

// Lighting: more pendants, table & floor lamps
$plScenes = 'pendant-light-espoo-scene-detail-002.jpg,pendant-light-espoo-scene-dining-001.jpg';
$newSimples[] = ['sku' => 'DRX-HL-PL-002', 'attribute_set' => 'lighting', 'price' => '229.00', 'qty' => '40', 'visibility' => '4', 'status' => '1', 'categories' => 'lighting/pendant-lights', 'material' => 'metal', 'style' => 'modern', 'room' => 'living,dining', 'dimensions' => '50 cm diameter', 'weight_capacity' => '', 'weight' => '3.0', 'images' => "pendant-light-espoo-001.jpg,{$plScenes}"];
$newSimples[] = ['sku' => 'DRX-HL-PL-003', 'attribute_set' => 'lighting', 'price' => '169.00', 'qty' => '60', 'visibility' => '4', 'status' => '1', 'categories' => 'lighting/pendant-lights', 'material' => 'metal', 'style' => 'industrial', 'room' => 'dining,living', 'dimensions' => '35 cm diameter', 'weight_capacity' => '', 'weight' => '2.2', 'images' => "pendant-light-espoo-001.jpg,{$plScenes}"];

$flScenesS = 'floor-lamp-stockholm-scene-detail-002.jpg,floor-lamp-stockholm-scene-living-001.jpg';
$flScenesT = 'floor-lamp-trondheim-scene-detail-002.jpg,floor-lamp-trondheim-scene-living-001.jpg';
$newSimples[] = ['sku' => 'DRX-HL-FL-003', 'attribute_set' => 'lighting', 'price' => '109.00', 'qty' => '60', 'visibility' => '4', 'status' => '1', 'categories' => 'lighting/floor-lamps', 'material' => 'metal', 'style' => 'modern', 'room' => 'living,bedroom', 'dimensions' => '155 cm height', 'weight_capacity' => '', 'weight' => '4.5', 'images' => "floor-lamp-stockholm-001.jpg,{$flScenesS}"];
$newSimples[] = ['sku' => 'DRX-HL-FL-004', 'attribute_set' => 'lighting', 'price' => '189.00', 'qty' => '40', 'visibility' => '4', 'status' => '1', 'categories' => 'lighting/floor-lamps', 'material' => 'metal', 'style' => 'mid-century', 'room' => 'living', 'dimensions' => '165 cm height', 'weight_capacity' => '', 'weight' => '5.5', 'images' => "floor-lamp-trondheim-001.jpg,{$flScenesT}"];

$tlScenes = 'table-lamp-bergen-scene-bedroom-001.jpg,table-lamp-bergen-scene-detail-002.jpg';
$newSimples[] = ['sku' => 'DRX-HL-TL-002', 'attribute_set' => 'lighting', 'price' => '79.00', 'qty' => '120', 'visibility' => '4', 'status' => '1', 'categories' => 'lighting/table-lamps', 'material' => 'ceramic', 'style' => 'scandinavian', 'room' => 'living,bedroom', 'dimensions' => '40 cm height', 'weight_capacity' => '', 'weight' => '2.0', 'images' => "table-lamp-bergen-001.jpg,{$tlScenes}"];
$newSimples[] = ['sku' => 'DRX-HL-TL-003', 'attribute_set' => 'lighting', 'price' => '89.00', 'qty' => '90', 'visibility' => '4', 'status' => '1', 'categories' => 'lighting/table-lamps', 'material' => 'metal', 'style' => 'industrial', 'room' => 'living,bedroom', 'dimensions' => '38 cm height', 'weight_capacity' => '', 'weight' => '2.4', 'images' => "table-lamp-bergen-001.jpg,{$tlScenes}"];

// Decor: more vases, mirrors, wall art
$vsScenesC = 'vase-copenhagen-scene-detail-002.jpg,vase-copenhagen-scene-living-001.jpg';
$vsScenesT = 'vase-tampere-scene-detail-002.jpg,vase-tampere-scene-living-001.jpg';
$newSimples[] = ['sku' => 'DRX-HL-VAS-003', 'attribute_set' => 'decor', 'price' => '34.95', 'qty' => '180', 'visibility' => '4', 'status' => '1', 'categories' => 'decor/vases', 'material' => 'ceramic', 'style' => 'mid-century', 'room' => 'living', 'dimensions' => '28 cm height', 'weight_capacity' => '', 'weight' => '1.0', 'images' => "vase-copenhagen-001.jpg,{$vsScenesC}"];
$newSimples[] = ['sku' => 'DRX-HL-VAS-004', 'attribute_set' => 'decor', 'price' => '49.95', 'qty' => '140', 'visibility' => '4', 'status' => '1', 'categories' => 'decor/vases', 'material' => 'glass', 'style' => 'modern', 'room' => 'living,dining', 'dimensions' => '35 cm height', 'weight_capacity' => '', 'weight' => '1.4', 'images' => "vase-tampere-001.jpg,{$vsScenesT}"];

$mirScenes = 'mirror-aalborg-scene-bedroom-002.jpg,mirror-aalborg-scene-hallway-001.jpg';
$newSimples[] = ['sku' => 'DRX-HL-MIR-002', 'attribute_set' => 'decor', 'price' => '189.00', 'qty' => '30', 'visibility' => '4', 'status' => '1', 'categories' => 'decor/mirrors', 'material' => 'metal', 'style' => 'mid-century', 'room' => 'living,bedroom', 'dimensions' => '70 x 100 cm', 'weight_capacity' => '', 'weight' => '5.0', 'images' => "mirror-aalborg-001.jpg,{$mirScenes}"];
$newSimples[] = ['sku' => 'DRX-HL-MIR-003', 'attribute_set' => 'decor', 'price' => '129.00', 'qty' => '50', 'visibility' => '4', 'status' => '1', 'categories' => 'decor/mirrors', 'material' => 'wood', 'style' => 'scandinavian', 'room' => 'bedroom,living', 'dimensions' => '55 x 80 cm', 'weight_capacity' => '', 'weight' => '3.5', 'images' => "mirror-aalborg-001.jpg,{$mirScenes}"];

$waScenes = 'wall-art-trio-scene-detail-002.jpg,wall-art-trio-scene-living-001.jpg';
$newSimples[] = ['sku' => 'DRX-HL-WA-002', 'attribute_set' => 'decor', 'price' => '99.00', 'qty' => '40', 'visibility' => '4', 'status' => '1', 'categories' => 'decor/wall-art', 'material' => 'wood', 'style' => 'mid-century', 'room' => 'living,dining', 'dimensions' => '40 x 50 cm', 'weight_capacity' => '', 'weight' => '1.3', 'images' => "wall-art-trio-001.jpg,{$waScenes}"];
$newSimples[] = ['sku' => 'DRX-HL-WA-003', 'attribute_set' => 'decor', 'price' => '129.00', 'qty' => '30', 'visibility' => '4', 'status' => '1', 'categories' => 'decor/wall-art', 'material' => 'wood', 'style' => 'modern', 'room' => 'living', 'dimensions' => '50 x 60 cm', 'weight_capacity' => '', 'weight' => '1.6', 'images' => "wall-art-trio-001.jpg,{$waScenes}"];

// Outdoor: more outdoor lighting and garden furniture pieces
$olScenes = 'outdoor-lamp-skagen-scene-detail-002.jpg,outdoor-lamp-skagen-scene-garden-001.jpg';
$newSimples[] = ['sku' => 'DRX-HL-OL-002', 'attribute_set' => 'lighting', 'price' => '149.00', 'qty' => '40', 'visibility' => '4', 'status' => '1', 'categories' => 'outdoor/outdoor-lighting', 'material' => 'metal', 'style' => 'industrial', 'room' => 'outdoor', 'dimensions' => '200 cm height', 'weight_capacity' => '', 'weight' => '6.5', 'images' => "outdoor-lamp-skagen-001.jpg,{$olScenes}"];
$newSimples[] = ['sku' => 'DRX-HL-OL-003', 'attribute_set' => 'lighting', 'price' => '89.00', 'qty' => '60', 'visibility' => '4', 'status' => '1', 'categories' => 'outdoor/outdoor-lighting', 'material' => 'metal', 'style' => 'scandinavian', 'room' => 'outdoor', 'dimensions' => '150 cm height', 'weight_capacity' => '', 'weight' => '4.0', 'images' => "outdoor-lamp-skagen-001.jpg,{$olScenes}"];
$newSimples[] = ['sku' => 'DRX-HL-OL-004', 'attribute_set' => 'lighting', 'price' => '69.00', 'qty' => '80', 'visibility' => '4', 'status' => '1', 'categories' => 'outdoor/outdoor-lighting', 'material' => 'metal', 'style' => 'modern', 'room' => 'outdoor', 'dimensions' => '120 cm height', 'weight_capacity' => '', 'weight' => '3.2', 'images' => "outdoor-lamp-skagen-001.jpg,{$olScenes}"];

$ogScenes = 'outdoor-garden-scene-evening-002.jpg,outdoor-garden-scene-terrace-001.jpg';
$newSimples[] = ['sku' => 'DRX-HL-OG-001', 'attribute_set' => 'furniture', 'price' => '349.00', 'qty' => '20', 'visibility' => '4', 'status' => '1', 'categories' => 'outdoor/garden-furniture', 'material' => 'metal', 'style' => 'modern', 'room' => 'outdoor', 'dimensions' => '60 x 60 x 75 cm', 'weight_capacity' => '', 'weight' => '8.0', 'images' => "outdoor-garden-grey-001.jpg,{$ogScenes}"];
$newSimples[] = ['sku' => 'DRX-HL-OG-002', 'attribute_set' => 'furniture', 'price' => '199.00', 'qty' => '40', 'visibility' => '4', 'status' => '1', 'categories' => 'outdoor/garden-furniture', 'material' => 'metal', 'style' => 'modern', 'room' => 'outdoor', 'dimensions' => '50 x 50 x 80 cm', 'weight_capacity' => 'Max. 120 kg', 'weight' => '6.0', 'images' => "outdoor-garden-black-001.jpg,{$ogScenes}"];
$newSimples[] = ['sku' => 'DRX-HL-OG-003', 'attribute_set' => 'furniture', 'price' => '799.00', 'qty' => '12', 'visibility' => '4', 'status' => '1', 'categories' => 'outdoor/garden-furniture', 'material' => 'metal', 'style' => 'modern', 'room' => 'outdoor', 'dimensions' => '180 x 100 x 75 cm', 'weight_capacity' => '', 'weight' => '22.0', 'images' => "outdoor-garden-grey-001.jpg,{$ogScenes}"];

// =============================================================================
// Phase C: New i18n rows (EN/NL) so the new SKUs have names in the storefront
// =============================================================================

$i18nNamesEn = [
    'DRX-HL-SOFA-HEL-NVY-LIN' => ['Sofa Helsinki - Navy Linen', 'Modular three-seat sofa in deep navy linen.'],
    'DRX-HL-SOFA-HEL-FOR-VEL' => ['Sofa Helsinki - Forest Velvet', 'Modular three-seat sofa in forest green velvet.'],
    'DRX-HL-SOFA-HEL-BEI-LIN' => ['Sofa Helsinki - Beige Linen', 'Modular three-seat sofa in soft beige linen.'],
    'DRX-HL-SOFA-HEL-GRY-VEL' => ['Sofa Helsinki - Grey Velvet', 'Modular three-seat sofa in grey velvet.'],
    'DRX-HL-SOFA-OSL-FOR' => ['Sofa Oslo - Forest', 'Curved sofa in forest green.'],
    'DRX-HL-SOFA-OSL-CHA' => ['Sofa Oslo - Charcoal', 'Curved sofa in charcoal.'],
    'DRX-HL-LC-AAR-BOU' => ['Lounge Chair Aarhus - Boucle', 'Lounge chair in textured boucle fabric.'],
    'DRX-HL-LC-AAR-WOV' => ['Lounge Chair Aarhus - Woven', 'Lounge chair in handwoven fabric.'],
    'DRX-HL-DC-CHA-NVY' => ['Dining Chair Charlottenborg - Navy', 'Dining chair in navy.'],
    'DRX-HL-DC-CHA-OAK' => ['Dining Chair Charlottenborg - Oak', 'Dining chair in oak finish.'],
    'DRX-HL-DC-CHA-WAL' => ['Dining Chair Charlottenborg - Walnut', 'Dining chair in walnut.'],
    'DRX-HL-WA-PRT-JPN' => ['Wall Art Print - Japanese', 'Japanese-inspired botanical print.'],
    'DRX-HL-WA-PRT-ABS' => ['Wall Art Print - Abstract', 'Abstract gallery wall art print.'],
    'DRX-HL-WA-PRT-MIN' => ['Wall Art Print - Minimalist', 'Minimalist line art print.'],
    'DRX-HL-OUT-GAR-WHT' => ['Outdoor Garden Set - White', 'Outdoor lounge set in powder-coated white.'],
    'DRX-HL-OUT-GAR-BEI' => ['Outdoor Garden Set - Beige', 'Outdoor lounge set in warm beige.'],
    'DRX-HL-OUT-GAR-NVY' => ['Outdoor Garden Set - Navy', 'Outdoor lounge set in navy.'],
    'DRX-HL-OUT-GAR-FOR' => ['Outdoor Garden Set - Forest', 'Outdoor lounge set in forest green.'],
    'DRX-HL-CT-004' => ['Coffee Table Oslo - Compact', 'Compact Scandinavian coffee table.'],
    'DRX-HL-CT-005' => ['Coffee Table Bergen - Wide', 'Wide mid-century coffee table.'],
    'DRX-HL-CT-006' => ['Coffee Table Tromso - Glass', 'Modern glass coffee table.'],
    'DRX-HL-NS-003' => ['Nightstand Malmo - Tall', 'Taller Scandinavian nightstand.'],
    'DRX-HL-NS-004' => ['Nightstand Lund - Wide', 'Wide mid-century nightstand.'],
    'DRX-HL-NS-005' => ['Nightstand Malmo - Compact', 'Compact modern nightstand.'],
    'DRX-HL-WAR-002' => ['Wardrobe Goteborg - Wide', 'Wide mid-century wardrobe.'],
    'DRX-HL-WAR-003' => ['Wardrobe Goteborg - XL', 'Extra-large modern wardrobe.'],
    'DRX-HL-BED-002' => ['Bed Stockholm - Queen', 'Queen-size Scandinavian bed frame.'],
    'DRX-HL-BED-003' => ['Bed Stockholm - King', 'King-size modern bed frame.'],
    'DRX-HL-DT-002' => ['Dining Table Reykjavik - Long', 'Long mid-century dining table.'],
    'DRX-HL-DT-003' => ['Dining Table Reykjavik - XL', 'Extra-large modern dining table.'],
    'DRX-HL-DC-002' => ['Dining Chair Helsinki - Mid-Century', 'Mid-century dining chair.'],
    'DRX-HL-DC-003' => ['Dining Chair Helsinki - Modern', 'Modern dining chair.'],
    'DRX-HL-PL-002' => ['Pendant Light Espoo - Wide', 'Wide modern pendant light.'],
    'DRX-HL-PL-003' => ['Pendant Light Espoo - Compact', 'Compact industrial pendant light.'],
    'DRX-HL-FL-003' => ['Floor Lamp Stockholm - Modern', 'Modern Scandinavian floor lamp.'],
    'DRX-HL-FL-004' => ['Floor Lamp Trondheim - Mid-Century', 'Mid-century industrial floor lamp.'],
    'DRX-HL-TL-002' => ['Table Lamp Bergen - Tall', 'Tall Scandinavian ceramic table lamp.'],
    'DRX-HL-TL-003' => ['Table Lamp Bergen - Industrial', 'Industrial metal table lamp.'],
    'DRX-HL-VAS-003' => ['Vase Copenhagen - Tall', 'Tall mid-century ceramic vase.'],
    'DRX-HL-VAS-004' => ['Vase Tampere - XL', 'Extra-large modern glass vase.'],
    'DRX-HL-MIR-002' => ['Mirror Aalborg - Large', 'Large mid-century round mirror.'],
    'DRX-HL-MIR-003' => ['Mirror Aalborg - Wood', 'Scandinavian wood-framed mirror.'],
    'DRX-HL-WA-002' => ['Wall Art Trio - Mid-Century', 'Mid-century gallery wall art trio.'],
    'DRX-HL-WA-003' => ['Wall Art Trio - Modern', 'Modern gallery wall art trio.'],
    'DRX-HL-OL-002' => ['Outdoor Lamp Skagen - Tall', 'Tall industrial outdoor lamp.'],
    'DRX-HL-OL-003' => ['Outdoor Lamp Skagen - Standard', 'Standard Scandinavian outdoor lamp.'],
    'DRX-HL-OL-004' => ['Outdoor Lamp Skagen - Compact', 'Compact modern outdoor lamp.'],
    'DRX-HL-OG-001' => ['Garden Side Table', 'Outdoor side table in powder-coated metal.'],
    'DRX-HL-OG-002' => ['Garden Stool', 'Outdoor stool in powder-coated metal.'],
    'DRX-HL-OG-003' => ['Garden Bench', 'Long outdoor bench in powder-coated metal.'],
];

$i18nNamesNl = [
    'DRX-HL-SOFA-HEL-NVY-LIN' => ['Bank Helsinki - Marineblauw Linnen', 'Modulaire driepersoonsbank in donker marineblauw linnen.'],
    'DRX-HL-SOFA-HEL-FOR-VEL' => ['Bank Helsinki - Bosgroen Velvet', 'Modulaire driepersoonsbank in bosgroen velvet.'],
    'DRX-HL-SOFA-HEL-BEI-LIN' => ['Bank Helsinki - Beige Linnen', 'Modulaire driepersoonsbank in zacht beige linnen.'],
    'DRX-HL-SOFA-HEL-GRY-VEL' => ['Bank Helsinki - Grijs Velvet', 'Modulaire driepersoonsbank in grijs velvet.'],
    'DRX-HL-SOFA-OSL-FOR' => ['Bank Oslo - Bosgroen', 'Gebogen bank in bosgroen.'],
    'DRX-HL-SOFA-OSL-CHA' => ['Bank Oslo - Antraciet', 'Gebogen bank in antraciet.'],
    'DRX-HL-LC-AAR-BOU' => ['Loungestoel Aarhus - Boucle', 'Loungestoel in boucle stof.'],
    'DRX-HL-LC-AAR-WOV' => ['Loungestoel Aarhus - Geweven', 'Loungestoel in geweven stof.'],
    'DRX-HL-DC-CHA-NVY' => ['Eetkamerstoel Charlottenborg - Marine', 'Eetkamerstoel in marineblauw.'],
    'DRX-HL-DC-CHA-OAK' => ['Eetkamerstoel Charlottenborg - Eik', 'Eetkamerstoel in eikenhout.'],
    'DRX-HL-DC-CHA-WAL' => ['Eetkamerstoel Charlottenborg - Walnoot', 'Eetkamerstoel in walnoot.'],
    'DRX-HL-WA-PRT-JPN' => ['Wandprint - Japans', 'Japans geïnspireerde botanische print.'],
    'DRX-HL-WA-PRT-ABS' => ['Wandprint - Abstract', 'Abstracte galerij wandprint.'],
    'DRX-HL-WA-PRT-MIN' => ['Wandprint - Minimalistisch', 'Minimalistische lijnkunst print.'],
    'DRX-HL-OUT-GAR-WHT' => ['Tuinset - Wit', 'Tuinset in poedercoating wit.'],
    'DRX-HL-OUT-GAR-BEI' => ['Tuinset - Beige', 'Tuinset in warm beige.'],
    'DRX-HL-OUT-GAR-NVY' => ['Tuinset - Marine', 'Tuinset in marineblauw.'],
    'DRX-HL-OUT-GAR-FOR' => ['Tuinset - Bosgroen', 'Tuinset in bosgroen.'],
    'DRX-HL-CT-004' => ['Salontafel Oslo - Compact', 'Compacte Scandinavische salontafel.'],
    'DRX-HL-CT-005' => ['Salontafel Bergen - Breed', 'Brede mid-century salontafel.'],
    'DRX-HL-CT-006' => ['Salontafel Tromso - Glas', 'Moderne glazen salontafel.'],
    'DRX-HL-NS-003' => ['Nachtkastje Malmo - Hoog', 'Hoger Scandinavisch nachtkastje.'],
    'DRX-HL-NS-004' => ['Nachtkastje Lund - Breed', 'Breed mid-century nachtkastje.'],
    'DRX-HL-NS-005' => ['Nachtkastje Malmo - Compact', 'Compact modern nachtkastje.'],
    'DRX-HL-WAR-002' => ['Kledingkast Goteborg - Breed', 'Brede mid-century kledingkast.'],
    'DRX-HL-WAR-003' => ['Kledingkast Goteborg - XL', 'Extra grote moderne kledingkast.'],
    'DRX-HL-BED-002' => ['Bed Stockholm - Queen', 'Queensize Scandinavisch bedframe.'],
    'DRX-HL-BED-003' => ['Bed Stockholm - King', 'Kingsize modern bedframe.'],
    'DRX-HL-DT-002' => ['Eettafel Reykjavik - Lang', 'Lange mid-century eettafel.'],
    'DRX-HL-DT-003' => ['Eettafel Reykjavik - XL', 'Extra grote moderne eettafel.'],
    'DRX-HL-DC-002' => ['Eetkamerstoel Helsinki - Mid-Century', 'Mid-century eetkamerstoel.'],
    'DRX-HL-DC-003' => ['Eetkamerstoel Helsinki - Modern', 'Moderne eetkamerstoel.'],
    'DRX-HL-PL-002' => ['Hanglamp Espoo - Breed', 'Brede moderne hanglamp.'],
    'DRX-HL-PL-003' => ['Hanglamp Espoo - Compact', 'Compacte industriële hanglamp.'],
    'DRX-HL-FL-003' => ['Vloerlamp Stockholm - Modern', 'Moderne Scandinavische vloerlamp.'],
    'DRX-HL-FL-004' => ['Vloerlamp Trondheim - Mid-Century', 'Mid-century industriële vloerlamp.'],
    'DRX-HL-TL-002' => ['Tafellamp Bergen - Hoog', 'Hoge Scandinavische keramische tafellamp.'],
    'DRX-HL-TL-003' => ['Tafellamp Bergen - Industrieel', 'Industriële metalen tafellamp.'],
    'DRX-HL-VAS-003' => ['Vaas Copenhagen - Hoog', 'Hoge mid-century keramische vaas.'],
    'DRX-HL-VAS-004' => ['Vaas Tampere - XL', 'Extra grote moderne glazen vaas.'],
    'DRX-HL-MIR-002' => ['Spiegel Aalborg - Groot', 'Grote mid-century ronde spiegel.'],
    'DRX-HL-MIR-003' => ['Spiegel Aalborg - Hout', 'Scandinavische spiegel met houten lijst.'],
    'DRX-HL-WA-002' => ['Wandkunst Trio - Mid-Century', 'Mid-century galerij wandkunst trio.'],
    'DRX-HL-WA-003' => ['Wandkunst Trio - Modern', 'Moderne galerij wandkunst trio.'],
    'DRX-HL-OL-002' => ['Buitenlamp Skagen - Hoog', 'Hoge industriële buitenlamp.'],
    'DRX-HL-OL-003' => ['Buitenlamp Skagen - Standaard', 'Standaard Scandinavische buitenlamp.'],
    'DRX-HL-OL-004' => ['Buitenlamp Skagen - Compact', 'Compacte moderne buitenlamp.'],
    'DRX-HL-OG-001' => ['Tuinbijzettafel', 'Bijzettafel voor buiten in poedercoating metaal.'],
    'DRX-HL-OG-002' => ['Tuinkruk', 'Kruk voor buiten in poedercoating metaal.'],
    'DRX-HL-OG-003' => ['Tuinbank', 'Lange bank voor buiten in poedercoating metaal.'],
];

// =============================================================================
// Apply
// =============================================================================

$variantsAdded = appendUnique($base . '/configurable_variations.csv', $newVariants, 'child_sku');
echo "Configurable variants added: {$variantsAdded}\n";

$simplesAdded = appendUnique($base . '/simple_products.csv', $newSimples, 'sku');
echo "Simple products added: {$simplesAdded}\n";

// i18n EN
$enRows = [];
foreach ($i18nNamesEn as $sku => [$name, $desc]) {
    $urlKey = strtolower(str_replace([' ', '/', ',', '.', '-'], ['-', '-', '', '', '-'], $name));
    $urlKey = preg_replace('/-+/', '-', $urlKey);
    $enRows[] = [
        'sku' => $sku, 'name' => $name, 'description' => $desc,
        'short_description' => $desc,
        'url_key' => trim($urlKey, '-'),
        'meta_title' => $name,
        'meta_description' => $desc,
        'meta_keyword' => '',
    ];
}
$enAdded = appendUnique($i18nEn . '/products.csv', $enRows, 'sku');
echo "i18n EN added: {$enAdded}\n";

$nlRows = [];
foreach ($i18nNamesNl as $sku => [$name, $desc]) {
    $urlKey = strtolower(str_replace([' ', '/', ',', '.', '-', 'ä', 'ë', 'ï', 'ö', 'ü'], ['-', '-', '', '', '-', 'a', 'e', 'i', 'o', 'u'], $name));
    $urlKey = preg_replace('/-+/', '-', $urlKey);
    $urlKey = preg_replace('/[^a-z0-9-]/', '', $urlKey);
    $nlRows[] = [
        'sku' => $sku, 'name' => $name, 'description' => $desc,
        'short_description' => $desc,
        'url_key' => trim($urlKey, '-'),
        'meta_title' => $name,
        'meta_description' => $desc,
        'meta_keyword' => '',
    ];
}
$nlAdded = appendUnique($i18nNl . '/products.csv', $nlRows, 'sku');
echo "i18n NL added: {$nlAdded}\n";

echo "Done.\n";
