<?php
require_once 'config.php';

// Démarrer la session 
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$message = '';
$message_type = 'error';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $mot_de_passe = $_POST['mot_de_passe'] ?? '';

    if ($email !== '' && $mot_de_passe !== '') {
        try {
            // On récupère aussi la colonne "valide"
            $sql = "SELECT id, nom, prenom, role, mot_de_passe, valide 
                    FROM utilisateur 
                    WHERE email = ? 
                    LIMIT 1";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($mot_de_passe, $user['mot_de_passe'])) {

                // Si la colonne n'existe pas, $user['valide'] sera null/indéfini -> on considère 'en attente'
                $statut = isset($user['valide']) ? (int)$user['valide'] : -1;

                if ($statut === 1) {
                    // ✅ Connexion autorisée
                    $_SESSION['user'] = [
                        'id'     => $user['id'],
                        'nom'    => $user['nom'],
                        'prenom' => $user['prenom'],
                        'role'   => $user['role']
                    ];

                    header('Location: acceuil.php');
                    exit();

                } elseif ($statut === -1) {
                    // ⏳ En attente
                    $message = "⏳ Votre compte est en attente de validation par un administrateur.";
                    $message_type = 'error';

                } else { // 0 (refusé) ou autre valeur
                    // ❌ Refusé
                    $message = "❌ Votre compte a été refusé. Contactez l'administration si besoin.";
                    $message_type = 'error';
                }

            } else {
                $message = "Email ou mot de passe incorrect.";
                $message_type = 'error';
            }
        } catch (PDOException $e) {
            // la colonne 'valide'
            if (stripos($e->getMessage(), "Unknown column 'valide'") !== false) {
                $message = "La colonne <b>valide</b> n'existe pas encore dans la table <b>utilisateur</b>.<br>
                Exécute cette requête SQL puis réessaie :<br>
                <code>ALTER TABLE utilisateur ADD COLUMN valide TINYINT(1) NOT NULL DEFAULT -1;</code>";
            } else {
                $message = "Erreur de connexion. Veuillez réessayer.";
            }
            $message_type = 'error';
            error_log("Erreur login: " . $e->getMessage());
        }


    } else {
        $message = "Veuillez remplir tous les champs.";
        $message_type = 'error';
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - ECE Ciné</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet" />
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(rgba(0,0,0,0.4), rgba(0,0,0,0.4)),
                        url('https://i.pinimg.com/474x/27/da/97/27da97815c0c6f03d457d4158095d014--cinema-party-home-movies.jpg') no-repeat center center fixed;
            background-size: cover;
            background-attachment: fixed;
            color: white;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .container {
            background: rgba(0,0,0,0.85);
            backdrop-filter: blur(15px);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.4);
            overflow: hidden;
            width: 100%;
            max-width: 450px;
            animation: slideUp 0.6s ease-out;
        }

        @keyframes slideUp { from {opacity:0; transform:translateY(30px);} to {opacity:1; transform:translateY(0);} }

        .header {
            background: #000000;
            color: white;
            text-align: center;
            padding: 40px 20px;
            position: relative;
            overflow: hidden;
        }

        .header::before {
            content: '';
            position: absolute; inset: 0;
            background: rgba(255,255,255,0.1);
            backdrop-filter: blur(10px);
        }

        .header-content { position: relative; z-index: 2; }

        .cinema-icon { font-size: 3rem; margin-bottom: 15px; animation: bounce 2s infinite; }
        @keyframes bounce {
            0%,20%,50%,80%,100% { transform: translateY(0); }
            40% { transform: translateY(-10px); }
            60% { transform: translateY(-5px); }
        }

        .header h1 { font-size: 28px; font-weight: 700; margin-bottom: 8px; text-shadow: 2px 2px 4px rgba(0,0,0,0.3); }
        .header p { opacity: 0.9; font-size: 16px; text-shadow: 1px 1px 2px rgba(0,0,0,0.3); }

        .form-container { padding: 40px; }

        .message {
            padding: 15px; border-radius: 12px; margin-bottom: 25px; font-weight: 500; text-align: center;
            animation: fadeIn 0.5s ease-in; backdrop-filter: blur(5px);
        }
        .message.success { background-color: rgba(212, 237, 218, 0.9); color: #155724; border: 2px solid rgba(195, 230, 203, 0.7); }
        .message.error   { background-color: rgba(248, 215, 218, 0.9); color: #721c24; border: 2px solid rgba(245, 198, 203, 0.7); }
        @keyframes fadeIn { from {opacity:0; transform:translateY(-10px);} to {opacity:1; transform:translateY(0);} }

        .form-group { margin-bottom: 25px; }
        label { display: block; margin-bottom: 8px; color: white; font-weight: 600; font-size: 14px; text-shadow: 1px 1px 2px rgba(0,0,0,0.5); }
        .required { color: #ff6b6b; }

        input[type="email"], input[type="password"] {
            width: 100%; padding: 15px; border: 2px solid rgba(255,255,255,0.3); border-radius: 12px; font-size: 16px;
            transition: all 0.3s ease; background: rgba(255,255,255,0.1); backdrop-filter: blur(5px); color: white;
        }
        input::placeholder { color: rgba(255,255,255,0.7); }
        input:focus {
            outline: none; border-color: rgba(255,255,255,0.6); background: rgba(255,255,255,0.15);
            box-shadow: 0 0 0 3px rgba(255, 255, 255, 0.1); transform: translateY(-2px);
        }

        .submit-btn {
            width: 100%; background: #000000;
            color: white; border: none; padding: 18px; border-radius: 12px; font-size: 18px; font-weight: 600; cursor: pointer;
            transition: all 0.3s ease; text-transform: uppercase; letter-spacing: 1px; box-shadow: 0 8px 20px rgba(0, 0, 0, 0.3);
        }
        .submit-btn:hover { transform: translateY(-3px); box-shadow: 0 15px 30px rgba(0, 0, 0, 0.4); }
        .submit-btn:active { transform: translateY(-1px); }

        .register-link { text-align: center; margin-top: 25px; padding-top: 25px; border-top: 1px solid rgba(255,255,255,0.2); }
        .register-link p { color: rgba(255,255,255,0.8); }
        .register-link a { color: #667eea; text-decoration: none; font-weight: 500; transition: color 0.3s ease; text-shadow: 1px 1px 2px rgba(0,0,0,0.3); }
        .register-link a:hover { color: #764ba2; text-shadow: 2px 2px 4px rgba(0,0,0,0.5); }

        .shake { animation: shake 0.6s ease-in-out; }
        @keyframes shake {
            0%,100% { transform: translateX(0); }
            10%,30%,50%,70%,90% { transform: translateX(-5px); }
            20%,40%,60%,80% { transform: translateX(5px); }
        }

        .particles { position: fixed; top: 0; left: 0; width: 100%; height: 100%; pointer-events: none; z-index: -1; }
        .particle { position: absolute; background: rgba(255,255,255,0.1); border-radius: 50%; animation: float 6s infinite linear; }
        @keyframes float {
            0% { transform: translateY(100vh) rotate(0deg); opacity: 0; }
            10% { opacity: 1; } 90% { opacity: 1; }
            100% { transform: translateY(-10px) rotate(360deg); opacity: 0; }
        }

        @media (max-width: 480px) {
            .container { margin: 10px; border-radius: 15px; }
            .form-container { padding: 30px 25px; }
            .header { padding: 30px 20px; }
            .header h1 { font-size: 24px; }
            .cinema-icon { font-size: 2.5rem; }
        }
    </style>
</head>
<body>

    <div class="container">
        <div class="header">
            <div class="header-content">
                <div class="cinema-icon">🎬</div>
                <h1><i class="fas fa-film me-2"></i>ECE Ciné</h1>
                <p>Connexion à votre compte</p>
            </div>
        </div>

        <div class="form-container">
            <?php if (!empty($message)): ?>
                <div class="message <?= htmlspecialchars($message_type) ?> <?= $message_type === 'error' ? 'shake' : '' ?>">
                    <?= $message /* déjà sécurisé ci-dessus, contient éventuellement un <code> */ ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="" autocomplete="on">
                <div class="form-group">
                    <label for="email"><i class="fas fa-envelope me-2"></i>Email <span class="required">*</span></label>
                    <input type="email"
                           id="email"
                           name="email"
                           required
                           autocomplete="email"
                           placeholder="votre@email.com"
                           value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>">
                </div>

                <div class="form-group">
                    <label for="mot_de_passe"><i class="fas fa-lock me-2"></i>Mot de passe <span class="required">*</span></label>
                    <input type="password"
                           id="mot_de_passe"
                           name="mot_de_passe"
                           required
                           autocomplete="current-password"
                           placeholder="••••••••">
                </div>

                <button type="submit" class="submit-btn">
                    <i class="fas fa-sign-in-alt me-2"></i>Se connecter
                </button>
            </form>

            <div class="register-link">
                <p>Pas encore inscrit ? <a href="register.php"><i class="fas fa-user-plus me-1"></i>Créer un compte</a></p>
            </div>
        </div>
    </div>

    <script>
        // Éviter la resoumission du formulaire lors du rafraîchissement
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.href);
        }

        // Focus automatique sur le premier champ
        document.addEventListener('DOMContentLoaded', function() {
            const emailField = document.getElementById('email');
            if (emailField && !emailField.value) {
                emailField.focus();
            }
        });
        }
        setInterval(createParticles, 3000);
        createParticles();
    </script>
</body>
</html>