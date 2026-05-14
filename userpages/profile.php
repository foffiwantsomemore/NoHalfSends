<?php
require_once __DIR__ . '/../include/header.php';

$pdo = DBHandler::getPDO();

$loggedInUserId = $_SESSION['userId'] ?? null;
$userId = isset($_GET['id']) ? (int)$_GET['id'] : $loggedInUserId;

if (!$userId) {
    header('Location: ../include/loginForm.php');
    exit;
}

$isOwnProfile = ($loggedInUserId === $userId);
$isFollowing = false;

if (!$isOwnProfile && $loggedInUserId && $userId) {
    $sqlCheck = "SELECT 1 FROM v_user_following WHERE userid = :fid AND followedid = :uid";
    $sthCheck = $pdo->prepare($sqlCheck);
    $sthCheck->execute([':fid' => $loggedInUserId, ':uid' => $userId]);
    $isFollowing = (bool)$sthCheck->fetch();
}

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
    FROM v_user_followers
    WHERE userid = :uid
";
$sthFollowers = $pdo->prepare($sqlFollowers);
$sthFollowers->bindValue(':uid', $userId, PDO::PARAM_INT);
$sthFollowers->execute();
$followerCount = $sthFollowers->fetch()['follower_count'] ?? 0;

// following count
$sqlFollowing = "
    SELECT COUNT(*) AS following_count
    FROM v_user_following
    WHERE userid = :uid
";
$sthFollowing = $pdo->prepare($sqlFollowing);
$sthFollowing->bindValue(':uid', $userId, PDO::PARAM_INT);
$sthFollowing->execute();
$followingCount = $sthFollowing->fetch()['following_count'] ?? 0;

$sqlFollowerUsers = "
    SELECT followerid AS userid, name, surname, username, userimage
    FROM v_user_followers
    WHERE userid = :uid
    ORDER BY followdate DESC, username ASC
";
$sthFollowerUsers = $pdo->prepare($sqlFollowerUsers);
$sthFollowerUsers->bindValue(':uid', $userId, PDO::PARAM_INT);
$sthFollowerUsers->execute();
$followerUsers = $sthFollowerUsers->fetchAll();

$sqlFollowingUsers = "
    SELECT followedid AS userid, name, surname, username, userimage
    FROM v_user_following
    WHERE userid = :uid
    ORDER BY followdate DESC, username ASC
";
$sthFollowingUsers = $pdo->prepare($sqlFollowingUsers);
$sthFollowingUsers->bindValue(':uid', $userId, PDO::PARAM_INT);
$sthFollowingUsers->execute();
$followingUsers = $sthFollowingUsers->fetchAll();


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
$recentActivities = [];
if ($isOwnProfile || $isFollowing) {
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
}

// sport distribution for donut chart
$sqlSportDistribution = "
    SELECT sport_name, COUNT(*) AS total
    FROM v_user_activities
    WHERE userid = :uid
    GROUP BY sport_name
    ORDER BY total DESC, sport_name ASC
";
$sthSportDistribution = $pdo->prepare($sqlSportDistribution);
$sthSportDistribution->bindValue(':uid', $userId, PDO::PARAM_INT);
$sthSportDistribution->execute();
$sportDistributionRows = $sthSportDistribution->fetchAll();

$sportDistributionLabels = [];
$sportDistributionData = [];
foreach ($sportDistributionRows as $row) {
    $sportDistributionLabels[] = $row['sport_name'];
    $sportDistributionData[] = (int)$row['total'];
}

// weekly distance (last 7 days) for line chart, in km
$sqlWeeklyDistance = "
    SELECT
        DATE(a.activitydate) AS activity_day,
        ROUND(
            SUM(
                COALESCE(
                    r.distance,
                    c.distance,
                    e.distance,
                    s.distance,
                    sw.distance / 1000,
                    0
                )
            ),
            2
        ) AS total_distance
    FROM Activity a
    LEFT JOIN Run r ON r.activityid = a.activityid
    LEFT JOIN Cycling c ON c.activityid = a.activityid
    LEFT JOIN Excursion e ON e.activityid = a.activityid
    LEFT JOIN Ski s ON s.activityid = a.activityid
    LEFT JOIN Swimming sw ON sw.activityid = a.activityid
    WHERE a.userid = :uid
      AND a.activitydate >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
    GROUP BY DATE(a.activitydate)
    ORDER BY activity_day ASC
";
$sthWeeklyDistance = $pdo->prepare($sqlWeeklyDistance);
$sthWeeklyDistance->bindValue(':uid', $userId, PDO::PARAM_INT);
$sthWeeklyDistance->execute();
$weeklyDistanceRows = $sthWeeklyDistance->fetchAll();

$weeklyDistanceByDay = [];
foreach ($weeklyDistanceRows as $row) {
    $weeklyDistanceByDay[$row['activity_day']] = (float)$row['total_distance'];
}

$weeklyDistanceLabels = [];
$weeklyDistanceData = [];
for ($i = 6; $i >= 0; $i--) {
    $date = new DateTime('-' . $i . ' day');
    $dayKey = $date->format('Y-m-d');
    $weeklyDistanceLabels[] = $date->format('d/m');
    $weeklyDistanceData[] = $weeklyDistanceByDay[$dayKey] ?? 0;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Profile - NHS</title>
    <link rel="stylesheet" href="../css/global.css?v=2">
    <link rel="stylesheet" href="../css/header-footer.css">
</head>
<body>
<?php include __DIR__ . '/../include/menu/menuChoice.php'; ?>

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
                <?php if ($isOwnProfile): ?>
                    <a href="../userpages/profileEdit.php" class="btn">Edit profile</a>
                <?php else: ?>
                    <form method="post" action="feed.php" style="display:inline;">
                        <input type="hidden" name="target_id" value="<?php echo $userId; ?>">
                        <?php if ($isFollowing): ?>
                            <input type="hidden" name="action" value="unfollow">
                            <button type="submit" class="btn btn-secondary">Unfollow</button>
                        <?php else: ?>
                            <input type="hidden" name="action" value="follow">
                            <button type="submit" class="btn">Follow</button>
                        <?php endif; ?>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!--followers and following-->
    <section class="profile-section">
        <h2 class="section-title">Community</h2>

        <div class="community-stats">
            <div class="community-card">
                <details class="community-people">
                    <summary>
                        <span>
                            <span class="community-value"><?php echo htmlspecialchars($followerCount, ENT_QUOTES); ?></span>
                            <span class="community-label">Followers</span>
                        </span>
                        <span class="community-open-icon" aria-hidden="true"></span>
                    </summary>

                    <div class="community-people-list">
                        <?php if (count($followerUsers) === 0): ?>
                            <p class="community-empty">No followers yet.</p>
                        <?php else: ?>
                            <?php foreach ($followerUsers as $person): ?>
                                <a class="community-person" href="profile.php?id=<?php echo (int)$person['userid']; ?>">
                                    <span class="community-person-avatar">
                                        <?php if (!empty($person['userimage'])): ?>
                                            <img src="<?php echo htmlspecialchars($person['userimage'], ENT_QUOTES); ?>" alt="">
                                        <?php else: ?>
                                            <img src="../media/default-user.png" alt="">
                                        <?php endif; ?>
                                    </span>
                                    <span class="community-person-info">
                                        <span class="community-person-name">
                                            <?php echo htmlspecialchars(trim($person['name'] . ' ' . $person['surname']), ENT_QUOTES); ?>
                                        </span>
                                        <?php if (!empty($person['username'])): ?>
                                            <span class="community-person-username">@<?php echo htmlspecialchars($person['username'], ENT_QUOTES); ?></span>
                                        <?php endif; ?>
                                    </span>
                                </a>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </details>
            </div>
            <div class="community-card">
                <details class="community-people">
                    <summary>
                        <span>
                            <span class="community-value"><?php echo htmlspecialchars($followingCount, ENT_QUOTES); ?></span>
                            <span class="community-label">Following</span>
                        </span>
                        <span class="community-open-icon" aria-hidden="true"></span>
                    </summary>

                    <div class="community-people-list">
                        <?php if (count($followingUsers) === 0): ?>
                            <p class="community-empty">Not following anyone yet.</p>
                        <?php else: ?>
                            <?php foreach ($followingUsers as $person): ?>
                                <a class="community-person" href="profile.php?id=<?php echo (int)$person['userid']; ?>">
                                    <span class="community-person-avatar">
                                        <?php if (!empty($person['userimage'])): ?>
                                            <img src="<?php echo htmlspecialchars($person['userimage'], ENT_QUOTES); ?>" alt="">
                                        <?php else: ?>
                                            <img src="../media/default-user.png" alt="">
                                        <?php endif; ?>
                                    </span>
                                    <span class="community-person-info">
                                        <span class="community-person-name">
                                            <?php echo htmlspecialchars(trim($person['name'] . ' ' . $person['surname']), ENT_QUOTES); ?>
                                        </span>
                                        <?php if (!empty($person['username'])): ?>
                                            <span class="community-person-username">@<?php echo htmlspecialchars($person['username'], ENT_QUOTES); ?></span>
                                        <?php endif; ?>
                                    </span>
                                </a>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </details>
            </div>
        </div>
    </section>

    <!--sports practiced-->
    <section class="profile-section">
        <div class="sports-header">
            <h2 class="section-title">Sports practiced</h2>
            <?php if ($isOwnProfile): ?>
                <button type="button" class="btn" id="open-manage-sports">Add sport</button>
            <?php endif; ?>
        </div>
        
        <!-- Manage sports dropdown -->
        <div id="manage-sports-dropdown" class="manage-sports-dropdown" style="display: none;">
            <div class="manage-sports-content">
                <div class="manage-sports-title">
                    <h3>Choose your sports</h3>
                    <button type="button" class="manage-sports-close" id="close-manage-sports">Close</button>
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

        <div class="charts-grid">
            <div class="analysis-card chart-card">
                <div class="analysis-label">Sport distribution in activities</div>
                <div class="chart-canvas-wrap">
                    <canvas id="sportDistributionChart"></canvas>
                </div>
            </div>
            <div class="analysis-card chart-card">
                <div class="analysis-label">Weekly distance (km)</div>
                <div class="chart-canvas-wrap">
                    <canvas id="weeklyDistanceChart"></canvas>
                </div>
            </div>
        </div>
    </section>

    <!--recent activities-->
    <section class="profile-section">
        <h2 class="section-title">Recent activities</h2>
        <div class="recent-activities">
            <?php if (!$isOwnProfile && !$isFollowing): ?>
                <p class="sports-empty">Follow this user to see their recent activities.</p>
            <?php elseif (count($recentActivities) === 0): ?>
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
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var sportLabels = <?php echo json_encode($sportDistributionLabels, JSON_UNESCAPED_UNICODE); ?>;
    var sportData = <?php echo json_encode($sportDistributionData, JSON_UNESCAPED_UNICODE); ?>;
    var weeklyDistanceLabels = <?php echo json_encode($weeklyDistanceLabels, JSON_UNESCAPED_UNICODE); ?>;
    var weeklyDistanceData = <?php echo json_encode($weeklyDistanceData, JSON_UNESCAPED_UNICODE); ?>;

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

    if (typeof Chart !== 'undefined') {
        var donutCanvas = document.getElementById('sportDistributionChart');
        var lineCanvas = document.getElementById('weeklyDistanceChart');

        var donutHasData = sportData.length > 0;
        var donutDatasetData = donutHasData ? sportData : [1];
        var donutDatasetLabels = donutHasData ? sportLabels : ['No activities'];
        var donutColors = ['#00eeff', '#36a2eb', '#4bc0c0', '#2ecc71', '#f1c40f', '#ff9f40', '#ff6384', '#9966ff'];

        if (donutCanvas) {
            new Chart(donutCanvas, {
                type: 'doughnut',
                data: {
                    labels: donutDatasetLabels,
                    datasets: [{
                        data: donutDatasetData,
                        backgroundColor: donutHasData
                            ? donutDatasetLabels.map(function (_, idx) {
                                return donutColors[idx % donutColors.length];
                            })
                            : ['rgba(255, 255, 255, 0.25)'],
                        borderColor: 'rgba(9, 18, 28, 1)',
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            labels: {
                                color: '#ffffff'
                            }
                        }
                    }
                }
            });
        }

        if (lineCanvas) {
            new Chart(lineCanvas, {
                type: 'line',
                data: {
                    labels: weeklyDistanceLabels,
                    datasets: [{
                        label: 'Distance (km)',
                        data: weeklyDistanceData,
                        borderColor: '#00eeff',
                        backgroundColor: 'rgba(0, 238, 255, 0.22)',
                        pointBackgroundColor: '#00eeff',
                        pointRadius: 3,
                        borderWidth: 2,
                        tension: 0.35,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        x: {
                            ticks: {
                                color: 'rgba(255, 255, 255, 0.85)'
                            },
                            grid: {
                                color: 'rgba(255, 255, 255, 0.08)'
                            }
                        },
                        y: {
                            beginAtZero: true,
                            ticks: {
                                color: 'rgba(255, 255, 255, 0.85)'
                            },
                            grid: {
                                color: 'rgba(255, 255, 255, 0.08)'
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            labels: {
                                color: '#ffffff'
                            }
                        }
                    }
                }
            });
        }
    }
});
</script>


</body>
</html>
