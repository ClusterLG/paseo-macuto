<?php
// config/supabase_config.php - Credenciales de conexión a Supabase
// Copia este archivo como config/supabase_config.php y completa tus credenciales.

return [
    // Opción 1: Conexión directa a PostgreSQL de Supabase
    'host'     => 'db.tu-proyecto-ref.supabase.co', // o aws-0-region.pooler.supabase.com
    'port'     => 5432, // o 6543 para connection pooling
    'database' => 'postgres',
    'user'     => 'postgres',
    'password' => 'TU_CONTRASENA_DE_BASE_DE_DATOS_SUPABASE',

    // Opción 2: API REST de Supabase (PostgREST)
    'url'      => 'https://tu-proyecto-ref.supabase.co',
    'anon_key' => 'TU_SUPABASE_ANON_KEY',
    'service_role_key' => 'TU_SUPABASE_SERVICE_ROLE_KEY'
];
