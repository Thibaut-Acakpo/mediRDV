<?php
/**
 * Installation en ligne de commande :
 *   php database/install.php          -> crée la base et les tables
 *   php database/install.php --seed   -> + comptes et données de démonstration
 *   php database/install.php --fresh --seed -> repart de zéro (efface tout !)
 */
if (PHP_SAPI !== 'cli') {
    exit('Ce script se lance en ligne de commande.');
}
$config = require dirname(__DIR__) . '/config/config.php';
$c = $config['db'];
$fresh = in_array('--fresh', $argv, true);
$seed  = in_array('--seed', $argv, true);

try {
    $pdo = new PDO("mysql:host={$c['host']};port={$c['port']};charset=utf8mb4", $c['user'], $c['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
} catch (PDOException $e) {
    fwrite(STDERR, "Connexion MySQL impossible : " . $e->getMessage() . "\nVérifiez que MySQL est démarré et config/config.php.\n");
    exit(1);
}

$db = str_replace('`', '', $c['name']);
if ($fresh) {
    $pdo->exec("DROP DATABASE IF EXISTS `$db`");
    echo "Base `$db` supprimée.\n";
}
$pdo->exec("CREATE DATABASE IF NOT EXISTS `$db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$pdo->exec("USE `$db`");

$sql = file_get_contents(__DIR__ . '/schema.sql');
$sql = preg_replace('/^(CREATE DATABASE|USE) .*?;\s*$/mi', '', $sql);
foreach (array_filter(array_map('trim', explode(";\n", $sql))) as $stmt) {
    $pdo->exec($stmt);
}
echo "Tables créées dans `$db`.\n";

if ($seed) {
    require __DIR__ . '/seed.php';
}
echo "Terminé.\n";
