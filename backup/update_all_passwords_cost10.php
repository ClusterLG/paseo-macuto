<?php
require_once dirname(__DIR__) . '/config/database.php';

$pdo = Database::getConnection();

$users = $pdo->query("SELECT id, email, role FROM public.users")->fetchAll(PDO::FETCH_ASSOC);

$knownPasswords = [
    'lams210488@gmail.com' => 'admin123',
    'turista@paseomacuto.com' => 'turista123'
];

foreach ($users as $u) {
    $email = strtolower(trim($u['email']));
    $role = $u['role'];
    $pass = $knownPasswords[$email] ?? ($role === 'merchant' ? 'comercio123' : 'turista123');

    $stmt = $pdo->prepare("
        UPDATE auth.users 
        SET encrypted_password = extensions.crypt(?, extensions.gen_salt('bf', 10)),
            phone_change = '',
            email_change = '',
            email_change_token_new = '',
            recovery_token = ''
        WHERE email = ?
    ");
    $stmt->execute([$pass, $email]);
    echo "Updated cost 10 for $email\n";
}

echo "All users updated with cost 10.\n";
