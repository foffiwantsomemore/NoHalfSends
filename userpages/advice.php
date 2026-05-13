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

// Check if user is admin
$stmt = $pdo->prepare("SELECT role FROM User WHERE userid = ?");
$stmt->execute([$userId]);
$role = $stmt->fetchColumn();
$isAdmin = ($role === 'admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];

        if ($action === 'publish' && $isAdmin) {
            $title = trim($_POST['title']);
            $content = trim($_POST['content']);
            $category = $_POST['category'];

            if (!empty($title) && !empty($content)) {
                $stmt = $pdo->prepare("INSERT INTO Advice (authorid, title, content, createdate, category) VALUES (?, ?, ?, NOW(), ?)");
                $stmt->execute([$userId, $title, $content, $category]);
                $adviceId = $pdo->lastInsertId();

                if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
                    $file = $_FILES['photo'];
                    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                    if (in_array($file['type'], $allowedTypes) && $file['size'] <= 5 * 1024 * 1024) {
                        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                        $uploadDir = __DIR__ . '/../images/advice/';
                        if (!is_dir($uploadDir)) {
                            mkdir($uploadDir, 0777, true);
                        }
                        $filename = 'advice_' . $adviceId . '_' . time() . '.' . $ext;
                        $destPath = $uploadDir . $filename;
                        if (move_uploaded_file($file['tmp_name'], $destPath)) {
                            $relativePath = '../images/advice/' . $filename;
                            $stmt = $pdo->prepare("INSERT INTO AdvicePhoto (adviceid, url) VALUES (?, ?)");
                            $stmt->execute([$adviceId, $relativePath]);
                        }
                    }
                }
            }
            header('Location: advice.php');
            exit;
        } elseif ($action === 'like') {
            $adviceId = (int)$_POST['adviceid'];
            $stmt = $pdo->prepare("SELECT 1 FROM AdviceLike WHERE userid = ? AND adviceid = ?");
            $stmt->execute([$userId, $adviceId]);
            if ($stmt->fetchColumn()) {
                $stmt = $pdo->prepare("DELETE FROM AdviceLike WHERE userid = ? AND adviceid = ?");
                $stmt->execute([$userId, $adviceId]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO AdviceLike (userid, adviceid) VALUES (?, ?)");
                $stmt->execute([$userId, $adviceId]);
            }
            header('Location: advice.php');
            exit;
        } elseif ($action === 'comment') {
            $adviceId = (int)$_POST['adviceid'];
            $text = trim($_POST['text']);
            if (!empty($text)) {
                $stmt = $pdo->prepare("INSERT INTO AdviceComment (userid, adviceid, text, commentdate) VALUES (?, ?, ?, NOW())");
                $stmt->execute([$userId, $adviceId, $text]);
            }
            header('Location: advice.php');
            exit;
        }
    }
}

// Fetch advices
$stmt = $pdo->query("
    SELECT a.*, u.name as author_name, u.surname as author_surname, u.userimage as author_image,
           (SELECT COUNT(*) FROM AdviceLike WHERE adviceid = a.adviceid) as like_count,
           (SELECT COUNT(*) FROM AdviceLike WHERE adviceid = a.adviceid AND userid = $userId) as is_liked,
           p.url as photo_url
    FROM Advice a
    JOIN User u ON a.authorid = u.userid
    LEFT JOIN AdvicePhoto p ON a.adviceid = p.adviceid
    ORDER BY a.createdate DESC
");
$advices = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Advice & News - NHS</title>
    <link rel="stylesheet" href="../css/global.css?v=2">
    <link rel="stylesheet" href="../css/header-footer.css">
</head>
<body>

<?php include __DIR__ . '/../include/header.php'; ?>

<div class="advice-container">
    <div class="advice-page-header">
        <h2>Advice &amp; News</h2>
        <?php if ($isAdmin): ?>
            <button class="btn" onclick="toggleAdminForm()" id="admin-toggle-btn">+ New Advice</button>
        <?php endif; ?>
    </div>

    <?php if ($isAdmin): ?>
        <div class="admin-form-container" id="admin-form" style="display:none;">
            <h3 class="admin-form-title">Publish New Advice</h3>
            <form method="post" enctype="multipart/form-data" class="advice-admin-form">
                <input type="hidden" name="action" value="publish">
                <label>Title</label>
                <input type="text" name="title" required placeholder="Advice title...">
                <label>Category</label>
                <select name="category" required>
                    <option value="nutrition">🥗 Nutrition</option>
                    <option value="training">🏋️ Training</option>
                    <option value="recovery">💤 Recovery</option>
                </select>
                <label>Content</label>
                <textarea name="content" rows="5" required placeholder="Write your advice..."></textarea>
                <label>Photo (optional)</label>
                <input type="file" name="photo" accept="image/*">
                <button type="submit" class="btn" style="width:100%; margin-top:0.5rem;">Publish</button>
            </form>
        </div>
    <?php endif; ?>

    <?php if (empty($advices)): ?>
        <p style="color: var(--color-text-muted, rgba(255,255,255,0.6));">No advice or news published yet.</p>
    <?php else: ?>
        <?php foreach ($advices as $adv): ?>
            <div class="advice-card">
                <div class="advice-header">
                    <?php $uImg = !empty($adv['author_image']) ? htmlspecialchars($adv['author_image']) : '../media/default-user.png'; ?>
                    <img src="<?php echo $uImg; ?>" class="advice-author-img" alt="Author">
                    <div class="advice-meta">
                        <h4><?php echo htmlspecialchars($adv['author_name'] . ' ' . $adv['author_surname']); ?></h4>
                        <span><?php echo (new DateTime($adv['createdate']))->format('d M Y, H:i'); ?></span>
                    </div>
                    <div class="advice-category"><?php echo htmlspecialchars($adv['category']); ?></div>
                </div>

                <h3 class="advice-title"><?php echo htmlspecialchars($adv['title']); ?></h3>
                
                <div class="advice-content">
                    <?php echo nl2br(htmlspecialchars($adv['content'])); ?>
                </div>

                <?php if (!empty($adv['photo_url'])): ?>
                    <img src="<?php echo htmlspecialchars($adv['photo_url']); ?>" class="advice-photo" alt="Advice Photo">
                <?php endif; ?>

                <div class="advice-actions">
                    <form method="post">
                        <input type="hidden" name="action" value="like">
                        <input type="hidden" name="adviceid" value="<?php echo $adv['adviceid']; ?>">
                        <button type="submit" class="action-btn <?php echo $adv['is_liked'] ? 'liked' : ''; ?>">
                            ♥ <?php echo $adv['like_count']; ?> Likes
                        </button>
                    </form>
                    
                    <?php
                        $stmt = $pdo->prepare("SELECT c.*, u.name, u.surname FROM AdviceComment c JOIN User u ON c.userid = u.userid WHERE c.adviceid = ? ORDER BY c.commentdate ASC");
                        $stmt->execute([$adv['adviceid']]);
                        $comments = $stmt->fetchAll();
                    ?>
                    
                    <button type="button" class="action-btn" onclick="toggleComments('comments-<?php echo $adv['adviceid']; ?>')">
                        💬 <?php echo count($comments); ?> Comments
                    </button>
                </div>

                <div class="comments-section" id="comments-<?php echo $adv['adviceid']; ?>">
                    <?php foreach ($comments as $c): ?>
                        <div class="comment-item">
                            <div class="comment-header">
                                <strong><?php echo htmlspecialchars($c['name'] . ' ' . $c['surname']); ?></strong>
                                <span><?php echo (new DateTime($c['commentdate']))->format('d M, H:i'); ?></span>
                            </div>
                            <p class="comment-text"><?php echo htmlspecialchars($c['text']); ?></p>
                        </div>
                    <?php endforeach; ?>
                    
                    <form method="post" class="comment-form">
                        <input type="hidden" name="action" value="comment">
                        <input type="hidden" name="adviceid" value="<?php echo $adv['adviceid']; ?>">
                        <input type="text" name="text" placeholder="Write a comment..." required>
                        <button type="submit" class="btn">Send</button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<script>
function toggleComments(id) {
    const el = document.getElementById(id);
    if (el.style.display === 'block') {
        el.style.display = 'none';
    } else {
        el.style.display = 'block';
    }
}

function toggleAdminForm() {
    const form = document.getElementById('admin-form');
    const btn  = document.getElementById('admin-toggle-btn');
    const open = form.style.display === 'block';
    form.style.display = open ? 'none' : 'block';
    btn.textContent    = open ? '+ New Advice' : '✕ Close';
}
</script>

</body>
</html>