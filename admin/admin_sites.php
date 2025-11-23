<?php
session_start();
require '../connexion.php';
require '../crypt.php';
include('../header.php');
echo('<link rel="stylesheet" href="../style.css">');
if ($_SESSION['role'] !== 'admin') header('Location: dashboard.php');

// --- Liste des clients ---
$clients = $pdo->query("SELECT * FROM clients")->fetchAll();

// --- Récupération du client sélectionné ---
$client_id = isset($_POST['client_id']) ? $_POST['client_id'] : null;

// --- Ajout d'un site (form affiché seulement si client choisi) ---
if (isset($_POST['ajouter'])) {
    $stmt = $pdo->prepare("INSERT INTO sites (client_id, nom, localisation) VALUES (?, ?, ?)");
    $stmt->execute([
        $_POST['client_id'],
        encrypt_data($_POST['nom']),
        encrypt_data($_POST['localisation'])
    ]);
    $client_id = $_POST['client_id']; // Garde le filtre après ajout
}

// --- Suppression d'un site ---
if (isset($_POST['supprimer'])) {
    $stmt = $pdo->prepare("DELETE FROM sites WHERE id=?");
    $stmt->execute([$_POST['id']]);
}

// --- Sites du client sélectionné ---
$sites = [];
if ($client_id) {
    $sites_stmt = $pdo->prepare("SELECT * FROM sites WHERE client_id = ?");
    $sites_stmt->execute([$client_id]);
    $sites = $sites_stmt->fetchAll();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Gestion des Sites</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<main>
<h2>Gestion des Sites</h2>

<!-- Sélection du client -->
<form method="post">
    <label>Choisir un client :</label>
    <select name="client_id" onchange="this.form.submit();" required>
      <option value="">Client associé…</option>
      <?php foreach($clients as $c): ?>
      <option value="<?= $c['id'] ?>" <?= ($client_id == $c['id']) ? "selected" : "" ?>>
        <?= htmlspecialchars(decrypt_data($c['nom'])) ?>
      </option>
      <?php endforeach; ?>
    </select>
</form>

<!-- Formulaire d'ajout de site, affiché uniquement pour le client sélectionné -->
<?php if ($client_id): ?>
<form method="post">
    <input type="hidden" name="client_id" value="<?= $client_id ?>">
    <input type="text" name="nom" placeholder="Nom du site" required>
    <input type="text" name="localisation" placeholder="Localisation">
    <button name="ajouter">Ajouter le site</button>
</form>

<!-- Tableau des sites du client sélectionné -->
<table>
    <tr><th>Nom</th><th>Client</th><th>Localisation</th><th>Actions</th></tr>
    <?php foreach($sites as $s): ?>
    <tr>
        <td><?= htmlspecialchars(decrypt_data($s['nom'])) ?></td>
        <td>
            <?php
            // Recherche du nom client basé sur l'id
            $client = array_filter($clients, function($cl) use ($s){return $cl['id'] == $s['client_id'];});
            if ($client) echo htmlspecialchars(decrypt_data(reset($client)['nom']));
            ?>
        </td>
        <td><?= htmlspecialchars(decrypt_data($s['localisation'])) ?></td>
        <td>
            <form method="post" style="display:inline;">
                <input type="hidden" name="id" value="<?= $s['id'] ?>">
                <input type="hidden" name="client_id" value="<?= $client_id ?>">
                <button name="supprimer">Supprimer</button>
            </form>
        </td>
    </tr>
    <?php endforeach; ?>
</table>
<?php endif; ?>
</main>
<?php include('../footer.php'); ?>
</body>
</html>
