<?php
// config/migration_cart_pagomovil.php - Migración para Carrito de Compras y Pago Móvil
require_once __DIR__ . '/database.php';

function runMigration() {
    $pdo = Database::getConnection();
    $driver = Database::getDriver();

    // 1. Agregar columnas a merchants si no existen
    $merchantCols = [
        'pago_movil_bank' => "VARCHAR(100) DEFAULT 'Banco de Venezuela (0102)'",
        'pago_movil_phone' => "VARCHAR(50) DEFAULT '0412-1010101'",
        'pago_movil_ci' => "VARCHAR(50) DEFAULT 'V-14892341'",
        'pago_movil_name' => "VARCHAR(150) DEFAULT 'Comercio Paseo Macuto'"
    ];

    foreach ($merchantCols as $col => $def) {
        try {
            $pdo->exec("ALTER TABLE merchants ADD COLUMN $col $def");
        } catch (Exception $e) {
            // Columna ya existe
        }
    }

    // Actualizar datos de pago móvil por defecto para los comerciantes de Paseo Macuto
    $pdo->exec("UPDATE merchants SET 
        pago_movil_bank = COALESCE(NULLIF(pago_movil_bank, ''), 'Banco de Venezuela (0102)'),
        pago_movil_phone = COALESCE(NULLIF(pago_movil_phone, ''), '0412-3551020'),
        pago_movil_ci = COALESCE(NULLIF(pago_movil_ci, ''), 'V-16890452'),
        pago_movil_name = COALESCE(NULLIF(pago_movil_name, ''), commercial_name)
    ");

    // 2. Agregar columnas a orders_transactions si no existen
    $orderCols = [
        'items_json' => "TEXT",
        'bcv_rate_used' => "DECIMAL(10, 2) DEFAULT 54.50",
        'sender_bank' => "VARCHAR(100)",
        'sender_phone' => "VARCHAR(50)",
        'sender_ci' => "VARCHAR(50)",
        'payment_reference' => "VARCHAR(100)",
        'proof_image' => "TEXT",
        'verified_at' => "DATETIME",
        'denied_reason' => "TEXT"
    ];

    foreach ($orderCols as $col => $def) {
        try {
            $pdo->exec("ALTER TABLE orders_transactions ADD COLUMN $col $def");
        } catch (Exception $e) {
            // Columna ya existe
        }
    }

    return true;
}

if (php_sapi_name() === 'cli' || isset($_GET['run_migration'])) {
    runMigration();
    echo "Migración de Carrito y Pago Móvil completada con éxito.\n";
}
