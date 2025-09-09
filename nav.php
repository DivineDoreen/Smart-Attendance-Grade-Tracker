<div class="nav-bar" style="background-color: #333; padding: 10px; text-align: right;">
    <?php if (isset($_SESSION['user_id'])): ?>
        <a href="logout.php" style="color: white; margin-left: 15px; text-decoration: none;">Logout</a>
    <?php else: ?>
        <?php if (basename($_SERVER['PHP_SELF']) != 'login.php'): ?>
            <a href="login.php" style="color: white; margin-left: 15px; text-decoration: none;">Login</a>
        <?php endif; ?>
    <?php endif; ?>
</div>
<style>
    .nav-bar a:hover { text-decoration: underline; }
</style>