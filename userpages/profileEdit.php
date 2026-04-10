<?php

$pdo = DBHandler::getPDO();

$userId = $_SESSION['userId'] ?? null;
if (!$userId) {
    header('Location: ../include/loginForm.php');
    exit;
}

$errorMessage = '';
$successMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $surname = trim($_POST['surname'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $description = trim($_POST['description'] ?? '');

    try {
        $sqlUpdate = "
            UPDATE User
            SET name = :name,
                surname = :surname,
                username = :username,
                email = :email,
                description = :description
            WHERE userid = :uid
        ";
        $sthUpdate = $pdo->prepare($sqlUpdate);
        $sthUpdate->bindValue(':name', $name, PDO::PARAM_STR);
        $sthUpdate->bindValue(':surname', $surname, PDO::PARAM_STR);
        $sthUpdate->bindValue(':username', $username, PDO::PARAM_STR);
        $sthUpdate->bindValue(':email', $email, PDO::PARAM_STR);
        $sthUpdate->bindValue(':description', $description, PDO::PARAM_STR);
        $sthUpdate->bindValue(':uid', $userId, PDO::PARAM_INT);
        $sthUpdate->execute();

        $successMessage = 'Profile updated successfully.';
    } catch (PDOException $e) {
        $errorMessage = 'Error updating profile information.';
    }
}

$sqlUser = "
    SELECT userid, name, surname, username, description, userimage, email, registrationdate
    FROM v_user_profile
    WHERE userid = :uid
";
$sthUser = $pdo->prepare($sqlUser);
$sthUser->bindValue(':uid', $userId, PDO::PARAM_INT);
$sthUser->execute();
$user = $sthUser->fetch();

if (!$user) {
    die('User not found.');
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Profile - NHS</title>
    <link rel="stylesheet" href="../css/global.css">
    <link rel="stylesheet" href="../css/header-footer.css">
</head>
<body>

<?php include __DIR__ . '/../include/menu/menuChoice.php'; ?>

<div class="profile-page">
    <section class="profile-section">
        <h2 class="section-title">Edit profile</h2>

        <?php if ($errorMessage): ?>
            <div class="error-message"><?php echo htmlspecialchars($errorMessage, ENT_QUOTES); ?></div>
        <?php endif; ?>

        <?php if ($successMessage): ?>
            <div class="success-message"><?php echo htmlspecialchars($successMessage, ENT_QUOTES); ?></div>
        <?php endif; ?>

        <div class="profile-edit-container">
            <div class="profile-image-section">
                <div class="profile-avatar">
                    <?php if (!empty($user['userimage'])): ?>
                        <img src="<?php echo htmlspecialchars($user['userimage'], ENT_QUOTES); ?>" alt="Profile picture">
                    <?php else: ?>
                        <img src="../media/default-user.png" alt="Default profile picture">
                    <?php endif; ?>
                </div>

                <div class="profile-main-info">
                    <h3><?php echo htmlspecialchars($user['name'] . ' ' . $user['surname'], ENT_QUOTES); ?></h3>
                    <?php if (!empty($user['username'])): ?>
                        <div class="profile-username">@<?php echo htmlspecialchars($user['username'], ENT_QUOTES); ?></div>
                    <?php endif; ?>
                </div>

                <div class="profile-image-actions">
                    <form action="uploadProfileImage.php" method="post" enctype="multipart/form-data" class="profile-image-form">
                        <label for="userimage">Cambia immagine profilo:</label>
                        <input type="file" name="userimage" id="userimage" accept="image/*" required>
                        <button type="submit" class="btn">Carica</button>
                    </form>

                    <?php if (!empty($user['userimage'])): ?>
                        <form action="uploadProfileImage.php" method="post" class="profile-image-form profile-image-remove-form">
                            <input type="hidden" name="remove_profile" value="1">
                            <button type="submit" class="btn">Rimuovi immagine profilo</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>

            <!--dati profilo base-->
            <form action="profileEdit.php" method="post" class="profile-edit-form">
            <div class="form-row">
                <label for="name">Nome</label>
                <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($user['name'] ?? '', ENT_QUOTES); ?>" required>
            </div>

            <div class="form-row">
                <label for="surname">Cognome</label>
                <input type="text" id="surname" name="surname" value="<?php echo htmlspecialchars($user['surname'] ?? '', ENT_QUOTES); ?>" required>
            </div>

            <div class="form-row">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($user['username'] ?? '', ENT_QUOTES); ?>" required>
            </div>

            <div class="form-row">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['email'] ?? '', ENT_QUOTES); ?>" required>
            </div>

            <div class="form-row">
                <label for="description">Descrizione</label>
                <textarea id="description" name="description" rows="4"><?php echo htmlspecialchars($user['description'] ?? '', ENT_QUOTES); ?></textarea>
            </div>

            <button type="submit" class="btn">Salva modifiche</button>
            </form>
        </div>

        <p style="margin-top: 1rem;">
            <a href="profile.php" class="btn">Torna al profilo</a>
        </p>
    </section>
</div>

</body>
</html>
