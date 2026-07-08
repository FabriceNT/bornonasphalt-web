<?php
// POST /api/reviews-upload.php
// Upload d'une photo pour une review (appelé avant reviews-submit.php).
// Accepte un fichier multipart/form-data, champ "photo".
// Redimensionne à max 1200px côté serveur via GD.
// Retourne { "path": "/uploads/reviews/abc123.jpg" }
//
// Sécurité :
// - Types acceptés : JPEG, PNG, WebP uniquement (vérifiés via getimagesize, pas juste l'extension)
// - Taille max : 8 MB par fichier
// - Nom de fichier aléatoire (uniqid + hash) — jamais le nom original
// - Le dossier uploads/reviews/ contient un .htaccess qui bloque l'exécution PHP

require_once __DIR__ . '/config.php';

boa_send_cors_headers();
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }

header('Content-Type: application/json');

const MAX_FILE_SIZE  = 8 * 1024 * 1024; // 8 MB
const MAX_DIMENSION  = 1200;             // px, côté le plus long
const ALLOWED_TYPES  = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP];
const UPLOAD_DIR_REL = '/uploads/reviews/';

$uploadDir = dirname(__DIR__) . UPLOAD_DIR_REL;

// Créer le dossier si nécessaire
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
    // Sécurité : bloquer l'exécution PHP dans ce dossier
    file_put_contents($uploadDir . '.htaccess',
        "Options -ExecCGI\nAddHandler cgi-script .php .pl .py .jsp .asp .sh\n" .
        "php_flag engine off\n"
    );
}

if (empty($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
    $errCode = $_FILES['photo']['error'] ?? 'no file';
    http_response_code(400);
    echo json_encode(['error' => "Upload error: {$errCode}"]);
    exit;
}

$file = $_FILES['photo'];

// Taille
if ($file['size'] > MAX_FILE_SIZE) {
    http_response_code(422);
    echo json_encode(['error' => 'File too large. Max 8 MB.']);
    exit;
}

// Type réel (pas juste l'extension)
$imgInfo = @getimagesize($file['tmp_name']);
if (!$imgInfo || !in_array($imgInfo[2], ALLOWED_TYPES, true)) {
    http_response_code(422);
    echo json_encode(['error' => 'Invalid file type. JPEG, PNG or WebP only.']);
    exit;
}

$imgType = $imgInfo[2];

// Charger l'image source
$src = match ($imgType) {
    IMAGETYPE_JPEG => imagecreatefromjpeg($file['tmp_name']),
    IMAGETYPE_PNG  => imagecreatefrompng($file['tmp_name']),
    IMAGETYPE_WEBP => imagecreatefromwebp($file['tmp_name']),
    default        => null,
};

if (!$src) {
    http_response_code(500);
    echo json_encode(['error' => 'Could not process image.']);
    exit;
}

// Redimensionner si nécessaire
[$srcW, $srcH] = [$imgInfo[0], $imgInfo[1]];
$ratio = $srcW / $srcH;

if ($srcW > MAX_DIMENSION || $srcH > MAX_DIMENSION) {
    if ($srcW >= $srcH) {
        $dstW = MAX_DIMENSION;
        $dstH = (int)round(MAX_DIMENSION / $ratio);
    } else {
        $dstH = MAX_DIMENSION;
        $dstW = (int)round(MAX_DIMENSION * $ratio);
    }
    $dst = imagecreatetruecolor($dstW, $dstH);

    // Préserver la transparence pour PNG/WebP
    if ($imgType === IMAGETYPE_PNG || $imgType === IMAGETYPE_WEBP) {
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
        imagefilledrectangle($dst, 0, 0, $dstW, $dstH, $transparent);
    }

    imagecopyresampled($dst, $src, 0, 0, 0, 0, $dstW, $dstH, $srcW, $srcH);
    imagedestroy($src);
    $src = $dst;
} else {
    $dstW = $srcW;
    $dstH = $srcH;
}

// Nom de fichier aléatoire
$ext      = match ($imgType) {
    IMAGETYPE_PNG  => 'png',
    IMAGETYPE_WEBP => 'webp',
    default        => 'jpg',
};
$filename = bin2hex(random_bytes(12)) . '.' . $ext;
$destPath = $uploadDir . $filename;

// Sauvegarder
$saved = match ($imgType) {
    IMAGETYPE_PNG  => imagepng($src, $destPath, 6),   // compression 6/9
    IMAGETYPE_WEBP => imagewebp($src, $destPath, 82), // qualité 82
    default        => imagejpeg($src, $destPath, 85), // qualité 85
};
imagedestroy($src);

if (!$saved) {
    http_response_code(500);
    echo json_encode(['error' => 'Could not save image.']);
    exit;
}

echo json_encode(['path' => UPLOAD_DIR_REL . $filename]);
