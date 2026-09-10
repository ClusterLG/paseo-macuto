// assets/js/main.js - Lógica Principal, Controladores de Interfaz y Estados

const PaseoMacutoUI = {
    currentUser: null,
    bcvRate: 54.50,
    currentTheme: 'light',

    async init() {
        this.initTheme();
        this.initAmbience();
        await this.loadSettings();
        await this.checkSession();
        this.initEventListeners();
        PaseoMacutoMap.init();
        this.updateBeachTelemetry();
    },

    // -------------------------------------------------------------
    // GESTIÓN DE AMBIENTACIÓN PLAYERA (SOL CARIBEÑO, ATARDECER, NOCHE MARINA)
    // -------------------------------------------------------------
    initAmbience() {
        const saved = localStorage.getItem('pm_ambience') || 'caribbean-day';
        this.setAmbience(saved);
    },

    setAmbience(mode) {
        this.currentAmbience = mode;
        document.documentElement.setAttribute('data-ambience', mode);
        localStorage.setItem('pm_ambience', mode);

        if (mode === 'tropical-night') {
            document.documentElement.setAttribute('data-theme', 'dark');
        } else {
            document.documentElement.setAttribute('data-theme', 'light');
        }

        document.querySelectorAll('.ambience-option-btn').forEach(btn => {
            if (btn.getAttribute('data-mode') === mode) {
                btn.classList.add('active');
            } else {
                btn.classList.remove('active');
            }
        });
    },

    updateBeachTelemetry() {
        const hour = new Date().getHours();
        const tideText = (hour % 12) < 6 ? 'Pleamar (Alta)' : 'Bajamar (Baja)';
        const tideEl = document.getElementById('header-tide-status');
        if (tideEl) tideEl.textContent = tideText;
    },

    // -------------------------------------------------------------
    // GESTIÓN DE TEMA CLARO / OSCURO
    // -------------------------------------------------------------
    initTheme() {
        const saved = localStorage.getItem('pm_theme') || 'light';
        this.setTheme(saved);
    },

    setTheme(theme) {
        this.currentTheme = theme;
        document.documentElement.setAttribute('data-theme', theme);
        localStorage.setItem('pm_theme', theme);
        const icon = document.getElementById('theme-toggle-icon');
        if (icon) {
            icon.className = theme === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
        }
    },

    toggleTheme() {
        const nextTheme = this.currentTheme === 'dark' ? 'light' : 'dark';
        this.setTheme(nextTheme);
    },

    // -------------------------------------------------------------
    // TASA BCV OFICIAL
    // -------------------------------------------------------------
    async loadSettings() {
        try {
            const res = await fetch('api/index.php?action=get_settings');
            if (!res.ok) throw new Error("HTTP " + res.status);
            const data = await res.json();
            if (data.success) {
                this.bcvRate = data.bcv_rate || 54.50;
                this.updateBcvWidget(this.bcvRate, data.bcv_updated_at);
            }
        } catch (err) {
            this.bcvRate = 54.50;
            this.updateBcvWidget(54.50, 'Oficial');
        }
    },

    updateBcvWidget(rate, updatedAt) {
        const rateEl = document.getElementById('bcv-rate-value');
        if (rateEl) {
            rateEl.innerHTML = `${rate.toFixed(2).replace('.', ',')} <small>Bs/$</small>`;
        }
    },

    openBcvModal() {
        if (!this.currentUser || this.currentUser.role !== 'superadmin') {
            alert(`Tasa Oficial BCV activa: ${this.bcvRate.toFixed(2)} Bs./USD.\nSolo el Superusuario puede modificar este valor.`);
            return;
        }
        document.getElementById('bcv-input-rate').value = this.bcvRate;
        this.openModal('modal-bcv-edit');
    },

    async saveBcvRate(e) {
        e.preventDefault();
        const newRate = parseFloat(document.getElementById('bcv-input-rate').value);
        if (!newRate || newRate <= 0) {
            alert("Ingrese un valor válido");
            return;
        }

        try {
            const res = await fetch('api/index.php?action=update_bcv', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ bcv_rate: newRate })
            });
            const data = await res.json();
            if (data.success) {
                this.bcvRate = newRate;
                this.updateBcvWidget(newRate, data.updated_at);
                this.closeModal('modal-bcv-edit');
                GamificationSystem.showXpAwardNotice(0, 1, `Tasa BCV actualizada a ${newRate.toFixed(2)} Bs.`);
                PaseoMacutoMap.loadMerchants();
            } else {
                alert(data.message);
            }
        } catch (err) {
            alert("Error al actualizar tasa BCV");
        }
    },

    // -------------------------------------------------------------
    // SESIÓN Y AUTENTICACIÓN
    // -------------------------------------------------------------
    async checkSession() {
        try {
            const res = await fetch('api/index.php?action=current_user');
            if (!res.ok) throw new Error("HTTP " + res.status);
            const data = await res.json();
            if (data.success) {
                this.currentUser = data.user;
                this.renderUserInterface();
                return;
            }
        } catch (err) {
            // Modo demostración en GitHub Pages
            const demoUser = localStorage.getItem('pm_demo_user');
            if (demoUser) {
                try {
                    this.currentUser = JSON.parse(demoUser);
                    this.renderUserInterface();
                    return;
                } catch (e) {}
            }
        }
        this.currentUser = null;
        this.renderGuestInterface();
    },

    renderUserInterface() {
        const userBtn = document.getElementById('btn-user-profile');
        if (userBtn) {
            let roleBadge = '';
            if (this.currentUser.role === 'superadmin') roleBadge = '⭐ Admin';
            else if (this.currentUser.role === 'merchant') roleBadge = '🏪 Local';
            else roleBadge = `Lv.${this.currentUser.level_data ? this.currentUser.level_data.level : 1}`;

            userBtn.innerHTML = `
                <i class="fas fa-user-circle"></i> 
                <span style="font-size:12px; font-weight:700;">${this.currentUser.name ? this.currentUser.name.split(' ')[0] : 'Mi Cuenta'}</span>
                <span style="font-size:10px; background:rgba(0,245,212,0.15); color:var(--caribbean-cyan); padding:2px 6px; border-radius:10px; font-weight:800;">${roleBadge}</span>
            `;
            userBtn.onclick = () => this.openUserProfileModal();
        }

        // Actualizar datos en barra lateral si existen
        this.updateSidebarUserInfo();
    },

    renderGuestInterface() {
        const userBtn = document.getElementById('btn-user-profile');
        if (userBtn) {
            userBtn.innerHTML = `<i class="fas fa-sign-in-alt"></i> <span style="font-size:12px; font-weight:700;">Acceder</span>`;
            userBtn.onclick = () => this.openModal('modal-auth');
        }
        this.updateSidebarUserInfo();
    },

    openRoleDashboard() {
        if (!this.currentUser) return;
        this.openUserProfileAndHistory();
    },

    async handleLogin(e) {
        e.preventDefault();
        const email = document.getElementById('login-email').value;
        const password = document.getElementById('login-password').value;

        try {
            const res = await fetch('api/index.php?action=login', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ email, password })
            });
            if (!res.ok) throw new Error("HTTP " + res.status);
            const data = await res.json();
            if (data.success) {
                this.currentUser = data.user;
                this.renderUserInterface();
                this.closeModal('modal-auth');
                GamificationSystem.showXpAwardNotice(5, 1, '¡Bono diario por visita!');
            } else {
                alert(data.message);
            }
        } catch (err) {
            // Modo Demostración en vivo en GitHub Pages
            if (email.includes('admin') || password.includes('admin')) {
                this.currentUser = { id: 1, name: 'Leonardo Morales (Admin)', email: email, role: 'superadmin', level: 10, xp: 5000 };
            } else if (email.includes('kiosco') || email.includes('comercio') || email.includes('pataruko')) {
                this.currentUser = { id: 2, name: 'Kiosco El Pataruko', email: email, role: 'merchant', level: 5, xp: 1200 };
            } else {
                this.currentUser = { id: 99, name: email.split('@')[0] || 'Turista Playero', email: email, role: 'visitor', level: 2, xp: 150 };
            }
            localStorage.setItem('pm_demo_user', JSON.stringify(this.currentUser));
            this.renderUserInterface();
            this.closeModal('modal-auth');
            GamificationSystem.showXpAwardNotice(15, 1, '¡Sesión activa en modo demostración GitHub Pages!');
        }
    },

    async handleRegister(e) {
        e.preventDefault();
        const role = document.getElementById('reg-role').value;
        const name = document.getElementById('reg-name').value;
        const email = document.getElementById('reg-email').value;
        const phone = document.getElementById('reg-phone').value;
        const password = document.getElementById('reg-password').value;

        const payload = { role, name, email, phone, password };

        if (role === 'merchant') {
            payload.commercial_name = document.getElementById('reg-commercial-name').value;
            payload.rif = document.getElementById('reg-rif').value;
            payload.category = document.getElementById('reg-category').value;
        }

        try {
            const res = await fetch('api/index.php?action=register', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            if (!res.ok) throw new Error("HTTP " + res.status);
            const data = await res.json();
            if (data.success) {
                this.currentUser = data.user;
                this.renderUserInterface();
                this.closeModal('modal-auth');

                if (data.is_merchant_pending) {
                    this.openModal('modal-merchant-registered-pending');
                } else {
                    alert(data.message);
                }
                PaseoMacutoMap.loadMerchants();
            } else {
                alert(data.message);
            }
        } catch (err) {
            // Modo Demostración en vivo en GitHub Pages
            this.currentUser = { id: Date.now(), name: name || 'Nuevo Usuario', email: email, role: role, level: 1, xp: 50 };
            localStorage.setItem('pm_demo_user', JSON.stringify(this.currentUser));
            this.renderUserInterface();
            this.closeModal('modal-auth');
            GamificationSystem.showXpAwardNotice(25, 1, '¡Bienvenido a Paseo Macuto en GitHub Pages!');
        }
    },

    async handleLogout() {
        try {
            await fetch('api/index.php?action=logout');
        } catch (e) {}
        localStorage.removeItem('pm_demo_user');
        this.currentUser = null;
        if (typeof CartAndPagoMovil !== 'undefined') {
            CartAndPagoMovil.clearCart();
        }
        this.renderGuestInterface();
        this.closeAllModals();
        alert("Sesión finalizada");
    },

    // -------------------------------------------------------------
    // DASHBOARD SUPERADMIN
    // -------------------------------------------------------------
    async openAdminDashboard() {
        this.openModal('modal-admin-dashboard');
        await this.loadAdminKpis();
        await this.loadAdminPendingVerifications();
        await this.loadAdminComplaints();
        await this.loadAdminBoosters();
    },

    async loadAdminKpis() {
        try {
            const res = await fetch('api/index.php?action=admin_kpis');
            const data = await res.json();
            if (data.success) {
                const k = data.kpis;
                document.getElementById('kpi-merchants-count').innerText = `${k.total_merchants} / ${k.capacity_max}`;
                document.getElementById('kpi-capacity-percent').innerText = `${k.capacity_percent}% Ocupación`;
                document.getElementById('kpi-visitors-count').innerText = k.total_visitors;
                document.getElementById('kpi-sales-usd').innerText = `$${k.total_usd.toFixed(2)}`;
                document.getElementById('kpi-sales-bs').innerText = `${k.total_bs.toFixed(2)} Bs.`;
                document.getElementById('kpi-satisfaction').innerText = `${k.avg_satisfaction} / 5.0`;

                // Alerta de RIFs pendientes
                const alertBox = document.getElementById('admin-pending-rif-alert');
                if (alertBox) {
                    if (k.pending_rif_count > 0) {
                        alertBox.style.display = 'flex';
                        alertBox.innerHTML = `
                            <span><i class="fas fa-exclamation-triangle"></i> Tienes <b>${k.pending_rif_count} comerciante(s)</b> pendientes de verificación de RIF.</span>
                            <button class="btn-primary" style="padding:4px 10px; font-size:11px;" onclick="document.getElementById('admin-tab-rif').click()">Auditar</button>
                        `;
                    } else {
                        alertBox.style.display = 'none';
                    }
                }
            }
        } catch (err) {
            console.error("Error al cargar KPIs:", err);
        }
    },

    async loadAdminPendingVerifications() {
        const container = document.getElementById('admin-verifications-list');
        if (!container) return;

        try {
            const res = await fetch('api/index.php?action=admin_pending_verifications');
            const data = await res.json();
            if (data.success) {
                if (data.pending_merchants.length === 0) {
                    container.innerHTML = `<div style="text-align:center; padding:16px; color:var(--text-muted);"><i class="fas fa-check-circle" style="color:var(--neon-emerald);"></i> Todos los comerciantes están verificados y ubicados en el mapa.</div>`;
                    return;
                }

                container.innerHTML = data.pending_merchants.map(m => `
                    <div style="background:var(--bg-primary); border:1px solid var(--border-color); border-radius:var(--radius-sm); padding:14px; display:flex; flex-direction:column; gap:10px;">
                        <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:10px; flex-wrap:wrap;">
                            <div>
                                <div style="font-weight:800; font-size:14px; color:var(--text-primary);">${m.commercial_name}</div>
                                <div style="font-size:12px; color:var(--caribbean-cyan); font-weight:700; margin-top:2px;">
                                    <i class="fas fa-id-card"></i> RIF: ${m.rif} • <span style="color:var(--text-secondary);">${m.category}</span>
                                </div>
                                <div style="font-size:11px; color:var(--text-muted); margin-top:2px;">
                                    <i class="fas fa-user"></i> Titular: ${m.owner_name} • <i class="fas fa-phone"></i> ${m.owner_phone || m.phone || 'Sin teléfono'}
                                </div>
                            </div>
                            <span class="badge-verified neon-glow-amber" style="font-size:10px;">⏳ Pendiente Ubicación y RIF</span>
                        </div>

                        <!-- Panel de Asignación Exclusiva de Plus Code por el Superusuario -->
                        <div style="background:rgba(0,180,216,0.05); border:1px dashed var(--neon-cyan); border-radius:var(--radius-sm); padding:10px 12px; display:flex; flex-direction:column; gap:6px;">
                            <div style="font-size:11px; font-weight:700; color:var(--neon-cyan); display:flex; align-items:center; justify-content:space-between;">
                                <span><i class="fas fa-map-pin"></i> Asignar Plus Code Oficial en Paseo Macuto:</span>
                                <span style="font-size:10px; color:var(--text-muted);">Ej: 769HJ344+J5 o J344+8F</span>
                            </div>
                            <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                                <input type="text" id="admin-assign-plus-${m.id}" class="form-control" placeholder="Ej: 769HJ344+J5" value="${m.plus_code || ''}" style="flex:1; min-width:170px; font-family:monospace; font-weight:700; font-size:12px; padding:6px 10px; text-transform:uppercase;">
                                <button class="btn-primary" style="padding:7px 14px; font-size:11px; white-space:nowrap;" onclick="PaseoMacutoUI.adminAssignPlusCodeAndActivate(${m.id})">
                                    <i class="fas fa-satellite-dish"></i> Asignar y Publicar en Mapa GIS
                                </button>
                                <button class="btn-danger" style="padding:7px 10px; font-size:11px; white-space:nowrap;" onclick="PaseoMacutoUI.verifyRif(${m.id}, -1)">
                                    <i class="fas fa-ban"></i> Rechazar
                                </button>
                            </div>
                            <div id="admin-assign-alert-${m.id}" style="display:none; font-size:11px; color:var(--coral-alert); margin-top:2px;"></div>
                        </div>
                    </div>
                `).join('');
            }
        } catch (err) {
            container.innerHTML = 'Error al cargar verificaciones';
        }
    },

    async adminAssignPlusCodeAndActivate(merchantId, customInputId = null) {
        const inputId = customInputId || `admin-assign-plus-${merchantId}`;
        const input = document.getElementById(inputId);
        const alertEl = document.getElementById(`admin-assign-alert-${merchantId}`);
        if (!input) return;
        const plusCode = input.value.trim().toUpperCase();

        if (!plusCode) {
            alert("⚠️ Ingrese el Plus Code oficial de Paseo Macuto para ubicar el establecimiento.");
            input.focus();
            return;
        }

        if (!this.isPlusCodeInPaseoMacuto(plusCode)) {
            if (alertEl) {
                alertEl.style.display = 'block';
                alertEl.innerHTML = `<i class="fas fa-exclamation-triangle"></i> El Plus Code <code>${plusCode}</code> está fuera del rango del Paseo Macuto. Ejemplo válido: <code>769HJ344+J5</code> o <code>J344+8F</code>`;
            } else {
                alert(`⚠️ Ubicación fuera de rango:\n\nEl Plus Code ingresado (${plusCode}) no pertenece a la franja del Paseo Macuto (La Guaira).\n\nEjemplo válido: 769HJ344+J5 o J344+8F`);
            }
            input.focus();
            return;
        }

        if (alertEl) alertEl.style.display = 'none';

        try {
            const res = await fetch('api/index.php?action=admin_assign_merchant_location', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    merchant_id: merchantId,
                    plus_code: plusCode
                })
            });
            const data = await res.json();
            if (data.success) {
                alert(data.message);
                this.loadAdminKpis();
                this.loadAdminPendingVerifications();
                
                // Cargar y mostrar inmediatamente en el mapa GIS
                PaseoMacutoMap.loadMerchants();

                // Si está viendo el perfil de este usuario, actualizarlo
                if (this.currentViewingProfileUserId) {
                    this.openUserProfileAndHistory(this.currentViewingProfileUserId);
                }
            } else {
                alert(data.message);
            }
        } catch (err) {
            alert("Error al asignar Plus Code");
        }
    },

    async adminAssignPlusCodeFromProfile(merchantId) {
        await this.adminAssignPlusCodeAndActivate(merchantId, `admin-profile-plus-${merchantId}`);
    },

    async verifyRif(merchantId, status) {
        try {
            const res = await fetch('api/index.php?action=admin_verify_rif', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ merchant_id: merchantId, status: status })
            });
            const data = await res.json();
            if (data.success) {
                alert(data.message);
                this.loadAdminKpis();
                this.loadAdminPendingVerifications();
                PaseoMacutoMap.loadMerchants();
            }
        } catch (err) {
            alert("Error al procesar RIF");
        }
    },

    async loadAdminComplaints() {
        const container = document.getElementById('admin-complaints-list');
        if (!container) return;

        try {
            const res = await fetch('api/index.php?action=admin_complaints');
            const data = await res.json();
            if (data.success) {
                if (data.complaints.length === 0) {
                    container.innerHTML = `<div style="text-align:center; padding:16px; color:var(--text-muted);">No hay quejas ni denuncias registradas.</div>`;
                    return;
                }

                container.innerHTML = data.complaints.map(c => `
                    <div style="background:var(--bg-primary); border:1px solid var(--border-color); border-radius:var(--radius-sm); padding:12px; display:flex; flex-direction:column; gap:8px;">
                        <div style="display:flex; justify-content:space-between; font-size:12px;">
                            <span style="font-weight:700; color:var(--coral-alert);"><i class="fas fa-exclamation-circle"></i> Denuncia contra: ${c.commercial_name}</span>
                            <span style="color:var(--text-muted);">${c.created_at}</span>
                        </div>
                        <div style="font-size:13px; background:var(--bg-surface); padding:8px; border-radius:6px;">"${c.description}"</div>
                        <div style="font-size:11px; color:var(--text-muted);">Denunciante: ${c.visitor_name} (${c.visitor_email})</div>
                        <div style="display:flex; gap:6px; margin-top:4px;">
                            <button class="btn-danger" style="padding:6px 10px;" onclick="PaseoMacutoUI.resolveComplaint(${c.id}, 'penalized')">
                                Penalizar Comerciante (-0.5 Rep)
                            </button>
                            <button class="btn-secondary" style="padding:6px 10px; font-size:12px;" onclick="PaseoMacutoUI.resolveComplaint(${c.id}, 'resolved')">
                                Marcar Resuelto
                            </button>
                        </div>
                    </div>
                `).join('');
            }
        } catch (err) {
            container.innerHTML = 'Error al cargar quejas';
        }
    },

    async resolveComplaint(id, status) {
        try {
            const res = await fetch('api/index.php?action=admin_resolve_complaint', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ complaint_id: id, status: status })
            });
            const data = await res.json();
            if (data.success) {
                alert(data.message);
                this.loadAdminComplaints();
                PaseoMacutoMap.loadMerchants();
            }
        } catch (err) {
            alert("Error al resolver denuncia");
        }
    },

    async loadAdminBoosters() {
        const container = document.getElementById('admin-boosters-list');
        if (!container) return;

        try {
            const res = await fetch('api/index.php?action=admin_boosters');
            const data = await res.json();
            if (data.success) {
                container.innerHTML = data.boosters.map(b => `
                    <div style="background:var(--bg-primary); border:1px solid var(--border-color); border-radius:var(--radius-sm); padding:10px 14px; display:flex; justify-content:space-between; align-items:center;">
                        <div>
                            <div style="font-weight:700; font-size:13px; display:flex; align-items:center; gap:6px;">
                                ${b.name}
                                <span style="background:#F59E0B; color:#fff; font-size:10px; font-weight:800; padding:1px 6px; border-radius:10px;">x${b.multiplier}</span>
                            </div>
                            <div style="font-size:11px; color:var(--text-muted);">${b.description}</div>
                        </div>
                        <button class="${parseInt(b.is_active) === 1 ? 'btn-primary' : 'btn-secondary'}" style="padding:4px 10px; font-size:11px;" onclick="PaseoMacutoUI.toggleBooster(${b.id})">
                            ${parseInt(b.is_active) === 1 ? 'Activo' : 'Inactivo'}
                        </button>
                    </div>
                `).join('');
            }
        } catch (err) {
            container.innerHTML = 'Error al cargar potenciadores';
        }
    },

    async toggleBooster(id) {
        try {
            await fetch('api/index.php?action=admin_boosters', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ sub_action: 'toggle', booster_id: id })
            });
            this.loadAdminBoosters();
        } catch (err) {
            alert("Error al alternar potenciador");
        }
    },

    async createBooster(e) {
        e.preventDefault();
        const name = document.getElementById('boost-name').value;
        const multiplier = parseFloat(document.getElementById('boost-multiplier').value);
        const actionType = document.getElementById('boost-action').value;
        const desc = document.getElementById('boost-desc').value;

        try {
            const res = await fetch('api/index.php?action=admin_boosters', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ sub_action: 'create', name, multiplier, action_type: actionType, description: desc })
            });
            const data = await res.json();
            if (data.success) {
                alert(data.message);
                document.getElementById('form-create-booster').reset();
                this.loadAdminBoosters();
            } else {
                alert(data.message);
            }
        } catch (err) {
            alert("Error al crear potenciador");
        }
    },

    // -------------------------------------------------------------
    // DASHBOARD COMERCIANTE (INVENTARIO Y GEOLOCALIZACIÓN)
    // -------------------------------------------------------------
    openMerchantDashboard() {
        if (!this.currentUser || !this.currentUser.merchant) {
            alert("No tienes un local asociado.");
            return;
        }
        const m = this.currentUser.merchant;
        document.getElementById('merch-dash-name').innerText = m.commercial_name;
        document.getElementById('merch-dash-rif').innerText = m.rif;
        const plusEl = document.getElementById('merch-dash-plus-display');
        const latEl = document.getElementById('merch-dash-lat-display');
        const lngEl = document.getElementById('merch-dash-lng-display');
        const locBadge = document.getElementById('merch-dash-loc-status-badge');

        const isLocated = m.plus_code && parseFloat(m.lat) !== 0;
        if (plusEl) plusEl.innerText = m.plus_code || 'Pendiente por Superusuario';
        if (latEl) latEl.innerText = isLocated ? parseFloat(m.lat).toFixed(6) : 'Pendiente';
        if (lngEl) lngEl.innerText = isLocated ? parseFloat(m.lng).toFixed(6) : 'Pendiente';

        if (locBadge) {
            locBadge.className = isLocated ? 'badge-verified neon-glow-emerald' : 'badge-pending neon-glow-amber';
            locBadge.innerHTML = isLocated
                ? '<i class="fas fa-check-circle"></i> Publicado en Mapa GIS'
                : '<i class="fas fa-hourglass-half"></i> En Verificación por Superusuario';
        }

        const rifBadge = document.getElementById('merch-dash-rif-badge');
        if (rifBadge) {
            rifBadge.className = parseInt(m.rif_verified) === 1 ? 'badge-verified neon-glow-emerald' : 'badge-pending neon-glow-amber';
            rifBadge.innerHTML = parseInt(m.rif_verified) === 1 ? '<i class="fas fa-check-circle"></i> RIF Aprobado' : '<i class="fas fa-clock"></i> Pendiente de Verificación por Admin';
        }

        // Doble Reputación
        document.getElementById('merch-rep-total').innerText = parseFloat(m.total_rep || 5.0).toFixed(1);
        document.getElementById('merch-rep-sales').innerText = parseFloat(m.sales_rep || 5.0).toFixed(1);
        document.getElementById('merch-rep-service').innerText = parseFloat(m.service_rep || 5.0).toFixed(1);
        document.getElementById('merch-sales-count').innerText = m.sales_count || 0;

        this.loadMerchantProducts(m.id);
        this.openModal('modal-merchant-dashboard');
    },

    async loadMerchantProducts(merchantId) {
        const container = document.getElementById('merch-products-list');
        if (!container) return;

        try {
            const res = await fetch(`api/index.php?action=get_merchant_detail&id=${merchantId}`);
            const data = await res.json();
            if (data.success) {
                const prods = data.merchant.products || [];
                if (prods.length === 0) {
                    container.innerHTML = `<div style="text-align:center; padding:12px; color:var(--text-muted);">Aún no has agregado productos a tu menú/catálogo.</div>`;
                    return;
                }

                container.innerHTML = prods.map(p => `
                    <div style="background:var(--bg-primary); border:1px solid var(--border-color); border-radius:var(--radius-sm); padding:10px 14px; display:flex; justify-content:space-between; align-items:center;">
                        <div>
                            <div style="font-weight:700; font-size:13px;">${p.name}</div>
                            <div style="font-size:11px; color:var(--text-muted);">${p.description || ''}</div>
                        </div>
                        <div style="text-align:right;">
                            <div style="font-size:14px; font-weight:800; color:var(--caribbean-cyan);">$${p.price_usd.toFixed(2)}</div>
                            <div style="font-size:11px; color:var(--text-muted);">${p.price_bs.toFixed(2)} Bs.</div>
                        </div>
                    </div>
                `).join('');
            }
        } catch (err) {
            container.innerHTML = 'Error al cargar productos';
        }
    },

    async saveMerchantLocation(e) {
        e.preventDefault();
        const merchantId = this.currentUser.merchant.id;
        const lat = parseFloat(document.getElementById('merch-dash-lat').value);
        const lng = parseFloat(document.getElementById('merch-dash-lng').value);
        const plus_code = (document.getElementById('merch-dash-plus').value || '').trim();

        // Validar rango geográfico de Paseo Macuto
        if (plus_code && !this.isPlusCodeInPaseoMacuto(plus_code)) {
            this.validateDashboardPlusCode(plus_code);
            alert("⚠️ Ubicación fuera de rango:\n\nEl Plus Code ingresado no pertenece a la extensión del Paseo Macuto.\nDebe ingresar un Plus Code correspondiente al Paseo Macuto (La Guaira).\n\nEjemplo válido: 769HJ344+J5");
            document.getElementById('merch-dash-plus').focus();
            return;
        }

        if (lat < 10.6010 || lat > 10.6130 || lng < -66.9010 || lng > -66.8880) {
            alert("⚠️ Coordenadas GPS fuera de rango:\n\nLa ubicación ingresada se encuentra fuera del perímetro del Paseo Macuto (La Guaira).");
            return;
        }

        try {
            const res = await fetch('api/index.php?action=update_merchant_location', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ merchant_id: merchantId, lat, lng, plus_code })
            });
            const data = await res.json();
            if (data.success) {
                alert("¡Ubicación del establecimiento actualizada en Paseo Macuto!");
                this.currentUser.merchant.lat = lat;
                this.currentUser.merchant.lng = lng;
                this.currentUser.merchant.plus_code = plus_code;
                PaseoMacutoMap.loadMerchants();
            } else {
                alert(data.message);
            }
        } catch (err) {
            alert("Error al actualizar coordenadas");
        }
    },

    detectMerchantGps() {
        if (!navigator.geolocation) {
            alert("Geolocalización no disponible");
            return;
        }
        navigator.geolocation.getCurrentPosition(
            (pos) => {
                document.getElementById('merch-dash-lat').value = pos.coords.latitude.toFixed(7);
                document.getElementById('merch-dash-lng').value = pos.coords.longitude.toFixed(7);
                alert("Coordenadas exactas del dispositivo capturadas. Guarda los cambios para actualizar el mapa.");
            },
            (err) => alert("Error al obtener GPS: " + err.message),
            { enableHighAccuracy: true }
        );
    },

    async addMerchantProduct(e) {
        e.preventDefault();
        const merchantId = this.currentUser.merchant.id;
        const name = document.getElementById('new-prod-name').value;
        const category = document.getElementById('new-prod-cat').value;
        const price_usd = parseFloat(document.getElementById('new-prod-price').value);
        const description = document.getElementById('new-prod-desc').value;

        try {
            const res = await fetch('api/index.php?action=save_product', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ merchant_id: merchantId, name, category, price_usd, description })
            });
            const data = await res.json();
            if (data.success) {
                alert(data.message);
                document.getElementById('form-add-product').reset();
                this.loadMerchantProducts(merchantId);
                PaseoMacutoMap.loadMerchants();
            } else {
                alert(data.message);
            }
        } catch (err) {
            alert("Error al guardar producto");
        }
    },

    // -------------------------------------------------------------
    // PERFIL VISITANTE Y GAMIFICACIÓN
    // -------------------------------------------------------------
    openVisitorProfile() {
        if (!this.currentUser) return;
        document.getElementById('visitor-profile-name').innerText = this.currentUser.name;
        document.getElementById('visitor-profile-email').innerText = this.currentUser.email;

        // Renderizar barra de 10 niveles
        const xp = this.currentUser.level_data ? this.currentUser.level_data.xp : 0;
        GamificationSystem.renderBadge('visitor-level-card', xp);

        this.openModal('modal-visitor-profile');
    },

    // -------------------------------------------------------------
    // DETALLE DE COMERCIO Y COMPRA PARA VISITANTES
    // -------------------------------------------------------------
    async openMerchantDetail(merchantId) {
        try {
            let m = null;
            try {
                const res = await fetch(`api/index.php?action=get_merchant_detail&id=${merchantId}`);
                if (!res.ok) throw new Error("HTTP error " + res.status);
                const data = await res.json();
                if (data.success) m = data.merchant;
            } catch (apiErr) {
                // Fallback para GitHub Pages
                if (window.PaseoMacutoMap && window.PaseoMacutoMap.allMerchants) {
                    m = window.PaseoMacutoMap.allMerchants.find(x => x.id == merchantId);
                    if (m && m.products) {
                        m.products = m.products.map(p => ({
                            ...p,
                            price_bs: p.price_usd * (window.PaseoMacutoMap.bcvRate || 54.50)
                        }));
                    }
                }
            }

            if (m) {
                document.getElementById('detail-merch-title').innerText = m.commercial_name || m.name;
                document.getElementById('detail-merch-category').innerText = `${m.category} • ${m.sector}`;
                document.getElementById('detail-merch-desc').innerText = m.description || '';
                document.getElementById('detail-merch-rep').innerText = m.total_rep.toFixed(1);
                document.getElementById('detail-merch-sales').innerText = m.sales_count;
                document.getElementById('detail-merch-rif').innerText = m.rif;

                const prodsContainer = document.getElementById('detail-products-list');
                if (m.products && m.products.length > 0) {
                    prodsContainer.innerHTML = m.products.map(p => `
                        <div style="background:var(--bg-primary); border:1px solid var(--border-color); border-radius:var(--radius-sm); padding:12px; display:flex; justify-content:space-between; align-items:center; gap:8px;">
                            <div>
                                <div style="font-weight:700; font-size:13px;">${p.name}</div>
                                <div style="font-size:11px; color:var(--text-muted);">${p.description || ''}</div>
                                <div style="font-size:12px; font-weight:800; color:var(--caribbean-cyan); margin-top:2px;">
                                    $${p.price_usd.toFixed(2)} <span style="font-weight:500; font-size:11px; color:var(--text-muted);">(${p.price_bs.toFixed(2)} Bs.)</span>
                                </div>
                            </div>
                            <div style="display:flex; gap:6px;">
                                <button class="btn-secondary" style="padding:6px 10px; font-size:11px;" onclick='CartAndPagoMovil.addToCart(${JSON.stringify(p)}, ${JSON.stringify({id: m.id, commercial_name: m.commercial_name})})'>
                                    <i class="fas fa-cart-plus"></i> + Carrito
                                </button>
                                <button class="btn-primary" style="padding:6px 10px; font-size:11px;" onclick='if(CartAndPagoMovil.addToCart(${JSON.stringify(p)}, ${JSON.stringify({id: m.id, commercial_name: m.commercial_name})})) CartAndPagoMovil.openCartModal();'>
                                    <i class="fas fa-bolt"></i> Pagar
                                </button>
                            </div>
                        </div>
                    `).join('');
                } else {
                    prodsContainer.innerHTML = `<div style="text-align:center; color:var(--text-muted); padding:16px;">Este local no tiene productos cargados aún.</div>`;
                }

                // Reseñas
                const revContainer = document.getElementById('detail-reviews-list');
                if (m.reviews && m.reviews.length > 0) {
                    revContainer.innerHTML = m.reviews.map(r => `
                        <div style="background:var(--bg-primary); padding:10px; border-radius:6px; font-size:12px; display:flex; flex-direction:column; gap:4px;">
                            <div style="display:flex; justify-content:space-between;">
                                <b style="color:var(--text-primary);">${r.visitor_name} (Lv.${r.visitor_level || 1})</b>
                                <span style="color:#F59E0B;"><i class="fas fa-star"></i> ${r.rating}.0</span>
                            </div>
                            <div style="color:var(--text-secondary);">"${r.comment || 'Buen servicio'}"</div>
                        </div>
                    `).join('');
                } else {
                    revContainer.innerHTML = `<div style="text-align:center; color:var(--text-muted); padding:10px;">Aún no hay opiniones. ¡Sé el primero en calificar!</div>`;
                }

                // Botón de Denuncia
                document.getElementById('btn-complaint-trigger').onclick = () => {
                    this.closeModal('modal-merchant-detail');
                    this.openComplaintModal(m.id, m.commercial_name);
                };

                this.openModal('modal-merchant-detail');
            }
        } catch (err) {
            alert("Error al cargar ficha del local");
        }
    },

    async simulatePurchase(merchantId, productId, amountUsd) {
        if (!this.currentUser) {
            alert("Inicia sesión para comprar productos y ganar XP.");
            this.openModal('modal-auth');
            return;
        }

        try {
            const res = await fetch('api/index.php?action=create_purchase', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ merchant_id: merchantId, product_id: productId, amount_usd: amountUsd })
            });
            const data = await res.json();
            if (data.success) {
                GamificationSystem.showXpAwardNotice(data.xp_earned, data.multiplier, '¡Compra realizada con éxito!');
                if (data.level_data) {
                    this.currentUser.level_data = data.level_data;
                    this.renderUserInterface();
                }
                this.closeModal('modal-merchant-detail');
                PaseoMacutoMap.loadMerchants();
            } else {
                alert(data.message);
            }
        } catch (err) {
            alert("Error al procesar compra");
        }
    },

    openReviewModal(merchantId, merchantName) {
        if (!this.currentUser) {
            alert("Inicia sesión para calificar este comercio.");
            this.openModal('modal-auth');
            return;
        }
        document.getElementById('rev-merchant-id').value = merchantId;
        document.getElementById('rev-merchant-title').innerText = unescape(merchantName);
        this.openModal('modal-submit-review');
    },

    async submitReview(e) {
        e.preventDefault();
        const merchantId = document.getElementById('rev-merchant-id').value;
        const rating = parseInt(document.getElementById('rev-rating').value);
        const comment = document.getElementById('rev-comment').value;

        try {
            const res = await fetch('api/index.php?action=submit_review', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ merchant_id: merchantId, rating, comment })
            });
            const data = await res.json();
            if (data.success) {
                GamificationSystem.showXpAwardNotice(data.xp_earned, 1, '¡Opinión publicada!');
                if (data.level_data) {
                    this.currentUser.level_data = data.level_data;
                    this.renderUserInterface();
                }
                this.closeModal('modal-submit-review');
                PaseoMacutoMap.loadMerchants();
            } else {
                alert(data.message);
            }
        } catch (err) {
            alert("Error al publicar calificación");
        }
    },

    openComplaintModal(merchantId, merchantName) {
        if (!this.currentUser) {
            alert("Inicia sesión para presentar una denuncia.");
            this.openModal('modal-auth');
            return;
        }
        document.getElementById('comp-merchant-id').value = merchantId;
        document.getElementById('comp-merchant-title').innerText = merchantName;
        this.openModal('modal-submit-complaint');
    },

    async submitComplaint(e) {
        e.preventDefault();
        const merchantId = document.getElementById('comp-merchant-id').value;
        const description = document.getElementById('comp-desc').value;

        try {
            const res = await fetch('api/index.php?action=submit_complaint', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ merchant_id: merchantId, description })
            });
            const data = await res.json();
            if (data.success) {
                alert(data.message);
                document.getElementById('form-submit-complaint').reset();
                this.closeModal('modal-submit-complaint');
            } else {
                alert(data.message);
            }
        } catch (err) {
            alert("Error al registrar denuncia");
        }
    },

    // -------------------------------------------------------------
    // GESTIÓN DE MODALES Y PANELES
    // -------------------------------------------------------------
    openModal(modalId, tab = null) {
        const modal = document.getElementById(modalId);
        if (modal) modal.classList.add('active');

        if (modalId === 'modal-auth' && tab) {
            const loginBox = document.getElementById('form-login-box');
            const regBox = document.getElementById('form-reg-box');
            const tabLogin = document.getElementById('tab-login');
            const tabRegister = document.getElementById('tab-register');

            if (tab === 'register') {
                if (loginBox) loginBox.style.display = 'none';
                if (regBox) regBox.style.display = 'flex';
                if (tabLogin) {
                    tabLogin.style.color = 'var(--text-secondary)';
                    tabLogin.style.borderBottom = 'none';
                }
                if (tabRegister) {
                    tabRegister.style.color = 'var(--caribbean-cyan)';
                    tabRegister.style.borderBottom = '2px solid var(--caribbean-cyan)';
                }
            } else if (tab === 'login') {
                if (loginBox) loginBox.style.display = 'flex';
                if (regBox) regBox.style.display = 'none';
                if (tabLogin) {
                    tabLogin.style.color = 'var(--caribbean-cyan)';
                    tabLogin.style.borderBottom = '2px solid var(--caribbean-cyan)';
                }
                if (tabRegister) {
                    tabRegister.style.color = 'var(--text-secondary)';
                    tabRegister.style.borderBottom = 'none';
                }
            }
        }
    },

    // -------------------------------------------------------------
    // GESTIÓN DEL PANEL RETRÁCTIL LATERAL Y MÓDULOS CON LUZ NEÓN
    // -------------------------------------------------------------
    toggleSidebar(forceOpen = null) {
        const sidebar = document.getElementById('left-retractable-sidebar');
        const backdrop = document.getElementById('sidebar-backdrop');
        if (!sidebar) return;

        const isOpen = forceOpen !== null ? forceOpen : !sidebar.classList.contains('open');
        if (isOpen) {
            this.updateRoleBasedSidebar();
            sidebar.classList.add('open');
            if (backdrop) backdrop.classList.add('active');
        } else {
            sidebar.classList.remove('open');
            if (backdrop) backdrop.classList.remove('active');
        }
    },

    updateRoleBasedSidebar() {
        const role = this.currentUser ? this.currentUser.role : 'guest';

        const pAdmin = document.getElementById('sidebar-panel-superadmin');
        const pMerchant = document.getElementById('sidebar-panel-merchant');
        const pVisitor = document.getElementById('sidebar-panel-visitor');
        const pGuest = document.getElementById('sidebar-panel-guest');

        // Ocultar todos los paneles primero
        if (pAdmin) pAdmin.style.display = 'none';
        if (pMerchant) pMerchant.style.display = 'none';
        if (pVisitor) pVisitor.style.display = 'none';
        if (pGuest) pGuest.style.display = 'none';

        const sidebarTitle = document.querySelector('.sidebar-title-badge span');

        // Mostrar EXCLUSIVAMENTE el panel que corresponde al rol activo
        if (role === 'superadmin') {
            if (pAdmin) pAdmin.style.display = 'flex';
            if (sidebarTitle) sidebarTitle.innerText = 'Panel Superusuario';
        } else if (role === 'merchant') {
            if (pMerchant) pMerchant.style.display = 'flex';
            if (sidebarTitle) sidebarTitle.innerText = 'Panel del Vendedor';
        } else if (role === 'visitor') {
            if (pVisitor) pVisitor.style.display = 'flex';
            if (sidebarTitle) sidebarTitle.innerText = 'Panel del Visitante';

            const ind = document.getElementById('visitor-purchases-indicator');
            if (ind) {
                fetch('api/index.php?action=get_module_data&module=visitor_purchases')
                    .then(r => r.json())
                    .then(d => {
                        if (d.success && d.data && d.data.counts) {
                            const c = d.data.counts;
                            ind.innerHTML = `${c.completed} Verif. • ${c.pending} Pend. <i class="fas fa-chevron-right"></i>`;
                        }
                    })
                    .catch(() => {});
            }
        } else {
            if (pGuest) pGuest.style.display = 'flex';
            if (sidebarTitle) sidebarTitle.innerText = 'Paseo Macuto';
        }

        // Widget de tasa BCV en cabecera
        const bcvWidget = document.getElementById('bcv-widget-trigger');
        if (bcvWidget) {
            bcvWidget.title = role === 'superadmin' 
                ? 'Tasa oficial BCV. Clic para auditar o modificar (Superadmin)' 
                : `Tasa oficial BCV: ${this.bcvRate.toFixed(2)} Bs./USD`;
            bcvWidget.style.cursor = role === 'superadmin' ? 'pointer' : 'default';
        }
    },

    // -------------------------------------------------------------
    // MÓDULO: MI PERFIL, EXPEDIENTE DE USUARIO Y LOG HISTÓRICO
    // -------------------------------------------------------------
    async openUserProfileAndHistory(targetUserId = null) {
        if (!this.currentUser) {
            this.openModal('modal-auth');
            return;
        }

        this.openModal('modal-user-profile-history');

        const kpisContainer = document.getElementById('prof-kpis-grid-container');
        const timelineList = document.getElementById('prof-history-timeline-list');
        const kpisTitle = document.getElementById('prof-kpis-title');
        const historyBadge = document.getElementById('prof-history-count-badge');
        const gamificationSec = document.getElementById('prof-gamification-section');
        const extraDetailsContainer = document.getElementById('prof-extra-details-container');
        const modalTitle = document.getElementById('profile-history-modal-title');
        const modalSub = document.getElementById('profile-history-modal-sub');
        const logoutBtn = document.getElementById('prof-btn-logout');

        if (kpisContainer) {
            kpisContainer.innerHTML = `
                <div style="grid-column: 1 / -1; text-align:center; padding:20px; color:var(--caribbean-cyan);">
                    <i class="fas fa-spinner fa-spin" style="font-size:24px;"></i>
                    <p style="margin-top:8px; font-size:12px;">Cargando expediente y bitácora completa del usuario...</p>
                </div>
            `;
        }
        if (extraDetailsContainer) extraDetailsContainer.innerHTML = '';

        const isOtherUser = targetUserId && Number(targetUserId) !== Number(this.currentUser.id);
        if (logoutBtn) logoutBtn.style.display = isOtherUser ? 'none' : 'inline-block';

        try {
            const url = targetUserId 
                ? `api/index.php?action=get_user_profile_and_history&user_id=${targetUserId}`
                : 'api/index.php?action=get_user_profile_and_history';

            const res = await fetch(url);
            const data = await res.json();

            if (!data.success) {
                if (kpisContainer) kpisContainer.innerHTML = `<div style="color:var(--coral-alert); padding:10px;">${data.message}</div>`;
                return;
            }

            const u = data.user;
            const k = data.kpis;
            const m = data.merchant_details;
            const prods = data.merchant_products || [];
            const history = data.history || [];

            // 0. Títulos del Modal
            if (modalTitle) {
                modalTitle.innerHTML = isOtherUser
                    ? `<i class="fas fa-address-card" style="color:var(--neon-cyan);"></i> Expediente de Usuario #${u.id}: <span style="color:#06D6A0;">${u.name}</span>`
                    : `<i class="fas fa-id-card-alt" style="color:var(--neon-cyan);"></i> Mi Perfil & Historial de Interacciones`;
            }
            if (modalSub) {
                modalSub.innerText = isOtherUser
                    ? `Todos los datos cargados en el sistema para este usuario (${u.role.toUpperCase()})`
                    : `Dashboard de rendimiento y bitácora cronológica completa`;
            }

            // 1. Cabecera de Identidad
            document.getElementById('prof-user-name').innerText = u.name;
            document.getElementById('prof-user-email').innerHTML = `
                <span>${u.email}</span> 
                ${u.phone ? `• <b style="color:var(--text-primary);"><i class="fas fa-phone-alt" style="font-size:10px;"></i> ${u.phone}</b>` : '• Tel: No registrado'} 
                • <span style="color:var(--text-muted);">ID #${u.id}</span>
            `;
            document.getElementById('prof-member-since').innerHTML = `
                <i class="fas fa-calendar-alt"></i> Miembro desde: ${u.member_since} (${k.days_member || 1} días en Paseo Macuto) 
                • <span style="color:#06D6A0;"><i class="fas fa-shield-alt"></i> Cuenta Activa</span>
            `;

            const roleBadge = document.getElementById('prof-user-role-badge');
            const avatarIcon = document.getElementById('profile-avatar-icon');

            if (u.role === 'superadmin') {
                if (roleBadge) {
                    roleBadge.innerText = 'Superusuario Central';
                    roleBadge.className = 'badge-verified neon-glow-purple';
                }
                if (avatarIcon) avatarIcon.innerHTML = '<i class="fas fa-crown" style="color:var(--neon-purple);"></i>';
            } else if (u.role === 'merchant') {
                if (roleBadge) {
                    roleBadge.innerText = `Vendedor: ${k.merchant_name || 'Comercio'}`;
                    roleBadge.className = 'badge-verified neon-glow-emerald';
                }
                if (avatarIcon) avatarIcon.innerHTML = '<i class="fas fa-store" style="color:var(--neon-emerald);"></i>';
            } else {
                if (roleBadge) {
                    roleBadge.innerText = 'Visitante Turista';
                    roleBadge.className = 'badge-verified neon-glow-cyan';
                }
                if (avatarIcon) avatarIcon.innerHTML = '<i class="fas fa-umbrella-beach" style="color:var(--caribbean-cyan);"></i>';
            }

            // 2. Sección de Gamificación
            if (gamificationSec && u.gamification) {
                const g = u.gamification;
                gamificationSec.innerHTML = `
                    <div style="background:var(--bg-primary); border:1px solid var(--border-color); border-radius:var(--radius-sm); padding:10px 14px;">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
                            <span style="font-size:12px; font-weight:800; color:var(--text-primary);">
                                <i class="fas fa-medal" style="color:#F59E0B;"></i> Nivel ${g.level}: <span style="color:var(--caribbean-cyan);">${g.title}</span>
                            </span>
                            <span style="font-size:11px; font-weight:800; color:#06D6A0;">${g.xp} XP acumulados</span>
                        </div>
                        <div class="progress-bar-bg" style="height:8px; background:rgba(255,255,255,0.1); border-radius:4px; overflow:hidden;">
                            <div class="progress-bar-fill" style="width:${g.progress_percent}%; height:100%; background:linear-gradient(90deg, var(--neon-cyan), #06D6A0); transition:width 0.6s ease;"></div>
                        </div>
                        <div style="display:flex; justify-content:space-between; font-size:10px; color:var(--text-muted); margin-top:4px;">
                            <span>Base: ${g.current_level_xp} XP</span>
                            <span>${g.progress_percent}% completado</span>
                            <span>Meta: ${g.next_level_xp} XP</span>
                        </div>
                    </div>
                `;
            }

            // 3. Renderizado de KPIs según Rol
            if (kpisTitle) {
                if (u.role === 'merchant') kpisTitle.innerText = `Dashboard de Rendimiento Comercial (${k.merchant_name || 'Comercio'})`;
                else if (u.role === 'superadmin') kpisTitle.innerText = 'Métricas Globales del Paseo Macuto';
                else kpisTitle.innerText = 'Métricas de Consumo del Visitante';
            }

            if (kpisContainer) {
                if (u.role === 'merchant') {
                    kpisContainer.innerHTML = `
                        <div class="profile-kpi-item neon-glow-emerald">
                            <span class="profile-kpi-val" style="color:#06D6A0;">$${k.total_sales_usd.toFixed(2)}</span>
                            <span class="profile-kpi-lbl">Ventas Efectivas (${k.completed_sales_count})</span>
                        </div>
                        <div class="profile-kpi-item neon-glow-amber" onclick="PaseoMacutoUI.closeModal('modal-user-profile-history'); CartAndPagoMovil.openMerchantVerificationPanel('pending');" style="cursor:pointer;" title="Clic para auditar pagos">
                            <span class="profile-kpi-val" style="color:var(--neon-amber);">${k.pending_verifications_count}</span>
                            <span class="profile-kpi-lbl">Pagos por Verificar <i class="fas fa-external-link-alt" style="font-size:9px;"></i></span>
                        </div>
                        <div class="profile-kpi-item">
                            <span class="profile-kpi-val" style="color:#F59E0B;">★ ${k.reputation_stars.toFixed(1)}</span>
                            <span class="profile-kpi-lbl">Reputación Comercial</span>
                        </div>
                        <div class="profile-kpi-item">
                            <span class="profile-kpi-val" style="color:var(--neon-cyan);">${k.active_products_count}</span>
                            <span class="profile-kpi-lbl">Productos en Menú</span>
                        </div>
                        <div class="profile-kpi-item">
                            <span class="profile-kpi-val" style="color:${k.complaints_count > 0 ? 'var(--coral-alert)' : 'var(--text-muted)'};">${k.complaints_count}</span>
                            <span class="profile-kpi-lbl">Reclamos Recibidos</span>
                        </div>
                    `;
                } else if (u.role === 'superadmin') {
                    kpisContainer.innerHTML = `
                        <div class="profile-kpi-item neon-glow-purple">
                            <span class="profile-kpi-val" style="color:var(--neon-purple);">${k.total_users}</span>
                            <span class="profile-kpi-lbl">Usuarios en Sistema</span>
                        </div>
                        <div class="profile-kpi-item neon-glow-cyan">
                            <span class="profile-kpi-val" style="color:var(--neon-cyan);">${k.total_merchants}</span>
                            <span class="profile-kpi-lbl">Comercios Registrados</span>
                        </div>
                        <div class="profile-kpi-item neon-glow-emerald">
                            <span class="profile-kpi-val" style="color:#06D6A0;">$${k.total_sales_usd.toFixed(2)}</span>
                            <span class="profile-kpi-lbl">Ventas Globales (${k.total_completed_sales})</span>
                        </div>
                        <div class="profile-kpi-item neon-glow-amber">
                            <span class="profile-kpi-val" style="color:var(--neon-amber);">${k.pending_rifs}</span>
                            <span class="profile-kpi-lbl">RIFs por Auditar</span>
                        </div>
                    `;
                } else {
                    // Visitante
                    kpisContainer.innerHTML = `
                        <div class="profile-kpi-item neon-glow-cyan">
                            <span class="profile-kpi-val" style="color:var(--caribbean-cyan);">$${k.total_spent_usd.toFixed(2)}</span>
                            <span class="profile-kpi-lbl">Total Comprado (${k.completed_purchases_count})</span>
                        </div>
                        <div class="profile-kpi-item neon-glow-amber">
                            <span class="profile-kpi-val" style="color:var(--neon-amber);">${k.pending_purchases_count}</span>
                            <span class="profile-kpi-lbl">Pagos en Verificación</span>
                        </div>
                        <div class="profile-kpi-item">
                            <span class="profile-kpi-val" style="color:#F59E0B;">${k.reviews_left_count}</span>
                            <span class="profile-kpi-lbl">Opiniones Publicadas</span>
                        </div>
                        <div class="profile-kpi-item">
                            <span class="profile-kpi-val" style="color:var(--neon-purple);">${k.xp}</span>
                            <span class="profile-kpi-lbl">Puntos XP Acumulados</span>
                        </div>
                        <div class="profile-kpi-item">
                            <span class="profile-kpi-val" style="color:var(--text-muted);">${k.complaints_left_count}</span>
                            <span class="profile-kpi-lbl">Reportes Enviados</span>
                        </div>
                    `;
                }
            }

            // 4. EXPEDIENTE COMPLETO: Datos cargados del Comercio / Establecimiento / Pago Móvil / Productos
            if (extraDetailsContainer) {
                if (u.role === 'merchant' && m) {
                    extraDetailsContainer.innerHTML = `
                        <div class="neon-card emerald" style="margin-bottom:14px; padding:14px;">
                            <div class="neon-card-title" style="margin-bottom:10px; font-size:13px; color:var(--neon-emerald);">
                                <i class="fas fa-store-alt"></i> Datos Cargados del Establecimiento Comercial
                            </div>
                            <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:10px; font-size:12px;">
                                <div style="background:var(--bg-primary); padding:8px 10px; border-radius:var(--radius-sm); border:1px solid var(--border-color);">
                                    <div style="color:var(--text-muted); font-size:10px;">Nombre Comercial / Puesto</div>
                                    <b style="color:var(--text-primary); font-size:13px;">${m.commercial_name}</b>
                                </div>
                                <div style="background:var(--bg-primary); padding:8px 10px; border-radius:var(--radius-sm); border:1px solid var(--border-color);">
                                    <div style="color:var(--text-muted); font-size:10px;">RIF Fiscal & Estatus</div>
                                    <div style="display:flex; align-items:center; gap:6px; margin-top:2px;">
                                        <b style="color:var(--text-primary);">${m.rif}</b>
                                        <span class="badge-verified ${m.rif_verified == 1 ? 'neon-glow-emerald' : 'neon-glow-amber'}" style="font-size:9px;">
                                            ${m.rif_verified == 1 ? '✓ Verificado' : '⏳ Pendiente'}
                                        </span>
                                    </div>
                                </div>
                                <div style="background:var(--bg-primary); padding:8px 10px; border-radius:var(--radius-sm); border:1px solid var(--border-color);">
                                    <div style="color:var(--text-muted); font-size:10px;">Categoría / Rubro</div>
                                    <b style="color:var(--caribbean-cyan);">${m.category}</b>
                                </div>
                                <div style="background:var(--bg-primary); padding:8px 10px; border-radius:var(--radius-sm); border:1px solid var(--border-color);">
                                    <div style="color:var(--text-muted); font-size:10px;">Plus Code de Google Maps</div>
                                    <div style="display:flex; align-items:center; gap:4px; margin-top:2px;">
                                        <i class="fas fa-map-pin" style="color:#E76F51;"></i>
                                        <b style="color:#06D6A0;">${m.plus_code || 'No asignado'}</b>
                                    </div>
                                </div>
                                <div style="background:var(--bg-primary); padding:8px 10px; border-radius:var(--radius-sm); border:1px solid var(--border-color);">
                                    <div style="color:var(--text-muted); font-size:10px;">Coordenadas GPS (Lat / Lng)</div>
                                    <span style="font-family:monospace; color:var(--text-primary); font-size:11px;">${parseFloat(m.lat || 0).toFixed(6)}, ${parseFloat(m.lng || 0).toFixed(6)}</span>
                                </div>
                                <div style="background:var(--bg-primary); padding:8px 10px; border-radius:var(--radius-sm); border:1px solid var(--border-color);">
                                    <div style="color:var(--text-muted); font-size:10px;">Reputación Detallada</div>
                                    <div style="font-size:11px; margin-top:2px;">
                                        Ventas: <b>★ ${parseFloat(m.sales_rep || 5).toFixed(1)}</b> | Servicio: <b>★ ${parseFloat(m.service_rep || 5).toFixed(1)}</b>
                                    </div>
                                </div>
                            </div>

                            <!-- Datos de Pago Móvil -->
                            <div style="margin-top:10px; padding-top:10px; border-top:1px solid rgba(255,255,255,0.08);">
                                <div style="font-size:11px; font-weight:800; color:var(--text-secondary); margin-bottom:6px;">
                                    <i class="fas fa-mobile-alt" style="color:var(--caribbean-cyan);"></i> Datos de Pago Móvil Receptor Registrados
                                </div>
                                <div style="display:flex; flex-wrap:wrap; gap:10px; font-size:11px; color:var(--text-muted);">
                                    <span>Banco: <b style="color:var(--text-primary);">${m.pago_movil_bank || 'No configurado'}</b></span>
                                    <span>Teléfono: <b style="color:var(--text-primary);">${m.pago_movil_phone || 'N/A'}</b></span>
                                    <span>Cédula/RIF: <b style="color:var(--text-primary);">${m.pago_movil_ci || 'N/A'}</b></span>
                                    <span>Titular: <b style="color:var(--text-primary);">${m.pago_movil_name || 'N/A'}</b></span>
                                </div>
                            </div>

                            ${this.currentUser && this.currentUser.role === 'superadmin' ? `
                            <!-- Control Superusuario: Asignación de Plus Code Oficial -->
                            <div style="margin-top:10px; padding:10px 12px; background:rgba(0,180,216,0.06); border:1px dashed var(--neon-cyan); border-radius:var(--radius-sm);">
                                <div style="font-size:11px; font-weight:800; color:var(--neon-cyan); margin-bottom:6px; display:flex; justify-content:space-between;">
                                    <span><i class="fas fa-satellite-dish"></i> Asignar / Actualizar Plus Code Oficial (Superusuario):</span>
                                    <span style="font-size:10px; color:var(--text-muted);">Ej: 769HJ344+J5 o J344+8F</span>
                                </div>
                                <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                                    <input type="text" id="admin-profile-plus-${m.id}" class="form-control" placeholder="Ej: 769HJ344+J5" value="${m.plus_code || ''}" style="flex:1; min-width:180px; font-family:monospace; font-weight:700; font-size:12px; padding:6px 10px; text-transform:uppercase;">
                                    <button class="btn-primary" style="padding:6px 14px; font-size:11px; white-space:nowrap;" onclick="PaseoMacutoUI.adminAssignPlusCodeFromProfile(${m.id})">
                                        <i class="fas fa-map-marker-alt"></i> Guardar Plus Code y Publicar en GIS
                                    </button>
                                </div>
                            </div>
                            ` : ''}

                            <!-- Menú / Catálogo de Productos -->
                            <div style="margin-top:12px; padding-top:10px; border-top:1px solid rgba(255,255,255,0.08);">
                                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                                    <div style="font-size:12px; font-weight:800; color:var(--text-primary);">
                                        <i class="fas fa-boxes" style="color:var(--neon-cyan);"></i> Menú y Catálogo Cargado (${prods.length} productos)
                                    </div>
                                </div>
                                ${prods.length === 0 
                                    ? '<div style="font-size:11px; color:var(--text-muted);">Este comercio aún no ha cargado productos en su catálogo.</div>'
                                    : `<div style="display:flex; flex-direction:column; gap:6px; max-height:160px; overflow-y:auto;">
                                        ${prods.map(p => `
                                            <div style="background:var(--bg-primary); border:1px solid var(--border-color); border-radius:var(--radius-sm); padding:6px 10px; display:flex; justify-content:space-between; align-items:center; font-size:11px;">
                                                <div>
                                                    <b style="color:var(--text-primary);">${p.name}</b>
                                                    ${p.description ? `<div style="font-size:10px; color:var(--text-muted);">${p.description}</div>` : ''}
                                                </div>
                                                <div style="text-align:right;">
                                                    <b style="color:#06D6A0;">$${p.price_usd.toFixed(2)}</b>
                                                    <span style="color:var(--text-muted); font-size:10px;">(${p.price_bs.toFixed(2)} Bs.)</span>
                                                </div>
                                            </div>
                                        `).join('')}
                                    </div>`
                                }
                            </div>
                        </div>
                    `;
                } else {
                    // Para Visitante o Superadmin
                    extraDetailsContainer.innerHTML = `
                        <div class="neon-card cyan" style="margin-bottom:14px; padding:12px 14px;">
                            <div class="neon-card-title" style="margin-bottom:8px; font-size:12px; color:var(--neon-cyan);">
                                <i class="fas fa-user-shield"></i> Resumen de Cuenta del Usuario
                            </div>
                            <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(180px, 1fr)); gap:8px; font-size:11px;">
                                <div>ID de Usuario: <b style="color:var(--text-primary);">#${u.id}</b></div>
                                <div>Correo: <b style="color:var(--text-primary);">${u.email}</b></div>
                                <div>Teléfono: <b style="color:var(--text-primary);">${u.phone || 'No registrado'}</b></div>
                                <div>Rol en Sistema: <b style="color:var(--caribbean-cyan); text-transform:uppercase;">${u.role}</b></div>
                                <div>Fecha de Creación: <b style="color:var(--text-primary);">${u.created_at || 'N/A'}</b></div>
                                <div>Estatus de Cuenta: <b style="color:#06D6A0;">Activo y Operativo</b></div>
                            </div>
                        </div>
                    `;
                }
            }

            // 5. Renderizado de la Línea de Tiempo del Log Histórico
            if (historyBadge) historyBadge.innerText = `${history.length} eventos`;

            if (timelineList) {
                if (history.length === 0) {
                    timelineList.innerHTML = `<div style="text-align:center; padding:20px; color:var(--text-muted); font-size:12px;">Aún no se registran interacciones en esta cuenta.</div>`;
                } else {
                    timelineList.innerHTML = history.map(item => `
                        <div class="history-timeline-item">
                            <div class="history-timeline-bullet" style="background:${item.color === 'emerald' ? '#06D6A0' : (item.color === 'amber' ? '#F59E0B' : (item.color === 'purple' ? '#8B5CF6' : 'var(--caribbean-cyan)'))};"></div>
                            <div class="history-item-header">
                                <div class="history-item-title">
                                    <i class="${item.icon}" style="color:${item.color === 'emerald' ? '#06D6A0' : (item.color === 'amber' ? '#F59E0B' : (item.color === 'purple' ? '#8B5CF6' : 'var(--caribbean-cyan)'))};"></i>
                                    <span>${item.title}</span>
                                </div>
                                <span class="history-item-badge">${item.badge}</span>
                            </div>
                            <div class="history-item-desc">${item.description}</div>
                            <div class="history-item-date"><i class="far fa-clock"></i> ${item.time_formatted}</div>
                        </div>
                    `).join('');
                }
            }

        } catch (e) {
            console.error("Error al cargar expediente e historial:", e);
            if (kpisContainer) kpisContainer.innerHTML = `<div style="color:var(--coral-alert); padding:10px;">Error de conexión al cargar la bitácora histórica.</div>`;
        }
    },

    async openModuleView(moduleKey) {
        const userRole = this.currentUser ? this.currentUser.role : 'guest';

        // Validación estricta antes de abrir modal o consultar API
        if (moduleKey.startsWith('admin_') && userRole !== 'superadmin') {
            alert("Acceso restringido: Este módulo de gestión central es exclusivo para el Superusuario.");
            return;
        }

        if (moduleKey.startsWith('merchant_') && userRole !== 'merchant' && userRole !== 'superadmin') {
            alert("Acceso restringido: Este módulo es exclusivo para comerciantes del Paseo Macuto.");
            return;
        }

        this.toggleSidebar(false); // Colapsar el panel lateral
        const modal = document.getElementById('modal-module-view');
        const titleEl = document.getElementById('module-view-title');
        const badgeEl = document.getElementById('module-view-badge');
        const container = document.getElementById('module-view-content');

        if (!modal || !container) return;

        container.innerHTML = `
            <div style="text-align:center; padding:30px; color:var(--caribbean-cyan);">
                <i class="fas fa-spinner fa-spin" style="font-size:28px;"></i>
                <p style="margin-top:10px; font-weight:700;">Cargando módulo con iluminación neón...</p>
            </div>
        `;
        this.openModal('modal-module-view');

        try {
            const res = await fetch(`api/index.php?action=get_module_data&module=${encodeURIComponent(moduleKey)}`);
            const data = await res.json();

            if (!data.success) {
                container.innerHTML = `
                    <div class="neon-card amber" style="text-align:center; padding:20px;">
                        <i class="fas fa-exclamation-triangle" style="font-size:26px; color:var(--neon-amber); margin-bottom:8px;"></i>
                        <h4 style="color:var(--text-primary);">${data.message || 'No tienes permisos para este módulo'}</h4>
                        <p style="font-size:12px; color:var(--text-secondary); margin-top:6px;">Inicia sesión con la cuenta autorizada correspondiente.</p>
                    </div>
                `;
                return;
            }

            this.renderModuleContent(moduleKey, data, titleEl, badgeEl, container);
        } catch (err) {
            container.innerHTML = `<div style="text-align:center; padding:20px; color:var(--coral-alert);">Error al cargar datos del módulo.</div>`;
        }
    },

    renderModuleContent(moduleKey, res, titleEl, badgeEl, container) {
        const d = res.data;
        const bcv = res.bcv_rate || this.bcvRate;

        switch (moduleKey) {
            // --- SUPERUSUARIO ---
            case 'admin_users':
                titleEl.innerText = 'Directorio de Usuarios Registrados';
                badgeEl.innerText = 'Superusuario';
                badgeEl.className = 'badge-verified neon-glow-cyan';
                window._cachedAdminUsers = d;
                container.innerHTML = `
                    <div class="neon-card cyan">
                        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; margin-bottom:12px;">
                            <div class="neon-card-title" style="margin:0;"><i class="fas fa-users"></i> Total Registrados: ${d.length}</div>
                            <div style="position:relative; min-width:220px; flex:1; max-width:320px;">
                                <input type="text" id="admin-user-search-input" class="form-control" placeholder="Buscar por nombre, correo, rol..." style="padding:6px 12px 6px 30px; font-size:12px;" oninput="PaseoMacutoUI.filterAdminUsersList(this.value)">
                                <i class="fas fa-search" style="position:absolute; left:10px; top:50%; transform:translateY(-50%); font-size:11px; color:var(--text-muted);"></i>
                            </div>
                        </div>
                        <div style="font-size:11px; color:var(--text-secondary); margin-bottom:10px; background:rgba(0,180,216,0.08); padding:6px 10px; border-radius:var(--radius-sm); border:1px dashed rgba(0,180,216,0.3);">
                            <i class="fas fa-info-circle" style="color:var(--caribbean-cyan);"></i> <b>Haz clic en cualquier usuario</b> para consultar su expediente íntegro: datos personales, RIF, Plus Code, menú de productos cargados, datos de Pago Móvil y bitácora histórica.
                        </div>
                        <div id="admin-users-items-list" style="display:flex; flex-direction:column; gap:8px; max-height:60vh; overflow-y:auto;">
                            ${d.map(u => `
                                <div class="admin-user-list-card" onclick="PaseoMacutoUI.openUserProfileAndHistory(${u.id})" style="background:var(--bg-primary); border:1px solid var(--border-color); border-radius:var(--radius-sm); padding:10px 14px; display:flex; justify-content:space-between; align-items:center; cursor:pointer; transition:all 0.2s ease;" onmouseover="this.style.borderColor='var(--neon-cyan)'; this.style.boxShadow='0 0 12px rgba(0, 180, 216, 0.25)';" onmouseout="this.style.borderColor='var(--border-color)'; this.style.boxShadow='none';">
                                    <div style="display:flex; align-items:center; gap:12px;">
                                        <div style="width:40px; height:40px; border-radius:50%; background:rgba(0,180,216,0.1); border:1px solid ${u.role === 'merchant' ? '#06D6A0' : (u.role === 'superadmin' ? '#8B5CF6' : 'var(--caribbean-cyan)')}; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                                            <i class="${u.role === 'merchant' ? 'fas fa-store' : (u.role === 'superadmin' ? 'fas fa-crown' : 'fas fa-user')}" style="color:${u.role === 'merchant' ? '#06D6A0' : (u.role === 'superadmin' ? '#8B5CF6' : 'var(--caribbean-cyan)')}; font-size:16px;"></i>
                                        </div>
                                        <div>
                                            <div style="display:flex; align-items:center; gap:6px;">
                                                <b style="color:var(--text-primary); font-size:13px;">${u.name}</b>
                                                <span style="font-size:10px; color:var(--text-muted); font-weight:700;">#${u.id}</span>
                                            </div>
                                            <div style="font-size:11px; color:var(--text-muted);">${u.email} ${u.phone ? '• Tel: ' + u.phone : ''}</div>
                                            <div style="font-size:10px; color:var(--caribbean-cyan); margin-top:2px;">
                                                <i class="far fa-clock"></i> Creado: ${u.created_at ? u.created_at.substring(0, 10) : 'N/A'}
                                            </div>
                                        </div>
                                    </div>
                                    <div style="text-align:right; display:flex; flex-direction:column; align-items:flex-end; gap:4px;">
                                        <span class="badge-verified ${u.role === 'merchant' ? 'neon-glow-emerald' : (u.role === 'superadmin' ? 'neon-glow-purple' : 'neon-glow-cyan')}" style="font-size:9px; text-transform:uppercase;">${u.role}</span>
                                        <div style="font-size:11px; color:var(--text-secondary); font-weight:700;">Nivel ${u.level} • ${u.xp} XP</div>
                                        <button class="btn-primary" style="padding:3px 10px; font-size:10px; border-radius:10px; margin-top:2px;" onclick="event.stopPropagation(); PaseoMacutoUI.openUserProfileAndHistory(${u.id});">
                                            <i class="fas fa-search-plus"></i> Ver Todo el Detalle
                                        </button>
                                    </div>
                                </div>
                            `).join('')}
                        </div>
                    </div>
                `;
                break;

            case 'admin_products':
                titleEl.innerText = 'Catálogo Global de Productos y Precios';
                badgeEl.innerText = 'Superusuario';
                badgeEl.className = 'badge-verified neon-glow-cyan';
                container.innerHTML = `
                    <div class="neon-card cyan">
                        <div class="neon-card-title"><i class="fas fa-boxes"></i> Inventario de los 180 Comerciantes (${d.length} artículos)</div>
                        <div style="display:flex; flex-direction:column; gap:8px; max-height:60vh; overflow-y:auto;">
                            ${d.map(p => `
                                <div style="background:var(--bg-primary); border:1px solid var(--border-color); border-radius:var(--radius-sm); padding:10px 12px; display:flex; justify-content:space-between; align-items:center;">
                                    <div>
                                        <b style="color:var(--text-primary);">${p.name}</b>
                                        <div style="font-size:11px; color:var(--caribbean-cyan); font-weight:600;">${p.commercial_name} • ${p.sector}</div>
                                        <div style="font-size:11px; color:var(--text-muted);">${p.description || ''}</div>
                                    </div>
                                    <div style="text-align:right;">
                                        <div style="font-size:14px; font-weight:900; color:#06D6A0;">$${p.price_usd.toFixed(2)}</div>
                                        <div style="font-size:11px; color:var(--text-muted);">${p.price_bs.toFixed(2)} Bs.</div>
                                    </div>
                                </div>
                            `).join('')}
                        </div>
                    </div>
                `;
                break;

            case 'admin_incidents':
            case 'admin_denuncias':
                titleEl.innerText = 'Centro de Incidencias y Denuncias';
                badgeEl.innerText = 'Superusuario';
                badgeEl.className = 'badge-pending neon-glow-amber';
                container.innerHTML = `
                    <div class="neon-card amber">
                        <div class="neon-card-title"><i class="fas fa-shield-alt"></i> Reclamaciones Ciudadanas Recibidas (${d.length})</div>
                        <div style="display:flex; flex-direction:column; gap:8px; max-height:60vh; overflow-y:auto;">
                            ${d.length === 0 ? '<div style="text-align:center; padding:16px; color:var(--text-muted);">Sin incidencias registradas.</div>' : ''}
                            ${d.map(c => `
                                <div style="background:var(--bg-primary); border:1px solid var(--border-color); border-radius:var(--radius-sm); padding:12px; display:flex; flex-direction:column; gap:6px;">
                                    <div style="display:flex; justify-content:space-between; font-size:12px;">
                                        <b style="color:var(--coral-alert);"><i class="fas fa-exclamation-triangle"></i> Local: ${c.commercial_name} (RIF: ${c.rif})</b>
                                        <span class="badge-pending">${c.status}</span>
                                    </div>
                                    <div style="font-size:13px; background:var(--bg-surface); padding:8px; border-radius:6px; border-left:3px solid var(--coral-alert);">
                                        "${c.description}"
                                    </div>
                                    <div style="font-size:11px; color:var(--text-muted); display:flex; justify-content:space-between;">
                                        <span>Denunciante: ${c.visitor_name} (${c.visitor_email})</span>
                                        <span>${c.created_at}</span>
                                    </div>
                                    <div style="display:flex; gap:6px; margin-top:4px;">
                                        <button class="btn-danger" style="padding:6px 10px;" onclick="PaseoMacutoUI.resolveComplaint(${c.id}, 'penalized')">
                                            <i class="fas fa-gavel"></i> Penalizar (-0.5 Rep)
                                        </button>
                                        <button class="btn-secondary" style="padding:6px 10px; font-size:12px;" onclick="PaseoMacutoUI.resolveComplaint(${c.id}, 'resolved')">
                                            <i class="fas fa-check"></i> Resolver
                                        </button>
                                    </div>
                                </div>
                            `).join('')}
                        </div>
                    </div>
                `;
                break;

            case 'admin_purchases':
                titleEl.innerText = 'Compras Efectivas y Transacciones';
                badgeEl.innerText = 'Superusuario';
                badgeEl.className = 'badge-verified neon-glow-emerald';
                container.innerHTML = `
                    <div class="neon-card emerald">
                        <div class="neon-card-title"><i class="fas fa-receipt"></i> Historial de Ventas Completadas (${d.length})</div>
                        <div style="display:flex; flex-direction:column; gap:8px; max-height:60vh; overflow-y:auto;">
                            ${d.map(o => `
                                <div style="background:var(--bg-primary); border:1px solid var(--border-color); border-radius:var(--radius-sm); padding:10px 12px; display:flex; justify-content:space-between; align-items:center;">
                                    <div>
                                        <b style="color:var(--text-primary);">${o.product_name || 'Servicio Playero'}</b>
                                        <div style="font-size:11px; color:var(--text-secondary);">${o.commercial_name} • Comprador: ${o.visitor_name}</div>
                                        <div style="font-size:10px; color:var(--text-muted);">${o.created_at}</div>
                                    </div>
                                    <div style="text-align:right;">
                                        <div style="font-size:14px; font-weight:800; color:#06D6A0;">$${parseFloat(o.amount_usd).toFixed(2)}</div>
                                        <div style="font-size:11px; color:var(--text-muted);">${parseFloat(o.amount_bs).toFixed(2)} Bs.</div>
                                    </div>
                                </div>
                            `).join('')}
                        </div>
                    </div>
                `;
                break;

            case 'admin_reviews':
                titleEl.innerText = 'Comentarios y Valoraciones';
                badgeEl.innerText = 'Superusuario';
                badgeEl.className = 'badge-verified neon-glow-cyan';
                container.innerHTML = `
                    <div class="neon-card cyan">
                        <div class="neon-card-title"><i class="fas fa-comments"></i> Reseñas Verificadas de Visitantes (${d.length})</div>
                        <div style="display:flex; flex-direction:column; gap:8px; max-height:60vh; overflow-y:auto;">
                            ${d.map(r => `
                                <div style="background:var(--bg-primary); border:1px solid var(--border-color); border-radius:var(--radius-sm); padding:10px 12px; display:flex; flex-direction:column; gap:4px;">
                                    <div style="display:flex; justify-content:space-between; font-size:12px;">
                                        <b>${r.visitor_name} en <span style="color:var(--caribbean-cyan);">${r.commercial_name}</span></b>
                                        <span class="reputation-stars-pill"><i class="fas fa-star"></i> ${r.rating}.0</span>
                                    </div>
                                    <div style="font-size:12px; color:var(--text-secondary);">"${r.comment || 'Excelente atención'}"</div>
                                </div>
                            `).join('')}
                        </div>
                    </div>
                `;
                break;

            case 'admin_reputations':
                titleEl.innerText = 'Ranking y Reputaciones de Comerciantes';
                badgeEl.innerText = 'Superusuario';
                badgeEl.className = 'badge-verified neon-glow-purple';
                container.innerHTML = `
                    <div class="neon-card purple">
                        <div class="neon-card-title"><i class="fas fa-trophy"></i> Clasificación de los 180 Puestos del Paseo</div>
                        <div style="display:flex; flex-direction:column; gap:8px; max-height:60vh; overflow-y:auto;">
                            ${d.map((m, idx) => `
                                <div style="background:var(--bg-primary); border:1px solid var(--border-color); border-radius:var(--radius-sm); padding:10px 14px; display:flex; justify-content:space-between; align-items:center;">
                                    <div style="display:flex; align-items:center; gap:10px;">
                                        <span style="font-size:14px; font-weight:900; color:var(--caribbean-cyan); width:24px;">#${idx + 1}</span>
                                        <div>
                                            <b style="color:var(--text-primary);">${m.commercial_name}</b>
                                            <div style="font-size:11px; color:var(--text-muted);">${m.category} • ${m.sector}</div>
                                        </div>
                                    </div>
                                    <div style="text-align:right;">
                                        <span class="reputation-stars-pill" style="font-size:14px;"><i class="fas fa-star"></i> ${parseFloat(m.total_rep).toFixed(1)}</span>
                                        <div style="font-size:10px; color:var(--text-muted);">Ventas: ${parseFloat(m.sales_rep).toFixed(1)} | Calidad: ${parseFloat(m.service_rep).toFixed(1)}</div>
                                    </div>
                                </div>
                            `).join('')}
                        </div>
                    </div>
                `;
                break;

            // --- VISITANTE ---
            case 'visitor_products':
                titleEl.innerText = 'Explorador de Productos Playeros';
                badgeEl.innerText = 'Visitante';
                badgeEl.className = 'badge-verified neon-glow-cyan';
                container.innerHTML = `
                    <div class="neon-card cyan">
                        <div class="neon-card-title"><i class="fas fa-utensils"></i> Delicias y Compras en Paseo Macuto</div>
                        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:10px; max-height:60vh; overflow-y:auto;">
                            ${d.map(p => `
                                <div style="background:var(--bg-primary); border:1px solid var(--border-color); border-radius:var(--radius-md); padding:12px; display:flex; flex-direction:column; justify-content:space-between; gap:6px;">
                                    <div>
                                        <b style="color:var(--text-primary); font-size:14px;">${p.name}</b>
                                        <div style="font-size:11px; color:var(--caribbean-cyan); font-weight:700;">${p.commercial_name}</div>
                                        <div style="font-size:11px; color:var(--text-secondary); margin-top:2px;">${p.description || ''}</div>
                                    </div>
                                    <div style="display:flex; justify-content:space-between; align-items:center; margin-top:6px; border-top:1px solid var(--border-color); padding-top:6px;">
                                        <div>
                                            <div style="font-size:14px; font-weight:900; color:#06D6A0;">$${p.price_usd.toFixed(2)}</div>
                                            <div style="font-size:10px; color:var(--text-muted);">${p.price_bs.toFixed(2)} Bs.</div>
                                        </div>
                                        <button class="btn-primary neon-glow-cyan" style="padding:6px 12px; font-size:11px;" onclick='if(CartAndPagoMovil.addToCart(${JSON.stringify(p)}, {id: ${p.merchant_id}, commercial_name: "${p.commercial_name}"})) { PaseoMacutoUI.closeModal("modal-module-view"); CartAndPagoMovil.openCartModal(); }'>
                                            <i class="fas fa-cart-plus"></i> Comprar
                                        </button>
                                    </div>
                                </div>
                            `).join('')}
                        </div>
                    </div>
                `;
                break;

            case 'visitor_services':
                titleEl.innerText = 'Servicios Turísticos y Actividades';
                badgeEl.innerText = 'Visitante';
                badgeEl.className = 'badge-verified neon-glow-emerald';
                container.innerHTML = `
                    <div class="neon-card emerald">
                        <div class="neon-card-title"><i class="fas fa-umbrella-beach"></i> Toldos, Peñeros, Kayak, Hospedaje y Paseos</div>
                        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:10px; max-height:60vh; overflow-y:auto;">
                            ${d.map(s => `
                                <div style="background:var(--bg-primary); border:1px solid var(--border-color); border-radius:var(--radius-md); padding:12px; display:flex; flex-direction:column; justify-content:space-between; gap:6px;">
                                    <div>
                                        <b style="color:var(--text-primary); font-size:14px;">${s.name}</b>
                                        <div style="font-size:11px; color:var(--sea-deep); font-weight:700;">${s.commercial_name} • ${s.sector}</div>
                                        <div style="font-size:11px; color:var(--text-secondary); margin-top:2px;">${s.description || ''}</div>
                                    </div>
                                    <div style="display:flex; justify-content:space-between; align-items:center; margin-top:6px; border-top:1px solid var(--border-color); padding-top:6px;">
                                        <div>
                                            <div style="font-size:14px; font-weight:900; color:#0A84FF;">$${s.price_usd.toFixed(2)}</div>
                                            <div style="font-size:10px; color:var(--text-muted);">${s.price_bs.toFixed(2)} Bs.</div>
                                        </div>
                                        <button class="btn-primary neon-glow-emerald" style="padding:6px 12px; font-size:11px;" onclick='if(CartAndPagoMovil.addToCart(${JSON.stringify(s)}, {id: ${s.merchant_id}, commercial_name: "${s.commercial_name}"})) { PaseoMacutoUI.closeModal("modal-module-view"); CartAndPagoMovil.openCartModal(); }'>
                                            <i class="fas fa-calendar-check"></i> Reservar
                                        </button>
                                    </div>
                                </div>
                            `).join('')}
                        </div>
                    </div>
                `;
                break;

            case 'visitor_complaints':
                titleEl.innerText = 'Estatus de Mis Quejas Realizadas';
                badgeEl.innerText = 'Visitante';
                badgeEl.className = 'badge-pending neon-glow-amber';
                container.innerHTML = `
                    <div class="neon-card amber">
                        <div class="neon-card-title"><i class="fas fa-clipboard-check"></i> Mis Reportes Enviados (${d.length})</div>
                        <div style="display:flex; flex-direction:column; gap:8px;">
                            ${d.length === 0 ? '<div style="text-align:center; padding:16px; color:var(--text-muted);">No has realizado ninguna queja. ¡Todo en orden!</div>' : ''}
                            ${d.map(c => `
                                <div style="background:var(--bg-primary); border:1px solid var(--border-color); border-radius:var(--radius-sm); padding:12px; display:flex; flex-direction:column; gap:4px;">
                                    <div style="display:flex; justify-content:space-between; font-size:12px;">
                                        <b>Contra: ${c.commercial_name}</b>
                                        <span class="badge-pending" style="text-transform:uppercase;">${c.status}</span>
                                    </div>
                                    <div style="font-size:12px; color:var(--text-secondary);">"${c.description}"</div>
                                    <div style="font-size:10px; color:var(--text-muted);">${c.created_at}</div>
                                </div>
                            `).join('')}
                        </div>
                    </div>
                `;
                break;

            case 'visitor_purchases':
                titleEl.innerText = 'Mis Compras y Pedidos';
                badgeEl.innerText = 'Visitante';
                badgeEl.className = 'badge-verified neon-glow-cyan';

                window._cachedVisitorPurchases = d;

                window.switchVisitorPurchasesTab = function(tabName) {
                    const tabs = ['pending', 'completed', 'denied'];
                    tabs.forEach(t => {
                        const btn = document.getElementById(`tab-btn-vp-${t}`);
                        const view = document.getElementById(`tab-content-vp-${t}`);
                        if (btn) {
                            if (t === tabName) {
                                btn.style.background = (t === 'pending') ? 'var(--neon-amber)' : (t === 'completed' ? 'var(--neon-emerald)' : 'var(--coral-alert)');
                                btn.style.color = '#fff';
                                btn.style.boxShadow = (t === 'pending') ? '0 0 10px rgba(255, 183, 3, 0.4)' : (t === 'completed' ? '0 0 10px rgba(6, 214, 160, 0.4)' : '0 0 10px rgba(239, 71, 111, 0.4)');
                            } else {
                                btn.style.background = 'transparent';
                                btn.style.color = 'var(--text-secondary)';
                                btn.style.boxShadow = 'none';
                            }
                        }
                        if (view) {
                            view.style.display = (t === tabName) ? 'flex' : 'none';
                        }
                    });
                };

                const renderOrderCard = (o, type) => {
                    const items = o.items || [];
                    const itemsHtml = items.map(it => `
                        <div style="display:flex; justify-content:space-between; font-size:12px; color:var(--text-secondary); padding:2px 0;">
                            <span>• <b>${it.name || 'Producto'}</b> (x${it.qty || 1})</span>
                            <span style="font-weight:700; color:var(--text-primary);">$${((it.price_usd || 0) * (it.qty || 1)).toFixed(2)}</span>
                        </div>
                    `).join('');

                    let statusBadge = '';
                    let actionButtons = '';

                    if (type === 'pending') {
                        statusBadge = `
                            <span class="badge-pending neon-glow-amber" style="font-size:11px; padding:4px 10px;">
                                <i class="fas fa-clock fa-spin"></i> Esperando Verificación del Vendedor
                            </span>
                        `;
                        actionButtons = `
                            <div style="font-size:11px; color:var(--neon-amber); background:rgba(255,183,3,0.08); padding:8px 12px; border-radius:var(--radius-sm); border:1px dashed rgba(255,183,3,0.3); margin-top:8px;">
                                <i class="fas fa-info-circle"></i> El vendedor titular de este Plus Code está verificando el pago en su cuenta bancaria. Recibirás un aviso emergente en cuanto sea acreditado.
                            </div>
                        `;
                    } else if (type === 'completed') {
                        statusBadge = `
                            <span class="badge-verified neon-glow-emerald" style="font-size:11px; padding:4px 10px;">
                                <i class="fas fa-check-circle"></i> Pago Verificado y Efectivo
                            </span>
                        `;
                        actionButtons = `
                            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px; margin-top:8px; padding-top:8px; border-top:1px solid var(--border-color);">
                                <div style="font-size:11px; color:#06D6A0; font-weight:700;">
                                    <i class="fas fa-star"></i> ¡Venta consolidada! Ganaste +25 XP
                                </div>
                                <div>
                                    ${o.has_review ? `
                                        <span style="font-size:11px; color:var(--neon-amber); font-weight:700;">
                                            <i class="fas fa-star"></i> Ya calificaste este local (${o.review_rating}★)
                                        </span>
                                    ` : `
                                        <button class="btn-primary neon-glow-cyan" style="padding:4px 12px; font-size:11px; border-radius:12px;" onclick="PaseoMacutoUI.closeModal('modal-module-view'); PaseoMacutoUI.openReviewModal(${o.merchant_id}, '${escape(o.commercial_name)}');">
                                            <i class="fas fa-star"></i> Calificar Comercio
                                        </button>
                                    `}
                                </div>
                            </div>
                        `;
                    } else {
                        statusBadge = `
                            <span class="badge-danger neon-glow-coral" style="font-size:11px; padding:4px 10px; background:rgba(239, 71, 111, 0.15); color:var(--coral-alert); border:1px solid var(--coral-alert);">
                                <i class="fas fa-times-circle"></i> Pago No Acreditado / Rechazado
                            </span>
                        `;
                        actionButtons = `
                            <div style="font-size:11px; color:var(--coral-alert); background:rgba(239,71,111,0.08); padding:8px 12px; border-radius:var(--radius-sm); border:1px dashed rgba(239,71,111,0.3); margin-top:8px;">
                                <b>Motivo de rechazo:</b> ${o.denied_reason || 'El comprobante no concilia con la cuenta bancaria.'}
                            </div>
                        `;
                    }

                    return `
                        <div class="neon-card ${type === 'pending' ? 'amber' : (type === 'completed' ? 'emerald' : 'cyan')}" style="padding:14px; margin-bottom:10px;">
                            <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:8px; border-bottom:1px solid var(--border-color); padding-bottom:8px;">
                                <div>
                                    <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                                        <b style="font-size:15px; color:var(--text-primary);">${o.commercial_name}</b>
                                        <span style="font-family:monospace; color:var(--neon-cyan); background:rgba(0,180,216,0.12); padding:2px 6px; border-radius:4px; font-size:11px; border:1px solid rgba(0,180,216,0.3);">
                                            <i class="fas fa-map-pin"></i> Plus Code: <b>${o.plus_code || 'Macuto'}</b>
                                        </span>
                                    </div>
                                    <div style="font-size:11px; color:var(--text-muted); margin-top:2px;">
                                        Orden #${o.id} • ${o.created_at || 'Fecha reciente'} • Sector: ${o.sector || 'Paseo Macuto'}
                                    </div>
                                </div>
                                <div>
                                    ${statusBadge}
                                </div>
                            </div>

                            <!-- Lista de Productos -->
                            <div style="margin:10px 0; background:rgba(0,0,0,0.03); padding:8px 10px; border-radius:var(--radius-sm);">
                                <span style="font-size:11px; font-weight:700; text-transform:uppercase; color:var(--text-muted); display:block; margin-bottom:4px;">
                                    <i class="fas fa-shopping-bag"></i> Artículos del Pedido:
                                </span>
                                ${itemsHtml || '<div style="font-size:11px; color:var(--text-muted);">Consumo en establecimiento</div>'}
                            </div>

                            <!-- Datos de Pago Móvil -->
                            <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(160px, 1fr)); gap:8px; background:var(--bg-primary); padding:8px 10px; border-radius:var(--radius-sm); border:1px solid var(--border-color);">
                                <div>
                                    <span style="font-size:10px; color:var(--text-muted); display:block;">Total Pagado:</span>
                                    <b style="font-size:14px; color:#06D6A0;">$${parseFloat(o.amount_usd).toFixed(2)} USD</b>
                                    <span style="font-size:11px; color:var(--text-secondary);"> (${parseFloat(o.amount_bs).toFixed(2)} Bs.)</span>
                                </div>
                                <div>
                                    <span style="font-size:10px; color:var(--text-muted); display:block;">Banco Emisor:</span>
                                    <b style="font-size:11px; color:var(--text-primary);">${o.sender_bank || 'Pago Móvil'}</b>
                                </div>
                                <div>
                                    <span style="font-size:10px; color:var(--text-muted); display:block;">Referencia Bancaria:</span>
                                    <b style="font-size:11px; font-family:monospace; color:var(--caribbean-cyan);">${o.payment_reference || 'N/A'}</b>
                                </div>
                                <div>
                                    <span style="font-size:10px; color:var(--text-muted); display:block;">Comprobante:</span>
                                    ${o.proof_image ? `
                                        <button class="btn-secondary" style="padding:2px 8px; font-size:10px;" onclick="window.open('${o.proof_image}', '_blank')">
                                            <i class="fas fa-image"></i> Ver Captura
                                        </button>
                                    ` : '<span style="font-size:10px; color:var(--text-muted);">Sin captura</span>'}
                                </div>
                            </div>

                            ${actionButtons}
                        </div>
                    `;
                };

                container.innerHTML = `
                    <div style="display:flex; flex-direction:column; gap:14px;">
                        <!-- Resumen y Pestañas Neón -->
                        <div style="display:flex; gap:8px; border-bottom:1px solid var(--border-color); padding-bottom:10px; overflow-x:auto;">
                            <button id="tab-btn-vp-pending" onclick="window.switchVisitorPurchasesTab('pending')" style="flex:1; padding:10px 14px; border-radius:var(--radius-md); border:1px solid rgba(255,183,3,0.5); background:var(--neon-amber); color:#fff; font-weight:800; font-size:12px; cursor:pointer; display:flex; align-items:center; justify-content:center; gap:6px; box-shadow:0 0 10px rgba(255, 183, 3, 0.4); transition:all 0.2s ease;">
                                <i class="fas fa-clock"></i> Por Verificar (${d.counts.pending})
                            </button>
                            <button id="tab-btn-vp-completed" onclick="window.switchVisitorPurchasesTab('completed')" style="flex:1; padding:10px 14px; border-radius:var(--radius-md); border:1px solid rgba(6,214,160,0.5); background:transparent; color:var(--text-secondary); font-weight:800; font-size:12px; cursor:pointer; display:flex; align-items:center; justify-content:center; gap:6px; transition:all 0.2s ease;">
                                <i class="fas fa-check-circle"></i> Verificadas (${d.counts.completed})
                            </button>
                            <button id="tab-btn-vp-denied" onclick="window.switchVisitorPurchasesTab('denied')" style="flex:1; padding:10px 14px; border-radius:var(--radius-md); border:1px solid rgba(239,71,111,0.5); background:transparent; color:var(--text-secondary); font-weight:800; font-size:12px; cursor:pointer; display:flex; align-items:center; justify-content:center; gap:6px; transition:all 0.2s ease;">
                                <i class="fas fa-times-circle"></i> Denegadas (${d.counts.denied})
                            </button>
                        </div>

                        <!-- Contenedor Pestaña 1: Por Verificar -->
                        <div id="tab-content-vp-pending" style="display:flex; flex-direction:column; gap:10px; max-height:60vh; overflow-y:auto;">
                            ${d.pending.length === 0 ? `
                                <div style="text-align:center; padding:35px 20px; color:var(--text-muted);">
                                    <i class="fas fa-hourglass-end" style="font-size:36px; opacity:0.4; margin-bottom:10px; color:var(--neon-amber);"></i>
                                    <h4 style="color:var(--text-primary); font-size:14px;">No tienes pagos en espera de verificación</h4>
                                    <p style="font-size:12px; margin-top:4px;">Cuando realices un Pago Móvil a cualquier comercio, podrás seguir aquí la confirmación del vendedor en tiempo real.</p>
                                </div>
                            ` : d.pending.map(o => renderOrderCard(o, 'pending')).join('')}
                        </div>

                        <!-- Contenedor Pestaña 2: Verificadas -->
                        <div id="tab-content-vp-completed" style="display:none; flex-direction:column; gap:10px; max-height:60vh; overflow-y:auto;">
                            ${d.completed.length === 0 ? `
                                <div style="text-align:center; padding:35px 20px; color:var(--text-muted);">
                                    <i class="fas fa-shopping-bag" style="font-size:36px; opacity:0.4; margin-bottom:10px; color:#06D6A0;"></i>
                                    <h4 style="color:var(--text-primary); font-size:14px;">Aún no tienes compras consolidadas</h4>
                                    <p style="font-size:12px; margin-top:4px;">Una vez que el comerciante verifique tu transferencia, tu compra aparecerá aquí con los puntos XP acreditados.</p>
                                </div>
                            ` : d.completed.map(o => renderOrderCard(o, 'completed')).join('')}
                        </div>

                        <!-- Contenedor Pestaña 3: Denegadas -->
                        <div id="tab-content-vp-denied" style="display:none; flex-direction:column; gap:10px; max-height:60vh; overflow-y:auto;">
                            ${d.denied.length === 0 ? `
                                <div style="text-align:center; padding:35px 20px; color:var(--text-muted);">
                                    <i class="fas fa-shield-alt" style="font-size:36px; opacity:0.4; margin-bottom:10px; color:#06D6A0;"></i>
                                    <h4 style="color:var(--text-primary); font-size:14px;">¡Excelente! No tienes pagos rechazados</h4>
                                    <p style="font-size:12px; margin-top:4px;">Todos tus comprobantes han sido conciliados exitosamente con los comercios.</p>
                                </div>
                            ` : d.denied.map(o => renderOrderCard(o, 'denied')).join('')}
                        </div>
                    </div>
                `;
                break;

            // --- COMERCIANTE ---
            case 'merchant_finances':
                titleEl.innerText = 'Ganancias y Balance de Pagos';
                badgeEl.innerText = 'Comerciante';
                badgeEl.className = 'badge-verified neon-glow-emerald';
                container.innerHTML = `
                    <div class="neon-card emerald">
                        <div class="neon-card-title"><i class="fas fa-wallet"></i> Balance de ${res.merchant_name}</div>
                        <div class="kpi-grid" style="margin:10px 0;">
                            <div class="kpi-card">
                                <span class="kpi-title">Ventas Brutas ($)</span>
                                <span class="kpi-value">$${d.total_usd.toFixed(2)}</span>
                            </div>
                            <div class="kpi-card">
                                <span class="kpi-title">Equivalente BCV</span>
                                <span class="kpi-value" style="font-size:16px;">${d.total_bs.toFixed(2)} Bs.</span>
                            </div>
                            <div class="kpi-card">
                                <span class="kpi-title">Órdenes Pagadas</span>
                                <span class="kpi-value">${d.count_orders}</span>
                            </div>
                            <div class="kpi-card">
                                <span class="kpi-title">Balance Disponible</span>
                                <span class="kpi-value" style="color:#06D6A0;">$${d.balance_usd.toFixed(2)}</span>
                            </div>
                        </div>
                        <div style="font-size:11px; color:var(--text-muted); text-align:center;">
                            Tasa de liquidación oficial: ${bcv.toFixed(2)} Bs./USD.
                        </div>
                    </div>
                `;
                break;

            case 'merchant_complaints_requests':
                titleEl.innerText = 'Denuncias y Solicitudes Recibidas';
                badgeEl.innerText = 'Comerciante';
                badgeEl.className = 'badge-pending neon-glow-amber';
                container.innerHTML = `
                    <div class="neon-card amber">
                        <div class="neon-card-title"><i class="fas fa-inbox"></i> Notificaciones de Calidad y Clientes (${d.length})</div>
                        <div style="display:flex; flex-direction:column; gap:8px;">
                            ${d.length === 0 ? '<div style="text-align:center; padding:16px; color:var(--text-muted);"><i class="fas fa-check-circle" style="color:#06D6A0;"></i> ¡Felicitaciones! No tienes denuncias activas en tu contra.</div>' : ''}
                            ${d.map(c => `
                                <div style="background:var(--bg-primary); border:1px solid var(--border-color); border-radius:var(--radius-sm); padding:10px 12px; display:flex; flex-direction:column; gap:4px;">
                                    <div style="display:flex; justify-content:space-between; font-size:12px;">
                                        <b style="color:var(--coral-alert);">Reporte de Cliente: ${c.visitor_name}</b>
                                        <span class="badge-pending">${c.status}</span>
                                    </div>
                                    <div style="font-size:12px; color:var(--text-secondary);">"${c.description}"</div>
                                    <div style="font-size:10px; color:var(--text-muted);">${c.created_at}</div>
                                </div>
                            `).join('')}
                        </div>
                    </div>
                `;
                break;

            default:
                container.innerHTML = `<div style="text-align:center; padding:20px;">Módulo cargado correctamente.</div>`;
                break;
        }
    },

    closeModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) modal.classList.remove('active');
    },

    closeAllModals() {
        document.querySelectorAll('.modal-overlay').forEach(m => m.classList.remove('active'));
    },

    toggleBottomSheet() {
        const sheet = document.getElementById('bottom-sheet');
        if (!sheet) return;

        if (sheet.classList.contains('minimized')) {
            sheet.classList.remove('minimized');
            sheet.classList.add('half');
        } else if (sheet.classList.contains('half')) {
            sheet.classList.remove('half');
            sheet.classList.add('expanded');
        } else {
            sheet.classList.remove('expanded');
            sheet.classList.add('minimized');
        }
    },

    filterAdminUsersList(query) {
        if (!window._cachedAdminUsers) return;
        const q = (query || '').toLowerCase().trim();
        const listContainer = document.getElementById('admin-users-items-list');
        if (!listContainer) return;

        const filtered = window._cachedAdminUsers.filter(u => 
            (u.name && u.name.toLowerCase().includes(q)) ||
            (u.email && u.email.toLowerCase().includes(q)) ||
            (u.role && u.role.toLowerCase().includes(q)) ||
            (u.phone && u.phone.toLowerCase().includes(q)) ||
            (String(u.id) === q)
        );

        if (filtered.length === 0) {
            listContainer.innerHTML = `<div style="text-align:center; padding:24px 10px; color:var(--text-muted); font-size:12px;"><i class="fas fa-search" style="font-size:20px; opacity:0.4; margin-bottom:6px; display:block;"></i>No se encontraron usuarios coincidentes con "${query}".</div>`;
            return;
        }

        listContainer.innerHTML = filtered.map(u => `
            <div class="admin-user-list-card" onclick="PaseoMacutoUI.openUserProfileAndHistory(${u.id})" style="background:var(--bg-primary); border:1px solid var(--border-color); border-radius:var(--radius-sm); padding:10px 14px; display:flex; justify-content:space-between; align-items:center; cursor:pointer; transition:all 0.2s ease;" onmouseover="this.style.borderColor='var(--neon-cyan)'; this.style.boxShadow='0 0 12px rgba(0, 180, 216, 0.25)';" onmouseout="this.style.borderColor='var(--border-color)'; this.style.boxShadow='none';">
                <div style="display:flex; align-items:center; gap:12px;">
                    <div style="width:40px; height:40px; border-radius:50%; background:rgba(0,180,216,0.1); border:1px solid ${u.role === 'merchant' ? '#06D6A0' : (u.role === 'superadmin' ? '#8B5CF6' : 'var(--caribbean-cyan)')}; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                        <i class="${u.role === 'merchant' ? 'fas fa-store' : (u.role === 'superadmin' ? 'fas fa-crown' : 'fas fa-user')}" style="color:${u.role === 'merchant' ? '#06D6A0' : (u.role === 'superadmin' ? '#8B5CF6' : 'var(--caribbean-cyan)')}; font-size:16px;"></i>
                    </div>
                    <div>
                        <div style="display:flex; align-items:center; gap:6px;">
                            <b style="color:var(--text-primary); font-size:13px;">${u.name}</b>
                            <span style="font-size:10px; color:var(--text-muted); font-weight:700;">#${u.id}</span>
                        </div>
                        <div style="font-size:11px; color:var(--text-muted);">${u.email} ${u.phone ? '• Tel: ' + u.phone : ''}</div>
                        <div style="font-size:10px; color:var(--caribbean-cyan); margin-top:2px;">
                            <i class="far fa-clock"></i> Creado: ${u.created_at ? u.created_at.substring(0, 10) : 'N/A'}
                        </div>
                    </div>
                </div>
                <div style="text-align:right; display:flex; flex-direction:column; align-items:flex-end; gap:4px;">
                    <span class="badge-verified ${u.role === 'merchant' ? 'neon-glow-emerald' : (u.role === 'superadmin' ? 'neon-glow-purple' : 'neon-glow-cyan')}" style="font-size:9px; text-transform:uppercase;">${u.role}</span>
                    <div style="font-size:11px; color:var(--text-secondary); font-weight:700;">Nivel ${u.level} • ${u.xp} XP</div>
                    <button class="btn-primary" style="padding:3px 10px; font-size:10px; border-radius:10px; margin-top:2px;" onclick="event.stopPropagation(); PaseoMacutoUI.openUserProfileAndHistory(${u.id});">
                        <i class="fas fa-search-plus"></i> Ver Todo el Detalle
                    </button>
                </div>
            </div>
        `).join('');
    },

    // Validación geográfica de Plus Code dentro del rango de Paseo Macuto
    isPlusCodeInPaseoMacuto(code) {
        if (!code || typeof code !== 'string') return false;
        const clean = code.trim().toUpperCase().replace(/,.*$/, '').replace(/\s+/g, '');
        // El cuadrante oficial de Paseo Macuto abarca la franja costera J34x y J35x
        const fullPattern = /^769HJ3[3-5][0-9A-Z]\+[0-9A-Z]{2,4}$/;
        const shortPattern = /^J3[3-5][0-9A-Z]\+[0-9A-Z]{2,4}$/;
        return fullPattern.test(clean) || shortPattern.test(clean);
    },

    validateRegistrationPlusCode(value) {
        const alertBox = document.getElementById('reg-pluscode-alert');
        if (!alertBox) return;
        const trimmed = (value || '').trim();
        if (!trimmed) {
            alertBox.style.display = 'none';
            return;
        }
        if (!this.isPlusCodeInPaseoMacuto(trimmed)) {
            alertBox.style.display = 'block';
            alertBox.innerHTML = `<i class="fas fa-exclamation-triangle"></i> <b>Ubicación fuera de rango:</b> El Plus Code debe pertenecer obligatoriamente a la extensión del Paseo Macuto (La Guaira). Ejemplo válido: <code>769HJ344+J5</code>`;
        } else {
            alertBox.style.display = 'none';
        }
    },

    validateDashboardPlusCode(value) {
        const alertBox = document.getElementById('merch-dash-plus-alert');
        if (!alertBox) return;
        const trimmed = (value || '').trim();
        if (!trimmed) {
            alertBox.style.display = 'none';
            return;
        }
        if (!this.isPlusCodeInPaseoMacuto(trimmed)) {
            alertBox.style.display = 'block';
            alertBox.innerHTML = `<i class="fas fa-exclamation-triangle"></i> <b>Ubicación fuera de rango:</b> El Plus Code debe pertenecer a la extensión del Paseo Macuto (La Guaira). Ejemplo válido: <code>769HJ344+J5</code>`;
        } else {
            alertBox.style.display = 'none';
        }
    },

    initEventListeners() {
        // Toggle tema
        const themeBtn = document.getElementById('btn-theme-toggle');
        if (themeBtn) themeBtn.addEventListener('click', () => this.toggleTheme());

        // Selector de rol en formulario de registro
        const roleSel = document.getElementById('reg-role');
        if (roleSel) {
            roleSel.addEventListener('change', (e) => {
                const merchFields = document.getElementById('merchant-extra-fields');
                if (merchFields) {
                    merchFields.style.display = e.target.value === 'merchant' ? 'flex' : 'none';
                }
            });
        }

        // Filtro de chips por categoría
        document.querySelectorAll('.category-chip').forEach(chip => {
            chip.addEventListener('click', (e) => {
                document.querySelectorAll('.category-chip').forEach(c => c.classList.remove('active'));
                chip.classList.add('active');
                const cat = chip.getAttribute('data-cat') || 'all';
                PaseoMacutoMap.loadMerchants(cat);
            });
        });

        // Búsqueda en tiempo real
        const searchInput = document.getElementById('map-search-input');
        if (searchInput) {
            let debounceTimer;
            searchInput.addEventListener('input', (e) => {
                clearTimeout(debounceTimer);
                debounceTimer = setTimeout(() => {
                    const term = e.target.value.trim();
                    const activeChip = document.querySelector('.category-chip.active');
                    const cat = activeChip ? activeChip.getAttribute('data-cat') : 'all';
                    PaseoMacutoMap.loadMerchants(cat, term);
                }, 300);
            });
        }
    }
};

window.PaseoMacutoUI = PaseoMacutoUI;

document.addEventListener('DOMContentLoaded', () => {
    PaseoMacutoUI.init();
});

