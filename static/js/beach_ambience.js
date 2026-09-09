/**
 * Paseo Macuto - Gestor de Ambientación Playera y Experiencia Costera
 * Manejo de Modos de Iluminación (Sol Caribeño, Atardecer, Noche Marina)
 * Sincronización de Mareas y Condiciones de La Guaira
 */

const BeachAmbience = {
    currentMode: 'caribbean-day',

    init() {
        // Cargar modo preferido guardado o aplicar Sol Caribeño por defecto
        const savedMode = localStorage.getItem('paseo_macuto_ambience') || 'caribbean-day';
        this.setAmbience(savedMode);

        // Vincular eventos en botones de ambientación
        document.querySelectorAll('.ambience-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const targetMode = btn.getAttribute('data-mode');
                if (targetMode) {
                    this.setAmbience(targetMode);
                }
            });
        });

        // Configurar filtros de categorías en el directorio
        this.initCategoryFilters();

        // Actualizar datos del clima playero periódicamente
        this.updateLiveBeachConditions();
        setInterval(() => this.updateLiveBeachConditions(), 60000); // Cada 60 segundos
    },

    setAmbience(mode) {
        this.currentMode = mode;
        document.documentElement.setAttribute('data-ambience', mode);
        localStorage.setItem('paseo_macuto_ambience', mode);

        // Actualizar estado visual de los botones
        document.querySelectorAll('.ambience-btn').forEach(btn => {
            if (btn.getAttribute('data-mode') === mode) {
                btn.classList.add('active');
            } else {
                btn.classList.remove('active');
            }
        });

        console.log(`[Paseo Macuto] Ambientación playera cambiada a: ${mode}`);
    },

    initCategoryFilters() {
        const chips = document.querySelectorAll('.filter-chip');
        const cards = document.querySelectorAll('.beach-card');

        chips.forEach(chip => {
            chip.addEventListener('click', () => {
                chips.forEach(c => c.classList.remove('active'));
                chip.classList.add('active');

                const category = chip.getAttribute('data-category');

                cards.forEach(card => {
                    const cardCategory = card.getAttribute('data-category');
                    if (category === 'all' || cardCategory === category) {
                        card.style.display = 'flex';
                    } else {
                        card.style.display = 'none';
                    }
                });
            });
        });
    },

    async updateLiveBeachConditions() {
        try {
            const response = await fetch('/api/clima-playero');
            if (!response.ok) return;
            const data = await response.json();

            if (data.success) {
                const airElem = document.getElementById('weather-air-temp');
                const waterElem = document.getElementById('weather-water-temp');
                const tideElem = document.getElementById('weather-tide-val');
                const swellElem = document.getElementById('weather-swell-val');

                if (airElem) airElem.textContent = data.air_temperature;
                if (waterElem) waterElem.textContent = data.water_temperature;
                if (tideElem) tideElem.textContent = data.tide;
                if (swellElem) swellElem.textContent = data.swell_height.split(' ')[0] + ' m';
            }
        } catch (err) {
            console.warn('[Paseo Macuto] No fue posible refrescar telemetría costera en vivo:', err);
        }
    }
};

// Inicializar al cargar el DOM
document.addEventListener('DOMContentLoaded', () => {
    BeachAmbience.init();
});
