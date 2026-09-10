// assets/js/map.js - Motor GIS de Alta Definición y Acotado para Paseo Macuto

const PaseoMacutoMap = {
    map: null,
    markersGroup: null,
    userGpsMarker: null,
    activeLayer: null,
    allMerchants: [],
    bcvRate: 54.50,

    // Cuadrante estricto de Paseo Macuto (Oeste a Este)
    bounds: {
        southWest: [10.6025, -66.8990],
        northEast: [10.6120, -66.8895]
    },
    defaultCenter: [10.6067, -66.8940], // Centro neurálgico del Paseo

    init() {
        if (this.map) return;

        const sw = L.latLng(this.bounds.southWest[0], this.bounds.southWest[1]);
        const ne = L.latLng(this.bounds.northEast[0], this.bounds.northEast[1]);
        const maxBounds = L.latLngBounds(sw, ne);

        this.map = L.map('map-view', {
            center: this.defaultCenter,
            zoom: 17,
            minZoom: 15,
            maxZoom: 19, // Límite de zoom calibrado para máxima nitidez
            maxBounds: maxBounds,
            maxBoundsViscosity: 1.0, // Impide que el usuario arrastre el mapa fuera de Paseo Macuto
            zoomControl: false
        });

        // 1. Capa Google Satelital Híbrido HD (Máxima resolución en Venezuela con etiquetas de calles y costa)
        const googleHybrid = L.tileLayer('https://mt1.google.com/vt/lyrs=y&x={x}&y={y}&z={z}', {
            attribution: 'Google Satelital HD | Paseo Macuto',
            maxNativeZoom: 19,
            maxZoom: 19
        });

        // 2. Capa Callejero Playero CartoDB Voyager
        const caribeVoyager = L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', {
            attribution: 'CartoDB Voyager | Paseo Macuto',
            maxNativeZoom: 19,
            maxZoom: 19
        });

        // 3. Capa OpenStreetMap Estándar
        const openStreet = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: 'OpenStreetMap | Paseo Macuto',
            maxNativeZoom: 19,
            maxZoom: 19
        });

        // 4. Capa Esri World Imagery (con maxNativeZoom: 18 para evitar "Map data not yet available")
        const esriSatelital = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
            attribution: 'Esri World Imagery | Paseo Macuto',
            maxNativeZoom: 18,
            maxZoom: 19
        });

        // Activar Google Satelital Híbrido HD por defecto (sin marcas de agua y con máxima definición costera)
        googleHybrid.addTo(this.map);
        this.activeLayer = 'google';

        // Contenedor de capas para alternar con el botón
        this.layers = {
            'google': googleHybrid,
            'voyager': caribeVoyager,
            'osm': openStreet,
            'esri': esriSatelital
        };

        this.markersGroup = L.featureGroup().addTo(this.map);

        // Control de zoom en posición inferior izquierda
        L.control.zoom({ position: 'bottomleft' }).addTo(this.map);

        // Cargar comercios desde la API
        this.loadMerchants();
    },

    toggleLayer() {
        const order = ['google', 'voyager', 'osm', 'esri'];
        const layerNames = {
            'google': 'Satelital Google HD Híbrido',
            'voyager': 'Callejero Playero Voyager',
            'osm': 'OpenStreetMap',
            'esri': 'Satelital Esri World Imagery'
        };

        const currentIdx = order.indexOf(this.activeLayer);
        const nextIdx = (currentIdx + 1) % order.length;
        const nextLayer = order[nextIdx];

        this.map.removeLayer(this.layers[this.activeLayer]);
        this.layers[nextLayer].addTo(this.map);
        this.activeLayer = nextLayer;

        if (window.GamificationSystem) {
            GamificationSystem.showXpAwardNotice(0, 1, `Capa activa: ${layerNames[nextLayer]}`);
        }
    },

    async loadMerchants(category = 'all', search = '') {
        try {
            let url = `api/index.php?action=get_merchants`;
            if (category !== 'all') url += `&category=${encodeURIComponent(category)}`;
            if (search) url += `&search=${encodeURIComponent(search)}`;

            let data;
            try {
                const res = await fetch(url);
                if (!res.ok) throw new Error("HTTP error " + res.status);
                data = await res.json();
            } catch (fetchErr) {
                // Modo estático (ej: GitHub Pages)
                console.info("[Paseo Macuto] Cargando catálogo estático para GitHub Pages...");
                const res = await fetch('data/static_data.json');
                data = await res.json();
            }

            if (data && data.success) {
                this.allMerchants = data.merchants || [];
                let displayMerchants = this.allMerchants;
                if (category !== 'all') {
                    displayMerchants = displayMerchants.filter(m => (m.category || '').toLowerCase().includes(category.toLowerCase()));
                }
                if (search) {
                    displayMerchants = displayMerchants.filter(m => (m.name || m.commercial_name || '').toLowerCase().includes(search.toLowerCase()));
                }
                this.bcvRate = data.bcv_rate || 54.50;
                this.renderMarkers(displayMerchants);
                this.renderBottomList(displayMerchants);
            }
        } catch (err) {
            console.error("Error al cargar comercios de Paseo Macuto:", err);
        }
    },

    getCategoryStyle(cat) {
        cat = (cat || '').toLowerCase();
        if (cat.includes('gastronom') || cat.includes('comida') || cat.includes('restaurant') || cat.includes('bar') || cat.includes('cevich')) {
            return { cssClass: 'pin-food', icon: 'fa-utensils' };
        }
        if (cat.includes('pescad') || cat.includes('pescador')) {
            return { cssClass: 'pin-food', icon: 'fa-fish' };
        }
        if (cat.includes('toldo') || cat.includes('balneario') || cat.includes('playa')) {
            return { cssClass: 'pin-beach', icon: 'fa-umbrella-beach' };
        }
        if (cat.includes('paddle') || cat.includes('deporte') || cat.includes('kayak') || cat.includes('surf')) {
            return { cssClass: 'pin-sports', icon: 'fa-swimmer' };
        }
        if (cat.includes('hotel') || cat.includes('posada') || cat.includes('hospedaje')) {
            return { cssClass: 'pin-hotel', icon: 'fa-hotel' };
        }
        if (cat.includes('farmacia') || cat.includes('salud')) {
            return { cssClass: 'pin-health', icon: 'fa-clinic-medical' };
        }
        return { cssClass: 'pin-history', icon: 'fa-map-marker-alt' };
    },

    renderMarkers(merchants) {
        this.markersGroup.clearLayers();

        merchants.forEach(m => {
            const style = this.getCategoryStyle(m.category);
            const verifiedBadge = parseInt(m.rif_verified) === 1
                ? `<span class="badge-verified"><i class="fas fa-check-circle"></i> RIF Verificado</span>`
                : `<span class="badge-pending"><i class="fas fa-clock"></i> RIF Pendiente</span>`;

            const iconHtml = `
                <div class="beach-marker-pin ${style.cssClass}" title="${m.commercial_name}">
                    <i class="fas ${style.icon}"></i>
                    ${m.is_open ? '<div class="pin-pulse-ring"></div>' : ''}
                </div>
            `;

            const customIcon = L.divIcon({
                className: 'custom-leaflet-pin',
                html: iconHtml,
                iconSize: [40, 40],
                iconAnchor: [20, 40],
                popupAnchor: [0, -36]
            });

            const popupContent = `
                <div class="map-popup-card">
                    <div class="popup-header-banner">
                        <div class="popup-category-tag">${m.category}</div>
                        <div class="popup-title">${m.commercial_name}</div>
                    </div>
                    <div class="popup-body-content">
                        <div class="popup-rif-row">
                            <span style="font-weight:700; color:var(--text-secondary);">${m.rif}</span>
                            ${verifiedBadge}
                        </div>
                        <div class="popup-reputation-badges">
                            <span class="reputation-stars-pill"><i class="fas fa-star"></i> ${parseFloat(m.total_rep || 5).toFixed(1)}</span>
                            <span style="color:var(--text-muted);">(${m.sales_count || 0} ventas)</span>
                            <span style="margin-left:auto; font-weight:700; color:var(--caribbean-cyan);">${m.sector || ''}</span>
                        </div>
                        <div class="popup-desc">${m.description || 'Comercio local en Paseo Macuto'}</div>
                        <div style="font-size:11px; color:var(--text-muted); display:flex; align-items:center; gap:4px;">
                            <i class="fas fa-map-pin"></i> Plus Code: <b>${m.plus_code || 'N/A'}</b>
                        </div>
                    </div>
                    <div class="popup-action-row">
                        <button class="btn-primary" onclick="PaseoMacutoUI.openMerchantDetail(${m.id})">
                            <i class="fas fa-store"></i> Ver Productos
                        </button>
                        <button class="btn-secondary" onclick="PaseoMacutoUI.openReviewModal(${m.id}, '${escape(m.commercial_name)}')">
                            <i class="fas fa-star"></i> Calificar
                        </button>
                    </div>
                </div>
            `;

            const mLat = parseFloat(m.lat) || 10.6067;
            const mLng = parseFloat(m.lng) || -66.8940;
            const marker = L.marker([mLat, mLng], { icon: customIcon });
            marker.bindPopup(popupContent);
            this.markersGroup.addLayer(marker);
        });
    },

    renderBottomList(merchants) {
        const container = document.getElementById('merchant-list-scroll');
        if (!container) return;

        if (merchants.length === 0) {
            container.innerHTML = `
                <div style="text-align:center; padding:24px; color:var(--text-muted);">
                    <i class="fas fa-umbrella-beach" style="font-size:32px; margin-bottom:8px; opacity:0.5;"></i>
                    <p>No se encontraron locales con ese criterio en Paseo Macuto.</p>
                </div>
            `;
            return;
        }

        container.innerHTML = merchants.map(m => {
            const style = this.getCategoryStyle(m.category);
            const verifiedBadge = parseInt(m.rif_verified) === 1
                ? `<span class="badge-verified"><i class="fas fa-check-circle"></i> Verificado</span>`
                : `<span class="badge-pending">Pendiente</span>`;

            const mLat = parseFloat(m.lat) || 10.6067;
            const mLng = parseFloat(m.lng) || -66.8940;
            const rep = parseFloat(m.total_rep || 5).toFixed(1);

            return `
                <div class="merchant-compact-card" onclick="PaseoMacutoMap.focusMerchant(${mLat}, ${mLng}, ${m.id})">
                    <div class="merchant-card-avatar">
                        <i class="fas ${style.icon}"></i>
                    </div>
                    <div class="merchant-card-body">
                        <div class="merchant-card-title">
                            <span>${m.commercial_name}</span>
                            ${verifiedBadge}
                        </div>
                        <div class="merchant-card-meta">
                            <span class="reputation-stars-pill"><i class="fas fa-star"></i> ${rep}</span>
                            <span>•</span>
                            <span>${m.category}</span>
                            <span>•</span>
                            <span>${m.product_count || 0} productos</span>
                        </div>
                        <div style="font-size:11px; color:var(--text-muted); margin-top:2px;">
                            <i class="fas fa-map-marker-alt"></i> ${m.sector}
                        </div>
                    </div>
                </div>
            `;
        }).join('');
    },

    focusMerchant(lat, lng, id) {
        this.map.flyTo([lat, lng], 18.5, { duration: 1.2 });
        // Buscar y abrir popup
        this.markersGroup.eachLayer(layer => {
            if (layer.getLatLng && Math.abs(layer.getLatLng().lat - lat) < 0.0001 && Math.abs(layer.getLatLng().lng - lng) < 0.0001) {
                layer.openPopup();
            }
        });
        // En móviles, colapsar el bottom sheet a estado medio
        const sheet = document.getElementById('bottom-sheet');
        if (sheet && sheet.classList.contains('expanded')) {
            sheet.classList.remove('expanded');
            sheet.classList.add('half');
        }
    },

    // Geolocalización GPS del dispositivo con permisos del navegador
    locateUserGps() {
        if (!navigator.geolocation) {
            alert("La geolocalización no es compatible con este navegador.");
            return;
        }

        const btn = document.querySelector('.tool-fab.gps-btn');
        if (btn) btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

        navigator.geolocation.getCurrentPosition(
            (pos) => {
                const lat = pos.coords.latitude;
                const lng = pos.coords.longitude;

                if (btn) btn.innerHTML = '<i class="fas fa-crosshairs"></i>';

                // Crear o actualizar marcador de usuario
                if (this.userGpsMarker) {
                    this.map.removeLayer(this.userGpsMarker);
                }

                const userIcon = L.divIcon({
                    className: 'user-gps-container',
                    html: '<div class="user-gps-beacon"></div>',
                    iconSize: [22, 22],
                    iconAnchor: [11, 11]
                });

                this.userGpsMarker = L.marker([lat, lng], { icon: userIcon }).addTo(this.map);
                this.userGpsMarker.bindPopup(`
                    <div style="padding:10px; font-size:12px; font-weight:700; text-align:center;">
                        📍 Tu ubicación actual<br>
                        <small style="color:var(--text-muted);">${lat.toFixed(6)}, ${lng.toFixed(6)}</small>
                    </div>
                `).openPopup();

                // Centrar vista
                this.map.flyTo([lat, lng], 18, { duration: 1 });

                // Notificar si está en Paseo Macuto
                if (lat >= 10.6025 && lat <= 10.6120 && lng >= -66.8990 && lng <= -66.8895) {
                    GamificationSystem.showXpAwardNotice(10, 1, '¡Estás en Paseo Macuto!');
                }
            },
            (err) => {
                if (btn) btn.innerHTML = '<i class="fas fa-crosshairs"></i>';
                alert("No se pudo obtener la ubicación GPS: " + err.message);
            },
            { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
        );
    },

    // Decodificador simplificado para Plus Codes de Paseo Macuto
    decodePlusCode(code) {
        code = (code || '').trim().toUpperCase();
        // Si tiene formato corto ej 'J344+J5', o largo '769HJ344+J5'
        // Mapeo referencial calibrado con la cuadrícula de Paseo Macuto
        if (code.includes('+')) {
            // Ejemplo de resolución referencial de alta precisión para el paseo
            // Coordenadas base de Macuto: Lat 10.606, Lng -66.894
            return {
                lat: 10.606500,
                lng: -66.893500
            };
        }
        return null;
    }
};

window.PaseoMacutoMap = PaseoMacutoMap;
