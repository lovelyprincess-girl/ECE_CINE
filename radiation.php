<?php
session_start();
require_once 'config.php';

// ✅ Vérification que l'utilisateur est administrateur
if (!isset($_SESSION['user']) || strtolower($_SESSION['user']['role']) !== 'admin') {
    die("Accès refusé : seuls les administrateurs peuvent radier des utilisateurs.");
}

// ✅ Suppression (radiation) si un id est envoyé
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['id'])) {
    $id = intval($_POST['id']);

    // Sécurité : on ne peut pas supprimer un administrateur
    $stmt = $conn->prepare("SELECT role FROM utilisateur WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && strtolower($user['role']) !== 'admin') {
        $delete = $conn->prepare("DELETE FROM utilisateur WHERE id = :id");
        $delete->execute([':id' => $id]);
        $message = "Utilisateur radié avec succès.";
    } else {
        $message = "Impossible de radier cet utilisateur.";
    }
}

// ✅ Récupérer la liste des utilisateurs (hors administrateurs)
$stmt = $conn->query("SELECT id, nom, prenom, email, role FROM utilisateur WHERE LOWER(role) != 'admin'");
$utilisateurs = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Radier un utilisateur</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Arial', sans-serif;
            background: url('https://i.pinimg.com/474x/27/da/97/27da97815c0c6f03d457d4158095d014--cinema-party-home-movies.jpg') no-repeat center center fixed;
            background-size: cover;
            min-height: 100vh;
            position: relative;
        }
        body::before {
            content: '';
            position: fixed; top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0, 0, 0, 0.7);
            z-index: -1;
        }
        .page-title {
            color: white;
            font-size: 32px;
            margin: 30px 0;
            text-align: center;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.8);
        }
        .radiation-container {
            max-width: 1000px;
            margin: 40px auto;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            padding: 40px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }
        .user-card {
            background: white;
            border: 1px solid #ddd;
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.3s ease;
        }
        .user-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
        }
        .user-details h3 { color: #333; margin-bottom: 5px; }
        .user-details p { color: #666; margin: 2px 0; }
        .btn-radiate {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: bold;
            transition: all 0.3s ease;
        }
        .btn-radiate:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(220, 53, 69, 0.4);
        }
        .message { text-align: center; color: green; margin-bottom: 20px; font-weight: bold; }
    </style>
</head>
<body>

    <h1 class="page-title">Gestion des utilisateurs - Radiation</h1>

    <div class="radiation-container">
        <?php if (!empty($message)) : ?>
            <p class="message"><?= htmlspecialchars($message) ?></p>
        <?php endif; ?>

        <?php foreach ($utilisateurs as $u): ?>
        <div class="user-card">
            <div class="user-details">
                <h3><?= htmlspecialchars($u['prenom'] . " " . $u['nom']) ?></h3>
                <p>Email : <?= htmlspecialchars($u['email']) ?></p>
                <p>Rôle : <?= htmlspecialchars($u['role']) ?></p>
            </div>
            <form method="POST" onsubmit="return confirm('Confirmer la radiation de cet utilisateur ?');">
                <input type="hidden" name="id" value="<?= $u['id'] ?>">
                <button type="submit" class="btn-radiate">Radier</button>
            </form>
        </div>
        <?php endforeach; ?>
    </div>

</body>
</html>
