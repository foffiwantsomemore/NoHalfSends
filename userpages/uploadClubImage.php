<?php
session_start();
require_once __DIR__ . '/../include/dbHandler.php';

header('Content-Type: application/json');

// Check if user is authenticated
$userId = isset($_SESSION['userId']) ? (int) $_SESSION['userId'] : 0;
if ($userId <= 0) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

// Get club ID
$clubId = (int) ($_POST['club_id'] ?? 0);
if ($clubId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid club ID']);
    exit;
}

$pdo = DBHandler::getPDO();

// Check if user is admin of the club
$checkAdmin = $pdo->prepare(
    'SELECT admin FROM UserClub WHERE userid = :uid AND clubid = :cid'
);
$checkAdmin->bindValue(':uid', $userId, PDO::PARAM_INT);
$checkAdmin->bindValue(':cid', $clubId, PDO::PARAM_INT);
$checkAdmin->execute();
$membership = $checkAdmin->fetch();

if (!$membership || (int) $membership['admin'] !== 1) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Not authorized to modify this club']);
    exit;
}

// Handle file upload
if (!isset($_FILES['image']) || $_FILES['image']['error'] === UPLOAD_ERR_NO_FILE) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'No image file provided']);
    exit;
}

$file = $_FILES['image'];
$allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
$maxFileSize = 5 * 1024 * 1024; // 5MB

// Validate file
if ($file['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'File upload error: ' . $file['error']]);
    exit;
}

if ($file['size'] > $maxFileSize) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'File size exceeds 5MB limit']);
    exit;
}

// Validate image
$fileInfo = getimagesize($file['tmp_name']);
if ($fileInfo === false) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'File is not a valid image']);
    exit;
}

$extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if (!in_array($extension, $allowedExtensions, true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'File type not allowed']);
    exit;
}

try {
    // Get current image path
    $getClub = $pdo->prepare('SELECT clubimage FROM Club WHERE clubid = :cid');
    $getClub->bindValue(':cid', $clubId, PDO::PARAM_INT);
    $getClub->execute();
    $club = $getClub->fetch();

    if (!$club) {
        throw new Exception('Club not found');
    }

    // Generate filename
    $timestamp = time();
    $newFilename = "club_" . $clubId . "_" . $timestamp . "." . $extension;
    $uploadDir = __DIR__ . '/../images/clubs/';
    $uploadPath = $uploadDir . $newFilename;

    // Create directory if needed
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    // Delete old image if exists
    if (!empty($club['clubimage'])) {
        $oldImagePath = __DIR__ . '/../' . $club['clubimage'];
        if (file_exists($oldImagePath)) {
            @unlink($oldImagePath);
        }
    }

    // Move file
    if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
        throw new Exception('Failed to save image');
    }

    // Update database
    $relativePath = 'images/clubs/' . $newFilename;
    $updateImage = $pdo->prepare('UPDATE Club SET clubimage = :image WHERE clubid = :cid');
    $updateImage->bindValue(':image', $relativePath, PDO::PARAM_STR);
    $updateImage->bindValue(':cid', $clubId, PDO::PARAM_INT);
    $updateImage->execute();

    echo json_encode([
        'success' => true,
        'message' => 'Image uploaded successfully',
        'image_path' => '../' . $relativePath
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
