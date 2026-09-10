<?php
// api/index.php - Controlador Central de API RESTful para Paseo Macuto
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/setup.php';
require_once dirname(__DIR__) . '/config/supabase_auth.php';

$pdo = Database::getConnection();

$action = $_GET['action'] ?? '';
$response = ['success' => false, 'message' => 'Acción no válida'];

// Decodificar JSON si se envía como application/json
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST)) {
    $rawInput = file_get_contents('php://input');
    if (!empty($rawInput)) {
        $parsedJson = json_decode($rawInput, true);
        if (is_array($parsedJson)) {
            $_POST = $parsedJson;
        }
    }
}

// Helper para obtener usuario en sesión
function getCurrentUser($pdo) {
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    $stmt = $pdo->prepare("SELECT id, name, email, role, level, xp, phone, status FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch();
}

// Función para calcular nivel según escala exponencial en base 10
function calculateLevelAndProgress($xp) {
    if ($xp < 10) {
        $level = 1;
        $currentLevelXp = 0;
        $nextLevelXp = 10;
    } else {
        $level = (int)floor(log10($xp)) + 1;
        if ($level > 10) $level = 10;
        $currentLevelXp = pow(10, $level - 1);
        $nextLevelXp = ($level < 10) ? pow(10, $level) : $currentLevelXp;
    }
    
    $levelNames = [
        1 => 'Novato Playero',
        2 => 'Caminante de Macuto',
        3 => 'Explorador Costero',
        4 => 'Amigo de los Pescadores',
        5 => 'Guía de la Bahía',
        6 => 'Capitán de Paseo',
        7 => 'Conocedor del Litoral',
        8 => 'Embajador de La Guaira',
        9 => 'Protector del Malecón',
        10 => 'Leyenda del Paseo Macuto'
    ];

    $range = max(1, $nextLevelXp - $currentLevelXp);
    $progress = ($level >= 10) ? 100 : min(100, max(0, round((($xp - $currentLevelXp) / $range) * 100, 1)));

    return [
        'level' => $level,
        'title' => $levelNames[$level] ?? 'Visitante',
        'xp' => (int)$xp,
        'current_level_xp' => (int)$currentLevelXp,
        'next_level_xp' => (int)$nextLevelXp,
        'progress_percent' => $progress
    ];
}

// Función para obtener multiplicador activo de XP según tipo de acción
function getActiveXpMultiplier($pdo, $actionType) {
    $stmt = $pdo->prepare("SELECT multiplier FROM xp_boosters WHERE is_active = 1 AND (action_type = ? OR action_type = 'all')");
    $stmt->execute([$actionType]);
    $boosters = $stmt->fetchAll();
    $totalMult = 1.0;
    foreach ($boosters as $b) {
        $totalMult *= floatval($b['multiplier']);
    }
    return max(1.0, $totalMult);
}

// Función para otorgar XP y actualizar nivel
function awardXp($pdo, $userId, $actionType, $baseXp) {
    $multiplier = getActiveXpMultiplier($pdo, $actionType);
    $earnedXp = (int)round($baseXp * $multiplier);

    // Registrar log de actividad
    $stmt = $pdo->prepare("INSERT INTO user_activity_logs (user_id, action_type, xp_earned) VALUES (?, ?, ?)");
    $stmt->execute([$userId, $actionType, $earnedXp]);

    // Sumar XP a usuario
    $stmtUser = $pdo->prepare("SELECT xp FROM users WHERE id = ?");
    $stmtUser->execute([$userId]);
    $currentXp = (int)$stmtUser->fetchColumn();
    $newXp = $currentXp + $earnedXp;

    $calc = calculateLevelAndProgress($newXp);
    $stmtUp = $pdo->prepare("UPDATE users SET xp = ?, level = ? WHERE id = ?");
    $stmtUp->execute([$newXp, $calc['level'], $userId]);

    return [
        'earned_xp' => $earnedXp,
        'multiplier' => $multiplier,
        'new_xp' => $newXp,
        'level_data' => $calc
    ];
}

// Función para registrar eventos en la bitácora de actividad histórica del usuario
function logUserActivity($pdo, $userId, $actionType, $xpEarned = 0, $metadata = '') {
    try {
        $now = date('Y-m-d H:i:s');
        $stmt = $pdo->prepare("INSERT INTO user_activity_logs (user_id, action_type, xp_earned, metadata, created_at) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$userId, $actionType, $xpEarned, $metadata, $now]);
    } catch (Exception $e) {
        // Fallback en caso de esquemas variantes
        try {
            $stmt = $pdo->prepare("INSERT INTO user_activity_logs (user_id, action_type, xp_earned) VALUES (?, ?, ?)");
            $stmt->execute([$userId, $actionType, $xpEarned]);
        } catch (Exception $ex) {}
    }
}

// Función para validar que un Plus Code pertenezca a la extensión geográfica de Paseo Macuto
function isPlusCodeInPaseoMacuto($code) {
    if (empty($code) || !is_string($code)) return false;
    $clean = strtoupper(trim(preg_replace('/,.*$/', '', $code)));
    $clean = str_replace(' ', '', $clean);
    // El cuadrante oficial de Paseo Macuto abarca la franja costera J34x y J35x
    $fullPattern = '/^769HJ3[3-5][0-9A-Z]\+[0-9A-Z]{2,4}$/';
    $shortPattern = '/^J3[3-5][0-9A-Z]\+[0-9A-Z]{2,4}$/';
    return (bool)(preg_match($fullPattern, $clean) || preg_match($shortPattern, $clean));
}

// Función para derivar o calcular coordenadas GPS precisas dentro del Paseo Macuto a partir del Plus Code
function deriveCoordinatesFromPlusCode($code) {
    if (empty($code)) return ['lat' => 10.606500, 'lng' => -66.893500];

    // Puntos de referencia exactos comprobados en el Paseo Macuto
    $exactMatches = [
        '769HJ343+6C' => ['lat' => 10.605669, 'lng' => -66.896796],
        'J343+6C'     => ['lat' => 10.605669, 'lng' => -66.896796],
        '769HJ344+8F' => ['lat' => 10.606320, 'lng' => -66.894310],
        'J344+8F'     => ['lat' => 10.606320, 'lng' => -66.894310],
        '769HJ344+XX' => ['lat' => 10.606500, 'lng' => -66.893500],
        'J344+XX'     => ['lat' => 10.606500, 'lng' => -66.893500],
        '769HJ344+J5' => ['lat' => 10.606850, 'lng' => -66.893120],
        'J344+J5'     => ['lat' => 10.606850, 'lng' => -66.893120],
        '769HJ345+3W' => ['lat' => 10.607410, 'lng' => -66.891820],
        'J345+3W'     => ['lat' => 10.607410, 'lng' => -66.891820],
        '769HJ355+RF' => ['lat' => 10.609560, 'lng' => -66.889812],
        'J355+RF'     => ['lat' => 10.609560, 'lng' => -66.889812],
    ];

    $clean = strtoupper(trim(preg_replace('/,.*$/', '', $code)));
    $clean = str_replace(' ', '', $clean);

    if (isset($exactMatches[$clean])) {
        return $exactMatches[$clean];
    }

    $shortCode = str_replace('769H', '', $clean);
    if (isset($exactMatches[$shortCode])) {
        return $exactMatches[$shortCode];
    }

    // Interpolación matemática en la franja costera de Paseo Macuto:
    // Desde Oeste (10.605500, -66.897000) hasta Este (10.609600, -66.889500)
    $alphabet = '23456789CFGHJMPQRVWX';
    $parts = explode('+', $clean);
    $prefix = $parts[0];
    $suffix = $parts[1] ?? 'XX';

    $lastTwo = substr($prefix, -2);
    $idx1 = strpos($alphabet, $lastTwo[0] ?? '4');
    $idx2 = strpos($alphabet, $lastTwo[1] ?? '4');
    $suf1 = strpos($alphabet, $suffix[0] ?? 'J');
    $suf2 = strpos($alphabet, $suffix[1] ?? '5');

    $factor = (($idx1 !== false ? $idx1 : 2) * 400 + ($idx2 !== false ? $idx2 : 2) * 20 + ($suf1 !== false ? $suf1 : 10)) / 2000.0;
    $factor = max(0.0, min(1.0, $factor));

    $lat = 10.605500 + ($factor * (10.609600 - 10.605500));
    $lng = -66.897000 + ($factor * (-66.889500 - (-66.897000)));

    return [
        'lat' => round($lat, 6),
        'lng' => round($lng, 6)
    ];
}

// Recibir cuerpo JSON si aplica
$jsonInput = json_decode(file_get_contents('php://input'), true);
if (is_array($jsonInput)) {
    $_POST = array_merge($_POST, $jsonInput);
}

switch ($action) {
    // -------------------------------------------------------------
    // 1. CONFIGURACIÓN Y TASA BCV
    // -------------------------------------------------------------
    case 'get_settings':
        $stmt = $pdo->query("SELECT setting_key, setting_value, updated_at FROM system_settings");
        $settings = [];
        while ($row = $stmt->fetch()) {
            $settings[$row['setting_key']] = $row['setting_value'];
            if ($row['setting_key'] === 'bcv_rate') {
                $settings['bcv_updated_at'] = $row['updated_at'];
            }
        }
        $bcvRate = floatval($settings['bcv_rate'] ?? 54.50);
        
        $response = [
            'success' => true,
            'bcv_rate' => $bcvRate,
            'bcv_updated_at' => $settings['bcv_updated_at'] ?? date('Y-m-d H:i:s'),
            'settings' => $settings
        ];
        break;

    case 'update_bcv':
        $currentUser = getCurrentUser($pdo);
        if (!$currentUser || $currentUser['role'] !== 'superadmin') {
            $response = ['success' => false, 'message' => 'Solo el Superusuario puede actualizar la tasa oficial BCV'];
            break;
        }

        $newRate = floatval($_POST['bcv_rate'] ?? 0);
        if ($newRate <= 0) {
            $response = ['success' => false, 'message' => 'Ingrese una tasa BCV válida mayor a 0'];
            break;
        }

        $now = date('Y-m-d H:i:s');
        $stmt = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value, updated_by, updated_at) 
                               VALUES ('bcv_rate', ?, ?, ?)
                               ON CONFLICT(setting_key) DO UPDATE SET setting_value = excluded.setting_value, updated_by = excluded.updated_by, updated_at = excluded.updated_at");
        // Soporte tanto para SQLite moderno como MySQL
        if (Database::getDriver() === 'mysql') {
            $stmt = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value, updated_by, updated_at) 
                                   VALUES ('bcv_rate', ?, ?, ?) 
                                   ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_by = VALUES(updated_by), updated_at = VALUES(updated_at)");
        }
        $stmt->execute([number_format($newRate, 2, '.', ''), $currentUser['name'], $now]);

        $response = [
            'success' => true,
            'message' => 'Tasa oficial BCV actualizada a ' . number_format($newRate, 2, ',', '.') . ' Bs./$',
            'bcv_rate' => $newRate,
            'updated_at' => $now
        ];
        break;

    // -------------------------------------------------------------
    // 2. COMERCIANTES Y MAPA GIS ACOTADO
    // -------------------------------------------------------------
    case 'get_merchants':
        $category = $_GET['category'] ?? '';
        $sector = $_GET['sector'] ?? '';
        $search = $_GET['search'] ?? '';

        $sql = "SELECT m.*, u.name as owner_name FROM merchants m LEFT JOIN users u ON m.user_id = u.id WHERE 1=1";
        $params = [];

        // En el mapa público GIS solo se muestran comerciantes verificados con ubicación oficial asignada
        $includeAll = isset($_GET['all_admin']) && getCurrentUser($pdo) && getCurrentUser($pdo)['role'] === 'superadmin';
        if (!$includeAll) {
            $sql .= " AND m.lat != 0 AND m.lng != 0 AND m.rif_verified = 1";
        }

        if (!empty($category) && $category !== 'all') {
            $sql .= " AND m.category LIKE ?";
            $params[] = "%$category%";
        }
        if (!empty($sector) && $sector !== 'all') {
            $sql .= " AND m.sector = ?";
            $params[] = $sector;
        }
        if (!empty($search)) {
            $sql .= " AND (m.commercial_name LIKE ? OR m.description LIKE ? OR m.category LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }

        $sql .= " ORDER BY m.total_rep DESC, m.sales_count DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $merchants = $stmt->fetchAll();

        // Obtener tasa BCV actual para cálculo de precios referenciales
        $bcvStmt = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'bcv_rate'");
        $bcvRate = floatval($bcvStmt->fetchColumn() ?: 54.50);

        // Adjuntar conteo de productos por comercio
        foreach ($merchants as &$m) {
            $pCount = $pdo->prepare("SELECT COUNT(*) FROM products_services WHERE merchant_id = ? AND is_active = 1");
            $pCount->execute([$m['id']]);
            $m['product_count'] = (int)$pCount->fetchColumn();
            $m['lat'] = floatval($m['lat']);
            $m['lng'] = floatval($m['lng']);
            $m['total_rep'] = floatval($m['total_rep']);
            $m['sales_rep'] = floatval($m['sales_rep']);
            $m['service_rep'] = floatval($m['service_rep']);
        }

        $response = [
            'success' => true,
            'bcv_rate' => $bcvRate,
            'count' => count($merchants),
            'merchants' => $merchants
        ];
        break;

    case 'get_merchant_detail':
        $id = intval($_GET['id'] ?? 0);
        $stmt = $pdo->prepare("SELECT m.*, u.name as owner_name, u.email as owner_email FROM merchants m LEFT JOIN users u ON m.user_id = u.id WHERE m.id = ?");
        $stmt->execute([$id]);
        $merchant = $stmt->fetch();

        if (!$merchant) {
            $response = ['success' => false, 'message' => 'Comercio no encontrado'];
            break;
        }

        // Tasa BCV
        $bcvRate = floatval($pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'bcv_rate'")->fetchColumn() ?: 54.50);

        // Productos
        $pStmt = $pdo->prepare("SELECT * FROM products_services WHERE merchant_id = ? AND is_active = 1 ORDER BY id DESC");
        $pStmt->execute([$id]);
        $products = $pStmt->fetchAll();
        foreach ($products as &$p) {
            $p['price_usd'] = floatval($p['price_usd']);
            $p['price_bs'] = round($p['price_usd'] * $bcvRate, 2);
        }

        // Reseñas
        $rStmt = $pdo->prepare("SELECT r.*, u.name as visitor_name, u.level as visitor_level FROM reviews r LEFT JOIN users u ON r.visitor_id = u.id WHERE r.merchant_id = ? ORDER BY r.id DESC LIMIT 15");
        $rStmt->execute([$id]);
        $reviews = $rStmt->fetchAll();

        $merchant['lat'] = floatval($merchant['lat']);
        $merchant['lng'] = floatval($merchant['lng']);
        $merchant['products'] = $products;
        $merchant['reviews'] = $reviews;

        $response = [
            'success' => true,
            'bcv_rate' => $bcvRate,
            'merchant' => $merchant
        ];
        break;

    case 'update_merchant_location':
        $currentUser = getCurrentUser($pdo);
        if (!$currentUser || $currentUser['role'] !== 'superadmin') {
            $response = ['success' => false, 'message' => 'Acceso denegado: Solo el Superusuario puede asignar o modificar la ubicación geográfica de los comercios.'];
            break;
        }

        $merchantId = intval($_POST['merchant_id'] ?? 0);
        $lat = floatval($_POST['lat'] ?? 0);
        $lng = floatval($_POST['lng'] ?? 0);
        $plusCode = trim($_POST['plus_code'] ?? '');

        if ($lat == 0 || $lng == 0) {
            $response = ['success' => false, 'message' => 'Coordenadas GPS no válidas'];
            break;
        }

        // Validar que el Plus Code pertenezca al Paseo Macuto
        if (!empty($plusCode) && !isPlusCodeInPaseoMacuto($plusCode)) {
            $response = [
                'success' => false,
                'message' => 'El Plus Code ingresado se encuentra fuera del rango de Paseo Macuto. Debe pertenecer a la extensión del Paseo Macuto (La Guaira). Ejemplo válido: 769HJ344+J5'
            ];
            break;
        }

        // Validar que las coordenadas GPS estén dentro de los límites de Paseo Macuto
        if ($lat < 10.6010 || $lat > 10.6130 || $lng < -66.9010 || $lng > -66.8880) {
            $response = [
                'success' => false,
                'message' => 'Las coordenadas ingresadas están fuera del perímetro del Paseo Macuto (La Guaira).'
            ];
            break;
        }

        $stmt = $pdo->prepare("UPDATE merchants SET lat = ?, lng = ?, plus_code = ? WHERE id = ?");
        $stmt->execute([$lat, $lng, $plusCode, $merchantId]);

        $response = [
            'success' => true,
            'message' => 'Ubicación de Paseo Macuto actualizada con éxito',
            'lat' => $lat,
            'lng' => $lng,
            'plus_code' => $plusCode
        ];
        break;

    case 'admin_assign_merchant_location':
        $currentUser = getCurrentUser($pdo);
        if (!$currentUser || $currentUser['role'] !== 'superadmin') {
            $response = ['success' => false, 'message' => 'Acceso denegado: Solo el Superusuario puede asignar ubicaciones oficiales'];
            break;
        }

        $merchantId = intval($_POST['merchant_id'] ?? 0);
        $plusCode = strtoupper(trim($_POST['plus_code'] ?? ''));

        if ($merchantId <= 0) {
            $response = ['success' => false, 'message' => 'ID de comerciante inválido'];
            break;
        }

        if (empty($plusCode)) {
            $response = ['success' => false, 'message' => 'Debe ingresar el Plus Code oficial de Paseo Macuto'];
            break;
        }

        // Si es código corto como J344+J5, anteponer 769H para estandarizar
        $cleanTest = str_replace(' ', '', $plusCode);
        if (strpos($cleanTest, 'J3') === 0) {
            $plusCode = '769H' . $cleanTest;
        }

        if (!isPlusCodeInPaseoMacuto($plusCode)) {
            $response = [
                'success' => false,
                'message' => "⚠️ Plus Code fuera de rango: El código ingresado ('$plusCode') no pertenece a la extensión de Paseo Macuto (La Guaira). Ejemplo válido: 769HJ344+J5 o J344+8F"
            ];
            break;
        }

        // Derivar coordenadas GPS automáticas a partir del Plus Code
        $derived = deriveCoordinatesFromPlusCode($plusCode);
        $lat = floatval($_POST['lat'] ?? $derived['lat']);
        $lng = floatval($_POST['lng'] ?? $derived['lng']);

        if ($lat == 0 || $lng == 0) {
            $lat = $derived['lat'];
            $lng = $derived['lng'];
        }

        // Actualizar en BD: asignar Plus Code, coordenadas, certificar RIF y activar para ventas
        $stmt = $pdo->prepare("UPDATE merchants SET plus_code = ?, lat = ?, lng = ?, rif_verified = 1, is_open = 1 WHERE id = ?");
        $stmt->execute([$plusCode, $lat, $lng, $merchantId]);

        // Consultar comercio actualizado
        $mStmt = $pdo->prepare("SELECT m.*, u.name as owner_name FROM merchants m LEFT JOIN users u ON m.user_id = u.id WHERE m.id = ?");
        $mStmt->execute([$merchantId]);
        $merchant = $mStmt->fetch();

        if ($merchant) {
            $merchant['lat'] = floatval($merchant['lat']);
            $merchant['lng'] = floatval($merchant['lng']);

            // Registrar eventos de auditoría y notificación en bitácora
            logUserActivity($pdo, $merchant['user_id'], 'location_assigned', 50, "¡Excelente noticia! El Superusuario te asignó el Plus Code oficial ({$plusCode}) y activó tu comercio en el Mapa GIS.");
            logUserActivity($pdo, $currentUser['id'], 'admin_assigned_location', 30, "Asignaste ubicación oficial {$plusCode} al comercio '{$merchant['commercial_name']}' (#{$merchantId}).");
        }

        $response = [
            'success' => true,
            'message' => "¡Ubicación oficial cargada con éxito! El establecimiento '{$merchant['commercial_name']}' ahora aparece inmediatamente en el Mapa GIS con sus productos disponibles.",
            'merchant' => $merchant
        ];
        break;

    // -------------------------------------------------------------
    // 3. GESTIÓN DE PRODUCTOS / SERVICIOS
    // -------------------------------------------------------------
    case 'save_product':
        $currentUser = getCurrentUser($pdo);
        if (!$currentUser || ($currentUser['role'] !== 'merchant' && $currentUser['role'] !== 'superadmin')) {
            $response = ['success' => false, 'message' => 'Solo comerciantes o el administrador pueden gestionar productos'];
            break;
        }

        $merchantId = intval($_POST['merchant_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $category = trim($_POST['category'] ?? 'General');
        $priceUsd = floatval($_POST['price_usd'] ?? 0);
        $desc = trim($_POST['description'] ?? '');
        $prodId = intval($_POST['product_id'] ?? 0);

        if (empty($name) || $priceUsd <= 0) {
            $response = ['success' => false, 'message' => 'Nombre y precio en USD son obligatorios'];
            break;
        }

        if ($prodId > 0) {
            $stmt = $pdo->prepare("UPDATE products_services SET name = ?, category = ?, price_usd = ?, description = ? WHERE id = ? AND merchant_id = ?");
            $stmt->execute([$name, $category, $priceUsd, $desc, $prodId, $merchantId]);
            $msg = 'Producto actualizado';
        } else {
            $stmt = $pdo->prepare("INSERT INTO products_services (merchant_id, name, category, price_usd, description, is_active) VALUES (?, ?, ?, ?, ?, 1)");
            $stmt->execute([$merchantId, $name, $category, $priceUsd, $desc]);
            $msg = 'Producto agregado al catálogo';
        }

        $response = ['success' => true, 'message' => $msg];
        break;

    case 'delete_product':
        $currentUser = getCurrentUser($pdo);
        if (!$currentUser) {
            $response = ['success' => false, 'message' => 'Debe iniciar sesión'];
            break;
        }
        $prodId = intval($_POST['product_id'] ?? 0);
        $stmt = $pdo->prepare("UPDATE products_services SET is_active = 0 WHERE id = ?");
        $stmt->execute([$prodId]);
        $response = ['success' => true, 'message' => 'Producto eliminado del catálogo'];
        break;

    // -------------------------------------------------------------
    // 4. TRANSACCIONES / COMPRAS (GAMIFICACIÓN Y REPUTACIÓN)
    // -------------------------------------------------------------
    case 'create_purchase':
        $currentUser = getCurrentUser($pdo);
        if (!$currentUser) {
            $response = ['success' => false, 'message' => 'Inicia sesión como visitante para comprar o solicitar servicio'];
            break;
        }

        $merchantId = intval($_POST['merchant_id'] ?? 0);
        $productId = intval($_POST['product_id'] ?? 0);
        $amountUsd = floatval($_POST['amount_usd'] ?? 5.0);

        $bcvRate = floatval($pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'bcv_rate'")->fetchColumn() ?: 54.50);
        $amountBs = round($amountUsd * $bcvRate, 2);

        // Insertar orden completada
        $stmt = $pdo->prepare("INSERT INTO orders_transactions (visitor_id, merchant_id, product_id, amount_usd, amount_bs, status) VALUES (?, ?, ?, ?, ?, 'completed')");
        $stmt->execute([$currentUser['id'], $merchantId, $productId ?: null, $amountUsd, $amountBs]);
        $orderId = $pdo->lastInsertId();

        // Incrementar ventas del comercio y recalcular reputación de ventas
        $pdo->prepare("UPDATE merchants SET sales_count = sales_count + 1 WHERE id = ?")->execute([$merchantId]);

        // Otorgar XP al visitante (base: 15 XP por compra + boosters)
        $xpAward = awardXp($pdo, $currentUser['id'], 'purchase', 20);

        $response = [
            'success' => true,
            'message' => '¡Compra completada con éxito! Has apoyado al comercio local de Paseo Macuto.',
            'order_id' => $orderId,
            'amount_usd' => $amountUsd,
            'amount_bs' => $amountBs,
            'bcv_rate' => $bcvRate,
            'xp_earned' => $xpAward['earned_xp'],
            'multiplier' => $xpAward['multiplier'],
            'level_data' => $xpAward['level_data']
        ];
        break;

    // -------------------------------------------------------------
    // 4.1 CARRITO DE COMPRAS Y PAGO MÓVIL VENEZUELA
    // -------------------------------------------------------------
    case 'get_merchant_pagomovil':
        $merchantId = intval($_GET['merchant_id'] ?? 0);
        $stmt = $pdo->prepare("SELECT id, commercial_name, plus_code, pago_movil_bank, pago_movil_phone, pago_movil_ci, pago_movil_name FROM merchants WHERE id = ?");
        $stmt->execute([$merchantId]);
        $m = $stmt->fetch();
        $bcvRate = floatval($pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'bcv_rate'")->fetchColumn() ?: 54.50);

        if ($m) {
            $response = [
                'success' => true,
                'merchant' => $m,
                'bcv_rate' => $bcvRate
            ];
        } else {
            $response = ['success' => false, 'message' => 'Comercio no encontrado'];
        }
        break;

    case 'submit_cart_payment':
        $currentUser = getCurrentUser($pdo);
        if (!$currentUser) {
            $response = ['success' => false, 'message' => 'Inicia sesión como visitante para procesar el pago del carrito'];
            break;
        }

        $merchantId = intval($_POST['merchant_id'] ?? 0);
        $itemsJson = $_POST['items_json'] ?? '[]';
        $amountUsd = floatval($_POST['amount_usd'] ?? 0);
        $senderBank = trim($_POST['sender_bank'] ?? '');
        $senderPhone = trim($_POST['sender_phone'] ?? '');
        $senderCi = trim($_POST['sender_ci'] ?? '');
        $paymentRef = trim($_POST['payment_reference'] ?? '');
        $proofImage = $_POST['proof_image'] ?? '';

        if ($merchantId <= 0 || $amountUsd <= 0 || empty($senderBank) || empty($senderPhone) || empty($senderCi) || empty($paymentRef)) {
            $response = ['success' => false, 'message' => 'Todos los datos del comprobante son requeridos'];
            break;
        }

        $bcvRate = floatval($pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'bcv_rate'")->fetchColumn() ?: 54.50);
        $amountBs = round($amountUsd * $bcvRate, 2);

        $stmt = $pdo->prepare("INSERT INTO orders_transactions 
            (visitor_id, merchant_id, items_json, amount_usd, amount_bs, bcv_rate_used, sender_bank, sender_phone, sender_ci, payment_reference, proof_image, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending_verification')");
        $stmt->execute([
            $currentUser['id'],
            $merchantId,
            $itemsJson,
            $amountUsd,
            $amountBs,
            $bcvRate,
            $senderBank,
            $senderPhone,
            $senderCi,
            $paymentRef,
            $proofImage
        ]);
        $orderId = $pdo->lastInsertId();

        // Registrar en bitácora
        logUserActivity($pdo, $currentUser['id'], 'order_placed', 0, "Enviaste pago móvil por \$$amountUsd ($amountBs Bs.) a verificar ref: $paymentRef");

        $response = [
            'success' => true,
            'order_id' => $orderId,
            'message' => '¡Tu pago está siendo verificado por el vendedor! En breve será confirmado.',
            'amount_usd' => $amountUsd,
            'amount_bs' => $amountBs,
            'bcv_rate' => $bcvRate
        ];
        break;

    case 'merchant_pending_payments':
        $currentUser = getCurrentUser($pdo);
        if (!$currentUser || $currentUser['role'] !== 'merchant') {
            $response = ['success' => false, 'message' => 'Acceso denegado'];
            break;
        }

        $mStmt = $pdo->prepare("SELECT id, commercial_name FROM merchants WHERE user_id = ?");
        $mStmt->execute([$currentUser['id']]);
        $merchant = $mStmt->fetch();

        if (!$merchant) {
            $response = ['success' => false, 'message' => 'Establecimiento no encontrado'];
            break;
        }

        $tab = $_GET['tab'] ?? 'pending'; // 'pending', 'approved', 'denied'
        $statusFilter = ($tab === 'approved') ? 'completed' : (($tab === 'denied') ? 'denied' : 'pending_verification');

        $stmt = $pdo->prepare("SELECT o.*, u.name as visitor_name, u.email as visitor_email, u.phone as visitor_phone 
                               FROM orders_transactions o 
                               JOIN users u ON o.visitor_id = u.id 
                               WHERE o.merchant_id = ? AND o.status = ? 
                               ORDER BY o.id DESC");
        $stmt->execute([$merchant['id'], $statusFilter]);
        $orders = $stmt->fetchAll();

        // Conteos para las 3 solapas
        $cPending = $pdo->prepare("SELECT COUNT(*) FROM orders_transactions WHERE merchant_id = ? AND status = 'pending_verification'");
        $cPending->execute([$merchant['id']]);
        $countPending = (int)$cPending->fetchColumn();

        $cApproved = $pdo->prepare("SELECT COUNT(*) FROM orders_transactions WHERE merchant_id = ? AND status = 'completed'");
        $cApproved->execute([$merchant['id']]);
        $countApproved = (int)$cApproved->fetchColumn();

        $cDenied = $pdo->prepare("SELECT COUNT(*) FROM orders_transactions WHERE merchant_id = ? AND status = 'denied'");
        $cDenied->execute([$merchant['id']]);
        $countDenied = (int)$cDenied->fetchColumn();

        $response = [
            'success' => true,
            'tab' => $tab,
            'orders' => $orders,
            'counts' => [
                'pending' => $countPending,
                'approved' => $countApproved,
                'denied' => $countDenied
            ]
        ];
        break;

    case 'merchant_process_payment':
        $currentUser = getCurrentUser($pdo);
        if (!$currentUser || $currentUser['role'] !== 'merchant') {
            $response = ['success' => false, 'message' => 'Acceso denegado'];
            break;
        }

        $orderId = intval($_POST['order_id'] ?? 0);
        $decision = $_POST['decision'] ?? 'accept'; // 'accept' o 'deny'
        $reason = trim($_POST['reason'] ?? '');

        // Validar que la orden pertenezca a este comerciante
        $oStmt = $pdo->prepare("SELECT o.*, m.user_id as merchant_user_id, m.commercial_name, m.plus_code 
                                FROM orders_transactions o 
                                JOIN merchants m ON o.merchant_id = m.id 
                                WHERE o.id = ?");
        $oStmt->execute([$orderId]);
        $order = $oStmt->fetch();

        if (!$order || $order['merchant_user_id'] != $currentUser['id']) {
            $response = ['success' => false, 'message' => 'No tienes permiso sobre esta transacción'];
            break;
        }

        if ($decision === 'accept' || $decision === 'approve') {
            $pdo->prepare("UPDATE orders_transactions SET status = 'completed', verified_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([$orderId]);
            $pdo->prepare("UPDATE merchants SET sales_count = sales_count + 1, sales_rep = MIN(5.0, sales_rep + 0.05), total_rep = MIN(5.0, (sales_rep + service_rep) / 2) WHERE id = ?")->execute([$order['merchant_id']]);
            $xpAward = awardXp($pdo, $order['visitor_id'], 'purchase', 25);

            logUserActivity($pdo, $order['visitor_id'], 'payment_verified', 25, "Tu pago de \${$order['amount_usd']} fue verificado exitosamente por {$order['commercial_name']} (Plus Code: {$order['plus_code']}). ¡Compra consolidada!");
            logUserActivity($pdo, $currentUser['id'], 'sale_completed', 0, "Aprobaste el pago de la orden #{$orderId} (\${$order['amount_usd']}) para el comercio de Plus Code {$order['plus_code']}");

            $response = [
                'success' => true,
                'status' => 'completed',
                'message' => '¡Pago aceptado y venta cargada exitosamente! Se sumaron los puntos XP al comprador.',
                'xp_awarded' => $xpAward['earned_xp']
            ];
        } else {
            $pdo->prepare("UPDATE orders_transactions SET status = 'denied', denied_reason = ? WHERE id = ?")->execute([$reason ?: 'Comprobante no coincide o fondos no acreditados', $orderId]);
            
            logUserActivity($pdo, $order['visitor_id'], 'payment_denied', 0, "Tu pago de la orden #{$orderId} fue rechazado por {$order['commercial_name']}. Motivo: " . ($reason ?: 'No conciliado'));
            logUserActivity($pdo, $currentUser['id'], 'sale_denied', 0, "Rechazaste la orden de pago #{$orderId}");

            $response = [
                'success' => true,
                'status' => 'denied',
                'message' => 'Pago rechazado. El usuario ha sido notificado.'
            ];
        }
        break;

    case 'visitor_check_payment_notifications':
        $currentUser = getCurrentUser($pdo);
        if (!$currentUser) {
            $response = ['success' => false, 'orders' => []];
            break;
        }

        $stmt = $pdo->prepare("SELECT o.*, m.commercial_name 
                               FROM orders_transactions o 
                               JOIN merchants m ON o.merchant_id = m.id 
                               WHERE o.visitor_id = ? 
                               ORDER BY o.id DESC LIMIT 10");
        $stmt->execute([$currentUser['id']]);
        $orders = $stmt->fetchAll();

        $response = [
            'success' => true,
            'orders' => $orders
        ];
        break;

    case 'save_merchant_pagomovil_data':
        $currentUser = getCurrentUser($pdo);
        if (!$currentUser || $currentUser['role'] !== 'merchant') {
            $response = ['success' => false, 'message' => 'Acceso denegado'];
            break;
        }

        $bank = trim($_POST['pago_movil_bank'] ?? '');
        $phone = trim($_POST['pago_movil_phone'] ?? '');
        $ci = trim($_POST['pago_movil_ci'] ?? '');
        $name = trim($_POST['pago_movil_name'] ?? '');

        $stmt = $pdo->prepare("UPDATE merchants SET pago_movil_bank = ?, pago_movil_phone = ?, pago_movil_ci = ?, pago_movil_name = ? WHERE user_id = ?");
        $stmt->execute([$bank, $phone, $ci, $name, $currentUser['id']]);

        $response = ['success' => true, 'message' => 'Datos de Pago Móvil guardados con éxito'];
        break;

    // -------------------------------------------------------------
    // 5. RESEÑAS Y DENUNCIAS
    // -------------------------------------------------------------
    case 'submit_review':
        $currentUser = getCurrentUser($pdo);
        if (!$currentUser) {
            $response = ['success' => false, 'message' => 'Debe iniciar sesión para calificar'];
            break;
        }

        $merchantId = intval($_POST['merchant_id'] ?? 0);
        $rating = intval($_POST['rating'] ?? 5);
        $comment = trim($_POST['comment'] ?? '');

        if ($rating < 1 || $rating > 5) {
            $response = ['success' => false, 'message' => 'Calificación debe ser entre 1 y 5 estrellas'];
            break;
        }

        // Verificar si tiene compra
        $orderCheck = $pdo->prepare("SELECT id FROM orders_transactions WHERE visitor_id = ? AND merchant_id = ? LIMIT 1");
        $orderCheck->execute([$currentUser['id'], $merchantId]);
        $hasOrder = $orderCheck->fetch();

        $stmt = $pdo->prepare("INSERT INTO reviews (visitor_id, merchant_id, order_id, rating, comment, verified_purchase) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$currentUser['id'], $merchantId, $hasOrder ? $hasOrder['id'] : null, $rating, $comment, $hasOrder ? 1 : 0]);

        // Recalcular reputación del comercio:
        // Service Rep = promedio de reviews
        $avgStmt = $pdo->prepare("SELECT AVG(rating) as avg_rate FROM reviews WHERE merchant_id = ?");
        $avgStmt->execute([$merchantId]);
        $serviceRep = round(floatval($avgStmt->fetchColumn() ?: 5.0), 2);

        // Penalizaciones por denuncias admitidas
        $compCount = $pdo->prepare("SELECT COUNT(*) FROM complaints WHERE merchant_id = ? AND status = 'penalized'");
        $compCount->execute([$merchantId]);
        $penalties = intval($compCount->fetchColumn()) * 0.40; // Resta 0.40 por denuncia comprobada
        $serviceRep = max(1.0, round($serviceRep - $penalties, 2));

        // Total Rep = 40% Ventas Rep + 60% Service Rep
        $mStmt = $pdo->prepare("SELECT sales_rep FROM merchants WHERE id = ?");
        $mStmt->execute([$merchantId]);
        $salesRep = floatval($mStmt->fetchColumn() ?: 5.0);

        $totalRep = round(($salesRep * 0.40) + ($serviceRep * 0.60), 2);

        $upStmt = $pdo->prepare("UPDATE merchants SET service_rep = ?, total_rep = ? WHERE id = ?");
        $upStmt->execute([$serviceRep, $totalRep, $merchantId]);

        // Otorgar XP al visitante por aportar reseña
        $xpAward = awardXp($pdo, $currentUser['id'], 'review', 15);

        $response = [
            'success' => true,
            'message' => '¡Tu valoración ha sido publicada! Gracias por fortalecer la comunidad.',
            'new_total_rep' => $totalRep,
            'xp_earned' => $xpAward['earned_xp'],
            'level_data' => $xpAward['level_data']
        ];
        break;

    case 'submit_complaint':
        $currentUser = getCurrentUser($pdo);
        if (!$currentUser) {
            $response = ['success' => false, 'message' => 'Debe iniciar sesión para emitir una denuncia formal'];
            break;
        }

        $merchantId = intval($_POST['merchant_id'] ?? 0);
        $desc = trim($_POST['description'] ?? '');

        if (empty($desc)) {
            $response = ['success' => false, 'message' => 'Por favor detalle el motivo de la queja o denuncia'];
            break;
        }

        $stmt = $pdo->prepare("INSERT INTO complaints (visitor_id, merchant_id, description, status) VALUES (?, ?, ?, 'pending')");
        $stmt->execute([$currentUser['id'], $merchantId, $desc]);

        $response = [
            'success' => true,
            'message' => 'Tu denuncia ha sido registrada de forma segura. El Superusuario auditará el caso inmediatamente.'
        ];
        break;

    // -------------------------------------------------------------
    // 6. AUTENTICACIÓN Y SESIÓN
    // -------------------------------------------------------------
    case 'login':
        $email = strtolower(trim($_POST['email'] ?? ''));
        $password = $_POST['password'] ?? '';

        if (empty($email) || empty($password)) {
            $response = ['success' => false, 'message' => 'Ingrese correo y contraseña'];
            break;
        }

        $user = null;
        $supabaseSession = null;

        // 1. Intentar autenticar contra Supabase Auth en la nube
        if (SupabaseAuthService::isConfigured()) {
            $sbRes = SupabaseAuthService::signInWithPassword($email, $password);
            if ($sbRes['success']) {
                $supabaseSession = [
                    'access_token' => $sbRes['access_token'],
                    'refresh_token' => $sbRes['refresh_token'],
                    'expires_in' => $sbRes['expires_in']
                ];
                $sbUid = $sbRes['user']['id'] ?? null;

                // Buscar perfil del usuario en la base de datos
                $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? OR supabase_uid = ?");
                $stmt->execute([$email, $sbUid]);
                $user = $stmt->fetch();

                // Si no tiene supabase_uid guardado, vincularlo
                if ($user && empty($user['supabase_uid']) && $sbUid) {
                    $pdo->prepare("UPDATE users SET supabase_uid = ? WHERE id = ?")->execute([$sbUid, $user['id']]);
                }
            }
        }

        // 2. Si Supabase Auth no autenticó o está en transición, fallback seguro a public.users
        if (!$user) {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $localUser = $stmt->fetch();

            if ($localUser && password_verify($password, $localUser['password_hash'])) {
                $user = $localUser;
            }
        }

        if ($user) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_role'] = $user['role'];
            if ($supabaseSession) {
                $_SESSION['supabase_access_token'] = $supabaseSession['access_token'];
            }

            // Si es comerciante, buscar su comercio
            $merchantInfo = null;
            if ($user['role'] === 'merchant') {
                $mStmt = $pdo->prepare("SELECT * FROM merchants WHERE user_id = ?");
                $mStmt->execute([$user['id']]);
                $merchantInfo = $mStmt->fetch();
            }

            // Award daily login bonus
            $xpAward = awardXp($pdo, $user['id'], 'visit', 5);

            $levelData = calculateLevelAndProgress($xpAward['new_xp']);

            $response = [
                'success' => true,
                'message' => '¡Bienvenido a Paseo Macuto, ' . htmlspecialchars($user['name']) . '!',
                'supabase_auth' => ($supabaseSession !== null),
                'supabase_session' => $supabaseSession,
                'user' => [
                    'id' => $user['id'],
                    'name' => $user['name'],
                    'email' => $user['email'],
                    'role' => $user['role'],
                    'merchant' => $merchantInfo,
                    'level_data' => $levelData
                ]
            ];
        } else {
            $response = ['success' => false, 'message' => 'Correo o contraseña incorrectos'];
        }
        break;

    case 'recover_password':
        $email = strtolower(trim($_POST['email'] ?? ''));
        if (empty($email)) {
            $response = ['success' => false, 'message' => 'Ingrese su correo electrónico para recuperación'];
            break;
        }

        if (SupabaseAuthService::isConfigured()) {
            $recRes = SupabaseAuthService::resetPasswordForEmail($email);
            if ($recRes['success']) {
                $response = [
                    'success' => true,
                    'message' => 'Se ha enviado un correo con instrucciones de restablecimiento de contraseña.'
                ];
            } else {
                $response = [
                    'success' => false,
                    'message' => 'No se pudo enviar el correo de recuperación: ' . $recRes['error']
                ];
            }
        } else {
            $response = ['success' => false, 'message' => 'Servicio de recuperación no disponible en este momento'];
        }
        break;

    case 'register':
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $role = $_POST['role'] ?? 'visitor'; // 'visitor' o 'merchant'
        $phone = trim($_POST['phone'] ?? '');

        if (empty($name) || empty($email) || empty($password)) {
            $response = ['success' => false, 'message' => 'Todos los campos obligatorios deben ser completados'];
            break;
        }

        // Validar si ya existe
        $check = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $check->execute([$email]);
        if ($check->fetch()) {
            $response = ['success' => false, 'message' => 'El correo electrónico ya se encuentra registrado'];
            break;
        }

        // Si es comerciante, validar datos comerciales y RIF
        $commercialName = '';
        $rif = '';
        $sector = 'Paseo Macuto';
        $category = 'Gastronomía y Bebidas';
        $lat = 0.0;
        $lng = 0.0;
        $plusCode = null;

        if ($role === 'merchant') {
            $commercialName = trim($_POST['commercial_name'] ?? '');
            $rif = strtoupper(trim($_POST['rif'] ?? ''));
            $sector = trim($_POST['sector'] ?? 'Paseo Macuto');
            $category = $_POST['category'] ?? 'Gastronomía y Bebidas';

            if (empty($commercialName) || empty($rif)) {
                $response = ['success' => false, 'message' => 'Comerciante debe ingresar Nombre del Establecimiento y RIF'];
                break;
            }

            // Validar formato de RIF (V, J, G, E seguido de números y guiones)
            if (!preg_match('/^[VJEG]-?\d{7,9}-?\d?$/i', $rif)) {
                $response = ['success' => false, 'message' => 'Formato de RIF inválido. Ejemplo: J-12345678-0 o V-12345678-9'];
                break;
            }
        }

        // Registrar en Supabase Auth si está configurado
        $supabaseUid = null;
        if (SupabaseAuthService::isConfigured()) {
            $sbRes = SupabaseAuthService::signUp($email, $password, [
                'name' => $name,
                'role' => $role
            ]);
            if ($sbRes['success'] && !empty($sbRes['user']['id'])) {
                $supabaseUid = $sbRes['user']['id'];
            }
        }

        $passHash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare("INSERT INTO users (name, email, password_hash, role, level, xp, phone, supabase_uid) VALUES (?, ?, ?, ?, 1, 0, ?, ?)");
        $stmt->execute([$name, $email, $passHash, $role, $phone, $supabaseUid]);
        $newUserId = $pdo->lastInsertId();

        // Si es comerciante, crear su registro de establecimiento con RIF en estado pendiente (0) y sin ubicación en mapa (0, 0)
        // La ubicación (Plus Code y GPS) solo puede ser asignada por el Superusuario tras auditar el RIF
        $newMerchantId = null;
        if ($role === 'merchant') {
            $mStmt = $pdo->prepare("INSERT INTO merchants (user_id, commercial_name, rif, rif_verified, category, sector, lat, lng, plus_code, phone, description, is_open, sales_rep, service_rep, total_rep) VALUES (?, ?, ?, 0, ?, ?, 0, 0, NULL, ?, ?, 0, 5.0, 5.0, 5.0)");
            $mStmt->execute([
                $newUserId,
                $commercialName,
                $rif,
                $category,
                $sector,
                $phone,
                'Nuevo emprendimiento en Paseo Macuto. ¡Próximamente disponible!'
            ]);
            $newMerchantId = $pdo->lastInsertId();

            // Bitácora de actividad histórica
            logUserActivity($pdo, $newUserId, 'registered_merchant', 20, "Registro de comerciante: $commercialName (RIF: $rif). Estatus: En verificación por el Superadministrador.");
        }

        $_SESSION['user_id'] = $newUserId;
        $_SESSION['user_role'] = $role;

        $levelData = calculateLevelAndProgress(0);

        $response = [
            'success' => true,
            'is_merchant_pending' => ($role === 'merchant'),
            'message' => ($role === 'merchant'
                ? '¡Registro exitoso! Tu cuenta de comerciante ha sido creada y su estatus está en verificación. El Superadministrador auditará tu RIF y asignará la ubicación oficial en el mapa para activar tus ventas.'
                : '¡Registro exitoso en Paseo Macuto! Bienvenido.'),
            'user' => [
                'id' => $newUserId,
                'name' => $name,
                'email' => $email,
                'role' => $role,
                'level_data' => $levelData
            ]
        ];
        break;

    case 'logout':
        session_destroy();
        $response = ['success' => true, 'message' => 'Sesión cerrada correctamente'];
        break;

    case 'current_user':
        $user = getCurrentUser($pdo);
        if ($user) {
            $levelData = calculateLevelAndProgress($user['xp']);
            $merchantInfo = null;
            if ($user['role'] === 'merchant') {
                $mStmt = $pdo->prepare("SELECT * FROM merchants WHERE user_id = ?");
                $mStmt->execute([$user['id']]);
                $merchantInfo = $mStmt->fetch();
                if ($merchantInfo) {
                    $merchantInfo['lat'] = floatval($merchantInfo['lat']);
                    $merchantInfo['lng'] = floatval($merchantInfo['lng']);
                }
            }

            $response = [
                'success' => true,
                'user' => [
                    'id' => $user['id'],
                    'name' => $user['name'],
                    'email' => $user['email'],
                    'role' => $user['role'],
                    'phone' => $user['phone'],
                    'merchant' => $merchantInfo,
                    'level_data' => $levelData
                ]
            ];
        } else {
            $response = ['success' => false, 'message' => 'No hay sesión activa'];
        }
        break;

    // -------------------------------------------------------------
    // 7. SUPERUSUARIO: KPIS, AUDITORÍA DE RIF, QUEJAS Y BOOSTERS
    // -------------------------------------------------------------
    case 'admin_kpis':
        $currentUser = getCurrentUser($pdo);
        if (!$currentUser || $currentUser['role'] !== 'superadmin') {
            $response = ['success' => false, 'message' => 'Acceso denegado'];
            break;
        }

        // Total comerciantes activos
        $totalMerchants = (int)$pdo->query("SELECT COUNT(*) FROM merchants")->fetchColumn();
        
        // Capacidad estimada de Paseo Macuto: 180 comerciantes
        $capacityMax = 180;
        $capacityPercent = min(100, round(($totalMerchants / $capacityMax) * 100, 1));

        // RIF pendientes de verificación
        $pendingRifCount = (int)$pdo->query("SELECT COUNT(*) FROM merchants WHERE rif_verified = 0")->fetchColumn();

        // Total visitantes registrados
        $totalVisitors = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'visitor'")->fetchColumn();

        // Total transacciones y volumen
        $salesStmt = $pdo->query("SELECT COUNT(*) as total_sales, COALESCE(SUM(amount_usd), 0) as total_usd, COALESCE(SUM(amount_bs), 0) as total_bs FROM orders_transactions WHERE status = 'completed'");
        $salesData = $salesStmt->fetch();

        // Quejas pendientes
        $pendingComplaints = (int)$pdo->query("SELECT COUNT(*) FROM complaints WHERE status = 'pending'")->fetchColumn();

        // Promedio satisfacción
        $avgSat = floatval($pdo->query("SELECT COALESCE(AVG(total_rep), 5.0) FROM merchants")->fetchColumn());

        // Tasa BCV
        $bcvRate = floatval($pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'bcv_rate'")->fetchColumn() ?: 54.50);

        $response = [
            'success' => true,
            'kpis' => [
                'total_merchants' => $totalMerchants,
                'capacity_max' => $capacityMax,
                'capacity_percent' => $capacityPercent,
                'pending_rif_count' => $pendingRifCount,
                'total_visitors' => $totalVisitors,
                'total_sales' => (int)$salesData['total_sales'],
                'total_usd' => floatval($salesData['total_usd']),
                'total_bs' => floatval($salesData['total_bs']),
                'pending_complaints' => $pendingComplaints,
                'avg_satisfaction' => round($avgSat, 2),
                'bcv_rate' => $bcvRate
            ]
        ];
        break;

    case 'admin_pending_verifications':
        $currentUser = getCurrentUser($pdo);
        if (!$currentUser || $currentUser['role'] !== 'superadmin') {
            $response = ['success' => false, 'message' => 'Acceso denegado'];
            break;
        }

        $stmt = $pdo->query("SELECT m.*, u.name as owner_name, u.email as owner_email, u.phone as owner_phone FROM merchants m LEFT JOIN users u ON m.user_id = u.id WHERE m.rif_verified = 0 ORDER BY m.id DESC");
        $pending = $stmt->fetchAll();

        $response = [
            'success' => true,
            'count' => count($pending),
            'pending_merchants' => $pending
        ];
        break;

    case 'admin_verify_rif':
        $currentUser = getCurrentUser($pdo);
        if (!$currentUser || $currentUser['role'] !== 'superadmin') {
            $response = ['success' => false, 'message' => 'Acceso denegado'];
            break;
        }

        $merchantId = intval($_POST['merchant_id'] ?? 0);
        $status = intval($_POST['status'] ?? 1); // 1 = Aprobado, -1 = Rechazado

        $stmt = $pdo->prepare("UPDATE merchants SET rif_verified = ? WHERE id = ?");
        $stmt->execute([$status, $merchantId]);

        $statusText = ($status === 1) ? 'RIF Verificado y Sello Oficial Aprobado' : 'RIF Rechazado por inconsistencia';

        $response = [
            'success' => true,
            'message' => "Comercio #$merchantId actualizado: $statusText",
            'status' => $status
        ];
        break;

    case 'admin_complaints':
        $currentUser = getCurrentUser($pdo);
        if (!$currentUser || $currentUser['role'] !== 'superadmin') {
            $response = ['success' => false, 'message' => 'Acceso denegado'];
            break;
        }

        $stmt = $pdo->query("SELECT c.*, m.commercial_name, m.rif, u.name as visitor_name, u.email as visitor_email FROM complaints c JOIN merchants m ON c.merchant_id = m.id JOIN users u ON c.visitor_id = u.id ORDER BY c.id DESC");
        $complaints = $stmt->fetchAll();

        $response = [
            'success' => true,
            'complaints' => $complaints
        ];
        break;

    case 'admin_resolve_complaint':
        $currentUser = getCurrentUser($pdo);
        if (!$currentUser || $currentUser['role'] !== 'superadmin') {
            $response = ['success' => false, 'message' => 'Acceso denegado'];
            break;
        }

        $complaintId = intval($_POST['complaint_id'] ?? 0);
        $newStatus = $_POST['status'] ?? 'reviewed'; // reviewed, penalized, resolved
        $notes = trim($_POST['admin_notes'] ?? '');

        $stmt = $pdo->prepare("UPDATE complaints SET status = ?, admin_notes = ? WHERE id = ?");
        $stmt->execute([$newStatus, $notes, $complaintId]);

        // Si se penaliza, recalcular reputación del comerciante afectado
        if ($newStatus === 'penalized') {
            $cStmt = $pdo->prepare("SELECT merchant_id FROM complaints WHERE id = ?");
            $cStmt->execute([$complaintId]);
            $merchantId = $cStmt->fetchColumn();

            if ($merchantId) {
                // Descontar penalización en service_rep
                $pdo->prepare("UPDATE merchants SET service_rep = MAX(1.0, service_rep - 0.50), total_rep = MAX(1.0, total_rep - 0.35) WHERE id = ?")->execute([$merchantId]);
            }
        }

        $response = ['success' => true, 'message' => "Estado de denuncia actualizado a $newStatus"];
        break;

    case 'admin_boosters':
        $currentUser = getCurrentUser($pdo);
        if (!$currentUser || $currentUser['role'] !== 'superadmin') {
            $response = ['success' => false, 'message' => 'Acceso denegado'];
            break;
        }

        // Si es POST, guardar o alternar
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $subAction = $_POST['sub_action'] ?? '';
            if ($subAction === 'toggle') {
                $boosterId = intval($_POST['booster_id'] ?? 0);
                $pdo->prepare("UPDATE xp_boosters SET is_active = CASE WHEN is_active = 1 THEN 0 ELSE 1 END WHERE id = ?")->execute([$boosterId]);
                $response = ['success' => true, 'message' => 'Estado de potenciador actualizado'];
                break;
            } elseif ($subAction === 'create') {
                $name = trim($_POST['name'] ?? '');
                $multiplier = floatval($_POST['multiplier'] ?? 1.5);
                $actionType = $_POST['action_type'] ?? 'purchase';
                $desc = trim($_POST['description'] ?? '');

                if (empty($name) || $multiplier <= 1.0) {
                    $response = ['success' => false, 'message' => 'Nombre y multiplicador mayor a 1.0 son requeridos'];
                    break;
                }

                $stmt = $pdo->prepare("INSERT INTO xp_boosters (name, multiplier, action_type, is_active, description) VALUES (?, ?, ?, 1, ?)");
                $stmt->execute([$name, $multiplier, $actionType, $desc]);
                $response = ['success' => true, 'message' => '¡Nuevo potenciador de XP activado con éxito!'];
                break;
            }
        }

        // Listar
        $stmt = $pdo->query("SELECT * FROM xp_boosters ORDER BY is_active DESC, id DESC");
        $boosters = $stmt->fetchAll();
        $response = [
            'success' => true,
            'boosters' => $boosters
        ];
        break;

    // -------------------------------------------------------------
    // 8. MÓDULOS DE PANEL RETRÁCTIL (SUPERADMIN, VISITANTE, COMERCIANTE)
    // -------------------------------------------------------------
    case 'get_module_data':
        $module = $_GET['module'] ?? '';
        $currentUser = getCurrentUser($pdo);
        $bcvRate = floatval($pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'bcv_rate'")->fetchColumn() ?: 54.50);

        switch ($module) {
            // --- SUPERUSUARIO ---
            case 'admin_users':
                if (!$currentUser || $currentUser['role'] !== 'superadmin') {
                    $response = ['success' => false, 'message' => 'Acceso restringido a Superusuario'];
                    break;
                }
                $users = $pdo->query("SELECT id, name, email, role, level, xp, phone, status, created_at FROM users ORDER BY id DESC")->fetchAll();
                $response = ['success' => true, 'module' => $module, 'data' => $users, 'bcv_rate' => $bcvRate];
                break;

            case 'admin_products':
                if (!$currentUser || $currentUser['role'] !== 'superadmin') {
                    $response = ['success' => false, 'message' => 'Acceso restringido a Superusuario'];
                    break;
                }
                $stmt = $pdo->query("SELECT p.*, m.commercial_name, m.sector FROM products_services p JOIN merchants m ON p.merchant_id = m.id ORDER BY p.id DESC");
                $prods = $stmt->fetchAll();
                foreach ($prods as &$p) {
                    $p['price_usd'] = floatval($p['price_usd']);
                    $p['price_bs'] = round($p['price_usd'] * $bcvRate, 2);
                }
                $response = ['success' => true, 'module' => $module, 'data' => $prods, 'bcv_rate' => $bcvRate];
                break;

            case 'admin_incidents':
            case 'admin_denuncias':
                if (!$currentUser || $currentUser['role'] !== 'superadmin') {
                    $response = ['success' => false, 'message' => 'Acceso restringido a Superusuario'];
                    break;
                }
                $stmt = $pdo->query("SELECT c.*, m.commercial_name, m.rif, u.name as visitor_name, u.email as visitor_email FROM complaints c JOIN merchants m ON c.merchant_id = m.id JOIN users u ON c.visitor_id = u.id ORDER BY c.id DESC");
                $complaints = $stmt->fetchAll();
                $response = ['success' => true, 'module' => $module, 'data' => $complaints, 'bcv_rate' => $bcvRate];
                break;

            case 'admin_purchases':
                if (!$currentUser || $currentUser['role'] !== 'superadmin') {
                    $response = ['success' => false, 'message' => 'Acceso restringido a Superusuario'];
                    break;
                }
                $stmt = $pdo->query("SELECT o.*, u.name as visitor_name, m.commercial_name, p.name as product_name 
                                     FROM orders_transactions o 
                                     JOIN users u ON o.visitor_id = u.id 
                                     JOIN merchants m ON o.merchant_id = m.id 
                                     LEFT JOIN products_services p ON o.product_id = p.id 
                                     ORDER BY o.id DESC");
                $orders = $stmt->fetchAll();
                $response = ['success' => true, 'module' => $module, 'data' => $orders, 'bcv_rate' => $bcvRate];
                break;

            case 'admin_reviews':
                if (!$currentUser || $currentUser['role'] !== 'superadmin') {
                    $response = ['success' => false, 'message' => 'Acceso restringido a Superusuario'];
                    break;
                }
                $stmt = $pdo->query("SELECT r.*, u.name as visitor_name, m.commercial_name 
                                     FROM reviews r 
                                     JOIN users u ON r.visitor_id = u.id 
                                     JOIN merchants m ON r.merchant_id = m.id 
                                     ORDER BY r.id DESC");
                $reviews = $stmt->fetchAll();
                $response = ['success' => true, 'module' => $module, 'data' => $reviews, 'bcv_rate' => $bcvRate];
                break;

            case 'admin_reputations':
                if (!$currentUser || $currentUser['role'] !== 'superadmin') {
                    $response = ['success' => false, 'message' => 'Acceso restringido a Superusuario'];
                    break;
                }
                $merchants = $pdo->query("SELECT id, commercial_name, rif, rif_verified, category, sector, sales_count, sales_rep, service_rep, total_rep FROM merchants ORDER BY total_rep DESC, sales_count DESC")->fetchAll();
                $response = ['success' => true, 'module' => $module, 'data' => $merchants, 'bcv_rate' => $bcvRate];
                break;

            // --- VISITANTE ---
            case 'visitor_products':
                $stmt = $pdo->query("SELECT p.*, m.commercial_name, m.sector FROM products_services p JOIN merchants m ON p.merchant_id = m.id WHERE p.is_active = 1 ORDER BY p.id DESC");
                $prods = $stmt->fetchAll();
                foreach ($prods as &$p) {
                    $p['price_usd'] = floatval($p['price_usd']);
                    $p['price_bs'] = round($p['price_usd'] * $bcvRate, 2);
                }
                $response = ['success' => true, 'module' => $module, 'data' => $prods, 'bcv_rate' => $bcvRate];
                break;

            case 'visitor_services':
                $stmt = $pdo->query("SELECT p.*, m.commercial_name, m.sector, m.phone FROM products_services p JOIN merchants m ON p.merchant_id = m.id WHERE p.is_active = 1 AND (m.category LIKE '%Toldo%' OR m.category LIKE '%Deporte%' OR m.category LIKE '%Hospedaje%' OR m.category LIKE '%Pescador%') ORDER BY p.id DESC");
                $services = $stmt->fetchAll();
                foreach ($services as &$s) {
                    $s['price_usd'] = floatval($s['price_usd']);
                    $s['price_bs'] = round($s['price_usd'] * $bcvRate, 2);
                }
                $response = ['success' => true, 'module' => $module, 'data' => $services, 'bcv_rate' => $bcvRate];
                break;

            case 'visitor_complaints':
                if (!$currentUser) {
                    $response = ['success' => false, 'message' => 'Inicia sesión para ver el estatus de tus quejas'];
                    break;
                }
                $stmt = $pdo->prepare("SELECT c.*, m.commercial_name, m.rif FROM complaints c JOIN merchants m ON c.merchant_id = m.id WHERE c.visitor_id = ? ORDER BY c.id DESC");
                $stmt->execute([$currentUser['id']]);
                $myComplaints = $stmt->fetchAll();
                $response = ['success' => true, 'module' => $module, 'data' => $myComplaints, 'bcv_rate' => $bcvRate];
                break;

            case 'visitor_purchases':
                if (!$currentUser) {
                    $response = ['success' => false, 'message' => 'Inicia sesión para consultar el estatus de tus compras'];
                    break;
                }
                $stmt = $pdo->prepare("SELECT o.*, m.commercial_name, m.plus_code, m.sector, m.pago_movil_phone, m.pago_movil_bank, m.phone as merchant_phone 
                                       FROM orders_transactions o 
                                       JOIN merchants m ON o.merchant_id = m.id 
                                       WHERE o.visitor_id = ? 
                                       ORDER BY o.id DESC");
                $stmt->execute([$currentUser['id']]);
                $myOrders = $stmt->fetchAll();

                $pending = [];
                $completed = [];
                $denied = [];

                foreach ($myOrders as $ord) {
                    $ord['items'] = json_decode($ord['items_json'] ?? '[]', true);
                    // Chequear si ya dejó reseña
                    $revCheck = $pdo->prepare("SELECT id, rating FROM reviews WHERE visitor_id = ? AND merchant_id = ? LIMIT 1");
                    $revCheck->execute([$currentUser['id'], $ord['merchant_id']]);
                    $existingRev = $revCheck->fetch();
                    $ord['has_review'] = $existingRev ? true : false;
                    $ord['review_rating'] = $existingRev ? intval($existingRev['rating']) : null;

                    if ($ord['status'] === 'completed') {
                        $completed[] = $ord;
                    } elseif ($ord['status'] === 'denied' || $ord['status'] === 'cancelled') {
                        $denied[] = $ord;
                    } else {
                        $pending[] = $ord;
                    }
                }

                $response = [
                    'success' => true,
                    'module' => $module,
                    'bcv_rate' => $bcvRate,
                    'data' => [
                        'pending' => $pending,
                        'completed' => $completed,
                        'denied' => $denied,
                        'counts' => [
                            'pending' => count($pending),
                            'completed' => count($completed),
                            'denied' => count($denied),
                            'total' => count($myOrders)
                        ]
                    ]
                ];
                break;

            // --- COMERCIANTE ---
            case 'merchant_finances':
                if (!$currentUser || $currentUser['role'] !== 'merchant') {
                    $response = ['success' => false, 'message' => 'Acceso exclusivo para comerciantes'];
                    break;
                }
                $mStmt = $pdo->prepare("SELECT id, commercial_name FROM merchants WHERE user_id = ?");
                $mStmt->execute([$currentUser['id']]);
                $merchant = $mStmt->fetch();

                if (!$merchant) {
                    $response = ['success' => false, 'message' => 'No se encontró tu establecimiento'];
                    break;
                }

                $stmtOrders = $pdo->prepare("SELECT COUNT(*) as count_orders, COALESCE(SUM(amount_usd), 0) as total_usd, COALESCE(SUM(amount_bs), 0) as total_bs FROM orders_transactions WHERE merchant_id = ? AND status = 'completed'");
                $stmtOrders->execute([$merchant['id']]);
                $finances = $stmtOrders->fetch();

                $response = [
                    'success' => true,
                    'module' => $module,
                    'merchant_name' => $merchant['commercial_name'],
                    'bcv_rate' => $bcvRate,
                    'data' => [
                        'total_usd' => floatval($finances['total_usd']),
                        'total_bs' => floatval($finances['total_bs']),
                        'count_orders' => intval($finances['count_orders']),
                        'balance_usd' => floatval($finances['total_usd']) * 0.95 // Balance neto disponible
                    ]
                ];
                break;

            case 'merchant_complaints_requests':
                if (!$currentUser || $currentUser['role'] !== 'merchant') {
                    $response = ['success' => false, 'message' => 'Acceso exclusivo para comerciantes'];
                    break;
                }
                $mStmt = $pdo->prepare("SELECT id FROM merchants WHERE user_id = ?");
                $mStmt->execute([$currentUser['id']]);
                $merchantId = $mStmt->fetchColumn();

                $stmt = $pdo->prepare("SELECT c.*, u.name as visitor_name FROM complaints c JOIN users u ON c.visitor_id = u.id WHERE c.merchant_id = ? ORDER BY c.id DESC");
                $stmt->execute([$merchantId]);
                $complaints = $stmt->fetchAll();

                $response = ['success' => true, 'module' => $module, 'data' => $complaints, 'bcv_rate' => $bcvRate];
                break;

            default:
                $response = ['success' => false, 'message' => 'Módulo no reconocido'];
                break;
        }
        break;

    // -------------------------------------------------------------
    // CARRITO DE COMPRAS Y PAGO MÓVIL
    // -------------------------------------------------------------
    case 'get_merchant_pagomovil':
        $merchantId = intval($_GET['merchant_id'] ?? 0);
        $stmt = $pdo->prepare("SELECT id, commercial_name, pago_movil_bank, pago_movil_phone, pago_movil_ci, pago_movil_name FROM merchants WHERE id = ?");
        $stmt->execute([$merchantId]);
        $merchant = $stmt->fetch();
        if (!$merchant) {
            $response = ['success' => false, 'message' => 'Comercio no encontrado'];
            break;
        }
        $response = ['success' => true, 'merchant' => $merchant];
        break;

    case 'submit_cart_payment':
        $currentUser = getCurrentUser($pdo);
        if (!$currentUser) {
            $response = ['success' => false, 'message' => 'Debes iniciar sesión para realizar compras y registrar pagos'];
            break;
        }

        $merchantId = intval($_POST['merchant_id'] ?? 0);
        $itemsJson = $_POST['items_json'] ?? '[]';
        $amountUsd = floatval($_POST['amount_usd'] ?? 0);
        $senderBank = trim($_POST['sender_bank'] ?? '');
        $senderPhone = trim($_POST['sender_phone'] ?? '');
        $senderCi = trim($_POST['sender_ci'] ?? '');
        $paymentRef = trim($_POST['payment_reference'] ?? '');
        $proofImage = $_POST['proof_image'] ?? '';

        if ($merchantId <= 0 || $amountUsd <= 0 || empty($senderBank) || empty($senderPhone) || empty($senderCi) || empty($paymentRef)) {
            $response = ['success' => false, 'message' => 'Por favor completa todos los datos requeridos del Pago Móvil'];
            break;
        }

        // Tasa BCV actual
        $bcvRate = floatval($pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'bcv_rate'")->fetchColumn() ?: 54.50);
        $amountBs = round($amountUsd * $bcvRate, 2);

        $now = date('Y-m-d H:i:s');
        $stmt = $pdo->prepare("INSERT INTO orders_transactions 
            (visitor_id, merchant_id, items_json, amount_usd, amount_bs, bcv_rate_used, sender_bank, sender_phone, sender_ci, payment_reference, proof_image, status, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?)");
        $stmt->execute([
            $currentUser['id'],
            $merchantId,
            $itemsJson,
            $amountUsd,
            $amountBs,
            $bcvRate,
            $senderBank,
            $senderPhone,
            $senderCi,
            $paymentRef,
            $proofImage,
            $now
        ]);

        $orderId = $pdo->lastInsertId();

        // Registrar en la bitácora de actividad de ambos participantes
        logUserActivity($pdo, $currentUser['id'], 'payment_sent', 0, "Envío de Pago Móvil por \${$amountUsd} ({$amountBs} Bs.) - Orden #{$orderId}");
        
        $mUserStmt = $pdo->prepare("SELECT user_id, commercial_name FROM merchants WHERE id = ?");
        $mUserStmt->execute([$merchantId]);
        $merchantInfo = $mUserStmt->fetch();
        if ($merchantInfo && !empty($merchantInfo['user_id'])) {
            logUserActivity($pdo, $merchantInfo['user_id'], 'payment_received_pending', 0, "Nuevo Pago Móvil recibido de {$currentUser['name']} por \${$amountUsd} ({$amountBs} Bs.) - Orden #{$orderId}");
        }

        $response = [
            'success' => true,
            'order_id' => $orderId,
            'message' => '¡Tu pago está siendo verificado por el vendedor! En breve será confirmado.',
            'amount_usd' => $amountUsd,
            'amount_bs' => $amountBs
        ];
        break;

    case 'merchant_pending_payments':
        $currentUser = getCurrentUser($pdo);
        if (!$currentUser) {
            $response = ['success' => false, 'message' => 'Debe iniciar sesión'];
            break;
        }

        $mStmt = $pdo->prepare("SELECT id, commercial_name FROM merchants WHERE user_id = ?");
        $mStmt->execute([$currentUser['id']]);
        $merchant = $mStmt->fetch();

        if (!$merchant && $currentUser['role'] !== 'superadmin') {
            $response = ['success' => false, 'message' => 'Solo comercios autorizados pueden verificar pagos'];
            break;
        }

        $merchantId = $merchant ? $merchant['id'] : intval($_GET['merchant_id'] ?? 1);
        $tab = $_GET['tab'] ?? 'pending';

        $statusFilter = 'pending';
        if ($tab === 'approved') $statusFilter = 'completed';
        if ($tab === 'denied') $statusFilter = 'cancelled';

        $stmt = $pdo->prepare("SELECT o.*, u.name as visitor_name, u.email as visitor_email, u.phone as visitor_phone, u.level as visitor_level 
                               FROM orders_transactions o 
                               JOIN users u ON o.visitor_id = u.id 
                               WHERE o.merchant_id = ? AND o.status = ? 
                               ORDER BY o.created_at DESC");
        $stmt->execute([$merchantId, $statusFilter]);
        $orders = $stmt->fetchAll();

        // Conteo general por solapa para insignias
        $cStmt = $pdo->prepare("SELECT status, COUNT(*) as count FROM orders_transactions WHERE merchant_id = ? GROUP BY status");
        $cStmt->execute([$merchantId]);
        $counts = ['pending' => 0, 'completed' => 0, 'cancelled' => 0];
        while ($row = $cStmt->fetch()) {
            if ($row['status'] === 'pending') $counts['pending'] = intval($row['count']);
            if ($row['status'] === 'completed') $counts['completed'] = intval($row['count']);
            if ($row['status'] === 'cancelled') $counts['cancelled'] = intval($row['count']);
        }

        $response = [
            'success' => true,
            'tab' => $tab,
            'counts' => $counts,
            'orders' => $orders
        ];
        break;

    case 'merchant_process_payment':
        $currentUser = getCurrentUser($pdo);
        if (!$currentUser) {
            $response = ['success' => false, 'message' => 'Debe iniciar sesión'];
            break;
        }

        $orderId = intval($_POST['order_id'] ?? 0);
        $decision = trim($_POST['decision'] ?? ''); // 'approve' | 'deny'
        $reason = trim($_POST['reason'] ?? '');

        if ($orderId <= 0 || !in_array($decision, ['approve', 'deny'])) {
            $response = ['success' => false, 'message' => 'Parámetros no válidos'];
            break;
        }

        // Buscar orden
        $stmt = $pdo->prepare("SELECT * FROM orders_transactions WHERE id = ?");
        $stmt->execute([$orderId]);
        $order = $stmt->fetch();

        if (!$order) {
            $response = ['success' => false, 'message' => 'Orden no encontrada'];
            break;
        }

        // Validar permisos sobre el comercio
        if ($currentUser['role'] !== 'superadmin') {
            $checkM = $pdo->prepare("SELECT id FROM merchants WHERE id = ? AND user_id = ?");
            $checkM->execute([$order['merchant_id'], $currentUser['id']]);
            if (!$checkM->fetch()) {
                $response = ['success' => false, 'message' => 'No tienes permiso para gestionar pagos de este comercio'];
                break;
            }
        }

        $now = date('Y-m-d H:i:s');

        if ($decision === 'approve') {
            // Aprobar venta
            $up = $pdo->prepare("UPDATE orders_transactions SET status = 'completed', verified_at = ? WHERE id = ?");
            $up->execute([$now, $orderId]);

            // Incrementar ventas y reputación del comercio
            $upM = $pdo->prepare("UPDATE merchants SET sales_count = sales_count + 1, sales_rep = MIN(5.0, sales_rep + 0.05), total_rep = MIN(5.0, (sales_rep + service_rep) / 2) WHERE id = ?");
            $upM->execute([$order['merchant_id']]);

            // Asignar puntos XP al visitante comprador
            $baseXp = max(10, (int)round(floatval($order['amount_usd']) * 2));
            $xpAward = awardXp($pdo, $order['visitor_id'], 'buy_product', $baseXp);

            // Registrar en historial de ambos
            logUserActivity($pdo, $order['visitor_id'], 'payment_verified', $baseXp, "Tu pago de \${$order['amount_usd']} fue verificado exitosamente. ¡Compra consolidada!");
            logUserActivity($pdo, $currentUser['id'], 'sale_completed', 0, "Aprobaste el pago de la orden #{$orderId} (\${$order['amount_usd']})");

            $response = [
                'success' => true,
                'message' => '¡Pago aprobado con éxito! La venta se cargó efectivamente y se acreditaron los puntos al cliente.',
                'order_id' => $orderId,
                'xp_awarded' => $xpAward['xp_earned'] ?? $baseXp
            ];
        } else {
            // Denegar pago
            $up = $pdo->prepare("UPDATE orders_transactions SET status = 'cancelled', denied_reason = ?, verified_at = ? WHERE id = ?");
            $up->execute([$reason ?: 'Comprobante no válido o no conciliado', $now, $orderId]);

            logUserActivity($pdo, $order['visitor_id'], 'payment_denied', 0, "Tu pago de la orden #{$orderId} fue rechazado. Motivo: " . ($reason ?: 'No conciliado'));
            logUserActivity($pdo, $currentUser['id'], 'sale_denied', 0, "Rechazaste la orden de pago #{$orderId}");

            $response = [
                'success' => true,
                'message' => 'Pago denegado correctamente.',
                'order_id' => $orderId
            ];
        }
        break;

    case 'visitor_check_payment_notifications':
        $currentUser = getCurrentUser($pdo);
        if (!$currentUser) {
            $response = ['success' => false, 'message' => 'No en sesión'];
            break;
        }

        $stmt = $pdo->prepare("SELECT o.*, m.commercial_name, m.sector 
                               FROM orders_transactions o 
                               JOIN merchants m ON o.merchant_id = m.id 
                               WHERE o.visitor_id = ? AND o.verified_at IS NOT NULL 
                               ORDER BY o.verified_at DESC LIMIT 5");
        $stmt->execute([$currentUser['id']]);
        $orders = $stmt->fetchAll();

        // Obtener nivel y XP actualizados
        $userFresh = $pdo->prepare("SELECT level, xp FROM users WHERE id = ?");
        $userFresh->execute([$currentUser['id']]);
        $userData = $userFresh->fetch();

        $calc = calculateLevelAndProgress($userData['xp'] ?? 0);

        $response = [
            'success' => true,
            'orders' => $orders,
            'user' => [
                'level' => $calc['level'],
                'title' => $calc['title'],
                'xp' => $calc['xp'],
                'progress_percent' => $calc['progress_percent']
            ]
        ];
        break;

    case 'save_merchant_pagomovil_data':
        $currentUser = getCurrentUser($pdo);
        if (!$currentUser || ($currentUser['role'] !== 'merchant' && $currentUser['role'] !== 'superadmin')) {
            $response = ['success' => false, 'message' => 'Acceso denegado'];
            break;
        }

        $bank = trim($_POST['pago_movil_bank'] ?? '');
        $phone = trim($_POST['pago_movil_phone'] ?? '');
        $ci = trim($_POST['pago_movil_ci'] ?? '');
        $name = trim($_POST['pago_movil_name'] ?? '');

        if (empty($bank) || empty($phone) || empty($ci)) {
            $response = ['success' => false, 'message' => 'Todos los campos de Pago Móvil son obligatorios'];
            break;
        }

        $stmt = $pdo->prepare("UPDATE merchants SET pago_movil_bank = ?, pago_movil_phone = ?, pago_movil_ci = ?, pago_movil_name = ? WHERE user_id = ?");
        $stmt->execute([$bank, $phone, $ci, $name, $currentUser['id']]);

        $response = ['success' => true, 'message' => 'Datos de Pago Móvil actualizados correctamente'];
        break;

    // -------------------------------------------------------------
    // PERFIL DE USUARIO, KPIS Y BITÁCORA HISTÓRICA DE INTERACCIONES
    // -------------------------------------------------------------
    case 'get_user_profile_and_history':
        $currentUser = getCurrentUser($pdo);
        if (!$currentUser) {
            $response = ['success' => false, 'message' => 'Debes iniciar sesión para consultar tu perfil e historial.'];
            break;
        }

        $targetUserId = intval($_GET['user_id'] ?? 0);
        $userId = $currentUser['id'];

        if ($targetUserId > 0) {
            if ($currentUser['role'] !== 'superadmin' && $targetUserId !== (int)$currentUser['id']) {
                $response = ['success' => false, 'message' => 'Acceso restringido a datos de otros usuarios'];
                break;
            }
            $userId = $targetUserId;
        }

        $bcvRate = floatval($pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'bcv_rate'")->fetchColumn() ?: 54.50);

        // 1. Datos de usuario y gamificación
        $stmtUser = $pdo->prepare("SELECT id, name, email, phone, role, level, xp, status, created_at FROM users WHERE id = ?");
        $stmtUser->execute([$userId]);
        $userData = $stmtUser->fetch();

        if (!$userData) {
            $response = ['success' => false, 'message' => 'Usuario no encontrado'];
            break;
        }

        $role = $userData['role'];
        $calc = calculateLevelAndProgress($userData['xp'] ?? 0);
        $daysMember = max(1, (int)floor((time() - strtotime($userData['created_at'])) / 86400));

        // 2. KPIs y detalles según el rol
        $kpis = [];
        $merchantData = null;
        $merchantProducts = [];

        if ($role === 'merchant') {
            $mStmt = $pdo->prepare("SELECT * FROM merchants WHERE user_id = ?");
            $mStmt->execute([$userId]);
            $merchant = $mStmt->fetch();

            if ($merchant) {
                $merchantData = $merchant;
                // Listado de productos/servicios cargados por este comercio
                $prListStmt = $pdo->prepare("SELECT id, name, price_usd, description, is_active FROM products_services WHERE merchant_id = ? ORDER BY id DESC");
                $prListStmt->execute([$merchant['id']]);
                $merchantProducts = $prListStmt->fetchAll();
                foreach ($merchantProducts as &$mp) {
                    $mp['price_usd'] = floatval($mp['price_usd']);
                    $mp['price_bs'] = round($mp['price_usd'] * $bcvRate, 2);
                }
                // Ventas completadas
                $vStmt = $pdo->prepare("SELECT COUNT(*) as count, COALESCE(SUM(amount_usd), 0) as total_usd, COALESCE(SUM(amount_bs), 0) as total_bs FROM orders_transactions WHERE merchant_id = ? AND status = 'completed'");
                $vStmt->execute([$merchant['id']]);
                $vData = $vStmt->fetch();

                // Pagos pendientes por verificar
                $pStmt = $pdo->prepare("SELECT COUNT(*) FROM orders_transactions WHERE merchant_id = ? AND status IN ('pending', 'pending_verification')");
                $pStmt->execute([$merchant['id']]);
                $pendingCount = intval($pStmt->fetchColumn());

                // Catálogo de productos
                $prStmt = $pdo->prepare("SELECT COUNT(*) FROM products_services WHERE merchant_id = ? AND is_active = 1");
                $prStmt->execute([$merchant['id']]);
                $prodCount = intval($prStmt->fetchColumn());

                // Denuncias recibidas
                $cStmt = $pdo->prepare("SELECT COUNT(*) FROM complaints WHERE merchant_id = ?");
                $cStmt->execute([$merchant['id']]);
                $compCount = intval($cStmt->fetchColumn());

                $kpis = [
                    'type' => 'merchant',
                    'merchant_name' => $merchant['commercial_name'],
                    'rif' => $merchant['rif'],
                    'rif_verified' => intval($merchant['rif_verified']),
                    'total_sales_usd' => floatval($vData['total_usd']),
                    'total_sales_bs' => floatval($vData['total_bs']),
                    'completed_sales_count' => intval($vData['count']),
                    'pending_verifications_count' => $pendingCount,
                    'active_products_count' => $prodCount,
                    'reputation_stars' => floatval($merchant['total_rep']),
                    'complaints_count' => $compCount,
                    'days_member' => $daysMember
                ];
            }
        } elseif ($role === 'superadmin') {
            $totalUsers = intval($pdo->query("SELECT COUNT(*) FROM users")->fetchColumn());
            $totalMerchants = intval($pdo->query("SELECT COUNT(*) FROM merchants")->fetchColumn());
            
            $globalSalesStmt = $pdo->query("SELECT COUNT(*) as count, COALESCE(SUM(amount_usd), 0) as total_usd, COALESCE(SUM(amount_bs), 0) as total_bs FROM orders_transactions WHERE status = 'completed'");
            $globalSales = $globalSalesStmt->fetch();

            $pendingRifs = intval($pdo->query("SELECT COUNT(*) FROM merchants WHERE rif_verified = 0")->fetchColumn());
            $pendingPayments = intval($pdo->query("SELECT COUNT(*) FROM orders_transactions WHERE status IN ('pending', 'pending_verification')")->fetchColumn());

            $kpis = [
                'type' => 'superadmin',
                'total_users' => $totalUsers,
                'total_merchants' => $totalMerchants,
                'total_sales_usd' => floatval($globalSales['total_usd']),
                'total_sales_bs' => floatval($globalSales['total_bs']),
                'total_completed_sales' => intval($globalSales['count']),
                'pending_rifs' => $pendingRifs,
                'pending_payments_global' => $pendingPayments,
                'days_member' => $daysMember
            ];
        } else {
            // Visitante
            $cStmt = $pdo->prepare("SELECT COUNT(*) as count, COALESCE(SUM(amount_usd), 0) as total_usd, COALESCE(SUM(amount_bs), 0) as total_bs FROM orders_transactions WHERE visitor_id = ? AND status = 'completed'");
            $cStmt->execute([$userId]);
            $cData = $cStmt->fetch();

            $pStmt = $pdo->prepare("SELECT COUNT(*) FROM orders_transactions WHERE visitor_id = ? AND status IN ('pending', 'pending_verification')");
            $pStmt->execute([$userId]);
            $pendingCount = intval($pStmt->fetchColumn());

            $rStmt = $pdo->prepare("SELECT COUNT(*) FROM reviews WHERE visitor_id = ?");
            $rStmt->execute([$userId]);
            $reviewsCount = intval($rStmt->fetchColumn());

            $cpStmt = $pdo->prepare("SELECT COUNT(*) FROM complaints WHERE visitor_id = ?");
            $cpStmt->execute([$userId]);
            $complaintsCount = intval($cpStmt->fetchColumn());

            $kpis = [
                'type' => 'visitor',
                'total_spent_usd' => floatval($cData['total_usd']),
                'total_spent_bs' => floatval($cData['total_bs']),
                'completed_purchases_count' => intval($cData['count']),
                'pending_purchases_count' => $pendingCount,
                'reviews_left_count' => $reviewsCount,
                'complaints_left_count' => $complaintsCount,
                'xp' => $calc['xp'],
                'level' => $calc['level'],
                'level_title' => $calc['title'],
                'days_member' => $daysMember
            ];
        }

        // 3. LOG HISTÓRICO DE TODAS LAS INTERACCIONES
        $historyLog = [];

        // a) Evento de Registro / Creación de Cuenta
        $historyLog[] = [
            'timestamp' => $userData['created_at'],
            'time_formatted' => date('d/m/Y h:i A', strtotime($userData['created_at'])),
            'type' => 'registration',
            'title' => 'Creación de Cuenta en Paseo Macuto',
            'description' => 'Tu usuario fue registrado exitosamente con el rol de ' . ($role === 'superadmin' ? 'Superusuario Administrador' : ($role === 'merchant' ? 'Prestador de Servicios (Comerciante)' : 'Visitante Turista')) . '.',
            'icon' => 'fas fa-user-check',
            'color' => 'cyan',
            'badge' => 'Bienvenida'
        ];

        // b) Transacciones de compra / venta
        if ($role === 'merchant') {
            $mStmt = $pdo->prepare("SELECT id FROM merchants WHERE user_id = ?");
            $mStmt->execute([$userId]);
            $mId = $mStmt->fetchColumn();

            if ($mId) {
                $oStmt = $pdo->prepare("SELECT o.*, u.name as client_name FROM orders_transactions o JOIN users u ON o.visitor_id = u.id WHERE o.merchant_id = ? ORDER BY o.created_at DESC LIMIT 50");
                $oStmt->execute([$mId]);
                $merchantOrders = $oStmt->fetchAll();

                foreach ($merchantOrders as $ord) {
                    if ($ord['status'] === 'completed') {
                        $historyLog[] = [
                            'timestamp' => $ord['verified_at'] ?: $ord['created_at'],
                            'time_formatted' => date('d/m/Y h:i A', strtotime($ord['verified_at'] ?: $ord['created_at'])),
                            'type' => 'sale_approved',
                            'title' => 'Venta Aprobada y Cobrada #' . $ord['id'],
                            'description' => "Verificaste y aprobaste el pago de \${$ord['amount_usd']} ({$ord['amount_bs']} Bs.) de {$ord['client_name']}. Banco: {$ord['sender_bank']} (Ref: {$ord['payment_reference']}).",
                            'icon' => 'fas fa-check-circle',
                            'color' => 'emerald',
                            'badge' => "+$" . number_format($ord['amount_usd'], 2)
                        ];
                    } elseif ($ord['status'] === 'pending' || $ord['status'] === 'pending_verification') {
                        $historyLog[] = [
                            'timestamp' => $ord['created_at'],
                            'time_formatted' => date('d/m/Y h:i A', strtotime($ord['created_at'])),
                            'type' => 'payment_received_pending',
                            'title' => 'Pago Móvil Recibido por Verificar #' . $ord['id'],
                            'description' => "El cliente {$ord['client_name']} envió un comprobante de Pago Móvil por \${$ord['amount_usd']} ({$ord['amount_bs']} Bs.) pendiente en tu módulo.",
                            'icon' => 'fas fa-hourglass-half',
                            'color' => 'amber',
                            'badge' => 'Por Verificar'
                        ];
                    } elseif ($ord['status'] === 'cancelled' || $ord['status'] === 'denied') {
                        $historyLog[] = [
                            'timestamp' => $ord['verified_at'] ?: $ord['created_at'],
                            'time_formatted' => date('d/m/Y h:i A', strtotime($ord['verified_at'] ?: $ord['created_at'])),
                            'type' => 'sale_denied',
                            'title' => 'Pago Rechazado #' . $ord['id'],
                            'description' => "Denegaste la solicitud de pago de {$ord['client_name']} (\${$ord['amount_usd']}). Motivo: " . ($ord['denied_reason'] ?: 'Comprobante no conciliado'),
                            'icon' => 'fas fa-times-circle',
                            'color' => 'coral',
                            'badge' => 'Denegado'
                        ];
                    }
                }
            }
        } else {
            // Para Visitante (o Superadmin)
            $oStmt = $pdo->prepare("SELECT o.*, m.commercial_name FROM orders_transactions o JOIN merchants m ON o.merchant_id = m.id WHERE o.visitor_id = ? ORDER BY o.created_at DESC LIMIT 50");
            $oStmt->execute([$userId]);
            $visitorOrders = $oStmt->fetchAll();

            foreach ($visitorOrders as $ord) {
                if ($ord['status'] === 'completed') {
                    $historyLog[] = [
                        'timestamp' => $ord['verified_at'] ?: $ord['created_at'],
                        'time_formatted' => date('d/m/Y h:i A', strtotime($ord['verified_at'] ?: $ord['created_at'])),
                        'type' => 'purchase_completed',
                        'title' => 'Compra Verificada #' . $ord['id'] . ' en ' . $ord['commercial_name'],
                        'description' => "Tu pago de \${$ord['amount_usd']} ({$ord['amount_bs']} Bs.) fue verificado exitosamente por {$ord['commercial_name']}.",
                        'icon' => 'fas fa-shopping-bag',
                        'color' => 'emerald',
                        'badge' => 'Compra Efectiva'
                    ];
                } elseif ($ord['status'] === 'pending' || $ord['status'] === 'pending_verification') {
                    $historyLog[] = [
                        'timestamp' => $ord['created_at'],
                        'time_formatted' => date('d/m/Y h:i A', strtotime($ord['created_at'])),
                        'type' => 'payment_sent',
                        'title' => 'Pago Móvil Enviado #' . $ord['id'],
                        'description' => "Registraste pago de \${$ord['amount_usd']} ({$ord['amount_bs']} Bs.) a {$ord['commercial_name']}. Ref: {$ord['payment_reference']}.",
                        'icon' => 'fas fa-paper-plane',
                        'color' => 'cyan',
                        'badge' => 'En Verificación'
                    ];
                } elseif ($ord['status'] === 'cancelled' || $ord['status'] === 'denied') {
                    $historyLog[] = [
                        'timestamp' => $ord['verified_at'] ?: $ord['created_at'],
                        'time_formatted' => date('d/m/Y h:i A', strtotime($ord['verified_at'] ?: $ord['created_at'])),
                        'type' => 'payment_rejected',
                        'title' => 'Pago No Aprobado #' . $ord['id'],
                        'description' => "El comercio {$ord['commercial_name']} no pudo validar el pago de \${$ord['amount_usd']}. Motivo: " . ($ord['denied_reason'] ?: 'Verificación no superada'),
                        'icon' => 'fas fa-exclamation-circle',
                        'color' => 'coral',
                        'badge' => 'No Validado'
                    ];
                }
            }
        }

        // c) Reseñas y Calificaciones
        if ($role === 'merchant') {
            $mStmt = $pdo->prepare("SELECT id FROM merchants WHERE user_id = ?");
            $mStmt->execute([$userId]);
            $mId = $mStmt->fetchColumn();
            if ($mId) {
                $rStmt = $pdo->prepare("SELECT r.*, u.name as reviewer FROM reviews r JOIN users u ON r.visitor_id = u.id WHERE r.merchant_id = ? ORDER BY r.created_at DESC LIMIT 20");
                $rStmt->execute([$mId]);
                while ($rev = $rStmt->fetch()) {
                    $historyLog[] = [
                        'timestamp' => $rev['created_at'],
                        'time_formatted' => date('d/m/Y h:i A', strtotime($rev['created_at'])),
                        'type' => 'review_received',
                        'title' => "Calificación Recibida ({$rev['rating']} Estrellas)",
                        'description' => "{$rev['reviewer']} opinó: \"{$rev['comment']}\"",
                        'icon' => 'fas fa-star',
                        'color' => 'amber',
                        'badge' => str_repeat('★', $rev['rating'])
                    ];
                }
            }
        } else {
            $rStmt = $pdo->prepare("SELECT r.*, m.commercial_name FROM reviews r JOIN merchants m ON r.merchant_id = m.id WHERE r.visitor_id = ? ORDER BY r.created_at DESC LIMIT 20");
            $rStmt->execute([$userId]);
            while ($rev = $rStmt->fetch()) {
                $historyLog[] = [
                    'timestamp' => $rev['created_at'],
                    'time_formatted' => date('d/m/Y h:i A', strtotime($rev['created_at'])),
                    'type' => 'review_given',
                    'title' => "Calificaste a {$rev['commercial_name']}",
                    'description' => "Puntuación de {$rev['rating']} estrellas. Tu opinión: \"{$rev['comment']}\"",
                    'icon' => 'fas fa-star',
                    'color' => 'amber',
                    'badge' => str_repeat('★', $rev['rating'])
                ];
            }
        }

        // d) Denuncias / Reclamos
        $compStmt = $pdo->prepare("SELECT c.*, m.commercial_name FROM complaints c JOIN merchants m ON c.merchant_id = m.id WHERE c.visitor_id = ? ORDER BY c.created_at DESC LIMIT 15");
        $compStmt->execute([$userId]);
        while ($comp = $compStmt->fetch()) {
            $historyLog[] = [
                'timestamp' => $comp['created_at'],
                'time_formatted' => date('d/m/Y h:i A', strtotime($comp['created_at'])),
                'type' => 'complaint',
                'title' => "Reporte / Queja registrada",
                'description' => "Reportaste una eventualidad sobre el comercio {$comp['commercial_name']}: \"{$comp['description']}\" [Estatus: {$comp['status']}].",
                'icon' => 'fas fa-shield-alt',
                'color' => 'coral',
                'badge' => ucfirst($comp['status'])
            ];
        }

        // e) Log general de user_activity_logs (XP ganada, bonos, etc.)
        $actStmt = $pdo->prepare("SELECT * FROM user_activity_logs WHERE user_id = ? ORDER BY created_at DESC LIMIT 50");
        $actStmt->execute([$userId]);
        while ($act = $actStmt->fetch()) {
            if ($act['xp_earned'] > 0) {
                $historyLog[] = [
                    'timestamp' => $act['created_at'],
                    'time_formatted' => date('d/m/Y h:i A', strtotime($act['created_at'])),
                    'type' => 'xp_boost',
                    'title' => "Puntos de Experiencia Obtenidos (+{$act['xp_earned']} XP)",
                    'description' => $act['metadata'] ?: ("Acción completada: " . ucfirst(str_replace('_', ' ', $act['action_type']))),
                    'icon' => 'fas fa-award',
                    'color' => 'purple',
                    'badge' => "+{$act['xp_earned']} XP"
                ];
            }
        }

        // Ordenar el historial de forma cronológica descendente (más reciente primero)
        usort($historyLog, function($a, $b) {
            return strtotime($b['timestamp']) - strtotime($a['timestamp']);
        });

        $response = [
            'success' => true,
            'user' => [
                'id' => $userData['id'],
                'name' => $userData['name'],
                'email' => $userData['email'],
                'phone' => $userData['phone'],
                'role' => $userData['role'],
                'created_at' => $userData['created_at'],
                'member_since' => date('d/m/Y', strtotime($userData['created_at'])),
                'gamification' => $calc
            ],
            'kpis' => $kpis,
            'merchant_details' => $merchantData,
            'merchant_products' => $merchantProducts,
            'bcv_rate' => $bcvRate,
            'history_count' => count($historyLog),
            'history' => $historyLog
        ];
        break;

    default:
        $response = ['success' => false, 'message' => 'Acción no especificada'];
        break;
}

echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
