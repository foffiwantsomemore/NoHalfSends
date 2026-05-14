<?php
$userId = isset($_SESSION['userId']) ? (int)$_SESSION['userId'] : 0;

$pdo = DBHandler::getPDO();
$activityId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($activityId <= 0) { header('Location: feed.php'); exit; }

// Load activity and verify ownership in the same query.
$sth = $pdo->prepare("
    SELECT a.*, s.name AS sport_name
    FROM Activity a
    JOIN Sport s ON s.sportid = a.sportid
    WHERE a.activityid = :aid AND a.userid = :uid
");
$sth->execute([':aid' => $activityId, ':uid' => $userId]);
$act = $sth->fetch();
if (!$act) { header('Location: feed.php'); exit; }

// Map sport names to their specialization tables.
$sportName = $act['sport_name'];
$sub = [];
$sportTableMap = [
    'Run'       => 'Run',
    'Cycling'   => 'Cycling',
    'Excursion' => 'Excursion',
    'Gym'       => 'Gym',
    'Ski'       => 'Ski',
    'Swimming'  => 'Swimming',
];
$subTable = isset($sportTableMap[$sportName]) ? $sportTableMap[$sportName] : null;
if ($subTable) {
    $sth2 = $pdo->prepare("SELECT * FROM `$subTable` WHERE activityid = ?");
    $sth2->execute([$activityId]);
    $sub = $sth2->fetch() ?: [];
}

$stmtP = $pdo->prepare("SELECT activityphotoid, url FROM ActivityPhoto WHERE activityid = ?");
$stmtP->execute([$activityId]);
$photos = $stmtP->fetchAll();

$sports = $pdo->query("SELECT sportid, name FROM Sport ORDER BY name")->fetchAll();

$flashMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['form_action'] ?? 'update';

    // Delete a photo
    if ($action === 'delete_photo') {
        $photoId = (int)($_POST['photo_id'] ?? 0);
        $sthPhoto = $pdo->prepare("SELECT url FROM ActivityPhoto WHERE activityphotoid = ? AND activityid = ?");
        $sthPhoto->execute([$photoId, $activityId]);
        $photoRow = $sthPhoto->fetch();
        if ($photoRow) {
            $absPath = __DIR__ . '/../' . ltrim($photoRow['url'], './');
            if (is_file($absPath)) @unlink($absPath);
            $pdo->prepare("DELETE FROM ActivityPhoto WHERE activityphotoid = ?")->execute([$photoId]);
        }
        header("Location: editactivity.php?id=$activityId");
        exit;
    }

    // Upload new photo
    if ($action === 'upload_photo') {
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['photo'];
            $allowed = ['image/jpeg','image/png','image/gif','image/webp'];
            if (in_array($file['type'], $allowed) && $file['size'] <= 8*1024*1024) {
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $uploadDir = __DIR__ . '/../images/activities/';
                if (!is_dir($uploadDir)) {
                    if (!mkdir($uploadDir, 0777, true)) {
                        $flashMessage = "Failed to create directory.";
                    }
                }
                if (empty($flashMessage)) {
                    $filename = 'act_'.$activityId.'_'.time().'.'.$ext;
                    if (move_uploaded_file($file['tmp_name'], $uploadDir.$filename)) {
                        $rel = '../images/activities/'.$filename;
                        $pdo->prepare("INSERT INTO ActivityPhoto (activityid, url) VALUES (?,?)")->execute([$activityId, $rel]);
                        header("Location: editactivity.php?id=$activityId");
                        exit;
                    } else {
                        $flashMessage = "Failed to move uploaded file.";
                    }
                }
            } else {
                $flashMessage = "Invalid file type or size too large. Type: " . $file['type'];
            }
        } else {
            $err = isset($_FILES['photo']) ? $_FILES['photo']['error'] : 'No file received';
            $flashMessage = "Upload error code: " . $err;
        }
    }

    // Main update for activity fields and sport-specific metrics.
    $name        = trim($_POST['name'] ?? '');
    $date        = $_POST['activitydate'] ?? '';
    $duration    = (int)($_POST['duration'] ?? 0);
    $description = trim($_POST['description'] ?? '');
    $avgHR       = !empty($_POST['avgheartrate']) ? (int)$_POST['avgheartrate'] : null;
    $maxHR       = !empty($_POST['maxheartrate']) ? (int)$_POST['maxheartrate'] : null;
    $calories    = !empty($_POST['calories'])      ? (int)$_POST['calories']     : null;

    if (!empty($name) && !empty($date) && $duration > 0) {
        try {
            $pdo->beginTransaction();

            $pdo->prepare("UPDATE Activity SET name=:name, activitydate=:date, duration=:dur,
                avgheartrate=:ahr, maxheartrate=:mhr, calories=:cal, description=:desc
                WHERE activityid=:aid")
                ->execute([':name'=>$name,':date'=>$date,':dur'=>$duration,
                           ':ahr'=>$avgHR,':mhr'=>$maxHR,':cal'=>$calories,
                           ':desc'=>$description,':aid'=>$activityId]);

            if ($subTable) {
                $pdo->prepare("DELETE FROM `$subTable` WHERE activityid=?")->execute([$activityId]);
                if ($sportName === 'Run') {
                    $pdo->prepare("INSERT INTO Run (activityid,distance,pace,cadence) VALUES (?,?,?,?)")
                        ->execute([$activityId,
                            !empty($_POST['distance']) ? (float)$_POST['distance'] : null,
                            !empty($_POST['pace'])     ? (float)$_POST['pace']     : null,
                            !empty($_POST['cadence'])  ? (int)$_POST['cadence']    : null]);
                } elseif ($sportName === 'Cycling') {
                    $pdo->prepare("INSERT INTO Cycling (activityid,distance,elevation,avgspeed,maxspeed,cadence,avgpower,maxpower,type) VALUES (?,?,?,?,?,?,?,?,?)")
                        ->execute([$activityId,
                            !empty($_POST['distance'])     ? (float)$_POST['distance']     : null,
                            !empty($_POST['elevation'])    ? (int)$_POST['elevation']      : null,
                            !empty($_POST['avgspeed'])     ? (float)$_POST['avgspeed']     : null,
                            !empty($_POST['maxspeed'])     ? (float)$_POST['maxspeed']     : null,
                            !empty($_POST['cadence'])      ? (int)$_POST['cadence']        : null,
                            !empty($_POST['avgpower'])     ? (int)$_POST['avgpower']       : null,
                            !empty($_POST['maxpower'])     ? (int)$_POST['maxpower']       : null,
                            !empty($_POST['cycling_type']) ? trim($_POST['cycling_type'])   : null]);
                } elseif ($sportName === 'Excursion') {
                    $pdo->prepare("INSERT INTO Excursion (activityid,distance,elevation,pace) VALUES (?,?,?,?)")
                        ->execute([$activityId,
                            !empty($_POST['distance'])  ? (float)$_POST['distance']  : null,
                            !empty($_POST['elevation']) ? (int)$_POST['elevation']   : null,
                            !empty($_POST['pace'])      ? (float)$_POST['pace']      : null]);
                } elseif ($sportName === 'Gym') {
                    $pdo->prepare("INSERT INTO Gym (activityid,type) VALUES (?,?)")
                        ->execute([$activityId, !empty($_POST['type']) ? trim($_POST['type']) : null]);
                } elseif ($sportName === 'Ski') {
                    $pdo->prepare("INSERT INTO Ski (activityid,distance,elevation,avgspeed) VALUES (?,?,?,?)")
                        ->execute([$activityId,
                            !empty($_POST['distance'])  ? (float)$_POST['distance']  : null,
                            !empty($_POST['elevation']) ? (int)$_POST['elevation']   : null,
                            !empty($_POST['avgspeed'])  ? (float)$_POST['avgspeed']  : null]);
                } elseif ($sportName === 'Swimming') {
                    $pdo->prepare("INSERT INTO Swimming (activityid,distance,type,pace) VALUES (?,?,?,?)")
                        ->execute([$activityId,
                            !empty($_POST['distance']) ? (float)$_POST['distance'] : null,
                            !empty($_POST['type'])     ? trim($_POST['type'])       : null,
                            !empty($_POST['pace'])     ? (float)$_POST['pace']     : null]);
                }
            }
            $pdo->commit();
            header("Location: feed.php");
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $flashMessage = 'Error updating activity: ' . $e->getMessage();
        }
    } else {
        $flashMessage = 'Please fill out all required fields.';
    }

    $sth->execute([':aid' => $activityId, ':uid' => $userId]);
    $act = $sth->fetch();
}

function subVal($sub, $key) {
    return htmlspecialchars(isset($sub[$key]) ? $sub[$key] : '', ENT_QUOTES);
}
$v = function($key) use ($sub) { return subVal($sub, $key); };

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Activity - NHS</title>
    <link rel="stylesheet" href="../css/global.css?v=3">
    <link rel="stylesheet" href="../css/header-footer.css">
</head>
<body>
<?php include __DIR__ . '/../include/menu/menuChoice.php'; ?>

<div class="form-container">
    <h2>Edit Activity</h2>
    <p class="form-subtitle"><?= htmlspecialchars($act['sport_name']) ?> · <?= (new DateTime($act['activitydate']))->format('d M Y') ?></p>

    <?php if ($flashMessage): ?>
        <div class="form-error-message">
            <?= htmlspecialchars($flashMessage) ?>
        </div>
    <?php endif; ?>

    <!-- MAIN EDIT FORM -->
    <form method="post" action="editactivity.php?id=<?= $activityId ?>">
        <input type="hidden" name="form_action" value="update">

        <p class="form-section-label">Basic Info</p>
        <div class="form-row form-row-2">
            <div class="form-group">
                <label>Activity Title *</label>
                <input type="text" name="name" required value="<?= htmlspecialchars($act['name']) ?>" placeholder="Title…">
            </div>
            <div class="form-group">
                <label>Sport</label>
                <input class="input-muted" type="text" value="<?= htmlspecialchars($act['sport_name']) ?>" disabled>
            </div>
        </div>
        <div class="form-row form-row-2">
            <div class="form-group">
                <label>Date and Time *</label>
                <input type="datetime-local" name="activitydate" required
                       value="<?= (new DateTime($act['activitydate']))->format('Y-m-d\TH:i') ?>">
            </div>
            <div class="form-group">
                <label>Duration (minutes) *</label>
                <input type="number" name="duration" required min="1" value="<?= (int)$act['duration'] ?>">
            </div>
        </div>

        <?php if (!empty($subTable)): ?>
        <hr class="form-divider">
        <p class="form-section-label"><?= htmlspecialchars($sportName) ?> Details</p>
        <div class="sport-specific-box">
            <div class="sport-fields-grid">
                <?php if (in_array($sportName, ['Run','Cycling','Excursion','Ski','Swimming'])): ?>
                <div class="form-group">
                    <label>Distance (km<?= $sportName==='Swimming' ? ' / m' : '' ?>)</label>
                    <input type="number" step="0.01" name="distance" value="<?= $v('distance') ?>" placeholder="0.00">
                </div>
                <?php endif; ?>
                <?php if (in_array($sportName, ['Cycling','Excursion','Ski'])): ?>
                <div class="form-group">
                    <label>Elevation Gain (m)</label>
                    <input type="number" name="elevation" value="<?= $v('elevation') ?>" placeholder="0">
                </div>
                <?php endif; ?>
                <?php if (in_array($sportName, ['Run','Excursion','Swimming'])): ?>
                <div class="form-group">
                    <label>Pace (min/km)</label>
                    <input type="number" step="0.01" name="pace" value="<?= $v('pace') ?>" placeholder="0.00">
                </div>
                <?php endif; ?>
                <?php if (in_array($sportName, ['Run','Cycling'])): ?>
                <div class="form-group">
                    <label>Cadence (rpm/spm)</label>
                    <input type="number" name="cadence" value="<?= $v('cadence') ?>" placeholder="0">
                </div>
                <?php endif; ?>
                <?php if (in_array($sportName, ['Cycling','Ski'])): ?>
                <div class="form-group">
                    <label>Avg Speed (km/h)</label>
                    <input type="number" step="0.01" name="avgspeed" value="<?= $v('avgspeed') ?>" placeholder="0.00">
                </div>
                <?php endif; ?>
                <?php if ($sportName === 'Cycling'): ?>
                <div class="form-group">
                    <label>Max Speed (km/h)</label>
                    <input type="number" step="0.01" name="maxspeed" value="<?= $v('maxspeed') ?>" placeholder="0.00">
                </div>
                <div class="form-group">
                    <label>Avg Power (W)</label>
                    <input type="number" name="avgpower" value="<?= $v('avgpower') ?>" placeholder="0">
                </div>
                <div class="form-group">
                    <label>Max Power (W)</label>
                    <input type="number" name="maxpower" value="<?= $v('maxpower') ?>" placeholder="0">
                </div>
                <div class="form-group">
                    <label>Bike Type</label>
                    <select name="cycling_type">
                        <option value="">Select…</option>
                        <?php foreach (['road'=>'Road','mountain bike'=>'Mountain Bike','gravel'=>'Gravel'] as $val=>$lbl): ?>
                        <option value="<?= $val ?>" <?= ($sub['type']??'')===$val?'selected':'' ?>><?= $lbl ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                <?php if (in_array($sportName, ['Gym','Swimming'])): ?>
                <div class="form-group">
                    <label>Type / Style</label>
                    <input type="text" name="type" value="<?= $v('type') ?>" placeholder="Freestyle, Legs…">
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <hr class="form-divider">
        <p class="form-section-label">Notes & Stats</p>
        <div class="form-group">
            <label>Description</label>
            <textarea name="description" rows="3" placeholder="How did it go?"><?= htmlspecialchars($act['description'] ?? '') ?></textarea>
        </div>
        <div class="form-row form-row-3">
            <div class="form-group">
                <label>Avg Heart Rate (bpm)</label>
                <input type="number" name="avgheartrate" value="<?= htmlspecialchars($act['avgheartrate'] ?? '') ?>" placeholder="—">
            </div>
            <div class="form-group">
                <label>Max Heart Rate (bpm)</label>
                <input type="number" name="maxheartrate" value="<?= htmlspecialchars($act['maxheartrate'] ?? '') ?>" placeholder="—">
            </div>
            <div class="form-group">
                <label>Calories (kcal)</label>
                <input type="number" name="calories" value="<?= htmlspecialchars($act['calories'] ?? '') ?>" placeholder="—">
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn form-primary-action">Save Changes</button>
            <a href="feed.php" class="btn btn-secondary">Cancel</a>
        </div>
    </form>

    <!-- PHOTOS SECTION -->
    <hr class="form-divider">
    <p class="form-section-label">Photos</p>

    <?php if (!empty($photos)): ?>
    <div class="photos-grid">
        <?php foreach ($photos as $photo): ?>
        <div class="photo-thumb">
            <img src="<?= htmlspecialchars($photo['url']) ?>" alt="Activity photo">
            <form method="post" action="editactivity.php?id=<?= $activityId ?>">
                <input type="hidden" name="form_action" value="delete_photo">
                <input type="hidden" name="photo_id" value="<?= (int)$photo['activityphotoid'] ?>">
                <button type="submit" class="photo-delete-btn" title="Remove photo">✕</button>
            </form>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Upload new photo -->
    <form method="post" action="editactivity.php?id=<?= $activityId ?>" enctype="multipart/form-data">
        <input type="hidden" name="form_action" value="upload_photo">
        <label class="upload-zone" id="upload-zone">
            <input type="file" name="photo" accept="image/*" id="photo-input">
            <span id="upload-label">Click to add a photo (JPG, PNG, WEBP - max 8 MB)</span>
        </label>
        <div class="upload-actions">
            <button type="submit" class="btn upload-submit-btn" id="upload-submit-btn">Upload</button>
        </div>
    </form>
</div>

<script>
const photoInput = document.getElementById('photo-input');
const uploadLabel = document.getElementById('upload-label');
const uploadSubmitBtn = document.getElementById('upload-submit-btn');

photoInput.addEventListener('change', function() {
    if (photoInput.files[0]) {
        uploadLabel.textContent = photoInput.files[0].name;
        uploadSubmitBtn.style.display = 'inline-block';
    }
});
</script>
</body>
</html>
