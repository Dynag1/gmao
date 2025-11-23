<?php header('Content-Type: text/html; charset=utf-8'); 
if (isset($_SESSION['id'])) header('Location: dashboard.php');
require 'conf/conf.php';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title><?php echo($marque) ?> - GMAO</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<?php
$user_name = isset($_SESSION['user_id'])
    ? (isset($_SESSION['nom']) ? $_SESSION['nom'] : '')
    : '';
?>
<header>
    <a href=dashboard.php><span class="logo"><?php echo($marque) ?></span></a>
    <button class="menu-toggle" aria-label="Ouvrir le menu">&#9776;</button>
    <nav class="navbar">
        <div class="nav-items-right">
            <a href="/dashboard.php">Accueil</a>
            <?php if (isset($_SESSION['role']) && $_SESSION['role']=='admin'): ?>
                <a href="/admin/admin_sites.php">Sites</a>
                <a href="/admin/admin_cameras.php">Caméras</a>
                <a href="/admin/admin_users.php">Utilisateurs</a>
            <?php endif; ?>
            

            <a href="/mon_compte.php">
                <?php if($user_name): ?>
                    <?= htmlspecialchars($user_name) ?> <span class="account-link">(Mon compte)</span>
                <?php else: ?>
                    Mon compte
                <?php endif; ?>
            </a>
            <a href="/logout.php" class="logout-link">Déconnexion</a>
        </div>
    </nav>
</header>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var btn = document.querySelector('.menu-toggle');
    var menu = document.querySelector('.navbar');
    btn.onclick = function() {
        menu.classList.toggle('open');
    };
    // Fermer le menu après clic sur un lien (optionnel)
    menu.querySelectorAll('a').forEach(function(link){
        link.onclick = function(){ menu.classList.remove('open'); };
    });
});
</script>

