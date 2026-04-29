<?php
/**
 * One-shot helper that walks the home-living CSVs and appends matching
 * scene image filenames to each product's `images` column. The scene
 * directory (`packages/theme-home-living-media/_files/scenes/`) carries
 * files named `<stem>-scene-<context>-<N>.jpg`; this script groups them by
 * stem and rewrites each row's images list to include studio + every scene
 * that shares the row's primary stem.
 *
 * Idempotent: if a row already references a scene file, we don't add it
 * again. Run from the repo root:
 *
 *   php dev/sample-data-themes/tools/append-scene-images.php
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$mediaScenes = $root . '/packages/theme-home-living-media/_files/scenes';
$baseDir = $root . '/packages/theme-home-living/_files/base';

if (!is_dir($mediaScenes)) {
    fwrite(STDERR, "Scene directory missing: {$mediaScenes}\n");
    exit(1);
}

$scenesByStem = [];
foreach (glob($mediaScenes . '/*.jpg') as $path) {
    $name = basename($path);
    if (preg_match('/^(.+?)-scene-/', $name, $m)) {
        $scenesByStem[$m[1]][] = $name;
    }
}
foreach ($scenesByStem as &$list) {
    sort($list);
}
unset($list);

$rewriteCsv = static function (string $path, int $imagesColIndex) use ($scenesByStem): void {
    if (!is_readable($path)) {
        echo "  skip: {$path} (not readable)\n";
        return;
    }
    $rows = array_map('str_getcsv', file($path));
    if (count($rows) < 2) {
        return;
    }
    $header = $rows[0];
    $touched = 0;
    for ($i = 1; $i < count($rows); $i++) {
        $row = $rows[$i];
        if (!isset($row[$imagesColIndex])) {
            continue;
        }
        $current = trim($row[$imagesColIndex]);
        if ($current === '') {
            continue;
        }
        $primary = explode(',', $current)[0];
        $primary = trim($primary);
        if (!preg_match('/^(.+?)-\d+\.[a-z]+$/i', $primary, $m)) {
            continue;
        }
        // Variants name files like `sofa-helsinki-beige-boucle-001.jpg`
        // but scenes are keyed at the product-line level
        // (`sofa-helsinki`). Walk backwards through hyphenated segments
        // until we find a stem that matches a scene group.
        $stem = $m[1];
        while ($stem !== '' && empty($scenesByStem[$stem])) {
            $cut = strrpos($stem, '-');
            if ($cut === false) {
                $stem = '';
                break;
            }
            $stem = substr($stem, 0, $cut);
        }
        if ($stem === '' || empty($scenesByStem[$stem])) {
            continue;
        }
        $existing = array_map('trim', explode(',', $current));
        $merged = $existing;
        foreach ($scenesByStem[$stem] as $scene) {
            if (!in_array($scene, $merged, true)) {
                $merged[] = $scene;
            }
        }
        if ($merged !== $existing) {
            $rows[$i][$imagesColIndex] = implode(',', $merged);
            $touched++;
        }
    }
    if ($touched === 0) {
        echo "  unchanged: " . basename($path) . "\n";
        return;
    }
    $fp = fopen($path, 'w');
    foreach ($rows as $row) {
        fputcsv($fp, $row);
    }
    fclose($fp);
    echo "  updated: " . basename($path) . " ({$touched} rows)\n";
};

$jobs = [
    $baseDir . '/simple_products.csv' => null,
    $baseDir . '/configurable_variations.csv' => null,
];

foreach ($jobs as $path => $_) {
    if (!is_readable($path)) {
        echo "Missing: {$path}\n";
        continue;
    }
    $header = str_getcsv(fgets(fopen($path, 'r')));
    $idx = array_search('images', $header, true);
    if ($idx === false) {
        echo "  no 'images' column in " . basename($path) . "; skip\n";
        continue;
    }
    echo "Processing " . basename($path) . " (images at column {$idx}):\n";
    $rewriteCsv($path, $idx);
}

echo "Done. Stems with scenes: " . count($scenesByStem) . "\n";
