<?php
session_start();
require_once 'config.php';

// Vérifier si utilisateur connecté
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user']['id'];

$table_exists = false;
try {
    $check_table = $conn->query("SHOW TABLES LIKE 'partage_film'");
    $table_exists = ($check_table->rowCount() > 0);
} catch (PDOException $e) {
    $table_exists = false;
}

$message = '';
$message_type = '';

if (!$table_exists) {
    $message = "La fonctionnalité de partage n'est pas encore configurée.";
    $message_type = "info";
    $partages = [];
} else {
    // Traitement du formulaire de partage
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['partager_film'])) {
        $titre = trim($_POST['titre']);
        $realisateur = trim($_POST['realisateur']);
        $commentaire = trim($_POST['commentaire']);
        $image_url = trim($_POST['trailer_film']);

        if (strlen($titre) >= 2 && strlen($commentaire) >= 10) {
            try {
                $sql = "INSERT INTO partage_film (id_utilisateur, titre, realisateur, commentaire, image_url, date_partage) 
                        VALUES (?, ?, ?, ?, ?, NOW())";
                $stmt = $conn->prepare($sql);
                $stmt->execute([$user_id, $titre, $realisateur, $commentaire, $image_url]);
                $message = "Film partagé avec succès !";
                $message_type = "success";
            } catch (PDOException $e) {
                $message = "Erreur lors du partage.";
                $message_type = "error";
            }
        } else {
            $message = "Le titre et le commentaire sont requis (commentaire min 10 caractères).";
            $message_type = "error";
        }
    }

    // Récupérer tous les partages
    try {
        $sql = "SELECT p.*, u.prenom, u.nom, u.role 
                FROM partage_film p 
                JOIN utilisateur u ON p.id_utilisateur = u.id 
                ORDER BY p.date_partage DESC";
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        $partages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $partages = [];
    }
}

function formatDate($date) {
    $timestamp = strtotime($date);
    $now = time();
    $diff = $now - $timestamp;
    
    if ($diff < 60) return "à l'instant";
    if ($diff < 3600) return "il y a " . floor($diff/60) . " min";
    if ($diff < 86400) return "il y a " . floor($diff/3600) . " h";
    return date('d/m/Y', $timestamp);
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Communauté - ECE Ciné</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet" />
<style>
    body {
        background: linear-gradient(rgba(0,0,0,0.4), rgba(0,0,0,0.4)), 
                    url('https://i.pinimg.com/474x/27/da/97/27da97815c0c6f03d457d4158095d014--cinema-party-home-movies.jpg') no-repeat center center fixed;
        background-size: cover;
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        color: white;
        min-height: 100vh;
    }

    .main-title {
        text-align: center;
        margin-bottom: 40px;
        font-size: 2.5rem;
        font-weight: 700;
        text-shadow: 3px 3px 8px rgba(0,0,0,0.8);
        color: white !important;
        padding: 20px 0;
    }

    .share-form {
        background: rgba(0,0,0,0.85);
        border-radius: 15px;
        padding: 30px;
        margin-bottom: 30px;
        border: 1px solid rgba(255,255,255,0.1);
    }

    .film-card {
        background: rgba(0,0,0,0.85);
        border: 1px solid rgba(255,255,255,0.1);
        border-radius: 15px;
        padding: 25px;
        margin-bottom: 20px;
        transition: all 0.3s ease;
    }

    .film-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 12px 40px rgba(0,0,0,0.4);
    }

    .film-header {
        display: flex;
        align-items: center;
        gap: 20px;
        margin-bottom: 20px;
    }

    .film-poster {
        width: 80px;
        height: 120px;
        object-fit: cover;
        border-radius: 8px;
        border: 2px solid rgba(255,255,255,0.2);
    }

    .film-poster-placeholder {
        width: 80px;
        height: 120px;
        background: linear-gradient(45deg, #667eea 0%, #764ba2 100%);
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 1.5rem;
    }

    .film-info h4 {
        color: white;
        margin-bottom: 5px;
    }

    .film-director {
        color: #e0e0e0;
        margin-bottom: 10px;
    }

    .film-meta {
        display: flex;
        align-items: center;
        gap: 15px;
        font-size: 0.9rem;
        color: #ccc;
    }

    .user-badge {
        background: rgba(255,255,255,0.1);
        padding: 4px 12px;
        border-radius: 15px;
        font-size: 0.8rem;
    }

    .user-badge.admin { background: rgba(255,193,7,0.2); color: #ffc107; }
    .user-badge.enseignant { background: rgba(40,167,69,0.2); color: #28a745; }

    .film-comment {
        color: #f0f0f0;
        line-height: 1.6;
        font-style: italic;
        padding: 15px;
        background: rgba(255,255,255,0.05);
        border-radius: 8px;
        border-left: 4px solid #667eea;
    }

    .form-control {
        background: rgba(255,255,255,0.1) !important;
        border: 1px solid rgba(255,255,255,0.3) !important;
        color: white !important;
        border-radius: 8px !important;
    }

    .form-control:focus {
        background: rgba(255,255,255,0.15) !important;
        border-color: rgba(255,255,255,0.5) !important;
        box-shadow: 0 0 0 0.2rem rgba(255,255,255,0.25) !important;
        color: white !important;
    }

    .form-control::placeholder {
        color: rgba(255,255,255,0.7) !important;
    }

    .btn-primary {
        background: linear-gradient(45deg, #667eea 0%, #764ba2 100%);
        border: none;
        font-weight: 600;
        padding: 12px 25px;
        border-radius: 25px;
        transition: all 0.3s ease;
    }

    .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(0,0,0,0.3);
    }

    .alert {
        background: rgba(255,255,255,0.1) !important;
        border: 1px solid rgba(255,255,255,0.3) !important;
        color: white !important;
        border-radius: 12px !important;
    }

    .alert-success { background: rgba(40,167,69,0.2) !important; border-color: rgba(40,167,69,0.5) !important; }
    .alert-danger { background: rgba(220,53,69,0.2) !important; border-color: rgba(220,53,69,0.5) !important; }
    .alert-info { background: rgba(13,110,253,0.2) !important; border-color: rgba(13,110,253,0.5) !important; }

    .no-films {
        text-align: center;
        padding: 60px 20px;
        background: rgba(0,0,0,0.7);
        border-radius: 15px;
    }
</style>
</head>
<body>
<div class="container py-4" style="max-width: 800px;">
    <h1 class="main-title">
        <i class="fas fa-users"></i> Communauté - Partage de Films
    </h1>

    <?php if (!empty($message)): ?>
        <div class="alert alert-<?= $message_type === 'success' ? 'success' : ($message_type === 'info' ? 'info' : 'danger') ?> alert-dismissible fade show">
            <i class="fas fa-<?= $message_type === 'success' ? 'check-circle' : 'info-circle' ?> me-2"></i>
            <?= htmlspecialchars($message) ?>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (!$table_exists): ?>
        <div class="card bg-dark border-warning mb-4">
            <div class="card-header bg-warning text-dark">
                <h5 class="mb-0"><i class="fas fa-database me-2"></i>Configuration requise</h5>
            </div>
            <div class="card-body">
                <p>Pour activer le partage de films, exécutez ce script SQL :</p>
                <pre class="bg-secondary p-3 text-white rounded"><code>CREATE TABLE partage_film (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_utilisateur INT NOT NULL,
    titre VARCHAR(200) NOT NULL,
    realisateur VARCHAR(100),
    commentaire TEXT NOT NULL,
    trailer TEXT,
    date_partage DATETIME NOT NULL,
    FOREIGN KEY (id_utilisateur) REFERENCES utilisateur(id)
);</code></pre>
            </div>
        </div>
    <?php else: ?>

        <!-- Formulaire de partage -->
        <div class="share-form">
            <h4 class="mb-4"><i class="fas fa-share me-2"></i>Partager un film</h4>
            <form method="POST">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label text-white">Titre du film *</label>
                        <input type="text" name="titre" class="form-control" 
                               placeholder="Ex: Inception" required />
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label text-white">Réalisateur</label>
                        <input type="text" name="realisateur" class="form-control" 
                               placeholder="Ex: Christopher Nolan" />
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label text-white">Trailer_film</label>
                    <input type="url" name="image_url" class="form-control" 
                           placeholder="https://..." />
                </div>
                <div class="mb-3">
                    <label class="form-label text-white">Pourquoi recommandez-vous ce film ? *</label>
                    <textarea name="commentaire" class="form-control" rows="4" 
                              placeholder="Partagez votre avis, ce que vous avez aimé..." 
                              required minlength="10"></textarea>
                </div>
                <button type="submit" name="partager_film" class="btn btn-primary">
                    <i class="fas fa-paper-plane me-2"></i>Partager ce film
                </button>
            </form>
        </div>

        <!-- Liste des partages -->
        <?php if (empty($partages)): ?>
            <div class="no-films">
                <i class="fas fa-film mb-3" style="font-size: 4rem; color: #666;"></i>
                <h3>Aucun film partagé</h3>
                <p>Soyez le premier à recommander un film à la communauté !</p>
            </div>
        <?php else: ?>
            <h4 class="mb-4 text-center">
                <i class="fas fa-film me-2"></i>Films partagés par la communauté (<?= count($partages) ?>)
            </h4>
            
            <?php foreach ($partages as $partage): ?>
                <div class="film-card">
                    <div class="film-header">
                        <?php if (!empty($partage['image_url'])): ?>
                            <img src="<?= htmlspecialchars($partage['image_url']) ?>" 
                                 alt="<?= htmlspecialchars($partage['titre']) ?>" 
                                 class="film-poster" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';" />
                            <div class="film-poster-placeholder" style="display:none;">
                                <i class="fas fa-film"></i>
                            </div>
                        <?php else: ?>
                            <div class="film-poster-placeholder">
                                <i class="fas fa-film"></i>
                            </div>
                        <?php endif; ?>
                        
                        <div class="film-info">
                            <h4><?= htmlspecialchars($partage['titre']) ?></h4>
                            <?php if ($partage['realisateur']): ?>
                                <div class="film-director">
                                    <i class="fas fa-user-tie me-2"></i>
                                    <?= htmlspecialchars($partage['realisateur']) ?>
                                </div>
                            <?php endif; ?>
                            <div class="film-meta">
                                <span class="user-badge <?= $partage['role'] ?>">
                                    <i class="fas fa-user me-1"></i>
                                    <?= htmlspecialchars($partage['prenom']) ?> <?= htmlspecialchars($partage['nom']) ?>
                                </span>
                                <span>
                                    <i class="fas fa-clock me-1"></i>
                                    <?= formatDate($partage['date_partage']) ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="film-comment">
                        <i class="fas fa-quote-left me-2"></i>
                        <?= nl2br(htmlspecialchars($partage['commentaire'])) ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    <?php endif; ?>

    <div class="text-center mt-5">
        <a href="dashboard.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left me-2"></i>Retour au tableau de bord
        </a>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>