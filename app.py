# -*- coding: utf-8 -*-
"""
Paseo Macuto - Servidor y Plantilla Web en Python con Flask
Plataforma Turística, Gastronómica y Comercial del Litoral Central (La Guaira, Venezuela)
Ambientación Costera, Oleaje Dinámico y Modos de Iluminación Playera
"""

import os
import sqlite3
import datetime
from flask import Flask, render_template, request, jsonify, g, abort

# Configuración inicial de la aplicación Flask
BASE_DIR = os.path.abspath(os.path.dirname(__file__))
SQLITE_DB_PATH = os.path.join(BASE_DIR, 'data', 'paseo_macuto.sqlite')

app = Flask(
    __name__,
    template_folder=os.path.join(BASE_DIR, 'templates'),
    static_folder=os.path.join(BASE_DIR, 'static')
)
app.config['SECRET_KEY'] = 'paseo_macuto_caribe_secret_key_2026'

# -------------------------------------------------------------
# GESTIÓN DE BASE DE DATOS SQLITE / MYSQL
# -------------------------------------------------------------
def get_db():
    """Obtiene o reutiliza la conexión a la base de datos para la petición actual."""
    if 'db' not in g:
        g.db = sqlite3.connect(SQLITE_DB_PATH)
        g.db.row_factory = sqlite3.Row
    return g.db

@app.teardown_appcontext
def close_db(error):
    """Cierra la conexión al finalizar el ciclo de vida de la petición."""
    db = g.pop('db', None)
    if db is not None:
        db.close()

# -------------------------------------------------------------
# CONTEXT PROCESSORS (DATOS GLOBALES PARA JINJA2)
# -------------------------------------------------------------
@app.context_processor
def inject_global_data():
    """Inyecta datos globales en todas las plantillas (BCV, clima costero, año)."""
    bcv_rate = 54.50
    try:
        db = get_db()
        cursor = db.cursor()
        cursor.execute("SELECT setting_value FROM system_settings WHERE setting_key = 'bcv_rate'")
        row = cursor.fetchone()
        if row and row['setting_value']:
            bcv_rate = float(row['setting_value'])
    except Exception:
        pass

    return {
        'current_year': datetime.datetime.now().year,
        'bcv_rate': bcv_rate,
        'bcv_rate_formatted': f"{bcv_rate:,.2f}".replace('.', ','),
        'location_name': 'Paseo Macuto, La Guaira',
        'sea_temp': '27.4°C',
        'air_temp': '30.5°C'
    }

@app.template_filter('beach_image')
def beach_image_filter(category):
    """Asigna imágenes costeras de alta calidad según la especialidad playera."""
    cat = str(category or '').lower()
    if any(k in cat for k in ['pesca', 'maris', 'gastro', 'comida', 'pataru']):
        return 'https://images.unsplash.com/photo-1534422298391-e4f8c172dddb?w=600&auto=format&fit=crop&q=80'
    elif any(k in cat for k in ['coctel', 'bebida', 'snack', 'chiringuito']):
        return 'https://images.unsplash.com/photo-1514362545857-3bc16c4c7d1b?w=600&auto=format&fit=crop&q=80'
    elif any(k in cat for k in ['toldo', 'playa', 'balneario']):
        return 'https://images.unsplash.com/photo-1507525428034-b723cf961d3e?w=600&auto=format&fit=crop&q=80'
    elif any(k in cat for k in ['deporte', 'surf', 'kayak', 'acuátic']):
        return 'https://images.unsplash.com/photo-1502680390469-be75c86b636f?w=600&auto=format&fit=crop&q=80'
    elif any(k in cat for k in ['hotel', 'hospedaje', 'posada']):
        return 'https://images.unsplash.com/photo-1566073771259-6a8506099945?w=600&auto=format&fit=crop&q=80'
    else:
        return 'https://images.unsplash.com/photo-1519046904884-53103b34b206?w=600&auto=format&fit=crop&q=80'


# -------------------------------------------------------------
# RUTAS DE LA INTERFAZ WEB (PLANTILLAS JINJA2)
# -------------------------------------------------------------
@app.route('/')
def index():
    """Página principal del Paseo Macuto con ambientación playera e interactividad."""
    db = get_db()
    cursor = db.cursor()

    # Obtener lista de comercios y kioscos playeros
    try:
        cursor.execute("""
            SELECT id, commercial_name AS name, category, description, phone, sector AS address, 
                   lat AS latitude, lng AS longitude, total_rep AS rating,
                   is_open
            FROM merchants 
            ORDER BY total_rep DESC, commercial_name ASC
        """)
        merchants = cursor.fetchall()
    except Exception as e:
        print("Error obteniendo comercios:", e)
        merchants = []

    # Extraer categorías únicas para el filtro de la playa
    categories = sorted(list(set([m['category'] for m in merchants if m['category']])))
    if not categories:
        categories = ['Gastronomía y Bebidas', 'Pescadería y Marisquería', 'Toldos y Servicios Playeros', 'Deportes Acuáticos', 'Hospedaje']

    # Niveles de gamificación del turista playero
    tourist_levels = [
        {'level': 1, 'name': 'Novato Playero', 'badge': 'fa-umbrella-beach', 'color': '#00B4D8'},
        {'level': 2, 'name': 'Caminante de Macuto', 'badge': 'fa-shoe-prints', 'color': '#48CAE4'},
        {'level': 3, 'name': 'Explorador Costero', 'badge': 'fa-compass', 'color': '#06D6A0'},
        {'level': 4, 'name': 'Amigo de los Pescadores', 'badge': 'fa-fish', 'color': '#118AB2'},
        {'level': 5, 'name': 'Guía de la Bahía', 'badge': 'fa-map-marked-alt', 'color': '#FFD166'},
        {'level': 6, 'name': 'Capitán de Paseo', 'badge': 'fa-ship', 'color': '#F4A261'},
        {'level': 7, 'name': 'Conocedor del Litoral', 'badge': 'fa-sun', 'color': '#E76F51'},
        {'level': 8, 'name': 'Embajador de La Guaira', 'badge': 'fa-award', 'color': '#E63946'},
        {'level': 9, 'name': 'Protector del Malecón', 'badge': 'fa-shield-alt', 'color': '#7209B7'},
        {'level': 10, 'name': 'Leyenda del Paseo Macuto', 'badge': 'fa-crown', 'color': '#F72585'}
    ]

    return render_template(
        'index.html',
        merchants=merchants,
        categories=categories,
        tourist_levels=tourist_levels,
        total_merchants=len(merchants)
    )

@app.route('/comercio/<int:merchant_id>')
def comercio_detail(merchant_id):
    """Vista detallada de un comercio, kiosco o restaurante con catálogo de productos."""
    db = get_db()
    cursor = db.cursor()

    # Buscar información del comercio
    cursor.execute("""
        SELECT id, commercial_name AS name, category, description, phone, sector AS address,
               lat AS latitude, lng AS longitude, total_rep AS rating
        FROM merchants WHERE id = ?
    """, (merchant_id,))
    merchant = cursor.fetchone()
    if not merchant:
        abort(404, description="Comercio costero no encontrado")

    # Obtener productos o servicios del comercio
    try:
        cursor.execute("""
            SELECT id, name, description, price_usd, category, image_url, is_active 
            FROM products_services 
            WHERE merchant_id = ? AND is_active = 1
            ORDER BY price_usd ASC
        """, (merchant_id,))
        products = cursor.fetchall()
    except Exception as e:
        print("Error obteniendo productos:", e)
        products = []

    return render_template('comercio.html', merchant=merchant, products=products)

# -------------------------------------------------------------
# RUTAS DE API RESTful
# -------------------------------------------------------------
@app.route('/api/clima-playero')
def api_clima_playero():
    """Datos en vivo de las condiciones marinas y meteorológicas de Macuto."""
    now = datetime.datetime.now()
    # Simulación de ciclo de marea según la hora del día en el mar Caribe
    tide_status = "Pleamar (Marea Alta)" if (now.hour % 12) < 6 else "Bajamar (Marea Baja)"
    
    return jsonify({
        'success': True,
        'location': 'Macuto, La Guaira, Mar Caribe',
        'air_temperature': '30.5°C',
        'water_temperature': '27.4°C',
        'swell_height': '0.9 metros (Oleaje moderado apto para baño)',
        'tide': tide_status,
        'tide_coefficient': '78%',
        'wind_speed': '19 km/h (Vientos Alisios del Este)',
        'uv_index': '9 (Muy Alto - Usar protector solar)',
        'water_clarity': 'Alta (Excelente visibilidad costera)',
        'updated_at': now.strftime('%I:%M %p')
    })

@app.route('/api/comercios')
def api_comercios():
    """Retorna listado de comercios en formato JSON para el mapa y filtros."""
    db = get_db()
    cursor = db.cursor()
    cursor.execute("""
        SELECT id, commercial_name AS name, category, description, phone, sector AS address, 
               lat AS latitude, lng AS longitude, total_rep AS rating
        FROM merchants
    """)
    rows = cursor.fetchall()
    data = [dict(row) for row in rows]
    return jsonify({'success': True, 'count': len(data), 'merchants': data})

@app.route('/api/bcv')
def api_bcv():
    """Consulta la tasa oficial del Banco Central de Venezuela."""
    db = get_db()
    cursor = db.cursor()
    cursor.execute("SELECT setting_value FROM system_settings WHERE setting_key = 'bcv_rate'")
    row = cursor.fetchone()
    rate = float(row['setting_value']) if (row and row['setting_value']) else 54.50
    return jsonify({'success': True, 'rate': rate, 'currency': 'VES/USD'})

# -------------------------------------------------------------
# RUTAS DE ACTIVOS ESTÁTICOS Y PROXY DE API COMPLETA
# -------------------------------------------------------------
@app.route('/assets/<path:path>')
def serve_assets(path):
    """Sirve hojas de estilo, scripts e imágenes de la plataforma."""
    from flask import send_from_directory
    return send_from_directory(os.path.join(BASE_DIR, 'assets'), path)

@app.route('/api/index.php', methods=['GET', 'POST', 'OPTIONS'])
def proxy_php_api():
    """Conecta con la API de Paseo Macuto para soportar inicio de sesión, usuarios y módulos."""
    import urllib.request
    from flask import Response
    try:
        qs = request.query_string.decode('utf-8')
        target_url = f"http://localhost/Paseo%20Macuto/api/index.php?{qs}" if qs else "http://localhost/Paseo%20Macuto/api/index.php"
        headers = {}
        if request.content_type:
            headers['Content-Type'] = request.content_type
        if 'Cookie' in request.headers:
            headers['Cookie'] = request.headers['Cookie']
            
        data = request.get_data() if request.method == 'POST' else None
        req = urllib.request.Request(target_url, data=data, headers=headers, method=request.method)
        with urllib.request.urlopen(req) as resp:
            content = resp.read()
            flask_resp = Response(content, status=resp.status, mimetype=resp.headers.get_content_type())
            set_cookie = resp.headers.get('Set-Cookie')
            if set_cookie:
                flask_resp.headers['Set-Cookie'] = set_cookie
            return flask_resp
    except Exception as err:
        return jsonify({'success': False, 'message': f'Error en API: {str(err)}'})


# -------------------------------------------------------------
# MANEJO DE ERRORES
# -------------------------------------------------------------
@app.errorhandler(404)
def page_not_found(e):
    return render_template('base.html', not_found=True), 404

# -------------------------------------------------------------
# PUNTO DE ENTRADA
# -------------------------------------------------------------
if __name__ == '__main__':
    print("[Paseo Macuto] Iniciando servidor Flask (Ambientacion Playera)...")
    print("[Paseo Macuto] Servidor disponible en: http://127.0.0.1:5000")
    app.run(host='0.0.0.0', port=5000, debug=False)

