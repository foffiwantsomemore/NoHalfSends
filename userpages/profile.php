<?php

$pdo = DBHandler::getPDO();

$userId = $_SESSION['userId'] ?? null;
/*if (!$userId) {
    header('Location: ../include/loginForm.php');
    exit;
}*/

include __DIR__ . '/../include/menu/menuChoice.php';

//user basic info
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

// followers count
$sqlFollowers = "
    SELECT COUNT(*) AS follower_count
    FROM Follow
    WHERE followedid = :uid
";
$sthFollowers = $pdo->prepare($sqlFollowers);
$sthFollowers->bindValue(':uid', $userId, PDO::PARAM_INT);
$sthFollowers->execute();
$followerCount = $sthFollowers->fetch()['follower_count'] ?? 0;

// following count
$sqlFollowing = "
    SELECT COUNT(*) AS following_count
    FROM Follow
    WHERE followerid = :uid
";
$sthFollowing = $pdo->prepare($sqlFollowing);
$sthFollowing->bindValue(':uid', $userId, PDO::PARAM_INT);
$sthFollowing->execute();
$followingCount = $sthFollowing->fetch()['following_count'] ?? 0;


//sports practiced
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

// all sports for popup selector
$sqlAllSports = "SELECT sportid, name, sportimage FROM Sport ORDER BY name";
$sthAllSports = $pdo->prepare($sqlAllSports);
$sthAllSports->execute();
$allSports = $sthAllSports->fetchAll();

// user's sports ids for checkbox preselection
$userSportIds = array_map(function($row) { return (int)$row['sportid']; }, $userSports);

//weekly summary (last 7 days)
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

//recent activities
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

    <!--account-->
    <section class="profile-section">
        <div class="profile-header">
            <div class="profile-avatar">
                <?php if (!empty($user['userimage'])): ?>
                    <img src="<?php echo htmlspecialchars($user['userimage'], ENT_QUOTES); ?>" alt="Profile picture">
                <?php else: ?>
                    <img src="../media/default-user.png" alt="Default profile picture">
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
                        echo $dt->format('d-m-Y');
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

    <!--followers and following-->
    <section class="profile-section">
        <h2 class="section-title">Community</h2>

        <div class="community-stats">
            <div class="community-card">
                <div class="community-value"><?php echo htmlspecialchars($followerCount, ENT_QUOTES); ?></div>
                <div class="community-label">Followers</div>
            </div>
            <div class="community-card">
                <div class="community-value"><?php echo htmlspecialchars($followingCount, ENT_QUOTES); ?></div>
                <div class="community-label">Following</div>
            </div>
        </div>
    </section>

    <!--sports practiced-->
    <section class="profile-section">
        <div class="sports-header">
            <h2 class="section-title">Sports practiced</h2>
            <button type="button" class="btn" id="open-manage-sports">Add sport</button>
        </div>
        
        <!-- Manage sports dropdown -->
        <div id="manage-sports-dropdown" class="manage-sports-dropdown" style="display: none;">
            <div class="manage-sports-content">
                <div class="manage-sports-title">
                    <h3>Choose your sports</h3>
                    <button type="button" class="manage-sports-close" id="close-manage-sports">×</button>
                </div>
                <p class="manage-sports-hint">Select the sports you practice. They will appear in your profile.</p>

                <form action="manageSports.php" method="post" class="manage-sports-form">
                    <div class="sport-select-grid">
                        <?php foreach ($allSports as $sport): ?>
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
                        <button type="button" class="btn btn-secondary" id="cancel-manage-sports">Cancel</button>
                    </div>
                </form>
            </div>
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

    <!--analysis-->
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
    </section>

    <!--recent activities-->
    <section class="profile-section">
        <h2 class="section-title">Recent activities</h2>
        <div class="recent-activities">
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
<script>
document.addEventListener('DOMContentLoaded', function () {
    var openBtn = document.getElementById('open-manage-sports');
    var dropdown = document.getElementById('manage-sports-dropdown');
    var closeBtn = document.getElementById('close-manage-sports');
    var cancelBtn = document.getElementById('cancel-manage-sports');

    function openDropdown() {
        if (dropdown) {
            dropdown.style.display = 'block';
        }
    }

    function closeDropdown() {
        if (dropdown) {
            dropdown.style.display = 'none';
        }
    }

    if (openBtn) {
        openBtn.addEventListener('click', openDropdown);
    }
    if (closeBtn) {
        closeBtn.addEventListener('click', closeDropdown);
    }
    if (cancelBtn) {
        cancelBtn.addEventListener('click', closeDropdown);
    }
});
</script>

</body>
</html>