<link rel="stylesheet" href="../../css/header-footer.css">

<?php
$currentPage = basename($_SERVER['PHP_SELF'] ?? '');
$isActive = function ($page) use ($currentPage) {
    return $currentPage === $page ? ' is-active' : '';
};
?>

<div class="home-brand">
  <a href="/projects/NoHalfSends/homepages/home.php" aria-label="NoHalfSends home">
    <img src="/projects/NoHalfSends/media/nhs.ico" alt="">
  </a>
</div>

<nav class="home-nav">
  <ul class="home-nav-links">
    <li><a class="<?= $isActive('home.php') ?>" href="/projects/NoHalfSends/homepages/home.php">Home</a></li>
    <li><a class="<?= $isActive('about.php') ?>" href="/projects/NoHalfSends/homepages/about.php">About</a></li>
    <li><a class="<?= $isActive('acronym.php') ?>" href="/projects/NoHalfSends/homepages/acronym.php">Acronym</a></li>
  </ul>
</nav>

<div class="auth-action home-auth-action">
  <a href="/projects/NoHalfSends/include/loginForm.php" aria-label="Login">
    <img src="/projects/NoHalfSends/media/user.svg" alt="">
  </a>
</div>

<script>
  (function () {
    document.documentElement.classList.add('home-page');
    document.body.classList.add('home-page');
  })();
</script>
