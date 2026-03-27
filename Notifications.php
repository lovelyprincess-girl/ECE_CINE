<?php
session_start(); 

// Vérifier si l'utilisateur est connecté 
if (!isset($_SESSION['user']['id'])) {
    header('Location: login.php');
    exit();
}

// Configuration de la base de données
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

// Correction : utiliser la session user
$user_id = $_SESSION['user']['id'];

// Traitement des actions (marquer comme lu, supprimer)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'mark_read':
                $notif_id = filter_var($_POST['notif_id'], FILTER_VALIDATE_INT);
                if ($notif_id) {
                    $stmt = $pdo->prepare("UPDATE notifications SET lu = 1 WHERE id = ? AND Id_utilisateur = ?");
                    $stmt->execute([$notif_id, $user_id]);
                }
                break;
                
            case 'delete':
                $notif_id = filter_var($_POST['notif_id'], FILTER_VALIDATE_INT);
                if ($notif_id) {
                    $stmt = $pdo->prepare("DELETE FROM notifications WHERE id = ? AND Id_utilisateur = ?");
                    $stmt->execute([$notif_id, $user_id]);
                }
                break;
                
            case 'mark_all_read':
                $stmt = $pdo->prepare("UPDATE notifications SET lu = 1 WHERE Id_utilisateur = ?");
                $stmt->execute([$user_id]);
                break;
                
            case 'delete_all':
                $stmt = $pdo->prepare("DELETE FROM notifications WHERE Id_utilisateur = ?");
                $stmt->execute([$user_id]);
                break;
        }
        
        // Redirection pour éviter la resoumission du formulaire
        header('Location: notifications.php');
        exit();
    }
}

// Récupérer les notifications de l'utilisateur avec pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Compter le total des notifications
$count_stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE Id_utilisateur = ?");
$count_stmt->execute([$user_id]);
$total_notifications = $count_stmt->fetchColumn();
$total_pages = ceil($total_notifications / $limit);

// Récupérer les notifications avec les détails du film
$stmt = $pdo->prepare("
    SELECT n.*, f.titre as film_titre, u.nom, u.prenom
    FROM notifications n
    LEFT JOIN film f ON n.id_film = f.id
    LEFT JOIN utilisateur u ON n.id_liker = u.id
    WHERE n.Id_utilisateur = ?
    ORDER BY n.date_creation DESC
    LIMIT $limit OFFSET $offset
");
$stmt->execute([$user_id]);
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Compter les notifications non lues
$unread_stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE Id_utilisateur = ? AND lu = 0");
$unread_stmt->execute([$user_id]);
$unread_count = $unread_stmt->fetchColumn();

// Récupérer les informations de l'utilisateur - Correction : utiliser la session
$user_info = [
    'nom' => $_SESSION['user']['nom'],
    'prenom' => $_SESSION['user']['prenom'],
    'role' => $_SESSION['user']['role']
];
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - ECE Ciné</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: url('https://i.pinimg.com/474x/27/da/97/27da97815c0c6f03d457d4158095d014--cinema-party-home-movies.jpg') no-repeat center center fixed;
            background-size: cover;
            color: #fff;
            min-height: 100vh;
            position: relative;
        }

        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.85);
            z-index: -1;
        }

        .header {
            background: linear-gradient(135deg, #000 0%, #333 100%);
            padding: 1rem 0;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.5);
            border-bottom: 2px solid #444;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            font-size: 2rem;
            font-weight: bold;
            color: #fff;
            text-decoration: none;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.5);
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(45deg, #666, #999);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            border: 2px solid #fff;
        }

        .navigation {
            background: #222;
            padding: 0.5rem 0;
            border-bottom: 1px solid #444;
        }

        .nav-menu {
            display: flex;
            gap: 2rem;
            list-style: none;
        }

        .nav-menu a {
            color: #ccc;
            text-decoration: none;
            padding: 0.5rem 1rem;
            border-radius: 5px;
            transition: all 0.3s ease;
        }

        .nav-menu a:hover, .nav-menu a.active {
            background: #444;
            color: #fff;
        }

        .main-content {
            padding: 2rem 0;
        }

        .notifications-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            background: rgba(0, 0, 0, 0.7);
            padding: 1.5rem;
            border-radius: 10px;
            border: 1px solid #444;
        }

        .notifications-title {
            font-size: 2rem;
            font-weight: bold;
            color: #fff;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.5);
        }

        .notifications-stats {
            display: flex;
            gap: 1rem;
            align-items: center;
        }

        .stat-item {
            background: rgba(255, 255, 255, 0.1);
            padding: 0.5rem 1rem;
            border-radius: 20px;
            border: 1px solid #555;
        }

        .unread-badge {
            background: #dc3545;
            color: white;
            padding: 0.2rem 0.5rem;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: bold;
        }

        .actions-bar {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
            flex-wrap: wrap;
        }

        .btn {
            padding: 0.7rem 1.5rem;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            font-weight: bold;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-primary {
            background: linear-gradient(45deg, #555, #777);
            color: white;
            border: 1px solid #666;
        }

        .btn-primary:hover {
            background: linear-gradient(45deg, #666, #888);
            transform: translateY(-2px);
        }

        .btn-danger {
            background: linear-gradient(45deg, #444, #666);
            color: white;
            border: 1px solid #555;
        }

        .btn-danger:hover {
            background: linear-gradient(45deg, #555, #777);
            transform: translateY(-2px);
        }

        .notifications-list {
            background: rgba(0, 0, 0, 0.8);
            border-radius: 10px;
            overflow: hidden;
            border: 1px solid #444;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
        }

        .notification-item {
            padding: 1.5rem;
            border-bottom: 1px solid #333;
            display: flex;
            align-items: center;
            gap: 1rem;
            transition: all 0.3s ease;
        }

        .notification-item:hover {
            background: rgba(255, 255, 255, 0.05);
        }

        .notification-item.unread {
            background: rgba(255, 255, 255, 0.1);
            border-left: 4px solid #666;
        }

        .notification-item:last-child {
            border-bottom: none;
        }

        .notification-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: linear-gradient(45deg, #555, #777);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.2rem;
            border: 2px solid #666;
        }

        .notification-content {
            flex: 1;
        }

        .notification-title {
            font-weight: bold;
            margin-bottom: 0.5rem;
            color: #fff;
        }

        .notification-message {
            color: #ccc;
            margin-bottom: 0.5rem;
            line-height: 1.4;
        }

        .notification-meta {
            display: flex;
            gap: 1rem;
            font-size: 0.9rem;
            color: #999;
        }

        .notification-actions {
            display: flex;
            gap: 0.5rem;
        }

        .btn-sm {
            padding: 0.4rem 0.8rem;
            font-size: 0.8rem;
            border-radius: 3px;
        }

        .empty-state {
            text-align: center;
            padding: 3rem;
            color: #ccc;
        }

        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1rem;
            color: #555;
        }

        .pagination {
            display: flex;
            justify-content: center;
            gap: 0.5rem;
            margin-top: 2rem;
        }

        .pagination a, .pagination span {
            padding: 0.7rem 1rem;
            background: rgba(0, 0, 0, 0.7);
            color: #fff;
            text-decoration: none;
            border-radius: 5px;
            border: 1px solid #444;
            transition: all 0.3s ease;
        }

        .pagination a:hover {
            background: rgba(255, 255, 255, 0.1);
        }

        .pagination .current {
            background: linear-gradient(45deg, #555, #777);
            border-color: #666;
        }

        .footer {
            background: #000;
            padding: 2rem 0;
            margin-top: 4rem;
            border-top: 1px solid #333;
        }

        .footer-content {
            text-align: center;
            color: #ccc;
        }

        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                gap: 1rem;
            }

            .nav-menu {
                flex-wrap: wrap;
                gap: 1rem;
            }

            .notifications-header {
                flex-direction: column;
                gap: 1rem;
                text-align: center;
            }

            .actions-bar {
                justify-content: center;
            }

            .notification-item {
                flex-direction: column;
                text-align: center;
                gap: 1rem;
            }

            .notification-actions {
                justify-content: center;
            }
        }

        .film-poster {
            width: 40px;
            height: 60px;
            border-radius: 5px;
            object-fit: cover;
            border: 1px solid #555;
        }

        .loading {
            display: none;
            text-align: center;
            padding: 2rem;
        }

        .spinner {
            border: 3px solid #555;
            border-top: 3px solid #fff;
            border-radius: 50%;
            width: 30px;
            height: 30px;
            animation: spin 1s linear infinite;
            margin: 0 auto 1rem;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="container">
            <div class="header-content">
                <a href="accueil.php" class="logo">
                    <i class="fas fa-film"></i> ECE Ciné
                </a>
                <div class="user-info">
                    <div class="user-avatar">
                        <?php echo strtoupper(substr($user_info['prenom'], 0, 1)); ?>
                    </div>
                    <span><?php echo htmlspecialchars($user_info['prenom'] . ' ' . $user_info['nom']); ?></span>
                    <span class="stat-item"><?php echo ucfirst($user_info['role']); ?></span>
                </div>
            </div>
        </div>
    </header>

    <!-- Navigation -->
    <nav class="navigation">
        <div class="container">
        </div>
    </nav>

    <!-- Contenu principal -->
    <main class="main-content">
        <div class="container">
            <!-- Header des notifications -->
            <div class="notifications-header">
                <h1 class="notifications-title">
                    <i class="fas fa-bell"></i> Mes Notifications
                </h1>
                <div class="notifications-stats">
                    <div class="stat-item">
                        <i class="fas fa-envelope"></i> Total: <?php echo $total_notifications; ?>
                    </div>
                    <?php if ($unread_count > 0): ?>
                    <div class="stat-item">
                        <span class="unread-badge"><?php echo $unread_count; ?> non lues</span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Barre d'actions -->
            <?php if ($total_notifications > 0): ?>
            <div class="actions-bar">
                <form method="post" style="display: inline;">
                    <input type="hidden" name="action" value="mark_all_read">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-check"></i> Marquer tout comme lu
                    </button>
                </form>
                <form method="post" style="display: inline;" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer toutes les notifications ?')">
                    <input type="hidden" name="action" value="delete_all">
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-trash"></i> Supprimer tout
                    </button>
                </form>
            </div>
            <?php endif; ?>

            <!-- Liste des notifications -->
            <div class="notifications-list">
                <?php if (empty($notifications)): ?>
                    <div class="empty-state">
                        <i class="fas fa-bell-slash"></i>
                        <h3>Aucune notification</h3>
                        <p>Vous n'avez pas encore de notifications. Les likes sur vos films partagés apparaîtront ici.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($notifications as $notif): ?>
                        <div class="notification-item <?php echo $notif['lu'] ? '' : 'unread'; ?>">
                            <div class="notification-icon">
                                <i class="fas fa-heart"></i>
                            </div>
                            
                            <?php 
                            if (isset($notif['image_url']) && $notif['image_url']): ?>
                                <img src="<?php echo htmlspecialchars($notif['image_url']); ?>" 
                                     alt="Affiche" class="film-poster"
                                     onerror="this.style.display='none'">
                            <?php endif; ?>
                            
                            <div class="notification-content">
                                <div class="notification-title">
                                    Nouveau like sur votre film !
                                </div>
                                <div class="notification-message">
                                    <strong><?php echo htmlspecialchars($notif['prenom'] . ' ' . $notif['nom']); ?></strong> 
                                    a aimé votre film 
                                    <strong>"<?php echo htmlspecialchars($notif['film_titre']); ?>"</strong>
                                </div>
                                <div class="notification-meta">
                                    <span><i class="fas fa-clock"></i> <?php echo date('d/m/Y à H:i', strtotime($notif['date_creation'])); ?></span>
                                    <?php if (!$notif['lu']): ?>
                                        <span><i class="fas fa-circle" style="color: #666; font-size: 0.6rem;"></i> Non lu</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="notification-actions">
                                <?php if (!$notif['lu']): ?>
                                <form method="post" style="display: inline;">
                                    <input type="hidden" name="action" value="mark_read">
                                    <input type="hidden" name="notif_id" value="<?php echo $notif['id']; ?>">
                                    <button type="submit" class="btn btn-primary btn-sm">
                                        <i class="fas fa-eye"></i> Marquer comme lu
                                    </button>
                                </form>
                                <?php endif; ?>
                                
                                <form method="post" style="display: inline;" onsubmit="return confirm('Supprimer cette notification ?')">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="notif_id" value="<?php echo $notif['id']; ?>">
                                    <button type="submit" class="btn btn-danger btn-sm">
                                        <i class="fas fa-trash"></i> Supprimer
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page - 1; ?>">
                        <i class="fas fa-chevron-left"></i> Précédent
                    </a>
                <?php endif; ?>
                
                <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                    <?php if ($i == $page): ?>
                        <span class="current"><?php echo $i; ?></span>
                    <?php else: ?>
                        <a href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
                
                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo $page + 1; ?>">
                        Suivant <i class="fas fa-chevron-right"></i>
                    </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <p>&copy; 2025 ECE Ciné - Plateforme de partage de films pour la communauté ECE</p>
                <p>Développé avec ❤ pour les passionnés de cinéma</p>
            </div>
        </div>
    </footer>

    <script>
        // Auto-refresh des notifications toutes les 30 secondes
        setInterval(function() {
            // Vérifier s'il y a de nouvelles notifications sans recharger la page
            fetch('check_notifications.php')
                .then(response => response.json())
                .then(data => {
                    if (data.new_notifications) {
                        // Afficher une indication discrète de nouvelles notifications
                        const badge = document.querySelector('.unread-badge');
                        if (badge) {
                            badge.style.animation = 'pulse 1s infinite';
                        }
                    }
                })
                .catch(error => console.log('Erreur lors de la vérification des notifications'));
        }, 30000);

        // Animation de pulse pour les notifications non lues
        document.addEventListener('DOMContentLoaded', function() {
            const style = document.createElement('style');
            style.textContent = `
                @keyframes pulse {
                    0% { transform: scale(1); }
                    50% { transform: scale(1.05); }
                    100% { transform: scale(1); }
                }
            `;
            document.head.appendChild(style);
        });

        // Confirmation avant suppression multiple
        function confirmDeleteAll() {
            return confirm('Êtes-vous sûr de vouloir supprimer toutes les notifications ? Cette action est irréversible.');
        }

        // Marquer automatiquement comme lu après 5 secondes de visualisation
        document.querySelectorAll('.notification-item.unread').forEach(item => {
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        setTimeout(() => {
                            if (entry.isIntersecting) {
                                // Marquer automatiquement comme lu après 5 secondes
                                const markReadBtn = item.querySelector('button[name="action"][value="mark_read"]');
                                if (markReadBtn) {
                                }
                            }
                        }, 5000);
                    }
                });
            });
            observer.observe(item);
        });
    </script>
</body>
</html>