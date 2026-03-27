<?php
// config.php - Configuration de la base de données ECE Ciné

// Paramètres de connexion
$host = 'localhost';
$dbname = 'ece_cine';
$user = 'root';
$pass = '';

try {
    // Création de la connexion PDO
    $conn = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    
    // Configuration des attributs PDO
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $conn->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
    
} catch (PDOException $e) {
    // Log de l'erreur pour le développement
    error_log("Erreur de connexion BDD : " . $e->getMessage());
    
    // Message générique pour l'utilisateur
    die("Erreur de connexion à la base de données. Veuillez contacter l'administrateur.");
}
?>