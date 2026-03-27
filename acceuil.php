<?php
session_start();

// Vérification de connexion
if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit();
}

// Récupération des données utilisateur
$user = $_SESSION['user'];
$prenom = htmlspecialchars($user['prenom'] ?? '');
$nom = htmlspecialchars($user['nom'] ?? '');
$role = htmlspecialchars($user['role'] ?? '');
$user_id = (int)($user['id'] ?? 0);

// Variables pour les données
$films_selection_semaine = [];
$admin_info = [];
$error_message = "";

// Connexion à la base de données
try {
    require_once 'config.php';
    
    // La table likes existe déjà avec la structure correcte
    
    // Récupération des 10 films les plus likés (sélection de la semaine)
    // Adaptation à la structure de base de données
    $sql_selection = "
        SELECT f.*, 
               COALESCE(likes_data.likes_count, 0) as likes_count,
               f.realisateur as realisateurs
        FROM film f 
        LEFT JOIN (
            SELECT id_film, COUNT(*) as likes_count 
            FROM likes 
            GROUP BY id_film
        ) likes_data ON f.id = likes_data.id_film
        WHERE f.valide = 'valide' 
        ORDER BY likes_count DESC 
        LIMIT 10
    ";
    
    $stmt = $conn->prepare($sql_selection);
    $stmt->execute();
    $films_selection_semaine = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Récupération des informations de l'administrateur principal
    $sql_admin = "
        SELECT nom, prenom, email 
        FROM utilisateur 
        WHERE role = 'Admin' 
        LIMIT 1
    ";
    $stmt = $conn->prepare($sql_admin);
    $stmt->execute();
    $admin_info = $stmt->fetch(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    // Si l'erreur concerne la table likes, on affiche les films sans les likes
    if (strpos($e->getMessage(), 'likes') !== false) {
        try {
            // Récupération des films sans les likes
            $sql_selection_fallback = "
                SELECT f.*, 
                       0 as likes_count,
                       f.realisateur as realisateurs
                FROM film f 
                WHERE f.valide = 'valide' 
                ORDER BY f.date_ajout DESC 
                LIMIT 10
            ";
            
            $stmt = $conn->prepare($sql_selection_fallback);
            $stmt->execute();
            $films_selection_semaine = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $error_message = "La fonctionnalité de likes n'est pas encore disponible. Les films sont affichés par date d'ajout.";
            
        } catch (PDOException $e2) {
            $error_message = "Erreur de chargement des films : " . $e2->getMessage();
        }
    } else {
        $error_message = "Erreur de chargement des données : " . $e->getMessage();
    }
}

// Menu selon le rôle
$menu_items = [
    'Etudiant' => [
        'tout_parcourir.php' => ['icon' => 'fas fa-th-large', 'text' => 'Tout parcourir'],
        'partage.php' => ['icon' => 'fas fa-plus-circle', 'text' => 'Partage'],
        'notifications.php' => ['icon' => 'fas fa-bell', 'text' => 'Notifications'],
        'compte.php' => ['icon' => 'fas fa-user-cog', 'text' => 'Compte'],
        'recherche.php' => ['icon' => 'fas fa-search', 'text' => 'Recherche']
    ],
    'Enseignant' => [
        'tout_parcourir.php' => ['icon' => 'fas fa-th-large', 'text' => 'Tout parcourir'],
        'partage.php' => ['icon' => 'fas fa-plus-circle', 'text' => 'Partage'],
        'validation_films.php' => ['icon' => 'fas fa-check-circle', 'text' => 'Valider films'],
        'notifications.php' => ['icon' => 'fas fa-bell', 'text' => 'Notifications'],
        'compte.php' => ['icon' => 'fas fa-user-cog', 'text' => 'Compte'],
        'recherche.php' => ['icon' => 'fas fa-search', 'text' => 'Recherche']
    ],
    'Administratif' => [
        'tout_parcourir.php' => ['icon' => 'fas fa-th-large', 'text' => 'Tout parcourir'],
        'partage.php' => ['icon' => 'fas fa-plus-circle', 'text' => 'Partage'],
        'validation_films.php' => ['icon' => 'fas fa-check-circle', 'text' => 'Valider films'],
        'validation_inscriptions.php' => ['icon' => 'fas fa-user-check', 'text' => 'Valider inscriptions'],
        'notifications.php' => ['icon' => 'fas fa-bell', 'text' => 'Notifications'],
        'compte.php' => ['icon' => 'fas fa-user-cog', 'text' => 'Compte'],
        'recherche.php' => ['icon' => 'fas fa-search', 'text' => 'Recherche']
    ],
    'Admin' => [
        'tout_parcourir.php' => ['icon' => 'fas fa-th-large', 'text' => 'Tout parcourir'],
        'partage.php' => ['icon' => 'fas fa-plus-circle', 'text' => 'Partage'],
        'validation_films.php' => ['icon' => 'fas fa-check-circle', 'text' => 'Valider films'],
        'validation_inscriptions.php' => ['icon' => 'fas fa-user-check', 'text' => 'Valider inscriptions'],
        'validation_admin.php' => ['icon' => 'fas fa-user-shield', 'text' => 'Valider admins'],
        'radiation.php' => ['icon' => 'fas fa-user-times', 'text' => 'Radiation'],
        'notifications.php' => ['icon' => 'fas fa-bell', 'text' => 'Notifications'],
        'compte.php' => ['icon' => 'fas fa-user-cog', 'text' => 'Compte'],
        'recherche.php' => ['icon' => 'fas fa-search', 'text' => 'Recherche']
    ]
];

$current_menu = $menu_items[$role] ?? $menu_items['Etudiant'];
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accueil - ECE Ciné</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(rgba(0,0,0,0.4), rgba(0,0,0,0.4)), 
                    url('https://i.pinimg.com/474x/27/da/97/27da97815c0c6f03d457d4158095d014--cinema-party-home-movies.jpg') no-repeat center center fixed;

         background-size: cover;
            color: white;
            min-height: 100vh;
        }
        
        .navbar-custom {
            background: rgba(0,0,0,0.85) !important;
            backdrop-filter: blur(10px);
            padding: 15px 0;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
        }
        
        .navbar-brand {
            font-size: 24px;
            font-weight: 700;
            color: white !important;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .role-badge {
            background: linear-gradient(135deg, #667eea, #764ba2);
            padding: 8px 16px;
            border-radius: 25px;
            font-size: 14px;
            color: white;
        }
        
        .main-container {
            background: rgba(0,0,0,0.85);
            backdrop-filter: blur(15px);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.4);
            padding: 40px;
            margin-top: 30px;
        }
        
        .welcome-section {
            text-align: center;
            margin-bottom: 50px;
        }
        
        .cinema-icon {
            font-size: 4rem;
            margin-bottom: 20px;
            animation: bounce 2s infinite;
        }
        
        @keyframes bounce {
            0%, 20%, 50%, 80%, 100% { transform: translateY(0); }
            40% { transform: translateY(-10px); }
            60% { transform: translateY(-5px); }
        }
        
        .presentation-text {
            font-size: 1.2rem;
            line-height: 1.8;
            color: #e0e0e0;
            margin-bottom: 30px;
        }
        
        .navigation-menu {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 50px;
        }
        
        .menu-item {
            background: rgba(255,255,255,0.05);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 15px;
            padding: 20px;
            text-align: center;
            transition: all 0.3s ease;
            text-decoration: none;
            color: white;
            display: block;
        }
        
        .menu-item:hover {
            transform: translateY(-5px);
            background: rgba(255,255,255,0.1);
            color: white;
            text-decoration: none;
        }
        
        .menu-icon {
            font-size: 2.5rem;
            margin-bottom: 15px;
            display: block;
        }
        
        .carousel-container {
            background: rgba(255,255,255,0.05);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 40px;
        }
        
        .film-card {
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.2);
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            height: 300px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        
        .film-poster {
            width: 100px;
            height: 150px;
            background: #333;
            margin: 0 auto 15px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #999;
        }
        
        .likes-count {
            background: linear-gradient(135deg, #ff6b6b, #ee5a24);
            color: white;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.9rem;
            display: inline-block;
        }
        
        .admin-contact {
            background: rgba(255,255,255,0.05);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 15px;
            padding: 25px;
            text-align: center;
        }
        
        .btn-logout {
            background: linear-gradient(135deg, #ff4757, #ff3742);
            border: none;
            padding: 12px 25px;
            border-radius: 12px;
            font-weight: 600;
            color: white;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        
        .btn-logout:hover {
            transform: translateY(-2px);
            color: white;
            text-decoration: none;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-custom">
        <div class="container-fluid">
            <span class="navbar-brand">
                <i class="fas fa-film me-2"></i>ECE Ciné
            </span>
            <div class="user-info">
                <span class="role-badge"><?= $role ?></span>
                <span class="text-white"><?= $nom . ' ' . $prenom ?></span>
                <a href="logout.php" class="btn btn-logout ms-3">
                    <i class="fas fa-sign-out-alt me-2"></i>Déconnexion
                </a>
            </div>
        </div>
    </nav>

    <!-- Contenu principal -->
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-10 col-xl-9">
                <div class="main-container">
                    
                    <!-- Messages d'erreur/succès -->
                    <?php if (!empty($error_message)): ?>
                        <div class="alert alert-warning mb-4" role="alert">
                            <i class="fas fa-exclamation-triangle me-2"></i><?= $error_message ?>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Section de bienvenue et présentation -->
                    <div class="welcome-section">
                        <div class="cinema-icon">🎬</div>
                        <h1 class="mb-4">Bienvenue sur ECE Ciné</h1>
                        <div class="presentation-text">
                            <p><strong>ECE Ciné</strong> est la plateforme de partage de films de l'école ECE. 
                            Découvrez, partagez et évaluez les films avec la communauté de votre école.</p>
                            
                            <p>Rejoignez une communauté passionnée de cinéma, partagez vos découvertes 
                            et explorez les recommandations de vos camarades et enseignants.</p>
                        </div>
                    </div>

                    <!-- Menu de navigation -->
                    <div class="navigation-menu">
                        <?php foreach ($current_menu as $url => $item): ?>
                            <a href="<?= $url ?>" class="menu-item">
                                <i class="<?= $item['icon'] ?> menu-icon"></i>
                                <h6 class="mb-0"><?= $item['text'] ?></h6>
                            </a>
                        <?php endforeach; ?>
                    </div>

                    <!-- Sélection de la semaine -->
                    <div class="carousel-container">
                        <h3 class="text-center mb-4">
                            <i class="fas fa-star me-2"></i>Sélection de la semaine
                        </h3>
                        <p class="text-center text-muted mb-4">Les films les plus appréciés par la communauté ECE Ciné</p>
                        
                        <?php if (!empty($films_selection_semaine)): ?>
                            <div id="carouselFilms" class="carousel slide" data-bs-ride="carousel">
                                <div class="carousel-inner">
                                    <?php 
                                    $chunks = array_chunk($films_selection_semaine, 3);
                                    foreach ($chunks as $index => $chunk): 
                                    ?>
                                        <div class="carousel-item <?= $index === 0 ? 'active' : '' ?>">
                                            <div class="row">
                                                <?php foreach ($chunk as $film): ?>
                                                    <div class="col-md-4 mb-3">
                                                        <div class="film-card">
                                                            <div>
                                                                <div class="film-poster">
                                                                    <?php if (!empty($film['image_url'])): ?>
                                                                        <img src="<?= htmlspecialchars($film['image_url']) ?>" 
                                                                             alt="<?= htmlspecialchars($film['titre']) ?>"
                                                                             class="img-fluid" style="max-height: 150px; border-radius: 8px;">
                                                                    <?php else: ?>
                                                                        <i class="fas fa-film fa-2x"></i>
                                                                    <?php endif; ?>
                                                                </div>
                                                                <h6 class="fw-bold"><?= htmlspecialchars($film['titre']) ?></h6>
                                                                <?php if (!empty($film['realisateur'])): ?>
                                                                    <p class="text-muted small mb-2">
                                                                        par <?= htmlspecialchars($film['realisateur']) ?>
                                                                    </p>
                                                                <?php endif; ?>
                                                            </div>
                                                            <div class="likes-count">
                                                                <i class="fas fa-heart me-1"></i>
                                                                <?= $film['likes_count'] ?> likes
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                
                                <?php if (count($chunks) > 1): ?>
                                    <button class="carousel-control-prev" type="button" data-bs-target="#carouselFilms" data-bs-slide="prev">
                                        <span class="carousel-control-prev-icon"></span>
                                    </button>
                                    <button class="carousel-control-next" type="button" data-bs-target="#carouselFilms" data-bs-slide="next">
                                        <span class="carousel-control-next-icon"></span>
                                    </button>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <p class="text-center text-muted">Aucun film disponible pour le moment.</p>
                        <?php endif; ?>
                    </div>

                    <!-- Contact administrateur -->
                    <?php if (!empty($admin_info)): ?>
                    <div class="admin-contact">
                        <h5 class="mb-3"><i class="fas fa-user-shield me-2"></i>Contact Administrateur</h5>
                        <p class="mb-2">
                            <strong><?= htmlspecialchars($admin_info['prenom'] . ' ' . $admin_info['nom']) ?></strong>
                        </p>
                        <p class="text-muted mb-0">
                            <i class="fas fa-envelope me-2"></i>
                            <a href="mailto:<?= htmlspecialchars($admin_info['email']) ?>" class="text-light">
                                <?= htmlspecialchars($admin_info['email']) ?>
                            </a>
                        </p>
                    </div>
                    <?php endif; ?>

                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>