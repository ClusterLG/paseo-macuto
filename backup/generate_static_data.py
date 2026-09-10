import sqlite3
import json

conn = sqlite3.connect('data/paseo_macuto.sqlite')
conn.row_factory = sqlite3.Row
c = conn.cursor()

c.execute('SELECT * FROM merchants ORDER BY total_rep DESC, commercial_name ASC')
merchants = [dict(r) for r in c.fetchall()]

c.execute('SELECT * FROM products_services')
products = [dict(r) for r in c.fetchall()]

# Associate products with merchants
for m in merchants:
    m['products'] = [p for p in products if p.get('merchant_id') == m['id']]

c.execute("SELECT setting_value FROM system_settings WHERE setting_key = 'bcv_rate'")
row = c.fetchone()
bcv = float(row['setting_value']) if row else 54.50

full_data = {
    'success': True,
    'bcv_rate': bcv,
    'merchants': merchants,
    'total_merchants': len(merchants),
    'total_products': len(products)
}

with open('data/static_data.json', 'w', encoding='utf-8') as f:
    json.dump(full_data, f, ensure_ascii=False, indent=2)

print(f"Generated data/static_data.json with {len(merchants)} merchants and {len(products)} products.")
