<?php
require_once __DIR__ . '/../include/header.php';

$userId = isset($_SESSION['userId']) ? (int) $_SESSION['userId'] : 0;
if ($userId <= 0) {
    header('Location: ../include/loginForm.php');
    exit;
}

$pdo = DBHandler::getPDO();

// Handle Follow / Unfollow
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

// Feed activities
$sqlFeed = "
    SELECT
        a.activityid, a.userid,
        u.username, u.name AS user_name, u.surname AS user_surname, u.userimage,
        s.name AS sport_name, s.sportimage,
        a.name AS activity_name, a.activitydate, a.duration, a.description,
        r.distance AS r_distance, r.pace AS r_pace,
        c.distance AS c_distance, c.elevation AS c_elevation,
        e.distance AS e_distance, e.elevation AS e_elevation,
        sk.distance AS sk_distance, sk.elevation AS sk_elevation,
        sw.distance AS sw_distance, sw.pace AS sw_pace,
        (SELECT COUNT(*) FROM ActivityLike al WHERE al.activityid = a.activityid) AS like_count,
        (SELECT COUNT(*) FROM ActivityLike al WHERE al.activityid = a.activityid AND al.userid = :userIdLike) AS user_liked,
        (SELECT COUNT(*) FROM ActivityComment ac WHERE ac.activityid = a.activityid) AS comment_count
    FROM Activity a
    INNER JOIN User u ON a.userid = u.userid
    INNER JOIN Sport s ON a.sportid = s.sportid
    LEFT JOIN Run r ON a.activityid = r.activityid
    LEFT JOIN Cycling c ON a.activityid = c.activityid
    LEFT JOIN Excursion e ON a.activityid = e.activityid
    LEFT JOIN Ski sk ON a.activityid = sk.activityid
    LEFT JOIN Swimming sw ON a.activityid = sw.activityid
    WHERE a.userid = :userId
       OR a.userid IN (SELECT followedid FROM Follow WHERE followerid = :userId2)
    ORDER BY a.activitydate DESC
    LIMIT 50
";
$sthFeed = $pdo->prepare($sqlFeed);
$sthFeed->execute([':userId' => $userId, ':userId2' => $userId, ':userIdLike' => $userId]);
$feedActivities = $sthFeed->fetchAll();

// Collect photos and comments for all activities
$activityIds = array_column($feedActivities, 'activityid');
$photosByActivity = [];
$commentsByActivity = [];

if (!empty($activityIds)) {
    $placeholders = implode(',', array_fill(0, count($activityIds), '?'));

    $stmtPhotos = $pdo->prepare("SELECT activityid, url FROM ActivityPhoto WHERE activityid IN ($placeholders)");
    $stmtPhotos->execute($activityIds);
    foreach ($stmtPhotos->fetchAll() as $p) {
        $photosByActivity[$p['activityid']][] = $p['url'];
    }

    $stmtComments = $pdo->prepare("
        SELECT ac.activityid, ac.text, ac.commentdate, u.username, u.name, u.surname, u.userimage
        FROM ActivityComment ac
        JOIN User u ON ac.userid = u.userid
        WHERE ac.activityid IN ($placeholders)
        ORDER BY ac.commentdate ASC
    ");
    $stmtComments->execute($activityIds);
    foreach ($stmtComments->fetchAll() as $c) {
        $commentsByActivity[$c['activityid']][] = $c;
    }
}

// Recommended users
$sqlRecommended = "
    SELECT u.userid, u.username, u.name, u.surname, u.userimage,
           (SELECT COUNT(*) FROM Follow WHERE followedid = u.userid) AS followers
    FROM User u
    WHERE u.userid != :userId
      AND u.userid NOT IN (SELECT followedid FROM Follow WHERE followerid = :userId2)
    ORDER BY RAND() LIMIT 5
";
$sthRec = $pdo->prepare($sqlRecommended);
$sthRec->execute([':userId' => $userId, ':userId2' => $userId]);
$recommendedUsers = $sthRec->fetchAll();

$formatDuration = function($minutes) {
    $h = intdiv($minutes, 60); $m = $minutes % 60;
    return $h > 0 ? "{$h}h {$m}m" : "{$m}m";
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Feed - NHS</title>
    <link rel="stylesheet" href="../css/global.css?v=3">
    <link rel="stylesheet" href="../css/header-footer.css">
</head>
<body>

<?php include __DIR__ . '/../include/menu/menuChoice.php'; ?>

<div class="feed-container">
    <main class="feed-main">
        <div class="feed-header">
            <h2>Your Feed</h2>
            <a href="newactivity.php" class="btn">+ New Activity</a>
        </div>

        <?php if (empty($feedActivities)): ?>
            <p class="feed-empty">No activities yet. Follow some athletes or log your first activity!</p>
        <?php else: ?>
            <?php foreach ($feedActivities as $act):
                $isOwner = ((int)$act['userid'] === $userId);
                $liked = (bool)$act['user_liked'];
                $aid = (int)$act['activityid'];
                $photos = $photosByActivity[$aid] ?? [];
                $comments = $commentsByActivity[$aid] ?? [];
                $userImg = !empty($act['userimage']) ? htmlspecialchars($act['userimage']) : '../media/default-user.png';
                $dist = $act['r_distance'] ?? $act['c_distance'] ?? $act['e_distance'] ?? $act['sk_distance'] ?? null;
                $elev = $act['c_elevation'] ?? $act['e_elevation'] ?? $act['sk_elevation'] ?? null;
                $pace = $act['r_pace'] ?? $act['sw_pace'] ?? null;
            ?>
            <article class="activity-card" id="card-<?= $aid ?>">

                <!-- Header: user info -->
                <div class="activity-card-header">
                    <a href="profile.php?id=<?= (int)$act['userid'] ?>" class="activity-user-link">
                        <img src="<?= $userImg ?>" alt="User" class="activity-user-img">
                        <div class="activity-user-info">
                            <strong><?= htmlspecialchars($act['user_name'] . ' ' . $act['user_surname']) ?></strong>
                            <span>@<?= htmlspecialchars($act['username']) ?> · <?= (new DateTime($act['activitydate']))->format('d M Y, H:i') ?></span>
                        </div>
                    </a>
                    <?php if (!empty($act['sportimage'])): ?>
                        <img src="../<?= htmlspecialchars($act['sportimage']) ?>" alt="<?= htmlspecialchars($act['sport_name']) ?>" class="activity-sport-icon" title="<?= htmlspecialchars($act['sport_name']) ?>">
                    <?php endif; ?>
                </div>

                <!-- Body -->
                <div class="activity-card-body">
                    <h3 class="activity-title"><?= htmlspecialchars($act['activity_name'] ?: $act['sport_name']) ?></h3>
                    <?php if (!empty($act['description'])): ?>
                        <p class="activity-description"><?= nl2br(htmlspecialchars($act['description'])) ?></p>
                    <?php endif; ?>

                    <!-- Stats chips -->
                    <div class="activity-stats">
                        <div class="stat-chip">
                            <span class="stat-val"><?= $formatDuration($act['duration']) ?></span>
                            <span class="stat-lbl">Duration</span>
                        </div>
                        <?php if ($dist !== null): ?>
                        <div class="stat-chip">
                            <span class="stat-val"><?= number_format((float)$dist, 2) ?> km</span>
                            <span class="stat-lbl">Distance</span>
                        </div>
                        <?php endif; ?>
                        <?php if (isset($act['sw_distance']) && $act['sw_distance'] !== null): ?>
                        <div class="stat-chip">
                            <span class="stat-val"><?= (int)$act['sw_distance'] ?> m</span>
                            <span class="stat-lbl">Distance</span>
                        </div>
                        <?php endif; ?>
                        <?php if ($elev !== null): ?>
                        <div class="stat-chip">
                            <span class="stat-val"><?= (int)$elev ?> m</span>
                            <span class="stat-lbl">Elevation</span>
                        </div>
                        <?php endif; ?>
                        <?php if ($pace !== null): ?>
                        <div class="stat-chip">
                            <span class="stat-val"><?= number_format((float)$pace, 2) ?>/km</span>
                            <span class="stat-lbl">Pace</span>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Photos -->
                    <?php if (!empty($photos)): ?>
                    <div class="activity-photos">
                        <?php foreach ($photos as $photoUrl): ?>
                            <img src="<?= htmlspecialchars($photoUrl) ?>" class="activity-photo" alt="Activity photo">
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Actions bar -->
                <div class="activity-actions-bar">
                    <button class="action-btn like-btn <?= $liked ? 'liked' : '' ?>"
                            data-id="<?= $aid ?>"
                            data-liked="<?= $liked ? '1' : '0' ?>">
                        <span class="like-icon"><?= $liked ? '&hearts;' : '&hearts;' ?></span>
                        <span class="like-count"><?= (int)$act['like_count'] ?></span>
                        Like
                    </button>

                    <button class="action-btn comment-toggle-btn" data-id="<?= $aid ?>">
                        Comment <span><?= (int)$act['comment_count'] ?></span>
                    </button>

                    <?php if ($isOwner): ?>
                    <a href="editactivity.php?id=<?= $aid ?>" class="action-btn" title="Edit activity" style="text-decoration:none;">Edit</a>
                    <?php endif; ?>
                </div>

                <!-- Comments section (hidden by default) -->
                <div class="activity-comments" id="comments-<?= $aid ?>" style="display:none;">
                    <div class="comments-list" id="comments-list-<?= $aid ?>">
                        <?php foreach ($comments as $c):
                            $cAvatar = !empty($c['userimage']) ? htmlspecialchars($c['userimage']) : '../media/default-user.png';
                            $cDt = new DateTime($c['commentdate']);
                        ?>
                        <div class="comment-item">
                            <div class="comment-header">
                                <img src="<?= $cAvatar ?>" alt="" class="comment-avatar">
                                <strong><?= htmlspecialchars($c['name'] . ' ' . $c['surname']) ?></strong>
                                <span>@<?= htmlspecialchars($c['username']) ?> · <?= $cDt->format('d M, H:i') ?></span>
                            </div>
                            <p class="comment-text"><?= nl2br(htmlspecialchars($c['text'])) ?></p>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <form class="comment-form" data-id="<?= $aid ?>">
                        <input type="text" placeholder="Write a comment…" class="comment-input" maxlength="500" required>
                        <button type="submit" class="btn" style="padding: 0.4rem 1rem;">Post</button>
                    </form>
                </div>

            </article>
            <?php endforeach; ?>
        <?php endif; ?>
    </main>

    <aside class="feed-sidebar">
        <div class="recommended-box">
            <h3>Suggested Athletes</h3>
            <?php if (empty($recommendedUsers)): ?>
                <p style="font-size:0.85rem;opacity:0.6;">No suggestions available.</p>
            <?php else: ?>
                <?php foreach ($recommendedUsers as $ru):
                    $ruImg = !empty($ru['userimage']) ? htmlspecialchars($ru['userimage']) : '../media/default-user.png';
                ?>
                <div class="rec-user" onclick="window.location.href='profile.php?id=<?= (int)$ru['userid'] ?>'">
                    <img src="<?= $ruImg ?>" alt="User">
                    <div class="rec-user-info">
                        <strong><?= htmlspecialchars($ru['name'] . ' ' . $ru['surname']) ?></strong>
                        <span>@<?= htmlspecialchars($ru['username']) ?></span>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </aside>
</div>

<script>
// Like toggle
document.querySelectorAll('.like-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        const id = btn.dataset.id;
        const liked = btn.dataset.liked === '1';
        const action = liked ? 'unlike' : 'like';

        fetch('activityAction.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `action=${action}&activity_id=${id}`
        })
        .then(r => r.text())
        .then(count => {
            btn.dataset.liked = liked ? '0' : '1';
            btn.classList.toggle('liked');
            btn.querySelector('.like-icon').innerHTML = liked ? '&hearts;' : '&hearts;';
            btn.querySelector('.like-count').textContent = count;
        });
    });
});

// Comment toggle
document.querySelectorAll('.comment-toggle-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        const section = document.getElementById('comments-' + btn.dataset.id);
        section.style.display = section.style.display === 'none' ? 'block' : 'none';
    });
});

// Comment submit
document.querySelectorAll('.comment-form').forEach(form => {
    form.addEventListener('submit', e => {
        e.preventDefault();
        const id = form.dataset.id;
        const input = form.querySelector('.comment-input');
        const text = input.value.trim();
        if (!text) return;

        fetch('activityAction.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `action=comment&activity_id=${id}&text=${encodeURIComponent(text)}`
        })
        .then(r => r.text())
        .then(html => {
            document.getElementById('comments-list-' + id).insertAdjacentHTML('beforeend', html);
            input.value = '';
            // Update comment count
            const toggleBtn = document.querySelector(`.comment-toggle-btn[data-id="${id}"] span`);
            if (toggleBtn) toggleBtn.textContent = parseInt(toggleBtn.textContent) + 1;
        });
    });
});

</script>

</body>
</html>