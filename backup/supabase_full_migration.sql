-- =========================================================================
-- MIGRACION COMPLETA A SUPABASE (ESQUEMA + DATOS) - PASEO MACUTO
-- =========================================================================

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


-- =========================================================================
-- INSERCION DE DATOS SEMILLA EXISTENTES
-- =========================================================================

INSERT INTO public.system_settings (setting_key, setting_value, updated_by, updated_at) VALUES ('bcv_rate', '820.10', 'Administrador Paseo Macuto', '2026-09-09 19:29:59') ON CONFLICT (setting_key) DO UPDATE SET setting_value = EXCLUDED.setting_value;
INSERT INTO public.users (id, name, email, password_hash, role, level, xp, phone, status, created_at) VALUES (1, 'Administrador Paseo Macuto', 'lams210488@gmail.com', '$2y$10$siHf1A7FazrXhEcMNsf79O3AgUh2DFBE1JfpR1UQSLNb3WZxEX4Su', 'superadmin', 7, 1000096, NULL, 'active', '2026-09-09 14:42:36') ON CONFLICT (id) DO NOTHING;
INSERT INTO public.users (id, name, email, password_hash, role, level, xp, phone, status, created_at) VALUES (2, 'Carlos Baralt (La Gaviota)', 'gaviota@paseomacuto.com', '$2y$10$Y3Zb8CPckCXOumBulIABLeSfsAvqF7sr27ztj0x9iId.gzjnH8CJq', 'merchant', 2, 57, '+58 416 3030303', 'active', '2026-09-09 14:42:37') ON CONFLICT (id) DO NOTHING;
INSERT INTO public.users (id, name, email, password_hash, role, level, xp, phone, status, created_at) VALUES (3, 'Mariana González (Turista)', 'turista@paseomacuto.com', '$2y$10$iigqExCZkc0RTZ/tvKznu.WgB0x3gUUzi9ipQ69V2eUnKJP2jvtVi', 'visitor', 3, 562, NULL, 'active', '2026-09-09 14:42:37') ON CONFLICT (id) DO NOTHING;
INSERT INTO public.users (id, name, email, password_hash, role, level, xp, phone, status, created_at) VALUES (4, 'Nuevo Turista', 'nuevoturistapersonal@gmail.com', '$2y$10$5hhy1jDk36EEsvcbNcCeU.0MHU3Ihw1zMVB88AaR0/KsmUYo2KFmm', 'visitor', 3, 120, '+4245689467', 'active', '2026-09-09 15:23:09') ON CONFLICT (id) DO NOTHING;
INSERT INTO public.users (id, name, email, password_hash, role, level, xp, phone, status, created_at) VALUES (8, 'Juan Mata (Plaza Las Palomas)', 'palomas@paseomacuto.com', '$2y$10$Y3Zb8CPckCXOumBulIABLeSfsAvqF7sr27ztj0x9iId.gzjnH8CJq', 'merchant', 2, 50, '+58 412 1010101', 'active', '2026-09-09 19:58:54') ON CONFLICT (id) DO NOTHING;
INSERT INTO public.users (id, name, email, password_hash, role, level, xp, phone, status, created_at) VALUES (9, 'Beatriz Ramos (Parque Infantil)', 'parqueinfantil@paseomacuto.com', '$2y$10$Y3Zb8CPckCXOumBulIABLeSfsAvqF7sr27ztj0x9iId.gzjnH8CJq', 'merchant', 2, 50, '+58 414 1020202', 'active', '2026-09-09 19:58:54') ON CONFLICT (id) DO NOTHING;
INSERT INTO public.users (id, name, email, password_hash, role, level, xp, phone, status, created_at) VALUES (10, 'Rafael Domínguez (CONPPA Macuto)', 'conppa@paseomacuto.com', '$2y$10$Y3Zb8CPckCXOumBulIABLeSfsAvqF7sr27ztj0x9iId.gzjnH8CJq', 'merchant', 2, 58, '+58 424 4040404', 'active', '2026-09-09 19:58:54') ON CONFLICT (id) DO NOTHING;
INSERT INTO public.users (id, name, email, password_hash, role, level, xp, phone, status, created_at) VALUES (11, 'Rosa Pataru (Empanadas Pataru)', 'pataru@paseomacuto.com', '$2y$10$Y3Zb8CPckCXOumBulIABLeSfsAvqF7sr27ztj0x9iId.gzjnH8CJq', 'merchant', 2, 58, '+58 412 5050505', 'active', '2026-09-09 19:58:54') ON CONFLICT (id) DO NOTHING;
INSERT INTO public.users (id, name, email, password_hash, role, level, xp, phone, status, created_at) VALUES (12, 'Alberto Cisneros (Hotel Colonial)', 'hotelcolonial@paseomacuto.com', '$2y$10$Y3Zb8CPckCXOumBulIABLeSfsAvqF7sr27ztj0x9iId.gzjnH8CJq', 'merchant', 2, 50, '+58 212 3551200', 'active', '2026-09-09 19:58:54') ON CONFLICT (id) DO NOTHING;
INSERT INTO public.users (id, name, email, password_hash, role, level, xp, phone, status, created_at) VALUES (13, 'Carmen Lucía Santana (Fuente Santa Ana)', 'santaana@paseomacuto.com', '$2y$10$Y3Zb8CPckCXOumBulIABLeSfsAvqF7sr27ztj0x9iId.gzjnH8CJq', 'merchant', 2, 50, '+58 414 0001122', 'active', '2026-09-09 19:58:54') ON CONFLICT (id) DO NOTHING;
INSERT INTO public.users (id, name, email, password_hash, role, level, xp, phone, status, created_at) VALUES (14, 'Miguel Marín (Pescadería Mar y Sabor)', 'pescaderia@paseomacuto.com', '$2y$10$Y3Zb8CPckCXOumBulIABLeSfsAvqF7sr27ztj0x9iId.gzjnH8CJq', 'merchant', 2, 50, '+58 416 7070707', 'active', '2026-09-09 19:58:54') ON CONFLICT (id) DO NOTHING;
INSERT INTO public.users (id, name, email, password_hash, role, level, xp, phone, status, created_at) VALUES (15, 'Héctor Zambrano (Toldos Oeste)', 'toldosoeste@paseomacuto.com', '$2y$10$Y3Zb8CPckCXOumBulIABLeSfsAvqF7sr27ztj0x9iId.gzjnH8CJq', 'merchant', 2, 50, '+58 424 9090909', 'active', '2026-09-09 19:58:54') ON CONFLICT (id) DO NOTHING;
INSERT INTO public.users (id, name, email, password_hash, role, level, xp, phone, status, created_at) VALUES (16, 'Dra. Elena Morales (Farmacia Macuvet)', 'macuvet@paseomacuto.com', '$2y$10$Y3Zb8CPckCXOumBulIABLeSfsAvqF7sr27ztj0x9iId.gzjnH8CJq', 'merchant', 2, 50, '+58 212 3552233', 'active', '2026-09-09 19:58:54') ON CONFLICT (id) DO NOTHING;
INSERT INTO public.users (id, name, email, password_hash, role, level, xp, phone, status, created_at) VALUES (17, 'Gabriel Blanco (Boulevar Macuto)', 'boulevard@paseomacuto.com', '$2y$10$Y3Zb8CPckCXOumBulIABLeSfsAvqF7sr27ztj0x9iId.gzjnH8CJq', 'merchant', 2, 50, '+58 412 0011223', 'active', '2026-09-09 19:58:54') ON CONFLICT (id) DO NOTHING;
INSERT INTO public.users (id, name, email, password_hash, role, level, xp, phone, status, created_at) VALUES (18, 'Luis Enrique Sosa (Balneario Sector A)', 'balneario@paseomacuto.com', '$2y$10$Y3Zb8CPckCXOumBulIABLeSfsAvqF7sr27ztj0x9iId.gzjnH8CJq', 'merchant', 2, 50, '+58 414 2233445', 'active', '2026-09-09 19:58:54') ON CONFLICT (id) DO NOTHING;
INSERT INTO public.users (id, name, email, password_hash, role, level, xp, phone, status, created_at) VALUES (19, 'Fernando Da Silva (Macuto II)', 'macuto2@paseomacuto.com', '$2y$10$Y3Zb8CPckCXOumBulIABLeSfsAvqF7sr27ztj0x9iId.gzjnH8CJq', 'merchant', 2, 50, '+58 212 3554411', 'active', '2026-09-09 19:58:54') ON CONFLICT (id) DO NOTHING;
INSERT INTO public.users (id, name, email, password_hash, role, level, xp, phone, status, created_at) VALUES (20, 'Maritza Gómez (Sol y Arena)', 'solyarena@paseomacuto.com', '$2y$10$Y3Zb8CPckCXOumBulIABLeSfsAvqF7sr27ztj0x9iId.gzjnH8CJq', 'merchant', 2, 50, '+58 424 5566778', 'active', '2026-09-09 19:58:54') ON CONFLICT (id) DO NOTHING;
INSERT INTO public.users (id, name, email, password_hash, role, level, xp, phone, status, created_at) VALUES (21, 'Antonio D''Amico (Sol Y Mar)', 'solymar@paseomacuto.com', '$2y$10$Y3Zb8CPckCXOumBulIABLeSfsAvqF7sr27ztj0x9iId.gzjnH8CJq', 'merchant', 2, 58, '+58 212 3553388', 'active', '2026-09-09 19:58:54') ON CONFLICT (id) DO NOTHING;
INSERT INTO public.users (id, name, email, password_hash, role, level, xp, phone, status, created_at) VALUES (22, 'José Fernández (Hotel Jofer)', 'hoteljofer@paseomacuto.com', '$2y$10$Y3Zb8CPckCXOumBulIABLeSfsAvqF7sr27ztj0x9iId.gzjnH8CJq', 'merchant', 2, 50, '+58 212 3552900', 'active', '2026-09-09 19:58:54') ON CONFLICT (id) DO NOTHING;
INSERT INTO public.users (id, name, email, password_hash, role, level, xp, phone, status, created_at) VALUES (23, 'Daniel Castillo (Toldos Playa Este)', 'playaeste@paseomacuto.com', '$2y$10$Y3Zb8CPckCXOumBulIABLeSfsAvqF7sr27ztj0x9iId.gzjnH8CJq', 'merchant', 2, 50, '+58 414 7788990', 'active', '2026-09-09 19:58:55') ON CONFLICT (id) DO NOTHING;
INSERT INTO public.users (id, name, email, password_hash, role, level, xp, phone, status, created_at) VALUES (24, 'Vicente Peñaloza (Mirador Macuto)', 'mirador@paseomacuto.com', '$2y$10$Y3Zb8CPckCXOumBulIABLeSfsAvqF7sr27ztj0x9iId.gzjnH8CJq', 'merchant', 2, 50, '+58 416 9988776', 'active', '2026-09-09 19:58:55') ON CONFLICT (id) DO NOTHING;
INSERT INTO public.users (id, name, email, password_hash, role, level, xp, phone, status, created_at) VALUES (25, 'Santiago Urbina (Paddle Surf)', 'paddlesurf@paseomacuto.com', '$2y$10$Y3Zb8CPckCXOumBulIABLeSfsAvqF7sr27ztj0x9iId.gzjnH8CJq', 'merchant', 2, 50, '+58 412 3456789', 'active', '2026-09-09 19:58:55') ON CONFLICT (id) DO NOTHING;
INSERT INTO public.users (id, name, email, password_hash, role, level, xp, phone, status, created_at) VALUES (26, 'Andrés Ceiba (El Ceibo)', 'elceibo@paseomacuto.com', '$2y$10$Y3Zb8CPckCXOumBulIABLeSfsAvqF7sr27ztj0x9iId.gzjnH8CJq', 'merchant', 2, 50, '+58 424 1234567', 'active', '2026-09-09 19:58:55') ON CONFLICT (id) DO NOTHING;
SELECT setval('users_id_seq', (SELECT COALESCE(MAX(id), 1) FROM public.users));
INSERT INTO public.merchants (id, user_id, commercial_name, rif, rif_verified, category, sector, lat, lng, plus_code, phone, description, sales_count, sales_rep, service_rep, total_rep, is_open, created_at) VALUES (1, 8, 'Plaza Andrés Mata (Las Palomas)', 'G-20000101-1', 1, 'Sitio Histórico y Recreativo', 'Sector Palomas (Oeste)', 10.6055589, -66.8965135, '769HJ343+6C', '+58 412 1010101', 'Plaza histórica arbolada frente al mar, punto de partida del Paseo Macuto famosa por sus palomas y esculturas.', 85, 4.9, 4.8, 4.85, 1, '2026-09-09 14:42:37') ON CONFLICT (id) DO NOTHING;
INSERT INTO public.merchants (id, user_id, commercial_name, rif, rif_verified, category, sector, lat, lng, plus_code, phone, description, sales_count, sales_rep, service_rep, total_rep, is_open, created_at) VALUES (2, 9, 'Parque Infantil Macuto', 'G-20000102-2', 1, 'Recreación Familiar', 'Sector Palomas (Oeste)', 10.6056834, -66.8962599, '769HJ343+7F', '+58 414 1020202', 'Área de juegos infantiles, alquiler de carritos y golosinas tradicionales frente a la brisa marina.', 120, 4.8, 4.7, 4.75, 1, '2026-09-09 14:42:37') ON CONFLICT (id) DO NOTHING;
INSERT INTO public.merchants (id, user_id, commercial_name, rif, rif_verified, category, sector, lat, lng, plus_code, phone, description, sales_count, sales_rep, service_rep, total_rep, is_open, created_at) VALUES (3, 2, 'Bar Restaurant La Gaviota', 'J-31456789-0', 1, 'Gastronomía y Bebidas', 'Sector Palomas (Oeste)', 10.6059109, -66.8959181, '769HJ343+9J', '+58 416 3030303', 'Clásico bar restaurante frente al malecón. Famoso por sus cócteles tropicales, tostones playeros y fosforera.', 432, 5, 4.9, 4.925000000000001, 1, '2026-09-09 14:42:37') ON CONFLICT (id) DO NOTHING;
INSERT INTO public.merchants (id, user_id, commercial_name, rif, rif_verified, category, sector, lat, lng, plus_code, phone, description, sales_count, sales_rep, service_rep, total_rep, is_open, created_at) VALUES (4, 10, 'CONPPA Paseo Macuto', 'G-20045612-4', 1, 'Pescadores y Servicios Marítimos', 'Sector Pescadores', 10.6065247, -66.8945678, '769HJ344+J5', '+58 424 4040404', 'Consejo de Pescadores Artesanales de Macuto. Venta directa de pescado fresco del día y paseos costeros en peñero.', 611, 5, 4.8, 4.9, 1, '2026-09-09 14:42:37') ON CONFLICT (id) DO NOTHING;
INSERT INTO public.merchants (id, user_id, commercial_name, rif, rif_verified, category, sector, lat, lng, plus_code, phone, description, sales_count, sales_rep, service_rep, total_rep, is_open, created_at) VALUES (5, 11, 'El Sabor de los Pataru', 'V-14892341-2', 1, 'Comida Rápida y Típica', 'Sector Pescadores', 10.6062727, -66.8945065, '769HJ344+G6', '+58 412 5050505', 'Empanadas gigantes de cazón, calamar, camarón y queso paisa recién fritas a orilla de playa.', 951, 4.9, 4.9, 4.9, 1, '2026-09-09 14:42:37') ON CONFLICT (id) DO NOTHING;
INSERT INTO public.merchants (id, user_id, commercial_name, rif, rif_verified, category, sector, lat, lng, plus_code, phone, description, sales_count, sales_rep, service_rep, total_rep, is_open, created_at) VALUES (6, 12, 'Hotel Colonial Macuto', 'J-00124589-9', 1, 'Hospedaje y Posada', 'Sector Tradicional', 10.6059573, -66.8943961, '769HJ344+96', '+58 212 3551200', 'Tradicional edificación con vista al mar, habitaciones con aire acondicionado y restaurante interno.', 180, 4.7, 4.6, 4.65, 1, '2026-09-09 14:42:37') ON CONFLICT (id) DO NOTHING;
INSERT INTO public.merchants (id, user_id, commercial_name, rif, rif_verified, category, sector, lat, lng, plus_code, phone, description, sales_count, sales_rep, service_rep, total_rep, is_open, created_at) VALUES (7, 13, 'Fuente de Santa Ana de Macuto', 'G-20099900-5', 1, 'Monumento y Paseo', 'Sector Tradicional', 10.6057242, -66.8943655, '769HJ344+77', '+58 414 0001122', 'Monumento patrimonial de Macuto, punto de encuentro turístico y área de artesanos locales.', 50, 4.9, 4.9, 4.9, 1, '2026-09-09 14:42:37') ON CONFLICT (id) DO NOTHING;
INSERT INTO public.merchants (id, user_id, commercial_name, rif, rif_verified, category, sector, lat, lng, plus_code, phone, description, sales_count, sales_rep, service_rep, total_rep, is_open, created_at) VALUES (8, 14, 'Pescadería Macuto Mar y Sabor', 'V-16782390-3', 1, 'Pescadería y Marisquería', 'Sector Tradicional', 10.6055868, -66.8943803, '769HJ344+67', '+58 416 7070707', 'Pargo, mero, corocoro, camarones y pulpo fresco arreglado y empacado para llevar o preparar.', 310, 4.6, 2.9000000000000004, 3.45, 1, '2026-09-09 14:42:37') ON CONFLICT (id) DO NOTHING;
INSERT INTO public.merchants (id, user_id, commercial_name, rif, rif_verified, category, sector, lat, lng, plus_code, phone, description, sales_count, sales_rep, service_rep, total_rep, is_open, created_at) VALUES (9, 15, 'Playa Macuto - Sector Toldos Oeste', 'V-19821034-7', 1, 'Toldos y Servicios Playeros', 'Sector Balneario Central', 10.6066734, -66.8940273, '769HJ344+M9', '+58 424 9090909', 'Alquiler de toldos confortables, sillas y atención directa en arena con servicio de bebidas y hielo.', 820, 4.8, 4.7, 4.75, 1, '2026-09-09 14:42:37') ON CONFLICT (id) DO NOTHING;
INSERT INTO public.merchants (id, user_id, commercial_name, rif, rif_verified, category, sector, lat, lng, plus_code, phone, description, sales_count, sales_rep, service_rep, total_rep, is_open, created_at) VALUES (10, 16, 'Farmacia Macuvet y Playa', 'J-40556677-1', 1, 'Salud y Farmacia', 'Sector Balneario Central', 10.6063268, -66.8936181, '769HJ344+GC', '+58 212 3552233', 'Protectores solares, medicamentos de primeros auxilios, hidratación y productos esenciales para el turista.', 240, 4.9, 4.8, 4.85, 1, '2026-09-09 14:42:37') ON CONFLICT (id) DO NOTHING;
INSERT INTO public.merchants (id, user_id, commercial_name, rif, rif_verified, category, sector, lat, lng, plus_code, phone, description, sales_count, sales_rep, service_rep, total_rep, is_open, created_at) VALUES (11, 17, 'Paseo La Playa Boulevar', 'G-20077889-0', 1, 'Paseo Turístico y Heladerías', 'Sector Balneario Central', 10.6068974, -66.8936284, '769HJ344+QC', '+58 412 0011223', 'Corredor principal con heladerías artesanales de coco, cepillados tradicionales y venta de artesanías.', 540, 4.8, 4.8, 4.8, 1, '2026-09-09 14:42:37') ON CONFLICT (id) DO NOTHING;
INSERT INTO public.merchants (id, user_id, commercial_name, rif, rif_verified, category, sector, lat, lng, plus_code, phone, description, sales_count, sales_rep, service_rep, total_rep, is_open, created_at) VALUES (12, 18, 'Balneario Macuto Sector A', 'V-15678432-8', 1, 'Balneario y Entretenimiento', 'Sector Balneario Central', 10.6070715, -66.8930758, '769HJ344+RR', '+58 414 2233445', 'Zona de playa protegida por rompeolas, ideal para niños. Duchas de agua dulce, vestidores y salvavidas.', 1100, 4.7, 4.6, 4.65, 1, '2026-09-09 14:42:37') ON CONFLICT (id) DO NOTHING;
INSERT INTO public.merchants (id, user_id, commercial_name, rif, rif_verified, category, sector, lat, lng, plus_code, phone, description, sales_count, sales_rep, service_rep, total_rep, is_open, created_at) VALUES (13, 19, 'Bar Restaurant Macuto II', 'J-30987654-2', 1, 'Gastronomía y Pescados', 'Sector Balneario Central', 10.6064669, -66.8932446, '769HJ344+HQ', '+58 212 3554411', 'Especialistas en rueda de carite frito, pargo al ajillo, ensalada mixta y hervido de pescado sabatino.', 740, 4.9, 4.9, 4.9, 1, '2026-09-09 14:42:37') ON CONFLICT (id) DO NOTHING;
INSERT INTO public.merchants (id, user_id, commercial_name, rif, rif_verified, category, sector, lat, lng, plus_code, phone, description, sales_count, sales_rep, service_rep, total_rep, is_open, created_at) VALUES (14, 20, 'Quiosco Sol y Arena Balneario', 'V-20112445-9', 1, 'Snacks y Bebidas', 'Sector Balneario Central', 10.6070331, -66.8931122, '769HJ344+RP', '+58 424 5566778', 'Cocos fríos, agua mineral, jugos naturales de papelón con limón y snacks playeros.', 190, 4.7, 4.5, 4.6, 1, '2026-09-09 14:42:37') ON CONFLICT (id) DO NOTHING;
INSERT INTO public.merchants (id, user_id, commercial_name, rif, rif_verified, category, sector, lat, lng, plus_code, phone, description, sales_count, sales_rep, service_rep, total_rep, is_open, created_at) VALUES (15, 21, 'Restaurant Sol Y Mar', 'J-40112233-4', 1, 'Gastronomía y Marisquería', 'Sector Paseo Este', 10.6067014, -66.8927311, '769HJ344+MW', '+58 212 3553388', 'Terraza marina de dos niveles con vista panorámica a la bahía. Paella marinera, asopado y mariscos al gratén.', 891, 4.95, 4.9, 4.9, 1, '2026-09-09 14:42:37') ON CONFLICT (id) DO NOTHING;
INSERT INTO public.merchants (id, user_id, commercial_name, rif, rif_verified, category, sector, lat, lng, plus_code, phone, description, sales_count, sales_rep, service_rep, total_rep, is_open, created_at) VALUES (16, 22, 'Hotel Jofer Macuto', 'J-29871100-8', 1, 'Hospedaje y Turismo', 'Sector Paseo Este', 10.6067767, -66.8926583, '769HJ344+PW', '+58 212 3552900', 'Hotel turístico con estacionamiento privado, wifi, piscina y cercanía inmediata a la franja de playa.', 310, 4.8, 4.7, 4.75, 1, '2026-09-09 14:42:37') ON CONFLICT (id) DO NOTHING;
INSERT INTO public.merchants (id, user_id, commercial_name, rif, rif_verified, category, sector, lat, lng, plus_code, phone, description, sales_count, sales_rep, service_rep, total_rep, is_open, created_at) VALUES (17, 23, 'Playa Macuto Sector Este', 'V-18774433-1', 1, 'Toldos y Actividades Acuáticas', 'Sector Paseo Este', 10.606667, -66.8925, '769HJ345+MG', '+58 414 7788990', 'Oleaje suave para nado, alquiler de tablas de flotación, toldos familiares y música ambiental suave.', 670, 4.8, 4.6, 4.7, 1, '2026-09-09 14:42:37') ON CONFLICT (id) DO NOTHING;
INSERT INTO public.merchants (id, user_id, commercial_name, rif, rif_verified, category, sector, lat, lng, plus_code, phone, description, sales_count, sales_rep, service_rep, total_rep, is_open, created_at) VALUES (18, 24, 'Mirador Paseo de Macuto', 'G-20033441-2', 1, 'Mirador y Artesanías', 'Sector Paseo Este', 10.60788, -66.8923029, '769HJ355+43', '+58 416 9988776', 'Punto fotográfico emblemático del paseo, venta de collares de perlas, franelas de La Guaira y recuerdos.', 410, 4.9, 4.9, 4.9, 1, '2026-09-09 14:42:37') ON CONFLICT (id) DO NOTHING;
INSERT INTO public.merchants (id, user_id, commercial_name, rif, rif_verified, category, sector, lat, lng, plus_code, phone, description, sales_count, sales_rep, service_rep, total_rep, is_open, created_at) VALUES (19, 25, 'La Guaira Paddle Surf & Kayak', 'J-50119988-6', 1, 'Deportes Acuáticos y Aventura', 'Sector Náutico Este', 10.608699, -66.8920996, '769HJ355+FM', '+58 412 3456789', 'Clases de Stand Up Paddle, paseos guiados en kayak al amanecer y atardecer, chalecos salvavidas e instructores certificados.', 520, 5, 5, 5, 1, '2026-09-09 14:42:37') ON CONFLICT (id) DO NOTHING;
INSERT INTO public.merchants (id, user_id, commercial_name, rif, rif_verified, category, sector, lat, lng, plus_code, phone, description, sales_count, sales_rep, service_rep, total_rep, is_open, created_at) VALUES (20, 26, 'Chiringuito y Restaurante El Ceibo', 'V-13456789-0', 1, 'Gastronomía y Chill Out', 'Sector El Ceibo (Extremo Este)', 10.6095397, -66.8912995, '769HJ355+RF', '+58 424 1234567', 'Remate este del Paseo Macuto. Chiringuito playero con mesas de madera bajo el ceibo histórico, cervezas heladas y ceviche costeño.', 930, 4.9, 4.9, 4.9, 1, '2026-09-09 14:42:37') ON CONFLICT (id) DO NOTHING;
SELECT setval('merchants_id_seq', (SELECT COALESCE(MAX(id), 1) FROM public.merchants));
INSERT INTO public.products_services (id, merchant_id, name, category, price_usd, description, image_url, is_active, created_at) VALUES (11, 1, 'Bolsita de Maíz para Palomas', 'Souvenirs', 1, 'Alimento especial para alimentar a las palomas en la histórica plaza.', NULL, 1, '2026-09-09 19:58:54') ON CONFLICT (id) DO NOTHING;
INSERT INTO public.products_services (id, merchant_id, name, category, price_usd, description, image_url, is_active, created_at) VALUES (12, 1, 'Foto Polarizada del Recuerdo con Palomas', 'Fotografía', 3.5, 'Fotografía instantánea impresa con marco alusivo a Macuto.', NULL, 1, '2026-09-09 19:58:54') ON CONFLICT (id) DO NOTHING;
INSERT INTO public.products_services (id, merchant_id, name, category, price_usd, description, image_url, is_active, created_at) VALUES (13, 1, 'Agua Mineral Helada 600ml', 'Bebidas', 1, 'Agua purificada fría para hidratarse durante el paseo.', NULL, 1, '2026-09-09 19:58:54') ON CONFLICT (id) DO NOTHING;
INSERT INTO public.products_services (id, merchant_id, name, category, price_usd, description, image_url, is_active, created_at) VALUES (14, 2, 'Alquiler de Carrito Eléctrico (20 min)', 'Atracciones', 4, 'Carrito a batería para niños en circuito cerrado del parque.', NULL, 1, '2026-09-09 19:58:54') ON CONFLICT (id) DO NOTHING;
INSERT INTO public.products_services (id, merchant_id, name, category, price_usd, description, image_url, is_active, created_at) VALUES (15, 2, 'Algodón de Azúcar Playero Gigante', 'Golosinas', 2, 'Algodón de azúcar multicolor recién elaborado.', NULL, 1, '2026-09-09 19:58:54') ON CONFLICT (id) DO NOTHING;
INSERT INTO public.products_services (id, merchant_id, name, category, price_usd, description, image_url, is_active, created_at) VALUES (16, 2, 'Entrada Inflable Brincabrinca (30 min)', 'Atracciones', 3, 'Diversión saltarina segura con monitor infantil.', NULL, 1, '2026-09-09 19:58:54') ON CONFLICT (id) DO NOTHING;
INSERT INTO public.products_services (id, merchant_id, name, category, price_usd, description, image_url, is_active, created_at) VALUES (17, 3, 'Fosforera Playera La Gaviota', 'Plato Fuerte', 12, 'Sopa concentrada marina con camarón, calamar, pepitonas y cangrejo.', NULL, 1, '2026-09-09 19:58:54') ON CONFLICT (id) DO NOTHING;
INSERT INTO public.products_services (id, merchant_id, name, category, price_usd, description, image_url, is_active, created_at) VALUES (18, 3, 'Tostón Playero Especial con Queso y Ensalada', 'Entrada', 6.5, 'Plátano verde frito con queso llanero rallado y salsas tártara y rosada.', NULL, 1, '2026-09-09 19:58:54') ON CONFLICT (id) DO NOTHING;
INSERT INTO public.products_services (id, merchant_id, name, category, price_usd, description, image_url, is_active, created_at) VALUES (19, 3, 'Cóctel Coco Loco Tropical', 'Bebidas', 5, 'Servido en coco natural con ron caribeño y leche de coco.', NULL, 1, '2026-09-09 19:58:54') ON CONFLICT (id) DO NOTHING;
INSERT INTO public.products_services (id, merchant_id, name, category, price_usd, description, image_url, is_active, created_at) VALUES (20, 4, 'Paseo en Peñero por la Bahía (por persona)', 'Tours', 10, 'Recorrido marítimo de 45 minutos bordeando el rompeolas y avistamiento de aves.', NULL, 1, '2026-09-09 19:58:54') ON CONFLICT (id) DO NOTHING;
INSERT INTO public.products_services (id, merchant_id, name, category, price_usd, description, image_url, is_active, created_at) VALUES (21, 4, 'Kilo de Rueda de Carite Fresco del Día', 'Pescadería', 7, 'Pescado recién capturado listo para preparar en rueda.', NULL, 1, '2026-09-09 19:58:54') ON CONFLICT (id) DO NOTHING;
INSERT INTO public.products_services (id, merchant_id, name, category, price_usd, description, image_url, is_active, created_at) VALUES (22, 4, 'Tour de Pesca Artesanal de Fondo (2 horas)', 'Tours', 25, 'Experiencia vivencial de pesca con anzuelo y carnada incluida.', NULL, 1, '2026-09-09 19:58:54') ON CONFLICT (id) DO NOTHING;
INSERT INTO public.products_services (id, merchant_id, name, category, price_usd, description, image_url, is_active, created_at) VALUES (23, 5, 'Empanada Gigante de Cazón Tradicional', 'Empanadas', 2, 'Masa crujiente de maíz rellena con guiso casero de cazón oriental.', NULL, 1, '2026-09-09 19:58:54') ON CONFLICT (id) DO NOTHING;
INSERT INTO public.products_services (id, merchant_id, name, category, price_usd, description, image_url, is_active, created_at) VALUES (24, 5, 'Empanada Mixta de Camarón y Queso Paisa', 'Empanadas', 3, 'Camarones frescos salteados con queso blanco fundido.', NULL, 1, '2026-09-09 19:58:54') ON CONFLICT (id) DO NOTHING;
INSERT INTO public.products_services (id, merchant_id, name, category, price_usd, description, image_url, is_active, created_at) VALUES (25, 5, 'Jarra de Papelón con Limón Frío', 'Bebidas', 2.5, 'Bebida tradicional venezolana con abundante hielo.', NULL, 1, '2026-09-09 19:58:54') ON CONFLICT (id) DO NOTHING;
INSERT INTO public.products_services (id, merchant_id, name, category, price_usd, description, image_url, is_active, created_at) VALUES (26, 6, 'Habitación Matrimonial Vista al Mar (Noche)', 'Hospedaje', 45, 'Incluye aire acondicionado, wifi de alta velocidad y desayuno criollo.', NULL, 1, '2026-09-09 19:58:54') ON CONFLICT (id) DO NOTHING;
INSERT INTO public.products_services (id, merchant_id, name, category, price_usd, description, image_url, is_active, created_at) VALUES (27, 6, 'Pasadía Familiar con Uso de Instalaciones', 'Hospedaje', 15, 'Acceso a terraza colonial, duchas, vestidores y área de descanso.', NULL, 1, '2026-09-09 19:58:54') ON CONFLICT (id) DO NOTHING;
INSERT INTO public.products_services (id, merchant_id, name, category, price_usd, description, image_url, is_active, created_at) VALUES (28, 6, 'Desayuno Criollo Colonial', 'Gastronomía', 6, 'Arepas asadas, carne mechada, queso blanco, caraotas y café con leche.', NULL, 1, '2026-09-09 19:58:54') ON CONFLICT (id) DO NOTHING;
INSERT INTO public.products_services (id, merchant_id, name, category, price_usd, description, image_url, is_active, created_at) VALUES (29, 7, 'Collar Artesanal de Caracoles y Perlas', 'Artesanías', 5, 'Hecho a mano por artesanas de Macuto con caracoles marinos.', NULL, 1, '2026-09-09 19:58:54') ON CONFLICT (id) DO NOTHING;
INSERT INTO public.products_services (id, merchant_id, name, category, price_usd, description, image_url, is_active, created_at) VALUES (30, 7, 'Sombrero Playero de Paja Tejido', 'Artesanías', 8, 'Protección solar artesanal tejido fino con cinta decorativa.', NULL, 1, '2026-09-09 19:58:54') ON CONFLICT (id) DO NOTHING;
INSERT INTO public.products_services (id, merchant_id, name, category, price_usd, description, image_url, is_active, created_at) VALUES (31, 7, 'Imán de Nevera Escultura Santa Ana', 'Souvenirs', 2.5, 'Recuerdo coleccionable en cerámica esmaltada.', NULL, 1, '2026-09-09 19:58:54') ON CONFLICT (id) DO NOTHING;
INSERT INTO public.products_services (id, merchant_id, name, category, price_usd, description, image_url, is_active, created_at) VALUES (32, 8, 'Kilo de Pargo Rojo Entero Eviscerado', 'Pescadería', 9.5, 'Pescado fresco del litoral central, limpio y listo para freír o asar.', NULL, 1, '2026-09-09 19:58:54') ON CONFLICT (id) DO NOTHING;
INSERT INTO public.products_services (id, merchant_id, name, category, price_usd, description, image_url, is_active, created_at) VALUES (33, 8, 'Kilo de Calamares Limpios', 'Mariscos', 8, 'Tubo y tentáculos de calamar fresco seleccionado.', NULL, 1, '2026-09-09 19:58:54') ON CONFLICT (id) DO NOTHING;
INSERT INTO public.products_services (id, merchant_id, name, category, price_usd, description, image_url, is_active, created_at) VALUES (34, 8, 'Combo Marino Surtido para Sopa (1.5 Kg)', 'Pescadería', 11, 'Mezcla de pescado en trozos, camarón, pepitonas y cangrejo.', NULL, 1, '2026-09-09 19:58:54') ON CONFLICT (id) DO NOTHING;
INSERT INTO public.products_services (id, merchant_id, name, category, price_usd, description, image_url, is_active, created_at) VALUES (35, 9, 'Alquiler Toldo Playero + 2 Sillas Confort', 'Servicios', 15, 'Día completo en la arena con sombra fresca y mesa de apoyo.', NULL, 1, '2026-09-09 19:58:54') ON CONFLICT (id) DO NOTHING;
INSERT INTO public.products_services (id, merchant_id, name, category, price_usd, description, image_url, is_active, created_at) VALUES (36, 9, 'Tobito Playero con 6 Cervezas Nacionales Polar', 'Bebidas', 8, 'Hielo frappé y cervezas bien frías servidas directo a tu toldo.', NULL, 1, '2026-09-09 19:58:54') ON CONFLICT (id) DO NOTHING;
INSERT INTO public.products_services (id, merchant_id, name, category, price_usd, description, image_url, is_active, created_at) VALUES (37, 9, 'Silla Reclinable Extra para Acompañante', 'Servicios', 4, 'Silla playera adicional para toda la estadía.', NULL, 1, '2026-09-09 19:58:54') ON CONFLICT (id) DO NOTHING;
INSERT INTO public.products_services (id, merchant_id, name, category, price_usd, description, image_url, is_active, created_at) VALUES (38, 10, 'Protector Solar SPF 50 Resistente al Agua 120ml', 'Salud', 9, 'Fórmula dermatológica con protección UVA/UVB para toda la familia.', NULL, 1, '2026-09-09 19:58:54') ON CONFLICT (id) DO NOTHING;
INSERT INTO public.products_services (id, merchant_id, name, category, price_usd, description, image_url, is_active, created_at) VALUES (39, 10, 'Gel Refrescante de Aloe Vera Post-Solar', 'Salud', 5.5, 'Alivio inmediato para pieles expuestas al sol con efecto calmante.', NULL, 1, '2026-09-09 19:58:54') ON CONFLICT (id) DO NOTHING;
INSERT INTO public.products_services (id, merchant_id, name, category, price_usd, description, image_url, is_active, created_at) VALUES (40, 10, 'Kit Primeros Auxilios Playero', 'Salud', 6, 'Alcohol, gasas, curitas, analgésicos y crema para picaduras.', NULL, 1, '2026-09-09 19:58:54') ON CONFLICT (id) DO NOTHING;
INSERT INTO public.products_services (id, merchant_id, name, category, price_usd, description, image_url, is_active, created_at) VALUES (41, 11, 'Cepillado Tradicional de Tamarindo y Colita', 'Postres', 1.5, 'Hielo raspado artesanal con jarabe de frutas y leche condensada.', NULL, 1, '2026-09-09 19:58:54') ON CONFLICT (id) DO NOTHING;
INSERT INTO public.products_services (id, merchant_id, name, category, price_usd, description, image_url, is_active, created_at) VALUES (42, 11, 'Helado Cremoso de Coco en Vaso Doble', 'Postres', 2.5, 'Elaborado con pulpa fresca de coco del litoral.', NULL, 1, '2026-09-09 19:58:54') ON CONFLICT (id) DO NOTHING;
INSERT INTO public.products_services (id, merchant_id, name, category, price_usd, description, image_url, is_active, created_at) VALUES (43, 11, 'Chicha Criolla con Canela y Leche Condensada', 'Bebidas', 2, 'Espesa, fría y endulzada al gusto.', NULL, 1, '2026-09-09 19:58:54') ON CONFLICT (id) DO NOTHING;
INSERT INTO public.products_services (id, merchant_id, name, category, price_usd, description, image_url, is_active, created_at) VALUES (44, 12, 'Pase de Entrada Balneario Familiar (4 Personas)', 'Acceso', 5, 'Uso de duchas de agua dulce, vestidores y zona protegida.', NULL, 1, '2026-09-09 19:58:54') ON CONFLICT (id) DO NOTHING;
INSERT INTO public.products_services (id, merchant_id, name, category, price_usd, description, image_url, is_active, created_at) VALUES (45, 12, 'Alquiler Flotador Salvavidas para Niños', 'Recreación', 3, 'Chaleco o flotador inflable certificado para nado seguro.', NULL, 1, '2026-09-09 19:58:54') ON CONFLICT (id) DO NOTHING;
INSERT INTO public.products_services (id, merchant_id, name, category, price_usd, description, image_url, is_active, created_at) VALUES (46, 12, 'Guardarropa / Casillero Seguro (Día Completo)', 'Servicios', 2, 'Guarda tus pertenencias con candado y vigilancia.', NULL, 1, '2026-09-09 19:58:54') ON CONFLICT (id) DO NOTHING;
INSERT INTO public.products_services (id, merchant_id, name, category, price_usd, description, image_url, is_active, created_at) VALUES (47, 13, 'Rueda de Carite Frito con Tostones y Tártara', 'Gastronomía', 11, 'Acompañado con ensalada rayada dulce y tostones doraditos.', NULL, 1, '2026-09-09 19:58:54') ON CONFLICT (id) DO NOTHING;
INSERT INTO public.products_services (id, merchant_id, name, category, price_usd, description, image_url, is_active, created_at) VALUES (48, 13, 'Hervido de Pescado Criollo Sabatino', 'Gastronomía', 7.5, 'Sopa caliente reconfortante con verduras de la zona y cilantro.', NULL, 1, '2026-09-09 19:58:54') ON CONFLICT (id) DO NOTHING;
INSERT INTO public.products_services (id, merchant_id, name, category, price_usd, description, image_url, is_active, created_at) VALUES (49, 13, 'Camarones al Ajillo en Cazuela de Barro', 'Gastronomía', 13, 'Salteados en aceite de oliva virgen con ajo dorado y perejil.', NULL, 1, '2026-09-09 19:58:54') ON CONFLICT (id) DO NOTHING;
INSERT INTO public.products_services (id, merchant_id, name, category, price_usd, description, image_url, is_active, created_at) VALUES (50, 14, 'Coco Frío Natural con Pitillo', 'Bebidas', 1.5, 'Agua fresca de coco verde recién abierto con cuchara para la pulpa.', NULL, 1, '2026-09-09 19:58:54') ON CONFLICT (id) DO NOTHING;
INSERT INTO public.products_services (id, merchant_id, name, category, price_usd, description, image_url, is_active, created_at) VALUES (51, 14, 'Paquete de Platanitos Fritos con Salsa Rosada', 'Snacks', 2, 'Plátanos crujientes fritos del día.', NULL, 1, '2026-09-09 19:58:54') ON CONFLICT (id) DO NOTHING;
INSERT INTO public.products_services (id, merchant_id, name, category, price_usd, description, image_url, is_active, created_at) VALUES (52, 14, 'Refresco de Lata 355ml Frío', 'Bebidas', 1.5, 'Coca Cola, Pepsi o Chinotto bien helado.', NULL, 1, '2026-09-09 19:58:54') ON CONFLICT (id) DO NOTHING;
INSERT INTO public.products_services (id, merchant_id, name, category, price_usd, description, image_url, is_active, created_at) VALUES (53, 15, 'Paella Marinera Valenciana Especial (Para 2)', 'Gastronomía', 28, 'Arroz con azafrán, camarones, calamares, mejillones y almejas.', NULL, 1, '2026-09-09 19:58:54') ON CONFLICT (id) DO NOTHING;
INSERT INTO public.products_services (id, merchant_id, name, category, price_usd, description, image_url, is_active, created_at) VALUES (54, 15, 'Asopado de Mariscos 7 Mares', 'Gastronomía', 14, 'Cremoso y rebosante de frutos del mar fresco.', NULL, 1, '2026-09-09 19:58:54') ON CONFLICT (id) DO NOTHING;
INSERT INTO public.products_services (id, merchant_id, name, category, price_usd, description, image_url, is_active, created_at) VALUES (55, 15, 'Copa de Sangría Marina de la Casa', 'Bebidas', 4.5, 'Vino tinto, frutas maceradas y un toque de licor de naranja.', NULL, 1, '2026-09-09 19:58:54') ON CONFLICT (id) DO NOTHING;
INSERT INTO public.products_services (id, merchant_id, name, category, price_usd, description, image_url, is_active, created_at) VALUES (56, 16, 'Suite Familiar con Balcón y Piscina (Noche)', 'Hospedaje', 60, 'Capacidad hasta 4 personas, TV por cable, wifi y estacionamiento.', NULL, 1, '2026-09-09 19:58:54') ON CONFLICT (id) DO NOTHING;
INSERT INTO public.products_services (id, merchant_id, name, category, price_usd, description, image_url, is_active, created_at) VALUES (57, 16, 'Pase de Día a la Piscina del Hotel', 'Recreación', 10, 'Acceso a piscina para adultos y niños con derecho a reposeras.', NULL, 1, '2026-09-09 19:58:54') ON CONFLICT (id) DO NOTHING;
INSERT INTO public.products_services (id, merchant_id, name, category, price_usd, description, image_url, is_active, created_at) VALUES (58, 16, 'Hamburguesa Especial Jofer con Papas', 'Snacks', 6.5, 'Carne a la parrilla, tocineta, queso amarillo y papas fritas.', NULL, 1, '2026-09-09 19:58:54') ON CONFLICT (id) DO NOTHING;
INSERT INTO public.products_services (id, merchant_id, name, category, price_usd, description, image_url, is_active, created_at) VALUES (59, 17, 'Combo Playero: Toldo + 2 Sillas + Cava con Hielo', 'Servicios', 16, 'Todo lo necesario para pasar el día en la arena con sombra garantizada.', NULL, 1, '2026-09-09 19:58:55') ON CONFLICT (id) DO NOTHING;
INSERT INTO public.products_services (id, merchant_id, name, category, price_usd, description, image_url, is_active, created_at) VALUES (60, 17, 'Alquiler de Tabla Bodyboard (1 hora)', 'Deportes', 5, 'Tabla de espuma resistente para deslizarse en las olas suaves.', NULL, 1, '2026-09-09 19:58:55') ON CONFLICT (id) DO NOTHING;
INSERT INTO public.products_services (id, merchant_id, name, category, price_usd, description, image_url, is_active, created_at) VALUES (61, 17, 'Bolsa de Hielo en Cubos 5 Kg', 'Servicios', 2.5, 'Hielo purificado para mantener frías tus bebidas.', NULL, 1, '2026-09-09 19:58:55') ON CONFLICT (id) DO NOTHING;
INSERT INTO public.products_services (id, merchant_id, name, category, price_usd, description, image_url, is_active, created_at) VALUES (62, 18, 'Franela Oficial Algodón "Paseo Macuto La Guaira"', 'Ropa', 10, 'Franela con estampado playero de alta duración en varias tallas.', NULL, 1, '2026-09-09 19:58:55') ON CONFLICT (id) DO NOTHING;
INSERT INTO public.products_services (id, merchant_id, name, category, price_usd, description, image_url, is_active, created_at) VALUES (63, 18, 'Gorra Bordada La Guaira Costa Bonita', 'Souvenirs', 7, 'Gorra trucker ajustable con bordado frontal.', NULL, 1, '2026-09-09 19:58:55') ON CONFLICT (id) DO NOTHING;
INSERT INTO public.products_services (id, merchant_id, name, category, price_usd, description, image_url, is_active, created_at) VALUES (64, 18, 'Artesanía en Madera Flotante Tallada a Mano', 'Artesanías', 12, 'Peces y gaviotas tallados en madera de mar por artesanos locales.', NULL, 1, '2026-09-09 19:58:55') ON CONFLICT (id) DO NOTHING;
INSERT INTO public.products_services (id, merchant_id, name, category, price_usd, description, image_url, is_active, created_at) VALUES (65, 19, 'Clase de Stand Up Paddle (1 hora con Instructor)', 'Deportes', 20, 'Incluye tabla profesional, remo, chaleco salvavidas y fotos acuáticas.', NULL, 1, '2026-09-09 19:58:55') ON CONFLICT (id) DO NOTHING;
INSERT INTO public.products_services (id, merchant_id, name, category, price_usd, description, image_url, is_active, created_at) VALUES (66, 19, 'Alquiler de Kayak Doble (1 hora)', 'Deportes', 18, 'Kayak biplaza insumergible para navegar la bahía.', NULL, 1, '2026-09-09 19:58:55') ON CONFLICT (id) DO NOTHING;
INSERT INTO public.products_services (id, merchant_id, name, category, price_usd, description, image_url, is_active, created_at) VALUES (67, 19, 'Tour Guiado en Paddle al Atardecer (Sunset Paddle)', 'Tours', 25, 'Remada mágica al caer el sol con brindis playero incluido.', NULL, 1, '2026-09-09 19:58:55') ON CONFLICT (id) DO NOTHING;
INSERT INTO public.products_services (id, merchant_id, name, category, price_usd, description, image_url, is_active, created_at) VALUES (68, 20, 'Ceviche Costero El Ceibo con Batata y Maíz', 'Gastronomía', 9, 'Pescado blanco curado con limón criollo, ají dulce y cebolla morada.', NULL, 1, '2026-09-09 19:58:55') ON CONFLICT (id) DO NOTHING;
INSERT INTO public.products_services (id, merchant_id, name, category, price_usd, description, image_url, is_active, created_at) VALUES (69, 20, 'Parrilla Mixta Mar y Tierra El Ceibo', 'Gastronomía', 18, 'Carne de res tierna, pechuga, camarones, calamares y yuca frita.', NULL, 1, '2026-09-09 19:58:55') ON CONFLICT (id) DO NOTHING;
INSERT INTO public.products_services (id, merchant_id, name, category, price_usd, description, image_url, is_active, created_at) VALUES (70, 20, 'Balde de 10 Cervezas Artesanales del Litoral', 'Bebidas', 15, 'Cervezas costeras rubias y rojas heladas servidas bajo el ceibo.', NULL, 1, '2026-09-09 19:58:55') ON CONFLICT (id) DO NOTHING;
SELECT setval('products_services_id_seq', (SELECT COALESCE(MAX(id), 1) FROM public.products_services));
INSERT INTO public.xp_boosters (id, name, multiplier, action_type, is_active, description, created_at) VALUES (1, 'Fin de Semana Playero x2', 2, 'purchase', 1, 'Doble XP en todas las compras realizadas viernes, sábado y domingo', '2026-09-09 14:42:37') ON CONFLICT (id) DO NOTHING;
INSERT INTO public.xp_boosters (id, name, multiplier, action_type, is_active, description, created_at) VALUES (2, 'Ruta Gastronómica x3', 3, 'purchase', 1, 'Triple XP al consumir en restaurantes y pescaderías de Macuto', '2026-09-09 14:42:37') ON CONFLICT (id) DO NOTHING;
INSERT INTO public.xp_boosters (id, name, multiplier, action_type, is_active, description, created_at) VALUES (3, 'Reseña Verificada x1.5', 1.5, 'review', 1, '50% más de XP por dejar tu opinión real con foto o detalles', '2026-09-09 14:42:37') ON CONFLICT (id) DO NOTHING;
INSERT INTO public.xp_boosters (id, name, multiplier, action_type, is_active, description, created_at) VALUES (4, 'Bienvenida Turista 2026', 1.5, 'visit', 1, 'Bonus diario por visitar la plataforma y explorar el mapa', '2026-09-09 14:42:37') ON CONFLICT (id) DO NOTHING;
SELECT setval('xp_boosters_id_seq', (SELECT COALESCE(MAX(id), 1) FROM public.xp_boosters));

-- =========================================================================
-- POLÍTICAS DE LECTURA PÚBLICA EN SUPABASE
-- =========================================================================
ALTER TABLE public.system_settings ENABLE ROW LEVEL SECURITY;
CREATE POLICY "Lectura pública de settings" ON public.system_settings FOR SELECT USING (true);
ALTER TABLE public.merchants ENABLE ROW LEVEL SECURITY;
CREATE POLICY "Lectura pública de comercios" ON public.merchants FOR SELECT USING (true);
ALTER TABLE public.products_services ENABLE ROW LEVEL SECURITY;
CREATE POLICY "Lectura pública de productos" ON public.products_services FOR SELECT USING (true);