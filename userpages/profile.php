<?php
require_once __DIR__ . '/../include/dbHandler.php';
require_once __DIR__ . '/../include/loggedIn.php';

$pdo = DBHandler::getPDO();

$userId = $_SESSION['userId'] ?? null;
if (!$userId) {
    header('Location: ../include/loginForm.php');
    exit;
}

/* --- Load user basic info (from view) --- */
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

/* --- Load sports practiced by user (from view) --- */
$sqlSports = "
    SELECT sportid, sport_name AS name, sportimage
    FROM v_user_sports
    WHERE userid = :uid
    ORDER BY sport_name
";
$sthSports = $pdo->prepare($sqlSports);
$sthSports->bindValue(':uid', $userId, PDO::PARAM_INT);
$sthSports->execute();
$userSports = $sthSports->fetchAll();

/* --- Weekly summary (last 7 days) --- */
$sqlWeekly = "
    SELECT
        COUNT(*) AS activities,
        COALESCE(ROUND(SUM(duration) / 60, 1), 0) AS hours
    FROM Activity
    WHERE userid = :uid
      AND activitydate >= DATE_SUB(NOW(), INTERVAL 7 DAY)
";
$sthWeekly = $pdo->prepare($sqlWeekly);
$sthWeekly->bindValue(':uid', $userId, PDO::PARAM_INT);
$sthWeekly->execute();
$weekly = $sthWeekly->fetch() ?: ['activities' => 0, 'hours' => 0];

/* --- Recent activities (from view) --- */
$sqlRecent = "
    SELECT activityid,
           activity_name,
           sport_name,
           activitydate,
           duration
    FROM v_user_activities
    WHERE userid = :uid
    ORDER BY activitydate DESC
    LIMIT 5
";
$sthRecent = $pdo->prepare($sqlRecent);
$sthRecent->bindValue(':uid', $userId, PDO::PARAM_INT);
$sthRecent->execute();
$recentActivities = $sthRecent->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Your Profile - NHS</title>
    <link rel="stylesheet" href="../css/global.css">
    <link rel="stylesheet" href="../css/header-footer.css">
</head>
<body>

<div class="profile-page">

    <!-- Account section -->
    <section class="profile-section">
        <div class="profile-header">
            <div class="profile-avatar">
                <?php if (!empty($user['userimage'])): ?>
                    <img src="<?php echo htmlspecialchars($user['userimage'], ENT_QUOTES); ?>" alt="Profile picture">
                <?php else: ?>
                    <img src="../media/default-user.svg" alt="Default profile picture">
                <?php endif; ?>
            </div>

            <div class="profile-main-info">
                <h2><?php echo htmlspecialchars($user['name'] . ' ' . $user['surname'], ENT_QUOTES); ?></h2>
                <?php if (!empty($user['username'])): ?>
                    <div class="profile-username">@<?php echo htmlspecialchars($user['username'], ENT_QUOTES); ?></div>
                <?php endif; ?>
                <div class="profile-meta">
                    Joined on
                    <?php
                    if (!empty($user['registrationdate'])) {
                        $dt = new DateTime($user['registrationdate']);
                        echo $dt->format('Y-m-d');
                    } else {
                        echo 'N/A';
                    }
                    ?>
                </div>
                <?php if (!empty($user['description'])): ?>
                    <div class="profile-description">
                        <?php echo nl2br(htmlspecialchars($user['description'], ENT_QUOTES)); ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="profile-actions">
                <a href="../userpages/profileEdit.php" class="btn">Edit profile</a>
            </div>
        </div>
    </section>

    <!-- Sports practiced section -->
    <section class="profile-section">
        <div class="sports-header">
            <h2 class="section-title">Sports practiced</h2>
            <a href="../userpages/manageSports.php" class="btn">Add sport</a>
        </div>

        <?php if (count($userSports) === 0): ?>
            <p class="sports-empty">You have not added any sports yet.</p>
        <?php else: ?>
            <div class="sports-list">
                <?php foreach ($userSports as $sport): ?>
                    <div class="sport-chip">
                        <?php if (!empty($sport['sportimage'])): ?>
                            <img src="../<?php echo htmlspecialchars($sport['sportimage'], ENT_QUOTES); ?>"
                                 alt="<?php echo htmlspecialchars($sport['name'], ENT_QUOTES); ?> icon">
                        <?php endif; ?>
                        <span><?php echo htmlspecialchars($sport['name'], ENT_QUOTES); ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <!-- Athlete analysis section -->
    <section class="profile-section">
        <h2 class="section-title">Athlete analysis</h2>

        <div class="analysis-grid">
            <div class="analysis-card">
                <div class="analysis-label">Weekly activity time</div>
                <div class="analysis-value">
                    <?php echo htmlspecialchars($weekly['hours'], ENT_QUOTES); ?> h
                </div>
            </div>
            <div class="analysis-card">
                <div class="analysis-label">Activities this week</div>
                <div class="analysis-value">
                    <?php echo htmlspecialchars($weekly['activities'], ENT_QUOTES); ?>
                </div>
            </div>
        </div>

        <div class="recent-activities">
            <h3>Recent activities</h3>
            <?php if (count($recentActivities) === 0): ?>
                <p class="sports-empty">No activities published yet.</p>
            <?php else: ?>
                <?php foreach ($recentActivities as $act): ?>
                    <div class="activity-item">
                        <div class="activity-main">
                            <div class="activity-name">
                                <?php
                                $name = $act['activity_name'] ?: $act['sport_name'];
                                echo htmlspecialchars($name, ENT_QUOTES);
                                ?>
                            </div>
                            <div class="activity-meta">
                                <?php echo htmlspecialchars($act['sport_name'], ENT_QUOTES); ?>
                                ·
                                <?php
                                $adt = new DateTime($act['activitydate']);
                                echo $adt->format('Y-m-d H:i');
                                ?>
                            </div>
                        </div>
                        <div class="activity-duration">
                            <?php
                            $durMin = (int)$act['duration'];
                            $hours = intdiv($durMin, 60);
                            $mins = $durMin % 60;
                            if ($hours > 0) {
                                echo $hours . 'h ' . $mins . 'm';
                            } else {
                                echo $mins . 'm';
                            }
                            ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </section>

</div>

</body>
</html>