<?php
// config/supabase_auth.php - Cliente de Servicio para Supabase Auth (GoTrue API)

class SupabaseAuthService {
    private static $url = null;
    private static $anonKey = null;

    public static function init() {
        if (self::$url !== null && self::$anonKey !== null) {
            return;
        }

        self::$url = getenv('SUPABASE_URL') ?: 'https://rmolbppwwiqdmpbzclnk.supabase.co';
        self::$anonKey = getenv('SUPABASE_ANON_KEY') ?: '';

        if (file_exists(__DIR__ . '/supabase_config.php')) {
            $cfg = require __DIR__ . '/supabase_config.php';
            if (is_array($cfg)) {
                self::$url = $cfg['url'] ?? self::$url;
                self::$anonKey = $cfg['anon_key'] ?? self::$anonKey;
            }
        }
    }

    public static function isConfigured() {
        self::init();
        return !empty(self::$url) && !empty(self::$anonKey);
    }

    public static function getUrl() {
        self::init();
        return self::$url;
    }

    public static function getAnonKey() {
        self::init();
        return self::$anonKey;
    }

    private static function request($endpoint, $method = 'GET', $body = null, $extraHeaders = []) {
        self::init();
        if (!self::isConfigured()) {
            return ['success' => false, 'error' => 'Supabase Auth no configurado (falta anon_key)'];
        }

        $fullUrl = rtrim(self::$url, '/') . $endpoint;
        $ch = curl_init($fullUrl);

        $headers = array_merge([
            'Content-Type: application/json',
            'apikey: ' . self::$anonKey,
            'Authorization: Bearer ' . self::$anonKey
        ], $extraHeaders);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 6);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($body !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, is_string($body) ? $body : json_encode($body));
            }
        } elseif ($method !== 'GET') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
            if ($body !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, is_string($body) ? $body : json_encode($body));
            }
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            return ['success' => false, 'error' => 'Error de conexión cURL: ' . $curlError];
        }

        $json = json_decode($response, true);
        if ($httpCode >= 200 && $httpCode < 300) {
            return [
                'success' => true,
                'status' => $httpCode,
                'data' => $json
            ];
        }

        $errorMsg = $json['msg'] ?? $json['error_description'] ?? $json['message'] ?? $json['error'] ?? 'Error desconocido en Supabase Auth';
        return [
            'success' => false,
            'status' => $httpCode,
            'error' => $errorMsg,
            'data' => $json
        ];
    }

    /**
     * Inicia sesión con correo y contraseña contra Supabase Auth.
     */
    public static function signInWithPassword($email, $password) {
        $res = self::request('/auth/v1/token?grant_type=password', 'POST', [
            'email' => $email,
            'password' => $password
        ]);

        if ($res['success']) {
            return [
                'success' => true,
                'user' => $res['data']['user'] ?? null,
                'access_token' => $res['data']['access_token'] ?? null,
                'refresh_token' => $res['data']['refresh_token'] ?? null,
                'expires_in' => $res['data']['expires_in'] ?? 3600
            ];
        }

        return [
            'success' => false,
            'error' => $res['error']
        ];
    }

    /**
     * Registra un nuevo usuario en Supabase Auth.
     */
    public static function signUp($email, $password, $metadata = []) {
        $payload = [
            'email' => $email,
            'password' => $password
        ];
        if (!empty($metadata)) {
            $payload['data'] = $metadata;
        }

        $res = self::request('/auth/v1/signup', 'POST', $payload);

        if ($res['success']) {
            return [
                'success' => true,
                'user' => $res['data']['user'] ?? $res['data'],
                'session' => $res['data']['session'] ?? null
            ];
        }

        return [
            'success' => false,
            'error' => $res['error']
        ];
    }

    /**
     * Envía correo de recuperación de contraseña.
     */
    public static function resetPasswordForEmail($email) {
        $res = self::request('/auth/v1/recover', 'POST', [
            'email' => $email
        ]);

        if ($res['success']) {
            return [
                'success' => true,
                'message' => 'Se ha enviado un enlace de recuperación al correo electrónico.'
            ];
        }

        return [
            'success' => false,
            'error' => $res['error']
        ];
    }

    /**
     * Obtiene los datos del usuario a partir de su JWT de Supabase.
     */
    public static function getUser($jwt) {
        if (empty($jwt)) return null;
        $res = self::request('/auth/v1/user', 'GET', null, [
            'Authorization: Bearer ' . $jwt
        ]);
        return $res['success'] ? ($res['data'] ?? null) : null;
    }
}
