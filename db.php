<?php
// db.php - Database Connection
error_reporting(E_ALL);
ini_set('display_errors', 1);
 
$host = 'localhost';
$db   = 'rsoa_rsoa0142_9';
$user = 'rsoa_rsoa0142_9';
$pass = '654321#';
$charset = 'utf8mb4';
 
$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];
 
try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    $pdo->exec("SET NAMES utf8mb4");
 
    // Create Tables
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100),
        username VARCHAR(100) UNIQUE,
        password VARCHAR(255),
        bio TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
        avatar VARCHAR(10),
        cover VARCHAR(20),
        photo TEXT,
        friends TEXT,
        mutuals TEXT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
 
    $pdo->exec("CREATE TABLE IF NOT EXISTS posts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        userId INT,
        text TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
        image TEXT,
        likes TEXT,
        comments TEXT,
        shares INT DEFAULT 0,
        time VARCHAR(50)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
 
    $pdo->exec("CREATE TABLE IF NOT EXISTS messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        chatKey VARCHAR(50),
        fromId INT,
        text TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
        time VARCHAR(50)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
 
    $pdo->exec("CREATE TABLE IF NOT EXISTS requests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        fromId INT,
        toId INT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
 
    // Seed Data
    $count = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    if ($count == 0) {
        $users = [
            [1, "Alex Rivera", "alexrivera", "alex123", "Photographer & traveler", "AR", "#1a3a5c", "[2, 3, 6]", "[]"],
            [2, "Jordan Kim", "jordankim", "jordan123", "Designer | Coffee addict", "JK", "#2d1b4e", "[1, 3, 4]", "[]"],
            [3, "Sam Patel", "sampatel", "sam123", "Building things that matter", "SP", "#1a4a2e", "[1, 2]", "[]"],
            [4, "Morgan Lee", "morganlee", "morgan123", "Music | Books | Code", "ML", "#4a1a1a", "[2]", "[]"],
            [5, "Taylor Chen", "taylorchen", "taylor123", "Foodie & fitness enthusiast", "TC", "#1a3a4a", "[]", "[]"],
            [6, "Muhammad", "muhammad", "123", "Just joined Nexus!", "MU", "#1e3a1e", "[1]", "[]"],
        ];
        $stmt = $pdo->prepare("INSERT INTO users (id, name, username, password, bio, avatar, cover, friends, mutuals) VALUES (?,?,?,?,?,?,?,?,?)");
        foreach ($users as $u) $stmt->execute($u);
 
        $posts = [
            [1, 2, "Just shipped a new design system!", null, "[1, 3]", "[{\"id\":1,\"userId\":3,\"text\":\"This is amazing!\",\"time\":\"2h ago\"}]", 4, "3h ago"],
            [2, 3, "Reminder: building in public is the best way to learn.", null, "[1, 2, 4]", "[]", 12, "5h ago"],
            [3, 2, "Morning walk vibes", "https://images.unsplash.com/photo-1506905925346-21bda4d32df4?w=600", "[1]", "[{\"id\":2,\"userId\":1,\"text\":\"Stunning shot!\",\"time\":\"1h ago\"}]", 2, "8h ago"],
            [4, 4, "Discovered incredible jazz record today.", null, "[2, 5]", "[]", 1, "1d ago"],
        ];
        $stmt = $pdo->prepare("INSERT INTO posts (id, userId, text, image, likes, comments, shares, time) VALUES (?,?,?,?,?,?,?,?)");
        foreach ($posts as $p) $stmt->execute($p);
 
        $pdo->exec("INSERT INTO requests (fromId, toId) VALUES (4, 1), (5, 1)");
    }
} catch (\PDOException $e) {
    die("DB Connection Error: " . $e->getMessage());
}
?>
