# 🌴 Paseo Macuto — Guía Turística, Gastronómica y Comercial
### La Guaira • Venezuela 🇻🇪

![Paseo Macuto Banner](https://images.unsplash.com/photo-1507525428034-b723cf961d3e?w=1200&auto=format&fit=crop&q=80)

Plataforma interactiva costera del emblemático **Paseo Macuto** en el litoral central de La Guaira. Integra un mapa GIS de alta definición con geolocalización de kioscos y restaurantes playeros, ambientación marina dinámica (3 modos de iluminación costera y oleaje animado SVG), catálogo gastronómico marino con conversión a tasa oficial BCV, sistema de gamificación por niveles turísticos, carrito de compras con PagoMóvil y base de datos relacional en la nube con **Supabase (PostgreSQL)**.

---

## 🌊 Características Principales

- **🏖️ Ambientación Marina y Modos de Iluminación**:
  - ☀️ **Sol Caribeño**: Paleta turquesa luminosa y arena dorada.
  - 🌅 **Atardecer Dorado (Golden Hour)**: Tonos coral, ámbar y reflejos crepusculares en el agua.
  - 🌙 **Noche Marina Tropical**: Azul profundo del litoral con bioluminiscencia cian.
  - 🌊 **Oleaje Dinámico SVG**: Banner de olas animadas multicapa con espumas y movimiento continuo.
- **🛰️ Mapa GIS Acotado en Tiempo Real**:
  - Geolocalización de los **20 kioscos y balnearios** de Macuto con Plus Codes oficiales y coordenadas GPS.
  - Filtros interactivos por especialidad: *Gastronomía Marina, Pescaderías (CONPPA), Toldos y Balnearios, Deportes Acuáticos (Paddle & Kayak), Hoteles y Posadas*.
- **📊 Telemetría Costera y Mareas**:
  - Monitoreo en vivo de temperatura del agua (27.4°C), temperatura ambiente (30.5°C), condición de la marea (*Pleamar / Bajamar*) y vientos alisios.
- **💵 Conversión Oficial BCV en Tiempo Real**:
  - Precios de platos playeros (pescado frito, tostones, empanadas de cazón, cócteles tropicales) mostrados simultáneamente en USD y Bolívares (VES).
- **🛒 Carrito de Compras & PagoMóvil**:
  - Generación de comprobante y panel de verificación de pagos para comerciantes con soporte de 3 solapas.
- **🏆 Gamificación del Turista Costero**:
  - Sistema de 10 niveles exponenciales (desde *Novato Playero* hasta *Leyenda del Paseo Macuto*) con acumulación de XP por visitas, compras y reseñas.
- **☁️ Base de Datos en la Nube con Supabase**:
  - Esquema relacional en PostgreSQL alojado en Supabase Cloud con soporte para conexión directa y Transaction Pooling.

---

## 🛠️ Tecnologías Utilizadas

- **Frontend**: HTML5 Semántico, Vanilla CSS (Sistema de variables y tokens de diseño playero), JavaScript ES6+.
- **Cartografía GIS**: Leaflet.js 1.9.4 & OpenStreetMap.
- **Backend**:
  - **PHP 8.2+**: Arquitectura MVC ligera con API RESTful integrada.
  - **Python 3.10+ con Flask**: Servidor web alternativo con plantillas Jinja2 y soporte para `psycopg2`.
- **Base de Datos**:
  - **Supabase Cloud (PostgreSQL)**: Base de datos primaria en la nube.
  - **SQLite / MySQL**: Fallbacks automáticos locales.

---

## 🚀 Puesta en Marcha Local

### Opción A: Entorno PHP / XAMPP
1. Coloca el proyecto en tu directorio web (ej: `c:/xampp/htdocs/Paseo Macuto`).
2. Inicia Apache en el panel de control de XAMPP.
3. Abre en tu navegador:
   ```
   http://localhost/Paseo%20Macuto/index.php
   ```

### Opción B: Entorno Python con Flask
1. Instala las dependencias:
   ```powershell
   pip install -r requirements.txt
   ```
2. Inicia el servidor:
   ```powershell
   python app.py
   # o haz doble clic en run_flask.bat
   ```
3. Abre en tu navegador:
   ```
   http://127.0.0.1:5000/
   ```

---

## 👥 Cuentas Demo para Pruebas

- **👑 Superadministrador**: `lams210488@gmail.com` / `admin123`
- **🏪 Comerciante / Vendedor**: `gaviota@paseomacuto.com` / `comercio123`
- **🏖️ Visitante / Turista**: `turista@paseomacuto.com` / `turista123`

---

## 👨‍💻 Autor y Créditos

- **Creador**: Leonardo Morales — **Cluster LG**
- **Ubicación**: Paseo Macuto, Estado La Guaira, Venezuela 🇻🇪
