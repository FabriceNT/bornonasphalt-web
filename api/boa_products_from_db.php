<?php

defined('BOA_SIZE_ORDER') || define('BOA_SIZE_ORDER', ['S','M','L','XL','2XL','3XL','4XL']);

function boa_products_from_db(): array
{
    $db = boa_db();

    $products = [];
    $stmt = $db->query('SELECT * FROM products ORDER BY id');
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $productId = $row['id'];

        $colors = [];
        $sizes = [];
        $images = [];
        $image = null;

        $imgStmt = $db->prepare('SELECT color, image_url, is_primary FROM product_images WHERE product_id = ?');
        $imgStmt->execute([$productId]);
        while ($imgRow = $imgStmt->fetch(PDO::FETCH_ASSOC)) {
            $images[$imgRow['color']] = $imgRow['image_url'];
            if ($imgRow['is_primary']) {
                $image = $imgRow['image_url'];
            }
        }

        $colorsSeen = [];
        $variantRows = [];
        $varStmt = $db->prepare('SELECT color, size, printful_sync_variant_id, printify_variant_id FROM product_variants WHERE product_id = ? ORDER BY size');
        $varStmt->execute([$productId]);
        while ($varRow = $varStmt->fetch(PDO::FETCH_ASSOC)) {
            if (!in_array($varRow['color'], $colorsSeen, true)) {
                $colorsSeen[] = $varRow['color'];
            }
            $sizes[$varRow['size']] = true;
            $variantRows[] = [
                'color'                   => $varRow['color'],
                'size'                    => $varRow['size'],
                'printful_sync_variant_id' => (int) $varRow['printful_sync_variant_id'],
                'printify_variant_id'     => $varRow['printify_variant_id'] === null
                               ? null
                               : (int) $varRow['printify_variant_id'],
            ];
        }
        $colors = $colorsSeen;

        usort($variantRows, function (array $a, array $b): int {
            $aPos = array_search($a['size'], BOA_SIZE_ORDER, true);
            $bPos = array_search($b['size'], BOA_SIZE_ORDER, true);
            $aPos = $aPos === false ? PHP_INT_MAX : $aPos;
            $bPos = $bPos === false ? PHP_INT_MAX : $bPos;
            if ($aPos !== $bPos) {
                return $aPos <=> $bPos;
            }
            return strcmp($a['color'], $b['color']);
        });
        $sizes = array_keys($sizes);
        usort($sizes, function (string $a, string $b): int {
            $aPos = array_search($a, BOA_SIZE_ORDER, true);
            $bPos = array_search($b, BOA_SIZE_ORDER, true);
            $aPos = $aPos === false ? PHP_INT_MAX : $aPos;
            $bPos = $bPos === false ? PHP_INT_MAX : $bPos;
            return $aPos <=> $bPos;
        });

        $products[] = [
            'id'                       => $row['id'],
            'title'                    => $row['title'],
            'series'                   => $row['series'],
            'tribe'                    => $row['tribe'],
            'tribeLabel'               => $row['tribe_label'],
            'slogan'                   => $row['slogan'],
            'sub'                      => $row['sub'],
            'desc'                     => $row['description'],
            'price'                    => (float) $row['price'],
            'swatch'                   => $row['swatch'],
            'printful_sync_product_id' => (int) $row['printful_sync_product_id'],
            'printify_product_id'      => $row['printify_product_id'],
            'comingSoon'               => (bool) $row['coming_soon'],
            'available'                => (bool) $row['available'],
            'colors'                   => $colors,
            'sizes'                    => $sizes,
            'images'                   => $images,
            'image'                    => $image,
            'variants'                 => $variantRows,
        ];
    }

    return $products;
}

function boa_find_product_from_db(string $id): ?array
{
    $db = boa_db();
    $stmt = $db->prepare('SELECT * FROM products WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row === false) {
        return null;
    }

    $productId = $row['id'];

    $colors = [];
    $sizes = [];
    $images = [];
    $image = null;

    $imgStmt = $db->prepare('SELECT color, image_url, is_primary FROM product_images WHERE product_id = ?');
    $imgStmt->execute([$productId]);
    while ($imgRow = $imgStmt->fetch(PDO::FETCH_ASSOC)) {
        $images[$imgRow['color']] = $imgRow['image_url'];
        if ($imgRow['is_primary']) {
            $image = $imgRow['image_url'];
        }
    }

    $colorsSeen = [];
    $variantRows = [];
    $varStmt = $db->prepare('SELECT color, size, printful_sync_variant_id, printify_variant_id FROM product_variants WHERE product_id = ? ORDER BY size');
    $varStmt->execute([$productId]);
    while ($varRow = $varStmt->fetch(PDO::FETCH_ASSOC)) {
        if (!in_array($varRow['color'], $colorsSeen, true)) {
            $colorsSeen[] = $varRow['color'];
        }
        $sizes[$varRow['size']] = true;
        $variantRows[] = [
            'color'                   => $varRow['color'],
            'size'                    => $varRow['size'],
            'printful_sync_variant_id' => (int) $varRow['printful_sync_variant_id'],
            'printify_variant_id'     => $varRow['printify_variant_id'] === null
                               ? null
                               : (int) $varRow['printify_variant_id'],
        ];
    }
    $colors = $colorsSeen;
    usort($variantRows, function (array $a, array $b): int {
        $aPos = array_search($a['size'], BOA_SIZE_ORDER, true);
        $bPos = array_search($b['size'], BOA_SIZE_ORDER, true);
        $aPos = $aPos === false ? PHP_INT_MAX : $aPos;
        $bPos = $bPos === false ? PHP_INT_MAX : $bPos;
        if ($aPos !== $bPos) {
            return $aPos <=> $bPos;
        }
        return strcmp($a['color'], $b['color']);
    });
    $sizes = array_keys($sizes);
    usort($sizes, function (string $a, string $b): int {
        $aPos = array_search($a, BOA_SIZE_ORDER, true);
        $bPos = array_search($b, BOA_SIZE_ORDER, true);
        $aPos = $aPos === false ? PHP_INT_MAX : $aPos;
        $bPos = $bPos === false ? PHP_INT_MAX : $bPos;
        return $aPos <=> $bPos;
    });

    return [
        'id'                       => $row['id'],
        'title'                    => $row['title'],
        'series'                   => $row['series'],
        'tribe'                    => $row['tribe'],
        'tribeLabel'               => $row['tribe_label'],
        'slogan'                   => $row['slogan'],
        'sub'                      => $row['sub'],
        'desc'                     => $row['description'],
        'price'                    => (float) $row['price'],
        'swatch'                   => $row['swatch'],
        'printful_sync_product_id' => (int) $row['printful_sync_product_id'],
        'printify_product_id'      => $row['printify_product_id'],
        'comingSoon'               => (bool) $row['coming_soon'],
        'available'                => (bool) $row['available'],
        'colors'                   => $colors,
        'sizes'                    => $sizes,
        'images'                   => $images,
        'image'                    => $image,
        'variants'                 => $variantRows,
    ];
}

function boa_find_variant_from_db(string $productId, string $color, string $size): ?array
{
    $db = boa_db();
    $stmt = $db->prepare(
        'SELECT color, size, printful_sync_variant_id, printify_variant_id ' .
        'FROM product_variants WHERE product_id = ? AND color = ? AND size = ?'
    );
    $stmt->execute([$productId, $color, $size]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row === false) {
        return null;
    }
    return [
        'color'                   => $row['color'],
        'size'                    => $row['size'],
        'printful_sync_variant_id' => $row['printful_sync_variant_id'],
        'printify_variant_id'     => $row['printify_variant_id'],
    ];
}