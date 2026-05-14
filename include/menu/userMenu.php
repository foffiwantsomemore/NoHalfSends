<link rel="stylesheet" href="../../css/header-footer.css">

<?php
$currentPage = basename($_SERVER['PHP_SELF'] ?? '');
$isActive = function ($page) use ($currentPage) {
    return $currentPage === $page ? ' is-active' : '';
};
?>

<div class="nhsicon-button app-brand-button">
    <a href="/projects/NoHalfSends/userpages/feed.php">
        <img src="/projects/NoHalfSends/media/nhs.ico" alt="NoHalfSends">
    </a>
</div>

<nav class="user-nav" aria-label="User navigation">
    <ul>
        <li><a class="<?= $isActive('feed.php') ?>" href="/projects/NoHalfSends/userpages/feed.php">Feed</a></li>
        <li><a class="<?= $isActive('clubs.php') ?>" href="/projects/NoHalfSends/userpages/clubs.php">Clubs</a></li>
        <li><a class="<?= $isActive('advice.php') ?>" href="/projects/NoHalfSends/userpages/advice.php">Advice</a></li>
        <li><a class="<?= $isActive('profile.php') ?>" href="/projects/NoHalfSends/userpages/profile.php">Profile</a></li>
    </ul>
</nav>

<div class="auth-action app-auth-action">
    <a href="/projects/NoHalfSends/include/logout.php" aria-label="Logout">
        <img src="/projects/NoHalfSends/media/logout.svg" alt="">
    </a>
</div>
