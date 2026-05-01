-- Phase-4 verification queries: confirm catalog meets the user's
-- "feels like a real shop" criteria.
--
-- Run with:
--   roll db connect -- < dev/sample-data-themes/tools/verify-coverage.sql

-- ----------------------------------------------------------------------
-- 1. Per-leaf VISIBLE product count. User requirement: every leaf >= 20.
-- ----------------------------------------------------------------------
SELECT '=== leaf coverage (each leaf needs >= 20) ===' AS section;

SELECT
    cce.entity_id,
    cev.value AS leaf_name,
    COUNT(DISTINCT cp.product_id) AS visible_products,
    CASE WHEN COUNT(DISTINCT cp.product_id) < 20 THEN 'BELOW' ELSE 'OK' END AS status
FROM catalog_category_entity cce
JOIN catalog_category_entity_varchar cev
    ON cev.entity_id = cce.entity_id
    AND cev.attribute_id = (SELECT attribute_id FROM eav_attribute
                            WHERE attribute_code = 'name' AND entity_type_id = 3)
    AND cev.store_id = 0
LEFT JOIN catalog_category_product cp
    ON cp.category_id = cce.entity_id
LEFT JOIN catalog_product_entity_int v
    ON v.entity_id = cp.product_id
    AND v.attribute_id = (SELECT attribute_id FROM eav_attribute WHERE attribute_code = 'visibility')
    AND v.value = 4
WHERE cce.level >= 3
GROUP BY cce.entity_id, cev.value
ORDER BY visible_products ASC, leaf_name;

-- ----------------------------------------------------------------------
-- 2. Per-filter-option product count. User requirement: every option >= 12.
-- ----------------------------------------------------------------------
SELECT '=== filter coverage (each option needs >= 12) ===' AS section;

SELECT
    a.attribute_code,
    ov.value AS option_label,
    COUNT(DISTINCT v.entity_id) AS products,
    CASE WHEN COUNT(DISTINCT v.entity_id) < 12 THEN 'BELOW' ELSE 'OK' END AS status
FROM eav_attribute a
JOIN eav_attribute_option o ON o.attribute_id = a.attribute_id
JOIN eav_attribute_option_value ov
    ON ov.option_id = o.option_id
    AND ov.store_id = 0
JOIN catalog_product_entity_int v
    ON v.attribute_id = a.attribute_id
    AND v.value = o.option_id
WHERE a.attribute_code IN ('color_family', 'material', 'fabric', 'style', 'room')
GROUP BY a.attribute_code, o.option_id, ov.value
ORDER BY products ASC, a.attribute_code, ov.value;

-- ----------------------------------------------------------------------
-- 3. Total catalog scale by product type.
-- ----------------------------------------------------------------------
SELECT '=== catalog scale ===' AS section;

SELECT type_id, COUNT(*) AS sku_count
FROM catalog_product_entity
WHERE sku LIKE 'DRX-HL-%'
GROUP BY type_id
ORDER BY type_id;

-- ----------------------------------------------------------------------
-- 4. Image coverage: every product should have at least one gallery row.
-- ----------------------------------------------------------------------
SELECT '=== image coverage ===' AS section;

SELECT
    e.type_id,
    COUNT(*) AS total,
    SUM(CASE WHEN g.imgs > 0 THEN 1 ELSE 0 END) AS with_images,
    ROUND(100.0 * SUM(CASE WHEN g.imgs > 0 THEN 1 ELSE 0 END) / COUNT(*), 1) AS pct
FROM catalog_product_entity e
LEFT JOIN (
    SELECT entity_id, COUNT(*) AS imgs
    FROM catalog_product_entity_media_gallery_value_to_entity
    GROUP BY entity_id
) g ON g.entity_id = e.entity_id
WHERE e.sku LIKE 'DRX-HL-%'
GROUP BY e.type_id
ORDER BY e.type_id;
