// assets/js/cart_pagomovil.js - Sistema de Carrito de Compras, Pago Móvil y 3 Solapas de Verificación

const CartAndPagoMovil = {
    cart: [],
    venezuelanBanks: [
        "Banco de Venezuela (0102)",
        "Banesco (0134)",
        "Banco Mercantil (0105)",
        "BBVA Provincial (0108)",
        "Bancamiga (0172)",
        "Banco Nacional de Crédito BNC (0191)",
        "Banco Bicentenario (0175)",
        "Banco Digital de los Trabajadores BDT (0163)",
        "Bancaribe (0114)",
        "Banco Exterior (0115)",
        "Banco Fondo Común BFC (0151)",
        "Banco Caroní (0128)",
        "Banco Plaza (0138)",
        "100% Banco (0156)",
        "Banco Sofitasa (0137)",
        "Banco Venezolano de Crédito (0104)",
        "Banplus (0174)",
        "Mi Banco (0169)",
        "Bancrecer (0168)",
        "Banco Activo (0171)"
    ],
    proofBase64: '',
    verifiedOrderCache: new Set(),
    pollingInterval: null,

    init() {
        this.loadCartFromStorage();
        this.updateCartBadge();
        this.populateBanksDropdown();
        this.startVisitorNotificationPoller();
    },

    loadCartFromStorage() {
        try {
            if (typeof PaseoMacutoUI !== 'undefined' && !PaseoMacutoUI.currentUser) {
                this.cart = [];
                localStorage.removeItem('pm_cart');
                return;
            }
            const saved = localStorage.getItem('pm_cart');
            this.cart = saved ? JSON.parse(saved) : [];
        } catch (e) {
            this.cart = [];
        }
    },

    saveCartToStorage() {
        if (typeof PaseoMacutoUI !== 'undefined' && !PaseoMacutoUI.currentUser) {
            this.cart = [];
            localStorage.removeItem('pm_cart');
            this.updateCartBadge();
            return;
        }
        localStorage.setItem('pm_cart', JSON.stringify(this.cart));
        this.updateCartBadge();
    },

    updateCartBadge() {
        const badge = document.getElementById('cart-counter');
        if (!badge) return;
        if (typeof PaseoMacutoUI !== 'undefined' && !PaseoMacutoUI.currentUser) {
            badge.style.display = 'none';
            return;
        }
        const count = this.cart.reduce((sum, item) => sum + (item.qty || 1), 0);
        badge.innerText = count;
        badge.style.display = count > 0 ? 'flex' : 'none';
    },

    addToCart(product, merchant) {
        // En sesión de invitado está prohibido llenar el carrito: solo iniciar sesión o crear cuenta
        if (typeof PaseoMacutoUI !== 'undefined' && !PaseoMacutoUI.currentUser) {
            alert("🔒 Acceso Restringido:\n\nEn la sesión de invitado no se puede llenar el carrito de compras.\n\nPor favor Inicia Sesión o Crea tu Cuenta para comprar productos y servicios en Paseo Macuto.");
            PaseoMacutoUI.openModal('modal-auth', 'login');
            return false;
        }

        // Validar si el carrito tiene productos de otro comercio
        if (this.cart.length > 0 && this.cart[0].merchant_id !== merchant.id) {
            const confirmClear = confirm(`Tu carrito ya tiene productos de "${this.cart[0].merchant_name}".\n\n¿Deseas vaciar el carrito para comprar en "${merchant.commercial_name}"?`);
            if (confirmClear) {
                this.cart = [];
            } else {
                return false;
            }
        }

        const existing = this.cart.find(i => i.id === product.id);
        if (existing) {
            existing.qty += 1;
        } else {
            this.cart.push({
                id: product.id,
                name: product.name,
                price_usd: parseFloat(product.price_usd),
                qty: 1,
                merchant_id: merchant.id,
                merchant_name: merchant.commercial_name,
                merchant_plus_code: merchant.plus_code || ''
            });
        }

        this.saveCartToStorage();
        GamificationSystem.showXpAwardNotice(0, 1, `"${product.name}" agregado al carrito`);
        return true;
    },

    updateQty(productId, delta) {
        const item = this.cart.find(i => i.id === productId);
        if (!item) return;

        item.qty += delta;
        if (item.qty <= 0) {
            this.cart = this.cart.filter(i => i.id !== productId);
        }
        this.saveCartToStorage();
        this.renderCartModal();
    },

    removeFromCart(productId) {
        this.cart = this.cart.filter(i => i.id !== productId);
        this.saveCartToStorage();
        this.renderCartModal();
    },

    clearCart() {
        this.cart = [];
        this.saveCartToStorage();
        this.renderCartModal();
    },

    openCartModal() {
        if (typeof PaseoMacutoUI !== 'undefined' && !PaseoMacutoUI.currentUser) {
            alert("🔒 Acceso Restringido:\n\nEn la sesión de invitado no se puede llenar ni acceder al carrito.\n\nPor favor Inicia Sesión o Crea tu Cuenta para comprar productos en Paseo Macuto.");
            PaseoMacutoUI.openModal('modal-auth', 'login');
            return;
        }
        this.renderCartModal();
        PaseoMacutoUI.openModal('modal-cart');
    },

    renderCartModal() {
        const container = document.getElementById('cart-items-list');
        const totalUsdEl = document.getElementById('cart-total-usd');
        const totalBsEl = document.getElementById('cart-total-bs');
        const merchantTitleEl = document.getElementById('cart-merchant-title');
        const merchantPlusCodeEl = document.getElementById('cart-merchant-pluscode');
        const checkoutBtn = document.getElementById('btn-cart-checkout');

        if (!container) return;

        if (this.cart.length === 0) {
            if (merchantTitleEl) merchantTitleEl.innerText = 'Tu carrito está vacío';
            if (merchantPlusCodeEl) merchantPlusCodeEl.innerHTML = '';
            container.innerHTML = `
                <div style="text-align:center; padding:30px; color:var(--text-muted);">
                    <i class="fas fa-shopping-cart" style="font-size:36px; margin-bottom:10px; opacity:0.4;"></i>
                    <p style="font-size:13px;">Aún no has agregado productos al carrito.</p>
                </div>
            `;
            if (totalUsdEl) totalUsdEl.innerText = '$0.00';
            if (totalBsEl) totalBsEl.innerText = '0.00 Bs.';
            if (checkoutBtn) checkoutBtn.disabled = true;
            return;
        }

        const merchantName = this.cart[0].merchant_name || 'Comercio';
        const merchantPlusCode = this.cart[0].merchant_plus_code || '';
        if (merchantTitleEl) merchantTitleEl.innerText = `Pedido para: ${merchantName}`;
        if (merchantPlusCodeEl) {
            merchantPlusCodeEl.innerHTML = merchantPlusCode ? `<i class="fas fa-map-pin"></i> Plus Code: <b>${merchantPlusCode}</b>` : '';
        }

        const bcv = PaseoMacutoUI.bcvRate || 54.50;
        let totalUsd = 0;

        container.innerHTML = this.cart.map(item => {
            const subtotal = item.price_usd * item.qty;
            totalUsd += subtotal;
            return `
                <div class="cart-item-row">
                    <div style="flex:1;">
                        <b style="color:var(--text-primary); font-size:13px;">${item.name}</b>
                        <div style="font-size:12px; color:var(--caribbean-cyan); font-weight:700;">
                            $${item.price_usd.toFixed(2)} <span style="font-size:10px; color:var(--text-muted);">(${(item.price_usd * bcv).toFixed(2)} Bs.)</span>
                        </div>
                    </div>
                    <div class="cart-qty-ctrl">
                        <button class="cart-qty-btn" onclick="CartAndPagoMovil.updateQty(${item.id}, -1)">-</button>
                        <span style="font-size:13px; font-weight:700; width:20px; text-align:center;">${item.qty}</span>
                        <button class="cart-qty-btn" onclick="CartAndPagoMovil.updateQty(${item.id}, 1)">+</button>
                        <button class="cart-qty-btn" style="color:var(--coral-alert); margin-left:4px;" onclick="CartAndPagoMovil.removeFromCart(${item.id})">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
            `;
        }).join('');

        const totalBs = totalUsd * bcv;
        if (totalUsdEl) totalUsdEl.innerText = `$${totalUsd.toFixed(2)}`;
        if (totalBsEl) totalBsEl.innerText = `${totalBs.toFixed(2)} Bs.`;
        if (checkoutBtn) checkoutBtn.disabled = false;
    },

    // -------------------------------------------------------------
    // CHECKOUT Y FORMULARIO PAGO MÓVIL
    // -------------------------------------------------------------
    populateBanksDropdown() {
        const select = document.getElementById('pay-sender-bank');
        if (!select) return;
        select.innerHTML = `<option value="">Selecciona tu banco emisor...</option>` +
            this.venezuelanBanks.map(b => `<option value="${b}">${b}</option>`).join('');
    },

    async openPagoMovilCheckout() {
        if (!PaseoMacutoUI.currentUser) {
            alert("Por favor inicia sesión como visitante para enviar tu pago.");
            PaseoMacutoUI.openModal('modal-auth');
            return;
        }

        if (this.cart.length === 0) {
            alert("El carrito está vacío.");
            return;
        }

        const merchantId = this.cart[0].merchant_id;
        const bcv = PaseoMacutoUI.bcvRate || 54.50;
        const totalUsd = this.cart.reduce((sum, i) => sum + (i.price_usd * i.qty), 0);
        const totalBs = totalUsd * bcv;

        // Cargar datos de pago móvil del vendedor
        try {
            const res = await fetch(`api/index.php?action=get_merchant_pagomovil&merchant_id=${merchantId}`);
            const data = await res.json();
            if (data.success) {
                const m = data.merchant;
                document.getElementById('pm-vendor-name').innerText = m.pago_movil_name || m.commercial_name;
                document.getElementById('pm-vendor-bank').innerText = m.pago_movil_bank || 'Banco de Venezuela (0102)';
                document.getElementById('pm-vendor-phone').innerText = m.pago_movil_phone || '0412-3551020';
                document.getElementById('pm-vendor-ci').innerText = m.pago_movil_ci || 'V-16890452';
                
                const pmPlusCodeEl = document.getElementById('pm-vendor-pluscode');
                if (pmPlusCodeEl) {
                    pmPlusCodeEl.innerText = m.plus_code || 'No asignado';
                }

                document.getElementById('pm-total-bs-display').innerText = `${totalBs.toFixed(2)} Bs.`;
                document.getElementById('pm-total-usd-display').innerText = `Total: $${totalUsd.toFixed(2)} USD (Tasa BCV: ${bcv.toFixed(2)} Bs/$)`;

                // Limpiar campos del formulario
                document.getElementById('form-pagomovil-submit').reset();
                this.proofBase64 = '';
                document.getElementById('proof-preview-container').style.display = 'none';

                PaseoMacutoUI.closeModal('modal-cart');
                PaseoMacutoUI.openModal('modal-pagomovil-checkout');
            } else {
                alert(data.message);
            }
        } catch (e) {
            alert("Error al obtener los datos de Pago Móvil del vendedor.");
        }
    },

    handleProofImageChange(event) {
        const file = event.target.files[0];
        if (!file) return;

        const reader = new FileReader();
        reader.onload = (e) => {
            this.proofBase64 = e.target.result;
            const preview = document.getElementById('proof-preview-img');
            const container = document.getElementById('proof-preview-container');
            if (preview && container) {
                preview.src = this.proofBase64;
                container.style.display = 'block';
            }
        };
        reader.readAsDataURL(file);
    },

    async submitPagoMovil(e) {
        e.preventDefault();
        const merchantId = this.cart[0].merchant_id;
        const totalUsd = this.cart.reduce((sum, i) => sum + (i.price_usd * i.qty), 0);
        const senderBank = document.getElementById('pay-sender-bank').value;
        const senderPhone = document.getElementById('pay-sender-phone').value;
        const senderCi = document.getElementById('pay-sender-ci').value;
        const paymentReference = document.getElementById('pay-reference').value;

        if (!senderBank || !senderPhone || !senderCi || !paymentReference) {
            alert("Por favor completa todos los campos del pago móvil.");
            return;
        }

        const payload = {
            merchant_id: merchantId,
            items_json: JSON.stringify(this.cart),
            amount_usd: totalUsd,
            sender_bank: senderBank,
            sender_phone: senderPhone,
            sender_ci: senderCi,
            payment_reference: paymentReference,
            proof_image: this.proofBase64 || ''
        };

        const submitBtn = document.getElementById('btn-submit-pagomovil');
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Enviando Pago...';
        }

        try {
            const res = await fetch('api/index.php?action=submit_cart_payment', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const data = await res.json();

            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Enviar Pago para Verificación';
            }

            if (data.success) {
                PaseoMacutoUI.closeModal('modal-pagomovil-checkout');
                this.cart = [];
                this.saveCartToStorage();

                // Mensaje emergente requerido
                document.getElementById('verified-pending-order-id').innerText = `#${data.order_id}`;
                PaseoMacutoUI.openModal('modal-payment-pending-notice');
            } else {
                alert(data.message);
            }
        } catch (err) {
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Enviar Pago para Verificación';
            }
            alert("Error al enviar comprobante de pago");
        }
    },

    // -------------------------------------------------------------
    // MÓDULO DEL VENDEDOR: PAGOS POR VERIFICAR (3 SOLAPAS)
    // -------------------------------------------------------------
    activeMerchantTab: 'pending',

    async openMerchantVerificationPanel(tab = 'pending') {
        this.activeMerchantTab = tab;
        PaseoMacutoUI.openModal('modal-merchant-payments');
        await this.loadMerchantPayments(tab);
    },

    switchMerchantPaymentTab(tab) {
        this.activeMerchantTab = tab;
        document.querySelectorAll('.payment-tab-btn').forEach(b => b.classList.remove('active'));
        const targetBtn = document.querySelector(`.payment-tab-btn[data-tab="${tab}"]`);
        if (targetBtn) targetBtn.classList.add('active');

        this.loadMerchantPayments(tab);
    },

    async loadMerchantPayments(tab = 'pending') {
        const container = document.getElementById('merchant-payments-list');
        if (!container) return;

        container.innerHTML = `
            <div style="text-align:center; padding:20px; color:var(--caribbean-cyan);">
                <i class="fas fa-spinner fa-spin" style="font-size:24px;"></i>
                <p style="margin-top:6px; font-size:12px;">Cargando transacciones...</p>
            </div>
        `;

        try {
            const res = await fetch(`api/index.php?action=merchant_pending_payments&tab=${tab}`);
            const data = await res.json();

            if (!data.success) {
                container.innerHTML = `<div style="text-align:center; padding:16px; color:var(--coral-alert);">${data.message}</div>`;
                return;
            }

            // Actualizar contadores de las solapas
            document.getElementById('badge-count-pending').innerText = data.counts.pending || 0;
            document.getElementById('badge-count-approved').innerText = data.counts.approved || 0;
            document.getElementById('badge-count-denied').innerText = data.counts.denied || 0;

            if (data.orders.length === 0) {
                container.innerHTML = `
                    <div style="text-align:center; padding:24px; color:var(--text-muted);">
                        <i class="fas fa-receipt" style="font-size:32px; margin-bottom:8px; opacity:0.4;"></i>
                        <p>No hay solicitudes en esta solapa.</p>
                    </div>
                `;
                return;
            }

            container.innerHTML = data.orders.map(o => {
                let itemsHtml = '';
                try {
                    const parsed = JSON.parse(o.items_json || '[]');
                    itemsHtml = parsed.map(i => `<span>• ${i.name} (x${i.qty || 1}) - $${(i.price_usd * (i.qty || 1)).toFixed(2)}</span>`).join('<br>');
                } catch (e) {
                    itemsHtml = 'Pedido playero';
                }

                const proofHtml = o.proof_image ? `
                    <div style="margin-top:6px;">
                        <span style="font-size:11px; font-weight:700; color:var(--text-muted);">Comprobante Adjunto:</span><br>
                        <img src="${o.proof_image}" class="proof-thumbnail-preview" onclick="window.open('${o.proof_image}')" title="Clic para ampliar">
                    </div>
                ` : '<div style="font-size:11px; color:var(--text-muted); margin-top:4px;">Sin foto de comprobante</div>';

                const actionButtons = o.status === 'pending_verification' ? `
                    <div style="display:flex; gap:8px; margin-top:10px; border-top:1px solid var(--border-color); padding-top:10px;">
                        <button class="btn-primary neon-glow-emerald" style="flex:1; background:linear-gradient(135deg, #06D6A0, #059669); font-size:12px;" onclick="CartAndPagoMovil.processPayment(${o.id}, 'accept')">
                            <i class="fas fa-check-circle"></i> Aceptar Pago
                        </button>
                        <button class="btn-danger neon-glow-amber" style="flex:1; font-size:12px;" onclick="CartAndPagoMovil.processPayment(${o.id}, 'deny')">
                            <i class="fas fa-times-circle"></i> Denegar
                        </button>
                    </div>
                ` : `
                    <div style="margin-top:8px; font-size:11px; color:var(--text-muted); font-weight:700;">
                        Estado: <span class="${o.status === 'completed' ? 'badge-verified' : 'badge-pending'}">${o.status === 'completed' ? 'Aprobado y Cargado' : 'Denegado'}</span>
                    </div>
                `;

                return `
                    <div class="neon-card ${o.status === 'completed' ? 'emerald' : (o.status === 'denied' ? 'amber' : 'cyan')}">
                        <div style="display:flex; justify-content:space-between; align-items:flex-start;">
                            <div>
                                <b style="font-size:14px; color:var(--text-primary);"><i class="fas fa-user"></i> ${o.visitor_name}</b>
                                <div style="font-size:11px; color:var(--text-muted);">${o.visitor_email} • Tlf: ${o.visitor_phone || o.sender_phone || 'N/A'}</div>
                            </div>
                            <div style="text-align:right;">
                                <div style="font-size:15px; font-weight:900; color:#06D6A0;">$${parseFloat(o.amount_usd).toFixed(2)}</div>
                                <div style="font-size:11px; color:var(--text-muted); font-weight:700;">${parseFloat(o.amount_bs).toFixed(2)} Bs.</div>
                            </div>
                        </div>

                        <div style="background:var(--bg-primary); padding:8px 10px; border-radius:var(--radius-sm); font-size:12px; margin-top:6px; display:grid; grid-template-columns:1fr 1fr; gap:6px;">
                            <div><span style="color:var(--text-muted); font-size:10px;">BANCO EMISOR:</span><br><b>${o.sender_bank || 'N/A'}</b></div>
                            <div><span style="color:var(--text-muted); font-size:10px;">REFERENCIA:</span><br><b style="color:var(--caribbean-cyan);">${o.payment_reference || 'N/A'}</b></div>
                            <div><span style="color:var(--text-muted); font-size:10px;">TELÉFONO PAGADOR:</span><br><b>${o.sender_phone || 'N/A'}</b></div>
                            <div><span style="color:var(--text-muted); font-size:10px;">CÉDULA PAGADOR:</span><br><b>${o.sender_ci || 'N/A'}</b></div>
                        </div>

                        <div style="font-size:12px; margin-top:6px;">
                            <span style="font-weight:700; color:var(--text-secondary);">Productos:</span><br>
                            <div style="color:var(--text-muted); font-size:11px; line-height:1.4;">${itemsHtml}</div>
                        </div>

                        ${proofHtml}
                        ${actionButtons}
                    </div>
                `;
            }).join('');
        } catch (err) {
            container.innerHTML = 'Error al cargar transacciones';
        }
    },

    async processPayment(orderId, decision) {
        let reason = '';
        if (decision === 'deny') {
            reason = prompt("Indica el motivo del rechazo del pago móvil:") || 'Comprobante no recibido';
        }

        try {
            const res = await fetch('api/index.php?action=merchant_process_payment', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ order_id: orderId, decision, reason })
            });
            const data = await res.json();
            if (data.success) {
                alert(data.message);
                this.loadMerchantPayments(this.activeMerchantTab);
                PaseoMacutoMap.loadMerchants();
            } else {
                alert(data.message);
            }
        } catch (err) {
            alert("Error al procesar el pago");
        }
    },

    // -------------------------------------------------------------
    // POLLER DE NOTIFICACIONES PARA EL VISITANTE
    // -------------------------------------------------------------
    startVisitorNotificationPoller() {
        if (this.pollingInterval) clearInterval(this.pollingInterval);

        this.pollingInterval = setInterval(async () => {
            if (!PaseoMacutoUI.currentUser || PaseoMacutoUI.currentUser.role !== 'visitor') return;

            try {
                const res = await fetch('api/index.php?action=visitor_check_payment_notifications');
                const data = await res.json();

                if (data.success && data.orders) {
                    data.orders.forEach(o => {
                        const cacheKey = `order_${o.id}_${o.status}`;
                        // Si es primera vez que lo vemos aprobado en esta sesión
                        if (o.status === 'completed' && !this.verifiedOrderCache.has(cacheKey)) {
                            this.verifiedOrderCache.add(cacheKey);

                            // Disparar mensaje emergente
                            document.getElementById('verified-success-merchant').innerText = o.commercial_name;
                            document.getElementById('verified-success-amount').innerText = `$${parseFloat(o.amount_usd).toFixed(2)} (${parseFloat(o.amount_bs).toFixed(2)} Bs.)`;
                            PaseoMacutoUI.openModal('modal-payment-approved-popup');

                            // Actualizar XP y nivel
                            PaseoMacutoUI.checkSession();
                        }
                    });
                }
            } catch (e) {
                // Silencioso
            }
        }, 6000);
    }
};

window.CartAndPagoMovil = CartAndPagoMovil;

document.addEventListener('DOMContentLoaded', () => {
    CartAndPagoMovil.init();
});
