<?php
// config/database.php - Conexión unificada PDO para Paseo Macuto
session_start();

class Database {
    private static $pdo = null;
    private static $driver = null;

    public static function getConnection() {
        if (self::$pdo !== null) {
            return self::$pdo;
        }

        $mysqlHost = '127.0.0.1';
        $mysqlDb   = 'paseo_macuto_db';
        $mysqlUser = 'root';
        $mysqlPass = '';
        $mysqlPort = 3306;

        // 1. Intentar conectar a Supabase PostgreSQL si está configurado
        $supabaseHost = getenv('SUPABASE_DB_HOST');
        $supabasePass = getenv('SUPABASE_DB_PASSWORD');
        $supabaseUser = getenv('SUPABASE_DB_USER') ?: 'postgres';
        $supabaseDb   = getenv('SUPABASE_DB_NAME') ?: 'postgres';
        $supabasePort = getenv('SUPABASE_DB_PORT') ?: 5432;

        if (file_exists(__DIR__ . '/supabase_config.php')) {
            $sbCfg = require __DIR__ . '/supabase_config.php';
            if (is_array($sbCfg)) {
                $supabaseHost = $sbCfg['host'] ?? $supabaseHost;
                $supabasePass = $sbCfg['password'] ?? $supabasePass;
                $supabaseUser = $sbCfg['user'] ?? $supabaseUser;
                $supabaseDb   = $sbCfg['database'] ?? $supabaseDb;
                $supabasePort = $sbCfg['port'] ?? $supabasePort;
            }
        }

        if (!empty($supabaseHost) && !empty($supabasePass)) {
            try {
                $dsn = "pgsql:host=$supabaseHost;port=$supabasePort;dbname=$supabaseDb;sslmode=require";
                self::$pdo = new PDO($dsn, $supabaseUser, $supabasePass, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_TIMEOUT => 4
                ]);
                self::$driver = 'pgsql';
                return self::$pdo;
            } catch (Exception $e) {
                // Si falla Supabase, continúa al fallback local
            }
        }

        // 2. Intentar conectar a MySQL de XAMPP
        try {
            // Intentamos conexión rápida con timeout corto
            $dsn = "mysql:host=$mysqlHost;port=$mysqlPort;charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_TIMEOUT => 2
            ];
            $testPdo = new PDO($dsn, $mysqlUser, $mysqlPass, $options);
            // Si conecta, aseguramos la base de datos
            $testPdo->exec("CREATE DATABASE IF NOT EXISTS `$mysqlDb` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            self::$pdo = new PDO("mysql:host=$mysqlHost;port=$mysqlPort;dbname=$mysqlDb;charset=utf8mb4", $mysqlUser, $mysqlPass, $options);
            self::$driver = 'mysql';
            return self::$pdo;
        } catch (Exception $e) {
            // 3. Si MySQL no está iniciado en XAMPP, fallback automático y seguro a SQLite
            $sqlitePath = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'paseo_macuto.sqlite';
            $dsn = "sqlite:" . $sqlitePath;
            self::$pdo = new PDO($dsn, null, null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]);
            self::$driver = 'sqlite';
            return self::$pdo;
        }
    }

    public static function getDriver() {
        if (self::$driver === null) {
            self::getConnection();
        }
        return self::$driver;
    }
}
