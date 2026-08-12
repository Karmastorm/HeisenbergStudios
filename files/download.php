<?php
/**
 * files/download.php?folder=analysis/research&file=somefile.pdf
 *
 * Streams a file to the browser only if:
 *  - the folder exists in file_folders
 *  - the user's access level meets the folder's requirement
 *  - the requested file actually lives inside that folder (no traversal)
 */
require_once __DIR__ . '/../includes/auth.php';
start_secure_session();

$pdo = get_db_connection();
$userLevel = $_SESSION['access_level'] ?? 0;

$folder = trim($_GET['folder'] ?? '', "/ \t\n\r\0\x0B");
$file   = $_GET['file'] ?? '';

// Reject any path traversal attempts outright
if (str_contains($folder, '..') || str_contains($file, '..') || str_contains($file, '/') || str_contains($file, '\\')) {
    http_response_code(400);
    exit('Invalid request.');
}

$stmt = $pdo->prepare('SELECT * FROM file_folders WHERE folder_path = :folder LIMIT 1');
$stmt->execute(['folder' => $folder]);
$folderRow = $stmt->fetch();

if (!$folderRow) {
    http_response_code(404);
    exit('Folder not found.');
}

if ($userLevel < (int)$folderRow['min_access_level']) {
    http_response_code(403);
    exit('Access denied.');
}

$fullPath = realpath(__DIR__ . '/' . $folderRow['folder_path'] . '/' . $file);
$folderRealPath = realpath(__DIR__ . '/' . $folderRow['folder_path']);

// Ensure resolved path is actually inside the allowed folder
if ($fullPath === false || $folderRealPath === false || !str_starts_with($fullPath, $folderRealPath)) {
    http_response_code(404);
    exit('File not found.');
}

if (!is_file($fullPath)) {
    http_response_code(404);
    exit('File not found.');
}

// Stream the file
$mime = mime_content_type($fullPath) ?: 'application/octet-stream';
header('Content-Type: ' . $mime);
header('Content-Disposition: attachment; filename="' . basename($fullPath) . '"');
header('Content-Length: ' . filesize($fullPath));
header('X-Content-Type-Options: nosniff');

readfile($fullPath);
exit;
