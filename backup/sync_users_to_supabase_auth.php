<?php
// backup/sync_users_to_supabase_auth.php - Sincroniza usuarios existentes con Supabase Auth directamente en PostgreSQL

require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/supabase_auth.php';

$pdo = Database::getConnection();
$driver = Database::getDriver();

if ($driver !== 'pgsql') {
    die("Este script de sincronización directa requiere conexión activa a PostgreSQL (Supabase).\n");
}

echo "Conectado a Supabase PostgreSQL. Sincronizando usuarios con auth.users...\n\n";

$knownPasswords = [
    'lams210488@gmail.com' => 'admin123',
    'turista@paseomacuto.com' => 'turista123'
];

$stmt = $pdo->query("SELECT id, name, email, role, supabase_uid FROM public.users ORDER BY id ASC");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

$synced = 0;

foreach ($users as $u) {
    $email = strtolower(trim($u['email']));
    $role = $u['role'];
    $name = $u['name'];
    $localId = $u['id'];
    $password = $knownPasswords[$email] ?? ($role === 'merchant' ? 'comercio123' : 'turista123');

    // Verificar si ya existe en auth.users
    $chk = $pdo->prepare("SELECT id FROM auth.users WHERE email = ?");
    $chk->execute([$email]);
    $existingUid = $chk->fetchColumn();

    if ($existingUid) {
        $pdo->prepare("UPDATE public.users SET supabase_uid = ? WHERE id = ?")->execute([$existingUid, $localId]);
        echo "[$localId] $email: Ya existe en auth.users (UID: $existingUid)\n";
        $synced++;
        continue;
    }

    // Insertar en auth.users y auth.identities
    try {
        $uidStmt = $pdo->query("SELECT extensions.gen_random_uuid()");
        $newUid = $uidStmt->fetchColumn();

        $meta = json_encode([
            'sub' => $newUid,
            'name' => $name,
            'role' => $role,
            'email' => $email,
            'local_id' => $localId,
            'email_verified' => true
        ]);
        $appMeta = json_encode([
            'provider' => 'email',
            'providers' => ['email']
        ]);

        $insUser = $pdo->prepare("
            INSERT INTO auth.users (
                instance_id, id, aud, role, email, encrypted_password,
                email_confirmed_at, raw_app_meta_data, raw_user_meta_data,
                created_at, updated_at, confirmation_token
            ) VALUES (
                '00000000-0000-0000-0000-000000000000', ?, 'authenticated', 'authenticated', ?,
                extensions.crypt(?, extensions.gen_salt('bf')),
                NOW(), ?::jsonb, ?::jsonb, NOW(), NOW(), ''
            )
        ");
        $insUser->execute([$newUid, $email, $password, $appMeta, $meta]);

        // Insertar en auth.identities (la columna email es generada automáticamente)
        $identityIdStmt = $pdo->query("SELECT extensions.gen_random_uuid()");
        $identityId = $identityIdStmt->fetchColumn();

        $insIdent = $pdo->prepare("
            INSERT INTO auth.identities (
                id, provider_id, user_id, identity_data, provider,
                last_sign_in_at, created_at, updated_at
            ) VALUES (
                ?, ?, ?, ?::jsonb, 'email',
                NOW(), NOW(), NOW()
            )
        ");
        $insIdent->execute([$identityId, $newUid, $newUid, $meta]);

        // Actualizar en public.users
        $pdo->prepare("UPDATE public.users SET supabase_uid = ? WHERE id = ?")->execute([$newUid, $localId]);

        echo "[$localId] $email: Sincronizado e inicializado en auth.users (UID: $newUid)\n";
        $synced++;
    } catch (Exception $e) {
        echo "[$localId] $email ERROR: " . $e->getMessage() . "\n";
    }
}

echo "\n--- Resumen de sincronización ---\n";
echo "Total usuarios en public.users: " . count($users) . "\n";
echo "Total sincronizados con Supabase Auth: $synced\n";
