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
                    $dist = !empty($_POST['distance']) ? (float)$_POST['distance'] : null;
                    $elev = !empty($_POST['elevation']) ? (int)$_POST['elevation'] : null;
                    $stmt = $pdo->prepare("INSERT INTO Cycling (activityid, distance, elevation) VALUES (?, ?, ?)");
                    $stmt->execute([$activityId, $dist, $elev]);
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

                $pdo->commit();
                header('Location: feed.php');
                exit;
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
    <link rel="stylesheet" href="../css/global.css?v=2">
    <link rel="stylesheet" href="../css/header-footer.css">
    <link rel="stylesheet" href="../css/form.css">
    <style>
        .form-container {
            max-width: 600px;
            margin: 3rem auto;
            background: var(--color-surface);
            padding: 2rem;
            border-radius: var(--radius-lg);
            border: 1px solid var(--color-border);
        }
        .form-group {
            margin-bottom: 1.5rem;
        }
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: var(--color-text-secondary);
        }
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 0.75rem;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--color-border);
            border-radius: var(--radius-md);
            color: var(--color-text-primary);
        }
        .sport-specific-fields {
            display: none;
            padding: 1rem;
            background: rgba(0, 0, 0, 0.2);
            border-radius: var(--radius-md);
            margin-bottom: 1.5rem;
        }
    </style>
</head>
<body>

<?php include __DIR__ . '/../include/header.php'; ?>

<div class="form-container">
    <h2 style="margin-top: 0;">Log a New Activity</h2>
    
    <?php if ($flashMessage): ?>
        <div style="color: #ff6b6b; margin-bottom: 1rem; padding: 1rem; background: rgba(255, 107, 107, 0.1); border-radius: var(--radius-sm);">
            <?php echo htmlspecialchars($flashMessage); ?>
        </div>
    <?php endif; ?>

    <form method="post" action="newactivity.php">
        <div class="form-group">
            <label for="name">Activity Title *</label>
            <input type="text" id="name" name="name" required placeholder="Morning Run, Evening Ride, etc.">
        </div>

        <div class="form-group">
            <label for="sport_id">Sport *</label>
            <select id="sport_id" name="sport_id" required onchange="showSportFields()">
                <option value="">Select a sport...</option>
                <?php foreach ($sports as $sport): ?>
                    <option value="<?php echo $sport['sportid']; ?>" data-name="<?php echo htmlspecialchars($sport['name']); ?>">
                        <?php echo htmlspecialchars($sport['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="activitydate">Date and Time *</label>
            <input type="datetime-local" id="activitydate" name="activitydate" required value="<?php echo date('Y-m-d\TH:i'); ?>">
        </div>

        <div class="form-group">
            <label for="duration">Duration (minutes) *</label>
            <input type="number" id="duration" name="duration" required min="1">
        </div>

        <!-- Sport Specific Fields Container -->
        <div id="sport-fields-container" class="sport-specific-fields">
            <div class="form-group" id="f-distance" style="display:none;">
                <label>Distance (km / m for Swimming)</label>
                <input type="number" step="0.01" name="distance">
            </div>
            <div class="form-group" id="f-elevation" style="display:none;">
                <label>Elevation Gain (m)</label>
                <input type="number" name="elevation">
            </div>
            <div class="form-group" id="f-pace" style="display:none;">
                <label>Pace (min/km)</label>
                <input type="number" step="0.01" name="pace">
            </div>
            <div class="form-group" id="f-cadence" style="display:none;">
                <label>Cadence (rpm/spm)</label>
                <input type="number" name="cadence">
            </div>
            <div class="form-group" id="f-avgspeed" style="display:none;">
                <label>Average Speed (km/h)</label>
                <input type="number" step="0.01" name="avgspeed">
            </div>
            <div class="form-group" id="f-type" style="display:none;">
                <label>Type / Style</label>
                <input type="text" name="type" placeholder="Freestyle, Legs, etc.">
            </div>
        </div>

        <div class="form-group">
            <label for="description">Description (optional)</label>
            <textarea id="description" name="description" rows="3" placeholder="How did it go?"></textarea>
        </div>

        <div style="display: flex; gap: 1rem;">
            <div class="form-group" style="flex: 1;">
                <label for="avgheartrate">Avg Heart Rate (bpm)</label>
                <input type="number" id="avgheartrate" name="avgheartrate">
            </div>
            <div class="form-group" style="flex: 1;">
                <label for="maxheartrate">Max Heart Rate (bpm)</label>
                <input type="number" id="maxheartrate" name="maxheartrate">
            </div>
            <div class="form-group" style="flex: 1;">
                <label for="calories">Calories (kcal)</label>
                <input type="number" id="calories" name="calories">
            </div>
        </div>

        <button type="submit" class="btn" style="width: 100%;">Save Activity</button>
    </form>
</div>

<script>
    function showSportFields() {
        const select = document.getElementById('sport_id');
        const sportName = select.options[select.selectedIndex].getAttribute('data-name');
        const container = document.getElementById('sport-fields-container');
        
        // Hide all fields first
        const allFields = ['f-distance', 'f-elevation', 'f-pace', 'f-cadence', 'f-avgspeed', 'f-type'];
        allFields.forEach(id => {
            const el = document.getElementById(id);
            if (el) {
                el.style.display = 'none';
                el.querySelector('input').value = '';
            }
        });

        if (!sportName) {
            container.style.display = 'none';
            return;
        }

        container.style.display = 'block';

        if (sportName === 'Run') {
            document.getElementById('f-distance').style.display = 'block';
            document.getElementById('f-pace').style.display = 'block';
            document.getElementById('f-cadence').style.display = 'block';
        } else if (sportName === 'Cycling') {
            document.getElementById('f-distance').style.display = 'block';
            document.getElementById('f-elevation').style.display = 'block';
        } else if (sportName === 'Excursion') {
            document.getElementById('f-distance').style.display = 'block';
            document.getElementById('f-elevation').style.display = 'block';
            document.getElementById('f-pace').style.display = 'block';
        } else if (sportName === 'Ski') {
            document.getElementById('f-distance').style.display = 'block';
            document.getElementById('f-elevation').style.display = 'block';
            document.getElementById('f-avgspeed').style.display = 'block';
        } else if (sportName === 'Swimming') {
            document.getElementById('f-distance').style.display = 'block';
            document.getElementById('f-pace').style.display = 'block';
            document.getElementById('f-type').style.display = 'block';
        } else if (sportName === 'Gym') {
            document.getElementById('f-type').style.display = 'block';
        } else {
            container.style.display = 'none'; // hide if no specific fields
        }
    }
</script>

</body>
</html>
