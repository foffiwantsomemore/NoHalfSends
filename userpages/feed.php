<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../include/dbHandler.php';

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
    header('Location: feed.php');
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
    <style>
        .feed-container {
            display: flex;
            max-width: 1000px;
            margin: 0 auto;
            padding: 2rem 1rem;
            gap: 2rem;
        }
        .feed-main {
            flex: 1;
        }
        .feed-sidebar {
            width: 300px;
            flex-shrink: 0;
        }
        .feed-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }
        .activity-card {
            background-color: var(--color-surface);
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            border: 1px solid var(--color-border);
        }
        .activity-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
        }
        .activity-user-img {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            object-fit: cover;
        }
        .activity-meta {
            flex: 1;
        }
        .activity-meta h4 {
            margin: 0;
            font-size: 1rem;
            color: var(--color-text-primary);
        }
        .activity-meta span {
            font-size: 0.85rem;
            color: var(--color-text-muted);
        }
        .activity-sport-icon {
            width: 32px;
            height: 32px;
        }
        .activity-body h3 {
            margin: 0 0 0.5rem 0;
            color: var(--color-primary);
        }
        .activity-stats {
            display: flex;
            gap: 2rem;
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid var(--color-border);
        }
        .stat-item {
            display: flex;
            flex-direction: column;
        }
        .stat-item .label {
            font-size: 0.8rem;
            color: var(--color-text-muted);
        }
        .stat-item .value {
            font-weight: 600;
            color: var(--color-text-primary);
            font-size: 1.1rem;
        }
        /* Recommended Box */
        .recommended-box {
            background-color: var(--color-surface);
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            border: 1px solid var(--color-border);
        }
        .recommended-box h3 {
            margin-top: 0;
            margin-bottom: 1rem;
        }
        .rec-user {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1rem;
            cursor: pointer;
            transition: opacity 0.2s;
        }
        .rec-user:hover {
            opacity: 0.8;
        }
        .rec-user img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
        }
        .rec-user-info {
            flex: 1;
        }
        .rec-user-info strong {
            display: block;
            font-size: 0.95rem;
            color: var(--color-text-primary);
        }
        .rec-user-info span {
            font-size: 0.8rem;
            color: var(--color-text-muted);
        }
        /* Modal Profile */
        .modal-overlay {
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.7);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 1000;
            backdrop-filter: blur(4px);
        }
        .modal-content {
            background: var(--color-surface);
            border-radius: var(--radius-lg);
            padding: 2rem;
            width: 90%;
            max-width: 400px;
            text-align: center;
            position: relative;
        }
        .modal-close {
            position: absolute;
            top: 1rem; right: 1rem;
            background: none; border: none;
            color: var(--color-text-muted);
            font-size: 1.5rem;
            cursor: pointer;
        }
        .modal-avatar {
            width: 80px; height: 80px;
            border-radius: 50%;
            margin-bottom: 1rem;
            object-fit: cover;
        }
        .modal-stats {
            display: flex; justify-content: center; gap: 2rem;
            margin: 1rem 0;
        }
        .modal-stats div { text-align: center; }
        .modal-stats strong { display: block; font-size: 1.2rem; }
        .modal-stats span { font-size: 0.85rem; color: var(--color-text-muted); }
        
        @media (max-width: 768px) {
            .feed-container { flex-direction: column; }
            .feed-sidebar { width: 100%; }
        }
    </style>
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
                        $ruData = json_encode([
                            'id' => $ru['userid'],
                            'name' => $ru['name'] . ' ' . $ru['surname'],
                            'username' => '@' . $ru['username'],
                            'img' => $ruImg,
                            'desc' => $ru['description'],
                            'followers' => $ru['followers']
                        ]);
                    ?>
                    <div class="rec-user" onclick="openProfileModal(<?php echo htmlspecialchars($ruData, ENT_QUOTES); ?>)">
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

<!-- Quick Profile Modal -->
<div class="modal-overlay" id="profileModal">
    <div class="modal-content">
        <button class="modal-close" onclick="closeProfileModal()">&times;</button>
        <img src="" id="modImg" class="modal-avatar" alt="User">
        <h2 id="modName" style="margin: 0; font-size: 1.5rem;"></h2>
        <div id="modUsername" style="color: var(--color-text-muted); margin-bottom: 0.5rem;"></div>
        <p id="modDesc" style="font-size: 0.9rem; margin-bottom: 1rem;"></p>
        
        <div class="modal-stats">
            <div>
                <strong id="modFollowers">0</strong>
                <span>Followers</span>
            </div>
        </div>
        
        <form method="post" action="feed.php" style="margin-top: 1.5rem;">
            <input type="hidden" name="action" value="follow">
            <input type="hidden" name="target_id" id="modTargetId" value="">
            <button type="submit" class="btn" style="width: 100%;">Follow User</button>
        </form>
    </div>
</div>

<script>
    function openProfileModal(user) {
        document.getElementById('modImg').src = user.img;
        document.getElementById('modName').innerText = user.name;
        document.getElementById('modUsername').innerText = user.username;
        document.getElementById('modDesc').innerText = user.desc || '';
        document.getElementById('modFollowers').innerText = user.followers;
        document.getElementById('modTargetId').value = user.id;
        document.getElementById('profileModal').style.display = 'flex';
    }

    function closeProfileModal() {
        document.getElementById('profileModal').style.display = 'none';
    }

    // Close on click outside
    document.getElementById('profileModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeProfileModal();
        }
    });
</script>

</body>
</html>