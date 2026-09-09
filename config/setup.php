<?php
// config/setup.php - Instalador y Sembrador de Base de Datos para Paseo Macuto
require_once __DIR__ . '/database.php';

function initializeDatabase() {
    $pdo = Database::getConnection();
    $driver = Database::getDriver();

    $idType = ($driver === 'sqlite') ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'INT AUTO_INCREMENT PRIMARY KEY';
    $textType = ($driver === 'sqlite') ? 'TEXT' : 'TEXT';
    $timestampType = ($driver === 'sqlite') ? 'DATETIME DEFAULT CURRENT_TIMESTAMP' : 'DATETIME DEFAULT CURRENT_TIMESTAMP';

    // 1. system_settings
    $pdo->exec("CREATE TABLE IF NOT EXISTS system_settings (
        setting_key VARCHAR(100) PRIMARY KEY,
        setting_value $textType,
        updated_by VARCHAR(100),
        updated_at $timestampType
    )");

    // 2. users
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id $idType,
        name VARCHAR(150) NOT NULL,
        email VARCHAR(150) UNIQUE NOT NULL,
        password_hash VARCHAR(255) NOT NULL,
        role VARCHAR(50) NOT NULL DEFAULT 'visitor',
        level INT DEFAULT 1,
        xp INT DEFAULT 0,
        phone VARCHAR(50),
        status VARCHAR(50) DEFAULT 'active',
        created_at $timestampType
    )");

    // 3. merchants
    $pdo->exec("CREATE TABLE IF NOT EXISTS merchants (
        id $idType,
        user_id INT,
        commercial_name VARCHAR(150) NOT NULL,
        rif VARCHAR(50) NOT NULL,
        rif_verified INT DEFAULT 0, -- 0: pendiente, 1: verificado, -1: rechazado
        category VARCHAR(100) NOT NULL,
        sector VARCHAR(100) NOT NULL,
        lat DECIMAL(10, 7) NOT NULL,
        lng DECIMAL(10, 7) NOT NULL,
        plus_code VARCHAR(50),
        phone VARCHAR(50),
        description $textType,
        sales_count INT DEFAULT 0,
        sales_rep DECIMAL(3, 2) DEFAULT 5.00,
        service_rep DECIMAL(3, 2) DEFAULT 5.00,
        total_rep DECIMAL(3, 2) DEFAULT 5.00,
        is_open INT DEFAULT 1,
        pago_movil_bank VARCHAR(100),
        pago_movil_phone VARCHAR(50),
        pago_movil_ci VARCHAR(50),
        pago_movil_name VARCHAR(150),
        created_at $timestampType
    )");

    // 4. products_services
    $pdo->exec("CREATE TABLE IF NOT EXISTS products_services (
        id $idType,
        merchant_id INT NOT NULL,
        name VARCHAR(150) NOT NULL,
        category VARCHAR(100),
        price_usd DECIMAL(10, 2) NOT NULL,
        description $textType,
        image_url VARCHAR(255),
        is_active INT DEFAULT 1,
        created_at $timestampType
    )");

    // 5. orders_transactions
    $pdo->exec("CREATE TABLE IF NOT EXISTS orders_transactions (
        id $idType,
        visitor_id INT NOT NULL,
        merchant_id INT NOT NULL,
        product_id INT,
        items_json $textType,
        amount_usd DECIMAL(10, 2) NOT NULL,
        amount_bs DECIMAL(12, 2) NOT NULL,
        bcv_rate_used DECIMAL(10, 2) DEFAULT 54.50,
        sender_bank VARCHAR(100),
        sender_phone VARCHAR(50),
        sender_ci VARCHAR(50),
        payment_reference VARCHAR(100),
        proof_image $textType,
        status VARCHAR(50) DEFAULT 'completed',
        verified_at $timestampType,
        denied_reason $textType,
        created_at $timestampType
    )");

    // 6. reviews
    $pdo->exec("CREATE TABLE IF NOT EXISTS reviews (
        id $idType,
        visitor_id INT NOT NULL,
        merchant_id INT NOT NULL,
        order_id INT,
        rating INT NOT NULL CHECK(rating >= 1 AND rating <= 5),
        comment $textType,
        verified_purchase INT DEFAULT 1,
        created_at $timestampType
    )");

    // 7. complaints (incidencias / denuncias)
    $pdo->exec("CREATE TABLE IF NOT EXISTS complaints (
        id $idType,
        visitor_id INT NOT NULL,
        merchant_id INT NOT NULL,
        description $textType NOT NULL,
        status VARCHAR(50) DEFAULT 'pending', -- pending, resolved, dismissed, penalized
        admin_notes $textType,
        created_at $timestampType
    )");

    // 8. xp_boosters
    $pdo->exec("CREATE TABLE IF NOT EXISTS xp_boosters (
        id $idType,
        name VARCHAR(100) NOT NULL,
        multiplier DECIMAL(3, 2) DEFAULT 1.50,
        action_type VARCHAR(50) NOT NULL, -- visit, review, purchase
        is_active INT DEFAULT 1,
        description $textType,
        created_at $timestampType
    )");

    // 9. activity_logs
    $pdo->exec("CREATE TABLE IF NOT EXISTS activity_logs (
        id $idType,
        user_id INT,
        action VARCHAR(100) NOT NULL,
        xp_earned INT DEFAULT 0,
        details $textType,
        ip_address VARCHAR(45),
        created_at $timestampType
    )");

    // SEMILLA: Tasa de cambio BCV oficial y configuraciones
    $stmtRate = $pdo->prepare("SELECT COUNT(*) FROM system_settings WHERE setting_key = 'bcv_rate'");
    $stmtRate->execute();
    if ($stmtRate->fetchColumn() == 0) {
        $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value, updated_by) VALUES ('bcv_rate', '54.50', 'Sistema')")->execute();
    }

    // SEMILLA: Usuarios iniciales (Superadmin, Visitante demo y 20 Comerciantes)
    $stmtUsers = $pdo->query("SELECT COUNT(*) FROM users");
    if ($stmtUsers->fetchColumn() == 0) {
        $passAdmin = password_hash('admin123', PASSWORD_BCRYPT);
        $passMerchant = password_hash('comercio123', PASSWORD_BCRYPT);
        $passVisitor = password_hash('turista123', PASSWORD_BCRYPT);

        // Superadmin
        $pdo->prepare("INSERT INTO users (name, email, password_hash, role, level, xp) VALUES (?, ?, ?, 'superadmin', 10, 1000000)")
            ->execute(['Administrador Paseo Macuto', 'lams210488@gmail.com', $passAdmin]);

        // Visitante demo
        $pdo->prepare("INSERT INTO users (name, email, password_hash, role, level, xp) VALUES (?, ?, ?, 'visitor', 2, 18)")
            ->execute(['Mariana González (Turista)', 'turista@paseomacuto.com', $passVisitor]);

        // 20 Usuarios Comerciantes dedicados vinculados a sus comercios
        $merchantUsers = [
            ['name' => 'Carlos Baralt (La Gaviota)', 'email' => 'gaviota@paseomacuto.com', 'role' => 'merchant', 'level' => 2, 'xp' => 49, 'phone' => '+58 416 3030303'],
            ['name' => 'Juan Mata (Plaza Las Palomas)', 'email' => 'palomas@paseomacuto.com', 'role' => 'merchant', 'level' => 2, 'xp' => 50, 'phone' => '+58 412 1010101'],
            ['name' => 'Beatriz Ramos (Parque Infantil)', 'email' => 'parqueinfantil@paseomacuto.com', 'role' => 'merchant', 'level' => 2, 'xp' => 50, 'phone' => '+58 414 1020202'],
            ['name' => 'Rafael Domínguez (CONPPA Macuto)', 'email' => 'conppa@paseomacuto.com', 'role' => 'merchant', 'level' => 2, 'xp' => 58, 'phone' => '+58 424 4040404'],
            ['name' => 'Rosa Pataru (Empanadas Pataru)', 'email' => 'pataru@paseomacuto.com', 'role' => 'merchant', 'level' => 2, 'xp' => 50, 'phone' => '+58 412 5050505'],
            ['name' => 'Alberto Cisneros (Hotel Colonial)', 'email' => 'hotelcolonial@paseomacuto.com', 'role' => 'merchant', 'level' => 2, 'xp' => 50, 'phone' => '+58 212 3551200'],
            ['name' => 'Carmen Lucía Santana (Fuente Santa Ana)', 'email' => 'santaana@paseomacuto.com', 'role' => 'merchant', 'level' => 2, 'xp' => 50, 'phone' => '+58 414 0001122'],
            ['name' => 'Miguel Marín (Pescadería Mar y Sabor)', 'email' => 'pescaderia@paseomacuto.com', 'role' => 'merchant', 'level' => 2, 'xp' => 50, 'phone' => '+58 416 7070707'],
            ['name' => 'Héctor Zambrano (Toldos Oeste)', 'email' => 'toldosoeste@paseomacuto.com', 'role' => 'merchant', 'level' => 2, 'xp' => 50, 'phone' => '+58 424 9090909'],
            ['name' => 'Dra. Elena Morales (Farmacia Macuvet)', 'email' => 'macuvet@paseomacuto.com', 'role' => 'merchant', 'level' => 2, 'xp' => 50, 'phone' => '+58 212 3552233'],
            ['name' => 'Gabriel Blanco (Boulevar Macuto)', 'email' => 'boulevard@paseomacuto.com', 'role' => 'merchant', 'level' => 2, 'xp' => 50, 'phone' => '+58 412 0011223'],
            ['name' => 'Luis Enrique Sosa (Balneario Sector A)', 'email' => 'balneario@paseomacuto.com', 'role' => 'merchant', 'level' => 2, 'xp' => 50, 'phone' => '+58 414 2233445'],
            ['name' => 'Fernando Da Silva (Macuto II)', 'email' => 'macuto2@paseomacuto.com', 'role' => 'merchant', 'level' => 2, 'xp' => 50, 'phone' => '+58 212 3554411'],
            ['name' => 'Maritza Gómez (Sol y Arena)', 'email' => 'solyarena@paseomacuto.com', 'role' => 'merchant', 'level' => 2, 'xp' => 50, 'phone' => '+58 424 5566778'],
            ['name' => 'Antonio D\'Amico (Sol Y Mar)', 'email' => 'solymar@paseomacuto.com', 'role' => 'merchant', 'level' => 2, 'xp' => 50, 'phone' => '+58 212 3553388'],
            ['name' => 'José Fernández (Hotel Jofer)', 'email' => 'hoteljofer@paseomacuto.com', 'role' => 'merchant', 'level' => 2, 'xp' => 50, 'phone' => '+58 212 3552900'],
            ['name' => 'Daniel Castillo (Toldos Playa Este)', 'email' => 'playaeste@paseomacuto.com', 'role' => 'merchant', 'level' => 2, 'xp' => 50, 'phone' => '+58 414 7788990'],
            ['name' => 'Vicente Peñaloza (Mirador Macuto)', 'email' => 'mirador@paseomacuto.com', 'role' => 'merchant', 'level' => 2, 'xp' => 50, 'phone' => '+58 416 9988776'],
            ['name' => 'Santiago Urbina (Paddle Surf)', 'email' => 'paddlesurf@paseomacuto.com', 'role' => 'merchant', 'level' => 2, 'xp' => 50, 'phone' => '+58 412 3456789'],
            ['name' => 'Andrés Ceiba (El Ceibo)', 'email' => 'elceibo@paseomacuto.com', 'role' => 'merchant', 'level' => 2, 'xp' => 50, 'phone' => '+58 424 1234567']
        ];

        $insUser = $pdo->prepare("INSERT INTO users (name, email, password_hash, role, level, xp, phone) VALUES (?, ?, ?, ?, ?, ?, ?)");
        foreach ($merchantUsers as $mu) {
            $insUser->execute([
                $mu['name'],
                $mu['email'],
                $passMerchant,
                $mu['role'],
                $mu['level'],
                $mu['xp'],
                $mu['phone']
            ]);
        }
    }

    // SEMILLA: Potenciadores iniciales (Boosters)
    $stmtBoost = $pdo->query("SELECT COUNT(*) FROM xp_boosters");
    if ($stmtBoost->fetchColumn() == 0) {
        $boosters = [
            ['Fin de Semana Playero x2', 2.00, 'purchase', 1, 'Doble XP en todas las compras realizadas viernes, sábado y domingo'],
            ['Ruta Gastronómica x3', 3.00, 'purchase', 1, 'Triple XP al consumir en restaurantes y pescaderías de Macuto'],
            ['Reseña Verificada x1.5', 1.50, 'review', 1, '50% más de XP por dejar tu opinión real con foto o detalles'],
            ['Bienvenida Turista 2026', 1.50, 'visit', 1, 'Bonus diario por visitar la plataforma y explorar el mapa']
        ];
        $bStmt = $pdo->prepare("INSERT INTO xp_boosters (name, multiplier, action_type, is_active, description) VALUES (?, ?, ?, ?, ?)");
        foreach ($boosters as $b) {
            $bStmt->execute($b);
        }
    }

    // SEMILLA: Los 20 Comercios oficiales de Paseo Macuto con sus Plus Codes y Pago Móvil
    $stmtMerchants = $pdo->query("SELECT COUNT(*) FROM merchants");
    if ($stmtMerchants->fetchColumn() == 0) {
        $realPlaces = [
            [
                'commercial_name' => 'Plaza Andrés Mata (Las Palomas)',
                'user_email' => 'palomas@paseomacuto.com',
                'rif' => 'G-20000101-1',
                'rif_verified' => 1,
                'category' => 'Sitio Histórico y Recreativo',
                'sector' => 'Sector Palomas (Oeste)',
                'lat' => 10.6055589,
                'lng' => -66.8965135,
                'plus_code' => '769HJ343+6C',
                'phone' => '+58 412 1010101',
                'description' => 'Plaza histórica arbolada frente al mar, punto de partida del Paseo Macuto famosa por sus palomas y esculturas.',
                'sales_count' => 85,
                'sales_rep' => 4.9,
                'service_rep' => 4.8,
                'total_rep' => 4.85,
                'is_open' => 1,
                'pago_movil_bank' => 'Banco de Venezuela (0102)',
                'pago_movil_phone' => '0412-1010101',
                'pago_movil_ci' => 'V-11223344',
                'pago_movil_name' => 'Plaza Las Palomas Macuto'
            ],
            [
                'commercial_name' => 'Parque Infantil Macuto',
                'user_email' => 'parqueinfantil@paseomacuto.com',
                'rif' => 'G-20000102-2',
                'rif_verified' => 1,
                'category' => 'Recreación Familiar',
                'sector' => 'Sector Palomas (Oeste)',
                'lat' => 10.6056834,
                'lng' => -66.8962599,
                'plus_code' => '769HJ343+7F',
                'phone' => '+58 414 1020202',
                'description' => 'Área de juegos infantiles, alquiler de carritos y golosinas tradicionales frente a la brisa marina.',
                'sales_count' => 120,
                'sales_rep' => 4.8,
                'service_rep' => 4.7,
                'total_rep' => 4.75,
                'is_open' => 1,
                'pago_movil_bank' => 'Banesco (0134)',
                'pago_movil_phone' => '0414-1020202',
                'pago_movil_ci' => 'V-12345678',
                'pago_movil_name' => 'Parque Infantil Paseo Macuto'
            ],
            [
                'commercial_name' => 'Bar Restaurant La Gaviota',
                'user_email' => 'gaviota@paseomacuto.com',
                'rif' => 'J-31456789-0',
                'rif_verified' => 1,
                'category' => 'Gastronomía y Bebidas',
                'sector' => 'Sector Palomas (Oeste)',
                'lat' => 10.6059109,
                'lng' => -66.8959181,
                'plus_code' => '769HJ343+9J',
                'phone' => '+58 416 3030303',
                'description' => 'Clásico bar restaurante frente al malecón. Famoso por sus cócteles tropicales, tostones playeros y fosforera.',
                'sales_count' => 431,
                'sales_rep' => 4.95,
                'service_rep' => 4.9,
                'total_rep' => 4.9,
                'is_open' => 1,
                'pago_movil_bank' => 'Banco Mercantil (0105)',
                'pago_movil_phone' => '0416-3030303',
                'pago_movil_ci' => 'J-31456789',
                'pago_movil_name' => 'Bar Restaurant La Gaviota'
            ],
            [
                'commercial_name' => 'CONPPA Paseo Macuto',
                'user_email' => 'conppa@paseomacuto.com',
                'rif' => 'G-20045612-4',
                'rif_verified' => 1,
                'category' => 'Pescadores y Servicios Marítimos',
                'sector' => 'Sector Pescadores',
                'lat' => 10.6065247,
                'lng' => -66.8945678,
                'plus_code' => '769HJ344+J5',
                'phone' => '+58 424 4040404',
                'description' => 'Consejo de Pescadores Artesanales de Macuto. Venta directa de pescado fresco del día y paseos costeros en peñero.',
                'sales_count' => 611,
                'sales_rep' => 5,
                'service_rep' => 4.8,
                'total_rep' => 4.9,
                'is_open' => 1,
                'pago_movil_bank' => 'Banco de Venezuela (0102)',
                'pago_movil_phone' => '0424-4040404',
                'pago_movil_ci' => 'G-20045612',
                'pago_movil_name' => 'CONPPA Paseo Macuto'
            ],
            [
                'commercial_name' => 'El Sabor de los Pataru',
                'user_email' => 'pataru@paseomacuto.com',
                'rif' => 'V-14892341-2',
                'rif_verified' => 1,
                'category' => 'Comida Rápida y Típica',
                'sector' => 'Sector Pescadores',
                'lat' => 10.6062727,
                'lng' => -66.8945065,
                'plus_code' => '769HJ344+G6',
                'phone' => '+58 412 5050505',
                'description' => 'Empanadas gigantes de cazón, calamar, camarón y queso paisa recién fritas a orilla de playa.',
                'sales_count' => 951,
                'sales_rep' => 4.9,
                'service_rep' => 4.9,
                'total_rep' => 4.9,
                'is_open' => 1,
                'pago_movil_bank' => 'Bancamiga (0172)',
                'pago_movil_phone' => '0412-5050505',
                'pago_movil_ci' => 'V-14892341',
                'pago_movil_name' => 'Empanadas El Sabor de los Pataru'
            ],
            [
                'commercial_name' => 'Hotel Colonial Macuto',
                'user_email' => 'hotelcolonial@paseomacuto.com',
                'rif' => 'J-00124589-9',
                'rif_verified' => 1,
                'category' => 'Hospedaje y Posada',
                'sector' => 'Sector Tradicional',
                'lat' => 10.6059573,
                'lng' => -66.8943961,
                'plus_code' => '769HJ344+96',
                'phone' => '+58 212 3551200',
                'description' => 'Tradicional edificación con vista al mar, habitaciones con aire acondicionado y restaurante interno.',
                'sales_count' => 180,
                'sales_rep' => 4.7,
                'service_rep' => 4.6,
                'total_rep' => 4.65,
                'is_open' => 1,
                'pago_movil_bank' => 'BBVA Provincial (0108)',
                'pago_movil_phone' => '0414-2551200',
                'pago_movil_ci' => 'J-00124589',
                'pago_movil_name' => 'Hotel Colonial Macuto'
            ],
            [
                'commercial_name' => 'Fuente de Santa Ana de Macuto',
                'user_email' => 'santaana@paseomacuto.com',
                'rif' => 'G-20099900-5',
                'rif_verified' => 1,
                'category' => 'Monumento y Paseo',
                'sector' => 'Sector Tradicional',
                'lat' => 10.6057242,
                'lng' => -66.8943655,
                'plus_code' => '769HJ344+77',
                'phone' => '+58 414 0001122',
                'description' => 'Monumento patrimonial de Macuto, punto de encuentro turístico y área de artesanos locales.',
                'sales_count' => 50,
                'sales_rep' => 4.9,
                'service_rep' => 4.9,
                'total_rep' => 4.9,
                'is_open' => 1,
                'pago_movil_bank' => 'Banco Bicentenario (0175)',
                'pago_movil_phone' => '0414-0001122',
                'pago_movil_ci' => 'V-15443322',
                'pago_movil_name' => 'Artesanías Fuente Santa Ana'
            ],
            [
                'commercial_name' => 'Pescadería Macuto Mar y Sabor',
                'user_email' => 'pescaderia@paseomacuto.com',
                'rif' => 'V-16782390-3',
                'rif_verified' => 1,
                'category' => 'Pescadería y Marisquería',
                'sector' => 'Sector Tradicional',
                'lat' => 10.6055868,
                'lng' => -66.8943803,
                'plus_code' => '769HJ344+67',
                'phone' => '+58 416 7070707',
                'description' => 'Pargo, mero, corocoro, camarones y pulpo fresco arreglado y empacado para llevar o preparar.',
                'sales_count' => 310,
                'sales_rep' => 4.6,
                'service_rep' => 2.9,
                'total_rep' => 3.45,
                'is_open' => 1,
                'pago_movil_bank' => 'Banco Nacional de Crédito BNC (0191)',
                'pago_movil_phone' => '0416-7070707',
                'pago_movil_ci' => 'V-16782390',
                'pago_movil_name' => 'Pescadería Macuto Mar y Sabor'
            ],
            [
                'commercial_name' => 'Playa Macuto - Sector Toldos Oeste',
                'user_email' => 'toldosoeste@paseomacuto.com',
                'rif' => 'V-19821034-7',
                'rif_verified' => 1,
                'category' => 'Toldos y Servicios Playeros',
                'sector' => 'Sector Balneario Central',
                'lat' => 10.6066734,
                'lng' => -66.8940273,
                'plus_code' => '769HJ344+M9',
                'phone' => '+58 424 9090909',
                'description' => 'Alquiler de toldos confortables, sillas y atención directa en arena con servicio de bebidas y hielo.',
                'sales_count' => 820,
                'sales_rep' => 4.8,
                'service_rep' => 4.7,
                'total_rep' => 4.75,
                'is_open' => 1,
                'pago_movil_bank' => 'Banco de Venezuela (0102)',
                'pago_movil_phone' => '0424-9090909',
                'pago_movil_ci' => 'V-19821034',
                'pago_movil_name' => 'Toldos Playa Macuto Oeste'
            ],
            [
                'commercial_name' => 'Farmacia Macuvet y Playa',
                'user_email' => 'macuvet@paseomacuto.com',
                'rif' => 'J-40556677-1',
                'rif_verified' => 1,
                'category' => 'Salud y Farmacia',
                'sector' => 'Sector Balneario Central',
                'lat' => 10.6063268,
                'lng' => -66.8936181,
                'plus_code' => '769HJ344+GC',
                'phone' => '+58 212 3552233',
                'description' => 'Protectores solares, medicamentos de primeros auxilios, hidratación y productos esenciales para el turista.',
                'sales_count' => 240,
                'sales_rep' => 4.9,
                'service_rep' => 4.8,
                'total_rep' => 4.85,
                'is_open' => 1,
                'pago_movil_bank' => 'Banesco (0134)',
                'pago_movil_phone' => '0412-3552233',
                'pago_movil_ci' => 'J-40556677',
                'pago_movil_name' => 'Farmacia Macuvet y Playa'
            ],
            [
                'commercial_name' => 'Paseo La Playa Boulevar',
                'user_email' => 'boulevard@paseomacuto.com',
                'rif' => 'G-20077889-0',
                'rif_verified' => 1,
                'category' => 'Paseo Turístico y Heladerías',
                'sector' => 'Sector Balneario Central',
                'lat' => 10.6068974,
                'lng' => -66.8936284,
                'plus_code' => '769HJ344+QC',
                'phone' => '+58 412 0011223',
                'description' => 'Corredor principal con heladerías artesanales de coco, cepillados tradicionales y venta de artesanías.',
                'sales_count' => 540,
                'sales_rep' => 4.8,
                'service_rep' => 4.8,
                'total_rep' => 4.8,
                'is_open' => 1,
                'pago_movil_bank' => 'Bancaribe (0114)',
                'pago_movil_phone' => '0412-0011223',
                'pago_movil_ci' => 'V-17882211',
                'pago_movil_name' => 'Heladería y Dulcería Boulevar'
            ],
            [
                'commercial_name' => 'Balneario Macuto Sector A',
                'user_email' => 'balneario@paseomacuto.com',
                'rif' => 'V-15678432-8',
                'rif_verified' => 1,
                'category' => 'Balneario y Entretenimiento',
                'sector' => 'Sector Balneario Central',
                'lat' => 10.6070715,
                'lng' => -66.8930758,
                'plus_code' => '769HJ344+RR',
                'phone' => '+58 414 2233445',
                'description' => 'Zona de playa protegida por rompeolas, ideal para niños. Duchas de agua dulce, vestidores y salvavidas.',
                'sales_count' => 1100,
                'sales_rep' => 4.7,
                'service_rep' => 4.6,
                'total_rep' => 4.65,
                'is_open' => 1,
                'pago_movil_bank' => 'Banco Mercantil (0105)',
                'pago_movil_phone' => '0414-2233445',
                'pago_movil_ci' => 'V-15678432',
                'pago_movil_name' => 'Concesión Balneario Macuto A'
            ],
            [
                'commercial_name' => 'Bar Restaurant Macuto II',
                'user_email' => 'macuto2@paseomacuto.com',
                'rif' => 'J-30987654-2',
                'rif_verified' => 1,
                'category' => 'Gastronomía y Pescados',
                'sector' => 'Sector Balneario Central',
                'lat' => 10.6064669,
                'lng' => -66.8932446,
                'plus_code' => '769HJ344+HQ',
                'phone' => '+58 212 3554411',
                'description' => 'Especialistas en rueda de carite frito, pargo al ajillo, ensalada mixta y hervido de pescado sabatino.',
                'sales_count' => 740,
                'sales_rep' => 4.9,
                'service_rep' => 4.9,
                'total_rep' => 4.9,
                'is_open' => 1,
                'pago_movil_bank' => 'Banesco (0134)',
                'pago_movil_phone' => '0412-3554411',
                'pago_movil_ci' => 'J-30987654',
                'pago_movil_name' => 'Bar Restaurant Macuto II'
            ],
            [
                'commercial_name' => 'Quiosco Sol y Arena Balneario',
                'user_email' => 'solyarena@paseomacuto.com',
                'rif' => 'V-20112445-9',
                'rif_verified' => 1,
                'category' => 'Snacks y Bebidas',
                'sector' => 'Sector Balneario Central',
                'lat' => 10.6070331,
                'lng' => -66.8931122,
                'plus_code' => '769HJ344+RP',
                'phone' => '+58 424 5566778',
                'description' => 'Cocos fríos, agua mineral, jugos naturales de papelón con limón y snacks playeros.',
                'sales_count' => 190,
                'sales_rep' => 4.7,
                'service_rep' => 4.5,
                'total_rep' => 4.6,
                'is_open' => 1,
                'pago_movil_bank' => 'Banco de Venezuela (0102)',
                'pago_movil_phone' => '0424-5566778',
                'pago_movil_ci' => 'V-20112445',
                'pago_movil_name' => 'Quiosco Sol y Arena Balneario'
            ],
            [
                'commercial_name' => 'Restaurant Sol Y Mar',
                'user_email' => 'solymar@paseomacuto.com',
                'rif' => 'J-40112233-4',
                'rif_verified' => 1,
                'category' => 'Gastronomía y Marisquería',
                'sector' => 'Sector Paseo Este',
                'lat' => 10.6067014,
                'lng' => -66.8927311,
                'plus_code' => '769HJ344+MW',
                'phone' => '+58 212 3553388',
                'description' => 'Terraza marina de dos niveles con vista panorámica a la bahía. Paella marinera, asopado y mariscos al gratén.',
                'sales_count' => 890,
                'sales_rep' => 4.9,
                'service_rep' => 4.9,
                'total_rep' => 4.9,
                'is_open' => 1,
                'pago_movil_bank' => 'Bancamiga (0172)',
                'pago_movil_phone' => '0414-3553388',
                'pago_movil_ci' => 'J-40112233',
                'pago_movil_name' => 'Inversiones Restaurant Sol Y Mar'
            ],
            [
                'commercial_name' => 'Hotel Jofer Macuto',
                'user_email' => 'hoteljofer@paseomacuto.com',
                'rif' => 'J-29871100-8',
                'rif_verified' => 1,
                'category' => 'Hospedaje y Turismo',
                'sector' => 'Sector Paseo Este',
                'lat' => 10.6067767,
                'lng' => -66.8926583,
                'plus_code' => '769HJ344+PW',
                'phone' => '+58 212 3552900',
                'description' => 'Hotel turístico con estacionamiento privado, wifi, piscina y cercanía inmediata a la franja de playa.',
                'sales_count' => 310,
                'sales_rep' => 4.8,
                'service_rep' => 4.7,
                'total_rep' => 4.75,
                'is_open' => 1,
                'pago_movil_bank' => 'Banco Nacional de Crédito BNC (0191)',
                'pago_movil_phone' => '0416-3552900',
                'pago_movil_ci' => 'J-29871100',
                'pago_movil_name' => 'Hotel Jofer Macuto'
            ],
            [
                'commercial_name' => 'Playa Macuto Sector Este',
                'user_email' => 'playaeste@paseomacuto.com',
                'rif' => 'V-18774433-1',
                'rif_verified' => 1,
                'category' => 'Toldos y Actividades Acuáticas',
                'sector' => 'Sector Paseo Este',
                'lat' => 10.606667,
                'lng' => -66.8925,
                'plus_code' => '769HJ345+MG',
                'phone' => '+58 414 7788990',
                'description' => 'Oleaje suave para nado, alquiler de tablas de flotación, toldos familiares y música ambiental suave.',
                'sales_count' => 670,
                'sales_rep' => 4.8,
                'service_rep' => 4.6,
                'total_rep' => 4.7,
                'is_open' => 1,
                'pago_movil_bank' => 'Banco Exterior (0115)',
                'pago_movil_phone' => '0414-7788990',
                'pago_movil_ci' => 'V-18774433',
                'pago_movil_name' => 'Servicios Playa Macuto Este'
            ],
            [
                'commercial_name' => 'Mirador Paseo de Macuto',
                'user_email' => 'mirador@paseomacuto.com',
                'rif' => 'G-20033441-2',
                'rif_verified' => 1,
                'category' => 'Mirador y Artesanías',
                'sector' => 'Sector Paseo Este',
                'lat' => 10.60788,
                'lng' => -66.8923029,
                'plus_code' => '769HJ355+43',
                'phone' => '+58 416 9988776',
                'description' => 'Punto fotográfico emblemático del paseo, venta de collares de perlas, franelas de La Guaira y recuerdos.',
                'sales_count' => 410,
                'sales_rep' => 4.9,
                'service_rep' => 4.9,
                'total_rep' => 4.9,
                'is_open' => 1,
                'pago_movil_bank' => 'Banco Digital de los Trabajadores BDT (0163)',
                'pago_movil_phone' => '0416-9988776',
                'pago_movil_ci' => 'V-13221199',
                'pago_movil_name' => 'Mirador Turístico Paseo Macuto'
            ],
            [
                'commercial_name' => 'La Guaira Paddle Surf & Kayak',
                'user_email' => 'paddlesurf@paseomacuto.com',
                'rif' => 'J-50119988-6',
                'rif_verified' => 1,
                'category' => 'Deportes Acuáticos y Aventura',
                'sector' => 'Sector Náutico Este',
                'lat' => 10.608699,
                'lng' => -66.8920996,
                'plus_code' => '769HJ355+FM',
                'phone' => '+58 412 3456789',
                'description' => 'Clases de Stand Up Paddle, paseos guiados en kayak al amanecer y atardecer, chalecos salvavidas e instructores certificados.',
                'sales_count' => 520,
                'sales_rep' => 5,
                'service_rep' => 5,
                'total_rep' => 5,
                'is_open' => 1,
                'pago_movil_bank' => 'Banesco (0134)',
                'pago_movil_phone' => '0412-3456789',
                'pago_movil_ci' => 'J-50119988',
                'pago_movil_name' => 'La Guaira Paddle Surf & Kayak'
            ],
            [
                'commercial_name' => 'Chiringuito y Restaurante El Ceibo',
                'user_email' => 'elceibo@paseomacuto.com',
                'rif' => 'V-13456789-0',
                'rif_verified' => 1,
                'category' => 'Gastronomía y Chill Out',
                'sector' => 'Sector El Ceibo (Extremo Este)',
                'lat' => 10.6095397,
                'lng' => -66.8912995,
                'plus_code' => '769HJ355+RF',
                'phone' => '+58 424 1234567',
                'description' => 'Remate este del Paseo Macuto. Chiringuito playero con mesas de madera bajo el ceibo histórico, cervezas heladas y ceviche costeño.',
                'sales_count' => 930,
                'sales_rep' => 4.9,
                'service_rep' => 4.9,
                'total_rep' => 4.9,
                'is_open' => 1,
                'pago_movil_bank' => 'Banco de Venezuela (0102)',
                'pago_movil_phone' => '0424-1234567',
                'pago_movil_ci' => 'V-13456789',
                'pago_movil_name' => 'Chiringuito Restaurante El Ceibo'
            ]
        ];

        $stmtInsM = $pdo->prepare("INSERT INTO merchants (user_id, commercial_name, rif, rif_verified, category, sector, lat, lng, plus_code, phone, description, sales_count, sales_rep, service_rep, total_rep, is_open, pago_movil_bank, pago_movil_phone, pago_movil_ci, pago_movil_name) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        foreach ($realPlaces as $place) {
            // Buscar el user_id asignado a este comercio según su email
            $userId = (int)$pdo->query("SELECT id FROM users WHERE email = " . $pdo->quote($place['user_email']))->fetchColumn();
            if (!$userId) $userId = 1;

            $stmtInsM->execute([
                $userId,
                $place['commercial_name'],
                $place['rif'],
                $place['rif_verified'],
                $place['category'],
                $place['sector'],
                $place['lat'],
                $place['lng'],
                $place['plus_code'],
                $place['phone'],
                $place['description'],
                $place['sales_count'],
                $place['sales_rep'],
                $place['service_rep'],
                $place['total_rep'],
                $place['is_open'],
                $place['pago_movil_bank'],
                $place['pago_movil_phone'],
                $place['pago_movil_ci'],
                $place['pago_movil_name']
            ]);
        }
    }

    // SEMILLA: Productos y servicios individuales para cada comercio
    $stmtProd = $pdo->query("SELECT COUNT(*) FROM products_services");
    if ($stmtProd->fetchColumn() == 0) {
        $sampleProducts = [
            [1, 'Bolsita de Maíz para Palomas', 'Souvenirs', 1, 'Alimento especial para alimentar a las palomas en la histórica plaza.'],
            [1, 'Foto Polarizada del Recuerdo con Palomas', 'Fotografía', 3.5, 'Fotografía instantánea impresa con marco alusivo a Macuto.'],
            [1, 'Agua Mineral Helada 600ml', 'Bebidas', 1, 'Agua purificada fría para hidratarse durante el paseo.'],
            [2, 'Alquiler de Carrito Eléctrico (20 min)', 'Atracciones', 4, 'Carrito a batería para niños en circuito cerrado del parque.'],
            [2, 'Algodón de Azúcar Playero Gigante', 'Golosinas', 2, 'Algodón de azúcar multicolor recién elaborado.'],
            [2, 'Entrada Inflable Brincabrinca (30 min)', 'Atracciones', 3, 'Diversión saltarina segura con monitor infantil.'],
            [3, 'Fosforera Playera La Gaviota', 'Plato Fuerte', 12, 'Sopa concentrada marina con camarón, calamar, pepitonas y cangrejo.'],
            [3, 'Tostón Playero Especial con Queso y Ensalada', 'Entrada', 6.5, 'Plátano verde frito con queso llanero rallado y salsas tártara y rosada.'],
            [3, 'Cóctel Coco Loco Tropical', 'Bebidas', 5, 'Servido en coco natural con ron caribeño y leche de coco.'],
            [4, 'Paseo en Peñero por la Bahía (por persona)', 'Tours', 10, 'Recorrido marítimo de 45 minutos bordeando el rompeolas y avistamiento de aves.'],
            [4, 'Kilo de Rueda de Carite Fresco del Día', 'Pescadería', 7, 'Pescado recién capturado listo para preparar en rueda.'],
            [4, 'Tour de Pesca Artesanal de Fondo (2 horas)', 'Tours', 25, 'Experiencia vivencial de pesca con anzuelo y carnada incluida.'],
            [5, 'Empanada Gigante de Cazón Tradicional', 'Empanadas', 2, 'Masa crujiente de maíz rellena con guiso casero de cazón oriental.'],
            [5, 'Empanada Mixta de Camarón y Queso Paisa', 'Empanadas', 3, 'Camarones frescos salteados con queso blanco fundido.'],
            [5, 'Jarra de Papelón con Limón Frío', 'Bebidas', 2.5, 'Bebida tradicional venezolana con abundante hielo.'],
            [6, 'Habitación Matrimonial Vista al Mar (Noche)', 'Hospedaje', 45, 'Incluye aire acondicionado, wifi de alta velocidad y desayuno criollo.'],
            [6, 'Pasadía Familiar con Uso de Instalaciones', 'Hospedaje', 15, 'Acceso a terraza colonial, duchas, vestidores y área de descanso.'],
            [6, 'Desayuno Criollo Colonial', 'Gastronomía', 6, 'Arepas asadas, carne mechada, queso blanco, caraotas y café con leche.'],
            [7, 'Collar Artesanal de Caracoles y Perlas', 'Artesanías', 5, 'Hecho a mano por artesanas de Macuto con caracoles marinos.'],
            [7, 'Sombrero Playero de Paja Tejido', 'Artesanías', 8, 'Protección solar artesanal tejido fino con cinta decorativa.'],
            [7, 'Imán de Nevera Escultura Santa Ana', 'Souvenirs', 2.5, 'Recuerdo coleccionable en cerámica esmaltada.'],
            [8, 'Kilo de Pargo Rojo Entero Eviscerado', 'Pescadería', 9.5, 'Pescado fresco del litoral central, limpio y listo para freír o asar.'],
            [8, 'Kilo de Calamares Limpios', 'Mariscos', 8, 'Tubo y tentáculos de calamar fresco seleccionado.'],
            [8, 'Combo Marino Surtido para Sopa (1.5 Kg)', 'Pescadería', 11, 'Mezcla de pescado en trozos, camarón, pepitonas y cangrejo.'],
            [9, 'Alquiler Toldo Playero + 2 Sillas Confort', 'Servicios', 15, 'Día completo en la arena con sombra fresca y mesa de apoyo.'],
            [9, 'Tobito Playero con 6 Cervezas Nacionales Polar', 'Bebidas', 8, 'Hielo frappé y cervezas bien frías servidas directo a tu toldo.'],
            [9, 'Silla Reclinable Extra para Acompañante', 'Servicios', 4, 'Silla playera adicional para toda la estadía.'],
            [10, 'Protector Solar SPF 50 Resistente al Agua 120ml', 'Salud', 9, 'Fórmula dermatológica con protección UVA/UVB para toda la familia.'],
            [10, 'Gel Refrescante de Aloe Vera Post-Solar', 'Salud', 5.5, 'Alivio inmediato para pieles expuestas al sol con efecto calmante.'],
            [10, 'Kit Primeros Auxilios Playero', 'Salud', 6, 'Alcohol, gasas, curitas, analgésicos y crema para picaduras.'],
            [11, 'Cepillado Tradicional de Tamarindo y Colita', 'Postres', 1.5, 'Hielo raspado artesanal con jarabe de frutas y leche condensada.'],
            [11, 'Helado Cremoso de Coco en Vaso Doble', 'Postres', 2.5, 'Elaborado con pulpa fresca de coco del litoral.'],
            [11, 'Chicha Criolla con Canela y Leche Condensada', 'Bebidas', 2, 'Espesa, fría y endulzada al gusto.'],
            [12, 'Pase de Entrada Balneario Familiar (4 Personas)', 'Acceso', 5, 'Uso de duchas de agua dulce, vestidores y zona protegida.'],
            [12, 'Alquiler Flotador Salvavidas para Niños', 'Recreación', 3, 'Chaleco o flotador inflable certificado para nado seguro.'],
            [12, 'Guardarropa / Casillero Seguro (Día Completo)', 'Servicios', 2, 'Guarda tus pertenencias con candado y vigilancia.'],
            [13, 'Rueda de Carite Frito con Tostones y Tártara', 'Gastronomía', 11, 'Acompañado con ensalada rayada dulce y tostones doraditos.'],
            [13, 'Hervido de Pescado Criollo Sabatino', 'Gastronomía', 7.5, 'Sopa caliente reconfortante con verduras de la zona y cilantro.'],
            [13, 'Camarones al Ajillo en Cazuela de Barro', 'Gastronomía', 13, 'Salteados en aceite de oliva virgen con ajo dorado y perejil.'],
            [14, 'Coco Frío Natural con Pitillo', 'Bebidas', 1.5, 'Agua fresca de coco verde recién abierto con cuchara para la pulpa.'],
            [14, 'Paquete de Platanitos Fritos con Salsa Rosada', 'Snacks', 2, 'Plátanos crujientes fritos del día.'],
            [14, 'Refresco de Lata 355ml Frío', 'Bebidas', 1.5, 'Coca Cola, Pepsi o Chinotto bien helado.'],
            [15, 'Paella Marinera Valenciana Especial (Para 2)', 'Gastronomía', 28, 'Arroz con azafrán, camarones, calamares, mejillones y almejas.'],
            [15, 'Asopado de Mariscos 7 Mares', 'Gastronomía', 14, 'Cremoso y rebosante de frutos del mar fresco.'],
            [15, 'Copa de Sangría Marina de la Casa', 'Bebidas', 4.5, 'Vino tinto, frutas maceradas y un toque de licor de naranja.'],
            [16, 'Suite Familiar con Balcón y Piscina (Noche)', 'Hospedaje', 60, 'Capacidad hasta 4 personas, TV por cable, wifi y estacionamiento.'],
            [16, 'Pase de Día a la Piscina del Hotel', 'Recreación', 10, 'Acceso a piscina para adultos y niños con derecho a reposeras.'],
            [16, 'Hamburguesa Especial Jofer con Papas', 'Snacks', 6.5, 'Carne a la parrilla, tocineta, queso amarillo y papas fritas.'],
            [17, 'Combo Playero: Toldo + 2 Sillas + Cava con Hielo', 'Servicios', 16, 'Todo lo necesario para pasar el día en la arena con sombra garantizada.'],
            [17, 'Alquiler de Tabla Bodyboard (1 hora)', 'Deportes', 5, 'Tabla de espuma resistente para deslizarse en las olas suaves.'],
            [17, 'Bolsa de Hielo en Cubos 5 Kg', 'Servicios', 2.5, 'Hielo purificado para mantener frías tus bebidas.'],
            [18, 'Franela Oficial Algodón "Paseo Macuto La Guaira"', 'Ropa', 10, 'Franela con estampado playero de alta duración en varias tallas.'],
            [18, 'Gorra Bordada La Guaira Costa Bonita', 'Souvenirs', 7, 'Gorra trucker ajustable con bordado frontal.'],
            [18, 'Artesanía en Madera Flotante Tallada a Mano', 'Artesanías', 12, 'Peces y gaviotas tallados en madera de mar por artesanos locales.'],
            [19, 'Clase de Stand Up Paddle (1 hora con Instructor)', 'Deportes', 20, 'Incluye tabla profesional, remo, chaleco salvavidas y fotos acuáticas.'],
            [19, 'Alquiler de Kayak Doble (1 hora)', 'Deportes', 18, 'Kayak biplaza insumergible para navegar la bahía.'],
            [19, 'Tour Guiado en Paddle al Atardecer (Sunset Paddle)', 'Tours', 25, 'Remada mágica al caer el sol con brindis playero incluido.'],
            [20, 'Ceviche Costero El Ceibo con Batata y Maíz', 'Gastronomía', 9, 'Pescado blanco curado con limón criollo, ají dulce y cebolla morada.'],
            [20, 'Parrilla Mixta Mar y Tierra El Ceibo', 'Gastronomía', 18, 'Carne de res tierna, pechuga, camarones, calamares y yuca frita.'],
            [20, 'Balde de 10 Cervezas Artesanales del Litoral', 'Bebidas', 15, 'Cervezas costeras rubias y rojas heladas servidas bajo el ceibo.']
        ];
        $pStmt = $pdo->prepare("INSERT INTO products_services (merchant_id, name, category, price_usd, description) VALUES (?, ?, ?, ?, ?)");
        foreach ($sampleProducts as $sp) {
            $pStmt->execute($sp);
        }
    }

    // SEMILLA: Reseña y Denuncia de prueba
    $stmtRev = $pdo->query("SELECT COUNT(*) FROM reviews");
    if ($stmtRev->fetchColumn() == 0) {
        $pdo->prepare("INSERT INTO reviews (visitor_id, merchant_id, rating, comment, verified_purchase) VALUES (2, 3, 5, 'Excelente atención en La Gaviota, los tostones estaban crujientes y la vista al atardecer es insuperable.', 1)")->execute();
        $pdo->prepare("INSERT INTO reviews (visitor_id, merchant_id, rating, comment, verified_purchase) VALUES (2, 5, 5, 'Las mejores empanadas de Macuto, el cazón bien condimentado y sin grasa.', 1)")->execute();
    }

    $stmtComp = $pdo->query("SELECT COUNT(*) FROM complaints");
    if ($stmtComp->fetchColumn() == 0) {
        $pdo->prepare("INSERT INTO complaints (visitor_id, merchant_id, description, status, admin_notes) VALUES (2, 8, 'Cobro indebido y trato poco amable al consultar el precio del pescado por kilo.', 'pending', NULL)")->execute();
    }

    return true;
}

// Si se ejecuta directamente por CLI o web
if (php_sapi_name() === 'cli' || isset($_GET['run_setup'])) {
    initializeDatabase();
    echo "Base de datos y datos semilla inicializados con éxito. Conexión activa: " . Database::getDriver() . "\n";
}