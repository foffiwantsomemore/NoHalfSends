<?php
$userId = isset($_SESSION['userId']) ? (int) $_SESSION['userId'] : 0;
if ($userId <= 0) {
    http_response_code(403);
    exit('Unauthorized');
}

$pdo = DBHandler::getPDO();
$action = $_POST['action'] ?? '';

// --- LIKE / UNLIKE ---
if ($action === 'like' || $action === 'unlike') {
    $activityId = (int) ($_POST['activity_id'] ?? 0);
    if ($activityId <= 0)
        exit('Invalid');

    if ($action === 'like') {
        $stmt = $pdo->prepare('INSERT IGNORE INTO ActivityLike (userid, activityid) VALUES (?, ?)');
        $stmt->execute([$userId, $activityId]);
    } else {
        $stmt = $pdo->prepare('DELETE FROM ActivityLike WHERE userid = ? AND activityid = ?');
        $stmt->execute([$userId, $activityId]);
    }

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM ActivityLike WHERE activityid = ?');
    $stmt->execute([$activityId]);
    echo $stmt->fetchColumn();
    exit;
}

// --- COMMENT ---
if ($action === 'comment') {
    $activityId = (int) ($_POST['activity_id'] ?? 0);
    $text = trim($_POST['text'] ?? '');
    if ($activityId <= 0 || $text === '')
        exit('Invalid');

    $stmt = $pdo->prepare('INSERT INTO ActivityComment (userid, activityid, text, commentdate) VALUES (?, ?, ?, NOW())');
    $stmt->execute([$userId, $activityId, $text]);

    // Return rendered comment HTML
    $stmt = $pdo->prepare('SELECT u.username, u.name, u.surname, u.userimage FROM User u WHERE u.userid = ?');
    $stmt->execute([$userId]);
    $u = $stmt->fetch();
    $avatar = !empty($u['userimage']) ? htmlspecialchars($u['userimage']) : '../media/default-user.png';
    $name = htmlspecialchars($u['name'] . ' ' . $u['surname']);
    $username = htmlspecialchars($u['username']);
    $safeText = nl2br(htmlspecialchars($text));

    echo <<<HTML
<div class="comment-item">
    <div class="comment-header">
        <img src="{$avatar}" alt="Avatar" class="comment-avatar">
        <strong>{$name}</strong>
        <span>@{$username} · just now</span>
    </div>
    <p class="comment-text">{$safeText}</p>
</div>
HTML;
    exit;
}

// --- UPLOAD PHOTO ---
if ($action === 'upload_photo') {
    $activityId = (int) ($_POST['activity_id'] ?? 0);
    if ($activityId <= 0)
        exit('Invalid');

    // Verify user owns activity
    $stmt = $pdo->prepare('SELECT userid FROM Activity WHERE activityid = ?');
    $stmt->execute([$activityId]);
    $owner = $stmt->fetchColumn();
    if ((int) $owner !== $userId) {
        http_response_code(403);
        exit('Not your activity');
    }

    if (!isset($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
        http_response_code(400);
        exit('Upload error');
    }

    $file = $_FILES['photo'];
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (!in_array($file['type'], $allowedTypes)) {
        http_response_code(400);
        exit('Invalid type');
    }
    if ($file['size'] > 10 * 1024 * 1024) {
        http_response_code(400);
        exit('File too large');
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $uploadDir = __DIR__ . '/../images/activities/';
    if (!is_dir($uploadDir))
        mkdir($uploadDir, 0777, true);

    // Add random bytes so repeated uploads in the same second cannot collide.
    $filename = 'act_' . $activityId . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $destPath = $uploadDir . $filename;

    if (!move_uploaded_file($file['tmp_name'], $destPath)) {
        http_response_code(500);
        exit('Move failed');
    }

    $relativePath = '../images/activities/' . $filename;
    $stmt = $pdo->prepare('INSERT INTO ActivityPhoto (activityid, url) VALUES (?, ?)');
    $stmt->execute([$activityId, $relativePath]);

    $safe = htmlspecialchars($relativePath);
    echo "<img src=\"{$safe}\" class=\"activity-photo\" alt=\"Activity photo\">";
    exit;
}
