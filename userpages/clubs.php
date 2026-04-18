<?php
//session_start();
require_once __DIR__ . '/../include/dbHandler.php';

$userId = isset($_SESSION['userId']) ? (int) $_SESSION['userId'] : 0;
if ($userId <= 0) {
    header('Location: ../include/loginForm.php');
    exit;
}

$pdo = DBHandler::getPDO();
$flashMessage = $_SESSION['clubs_flash'] ?? null;
unset($_SESSION['clubs_flash']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'create_club') {
            $clubName = trim((string) ($_POST['club_name'] ?? ''));
            $clubDescription = trim((string) ($_POST['club_description'] ?? ''));
            $sportId = (int) ($_POST['sport_id'] ?? 0);

            if ($clubName === '' || $sportId <= 0) {
                throw new RuntimeException('Inserisci un nome e seleziona uno sport per il club.');
            }

            $checkSport = $pdo->prepare('SELECT COUNT(*) FROM Sport WHERE sportid = :sid');
            $checkSport->bindValue(':sid', $sportId, PDO::PARAM_INT);
            $checkSport->execute();

            if ((int) $checkSport->fetchColumn() === 0) {
                throw new RuntimeException('Lo sport selezionato non esiste.');
            }

            $pdo->beginTransaction();

            $insertClub = $pdo->prepare(
                'INSERT INTO Club (sportid, name, description, creationdate) VALUES (:sportid, :name, :description, NOW())'
            );
            $insertClub->bindValue(':sportid', $sportId, PDO::PARAM_INT);
            $insertClub->bindValue(':name', $clubName, PDO::PARAM_STR);
            $insertClub->bindValue(':description', $clubDescription !== '' ? $clubDescription : null, PDO::PARAM_STR);
            $insertClub->execute();

            $clubId = (int) $pdo->lastInsertId();

            $insertMembership = $pdo->prepare(
                'INSERT INTO UserClub (userid, clubid, joindate, admin) VALUES (:uid, :cid, NOW(), 1)'
            );
            $insertMembership->bindValue(':uid', $userId, PDO::PARAM_INT);
            $insertMembership->bindValue(':cid', $clubId, PDO::PARAM_INT);
            $insertMembership->execute();

            $pdo->commit();

            $_SESSION['clubs_flash'] = [
                'type' => 'success',
                'text' => 'Club creato con successo.',
            ];

            header('Location: clubs.php');
            exit;
        }

        if ($action === 'join_club') {
            $clubId = (int) ($_POST['club_id'] ?? 0);

            if ($clubId <= 0) {
                throw new RuntimeException('Club non valido.');
            }

            $checkClub = $pdo->prepare('SELECT COUNT(*) FROM Club WHERE clubid = :cid');
            $checkClub->bindValue(':cid', $clubId, PDO::PARAM_INT);
            $checkClub->execute();

            if ((int) $checkClub->fetchColumn() === 0) {
                throw new RuntimeException('Il club selezionato non esiste.');
            }

            $checkMembership = $pdo->prepare('SELECT COUNT(*) FROM UserClub WHERE userid = :uid AND clubid = :cid');
            $checkMembership->bindValue(':uid', $userId, PDO::PARAM_INT);
            $checkMembership->bindValue(':cid', $clubId, PDO::PARAM_INT);
            $checkMembership->execute();

            if ((int) $checkMembership->fetchColumn() > 0) {
                throw new RuntimeException('Sei già membro di questo club.');
            }

            $insertMembership = $pdo->prepare(
                'INSERT INTO UserClub (userid, clubid, joindate, admin) VALUES (:uid, :cid, NOW(), 0)'
            );
            $insertMembership->bindValue(':uid', $userId, PDO::PARAM_INT);
            $insertMembership->bindValue(':cid', $clubId, PDO::PARAM_INT);
            $insertMembership->execute();

            $_SESSION['clubs_flash'] = [
                'type' => 'success',
                'text' => 'Ti sei unito al club.',
            ];

            header('Location: clubs.php');
            exit;
        }

        throw new RuntimeException('Azione non riconosciuta.');
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        $_SESSION['clubs_flash'] = [
            'type' => 'error',
            'text' => $e->getMessage(),
        ];

        header('Location: clubs.php');
        exit;
    }
}

$searchTerm = trim((string) ($_GET['q'] ?? ''));
$sportFilter = (int) ($_GET['sport'] ?? 0);

$sportsStmt = $pdo->query('SELECT sportid, name FROM Sport ORDER BY name');
$sports = $sportsStmt ? $sportsStmt->fetchAll() : [];

$baseSql = "
    SELECT
        c.clubid,
        c.sportid,
        c.name,
        c.description,
        c.clubimage,
        c.creationdate,
        s.name AS sport_name,
        s.sportimage,
        COALESCE(member_counts.member_count, 0) AS member_count,
        COALESCE(activity_counts.activity_count, 0) AS activity_count,
        activity_counts.last_activity_date,
        uc_user.admin AS is_admin,
        CASE WHEN uc_user.userid IS NULL THEN 0 ELSE 1 END AS is_member,
        creator_user.username AS creator_username
    FROM Club c
    LEFT JOIN Sport s ON s.sportid = c.sportid
    LEFT JOIN (
        SELECT clubid, COUNT(*) AS member_count
        FROM UserClub
        GROUP BY clubid
    ) member_counts ON member_counts.clubid = c.clubid
    LEFT JOIN (
        SELECT uc.clubid, COUNT(a.activityid) AS activity_count, MAX(a.activitydate) AS last_activity_date
        FROM UserClub uc
        LEFT JOIN Activity a ON a.userid = uc.userid
        GROUP BY uc.clubid
    ) activity_counts ON activity_counts.clubid = c.clubid
    LEFT JOIN UserClub uc_user ON uc_user.clubid = c.clubid AND uc_user.userid = :uid
    LEFT JOIN User creator_user ON creator_user.userid = (
        SELECT uc2.userid
        FROM UserClub uc2
        WHERE uc2.clubid = c.clubid AND uc2.admin = 1
        ORDER BY uc2.joindate ASC, uc2.userid ASC
        LIMIT 1
    )
";

$conditions = [];
$params = [':uid' => $userId];

if ($searchTerm !== '') {
    $conditions[] = '(c.name LIKE :search_term OR c.description LIKE :search_term OR s.name LIKE :search_term)';
    $params[':search_term'] = '%' . $searchTerm . '%';
}

if ($sportFilter > 0) {
    $conditions[] = 'c.sportid = :sport_filter';
    $params[':sport_filter'] = $sportFilter;
}

$clubSql = $baseSql;
if (!empty($conditions)) {
    $clubSql .= ' WHERE ' . implode(' AND ', $conditions);
}
$clubSql .= ' ORDER BY c.creationdate DESC, c.name ASC';

$clubStmt = $pdo->prepare($clubSql);
foreach ($params as $placeholder => $value) {
    if ($placeholder === ':uid' || $placeholder === ':sport_filter') {
        $clubStmt->bindValue($placeholder, (int) $value, PDO::PARAM_INT);
    } else {
        $clubStmt->bindValue($placeholder, $value, PDO::PARAM_STR);
    }
}
$clubStmt->execute();
$clubs = $clubStmt->fetchAll();

$clubIds = array_map(static function (array $club): int {
    return (int) $club['clubid'];
}, $clubs);

$recentActivitiesByClub = [];
if (!empty($clubIds)) {
    $placeholders = implode(',', array_fill(0, count($clubIds), '?'));
    $recentSql = "
        SELECT
            uc.clubid,
            a.activityid,
            COALESCE(NULLIF(a.name, ''), s.name) AS activity_name,
            a.activitydate,
            u.username,
            s.name AS sport_name
        FROM UserClub uc
        INNER JOIN Activity a ON a.userid = uc.userid
        INNER JOIN User u ON u.userid = a.userid
        INNER JOIN Sport s ON s.sportid = a.sportid
        WHERE uc.clubid IN ($placeholders)
        ORDER BY a.activitydate DESC
    ";

    $recentStmt = $pdo->prepare($recentSql);
    foreach ($clubIds as $index => $clubId) {
        $recentStmt->bindValue($index + 1, $clubId, PDO::PARAM_INT);
    }
    $recentStmt->execute();

    foreach ($recentStmt->fetchAll() as $row) {
        $clubId = (int) $row['clubid'];
        if (!isset($recentActivitiesByClub[$clubId])) {
            $recentActivitiesByClub[$clubId] = [];
        }

        if (count($recentActivitiesByClub[$clubId]) < 3) {
            $recentActivitiesByClub[$clubId][] = $row;
        }
    }
}

$createdClubs = array_values(array_filter($clubs, static function (array $club): bool {
    return (int) $club['is_admin'] === 1;
}));

$joinedClubs = array_values(array_filter($clubs, static function (array $club): bool {
    return (int) $club['is_member'] === 1 && (int) $club['is_admin'] !== 1;
}));

$totalClubs = count($clubs);
$totalCreated = count($createdClubs);
$totalJoined = count($joinedClubs);
$totalMembersAcrossFilteredClubs = array_reduce($clubs, static function (int $carry, array $club): int {
    return $carry + (int) $club['member_count'];
}, 0);

$formatDateTime = static function (?string $value): string {
    if (empty($value)) {
        return 'N/A';
    }

    try {
        return (new DateTime($value))->format('d M Y, H:i');
    } catch (Throwable $e) {
        return 'N/A';
    }
};

include __DIR__ . '/../include/menu/menuChoice.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clubs - NHS</title>
    <link rel="stylesheet" href="../css/global.css">
    <link rel="stylesheet" href="../css/clubs.css">
    <link rel="stylesheet" href="../css/header-footer.css">
</head>

<body class="clubs-page">

    <main class="clubs-shell">
        <section class="clubs-hero">
            <div class="clubs-hero-copy">
                <p class="clubs-eyebrow">Community hub</p>
                <h1>Discover, join, and create clubs around your sports.</h1>
                <p class="clubs-lead">
                    Build a community, follow the sports you care about, and keep track of the latest member activity in
                    one place.
                </p>
            </div>

            <div class="clubs-stats">
                <div class="clubs-stat-card">
                    <span
                        class="clubs-stat-value"><?php echo htmlspecialchars((string) $totalClubs, ENT_QUOTES); ?></span>
                    <span class="clubs-stat-label">Matching clubs</span>
                </div>
                <div class="clubs-stat-card">
                    <span
                        class="clubs-stat-value"><?php echo htmlspecialchars((string) $totalCreated, ENT_QUOTES); ?></span>
                    <span class="clubs-stat-label">Created by you</span>
                </div>
                <div class="clubs-stat-card">
                    <span
                        class="clubs-stat-value"><?php echo htmlspecialchars((string) $totalJoined, ENT_QUOTES); ?></span>
                    <span class="clubs-stat-label">Joined clubs</span>
                </div>
                <div class="clubs-stat-card">
                    <span
                        class="clubs-stat-value"><?php echo htmlspecialchars((string) $totalMembersAcrossFilteredClubs, ENT_QUOTES); ?></span>
                    <span class="clubs-stat-label">Members in view</span>
                </div>
            </div>
        </section>

        <?php if (!empty($flashMessage)): ?>
            <div
                class="<?php echo $flashMessage['type'] === 'success' ? 'success-message' : 'error-message'; ?> clubs-flash">
                <?php echo htmlspecialchars((string) $flashMessage['text'], ENT_QUOTES); ?>
            </div>
        <?php endif; ?>

        <section class="clubs-grid-panel">
            <article class="clubs-panel clubs-search-panel">
                <h2 class="section-title">Find clubs</h2>
                <form method="get" action="clubs.php" class="clubs-search-form">
                    <div class="clubs-form-row">
                        <label for="club-search">Search by name, description, or sport</label>
                        <input type="text" id="club-search" name="q"
                            value="<?php echo htmlspecialchars($searchTerm, ENT_QUOTES); ?>" placeholder="Search clubs">
                    </div>

                    <div class="clubs-form-row">
                        <label for="club-sport">Sport filter</label>
                        <select id="club-sport" name="sport">
                            <option value="0">All sports</option>
                            <?php foreach ($sports as $sport): ?>
                                <option value="<?php echo (int) $sport['sportid']; ?>" <?php echo $sportFilter === (int) $sport['sportid'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($sport['name'], ENT_QUOTES); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="clubs-form-actions">
                        <button type="submit" class="btn">Search</button>
                        <a href="clubs.php" class="btn btn-secondary">Reset</a>
                    </div>
                </form>
            </article>

            <article class="clubs-panel clubs-create-panel">
                <h2 class="section-title">Create a club</h2>
                <p class="clubs-panel-text">Set the topic, give it a clear name, and add a short description so people
                    know what to expect.</p>

                <form method="post" action="clubs.php" class="clubs-create-form">
                    <input type="hidden" name="action" value="create_club">

                    <div class="clubs-form-row">
                        <label for="club-name">Club name</label>
                        <input type="text" id="club-name" name="club_name" maxlength="100"
                            placeholder="Morning Runners">
                    </div>

                    <div class="clubs-form-row">
                        <label for="club-sport-create">Sport</label>
                        <select id="club-sport-create" name="sport_id" required>
                            <option value="">Choose a sport</option>
                            <?php foreach ($sports as $sport): ?>
                                <option value="<?php echo (int) $sport['sportid']; ?>">
                                    <?php echo htmlspecialchars($sport['name'], ENT_QUOTES); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="clubs-form-row">
                        <label for="club-description">Description</label>
                        <textarea id="club-description" name="club_description" rows="4" maxlength="1000"
                            placeholder="Tell people what your club is about"></textarea>
                    </div>

                    <div class="clubs-form-actions">
                        <button type="submit" class="btn">Create club</button>
                    </div>
                </form>
            </article>
        </section>

        <section class="clubs-section">
            <div class="clubs-section-header">
                <h2 class="section-title">Your clubs</h2>
                <span class="clubs-section-note">Created and joined clubs are shown separately.</span>
            </div>

            <div class="clubs-subsection">
                <div class="clubs-subsection-header">
                    <h3>Created by you</h3>
                    <span><?php echo htmlspecialchars((string) $totalCreated, ENT_QUOTES); ?></span>
                </div>

                <?php if (empty($createdClubs)): ?>
                    <p class="clubs-empty">You have not created any clubs yet.</p>
                <?php else: ?>
                    <div class="clubs-card-grid">
                        <?php foreach ($createdClubs as $club): ?>
                            <?php $recentActivities = $recentActivitiesByClub[(int) $club['clubid']] ?? []; ?>
                            <article class="club-card club-card-owner">
                                <div class="club-card-media">
                                    <?php if (!empty($club['clubimage'])): ?>
                                        <img src="../<?php echo htmlspecialchars($club['clubimage'], ENT_QUOTES); ?>"
                                            alt="<?php echo htmlspecialchars($club['name'], ENT_QUOTES); ?> image">
                                    <?php elseif (!empty($club['sportimage'])): ?>
                                        <img src="../<?php echo htmlspecialchars($club['sportimage'], ENT_QUOTES); ?>"
                                            alt="<?php echo htmlspecialchars($club['sport_name'], ENT_QUOTES); ?> icon">
                                    <?php else: ?>
                                        <div class="club-card-fallback">
                                            <?php echo htmlspecialchars(substr((string) $club['name'], 0, 1), ENT_QUOTES); ?></div>
                                    <?php endif; ?>
                                </div>

                                <div class="club-card-body">
                                    <div class="club-card-topline">
                                        <span class="club-badge club-badge-owner">Owner</span>
                                        <span
                                            class="club-badge club-badge-muted"><?php echo htmlspecialchars($club['sport_name'] ?? 'Sport', ENT_QUOTES); ?></span>
                                    </div>

                                    <h3><?php echo htmlspecialchars($club['name'], ENT_QUOTES); ?></h3>
                                    <p class="club-description">
                                        <?php echo nl2br(htmlspecialchars($club['description'] ?? '', ENT_QUOTES)); ?></p>

                                    <div class="club-meta-grid">
                                        <div><span>Members</span><strong><?php echo (int) $club['member_count']; ?></strong>
                                        </div>
                                        <div>
                                            <span>Activities</span><strong><?php echo (int) $club['activity_count']; ?></strong>
                                        </div>
                                        <div>
                                            <span>Created</span><strong><?php echo htmlspecialchars($formatDateTime($club['creationdate']), ENT_QUOTES); ?></strong>
                                        </div>
                                        <div>
                                            <span>By</span><strong><?php echo htmlspecialchars($club['creator_username'] ?? 'You', ENT_QUOTES); ?></strong>
                                        </div>
                                    </div>

                                    <div class="club-activity-block">
                                        <h4>Recent member activity</h4>
                                        <?php if (empty($recentActivities)): ?>
                                            <p class="clubs-empty clubs-empty-small">No activity yet for this club.</p>
                                        <?php else: ?>
                                            <ul class="club-activity-list">
                                                <?php foreach ($recentActivities as $activity): ?>
                                                    <li>
                                                        <div>
                                                            <strong><?php echo htmlspecialchars($activity['activity_name'], ENT_QUOTES); ?></strong>
                                                            <span><?php echo htmlspecialchars($activity['username'], ENT_QUOTES); ?></span>
                                                        </div>
                                                        <time
                                                            datetime="<?php echo htmlspecialchars((string) $activity['activitydate'], ENT_QUOTES); ?>"><?php echo htmlspecialchars($formatDateTime($activity['activitydate']), ENT_QUOTES); ?></time>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="clubs-subsection">
                <div class="clubs-subsection-header">
                    <h3>Joined clubs</h3>
                    <span><?php echo htmlspecialchars((string) $totalJoined, ENT_QUOTES); ?></span>
                </div>

                <?php if (empty($joinedClubs)): ?>
                    <p class="clubs-empty">You have not joined any clubs yet.</p>
                <?php else: ?>
                    <div class="clubs-card-grid">
                        <?php foreach ($joinedClubs as $club): ?>
                            <?php $recentActivities = $recentActivitiesByClub[(int) $club['clubid']] ?? []; ?>
                            <article class="club-card">
                                <div class="club-card-media">
                                    <?php if (!empty($club['clubimage'])): ?>
                                        <img src="../<?php echo htmlspecialchars($club['clubimage'], ENT_QUOTES); ?>"
                                            alt="<?php echo htmlspecialchars($club['name'], ENT_QUOTES); ?> image">
                                    <?php elseif (!empty($club['sportimage'])): ?>
                                        <img src="../<?php echo htmlspecialchars($club['sportimage'], ENT_QUOTES); ?>"
                                            alt="<?php echo htmlspecialchars($club['sport_name'], ENT_QUOTES); ?> icon">
                                    <?php else: ?>
                                        <div class="club-card-fallback">
                                            <?php echo htmlspecialchars(substr((string) $club['name'], 0, 1), ENT_QUOTES); ?></div>
                                    <?php endif; ?>
                                </div>

                                <div class="club-card-body">
                                    <div class="club-card-topline">
                                        <span class="club-badge club-badge-join">Member</span>
                                        <span
                                            class="club-badge club-badge-muted"><?php echo htmlspecialchars($club['sport_name'] ?? 'Sport', ENT_QUOTES); ?></span>
                                    </div>

                                    <h3><?php echo htmlspecialchars($club['name'], ENT_QUOTES); ?></h3>
                                    <p class="club-description">
                                        <?php echo nl2br(htmlspecialchars($club['description'] ?? '', ENT_QUOTES)); ?></p>

                                    <div class="club-meta-grid">
                                        <div><span>Members</span><strong><?php echo (int) $club['member_count']; ?></strong>
                                        </div>
                                        <div>
                                            <span>Activities</span><strong><?php echo (int) $club['activity_count']; ?></strong>
                                        </div>
                                        <div>
                                            <span>Created</span><strong><?php echo htmlspecialchars($formatDateTime($club['creationdate']), ENT_QUOTES); ?></strong>
                                        </div>
                                        <div>
                                            <span>By</span><strong><?php echo htmlspecialchars($club['creator_username'] ?? 'Community', ENT_QUOTES); ?></strong>
                                        </div>
                                    </div>

                                    <div class="club-activity-block">
                                        <h4>Recent member activity</h4>
                                        <?php if (empty($recentActivities)): ?>
                                            <p class="clubs-empty clubs-empty-small">No activity yet for this club.</p>
                                        <?php else: ?>
                                            <ul class="club-activity-list">
                                                <?php foreach ($recentActivities as $activity): ?>
                                                    <li>
                                                        <div>
                                                            <strong><?php echo htmlspecialchars($activity['activity_name'], ENT_QUOTES); ?></strong>
                                                            <span><?php echo htmlspecialchars($activity['username'], ENT_QUOTES); ?></span>
                                                        </div>
                                                        <time
                                                            datetime="<?php echo htmlspecialchars((string) $activity['activitydate'], ENT_QUOTES); ?>"><?php echo htmlspecialchars($formatDateTime($activity['activitydate']), ENT_QUOTES); ?></time>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <section class="clubs-section">
            <div class="clubs-section-header">
                <h2 class="section-title">Discover clubs</h2>
                <span class="clubs-section-note">Results reflect your current search and sport filter.</span>
            </div>

            <?php if (empty($clubs)): ?>
                <p class="clubs-empty">No clubs match the current filters yet. Create the first one or clear the search.</p>
            <?php else: ?>
                <div class="clubs-card-grid clubs-browse-grid">
                    <?php foreach ($clubs as $club): ?>
                        <?php $recentActivities = $recentActivitiesByClub[(int) $club['clubid']] ?? []; ?>
                        <article class="club-card">
                            <div class="club-card-media">
                                <?php if (!empty($club['clubimage'])): ?>
                                    <img src="../<?php echo htmlspecialchars($club['clubimage'], ENT_QUOTES); ?>"
                                        alt="<?php echo htmlspecialchars($club['name'], ENT_QUOTES); ?> image">
                                <?php elseif (!empty($club['sportimage'])): ?>
                                    <img src="../<?php echo htmlspecialchars($club['sportimage'], ENT_QUOTES); ?>"
                                        alt="<?php echo htmlspecialchars($club['sport_name'], ENT_QUOTES); ?> icon">
                                <?php else: ?>
                                    <div class="club-card-fallback">
                                        <?php echo htmlspecialchars(substr((string) $club['name'], 0, 1), ENT_QUOTES); ?></div>
                                <?php endif; ?>
                            </div>

                            <div class="club-card-body">
                                <div class="club-card-topline">
                                    <span
                                        class="club-badge"><?php echo htmlspecialchars($club['sport_name'] ?? 'Club', ENT_QUOTES); ?></span>
                                    <?php if ((int) $club['is_admin'] === 1): ?>
                                        <span class="club-badge club-badge-owner">Owner</span>
                                    <?php elseif ((int) $club['is_member'] === 1): ?>
                                        <span class="club-badge club-badge-join">Member</span>
                                    <?php else: ?>
                                        <span class="club-badge club-badge-muted">Open</span>
                                    <?php endif; ?>
                                </div>

                                <h3><?php echo htmlspecialchars($club['name'], ENT_QUOTES); ?></h3>
                                <p class="club-description">
                                    <?php echo nl2br(htmlspecialchars($club['description'] ?? '', ENT_QUOTES)); ?></p>

                                <div class="club-meta-grid">
                                    <div><span>Members</span><strong><?php echo (int) $club['member_count']; ?></strong></div>
                                    <div><span>Activities</span><strong><?php echo (int) $club['activity_count']; ?></strong>
                                    </div>
                                    <div>
                                        <span>Created</span><strong><?php echo htmlspecialchars($formatDateTime($club['creationdate']), ENT_QUOTES); ?></strong>
                                    </div>
                                    <div>
                                        <span>By</span><strong><?php echo htmlspecialchars($club['creator_username'] ?? 'Community', ENT_QUOTES); ?></strong>
                                    </div>
                                </div>

                                <div class="club-activity-block">
                                    <h4>Recent member activity</h4>
                                    <?php if (empty($recentActivities)): ?>
                                        <p class="clubs-empty clubs-empty-small">No activity yet for this club.</p>
                                    <?php else: ?>
                                        <ul class="club-activity-list">
                                            <?php foreach ($recentActivities as $activity): ?>
                                                <li>
                                                    <div>
                                                        <strong><?php echo htmlspecialchars($activity['activity_name'], ENT_QUOTES); ?></strong>
                                                        <span><?php echo htmlspecialchars($activity['username'], ENT_QUOTES); ?></span>
                                                    </div>
                                                    <time
                                                        datetime="<?php echo htmlspecialchars((string) $activity['activitydate'], ENT_QUOTES); ?>"><?php echo htmlspecialchars($formatDateTime($activity['activitydate']), ENT_QUOTES); ?></time>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php endif; ?>
                                </div>

                                <div class="club-card-actions">
                                    <?php if ((int) $club['is_member'] === 1): ?>
                                        <span class="club-card-state">Already joined</span>
                                    <?php else: ?>
                                        <form method="post" action="clubs.php">
                                            <input type="hidden" name="action" value="join_club">
                                            <input type="hidden" name="club_id" value="<?php echo (int) $club['clubid']; ?>">
                                            <button type="submit" class="btn">Join club</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </main>


</body>

</html>