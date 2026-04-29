<?php
/**
 * Phase 3 expansion: 8 new product lines, each with its own studio shot
 * + two scene shots (the unique-imagery batch). Adds rows to
 * simple_products.csv plus EN/NL translations.
 *
 * Idempotent — re-running is a no-op (skips rows whose SKU already exists).
 *
 * Run from the sample-data-themes package root:
 *   php tools/expand-catalog-v3.php
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

// Each entry: [sku, attrset, category, price, qty, material, style, room, dim, weight,
//              studio.jpg, scene_a.jpg, scene_b.jpg, weight_capacity]
$products = [
    ['DRX-HL-PL-008', 'lighting', 'lighting/pendant-lights', 219.00, 35, 'metal', 'modern', 'dining,living', '35 cm diameter', 2.0, 'pendant-light-helsinki-001.jpg', 'pendant-light-helsinki-scene-dining-001.jpg', 'pendant-light-helsinki-scene-detail-002.jpg', ''],
    ['DRX-HL-FL-008', 'lighting', 'lighting/floor-lamps', 159.00, 50, 'metal', 'scandinavian', 'living,bedroom', '155 cm height', 4.5, 'floor-lamp-malmo-001.jpg', 'floor-lamp-malmo-scene-living-001.jpg', 'floor-lamp-malmo-scene-detail-002.jpg', ''],
    ['DRX-HL-TL-007', 'lighting', 'lighting/table-lamps', 119.00, 80, 'fabric', 'mid-century', 'living,bedroom', '50 cm height', 2.5, 'table-lamp-aarhus-001.jpg', 'table-lamp-aarhus-scene-bedroom-001.jpg', 'table-lamp-aarhus-scene-detail-002.jpg', ''],
    ['DRX-HL-DC-009', 'furniture', 'dining/dining-chairs', 169.00, 60, 'wood', 'scandinavian', 'dining', '45 x 50 x 85 cm', 5.5, 'dining-chair-bergen-001.jpg', 'dining-chair-bergen-scene-dining-001.jpg', 'dining-chair-bergen-scene-detail-002.jpg', 'Max. 130 kg'],
    ['DRX-HL-SOFA-003', 'furniture', 'living-room/sofas-couches', 1299.00, 10, 'fabric', 'modern', 'living', '200 x 90 x 85 cm', 65.0, 'sofa-stockholm-001.jpg', 'sofa-stockholm-scene-living-001.jpg', 'sofa-stockholm-scene-detail-002.jpg', ''],
    ['DRX-HL-CT-010', 'furniture', 'living-room/coffee-tables', 289.00, 30, 'metal', 'modern', 'living', '90 cm diameter, 38 cm tall', 12.0, 'coffee-table-aalborg-001.jpg', 'coffee-table-aalborg-scene-living-001.jpg', 'coffee-table-aalborg-scene-detail-002.jpg', ''],
    ['DRX-HL-NS-009', 'furniture', 'bedroom/nightstands', 169.00, 40, 'wood', 'scandinavian', 'bedroom', '50 x 40 x 60 cm', 8.5, 'nightstand-trondheim-001.jpg', 'nightstand-trondheim-scene-bedroom-001.jpg', 'nightstand-trondheim-scene-detail-002.jpg', ''],
    ['DRX-HL-VAS-009', 'decor', 'decor/vases', 54.95, 90, 'ceramic', 'scandinavian', 'living', '35 cm height', 1.4, 'vase-aarhus-001.jpg', 'vase-aarhus-scene-living-001.jpg', 'vase-aarhus-scene-detail-002.jpg', ''],
];

$rows = [];
foreach ($products as $p) {
    [$sku, $set, $cat, $price, $qty, $mat, $style, $room, $dim, $weight, $studio, $sa, $sb, $wcap] = $p;
    $rows[] = [
        'sku' => $sku, 'attribute_set' => $set,
        'price' => number_format($price, 2, '.', ''), 'qty' => (string) $qty,
        'visibility' => '4', 'status' => '1',
        'categories' => $cat,
        'material' => $mat, 'style' => $style, 'room' => $room,
        'dimensions' => $dim, 'weight_capacity' => $wcap,
        'weight' => number_format($weight, 1, '.', ''),
        'images' => "{$studio},{$sa},{$sb}",
    ];
}

$tEn = [
    'DRX-HL-PL-008' => ['Pendant Light Helsinki', 'Modern brass dome pendant light with brushed gold finish.'],
    'DRX-HL-FL-008' => ['Floor Lamp Malmo', 'Slim Scandinavian floor lamp with white linen shade.'],
    'DRX-HL-TL-007' => ['Table Lamp Aarhus', 'Mid-century walnut and beige fabric table lamp.'],
    'DRX-HL-DC-009' => ['Dining Chair Bergen', 'Scandinavian bentwood dining chair with boucle seat.'],
    'DRX-HL-SOFA-003' => ['Sofa Stockholm', 'Modern two-seat grey wool sofa with clean square arms.'],
    'DRX-HL-CT-010' => ['Coffee Table Aalborg', 'Modern minimalist round white coffee table.'],
    'DRX-HL-NS-009' => ['Nightstand Trondheim', 'Scandinavian ash wood nightstand with brass pulls.'],
    'DRX-HL-VAS-009' => ['Vase Aarhus', 'Tall sage green ceramic vase, minimalist Scandinavian.'],
];

$tNl = [
    'DRX-HL-PL-008' => ['Hanglamp Helsinki', 'Moderne hanglamp met geborsteld goud koepelvorm.'],
    'DRX-HL-FL-008' => ['Vloerlamp Malmo', 'Slanke Scandinavische vloerlamp met witte linnen kap.'],
    'DRX-HL-TL-007' => ['Tafellamp Aarhus', 'Mid-century tafellamp met walnoot voet en beige stoffen kap.'],
    'DRX-HL-DC-009' => ['Eetkamerstoel Bergen', 'Scandinavische gebogen eetkamerstoel met boucle zitting.'],
    'DRX-HL-SOFA-003' => ['Bank Stockholm', 'Moderne tweezitsbank in grijze wol met strakke armleuningen.'],
    'DRX-HL-CT-010' => ['Salontafel Aalborg', 'Moderne minimalistische ronde witte salontafel.'],
    'DRX-HL-NS-009' => ['Nachtkastje Trondheim', 'Scandinavisch nachtkastje van essenhout met messing handvatten.'],
    'DRX-HL-VAS-009' => ['Vaas Aarhus', 'Hoge salieengroene keramische vaas, minimalistisch Scandinavisch.'],
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
echo "Phase-3 simples added: {$added}\n";
$enAdded = appendUnique($i18nEn . '/products.csv', $enRows, 'sku');
echo "Phase-3 EN added: {$enAdded}\n";
$nlAdded = appendUnique($i18nNl . '/products.csv', $nlRows, 'sku');
echo "Phase-3 NL added: {$nlAdded}\n";
echo "Done.\n";
