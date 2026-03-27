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

// Récupération des infos utilisateur pour le fond
$stmt = $pdo->prepare("SELECT fond_ecran FROM utilisateur WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Définir fond par défaut si aucun fond enregistré
$default_bg = "https://i.pinimg.com/474x/27/da/97/27da97815c0c6f03d457d4158095d014--cinema-party-home-movies.jpg";
$background_url = !empty($user['fond_ecran']) ? $user['fond_ecran'] : $default_bg;

// Traitement de la recherche
$search_term = isset($_GET['q']) ? trim($_GET['q']) : '';
$films = [];

if (!empty($search_term)) {
    $sql = "SELECT titre, realisateur, theme, image_url 
            FROM film 
            WHERE titre LIKE :term 
               OR realisateur LIKE :term 
               OR theme LIKE :term
            ORDER BY titre ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['term' => '%' . $search_term . '%']);
    $films = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Recherche - ECE Ciné</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <style>
        body {
            font-family: Arial, sans-serif;
            background: url('<?php echo htmlspecialchars($background_url); ?>') no-repeat center center fixed;
            background-size: cover;
            color: #fff;
            margin: 0;
            padding: 20px;
        }
        .container { max-width: 900px; margin: auto; }
        .card {
            background: rgba(0,0,0,0.75);
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
        input[type="text"] {
            width: 80%;
            padding: 10px;
            border-radius: 5px;
            border: none;
            margin-right: 10px;
        }
        button {
            padding: 10px 15px;
            border-radius: 5px;
            border: none;
            background: #444;
            color: #fff;
            cursor: pointer;
        }
        button:hover { background: #666; }
        .film {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
            padding: 10px;
            background: rgba(255,255,255,0.05);
            border-radius: 8px;
        }
        .film img {
            width: 80px;
            height: 120px;
            object-fit: cover;
            border-radius: 5px;
            margin-right: 15px;
            background: #333;
        }
        .film-info h3 {
            margin: 0;
            font-size: 18px;
        }
        .film-info p {
            margin: 5px 0 0;
            font-size: 14px;
            color: #ccc;
        }
    </style>
</head>
<body>
<div class="container">
    <h1>Recherche de films</h1>

    <div class="card">
        <form method="get" action="">
            <input type="text" name="q" placeholder="Rechercher par titre, réalisateur ou thème..." value="<?php echo htmlspecialchars($search_term); ?>">
            <button type="submit">Rechercher</button>
        </form>
    </div>

    <div class="card">
        <?php if ($search_term && empty($films)): ?>
            <p>Aucun film trouvé pour "<?php echo htmlspecialchars($search_term); ?>"</p>
        <?php elseif (!empty($films)): ?>
            <?php foreach ($films as $film): ?>
                <div class="film">
                    <?php if (!empty($film['image_url'])): ?>
                        <img src="<?php echo htmlspecialchars($film['image_url']); ?>" alt="Affiche du film">
                    <?php else: ?>
                        <img src="https://via.placeholder.com/80x120?text=Pas+d'image" alt="Pas d'image">
                    <?php endif; ?>
                    <div class="film-info">
                        <h3><?php echo htmlspecialchars($film['titre']); ?></h3>
                        <p>Réalisateur : <?php echo htmlspecialchars($film['realisateur']); ?></p>
                        <p>Thème : <?php echo htmlspecialchars($film['theme']); ?></p>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php elseif (!$search_term): ?>
            <p>Entrez un mot-clé pour rechercher des films.</p>
        <?php endif; ?>
    </div>
</div>
</body>
</html>