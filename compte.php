<?php
session_start();

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user']['id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user']['id'];

// Connexion à la base de données
$host = 'localhost';
$dbname = 'ece_cine';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erreur de connexion : " . $e->getMessage());
}

$success_message = '';
$error_message  = '';

// Récupération des infos utilisateur
$stmt = $pdo->prepare("
    SELECT id, nom, prenom, pseudo, email, date_naissance, statut, photo, fond_ecran
    FROM utilisateur
    WHERE id = ?
");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    session_destroy();
    header("Location: login.php");
    exit();
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    /* Changement mot de passe */
    if (isset($_POST['update_password'])) {
        $current_password = isset($_POST['current_password']) ? $_POST['current_password'] : '';
        $new_password     = isset($_POST['new_password']) ? $_POST['new_password'] : '';
        $confirm_password = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';

        if ($new_password !== $confirm_password) {
            $error_message = "Le nouveau mot de passe et la confirmation ne correspondent pas.";
        } elseif (strlen($new_password) < 6) {
            $error_message = "Le mot de passe doit contenir au moins 6 caractères.";
        } else {
            $stmt = $pdo->prepare("SELECT mot_de_passe FROM utilisateur WHERE id = ?");
            $stmt->execute([$user_id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row || !password_verify($current_password, $row['mot_de_passe'])) {
                $error_message = "Mot de passe actuel incorrect.";
            } else {
                $hashed = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt   = $pdo->prepare("UPDATE utilisateur SET mot_de_passe = ? WHERE id = ?");
                $stmt->execute([$hashed, $user_id]);
                $success_message = "Mot de passe changé avec succès.";
            }
        }
    }

    /* Changement photo de profil */
    if (isset($_POST['update_photo'])) {
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $allowed = array('image/jpeg','image/png','image/gif','image/webp');
            $type    = isset($_FILES['photo']['type']) ? $_FILES['photo']['type'] : '';
            if (!in_array($type, $allowed)) {
                $error_message = "Type de fichier non autorisé pour la photo.";
            } elseif ($_FILES['photo']['size'] > 5 * 1024 * 1024) {
                $error_message = "Photo trop volumineuse (max 5 Mo).";
            } else {
                $upload_dir = __DIR__ . "/uploads/avatars/";
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                $ext      = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
                $filename = "avatar_" . $user_id . "_" . time() . "." . $ext;
                $filepath_abs = $upload_dir . $filename; // chemin serveur
                $filepath_rel = "uploads/avatars/" . $filename; // chemin web

                if (move_uploaded_file($_FILES['photo']['tmp_name'], $filepath_abs)) {
                    // Supprimer l'ancienne photo si elle existe
                    if (!empty($user['photo'])) {
                        $old_abs = __DIR__ . "/" . $user['photo'];
                        if (is_file($old_abs)) {
                            @unlink($old_abs);
                        }
                    }
                    $stmt = $pdo->prepare("UPDATE utilisateur SET photo = ? WHERE id = ?");
                    $stmt->execute([$filepath_rel, $user_id]);
                    $success_message = "Photo de profil mise à jour.";
                    $user['photo']   = $filepath_rel;
                } else {
                    $error_message = "Erreur lors de l'upload de la photo.";
                }
            }
        } else {
            $error_message = "Aucun fichier photo reçu.";
        }
    }

    /* Suppression photo de profil */
    if (isset($_POST['remove_photo'])) {
        if (!empty($user['photo'])) {
            $old_abs = __DIR__ . "/" . $user['photo'];
            if (is_file($old_abs)) {
                @unlink($old_abs);
            }
        }
        $stmt = $pdo->prepare("UPDATE utilisateur SET photo = NULL WHERE id = ?");
        $stmt->execute([$user_id]);
        $user['photo'] = null;
        $success_message = "Photo de profil supprimée.";
    }

    /* Changement fond d'écran */
    if (isset($_POST['update_background'])) {
        if (isset($_FILES['fond_ecran']) && $_FILES['fond_ecran']['error'] === UPLOAD_ERR_OK) {
            $allowed = array('image/jpeg','image/png','image/gif','image/webp');
            $type    = isset($_FILES['fond_ecran']['type']) ? $_FILES['fond_ecran']['type'] : '';
            if (!in_array($type, $allowed)) {
                $error_message = "Type de fichier non autorisé pour le fond d'écran.";
            } elseif ($_FILES['fond_ecran']['size'] > 10 * 1024 * 1024) {
                $error_message = "Image trop volumineuse (max 10 Mo).";
            } else {
                $upload_dir = __DIR__ . "/uploads/backgrounds/";
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                $ext      = pathinfo($_FILES['fond_ecran']['name'], PATHINFO_EXTENSION);
                $filename = "bg_" . $user_id . "_" . time() . "." . $ext;
                $filepath_abs = $upload_dir . $filename;
                $filepath_rel = "uploads/backgrounds/" . $filename;

                if (move_uploaded_file($_FILES['fond_ecran']['tmp_name'], $filepath_abs)) {
                    // Supprimer l'ancien fond si présent
                    if (!empty($user['fond_ecran'])) {
                        $old_abs = __DIR__ . "/" . $user['fond_ecran'];
                        if (is_file($old_abs)) {
                            @unlink($old_abs);
                        }
                    }
                    $stmt = $pdo->prepare("UPDATE utilisateur SET fond_ecran = ? WHERE id = ?");
                    $stmt->execute([$filepath_rel, $user_id]);
                    $success_message   = "Fond d'écran mis à jour.";
                    $user['fond_ecran'] = $filepath_rel;
                } else {
                    $error_message = "Erreur lors de l'upload du fond d'écran.";
                }
            }
        } else {
            $error_message = "Aucun fichier fond d'écran reçu.";
        }
    }

    /* Suppression fond d'écran (retour au défaut Pinterest) */
    if (isset($_POST['remove_background'])) {
        if (!empty($user['fond_ecran'])) {
            $old_abs = __DIR__ . "/" . $user['fond_ecran'];
            if (is_file($old_abs)) {
                @unlink($old_abs);
            }
        }
        $stmt = $pdo->prepare("UPDATE utilisateur SET fond_ecran = NULL WHERE id = ?");
        $stmt->execute([$user_id]);
        $user['fond_ecran'] = null;
        $success_message = "Fond d'écran supprimé. Le fond par défaut est appliqué.";
    }
}


$default_bg = "https://i.pinimg.com/474x/27/da/97/27da97815c0c6f03d457d4158095d014--cinema-party-home-movies.jpg";
$background_url = !empty($user['fond_ecran']) ? $user['fond_ecran'] : $default_bg;

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Mon Compte - ECE Ciné</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: Arial, sans-serif;
            background: url('<?php echo htmlspecialchars($background_url); ?>') no-repeat center center fixed;
            background-size: cover;
            color: #fff;
            margin: 0;
            padding: 20px;
        }
        .wrap { max-width: 900px; margin: 0 auto; }
        .card {
            background: rgba(0,0,0,0.75);
            border: 1px solid rgba(255,255,255,0.15);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .title { margin: 0 0 10px; }
        .grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }
        @media (max-width: 800px) {
            .grid { grid-template-columns: 1fr; }
        }
        .avatar {
            width: 110px; height: 110px; border-radius: 50%; object-fit: cover;
            border: 3px solid rgba(255,255,255,0.5);
        }
        input[type="text"], input[type="email"], input[type="password"], input[type="file"], button {
            width: 100%; padding: 10px; border-radius: 8px; border: 1px solid #444; margin-top: 8px;
            background: rgba(255,255,255,0.08); color: #fff;
        }
        button {
            background: #444; cursor: pointer; border: 1px solid #666; font-weight: bold;
        }
        button:hover { background: #555; }
        .row { display: flex; gap: 12px; flex-wrap: wrap; }
        .grow { flex: 1; min-width: 220px; }
        .alert-success, .alert-error {
            padding: 12px; border-radius: 8px; margin-bottom: 16px; font-weight: bold;
        }
        .alert-success { background: rgba(40,167,69,0.25); border: 1px solid #28a745; }
        .alert-error   { background: rgba(220,53,69,0.25); border: 1px solid #dc3545; }
        .actions { display: flex; gap: 10px; margin-top: 10px; }
        .danger { background: #7a2c2c; border-color: #a33; }
        .danger:hover { background: #8a3d3d; }
        .muted { color: #ddd; }
    </style>
</head>
<body>
<div class="wrap">

    <h1 class="title">Mon Compte</h1>

    <?php if (!empty($success_message)) : ?>
        <div class="alert-success"><?php echo htmlspecialchars($success_message); ?></div>
    <?php endif; ?>
    <?php if (!empty($error_message)) : ?>
        <div class="alert-error"><?php echo htmlspecialchars($error_message); ?></div>
    <?php endif; ?>

    <div class="card">
        <h2 class="title">Profil</h2>
        <div class="row">
            <div>
                <?php if (!empty($user['photo'])) : ?>
                    <img class="avatar" src="<?php echo htmlspecialchars($user['photo']); ?>" alt="Avatar">
                <?php else : ?>
                    <div class="avatar" style="display:flex;align-items:center;justify-content:center;background:#333;">
                        <span style="font-size:42px;font-weight:bold;">
                            <?php echo htmlspecialchars(strtoupper(substr($user['pseudo'], 0, 1))); ?>
                        </span>
                    </div>
                <?php endif; ?>
            </div>
            <div class="grow">
                <p><strong>Nom :</strong> <?php echo htmlspecialchars($user['nom']); ?></p>
                <p><strong>Prénom :</strong> <?php echo htmlspecialchars($user['prenom']); ?></p>
                <p><strong>Pseudo :</strong> <?php echo htmlspecialchars($user['pseudo']); ?></p>
                <p><strong>Email :</strong> <?php echo htmlspecialchars($user['email']); ?></p>
                <p><strong>Date de naissance :</strong> <?php echo htmlspecialchars($user['date_naissance']); ?></p>
                <p><strong>role:</strong> <?php echo htmlspecialchars($user['statut']); ?></p>
            </div>
        </div>
    </div>

    <div class="grid">
        <div class="card">
            <h3 class="title">Changer le mot de passe</h3>
            <form method="post" autocomplete="off">
                <label>Mot de passe actuel
                    <input type="password" name="current_password" required>
                </label>
                <label>Nouveau mot de passe
                    <input type="password" name="new_password" required>
                </label>
                <label>Confirmer le nouveau mot de passe
                    <input type="password" name="confirm_password" required>
                </label>
                <button type="submit" name="update_password">Mettre à jour</button>
            </form>
        </div>

        <div class="card">
            <h3 class="title">Photo de profil</h3>
            <form method="post" enctype="multipart/form-data">
                <label>Choisir une image (jpg, png, gif, webp — 5 Mo max)
                    <input type="file" name="photo" accept=".jpg,.jpeg,.png,.gif,.webp" required>
                </label>
                <div class="actions">
                    <button type="submit" name="update_photo">Mettre à jour</button>
                    <button type="submit" name="remove_photo" class="danger">Supprimer la photo</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <h3 class="title">Fond d'écran</h3>
        <p class="muted">Si aucun fond n'est défini, un fond par défaut (Pinterest) est utilisé.</p>
        <form method="post" enctype="multipart/form-data" class="row">
            <div class="grow">
                <label>Choisir une image (jpg, png, gif, webp — 10 Mo max)
                    <input type="file" name="fond_ecran" accept=".jpg,.jpeg,.png,.gif,.webp">
                </label>
            </div>
            <div class="actions">
                <button type="submit" name="update_background">Mettre à jour</button>
                <button type="submit" name="remove_background" class="danger">Supprimer le fond</button>
            </div>
        </form>
    </div>

</div>
</body>
</html>
