<?php

$pdo = DBHandler::getPDO();

$userId = $_SESSION['userId'] ?? null;
if (!$userId) {
    header('Location: ../include/loginForm.php');
    exit;
}

// handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selected = isset($_POST['sports']) && is_array($_POST['sports'])
        ? array_map('intval', $_POST['sports'])
        : [];

    $pdo->beginTransaction();
    try {
        // remove all current sports for user
        $sqlDel = 'DELETE FROM SportUser WHERE userid = :uid';
        $sthDel = $pdo->prepare($sqlDel);
        $sthDel->bindValue(':uid', $userId, PDO::PARAM_INT);
        $sthDel->execute();

        // insert selected sports
        if (!empty($selected)) {
            $sqlIns = 'INSERT INTO SportUser (userid, sportid) VALUES (:uid, :sid)';
            $sthIns = $pdo->prepare($sqlIns);
            foreach ($selected as $sid) {
                $sthIns->bindValue(':uid', $userId, PDO::PARAM_INT);
                $sthIns->bindValue(':sid', $sid, PDO::PARAM_INT);
                $sthIns->execute();
            }
        }

        $pdo->commit();

        header('Location: profile.php');
        exit;
    } catch (PDOException $e) {
        $pdo->rollBack();
        // in caso di errore, continuiamo a mostrare la pagina senza redirect
    }
}

// load all sports
$sqlSports = 'SELECT sportid, name, sportimage FROM Sport ORDER BY name';
$sthSports = $pdo->prepare($sqlSports);
$sthSports->execute();
$sports = $sthSports->fetchAll();

// load user's current sports
$sqlUserSports = 'SELECT sportid FROM SportUser WHERE userid = :uid';
$sthUserSports = $pdo->prepare($sqlUserSports);
$sthUserSports->bindValue(':uid', $userId, PDO::PARAM_INT);
$sthUserSports->execute();
$userSportsRows = $sthUserSports->fetchAll();
$userSportIds = array_map(function($row) { return (int)$row['sportid']; }, $userSportsRows);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Sports - NHS</title>
    <link rel="stylesheet" href="../css/global.css">
    <link rel="stylesheet" href="../css/header-footer.css">
</head>
<body>

<?php include __DIR__ . '/../include/menu/menuChoice.php'; ?>

<div class="profile-page manage-sports-page">
    <section class="profile-section">
        <h2 class="section-title">Choose your sports</h2>
        <p class="manage-sports-hint">Select the sports you practice. They will appear in your profile.</p>

        <form action="manageSports.php" method="post" class="manage-sports-form">
            <div class="sport-select-grid">
                <?php foreach ($sports as $sport): ?>
                    <label class="sport-select-item">
                        <input type="checkbox" name="sports[]" value="<?php echo (int)$sport['sportid']; ?>"
                            <?php if (in_array((int)$sport['sportid'], $userSportIds, true)) echo 'checked'; ?>>
                        <?php if (!empty($sport['sportimage'])): ?>
                            <img src="../<?php echo htmlspecialchars($sport['sportimage'], ENT_QUOTES); ?>"
                                 alt="<?php echo htmlspecialchars($sport['name'], ENT_QUOTES); ?> icon">
                        <?php endif; ?>
                        <span class="sport-select-name"><?php echo htmlspecialchars($sport['name'], ENT_QUOTES); ?></span>
                    </label>
                <?php endforeach; ?>
            </div>

            <div class="manage-sports-actions">
                <button type="submit" class="btn">Save sports</button>
                <a href="profile.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </section>
</div>


</body>
</html>
