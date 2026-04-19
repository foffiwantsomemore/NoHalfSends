<?php

$userId = isset($_SESSION['userId']) ? (int) $_SESSION['userId'] : 0;
if ($userId <= 0) {
    header('Location: ../include/loginForm.php');
    exit;
}

$clubId = (int) ($_GET['id'] ?? 0);
if ($clubId <= 0) {
    header('Location: clubs.php');
    exit;
}

$pdo = DBHandler::getPDO();
$errorMessage = '';
$successMessage = '';

// Fetch club details
$sqlClub = "
    SELECT 
        c.clubid, 
        c.name, 
        c.description, 
        c.clubimage,
        c.sportid,
        s.name AS sport_name,
        uc.admin
    FROM Club c
    LEFT JOIN Sport s ON s.sportid = c.sportid
    LEFT JOIN UserClub uc ON uc.clubid = c.clubid AND uc.userid = :uid
    WHERE c.clubid = :cid
";

$stmtClub = $pdo->prepare($sqlClub);
$stmtClub->bindValue(':uid', $userId, PDO::PARAM_INT);
$stmtClub->bindValue(':cid', $clubId, PDO::PARAM_INT);
$stmtClub->execute();
$club = $stmtClub->fetch();

if (!$club) {
    header('Location: clubs.php');
    exit;
}

// Check if user is admin
if ((int) $club['admin'] !== 1) {
    header('Location: clubs.php');
    exit;
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'update_info') {
            $clubName = trim((string) ($_POST['club_name'] ?? ''));
            $clubDescription = trim((string) ($_POST['club_description'] ?? ''));

            if ($clubName === '') {
                throw new RuntimeException('Club name cannot be empty.');
            }

            $updateStmt = $pdo->prepare(
                'UPDATE Club SET name = :name, description = :description WHERE clubid = :cid'
            );
            $updateStmt->bindValue(':name', $clubName, PDO::PARAM_STR);
            $updateStmt->bindValue(':description', $clubDescription !== '' ? $clubDescription : null, PDO::PARAM_STR);
            $updateStmt->bindValue(':cid', $clubId, PDO::PARAM_INT);
            $updateStmt->execute();

            $successMessage = 'Club information updated successfully.';
            
            // Refresh club data
            $stmtClub->execute();
            $club = $stmtClub->fetch();
        }

        if ($action === 'upload_image') {
            if (!isset($_FILES['club_image']) || $_FILES['club_image']['error'] === UPLOAD_ERR_NO_FILE) {
                throw new RuntimeException('No image file selected.');
            }

            $uploadedFile = $_FILES['club_image'];
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            $maxFileSize = 5 * 1024 * 1024; // 5MB

            if ($uploadedFile['error'] !== UPLOAD_ERR_OK) {
                throw new RuntimeException('File upload error: ' . $uploadedFile['error']);
            }

            if ($uploadedFile['size'] > $maxFileSize) {
                throw new RuntimeException('File size exceeds 5MB limit.');
            }

            // Validate file type
            $fileInfo = getimagesize($uploadedFile['tmp_name']);
            if ($fileInfo === false) {
                throw new RuntimeException('Uploaded file is not a valid image.');
            }

            // Get file extension
            $originalExtension = pathinfo($uploadedFile['name'], PATHINFO_EXTENSION);
            $extension = strtolower($originalExtension);

            if (!in_array($extension, $allowedExtensions, true)) {
                throw new RuntimeException('File type not allowed. Allowed types: ' . implode(', ', $allowedExtensions));
            }

            // Generate unique filename with club ID
            $timestamp = time();
            $newFilename = "club_" . $clubId . "_" . $timestamp . "." . $extension;
            $uploadDir = __DIR__ . '/../images/clubs/';
            $uploadPath = $uploadDir . $newFilename;

            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            // Delete old image if exists
            if (!empty($club['clubimage'])) {
                $oldImagePath = __DIR__ . '/../' . $club['clubimage'];
                if (file_exists($oldImagePath)) {
                    unlink($oldImagePath);
                }
            }

            // Move uploaded file
            if (!move_uploaded_file($uploadedFile['tmp_name'], $uploadPath)) {
                throw new RuntimeException('Failed to save image file.');
            }

            // Update database
            $relativePath = 'images/clubs/' . $newFilename;
            $updateImageStmt = $pdo->prepare('UPDATE Club SET clubimage = :image WHERE clubid = :cid');
            $updateImageStmt->bindValue(':image', $relativePath, PDO::PARAM_STR);
            $updateImageStmt->bindValue(':cid', $clubId, PDO::PARAM_INT);
            $updateImageStmt->execute();

            $successMessage = 'Club image updated successfully.';
            
            // Refresh club data
            $stmtClub->execute();
            $club = $stmtClub->fetch();
        }

        if ($action === 'delete_image') {
            if (empty($club['clubimage'])) {
                throw new RuntimeException('No image to delete.');
            }

            $imagePath = __DIR__ . '/../' . $club['clubimage'];
            if (file_exists($imagePath)) {
                unlink($imagePath);
            }

            $updateImageStmt = $pdo->prepare('UPDATE Club SET clubimage = NULL WHERE clubid = :cid');
            $updateImageStmt->bindValue(':cid', $clubId, PDO::PARAM_INT);
            $updateImageStmt->execute();

            $successMessage = 'Club image deleted successfully.';
            
            // Refresh club data
            $stmtClub->execute();
            $club = $stmtClub->fetch();
        }
    } catch (Throwable $e) {
        $errorMessage = $e->getMessage();
    }
}

include __DIR__ . '/../include/menu/menuChoice.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Club - NHS</title>
    <link rel="stylesheet" href="../css/global.css">
    <link rel="stylesheet" href="../css/header-footer.css">
    <link rel="stylesheet" href="../css/clubs.css">
</head>
<body>
    <div class="club-edit-container">
        <div class="club-edit-header">
            <div class="club-edit-header-image">
                <?php if (!empty($club['clubimage'])): ?>
                    <img src="../<?php echo htmlspecialchars($club['clubimage'], ENT_QUOTES); ?>"
                        alt="<?php echo htmlspecialchars($club['name'], ENT_QUOTES); ?>">
                <?php else: ?>
                    <span style="font-size: 2.5rem; color: rgba(255, 255, 255, 0.3);">
                        <?php echo htmlspecialchars(substr($club['name'], 0, 1), ENT_QUOTES); ?>
                    </span>
                <?php endif; ?>
            </div>
            <div class="club-edit-header-text">
                <h1><?php echo htmlspecialchars($club['name'], ENT_QUOTES); ?></h1>
                <p><?php echo htmlspecialchars($club['sport_name'] ?? 'Unknown Sport', ENT_QUOTES); ?></p>
            </div>
        </div>

        <?php if (!empty($errorMessage)): ?>
            <div class="error-message"><?php echo htmlspecialchars($errorMessage, ENT_QUOTES); ?></div>
        <?php endif; ?>

        <?php if (!empty($successMessage)): ?>
            <div class="success-message"><?php echo htmlspecialchars($successMessage, ENT_QUOTES); ?></div>
        <?php endif; ?>

        <!-- Edit Club Information -->
        <section class="club-edit-section">
            <h2>Club Information</h2>
            <form method="post" action="clubEdit.php?id=<?php echo (int) $clubId; ?>">
                <input type="hidden" name="action" value="update_info">

                <div class="form-row">
                    <label for="club-name">Club Name</label>
                    <input type="text" id="club-name" name="club_name" maxlength="100"
                        value="<?php echo htmlspecialchars($club['name'], ENT_QUOTES); ?>" required>
                    <p class="help-text">Enter a clear, descriptive name for your club.</p>
                </div>

                <div class="form-row">
                    <label for="club-description">Description</label>
                    <textarea id="club-description" name="club_description" maxlength="1000"
                        placeholder="Tell members what your club is about..."><?php echo htmlspecialchars($club['description'] ?? '', ENT_QUOTES); ?></textarea>
                    <p class="help-text">Maximum 1000 characters</p>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn">Save Changes</button>
                    <a href="clubs.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </section>

        <!-- Edit Club Image -->
        <section class="club-edit-section">
            <h2>Club Image</h2>

            <?php if (!empty($club['clubimage'])): ?>
                <div class="image-preview">
                    <img src="../<?php echo htmlspecialchars($club['clubimage'], ENT_QUOTES); ?>"
                        alt="<?php echo htmlspecialchars($club['name'], ENT_QUOTES); ?>">
                </div>
            <?php endif; ?>

            <form method="post" action="clubEdit.php?id=<?php echo (int) $clubId; ?>" enctype="multipart/form-data">
                <input type="hidden" name="action" value="upload_image">

                <div class="form-row">
                    <label for="club-image">Upload New Image</label>
                    <input type="file" id="club-image" name="club_image" accept="image/*" required>
                    <p class="help-text">Supported formats: JPG, PNG, GIF, WebP. Max size: 5MB</p>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn">Upload Image</button>
                    <?php if (!empty($club['clubimage'])): ?>
                        <form method="post" action="clubEdit.php?id=<?php echo (int) $clubId; ?>" style="display: inline;">
                            <input type="hidden" name="action" value="delete_image">
                            <button type="submit" class="btn btn-danger" onclick="return confirm('Are you sure you want to delete the club image?');">Delete Image</button>
                        </form>
                    <?php endif; ?>
                </div>
            </form>
        </section>

        <div style="margin-top: 2rem; text-align: center;">
            <a href="clubs.php" class="btn btn-secondary">Back to Clubs</a>
        </div>
    </div>
</body>
</html>
