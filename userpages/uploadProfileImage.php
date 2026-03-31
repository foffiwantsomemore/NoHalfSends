<?php
session_start();

$pdo = DBHandler::getPDO();

$userId = $_SESSION['userId'] ?? null;

if (isset($_POST['remove_profile'])) {
    $sqlOld = "SELECT userimage FROM User WHERE userid = :uid";
    $sthOld = $pdo->prepare($sqlOld);
    $sthOld->bindValue(':uid', $userId, PDO::PARAM_INT);
    $sthOld->execute();
    $old = $sthOld->fetch();

    if ($old && !empty($old['userimage'])) {
        $oldPath = $old['userimage'];
        $basename = basename($oldPath);
        $absPath = __DIR__ . '/../images/users/' . $basename;
        if (is_file($absPath)) {
            @unlink($absPath);
        }
    }

    $sqlClear = "UPDATE User SET userimage = NULL WHERE userid = :uid";
    $sthClear = $pdo->prepare($sqlClear);
    $sthClear->bindValue(':uid', $userId, PDO::PARAM_INT);
    $sthClear->execute();

    header('Location: profile.php');
    exit;
}

if (!isset($_FILES['userimage']) || $_FILES['userimage']['error'] !== UPLOAD_ERR_OK) {
    header('Location: profile.php');
    exit;
}

$file = $_FILES['userimage'];

$allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
if (!in_array($file['type'], $allowedTypes)) {
    header('Location: profile.php');
    exit;
}

if ($file['size'] > 5 * 1024 * 1024) { //5MB
    header('Location: profile.php');
    exit;
}

$ext = pathinfo($file['name'], PATHINFO_EXTENSION);
$ext = strtolower($ext);

$sqlOld = "SELECT userimage FROM User WHERE userid = :uid";
$sthOld = $pdo->prepare($sqlOld);
$sthOld->bindValue(':uid', $userId, PDO::PARAM_INT);
$sthOld->execute();
$old = $sthOld->fetch();
$oldImage = $old['userimage'] ?? null;

$uploadDir = __DIR__ . '/../images/users/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

$filename = 'user_' . $userId . '_' . time() . '.' . $ext;
$destPath = $uploadDir . $filename;

if (!move_uploaded_file($file['tmp_name'], $destPath)) {
    header('Location: profile.php');
    exit;
}

$relativePath = '../images/users/' . $filename;

$sql = "UPDATE User SET userimage = :img WHERE userid = :uid";
$sth = $pdo->prepare($sql);
$sth->bindValue(':img', $relativePath, PDO::PARAM_STR);
$sth->bindValue(':uid', $userId, PDO::PARAM_INT);
$sth->execute();

if (!empty($oldImage)) {
    $basenameOld = basename($oldImage);
    $oldAbsPath = __DIR__ . '/../images/users/' . $basenameOld;
    if (is_file($oldAbsPath) && $basenameOld !== $filename) {
        @unlink($oldAbsPath);
    }
}

header('Location: profile.php');
exit;
