// assets/js/gamification.js - Sistema de Gamificación de 10 Niveles Exponenciales y Boosters

const GamificationSystem = {
    levelTitles: {
        1: 'Novato Playero',
        2: 'Caminante de Macuto',
        3: 'Explorador Costero',
        4: 'Amigo de los Pescadores',
        5: 'Guía de la Bahía',
        6: 'Capitán de Paseo',
        7: 'Conocedor del Litoral',
        8: 'Embajador de La Guaira',
        9: 'Protector del Malecón',
        10: 'Leyenda del Paseo Macuto'
    },

    // Cálculo exponencial en base 10 (10, 100, 1000...)
    calculateLevel(xp) {
        xp = parseInt(xp) || 0;
        let level = 1;
        let currentLevelXp = 0;
        let nextLevelXp = 10;

        if (xp < 10) {
            level = 1;
            currentLevelXp = 0;
            nextLevelXp = 10;
        } else {
            level = Math.floor(Math.log10(xp)) + 1;
            if (level > 10) level = 10;
            currentLevelXp = Math.pow(10, level - 1);
            nextLevelXp = (level < 10) ? Math.pow(10, level) : currentLevelXp;
        }

        const range = Math.max(1, nextLevelXp - currentLevelXp);
        const progress = (level >= 10) ? 100 : Math.min(100, Math.max(0, Math.round(((xp - currentLevelXp) / range) * 100)));

        return {
            level: level,
            title: this.levelTitles[level] || 'Visitante',
            xp: xp,
            currentLevelXp: currentLevelXp,
            nextLevelXp: nextLevelXp,
            progress: progress,
            xpRemaining: Math.max(0, nextLevelXp - xp)
        };
    },

    renderBadge(containerId, xp) {
        const container = document.getElementById(containerId);
        if (!container) return;

        const data = this.calculateLevel(xp);

        container.innerHTML = `
            <div class="level-badge-container">
                <div class="level-header-row">
                    <div>
                        <span class="level-number-pill">Nivel ${data.level} / 10</span>
                        <h4 style="margin-top:6px; font-size:15px; font-weight:800; color:#FFFFFF;">${data.title}</h4>
                    </div>
                    <div style="text-align:right;">
                        <span style="font-size:18px; font-weight:900; color:#06D6A0;">${data.xp.toLocaleString()} XP</span>
                    </div>
                </div>
                <div class="level-progress-track">
                    <div class="level-progress-bar" style="width: ${data.progress}%"></div>
                </div>
                <div class="level-details-text">
                    <span>${data.progress}% completado</span>
                    <span>${data.level < 10 ? 'Faltan ' + data.xpRemaining.toLocaleString() + ' XP para Nivel ' + (data.level + 1) : '¡Nivel Máximo Alcanzado!'}</span>
                </div>
            </div>
        `;
    },

    showXpAwardNotice(earnedXp, multiplier, actionTitle) {
        const toast = document.createElement('div');
        toast.className = 'toast-notice show';
        toast.innerHTML = `
            <span style="font-size:20px;">⚡</span>
            <div>
                <div style="font-size:13px; font-weight:800; color:var(--caribbean-cyan);">+${earnedXp} XP Obtenidos</div>
                <div style="font-size:11px; color:var(--text-secondary);">${actionTitle} ${multiplier > 1 ? `<b style="color:#F59E0B;">(x${multiplier} Potenciador)</b>` : ''}</div>
            </div>
        `;
        document.body.appendChild(toast);

        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => toast.remove(), 400);
        }, 3500);
    }
};

window.GamificationSystem = GamificationSystem;
