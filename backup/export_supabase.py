# -*- coding: utf-8 -*-
"""
Exportador de esquema y datos semilla de Paseo Macuto para Supabase (PostgreSQL)
"""
import sqlite3
import os

BASE_DIR = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
DB_PATH = os.path.join(BASE_DIR, 'data', 'paseo_macuto.sqlite')
SCHEMA_PATH = os.path.join(BASE_DIR, 'backup', 'schema_supabase.sql')
OUTPUT_PATH = os.path.join(BASE_DIR, 'backup', 'supabase_full_migration.sql')

conn = sqlite3.connect(DB_PATH)
conn.row_factory = sqlite3.Row
cur = conn.cursor()

with open(SCHEMA_PATH, 'r', encoding='utf-8') as f:
    schema = f.read()

sql_out = []
sql_out.append('-- =========================================================================')
sql_out.append('-- MIGRACION COMPLETA A SUPABASE (ESQUEMA + DATOS) - PASEO MACUTO')
sql_out.append('-- =========================================================================\n')
sql_out.append(schema)
sql_out.append('\n-- =========================================================================')
sql_out.append('-- INSERCION DE DATOS SEMILLA EXISTENTES')
sql_out.append('-- =========================================================================\n')

def escape_sql(val):
    if val is None:
        return 'NULL'
    if isinstance(val, (int, float)):
        return str(val)
    s = str(val).replace("'", "''")
    return f"'{s}'"

# 1. system_settings
cur.execute('SELECT setting_key, setting_value, updated_by, updated_at FROM system_settings')
for r in cur.fetchall():
    sql_out.append(f"INSERT INTO public.system_settings (setting_key, setting_value, updated_by, updated_at) VALUES ({escape_sql(r['setting_key'])}, {escape_sql(r['setting_value'])}, {escape_sql(r['updated_by'])}, {escape_sql(r['updated_at'])}) ON CONFLICT (setting_key) DO UPDATE SET setting_value = EXCLUDED.setting_value;")

# 2. users
cur.execute('SELECT id, name, email, password_hash, role, level, xp, phone, status, created_at FROM users ORDER BY id ASC')
for r in cur.fetchall():
    sql_out.append(f"INSERT INTO public.users (id, name, email, password_hash, role, level, xp, phone, status, created_at) VALUES ({r['id']}, {escape_sql(r['name'])}, {escape_sql(r['email'])}, {escape_sql(r['password_hash'])}, {escape_sql(r['role'])}, {r['level']}, {r['xp']}, {escape_sql(r['phone'])}, {escape_sql(r['status'])}, {escape_sql(r['created_at'])}) ON CONFLICT (id) DO NOTHING;")
sql_out.append("SELECT setval('users_id_seq', (SELECT COALESCE(MAX(id), 1) FROM public.users));")

# 3. merchants
cur.execute('SELECT * FROM merchants ORDER BY id ASC')
for r in cur.fetchall():
    sql_out.append(f"INSERT INTO public.merchants (id, user_id, commercial_name, rif, rif_verified, category, sector, lat, lng, plus_code, phone, description, sales_count, sales_rep, service_rep, total_rep, is_open, created_at) VALUES ({r['id']}, {escape_sql(r['user_id'])}, {escape_sql(r['commercial_name'])}, {escape_sql(r['rif'])}, {r['rif_verified'] or 0}, {escape_sql(r['category'])}, {escape_sql(r['sector'])}, {r['lat']}, {r['lng']}, {escape_sql(r['plus_code'])}, {escape_sql(r['phone'])}, {escape_sql(r['description'])}, {r['sales_count'] or 0}, {r['sales_rep'] or 5.0}, {r['service_rep'] or 5.0}, {r['total_rep'] or 5.0}, {r['is_open'] or 1}, {escape_sql(r['created_at'])}) ON CONFLICT (id) DO NOTHING;")
sql_out.append("SELECT setval('merchants_id_seq', (SELECT COALESCE(MAX(id), 1) FROM public.merchants));")

# 4. products_services
cur.execute('SELECT id, merchant_id, name, category, price_usd, description, image_url, is_active, created_at FROM products_services ORDER BY id ASC')
for r in cur.fetchall():
    sql_out.append(f"INSERT INTO public.products_services (id, merchant_id, name, category, price_usd, description, image_url, is_active, created_at) VALUES ({r['id']}, {r['merchant_id']}, {escape_sql(r['name'])}, {escape_sql(r['category'])}, {r['price_usd']}, {escape_sql(r['description'])}, {escape_sql(r['image_url'])}, {r['is_active']}, {escape_sql(r['created_at'])}) ON CONFLICT (id) DO NOTHING;")
sql_out.append("SELECT setval('products_services_id_seq', (SELECT COALESCE(MAX(id), 1) FROM public.products_services));")

# 5. xp_boosters
cur.execute('SELECT id, name, multiplier, action_type, is_active, description, created_at FROM xp_boosters ORDER BY id ASC')
for r in cur.fetchall():
    sql_out.append(f"INSERT INTO public.xp_boosters (id, name, multiplier, action_type, is_active, description, created_at) VALUES ({r['id']}, {escape_sql(r['name'])}, {r['multiplier']}, {escape_sql(r['action_type'])}, {r['is_active']}, {escape_sql(r['description'])}, {escape_sql(r['created_at'])}) ON CONFLICT (id) DO NOTHING;")
sql_out.append("SELECT setval('xp_boosters_id_seq', (SELECT COALESCE(MAX(id), 1) FROM public.xp_boosters));")

# 6. RLS (Row Level Security) opcional en Supabase
sql_out.append('\n-- =========================================================================')
sql_out.append('-- POLÍTICAS DE LECTURA PÚBLICA EN SUPABASE')
sql_out.append('-- =========================================================================')
sql_out.append('ALTER TABLE public.system_settings ENABLE ROW LEVEL SECURITY;')
sql_out.append('CREATE POLICY "Lectura pública de settings" ON public.system_settings FOR SELECT USING (true);')
sql_out.append('ALTER TABLE public.merchants ENABLE ROW LEVEL SECURITY;')
sql_out.append('CREATE POLICY "Lectura pública de comercios" ON public.merchants FOR SELECT USING (true);')
sql_out.append('ALTER TABLE public.products_services ENABLE ROW LEVEL SECURITY;')
sql_out.append('CREATE POLICY "Lectura pública de productos" ON public.products_services FOR SELECT USING (true);')

with open(OUTPUT_PATH, 'w', encoding='utf-8') as f:
    f.write('\n'.join(sql_out))

print(f"Migracion generada con exito en: {OUTPUT_PATH}")
