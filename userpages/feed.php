<?php

$userId = isset($_SESSION['userId']) ? (int) $_SESSION['userId'] : 0;
if ($userId <= 0) {
    header('Location: ../include/loginForm.php');
    exit;
}

$pdo = DBHandler::getPDO();

// Handle Follow / Unfollow actions via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'follow' && isset($_POST['target_id'])) {
        $targetId = (int)$_POST['target_id'];
        $stmt = $pdo->prepare('INSERT IGNORE INTO Follow (followerid, followedid, followdate) VALUES (:fid, :tid, NOW())');
        $stmt->execute([':fid' => $userId, ':tid' => $targetId]);
    } elseif ($_POST['action'] === 'unfollow' && isset($_POST['target_id'])) {
        $targetId = (int)$_POST['target_id'];
        $stmt = $pdo->prepare('DELETE FROM Follow WHERE followerid = :fid AND followedid = :tid');
        $stmt->execute([':fid' => $userId, ':tid' => $targetId]);
    }
    $redirect = $_SERVER['HTTP_REFERER'] ?? 'feed.php';
    header('Location: ' . $redirect);
    exit;
}

// Fetch Feed Activities
$sqlFeed = "
    SELECT
        a.activityid,
        a.userid,
        u.username,
        u.name AS user_name,
        u.surname AS user_surname,
        u.userimage,
        a.sportid,
        s.name AS sport_name,
        s.sportimage,
        a.name AS activity_name,
        a.activitydate,
        a.duration,
        a.description,
        r.distance AS r_distance, r.pace AS r_pace,
        c.distance AS c_distance, c.elevation AS c_elevation,
        e.distance AS e_distance, e.elevation AS e_elevation,
        sk.distance AS sk_distance, sk.elevation AS sk_elevation,
        sw.distance AS sw_distance, sw.pace AS sw_pace
    FROM Activity a
    INNER JOIN User u ON a.userid = u.userid
    INNER JOIN Sport s ON a.sportid = s.sportid
    LEFT JOIN Run r ON a.activityid = r.activityid
    LEFT JOIN Cycling c ON a.activityid = c.activityid
    LEFT JOIN Excursion e ON a.activityid = e.activityid
    LEFT JOIN Ski sk ON a.activityid = sk.activityid
    LEFT JOIN Swimming sw ON a.activityid = sw.activityid
    WHERE a.userid = :userId
       OR a.userid IN (SELECT followedid FROM Follow WHERE followerid = :userId)
    ORDER BY a.activitydate DESC
    LIMIT 50
";
$sthFeed = $pdo->prepare($sqlFeed);
$sthFeed->execute([':userId' => $userId]);
$feedActivities = $sthFeed->fetchAll();

// Fetch Recommended Users (Not followed by current user, not current user)
$sqlRecommended = "
    SELECT u.userid, u.username, u.name, u.surname, u.userimage, u.description,
           (SELECT COUNT(*) FROM Follow WHERE followedid = u.userid) AS followers
    FROM User u
    WHERE u.userid != :userId
      AND u.userid NOT IN (SELECT followedid FROM Follow WHERE followerid = :userId)
    ORDER BY RAND()
    LIMIT 5
";
$sthRec = $pdo->prepare($sqlRecommended);
$sthRec->execute([':userId' => $userId]);
$recommendedUsers = $sthRec->fetchAll();

$formatDuration = function($minutes) {
    $h = intdiv($minutes, 60);
    $m = $minutes % 60;
    if ($h > 0) return "{$h}h {$m}m";
    return "{$m}m";
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Feed - NHS</title>
    <link rel="stylesheet" href="../css/global.css?v=2">
    <link rel="stylesheet" href="../css/header-footer.css">
</head>
<body>

<?php include __DIR__ . '/../include/header.php'; ?>

<div class="feed-container">
    <main class="feed-main">
        <div class="feed-header">
            <h2>Your Feed</h2>
            <a href="newactivity.php" class="btn">Create Activity</a>
        </div>

        <?php if (empty($feedActivities)): ?>
            <p style="color: var(--color-text-muted);">No activities found. Follow some users or create your own activities!</p>
        <?php else: ?>
            <?php foreach ($feedActivities as $act): ?>
                <div class="activity-card">
                    <div class="activity-header">
                        <?php $userImg = !empty($act['userimage']) ? htmlspecialchars($act['userimage']) : '../media/default-user.png'; ?>
                        <img src="<?php echo $userImg; ?>" alt="User" class="activity-user-img">
                        <div class="activity-meta">
                            <h4><?php echo htmlspecialchars($act['user_name'] . ' ' . $act['user_surname']); ?></h4>
                            <span><?php echo (new DateTime($act['activitydate']))->format('d M Y, H:i'); ?></span>
                        </div>
                        <?php if (!empty($act['sportimage'])): ?>
                            <img src="../<?php echo htmlspecialchars($act['sportimage']); ?>" alt="Sport" class="activity-sport-icon">
                        <?php endif; ?>
                    </div>
                    <div class="activity-body">
                        <h3><?php echo htmlspecialchars($act['activity_name'] ?: $act['sport_name']); ?></h3>
                        <?php if (!empty($act['description'])): ?>
                            <p style="color: var(--color-text-muted); font-size: 0.95rem; margin-top: 0.5rem;">
                                <?php echo nl2br(htmlspecialchars($act['description'])); ?>
                            </p>
                        <?php endif; ?>
                        
                        <div class="activity-stats">
                            <div class="stat-item">
                                <span class="label">Duration</span>
                                <span class="value"><?php echo $formatDuration($act['duration']); ?></span>
                            </div>
                            
                            <?php 
                            // Render specific metrics based on sport
                            $dist = $act['r_distance'] ?? $act['c_distance'] ?? $act['e_distance'] ?? $act['sk_distance'] ?? null;
                            if ($dist !== null): ?>
                                <div class="stat-item">
                                    <span class="label">Distance</span>
                                    <span class="value"><?php echo $dist; ?> km</span>
                                </div>
                            <?php endif; ?>

                            <?php if (isset($act['sw_distance'])): ?>
                                <div class="stat-item">
                                    <span class="label">Distance</span>
                                    <span class="value"><?php echo $act['sw_distance']; ?> m</span>
                                </div>
                            <?php endif; ?>

                            <?php 
                            $elev = $act['c_elevation'] ?? $act['e_elevation'] ?? $act['sk_elevation'] ?? null;
                            if ($elev !== null): ?>
                                <div class="stat-item">
                                    <span class="label">Elevation</span>
                                    <span class="value"><?php echo $elev; ?> m</span>
                                </div>
                            <?php endif; ?>

                            <?php 
                            $pace = $act['r_pace'] ?? $act['sw_pace'] ?? null;
                            if ($pace !== null): ?>
                                <div class="stat-item">
                                    <span class="label">Pace</span>
                                    <span class="value"><?php echo $pace; ?> /km</span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </main>

    <aside class="feed-sidebar">
        <div class="recommended-box">
            <h3>Recommended to Follow</h3>
            <?php if (empty($recommendedUsers)): ?>
                <p style="font-size: 0.85rem; color: var(--color-text-muted);">No recommendations available.</p>
            <?php else: ?>
                <?php foreach ($recommendedUsers as $ru): ?>
                    <?php 
                        $ruImg = !empty($ru['userimage']) ? htmlspecialchars($ru['userimage']) : '../media/default-user.png'; 
                    ?>
                    <div class="rec-user" onclick="window.location.href='profile.php?id=<?php echo (int)$ru['userid']; ?>'">
                        <img src="<?php echo $ruImg; ?>" alt="User">
                        <div class="rec-user-info">
                            <strong><?php echo htmlspecialchars($ru['name'] . ' ' . $ru['surname']); ?></strong>
                            <span>@<?php echo htmlspecialchars($ru['username']); ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </aside>
</div>


</body>
</html>