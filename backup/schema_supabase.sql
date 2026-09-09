-- =========================================================================
-- ESQUEMA SUPABASE / POSTGRESQL PARA PLATAFORMA "PASEO MACUTO"
-- Compatible con Supabase Auth y Storage
-- =========================================================================

-- 1. Configuraciones del Sistema (Tasa BCV)
CREATE TABLE IF NOT EXISTS public.system_settings (
    setting_key VARCHAR(100) PRIMARY KEY,
    setting_value TEXT NOT NULL,
    updated_by VARCHAR(100),
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT timezone('utc'::text, now())
);

-- 2. Usuarios y Roles
CREATE TABLE IF NOT EXISTS public.users (
    id BIGSERIAL PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    email VARCHAR(150) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role VARCHAR(50) NOT NULL DEFAULT 'visitor', -- superadmin, merchant, visitor
    level INT DEFAULT 1,
    xp BIGINT DEFAULT 0,
    phone VARCHAR(50),
    status VARCHAR(50) DEFAULT 'active',
    created_at TIMESTAMP WITH TIME ZONE DEFAULT timezone('utc'::text, now())
);

-- 3. Establecimientos / Comerciantes de Paseo Macuto
CREATE TABLE IF NOT EXISTS public.merchants (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT REFERENCES public.users(id) ON DELETE SET NULL,
    commercial_name VARCHAR(150) NOT NULL,
    rif VARCHAR(50) NOT NULL,
    rif_verified INT DEFAULT 0, -- 0: pendiente, 1: verificado, -1: rechazado
    category VARCHAR(100) NOT NULL,
    sector VARCHAR(100) NOT NULL,
    lat NUMERIC(10, 7) NOT NULL,
    lng NUMERIC(10, 7) NOT NULL,
    plus_code VARCHAR(50),
    phone VARCHAR(50),
    description TEXT,
    sales_count INT DEFAULT 0,
    sales_rep NUMERIC(3, 2) DEFAULT 5.00,
    service_rep NUMERIC(3, 2) DEFAULT 5.00,
    total_rep NUMERIC(3, 2) DEFAULT 5.00,
    is_open INT DEFAULT 1,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT timezone('utc'::text, now())
);

-- 4. Productos y Servicios
CREATE TABLE IF NOT EXISTS public.products_services (
    id BIGSERIAL PRIMARY KEY,
    merchant_id BIGINT REFERENCES public.merchants(id) ON DELETE CASCADE,
    name VARCHAR(150) NOT NULL,
    category VARCHAR(100),
    price_usd NUMERIC(10, 2) NOT NULL,
    description TEXT,
    image_url VARCHAR(255),
    is_active INT DEFAULT 1,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT timezone('utc'::text, now())
);

-- 5. Transacciones / Órdenes
CREATE TABLE IF NOT EXISTS public.orders_transactions (
    id BIGSERIAL PRIMARY KEY,
    visitor_id BIGINT REFERENCES public.users(id) ON DELETE CASCADE,
    merchant_id BIGINT REFERENCES public.merchants(id) ON DELETE CASCADE,
    product_id BIGINT REFERENCES public.products_services(id) ON DELETE SET NULL,
    amount_usd NUMERIC(10, 2) NOT NULL,
    amount_bs NUMERIC(12, 2) NOT NULL,
    status VARCHAR(50) DEFAULT 'completed',
    created_at TIMESTAMP WITH TIME ZONE DEFAULT timezone('utc'::text, now())
);

-- 6. Reseñas y Calificaciones
CREATE TABLE IF NOT EXISTS public.reviews (
    id BIGSERIAL PRIMARY KEY,
    visitor_id BIGINT REFERENCES public.users(id) ON DELETE CASCADE,
    merchant_id BIGINT REFERENCES public.merchants(id) ON DELETE CASCADE,
    order_id BIGINT REFERENCES public.orders_transactions(id) ON DELETE SET NULL,
    rating INT NOT NULL CHECK (rating >= 1 AND rating <= 5),
    comment TEXT,
    verified_purchase INT DEFAULT 1,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT timezone('utc'::text, now())
);

-- 7. Quejas y Denuncias
CREATE TABLE IF NOT EXISTS public.complaints (
    id BIGSERIAL PRIMARY KEY,
    visitor_id BIGINT REFERENCES public.users(id) ON DELETE CASCADE,
    merchant_id BIGINT REFERENCES public.merchants(id) ON DELETE CASCADE,
    description TEXT NOT NULL,
    status VARCHAR(50) DEFAULT 'pending', -- pending, reviewed, penalized, resolved
    admin_notes TEXT,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT timezone('utc'::text, now())
);

-- 8. Potenciadores de Gamificación (Boosters)
CREATE TABLE IF NOT EXISTS public.xp_boosters (
    id BIGSERIAL PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    multiplier NUMERIC(4, 2) NOT NULL DEFAULT 1.5,
    action_type VARCHAR(100) NOT NULL,
    is_active INT DEFAULT 1,
    description VARCHAR(255),
    created_at TIMESTAMP WITH TIME ZONE DEFAULT timezone('utc'::text, now())
);

-- 9. Logs de Actividad de Usuarios
CREATE TABLE IF NOT EXISTS public.user_activity_logs (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT REFERENCES public.users(id) ON DELETE CASCADE,
    action_type VARCHAR(100) NOT NULL,
    xp_earned INT DEFAULT 0,
    metadata TEXT,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT timezone('utc'::text, now())
);

-- Índices de Rendimiento Geográfico
CREATE INDEX IF NOT EXISTS idx_merchants_coords ON public.merchants(lat, lng);
CREATE INDEX IF NOT EXISTS idx_merchants_sector ON public.merchants(sector);
CREATE INDEX IF NOT EXISTS idx_merchants_category ON public.merchants(category);
