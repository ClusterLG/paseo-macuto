<?php
// backup/drive_backup.php - Respaldo local y exportación para Google Drive
require_once dirname(__DIR__) . '/config/database.php';

class DriveBackup {
    public static function generateExport() {
        $pdo = Database::getConnection();
        $tables = [
            'system_settings',
            'users',
            'merchants',
            'products_services',
            'orders_transactions',
            'reviews',
            'complaints',
            'xp_boosters',
            'user_activity_logs'
        ];

        $dump = [
            'project' => 'Paseo Macuto - La Guaira',
            'exported_at' => date('Y-m-d H:i:s'),
            'database_driver' => Database::getDriver(),
            'tables' => []
        ];

        foreach ($tables as $table) {
            try {
                $stmt = $pdo->query("SELECT * FROM $table");
                $dump['tables'][$table] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (Exception $e) {
                $dump['tables'][$table] = [];
            }
        }

        $jsonBackup = json_encode($dump, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $filename = 'backup_paseo_macuto_' . date('Y_m_d_His') . '.json';
        $filepath = __DIR__ . DIRECTORY_SEPARATOR . $filename;
        file_put_contents($filepath, $jsonBackup);

        return [
            'success' => true,
            'filename' => $filename,
            'filepath' => $filepath,
            'size_bytes' => strlen($jsonBackup)
        ];
    }
}

if (php_sapi_name() === 'cli' || isset($_GET['export'])) {
    $result = DriveBackup::generateExport();
    echo "Respaldo generado con éxito:\nArchivo: " . $result['filename'] . " (" . $result['size_bytes'] . " bytes)\n";
}
