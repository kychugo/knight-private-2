<?php
define('DB_HOST', 'sql302.infinityfree.com');
define('DB_USER', 'if0_39913682');
define('DB_PASS', 'Kychw2025');
define('DB_NAME', 'if0_39913682_knight');
define('DB_PORT', 3306);

function getDB() {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            DB_HOST, DB_PORT, DB_NAME
        );
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        initDB($pdo);
    }
    return $pdo;
}

function initDB($pdo) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `settings` (
        `setting_key`   VARCHAR(50) NOT NULL,
        `setting_value` TEXT        NOT NULL,
        PRIMARY KEY (`setting_key`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `rankings` (
        `id`         INT          NOT NULL AUTO_INCREMENT,
        `name`       VARCHAR(100) NOT NULL,
        `moves`      INT          NOT NULL,
        `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Seed platform_locked = '1' only if not yet present
    $pdo->exec("INSERT IGNORE INTO `settings` (`setting_key`, `setting_value`)
                VALUES ('platform_locked', '1')");

    // Seed admin_password only if not yet present
    $cnt = (int)$pdo->query(
        "SELECT COUNT(*) FROM `settings` WHERE `setting_key` = 'admin_password'"
    )->fetchColumn();
    if ($cnt === 0) {
        $stmt = $pdo->prepare(
            "INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES ('admin_password', ?)"
        );
        $stmt->execute([password_hash('ccckyc', PASSWORD_DEFAULT)]);
    }
}

function getSetting($key) {
    $pdo  = getDB();
    $stmt = $pdo->prepare("SELECT `setting_value` FROM `settings` WHERE `setting_key` = ?");
    $stmt->execute([$key]);
    $row = $stmt->fetch();
    return $row ? $row['setting_value'] : null;
}

function setSetting($key, $value) {
    $pdo  = getDB();
    $stmt = $pdo->prepare(
        "INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE `setting_value` = VALUES(`setting_value`)"
    );
    $stmt->execute([$key, $value]);
}
