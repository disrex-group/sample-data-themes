<?php
/**
 * Phase 2 expansion: add ~10 more simple products per top-level category to
 * reach roughly 20 each. Reuses existing imagery — each new product
 * references one of the 24 existing studio shots plus matching scenes, so
 * no new image generation is required.
 *
 * Idempotent: re-running is a no-op (skips existing SKUs).
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$base = $root . '/packages/theme-home-living/_files/base';
$i18nEn = $root . '/packages/theme-home-living/_files/i18n/en_US';
$i18nNl = $root . '/packages/theme-home-living/_files/i18n/nl_NL';

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
// Phase 2 simple-product additions
// =============================================================================

$mk = function (
    string $sku, string $set, string $cat, float $price, int $qty,
    string $material, string $style, string $room, string $dim, float $weight,
    string $images, string $weight_capacity = ''
): array {
    return [
        'sku' => $sku, 'attribute_set' => $set,
        'price' => number_format($price, 2, '.', ''), 'qty' => (string) $qty,
        'visibility' => '4', 'status' => '1',
        'categories' => $cat,
        'material' => $material, 'style' => $style, 'room' => $room,
        'dimensions' => $dim, 'weight_capacity' => $weight_capacity,
        'weight' => number_format($weight, 1, '.', ''),
        'images' => $images,
    ];
};

// scene helpers
$ctOslo = ',coffee-table-oslo-scene-detail-002.jpg,coffee-table-oslo-scene-living-001.jpg';
$ctBergen = ',coffee-table-bergen-scene-detail-002.jpg,coffee-table-bergen-scene-living-001.jpg';
$ctTromso = ',coffee-table-tromso-scene-detail-002.jpg,coffee-table-tromso-scene-living-001.jpg';
$nsMalmo = ',nightstand-malmo-scene-bedroom-001.jpg,nightstand-malmo-scene-detail-002.jpg';
$nsLund = ',nightstand-lund-scene-bedroom-001.jpg,nightstand-lund-scene-detail-002.jpg';
$wrGote = ',wardrobe-goteborg-scene-bedroom-001.jpg,wardrobe-goteborg-scene-detail-002.jpg';
$bedSto = ',bed-stockholm-scene-bedroom-001.jpg,bed-stockholm-scene-detail-002.jpg';
$dtRey = ',dining-table-reykjavik-scene-detail-002.jpg,dining-table-reykjavik-scene-dining-001.jpg';
$dcHel = ',dining-chair-helsinki-scene-detail-002.jpg,dining-chair-helsinki-scene-dining-001.jpg';
$plEspoo = ',pendant-light-espoo-scene-detail-002.jpg,pendant-light-espoo-scene-dining-001.jpg';
$flStock = ',floor-lamp-stockholm-scene-detail-002.jpg,floor-lamp-stockholm-scene-living-001.jpg';
$flTrond = ',floor-lamp-trondheim-scene-detail-002.jpg,floor-lamp-trondheim-scene-living-001.jpg';
$tlBerg = ',table-lamp-bergen-scene-bedroom-001.jpg,table-lamp-bergen-scene-detail-002.jpg';
$vsCop = ',vase-copenhagen-scene-detail-002.jpg,vase-copenhagen-scene-living-001.jpg';
$vsTam = ',vase-tampere-scene-detail-002.jpg,vase-tampere-scene-living-001.jpg';
$mirAal = ',mirror-aalborg-scene-bedroom-002.jpg,mirror-aalborg-scene-hallway-001.jpg';
$waTrio = ',wall-art-trio-scene-detail-002.jpg,wall-art-trio-scene-living-001.jpg';
$olSkagen = ',outdoor-lamp-skagen-scene-detail-002.jpg,outdoor-lamp-skagen-scene-garden-001.jpg';
$ogScenes = ',outdoor-garden-scene-evening-002.jpg,outdoor-garden-scene-terrace-001.jpg';

$rows = [];

// LIVING ROOM (need ~10 more) — sub-cats: sofas-couches, coffee-tables, lounge-chairs
$rows[] = $mk('DRX-HL-CT-007', 'furniture', 'living-room/coffee-tables', 379.00, 22, 'wood', 'modern', 'living', '120 x 65 x 42 cm', 17.0, "coffee-table-oslo-001.jpg{$ctOslo}");
$rows[] = $mk('DRX-HL-CT-008', 'furniture', 'living-room/coffee-tables', 199.00, 50, 'glass', 'minimalist', 'living', '90 x 50 x 38 cm', 11.0, "coffee-table-tromso-001.jpg{$ctTromso}");
$rows[] = $mk('DRX-HL-CT-009', 'furniture', 'living-room/coffee-tables', 449.00, 18, 'wood', 'mid-century', 'living', '140 x 75 x 45 cm', 22.0, "coffee-table-bergen-001.jpg{$ctBergen}");
$rows[] = $mk('DRX-HL-LC-002', 'furniture', 'living-room/lounge-chairs', 379.00, 28, 'fabric', 'scandinavian', 'living', '75 x 80 x 90 cm', 16.0, "lounge-aarhus-linen-001.jpg,lounge-aarhus-scene-detail-002.jpg,lounge-aarhus-scene-reading-001.jpg");
$rows[] = $mk('DRX-HL-LC-003', 'furniture', 'living-room/lounge-chairs', 449.00, 22, 'fabric', 'mid-century', 'living', '78 x 82 x 92 cm', 17.5, "lounge-aarhus-velvet-001.jpg,lounge-aarhus-scene-detail-002.jpg,lounge-aarhus-scene-reading-001.jpg");
$rows[] = $mk('DRX-HL-LC-004', 'furniture', 'living-room/lounge-chairs', 539.00, 18, 'leather', 'modern', 'living', '80 x 85 x 95 cm', 19.0, "lounge-aarhus-leather-001.jpg,lounge-aarhus-scene-detail-002.jpg,lounge-aarhus-scene-reading-001.jpg");
$rows[] = $mk('DRX-HL-SOFA-001', 'furniture', 'living-room/sofas-couches', 1499.00, 8, 'fabric', 'modern', 'living', '230 x 95 x 85 cm', 78.0, "sofa-helsinki-grey-linen-001.jpg,sofa-helsinki-scene-detail-002.jpg,sofa-helsinki-scene-living-001.jpg");
$rows[] = $mk('DRX-HL-SOFA-002', 'furniture', 'living-room/sofas-couches', 1799.00, 6, 'fabric', 'mid-century', 'living', '250 x 100 x 88 cm', 85.0, "sofa-oslo-grey-001.jpg,sofa-oslo-scene-detail-002.jpg,sofa-oslo-scene-living-001.jpg");

// BEDROOM (need ~8 more)
$rows[] = $mk('DRX-HL-NS-006', 'furniture', 'bedroom/nightstands', 199.00, 35, 'wood', 'mid-century', 'bedroom', '55 x 50 x 65 cm', 9.5, "nightstand-malmo-001.jpg{$nsMalmo}");
$rows[] = $mk('DRX-HL-NS-007', 'furniture', 'bedroom/nightstands', 89.00, 70, 'wood', 'minimalist', 'bedroom', '40 x 35 x 50 cm', 5.5, "nightstand-lund-001.jpg{$nsLund}");
$rows[] = $mk('DRX-HL-WAR-004', 'furniture', 'bedroom/wardrobes', 1499.00, 5, 'wood', 'modern', 'bedroom', '240 x 65 x 240 cm', 110.0, "wardrobe-goteborg-001.jpg{$wrGote}");
$rows[] = $mk('DRX-HL-WAR-005', 'furniture', 'bedroom/wardrobes', 699.00, 12, 'wood', 'minimalist', 'bedroom', '160 x 55 x 200 cm', 65.0, "wardrobe-goteborg-001.jpg{$wrGote}");
$rows[] = $mk('DRX-HL-BED-004', 'furniture', 'bedroom/beds', 549.00, 20, 'wood', 'minimalist', 'bedroom', '140 x 200 cm', 48.0, "bed-stockholm-001.jpg{$bedSto}", 'Max. 180 kg');
$rows[] = $mk('DRX-HL-BED-005', 'furniture', 'bedroom/beds', 949.00, 8, 'wood', 'mid-century', 'bedroom', '180 x 200 cm', 70.0, "bed-stockholm-001.jpg{$bedSto}", 'Max. 240 kg');
$rows[] = $mk('DRX-HL-BED-006', 'furniture', 'bedroom/beds', 1199.00, 5, 'wood', 'modern', 'bedroom', '200 x 220 cm', 80.0, "bed-stockholm-001.jpg{$bedSto}", 'Max. 280 kg');
$rows[] = $mk('DRX-HL-NS-008', 'furniture', 'bedroom/nightstands', 219.00, 30, 'wood', 'modern', 'bedroom', '60 x 50 x 70 cm', 10.0, "nightstand-lund-001.jpg{$nsLund}");

// DINING (need ~10 more) — dining-tables, dining-chairs
$rows[] = $mk('DRX-HL-DT-004', 'furniture', 'dining/dining-tables', 649.00, 20, 'wood', 'minimalist', 'dining', '160 x 90 x 75 cm', 38.0, "dining-table-reykjavik-001.jpg{$dtRey}");
$rows[] = $mk('DRX-HL-DT-005', 'furniture', 'dining/dining-tables', 899.00, 14, 'wood', 'industrial', 'dining', '180 x 95 x 76 cm', 45.0, "dining-table-reykjavik-001.jpg{$dtRey}");
$rows[] = $mk('DRX-HL-DT-006', 'furniture', 'dining/dining-tables', 549.00, 25, 'wood', 'scandinavian', 'dining', '160 x 85 x 75 cm', 36.0, "dining-table-reykjavik-001.jpg{$dtRey}");
$rows[] = $mk('DRX-HL-DC-004', 'furniture', 'dining/dining-chairs', 79.00, 200, 'wood', 'minimalist', 'dining', '44 x 48 x 80 cm', 4.5, "dining-chair-helsinki-001.jpg{$dcHel}", 'Max. 110 kg');
$rows[] = $mk('DRX-HL-DC-005', 'furniture', 'dining/dining-chairs', 119.00, 120, 'wood', 'industrial', 'dining', '47 x 51 x 87 cm', 6.0, "dining-chair-helsinki-001.jpg{$dcHel}", 'Max. 130 kg');
$rows[] = $mk('DRX-HL-DC-006', 'furniture', 'dining/dining-chairs', 139.00, 90, 'fabric', 'modern', 'dining', '46 x 50 x 86 cm', 5.8, "dining-chair-helsinki-001.jpg{$dcHel}", 'Max. 130 kg');
$rows[] = $mk('DRX-HL-DC-007', 'furniture', 'dining/dining-chairs', 99.00, 150, 'wood', 'mid-century', 'dining', '45 x 49 x 84 cm', 5.2, "dining-chair-helsinki-001.jpg{$dcHel}", 'Max. 120 kg');
$rows[] = $mk('DRX-HL-DC-008', 'furniture', 'dining/dining-chairs', 159.00, 70, 'fabric', 'scandinavian', 'dining', '47 x 52 x 88 cm', 6.2, "dining-chair-helsinki-001.jpg{$dcHel}", 'Max. 130 kg');

// LIGHTING (need ~10 more)
$rows[] = ['sku' => 'DRX-HL-PL-004', 'attribute_set' => 'lighting', 'price' => '139.00', 'qty' => '60', 'visibility' => '4', 'status' => '1', 'categories' => 'lighting/pendant-lights', 'material' => 'metal', 'style' => 'minimalist', 'room' => 'living,dining', 'dimensions' => '30 cm diameter', 'weight_capacity' => '', 'weight' => '1.8', 'images' => "pendant-light-espoo-001.jpg{$plEspoo}"];
$rows[] = ['sku' => 'DRX-HL-PL-005', 'attribute_set' => 'lighting', 'price' => '299.00', 'qty' => '25', 'visibility' => '4', 'status' => '1', 'categories' => 'lighting/pendant-lights', 'material' => 'metal', 'style' => 'mid-century', 'room' => 'living,dining', 'dimensions' => '60 cm diameter', 'weight_capacity' => '', 'weight' => '4.0', 'images' => "pendant-light-espoo-001.jpg{$plEspoo}"];
$rows[] = ['sku' => 'DRX-HL-PL-006', 'attribute_set' => 'lighting', 'price' => '199.00', 'qty' => '40', 'visibility' => '4', 'status' => '1', 'categories' => 'lighting/pendant-lights', 'material' => 'glass', 'style' => 'scandinavian', 'room' => 'dining', 'dimensions' => '40 cm diameter', 'weight_capacity' => '', 'weight' => '2.5', 'images' => "pendant-light-espoo-001.jpg{$plEspoo}"];
$rows[] = ['sku' => 'DRX-HL-FL-005', 'attribute_set' => 'lighting', 'price' => '129.00', 'qty' => '50', 'visibility' => '4', 'status' => '1', 'categories' => 'lighting/floor-lamps', 'material' => 'metal', 'style' => 'minimalist', 'room' => 'living,bedroom', 'dimensions' => '150 cm height', 'weight_capacity' => '', 'weight' => '4.0', 'images' => "floor-lamp-stockholm-001.jpg{$flStock}"];
$rows[] = ['sku' => 'DRX-HL-FL-006', 'attribute_set' => 'lighting', 'price' => '249.00', 'qty' => '30', 'visibility' => '4', 'status' => '1', 'categories' => 'lighting/floor-lamps', 'material' => 'metal', 'style' => 'industrial', 'room' => 'living', 'dimensions' => '180 cm height', 'weight_capacity' => '', 'weight' => '6.5', 'images' => "floor-lamp-trondheim-001.jpg{$flTrond}"];
$rows[] = ['sku' => 'DRX-HL-TL-004', 'attribute_set' => 'lighting', 'price' => '109.00', 'qty' => '70', 'visibility' => '4', 'status' => '1', 'categories' => 'lighting/table-lamps', 'material' => 'ceramic', 'style' => 'mid-century', 'room' => 'living,bedroom', 'dimensions' => '42 cm height', 'weight_capacity' => '', 'weight' => '2.3', 'images' => "table-lamp-bergen-001.jpg{$tlBerg}"];
$rows[] = ['sku' => 'DRX-HL-TL-005', 'attribute_set' => 'lighting', 'price' => '59.00', 'qty' => '120', 'visibility' => '4', 'status' => '1', 'categories' => 'lighting/table-lamps', 'material' => 'metal', 'style' => 'minimalist', 'room' => 'living,bedroom', 'dimensions' => '32 cm height', 'weight_capacity' => '', 'weight' => '1.5', 'images' => "table-lamp-bergen-001.jpg{$tlBerg}"];
$rows[] = ['sku' => 'DRX-HL-TL-006', 'attribute_set' => 'lighting', 'price' => '79.00', 'qty' => '90', 'visibility' => '4', 'status' => '1', 'categories' => 'lighting/table-lamps', 'material' => 'ceramic', 'style' => 'modern', 'room' => 'living,bedroom', 'dimensions' => '36 cm height', 'weight_capacity' => '', 'weight' => '1.9', 'images' => "table-lamp-bergen-001.jpg{$tlBerg}"];
$rows[] = ['sku' => 'DRX-HL-PL-007', 'attribute_set' => 'lighting', 'price' => '249.00', 'qty' => '30', 'visibility' => '4', 'status' => '1', 'categories' => 'lighting/pendant-lights', 'material' => 'metal', 'style' => 'industrial', 'room' => 'dining,living', 'dimensions' => '45 cm diameter', 'weight_capacity' => '', 'weight' => '3.5', 'images' => "pendant-light-espoo-001.jpg{$plEspoo}"];
$rows[] = ['sku' => 'DRX-HL-FL-007', 'attribute_set' => 'lighting', 'price' => '189.00', 'qty' => '40', 'visibility' => '4', 'status' => '1', 'categories' => 'lighting/floor-lamps', 'material' => 'metal', 'style' => 'modern', 'room' => 'living,bedroom', 'dimensions' => '160 cm height', 'weight_capacity' => '', 'weight' => '5.0', 'images' => "floor-lamp-stockholm-001.jpg{$flStock}"];

// DECOR (need ~9 more)
$rows[] = ['sku' => 'DRX-HL-VAS-005', 'attribute_set' => 'decor', 'price' => '24.95', 'qty' => '250', 'visibility' => '4', 'status' => '1', 'categories' => 'decor/vases', 'material' => 'ceramic', 'style' => 'minimalist', 'room' => 'living', 'dimensions' => '20 cm height', 'weight_capacity' => '', 'weight' => '0.7', 'images' => "vase-copenhagen-001.jpg{$vsCop}"];
$rows[] = ['sku' => 'DRX-HL-VAS-006', 'attribute_set' => 'decor', 'price' => '59.95', 'qty' => '120', 'visibility' => '4', 'status' => '1', 'categories' => 'decor/vases', 'material' => 'glass', 'style' => 'mid-century', 'room' => 'living,dining', 'dimensions' => '40 cm height', 'weight_capacity' => '', 'weight' => '1.8', 'images' => "vase-tampere-001.jpg{$vsTam}"];
$rows[] = ['sku' => 'DRX-HL-VAS-007', 'attribute_set' => 'decor', 'price' => '19.95', 'qty' => '300', 'visibility' => '4', 'status' => '1', 'categories' => 'decor/vases', 'material' => 'ceramic', 'style' => 'scandinavian', 'room' => 'living', 'dimensions' => '15 cm height', 'weight_capacity' => '', 'weight' => '0.5', 'images' => "vase-copenhagen-001.jpg{$vsCop}"];
$rows[] = ['sku' => 'DRX-HL-MIR-004', 'attribute_set' => 'decor', 'price' => '79.00', 'qty' => '60', 'visibility' => '4', 'status' => '1', 'categories' => 'decor/mirrors', 'material' => 'metal', 'style' => 'minimalist', 'room' => 'bedroom,living', 'dimensions' => '40 x 60 cm', 'weight_capacity' => '', 'weight' => '2.5', 'images' => "mirror-aalborg-001.jpg{$mirAal}"];
$rows[] = ['sku' => 'DRX-HL-MIR-005', 'attribute_set' => 'decor', 'price' => '249.00', 'qty' => '20', 'visibility' => '4', 'status' => '1', 'categories' => 'decor/mirrors', 'material' => 'metal', 'style' => 'industrial', 'room' => 'living,bedroom', 'dimensions' => '80 x 120 cm', 'weight_capacity' => '', 'weight' => '6.5', 'images' => "mirror-aalborg-001.jpg{$mirAal}"];
$rows[] = ['sku' => 'DRX-HL-WA-004', 'attribute_set' => 'decor', 'price' => '69.00', 'qty' => '60', 'visibility' => '4', 'status' => '1', 'categories' => 'decor/wall-art', 'material' => 'wood', 'style' => 'minimalist', 'room' => 'living,bedroom', 'dimensions' => '30 x 40 cm', 'weight_capacity' => '', 'weight' => '0.9', 'images' => "wall-art-trio-001.jpg{$waTrio}"];
$rows[] = ['sku' => 'DRX-HL-WA-005', 'attribute_set' => 'decor', 'price' => '149.00', 'qty' => '25', 'visibility' => '4', 'status' => '1', 'categories' => 'decor/wall-art', 'material' => 'wood', 'style' => 'industrial', 'room' => 'living,dining', 'dimensions' => '60 x 80 cm', 'weight_capacity' => '', 'weight' => '2.2', 'images' => "wall-art-trio-001.jpg{$waTrio}"];
$rows[] = ['sku' => 'DRX-HL-WA-006', 'attribute_set' => 'decor', 'price' => '109.00', 'qty' => '40', 'visibility' => '4', 'status' => '1', 'categories' => 'decor/wall-art', 'material' => 'wood', 'style' => 'scandinavian', 'room' => 'living', 'dimensions' => '50 x 70 cm', 'weight_capacity' => '', 'weight' => '1.5', 'images' => "wall-art-trio-001.jpg{$waTrio}"];
$rows[] = ['sku' => 'DRX-HL-VAS-008', 'attribute_set' => 'decor', 'price' => '44.95', 'qty' => '150', 'visibility' => '4', 'status' => '1', 'categories' => 'decor/vases', 'material' => 'ceramic', 'style' => 'modern', 'room' => 'living', 'dimensions' => '32 cm height', 'weight_capacity' => '', 'weight' => '1.3', 'images' => "vase-tampere-001.jpg{$vsTam}"];

// OUTDOOR (need ~12 more) — outdoor-lighting, garden-furniture
$rows[] = ['sku' => 'DRX-HL-OL-005', 'attribute_set' => 'lighting', 'price' => '99.00', 'qty' => '50', 'visibility' => '4', 'status' => '1', 'categories' => 'outdoor/outdoor-lighting', 'material' => 'metal', 'style' => 'minimalist', 'room' => 'outdoor', 'dimensions' => '140 cm height', 'weight_capacity' => '', 'weight' => '3.5', 'images' => "outdoor-lamp-skagen-001.jpg{$olSkagen}"];
$rows[] = ['sku' => 'DRX-HL-OL-006', 'attribute_set' => 'lighting', 'price' => '169.00', 'qty' => '35', 'visibility' => '4', 'status' => '1', 'categories' => 'outdoor/outdoor-lighting', 'material' => 'metal', 'style' => 'mid-century', 'room' => 'outdoor', 'dimensions' => '170 cm height', 'weight_capacity' => '', 'weight' => '5.0', 'images' => "outdoor-lamp-skagen-001.jpg{$olSkagen}"];
$rows[] = ['sku' => 'DRX-HL-OL-007', 'attribute_set' => 'lighting', 'price' => '199.00', 'qty' => '25', 'visibility' => '4', 'status' => '1', 'categories' => 'outdoor/outdoor-lighting', 'material' => 'metal', 'style' => 'industrial', 'room' => 'outdoor', 'dimensions' => '210 cm height', 'weight_capacity' => '', 'weight' => '7.0', 'images' => "outdoor-lamp-skagen-001.jpg{$olSkagen}"];
$rows[] = ['sku' => 'DRX-HL-OG-004', 'attribute_set' => 'furniture', 'price' => '249.00', 'qty' => '30', 'visibility' => '4', 'status' => '1', 'categories' => 'outdoor/garden-furniture', 'material' => 'metal', 'style' => 'minimalist', 'room' => 'outdoor', 'dimensions' => '50 x 50 x 75 cm', 'weight_capacity' => 'Max. 100 kg', 'weight' => '5.5', 'images' => "outdoor-garden-grey-001.jpg{$ogScenes}"];
$rows[] = ['sku' => 'DRX-HL-OG-005', 'attribute_set' => 'furniture', 'price' => '449.00', 'qty' => '20', 'visibility' => '4', 'status' => '1', 'categories' => 'outdoor/garden-furniture', 'material' => 'metal', 'style' => 'mid-century', 'room' => 'outdoor', 'dimensions' => '120 x 60 x 75 cm', 'weight_capacity' => 'Max. 200 kg', 'weight' => '12.0', 'images' => "outdoor-garden-black-001.jpg{$ogScenes}"];
$rows[] = ['sku' => 'DRX-HL-OG-006', 'attribute_set' => 'furniture', 'price' => '149.00', 'qty' => '50', 'visibility' => '4', 'status' => '1', 'categories' => 'outdoor/garden-furniture', 'material' => 'metal', 'style' => 'modern', 'room' => 'outdoor', 'dimensions' => '40 x 40 x 70 cm', 'weight_capacity' => 'Max. 100 kg', 'weight' => '4.0', 'images' => "outdoor-garden-grey-001.jpg{$ogScenes}"];
$rows[] = ['sku' => 'DRX-HL-OG-007', 'attribute_set' => 'furniture', 'price' => '599.00', 'qty' => '15', 'visibility' => '4', 'status' => '1', 'categories' => 'outdoor/garden-furniture', 'material' => 'metal', 'style' => 'industrial', 'room' => 'outdoor', 'dimensions' => '160 x 80 x 75 cm', 'weight_capacity' => '', 'weight' => '18.0', 'images' => "outdoor-garden-black-001.jpg{$ogScenes}"];
$rows[] = ['sku' => 'DRX-HL-OG-008', 'attribute_set' => 'furniture', 'price' => '329.00', 'qty' => '25', 'visibility' => '4', 'status' => '1', 'categories' => 'outdoor/garden-furniture', 'material' => 'metal', 'style' => 'scandinavian', 'room' => 'outdoor', 'dimensions' => '90 x 60 x 75 cm', 'weight_capacity' => 'Max. 150 kg', 'weight' => '9.0', 'images' => "outdoor-garden-grey-001.jpg{$ogScenes}"];
$rows[] = ['sku' => 'DRX-HL-OL-008', 'attribute_set' => 'lighting', 'price' => '49.00', 'qty' => '100', 'visibility' => '4', 'status' => '1', 'categories' => 'outdoor/outdoor-lighting', 'material' => 'metal', 'style' => 'minimalist', 'room' => 'outdoor', 'dimensions' => '90 cm height', 'weight_capacity' => '', 'weight' => '2.0', 'images' => "outdoor-lamp-skagen-001.jpg{$olSkagen}"];

// Translations
$tEn = [
    'DRX-HL-CT-007' => ['Coffee Table Oslo - Modern Wide', 'Modern Scandinavian coffee table.'],
    'DRX-HL-CT-008' => ['Coffee Table Tromso - Minimalist', 'Minimalist glass coffee table.'],
    'DRX-HL-CT-009' => ['Coffee Table Bergen - Grand', 'Grand mid-century coffee table.'],
    'DRX-HL-LC-002' => ['Lounge Chair Aarhus - Cosy', 'Cosy Scandinavian lounge chair.'],
    'DRX-HL-LC-003' => ['Lounge Chair Aarhus - Mid-Century', 'Mid-century lounge chair.'],
    'DRX-HL-LC-004' => ['Lounge Chair Aarhus - Leather Premium', 'Premium leather lounge chair.'],
    'DRX-HL-SOFA-001' => ['Sofa Three Seater - Modern', 'Modern three-seat sofa.'],
    'DRX-HL-SOFA-002' => ['Sofa Curved - Mid-Century', 'Mid-century curved sofa.'],
    'DRX-HL-NS-006' => ['Nightstand Malmo - Wide', 'Wide mid-century nightstand.'],
    'DRX-HL-NS-007' => ['Nightstand Lund - Compact', 'Compact minimalist nightstand.'],
    'DRX-HL-NS-008' => ['Nightstand Lund - Modern Tall', 'Modern tall nightstand.'],
    'DRX-HL-WAR-004' => ['Wardrobe Goteborg - XL Modern', 'XL modern wardrobe.'],
    'DRX-HL-WAR-005' => ['Wardrobe Goteborg - Compact', 'Compact minimalist wardrobe.'],
    'DRX-HL-BED-004' => ['Bed Stockholm - Single', 'Single Scandinavian bed.'],
    'DRX-HL-BED-005' => ['Bed Stockholm - Mid-Century Queen', 'Queen mid-century bed.'],
    'DRX-HL-BED-006' => ['Bed Stockholm - King XL', 'King XL modern bed.'],
    'DRX-HL-DT-004' => ['Dining Table Reykjavik - Compact', 'Compact minimalist dining table.'],
    'DRX-HL-DT-005' => ['Dining Table Reykjavik - Industrial', 'Industrial dining table.'],
    'DRX-HL-DT-006' => ['Dining Table Reykjavik - Standard', 'Standard Scandinavian dining table.'],
    'DRX-HL-DC-004' => ['Dining Chair Helsinki - Compact', 'Compact minimalist dining chair.'],
    'DRX-HL-DC-005' => ['Dining Chair Helsinki - Industrial', 'Industrial dining chair.'],
    'DRX-HL-DC-006' => ['Dining Chair Helsinki - Upholstered', 'Modern upholstered dining chair.'],
    'DRX-HL-DC-007' => ['Dining Chair Helsinki - Mid-Century Slim', 'Mid-century slim dining chair.'],
    'DRX-HL-DC-008' => ['Dining Chair Helsinki - Cosy Linen', 'Scandinavian cosy linen dining chair.'],
    'DRX-HL-PL-004' => ['Pendant Light Espoo - Compact Minimalist', 'Compact minimalist pendant light.'],
    'DRX-HL-PL-005' => ['Pendant Light Espoo - Grand Mid-Century', 'Grand mid-century pendant light.'],
    'DRX-HL-PL-006' => ['Pendant Light Espoo - Glass Scandinavian', 'Scandinavian glass pendant light.'],
    'DRX-HL-PL-007' => ['Pendant Light Espoo - Industrial Wide', 'Industrial wide pendant light.'],
    'DRX-HL-FL-005' => ['Floor Lamp Stockholm - Minimalist', 'Minimalist floor lamp.'],
    'DRX-HL-FL-006' => ['Floor Lamp Trondheim - Industrial XL', 'Industrial XL floor lamp.'],
    'DRX-HL-FL-007' => ['Floor Lamp Stockholm - Modern Tall', 'Modern tall floor lamp.'],
    'DRX-HL-TL-004' => ['Table Lamp Bergen - Mid-Century', 'Mid-century ceramic table lamp.'],
    'DRX-HL-TL-005' => ['Table Lamp Bergen - Compact', 'Compact minimalist table lamp.'],
    'DRX-HL-TL-006' => ['Table Lamp Bergen - Modern Ceramic', 'Modern ceramic table lamp.'],
    'DRX-HL-VAS-005' => ['Vase Copenhagen - Mini', 'Mini minimalist ceramic vase.'],
    'DRX-HL-VAS-006' => ['Vase Tampere - Mid-Century XL', 'Mid-century XL glass vase.'],
    'DRX-HL-VAS-007' => ['Vase Copenhagen - Petite', 'Petite Scandinavian ceramic vase.'],
    'DRX-HL-VAS-008' => ['Vase Tampere - Modern', 'Modern glass vase.'],
    'DRX-HL-MIR-004' => ['Mirror Aalborg - Compact', 'Compact minimalist mirror.'],
    'DRX-HL-MIR-005' => ['Mirror Aalborg - Statement', 'Statement industrial mirror.'],
    'DRX-HL-WA-004' => ['Wall Art Trio - Minimalist', 'Minimalist wall art trio.'],
    'DRX-HL-WA-005' => ['Wall Art Trio - Industrial', 'Industrial wall art trio.'],
    'DRX-HL-WA-006' => ['Wall Art Trio - Scandinavian', 'Scandinavian wall art trio.'],
    'DRX-HL-OL-005' => ['Outdoor Lamp Skagen - Minimalist', 'Minimalist outdoor lamp.'],
    'DRX-HL-OL-006' => ['Outdoor Lamp Skagen - Mid-Century', 'Mid-century outdoor lamp.'],
    'DRX-HL-OL-007' => ['Outdoor Lamp Skagen - Industrial XL', 'Industrial XL outdoor lamp.'],
    'DRX-HL-OL-008' => ['Outdoor Lamp Skagen - Compact Path', 'Compact path outdoor lamp.'],
    'DRX-HL-OG-004' => ['Garden Side Table Minimalist', 'Minimalist outdoor side table.'],
    'DRX-HL-OG-005' => ['Garden Bench Mid-Century', 'Mid-century outdoor bench.'],
    'DRX-HL-OG-006' => ['Garden Stool Modern', 'Modern outdoor stool.'],
    'DRX-HL-OG-007' => ['Garden Bench Industrial', 'Industrial outdoor bench.'],
    'DRX-HL-OG-008' => ['Garden Side Table Scandinavian', 'Scandinavian outdoor side table.'],
];

$tNl = [
    'DRX-HL-CT-007' => ['Salontafel Oslo - Modern Breed', 'Moderne Scandinavische salontafel.'],
    'DRX-HL-CT-008' => ['Salontafel Tromso - Minimalistisch', 'Minimalistische glazen salontafel.'],
    'DRX-HL-CT-009' => ['Salontafel Bergen - Grand', 'Grote mid-century salontafel.'],
    'DRX-HL-LC-002' => ['Loungestoel Aarhus - Knus', 'Knusse Scandinavische loungestoel.'],
    'DRX-HL-LC-003' => ['Loungestoel Aarhus - Mid-Century', 'Mid-century loungestoel.'],
    'DRX-HL-LC-004' => ['Loungestoel Aarhus - Leer Premium', 'Premium lederen loungestoel.'],
    'DRX-HL-SOFA-001' => ['Bank Driezitter - Modern', 'Moderne driezitsbank.'],
    'DRX-HL-SOFA-002' => ['Bank Gebogen - Mid-Century', 'Mid-century gebogen bank.'],
    'DRX-HL-NS-006' => ['Nachtkastje Malmo - Breed', 'Breed mid-century nachtkastje.'],
    'DRX-HL-NS-007' => ['Nachtkastje Lund - Compact', 'Compact minimalistisch nachtkastje.'],
    'DRX-HL-NS-008' => ['Nachtkastje Lund - Modern Hoog', 'Modern hoog nachtkastje.'],
    'DRX-HL-WAR-004' => ['Kledingkast Goteborg - XL Modern', 'XL moderne kledingkast.'],
    'DRX-HL-WAR-005' => ['Kledingkast Goteborg - Compact', 'Compacte minimalistische kledingkast.'],
    'DRX-HL-BED-004' => ['Bed Stockholm - Eenpersoons', 'Eenpersoons Scandinavisch bed.'],
    'DRX-HL-BED-005' => ['Bed Stockholm - Mid-Century Queen', 'Queen mid-century bed.'],
    'DRX-HL-BED-006' => ['Bed Stockholm - King XL', 'King XL modern bed.'],
    'DRX-HL-DT-004' => ['Eettafel Reykjavik - Compact', 'Compacte minimalistische eettafel.'],
    'DRX-HL-DT-005' => ['Eettafel Reykjavik - Industrieel', 'Industriële eettafel.'],
    'DRX-HL-DT-006' => ['Eettafel Reykjavik - Standaard', 'Standaard Scandinavische eettafel.'],
    'DRX-HL-DC-004' => ['Eetkamerstoel Helsinki - Compact', 'Compacte minimalistische eetkamerstoel.'],
    'DRX-HL-DC-005' => ['Eetkamerstoel Helsinki - Industrieel', 'Industriële eetkamerstoel.'],
    'DRX-HL-DC-006' => ['Eetkamerstoel Helsinki - Bekleed', 'Moderne beklede eetkamerstoel.'],
    'DRX-HL-DC-007' => ['Eetkamerstoel Helsinki - Mid-Century Slank', 'Mid-century slanke eetkamerstoel.'],
    'DRX-HL-DC-008' => ['Eetkamerstoel Helsinki - Knus Linnen', 'Scandinavische knus linnen eetkamerstoel.'],
    'DRX-HL-PL-004' => ['Hanglamp Espoo - Compact Minimalistisch', 'Compacte minimalistische hanglamp.'],
    'DRX-HL-PL-005' => ['Hanglamp Espoo - Grand Mid-Century', 'Grote mid-century hanglamp.'],
    'DRX-HL-PL-006' => ['Hanglamp Espoo - Glas Scandinavisch', 'Scandinavische glazen hanglamp.'],
    'DRX-HL-PL-007' => ['Hanglamp Espoo - Industrieel Breed', 'Industriële brede hanglamp.'],
    'DRX-HL-FL-005' => ['Vloerlamp Stockholm - Minimalistisch', 'Minimalistische vloerlamp.'],
    'DRX-HL-FL-006' => ['Vloerlamp Trondheim - Industrieel XL', 'Industriële XL vloerlamp.'],
    'DRX-HL-FL-007' => ['Vloerlamp Stockholm - Modern Hoog', 'Moderne hoge vloerlamp.'],
    'DRX-HL-TL-004' => ['Tafellamp Bergen - Mid-Century', 'Mid-century keramische tafellamp.'],
    'DRX-HL-TL-005' => ['Tafellamp Bergen - Compact', 'Compacte minimalistische tafellamp.'],
    'DRX-HL-TL-006' => ['Tafellamp Bergen - Modern Keramisch', 'Moderne keramische tafellamp.'],
    'DRX-HL-VAS-005' => ['Vaas Copenhagen - Mini', 'Mini minimalistische keramische vaas.'],
    'DRX-HL-VAS-006' => ['Vaas Tampere - Mid-Century XL', 'Mid-century XL glazen vaas.'],
    'DRX-HL-VAS-007' => ['Vaas Copenhagen - Klein', 'Kleine Scandinavische keramische vaas.'],
    'DRX-HL-VAS-008' => ['Vaas Tampere - Modern', 'Moderne glazen vaas.'],
    'DRX-HL-MIR-004' => ['Spiegel Aalborg - Compact', 'Compacte minimalistische spiegel.'],
    'DRX-HL-MIR-005' => ['Spiegel Aalborg - Statement', 'Industriële statement spiegel.'],
    'DRX-HL-WA-004' => ['Wandkunst Trio - Minimalistisch', 'Minimalistische wandkunst trio.'],
    'DRX-HL-WA-005' => ['Wandkunst Trio - Industrieel', 'Industriële wandkunst trio.'],
    'DRX-HL-WA-006' => ['Wandkunst Trio - Scandinavisch', 'Scandinavische wandkunst trio.'],
    'DRX-HL-OL-005' => ['Buitenlamp Skagen - Minimalistisch', 'Minimalistische buitenlamp.'],
    'DRX-HL-OL-006' => ['Buitenlamp Skagen - Mid-Century', 'Mid-century buitenlamp.'],
    'DRX-HL-OL-007' => ['Buitenlamp Skagen - Industrieel XL', 'Industriële XL buitenlamp.'],
    'DRX-HL-OL-008' => ['Buitenlamp Skagen - Compact Pad', 'Compact pad buitenlamp.'],
    'DRX-HL-OG-004' => ['Tuinbijzettafel Minimalistisch', 'Minimalistische bijzettafel voor buiten.'],
    'DRX-HL-OG-005' => ['Tuinbank Mid-Century', 'Mid-century tuinbank.'],
    'DRX-HL-OG-006' => ['Tuinkruk Modern', 'Moderne tuinkruk.'],
    'DRX-HL-OG-007' => ['Tuinbank Industrieel', 'Industriële tuinbank.'],
    'DRX-HL-OG-008' => ['Tuinbijzettafel Scandinavisch', 'Scandinavische bijzettafel voor buiten.'],
];

$mkUrlKey = function (string $name): string {
    $slug = strtolower($name);
    $slug = strtr($slug, ['ä'=>'a','ë'=>'e','ï'=>'i','ö'=>'o','ü'=>'u']);
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
    return trim($slug, '-');
};

$enRows = [];
foreach ($tEn as $sku => [$name, $desc]) {
    $enRows[] = [
        'sku' => $sku, 'name' => $name, 'description' => $desc,
        'short_description' => $desc, 'url_key' => $mkUrlKey($name),
        'meta_title' => $name, 'meta_description' => $desc, 'meta_keyword' => '',
    ];
}

$nlRows = [];
foreach ($tNl as $sku => [$name, $desc]) {
    $nlRows[] = [
        'sku' => $sku, 'name' => $name, 'description' => $desc,
        'short_description' => $desc, 'url_key' => $mkUrlKey($name),
        'meta_title' => $name, 'meta_description' => $desc, 'meta_keyword' => '',
    ];
}

$added = appendUnique($base . '/simple_products.csv', $rows, 'sku');
echo "Phase-2 simples added: {$added}\n";
$enAdded = appendUnique($i18nEn . '/products.csv', $enRows, 'sku');
echo "Phase-2 EN added: {$enAdded}\n";
$nlAdded = appendUnique($i18nNl . '/products.csv', $nlRows, 'sku');
echo "Phase-2 NL added: {$nlAdded}\n";
echo "Done.\n";
