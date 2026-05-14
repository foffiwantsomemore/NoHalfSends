<?php
$userId = isset($_SESSION['userId']) ? (int) $_SESSION['userId'] : 0;

$pdo = DBHandler::getPDO();
$flashMessage = '';

// Create the base activity and its sport-specific row in one transaction.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sportId = (int)$_POST['sport_id'];
    $name = trim($_POST['name']);
    $date = $_POST['activitydate'];
    $duration = (int)$_POST['duration'];
    $description = trim($_POST['description']);
    $avgHR = !empty($_POST['avgheartrate']) ? (int)$_POST['avgheartrate'] : null;
    $maxHR = !empty($_POST['maxheartrate']) ? (int)$_POST['maxheartrate'] : null;
    $calories = !empty($_POST['calories']) ? (int)$_POST['calories'] : null;

    if ($sportId > 0 && !empty($name) && !empty($date) && $duration > 0) {
        // Find the sport name to know which sub-table to insert into
        $sth = $pdo->prepare("SELECT name FROM Sport WHERE sportid = ?");
        $sth->execute([$sportId]);
        $sportName = $sth->fetchColumn();

        if ($sportName) {
            try {
                $pdo->beginTransaction();

                // Insert the common data
                $sqlAct = "INSERT INTO Activity (userid, sportid, name, activitydate, duration, avgheartrate, maxheartrate, calories, description) 
                           VALUES (:uid, :sid, :name, :date, :dur, :ahr, :mhr, :cal, :desc)";
                $stmt = $pdo->prepare($sqlAct);
                $stmt->execute([
                    ':uid' => $userId,
                    ':sid' => $sportId,
                    ':name' => $name,
                    ':date' => $date,
                    ':dur' => $duration,
                    ':ahr' => $avgHR,
                    ':mhr' => $maxHR,
                    ':cal' => $calories,
                    ':desc' => $description
                ]);

                $activityId = $pdo->lastInsertId();

                // Insert metrics that only exist for the selected sport
                if ($sportName === 'Run') {
                    $dist = !empty($_POST['distance']) ? (float)$_POST['distance'] : null;
                    $pace = !empty($_POST['pace']) ? (float)$_POST['pace'] : null;
                    $cad = !empty($_POST['cadence']) ? (int)$_POST['cadence'] : null;
                    $stmt = $pdo->prepare("INSERT INTO Run (activityid, distance, pace, cadence) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$activityId, $dist, $pace, $cad]);
                } elseif ($sportName === 'Cycling') {
                    $dist    = !empty($_POST['distance'])  ? (float)$_POST['distance']  : null;
                    $elev    = !empty($_POST['elevation']) ? (int)$_POST['elevation']   : null;
                    $avgs    = !empty($_POST['avgspeed'])  ? (float)$_POST['avgspeed']  : null;
                    $maxs    = !empty($_POST['maxspeed'])  ? (float)$_POST['maxspeed']  : null;
                    $cad     = !empty($_POST['cadence'])   ? (int)$_POST['cadence']     : null;
                    $avgp    = !empty($_POST['avgpower'])  ? (int)$_POST['avgpower']    : null;
                    $maxp    = !empty($_POST['maxpower'])  ? (int)$_POST['maxpower']    : null;
                    $type    = !empty($_POST['cycling_type']) ? trim($_POST['cycling_type']) : null;
                    $stmt = $pdo->prepare("INSERT INTO Cycling (activityid, distance, elevation, avgspeed, maxspeed, cadence, avgpower, maxpower, type) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$activityId, $dist, $elev, $avgs, $maxs, $cad, $avgp, $maxp, $type]);
                } elseif ($sportName === 'Excursion') {
                    $dist = !empty($_POST['distance']) ? (float)$_POST['distance'] : null;
                    $elev = !empty($_POST['elevation']) ? (int)$_POST['elevation'] : null;
                    $pace = !empty($_POST['pace']) ? (float)$_POST['pace'] : null;
                    $stmt = $pdo->prepare("INSERT INTO Excursion (activityid, distance, elevation, pace) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$activityId, $dist, $elev, $pace]);
                } elseif ($sportName === 'Gym') {
                    $type = !empty($_POST['type']) ? trim($_POST['type']) : null;
                    $stmt = $pdo->prepare("INSERT INTO Gym (activityid, type) VALUES (?, ?)");
                    $stmt->execute([$activityId, $type]);
                } elseif ($sportName === 'Ski') {
                    $dist = !empty($_POST['distance']) ? (float)$_POST['distance'] : null;
                    $elev = !empty($_POST['elevation']) ? (int)$_POST['elevation'] : null;
                    $avgs = !empty($_POST['avgspeed']) ? (float)$_POST['avgspeed'] : null;
                    $stmt = $pdo->prepare("INSERT INTO Ski (activityid, distance, elevation, avgspeed) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$activityId, $dist, $elev, $avgs]);
                } elseif ($sportName === 'Swimming') {
                    $dist = !empty($_POST['distance']) ? (float)$_POST['distance'] : null;
                    $type = !empty($_POST['type']) ? trim($_POST['type']) : null;
                    $pace = !empty($_POST['pace']) ? (float)$_POST['pace'] : null;
                    $stmt = $pdo->prepare("INSERT INTO Swimming (activityid, distance, type, pace) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$activityId, $dist, $type, $pace]);
                }

                // Upload photos after activity is created
                $uploadDir = __DIR__ . '/../images/activities/';
                $uploadError = '';
                if (!is_dir($uploadDir)) {
                    if (!mkdir($uploadDir, 0777, true)) {
                        $uploadError .= "Failed to create directory. ";
                    }
                }
                
                if (empty($uploadError) && !empty($_FILES['photos']['name'][0])) {
                    foreach ($_FILES['photos']['tmp_name'] as $i => $tmpName) {
                        if ($_FILES['photos']['error'][$i] !== UPLOAD_ERR_OK) {
                            $uploadError .= "Error code " . $_FILES['photos']['error'][$i] . " for photo " . ($i+1) . ". ";
                            continue;
                        }
                        $allowed = ['image/jpeg','image/png','image/gif','image/webp'];
                        $mime = $_FILES['photos']['type'][$i];
                        if (!in_array($mime, $allowed)) {
                            $uploadError .= "Invalid mime $mime for photo " . ($i+1) . ". ";
                            continue;
                        }
                        if ($_FILES['photos']['size'][$i] > 8*1024*1024) {
                            $uploadError .= "Size too large for photo " . ($i+1) . ". ";
                            continue;
                        }
                        $ext = strtolower(pathinfo($_FILES['photos']['name'][$i], PATHINFO_EXTENSION));
                        $filename = 'act_'.$activityId.'_'.time().'_'.$i.'.'.$ext;
                        if (move_uploaded_file($tmpName, $uploadDir.$filename)) {
                            $pdo->prepare("INSERT INTO ActivityPhoto (activityid, url) VALUES (?,?)")
                                ->execute([$activityId, '../images/activities/'.$filename]);
                        } else {
                            $uploadError .= "Failed to move file " . ($i+1) . ". ";
                        }
                    }
                }

                if (!empty($uploadError)) {
                    $pdo->rollBack();
                    $flashMessage = 'Activity created but photo upload failed: ' . $uploadError;
                } else {
                    $pdo->commit();
                    header('Location: feed.php');
                    exit;
                }
            } catch (Exception $e) {
                $pdo->rollBack();
                $flashMessage = 'Error creating activity: ' . $e->getMessage();
            }
        } else {
            $flashMessage = 'Invalid sport selected.';
        }
    } else {
        $flashMessage = 'Please fill out all required fields.';
    }
}

$sports = $pdo->query("SELECT sportid, name FROM Sport ORDER BY name")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Activity - NHS</title>
    <link rel="stylesheet" href="../css/global.css?v=3">
    <link rel="stylesheet" href="../css/header-footer.css">
</head>
<body>

<div class="form-container">
    <h2>Log a New Activity</h2>
    
    <?php if ($flashMessage): ?>
        <div class="form-error-message">
            <?php echo htmlspecialchars($flashMessage); ?>
        </div>
    <?php endif; ?>

    <form method="post" action="newactivity.php" enctype="multipart/form-data">
        <div class="activity-form-panel">
        <div class="form-two-col">
            <div class="form-group">
                <label for="name">Activity Title *</label>
                <input type="text" id="name" name="name" required placeholder="Morning Run, Evening Ride…">
            </div>
            <div class="form-group">
                <label for="sport_id">Sport *</label>
                <select id="sport_id" name="sport_id" required onchange="showSportFields()">
                    <option value="">Select a sport…</option>
                    <?php foreach ($sports as $sport): ?>
                        <option value="<?php echo $sport['sportid']; ?>" data-name="<?php echo htmlspecialchars($sport['name']); ?>">
                            <?php echo htmlspecialchars($sport['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="form-two-col">
            <div class="form-group">
                <label for="activitydate">Date and Time *</label>
                <input type="datetime-local" id="activitydate" name="activitydate" required value="<?php echo date('Y-m-d\TH:i'); ?>">
            </div>
            <div class="form-group">
                <label for="duration">Duration (minutes) *</label>
                <input type="number" id="duration" name="duration" required min="1" placeholder="e.g. 45">
            </div>
        </div>
        </div>

        <!-- Sport Specific Fields -->
        <div id="sport-fields-container" class="sport-specific-fields">
            <p class="sport-fields-title" id="sport-fields-label">Sport details</p>
            <div class="sport-fields-grid">
                <div class="sport-field form-group" id="f-distance">
                    <label>Distance (km)</label>
                    <input type="number" step="0.01" name="distance" placeholder="0.00">
                </div>
                <div class="sport-field form-group" id="f-elevation">
                    <label>Elevation Gain (m)</label>
                    <input type="number" name="elevation" placeholder="0">
                </div>
                <div class="sport-field form-group" id="f-pace">
                    <label>Pace (min/km)</label>
                    <input type="number" step="0.01" name="pace" placeholder="0.00">
                </div>
                <div class="sport-field form-group" id="f-cadence">
                    <label>Cadence (rpm/spm)</label>
                    <input type="number" name="cadence" placeholder="0">
                </div>
                <div class="sport-field form-group" id="f-avgspeed">
                    <label>Avg Speed (km/h)</label>
                    <input type="number" step="0.01" name="avgspeed" placeholder="0.00">
                </div>
                <div class="sport-field form-group" id="f-maxspeed">
                    <label>Max Speed (km/h)</label>
                    <input type="number" step="0.01" name="maxspeed" placeholder="0.00">
                </div>
                <div class="sport-field form-group" id="f-avgpower">
                    <label>Avg Power (W)</label>
                    <input type="number" name="avgpower" placeholder="0">
                </div>
                <div class="sport-field form-group" id="f-maxpower">
                    <label>Max Power (W)</label>
                    <input type="number" name="maxpower" placeholder="0">
                </div>
                <div class="sport-field form-group" id="f-type">
                    <label>Type / Style</label>
                    <input type="text" name="type" placeholder="Freestyle, Legs…">
                </div>
                <div class="sport-field form-group" id="f-cycling-type">
                    <label>Bike Type</label>
                    <select name="cycling_type">
                        <option value="">Select…</option>
                        <option value="road">Road</option>
                        <option value="mountain bike">Mountain Bike</option>
                        <option value="gravel">Gravel</option>
                    </select>
                </div>
            </div>
        </div>

        <div class="form-group">
            <label for="description">Description (optional)</label>
            <textarea id="description" name="description" rows="3" placeholder="How did it go? Share your experience…"></textarea>
        </div>

        <div class="form-three-col">
            <div class="form-group">
                <label for="avgheartrate">Avg Heart Rate (bpm)</label>
                <input type="number" id="avgheartrate" name="avgheartrate" placeholder="—">
            </div>
            <div class="form-group">
                <label for="maxheartrate">Max Heart Rate (bpm)</label>
                <input type="number" id="maxheartrate" name="maxheartrate" placeholder="—">
            </div>
            <div class="form-group">
                <label for="calories">Calories (kcal)</label>
                <input type="number" id="calories" name="calories" placeholder="—">
            </div>
        </div>

        <hr class="form-divider">
        <p class="form-section-label">Photos (optional)</p>
        <label class="photo-upload-zone" id="photo-upload-zone">
            <input type="file" name="photos[]" id="photo-input" accept="image/*" multiple>
            <span id="photo-upload-label">Click to add photos (JPG, PNG, WEBP - max 8 MB each)</span>
        </label>

        <button type="submit" class="btn new-activity-submit">Save Activity</button>
    </form>
</div>

<script>
    // Show only the fields that match the selected sport.
    function showSportFields() {
        const select = document.getElementById('sport_id');
        const sportName = select.options[select.selectedIndex].getAttribute('data-name');
        const container = document.getElementById('sport-fields-container');

        // Hide and reset ALL sport-specific fields
        document.querySelectorAll('.sport-field').forEach(el => {
            el.style.display = 'none';
            el.querySelectorAll('input, select, textarea').forEach(inp => {
                if (inp.tagName === 'SELECT') {
                    inp.selectedIndex = 0;
                } else {
                    inp.value = '';
                }
            });
        });

        if (!sportName) {
            container.style.display = 'none';
            return;
        }

        container.style.display = 'block';

        function show(id) {
            const el = document.getElementById(id);
            if (el) el.style.display = 'block';
        }

        if (sportName === 'Run') {
            show('f-distance');
            show('f-pace');
            show('f-cadence');
        } else if (sportName === 'Cycling') {
            show('f-distance');
            show('f-elevation');
            show('f-avgspeed');
            show('f-maxspeed');
            show('f-cadence');
            show('f-avgpower');
            show('f-maxpower');
            show('f-cycling-type');
        } else if (sportName === 'Excursion') {
            show('f-distance');
            show('f-elevation');
            show('f-pace');
        } else if (sportName === 'Ski') {
            show('f-distance');
            show('f-elevation');
            show('f-avgspeed');
        } else if (sportName === 'Swimming') {
            show('f-distance');
            show('f-pace');
            show('f-type');
        } else if (sportName === 'Gym') {
            show('f-type');
        }
    }
</script>

</body>
</html>
