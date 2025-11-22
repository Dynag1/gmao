<?php
session_start();
require 'connexion.php';
include('header.php');
require 'crypt.php';

// Récupération identité et rôle utilisateur
$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

// Récupère l'id client du user connecté
$stmt = $pdo->prepare("SELECT client_id FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$client_id = $stmt->fetchColumn();

if ($role == 'admin') {
    // Liste des clients pour le filtre admin
    $clients = $pdo->query("SELECT * FROM clients")->fetchAll();
    $selected_client = isset($_POST['client_id']) ? $_POST['client_id'] : '';

    // Tickets ouverts
    $sql_ouvert = "SELECT p.*, c.nom AS camera, s.nom AS site, cl.nom AS client
        FROM pannes p
        JOIN cameras c ON p.camera_id = c.id
        JOIN sites s ON c.site_id = s.id
        JOIN clients cl ON p.client_id = cl.id
        WHERE p.statut = 'ouverte'";
    $sql_ferme = "SELECT p.*, c.nom AS camera, s.nom AS site, cl.nom AS client
        FROM pannes p
        JOIN cameras c ON p.camera_id = c.id
        JOIN sites s ON c.site_id = s.id
        JOIN clients cl ON p.client_id = cl.id
        WHERE p.statut = 'fermee'";
    if ($selected_client) {
        $sql_ouvert .= " AND cl.id = ?";
        $sql_ferme .= " AND cl.id = ?";
        $stmt = $pdo->prepare($sql_ouvert);
        $stmt->execute([$selected_client]);
        $tickets_ouverts = $stmt->fetchAll();
        $stmt = $pdo->prepare($sql_ferme);
        $stmt->execute([$selected_client]);
        $tickets_fermes = $stmt->fetchAll();
    } else {
        $tickets_ouverts = $pdo->query($sql_ouvert)->fetchAll();
        $tickets_fermes = $pdo->query($sql_ferme)->fetchAll();
    }
}
// Pour les users (clients, techniciens...)
else {
    // Récupère toutes les pannes du client auquel on est rattaché
    $sql_ouvert = "SELECT p.*, c.nom AS camera, s.nom AS site
        FROM pannes p
        JOIN cameras c ON p.camera_id = c.id
        JOIN sites s ON c.site_id = s.id
        WHERE p.client_id = ? AND p.statut = 'ouverte'";
    $sql_ferme = "SELECT p.*, c.nom AS camera, s.nom AS site
        FROM pannes p
        JOIN cameras c ON p.camera_id = c.id
        JOIN sites s ON c.site_id = s.id
        WHERE p.client_id = ? AND p.statut = 'fermee'";
    $stmt = $pdo->prepare($sql_ouvert);
    $stmt->execute([$client_id]);
    $tickets_ouverts = $stmt->fetchAll();
    $stmt = $pdo->prepare($sql_ferme);
    $stmt->execute([$client_id]);
    $tickets_fermes = $stmt->fetchAll();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Tableau de bord GMAO</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="style.css">
</head>
<body>
<main>
<?php if ($role == 'admin'): ?>
<form method="post" style="margin-bottom:2em;">
    <label>Filtrer par client :</label>
    <select name="client_id" onchange="this.form.submit();">
        <option value="">Tous les clients</option>
        <?php foreach($clients as $cl): ?>
            <option value="<?= $cl['id'] ?>" <?= ($selected_client == $cl['id']) ? "selected" : "" ?>>
                <?= htmlspecialchars(decrypt_data($cl['nom'])) ?>
            </option>
        <?php endforeach; ?>
    </select>
</form>
<?php endif; ?>

<?php if ($role != 'admin'): ?>
    <a href="signaler.php" class="btn-main">Signaler une panne</a>
<?php endif; ?>

<!-- Tableaux tickets ouverts -->
<h3>Tickets ouverts</h3>
<table id="openTable">
    <tr>
        <th>Site</th>
        <th>Caméra</th>
        <th>Description</th>
        <th>Statut</th>
        <?php if ($role == 'admin'): ?>
        <th>Client</th>
        <?php endif; ?>
        <th>Détail</th>
    </tr>
<?php foreach ($tickets_ouverts as $p):
    // Vérifie s'il y a des messages non lus pour ce ticket
    if($role == "admin"){
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE panne_id = ? AND lu_admin = 0");
        $stmt->execute([$p['id']]);
    }else{
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE panne_id = ? AND lu_user = 0");
        $stmt->execute([$p['id']]);
    }
    $non_lus = $stmt->fetchColumn();
    $lien_class = ($non_lus > 0) ? "lien-voir-rouge" : "lien-voir-vert";
?>
    <tr>
        <td data-label="Site"><?= htmlspecialchars(decrypt_data($p['site'])) ?></td>
        <td data-label="Caméra"><?= htmlspecialchars(decrypt_data($p['camera'])) ?></td>
        <td data-label="Description"><?= htmlspecialchars(decrypt_data($p['description'])) ?></td>
        <td data-label="Statut"><?= htmlspecialchars($p['statut']) ?></td>
        <?php if ($role == 'admin'): ?>
        <td data-label="Client"><?= htmlspecialchars(decrypt_data($p['client'])) ?></td>
        <?php endif; ?>
        <td data-label="Détail">
            <a href="detail_panne.php?panne_id=<?= $p['id'] ?>" class="<?= $lien_class ?>">Voir</a>
        </td>
    </tr>
<?php endforeach; ?>
</table>

<!-- Bouton et tableau tickets fermés -->
<button id="toggleClosed" style="margin-top:2em;">Afficher les tickets fermés</button>
<div id="closedTickets" style="display:none;">
    <h3>Tickets fermés</h3>
    <table id="closedTable">
        <tr>
            <th>Site</th>
            <th>Caméra</th>
            <th>Description</th>
            <th>Statut</th>
            <?php if ($role == 'admin'): ?>
            <th>Client</th>
            <?php endif; ?>
            <th>Détail</th>
        </tr>
    <?php foreach ($tickets_fermes as $p):
        if($role == "admin"){
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE panne_id = ? AND lu_admin = 0");
            $stmt->execute([$p['id']]);
        }else{
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE panne_id = ? AND lu_user = 0");
            $stmt->execute([$p['id']]);
        }
        $non_lus = $stmt->fetchColumn();
        $lien_class = ($non_lus > 0) ? "lien-voir-rouge" : "lien-voir-vert";
    ?>
        <tr>
            <td data-label="Site"><?= htmlspecialchars(decrypt_data($p['site'])) ?></td>
            <td data-label="Caméra"><?= htmlspecialchars(decrypt_data($p['camera'])) ?></td>
            <td data-label="Description"><?= htmlspecialchars(decrypt_data($p['description'])) ?></td>
            <td data-label="Statut"><?= htmlspecialchars($p['statut']) ?></td>
            <?php if ($role == 'admin'): ?>
            <td data-label="Client"><?= htmlspecialchars($p['client']) ?></td>
            <?php endif; ?>
            <td data-label="Détail">
                <a href="detail_panne.php?panne_id=<?= $p['id'] ?>" class="<?= $lien_class ?>">Voir</a>
            </td>
        </tr>
    <?php endforeach; ?>
    </table>
</div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function () {
    function setupTableSort(table) {
        if (!table) return;
        const ths = table.querySelectorAll('th');
        ths.forEach(function(th, colIndex) {
            th.style.cursor = 'pointer';
            th.addEventListener('click', function() {
                sortTable(table, colIndex);
            });
        });
    }
    function sortTable(table, colIndex) {
        const rows = Array.from(table.rows).slice(1);
        let asc = table.asc = !table.asc;
        rows.sort(function(rowA, rowB) {
            let a = rowA.cells[colIndex].innerText.normalize("NFD").replace(/[\u0300-\u036f]/g, "");
            let b = rowB.cells[colIndex].innerText.normalize("NFD").replace(/[\u0300-\u036f]/g, "");
            let cmp = a.localeCompare(b, undefined, {numeric: true});
            return asc ? cmp : -cmp;
        });
        rows.forEach(row => table.tBodies[0].appendChild(row));
    }
    setupTableSort(document.getElementById('openTable'));
    setupTableSort(document.getElementById('closedTable'));

    var btn = document.getElementById('toggleClosed');
    var div = document.getElementById('closedTickets');
    btn.addEventListener('click', function() {
        if (div.style.display === "none") {
            div.style.display = "block";
            btn.textContent = "Cacher les tickets fermés";
        } else {
            div.style.display = "none";
            btn.textContent = "Afficher les tickets fermés";
        }
    });
});
</script>
<?php include('footer.php'); ?>
</body>
</html>
