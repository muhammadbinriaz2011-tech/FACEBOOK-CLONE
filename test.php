<?php
// test.php - Complete Debugging Tool
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<!DOCTYPE html><html><head><title>Debug Test</title>";
echo "<style>body{font-family:monospace;background:#0f1419;color:#e7e9ea;padding:20px;}";
echo ".success{color:#22c55e;} .error{color:#ef4444;} .warning{color:#fbbf24;}";
echo "table{width:100%;border-collapse:collapse;margin:20px 0;}";
echo "th,td{border:1px solid #374151;padding:10px;text-align:left;}";
echo "th{background:#1f2937;} .box{background:#1f2937;padding:15px;border-radius:8px;margin:10px 0;}</style>";
echo "</head><body>";

echo "<h1>🔧 Facebook Clone - Debug Test</h1>";

// ── 1. PHP Info ──────────────────────────────────────────────────────────────
echo "<div class='box'>";
echo "<h2>1. PHP Information</h2>";
echo "<table>";
echo "<tr><th>Setting</th><th>Value</th></tr>";
echo "<tr><td>PHP Version</td><td>" . phpversion() . "</td></tr>";
echo "<tr><td>Display Errors</td><td>" . ini_get('display_errors') . "</td></tr>";
echo "<tr><td>Error Reporting</td><td>" . error_reporting() . "</td></tr>";
echo "<tr><td>PDO MySQL</td><td>" . (extension_loaded('pdo_mysql') ? "<span class='success'>✓ Enabled</span>" : "<span class='error'>✗ Disabled</span>") . "</td></tr>";
echo "<tr><td>JSON</td><td>" . (extension_loaded('json') ? "<span class='success'>✓ Enabled</span>" : "<span class='error'>✗ Disabled</span>") . "</td></tr>";
echo "<tr><td>Session</td><td>" . (extension_loaded('session') ? "<span class='success'>✓ Enabled</span>" : "<span class='error'>✗ Disabled</span>") . "</td></tr>";
echo "</table></div>";

// ── 2. Database Connection ──────────────────────────────────────────────────
echo "<div class='box'>";
echo "<h2>2. Database Connection</h2>";

$host = 'localhost';
$db   = 'rsoa_rsoa0142_9';
$user = 'rsoa_rsoa0142_9';
$pass = '654321#';

echo "<table>";
echo "<tr><th>Setting</th><th>Value</th></tr>";
echo "<tr><td>Host</td><td>$host</td></tr>";
echo "<tr><td>Database</td><td>$db</td></tr>";
echo "<tr><td>Username</td><td>$user</td></tr>";
echo "<tr><td>Password</td><td>***" . substr($pass, -3) . "</td></tr>";
echo "</table>";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    echo "<p class='success'>✓ Database connection successful!</p>";
    
    // Check charset
    $charset = $pdo->query("SELECT @@character_set_database")->fetchColumn();
    $collation = $pdo->query("SELECT @@collation_database")->fetchColumn();
    echo "<p>Database Charset: <strong>$charset</strong> | Collation: <strong>$collation</strong></p>";
    if ($charset !== 'utf8mb4') {
        echo "<p class='warning'>⚠ Warning: Database should use utf8mb4 for emoji support!</p>";
    }
    
} catch (PDOException $e) {
    echo "<p class='error'>✗ Database connection failed!</p>";
    echo "<p class='error'>Error: " . $e->getMessage() . "</p>";
    echo "<p><strong>Common fixes:</strong></p>";
    echo "<ul>";
    echo "<li>Check if database '$db' exists in phpMyAdmin</li>";
    echo "<li>Verify username and password in hosting panel</li>";
    echo "<li>Make sure user has permissions on database</li>";
    echo "<li>Try using IP instead of 'localhost' (sometimes 127.0.0.1)</li>";
    echo "</ul>";
    echo "</div></body></html>";
    exit;
}
echo "</div>";

// ── 3. Tables Check ─────────────────────────────────────────────────────────
echo "<div class='box'>";
echo "<h2>3. Database Tables</h2>";
$tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
echo "<table>";
echo "<tr><th>Table Name</th><th>Status</th><th>Row Count</th></tr>";

$required_tables = ['users', 'posts', 'messages', 'requests'];
foreach ($required_tables as $table) {
    if (in_array($table, $tables)) {
        $count = $pdo->query("SELECT COUNT(*) FROM $table")->fetchColumn();
        echo "<tr><td>$table</td><td class='success'>✓ Exists</td><td>$count rows</td></tr>";
    } else {
        echo "<tr><td>$table</td><td class='error'>✗ Missing</td><td>-</td></tr>";
    }
}
echo "</table>";

if (count($required_tables) !== count($tables)) {
    echo "<p class='warning'>⚠ Some tables are missing! Run the SQL import again.</p>";
}
echo "</div>";

// ── 4. Data Check ───────────────────────────────────────────────────────────
echo "<div class='box'>";
echo "<h2>4. Seed Data Check</h2>";

// Users
$users = $pdo->query("SELECT id, name, username, bio FROM users")->fetchAll();
echo "<h3>Users (" . count($users) . ")</h3>";
if (count($users) > 0) {
    echo "<table><tr><th>ID</th><th>Name</th><th>Username</th><th>Bio</th></tr>";
    foreach ($users as $u) {
        echo "<tr><td>{$u['id']}</td><td>{$u['name']}</td><td>{$u['username']}</td><td>" . substr($u['bio'], 0, 30) . "...</td></tr>";
    }
    echo "</table>";
    echo "<p class='success'>✓ Users table has data</p>";
} else {
    echo "<p class='error'>✗ Users table is empty! Seed data not inserted.</p>";
}

// Posts
$posts = $pdo->query("SELECT id, userId, text, time FROM posts")->fetchAll();
echo "<h3>Posts (" . count($posts) . ")</h3>";
if (count($posts) > 0) {
    echo "<table><tr><th>ID</th><th>User ID</th><th>Text</th><th>Time</th></tr>";
    foreach ($posts as $p) {
        echo "<tr><td>{$p['id']}</td><td>{$p['userId']}</td><td>" . substr($p['text'], 0, 40) . "...</td><td>{$p['time']}</td></tr>";
    }
    echo "</table>";
    echo "<p class='success'>✓ Posts table has data</p>";
} else {
    echo "<p class='error'>✗ Posts table is empty!</p>";
}

// Messages
$msgs = $pdo->query("SELECT COUNT(*) FROM messages")->fetchColumn();
echo "<h3>Messages</h3>";
echo "<p>" . ($msgs > 0 ? "<span class='success'>✓ $msgs messages</span>" : "<span class='error'>✗ No messages</span>") . "</p>";

// Requests
$reqs = $pdo->query("SELECT COUNT(*) FROM requests")->fetchColumn();
echo "<h3>Friend Requests</h3>";
echo "<p>" . ($reqs > 0 ? "<span class='success'>✓ $reqs requests</span>" : "<span class='error'>✗ No requests</span>") . "</p>";

echo "</div>";

// ── 5. File Check ───────────────────────────────────────────────────────────
echo "<div class='box'>";
echo "<h2>5. File Check</h2>";
echo "<table>";
echo "<tr><th>File</th><th>Exists</th><th>Readable</th><th>Size</th></tr>";

$files = ['db.php', 'index.php', 'test.php'];
foreach ($files as $file) {
    $exists = file_exists($file) ? "<span class='success'>✓</span>" : "<span class='error'>✗</span>";
    $readable = is_readable($file) ? "<span class='success'>✓</span>" : "<span class='error'>✗</span>";
    $size = file_exists($file) ? round(filesize($file) / 1024, 2) . " KB" : "-";
    echo "<tr><td>$file</td><td>$exists</td><td>$readable</td><td>$size</td></tr>";
}
echo "</table></div>";

// ── 6. Test Login ───────────────────────────────────────────────────────────
echo "<div class='box'>";
echo "<h2>6. Test Login Credentials</h2>";
echo "<table>";
echo "<tr><th>Username</th><th>Password</th><th>Status</th></tr>";

$test_accounts = [
    ['alexrivera', 'alex123'],
    ['muhammad', '123'],
    ['jordankim', 'jordan123']
];

foreach ($test_accounts as $acc) {
    $stmt = $pdo->prepare("SELECT id, name FROM users WHERE username = ? AND password = ?");
    $stmt->execute($acc);
    $user = $stmt->fetch();
    $status = $user ? "<span class='success'>✓ Works</span>" : "<span class='error'>✗ Failed</span>";
    echo "<tr><td>{$acc[0]}</td><td>{$acc[1]}</td><td>$status</td></tr>";
}
echo "</table></div>";

// ── 7. Quick Fixes ──────────────────────────────────────────────────────────
echo "<div class='box'>";
echo "<h2>7. Quick Fix Commands</h2>";
echo "<p>If you see errors, run these SQL commands in phpMyAdmin:</p>";
echo "<pre style='background:#111;padding:15px;border-radius:8px;overflow-x:auto;'>";
echo htmlspecialchars("
-- Fix charset for emojis
ALTER DATABASE `rsoa_rsoa0142_9` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE users CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE posts CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE messages CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE requests CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Grant permissions
GRANT ALL PRIVILEGES ON `rsoa_rsoa0142_9`.* TO 'rsoa_rsoa0142_9'@'localhost';
FLUSH PRIVILEGES;
");
echo "</pre></div>";

// ── 8. Session Test ─────────────────────────────────────────────────────────
echo "<div class='box'>";
echo "<h2>8. Session Test</h2>";
session_start();
if (session_status() === PHP_SESSION_ACTIVE) {
    echo "<p class='success'>✓ Sessions are working</p>";
    echo "<p>Session ID: " . session_id() . "</p>";
} else {
    echo "<p class='error'>✗ Sessions not working</p>";
}
echo "</div>";

echo "<div class='box'>";
echo "<h2>✅ Summary</h2>";
echo "<p>If all tests show <span class='success'>✓</span>, your setup is correct!</p>";
echo "<p>If you see <span class='error'>✗</span>, fix those issues first.</p>";
echo "<p><strong>Next step:</strong> Delete test.php and visit index.php</p>";
echo "</div>";

echo "</body></html>";
?>
