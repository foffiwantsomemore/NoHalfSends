<?php
$userId = isset($_SESSION['userId']) ? (int) $_SESSION['userId'] : 0;
if ($userId <= 0) {
    header('Location: ../include/loginForm.php');
    exit;
}

$pdo = DBHandler::getPDO();
$flashMessage = '';

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
    <style>
        .form-container {
            width: min(1040px, calc(100% - 2rem));
            margin: 6rem auto 4rem;
            background:
                linear-gradient(145deg, rgba(15,31,45,0.94), rgba(8,14,24,0.9)),
                rgba(9, 18, 28, 0.88);
            padding: 1.35rem;
            border-radius: 18px;
            border: 1px solid rgba(255,255,255,0.12);
            box-shadow: 0 18px 50px rgba(0,0,0,0.28);
            backdrop-filter: blur(16px);
            position: relative;
            overflow: hidden;
        }
        .form-container::before {
            content: "";
            position: absolute;
            inset: 0 0 auto;
            height: 1px;
            background: linear-gradient(90deg, transparent, rgba(0,238,255,0.45), transparent);
            pointer-events: none;
        }
        .form-container h2 {
            margin: 0 0 1.25rem;
            font-size: clamp(1.7rem, 2.6vw, 2.35rem);
            line-height: 1.05;
            font-weight: 800;
        }
        .form-group {
            margin-bottom: 1.4rem;
        }
        .form-group label {
            display: block;
            margin-bottom: 0.45rem;
            font-size: 0.9rem;
            font-weight: 750;
            color: rgba(255,255,255,0.75);
        }
        .form-group input,
        .form-group select,
        .form-group textarea {
            box-sizing: border-box;
            width: 100%;
            padding: 0.72rem 0.85rem;
            background: rgba(255,255,255,0.055);
            border: 1px solid rgba(255,255,255,0.16);
            border-radius: 12px;
            color: #fff;
            font-size: 0.95rem;
            font-family: inherit;
            outline: none;
            transition: border-color 150ms ease, background 150ms ease, box-shadow 150ms ease;
        }
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            border-color: rgba(0,238,255,0.5);
            background: rgba(255,255,255,0.1);
            box-shadow: 0 0 0 3px rgba(0,238,255,0.08);
        }
        .form-group select option { background: #09121c; }
        .form-two-col {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.2rem;
        }
        .form-three-col {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 1.2rem;
        }
        .sport-specific-fields {
            display: none;
            padding: 1rem;
            background: rgba(255,255,255,0.045);
            border-radius: 16px;
            margin-bottom: 1.4rem;
            border: 1px solid rgba(255,255,255,0.08);
        }
        .sport-fields-title {
            font-size: 0.8rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: #00eeff;
            margin: 0 0 1rem;
        }
        .sport-fields-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 1rem;
        }
        .activity-form-panel {
            padding: 1rem;
            background: rgba(255,255,255,0.045);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 16px;
            margin-bottom: 1rem;
        }
        .photo-preview-grid {
            display:grid;
            grid-template-columns:repeat(auto-fill,minmax(120px,1fr));
            gap:0.65rem;
            margin-top:0.85rem;
        }
        .photo-preview-thumb {
            border-radius:12px;
            overflow:hidden;
            aspect-ratio:4/3;
            border:1px solid rgba(255,255,255,0.1);
        }
        .photo-preview-thumb img {
            width:100%;
            height:100%;
            object-fit:cover;
            display:block;
        }
        @media (max-width: 600px) {
            .form-container { padding: 1rem; margin-top: 5.2rem; }
            .form-two-col, .form-three-col { grid-template-columns: 1fr; }
            .sport-fields-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<?php include __DIR__ . '/../include/menu/menuChoice.php'; ?>

<div class="form-container">
    <h2>Log a New Activity</h2>
    
    <?php if ($flashMessage): ?>
        <div style="color: #ff6b6b; margin-bottom: 1.5rem; padding: 0.9rem 1rem; background: rgba(255, 107, 107, 0.1); border-radius: 10px; border: 1px solid rgba(255,107,107,0.3);">
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
                <div class="sport-field form-group" id="f-distance" style="display:none;">
                    <label>Distance (km)</label>
                    <input type="number" step="0.01" name="distance" placeholder="0.00">
                </div>
                <div class="sport-field form-group" id="f-elevation" style="display:none;">
                    <label>Elevation Gain (m)</label>
                    <input type="number" name="elevation" placeholder="0">
                </div>
                <div class="sport-field form-group" id="f-pace" style="display:none;">
                    <label>Pace (min/km)</label>
                    <input type="number" step="0.01" name="pace" placeholder="0.00">
                </div>
                <div class="sport-field form-group" id="f-cadence" style="display:none;">
                    <label>Cadence (rpm/spm)</label>
                    <input type="number" name="cadence" placeholder="0">
                </div>
                <div class="sport-field form-group" id="f-avgspeed" style="display:none;">
                    <label>Avg Speed (km/h)</label>
                    <input type="number" step="0.01" name="avgspeed" placeholder="0.00">
                </div>
                <div class="sport-field form-group" id="f-maxspeed" style="display:none;">
                    <label>Max Speed (km/h)</label>
                    <input type="number" step="0.01" name="maxspeed" placeholder="0.00">
                </div>
                <div class="sport-field form-group" id="f-avgpower" style="display:none;">
                    <label>Avg Power (W)</label>
                    <input type="number" name="avgpower" placeholder="0">
                </div>
                <div class="sport-field form-group" id="f-maxpower" style="display:none;">
                    <label>Max Power (W)</label>
                    <input type="number" name="maxpower" placeholder="0">
                </div>
                <div class="sport-field form-group" id="f-type" style="display:none;">
                    <label>Type / Style</label>
                    <input type="text" name="type" placeholder="Freestyle, Legs…">
                </div>
                <div class="sport-field form-group" id="f-cycling-type" style="display:none;">
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

        <hr style="border:none;border-top:1px solid rgba(255,255,255,0.08);margin:1.5rem 0;">
        <p style="font-size:0.75rem;font-weight:700;text-transform:uppercase;letter-spacing:0.07em;color:#00eeff;margin:0 0 0.9rem;">Photos (optional)</p>
        <label class="photo-upload-zone" id="photo-upload-zone">
            <input type="file" name="photos[]" id="photo-input" accept="image/*" multiple style="display:none;">
            <span id="photo-upload-label">Click to add photos (JPG, PNG, WEBP - max 8 MB each)</span>
        </label>
        <div id="photo-preview" class="photo-preview-grid"></div>

        <button type="submit" class="btn" style="width:100%;margin-top:1.5rem;padding:0.8rem;">Save Activity</button>
    </form>
</div>

<script>
    // Photo preview
    const photoInput = document.getElementById('photo-input');
    const photoPreview = document.getElementById('photo-preview');
    const uploadZone = document.getElementById('photo-upload-zone');

    photoInput.addEventListener('change', () => {
        photoPreview.innerHTML = '';
        Array.from(photoInput.files).forEach(file => {
            const reader = new FileReader();
            reader.onload = e => {
                const div = document.createElement('div');
                div.className = 'photo-preview-thumb';
                const img = document.createElement('img');
                img.src = e.target.result;
                div.appendChild(img);
                photoPreview.appendChild(div);
            };
            reader.readAsDataURL(file);
        });
        document.getElementById('photo-upload-label').textContent =
            photoInput.files.length === 1
                ? photoInput.files[0].name
                : photoInput.files.length + ' photos selected';
    });

    uploadZone.addEventListener('dragover', e => { e.preventDefault(); uploadZone.style.borderColor='#00eeff'; });
    uploadZone.addEventListener('dragleave', () => { uploadZone.style.borderColor=''; });
    uploadZone.addEventListener('drop', e => {
        e.preventDefault();
        uploadZone.style.borderColor='';
        const dt = new DataTransfer();
        Array.from(e.dataTransfer.files).forEach(f => dt.items.add(f));
        photoInput.files = dt.files;
        photoInput.dispatchEvent(new Event('change'));
    });

    // Sport fields toggle
    function showSportFields() {
        const select = document.getElementById('sport_id');
        const sportName = select.options[select.selectedIndex].getAttribute('data-name');
        const container = document.getElementById('sport-fields-container');

        // Hide and reset ALL sport-specific fields (safe for both input and select)
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
