/**
 * careerconnect.js — CareerConnect Faculty Communication & Career Support
 */
window.CareerConnect = window.CareerConnect || { state: {} };

(function () {
    if (window.__CAREERCONNECT_LOADED__) return;
    window.__CAREERCONNECT_LOADED__ = true;

    const API = window.location.origin.replace(/\/$/, '') + '/api/v1';

    const state = {
        user: null, profile: null, page: 'dashboard',
        threads: [], facultyDirectory: [],
        activeThreadId: null,
        deptDetailId: null,
    };
    window.CareerConnect.state = state;

    function showInitError(msg) {
        const el = document.getElementById('careerconnect-loader-error');
        if (el) { el.style.display = 'block'; el.textContent = msg; }
    }
    window.addEventListener('module:error', e =>
        showInitError('Authentication failed: ' + (e.detail?.error || 'unknown')));

    window.addEventListener('module:ready', async e => {
        if (window.__CC_BOOTED__) return;
        window.__CC_BOOTED__ = true;
        try {
            const detail = e.detail || {};
            const token = detail.token || window.SSO_TOKEN || null;
            let user = detail.user || window.PORTAL_USER || null;
            if (!user?.id && token) {
                const exchanged = await fetch(window.location.origin + '/api/sso/exchange', {
                    method: 'POST', credentials: 'include',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        ...(window.CAREERCONNECT_SSO_HANDSHAKE_KEY
                            ? { 'X-CareerConnect-Sso-Key': window.CAREERCONNECT_SSO_HANDSHAKE_KEY }
                            : {}),
                    },
                    body: JSON.stringify({ token, embedded: !!detail.embedded }),
                }).then(async res => {
                    const body = await res.json().catch(() => ({}));
                    if (!res.ok || body.success === false) throw new Error(body.message || body.error || 'HTTP ' + res.status);
                    return body.user || null;
                });
                user = exchanged || user;
            }
            if (!user?.id) throw new Error('missing_user');
            await bootApp(user);
        } catch (err) { console.error('[careerconnect]', err); showInitError(err.message || 'Failed to load.'); }
    });

    /* ── API helper ──────────────────────────────────────── */
    async function api(path, opts = {}, attempt = 0) {
        const headers = { Accept: 'application/json', 'Content-Type': 'application/json', ...(opts.headers || {}) };
        const token = window.DEORIS_API_TOKEN;
        if (token && String(token).startsWith('dev:')) headers.Authorization = 'Bearer ' + token;
        const res = await fetch(API + path, { ...opts, headers, credentials: 'include',
            body: opts.body ? JSON.stringify(opts.body) : undefined });
        if (res.status === 401 && attempt < 1 && !token) {
            await new Promise(r => setTimeout(r, 150));
            return api(path, opts, attempt + 1);
        }
        if (!res.ok) {
            const b = await res.json().catch(() => ({}));
            if (b.errors) throw new Error(Object.values(b.errors).flat().join(' '));
            throw new Error(b.error || b.message || 'HTTP ' + res.status);
        }
        if (res.status === 204) return null;
        return res.json();
    }

    /* ── Helpers ─────────────────────────────────────────── */
    function esc(str) {
        return String(str ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');
    }
    function trunc(str, n) { const s = String(str ?? ''); return s.length > n ? s.slice(0,n)+'…' : s; }
    function fmtDate(val) {
        if (!val) return '—';
        try { return new Date(val).toLocaleString(undefined,{month:'short',day:'numeric',hour:'2-digit',minute:'2-digit'}); }
        catch(_) { return String(val); }
    }
    function emptyState(msg, sub) {
        return '<div class="cc-empty"><div class="cc-empty-icon"><svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="10"/><line x1="8" y1="12" x2="16" y2="12"/></svg></div>' +
            '<div class="cc-empty-title">' + esc(msg) + '</div>' +
            (sub ? '<p>' + esc(sub) + '</p>' : '') + '</div>';
    }
    function submitGuard(form, text) {
        const btn = form.querySelector('[type="submit"]');
        if (!btn || btn.disabled) return false;
        btn.disabled = true;
        btn._orig = btn.textContent;
        btn.textContent = text || 'Saving…';
        return true;
    }
    function charCounter(ta, max) {
        const wrap = ta.closest('.cc-form-group');
        if (!wrap) return;
        let el = wrap.querySelector('.cc-char-count');
        if (!el) { el = document.createElement('span'); el.className = 'cc-char-count'; wrap.appendChild(el); }
        const upd = () => { const l = max - ta.value.length; el.textContent = l + ' / ' + max; el.style.color = l < 20 ? 'var(--danger)' : ''; };
        ta.addEventListener('input', upd); upd();
    }

    /* ── Toast ───────────────────────────────────────────── */
    function initToasts() {
        if (document.getElementById('cc-toasts')) return;
        const c = document.createElement('div'); c.id = 'cc-toasts';
        document.body.appendChild(c);
    }
    function toast(msg, type) {
        const c = document.getElementById('cc-toasts'); if (!c) return;
        const icons = { success:'✓', error:'✕', info:'ℹ', warning:'⚠' };
        const t = document.createElement('div');
        t.className = 'cc-toast cc-toast-' + (type||'info');
        t.innerHTML = '<span class="cc-toast-icon">' + (icons[type]||'ℹ') + '</span><span>' + esc(msg) + '</span>';
        c.appendChild(t);
        requestAnimationFrame(() => requestAnimationFrame(() => t.classList.add('show')));
        setTimeout(() => { t.classList.remove('show'); setTimeout(() => t.remove(), 300); }, 3800);
    }

    window.__CC_BOOT_HOOK__ = {
        api, esc, emptyState, toast, submitGuard, fmtDate, state,
        nav: null,
        extraTabs: () => [],
        facultyOnlyTabs: () => true,
        defaultPage: () => 'dashboard',
        extraPages: {},
        afterProfileLoad: async () => {},
    };

    /* ── Boot ────────────────────────────────────────────── */
    async function bootApp(portalUser) {
        state.user = portalUser;
        initToasts();

        try {
            state.profile = await api('/auth/me');
        } catch (_) {
            state.profile = {
                id: portalUser.id,
                sso_id: portalUser.id,
                name: portalUser.name,
                email: portalUser.email,
                role: portalUser.role,
                permissions: {},
                capabilities: [],
            };
        }

        const root = document.getElementById('careerconnect-root');
        root.innerHTML = buildShell();
        root.style.cssText = 'display:block;min-height:100vh;';
        wireTabs();
        if (window.__CC_BOOT_HOOK__) await window.__CC_BOOT_HOOK__.afterProfileLoad();
        renderNavTabs();
        initWebSockets();
        wireRealtimeEvents();
        const startPage = window.__CC_BOOT_HOOK__?.defaultPage?.() || 'dashboard';
        await navigate(startPage);
        document.getElementById('careerconnect-loader')?.remove();
    }

    /* ── Shell ───────────────────────────────────────────── */
    const FACULTY_TABS = [
        { page:'dashboard',     label:'Dashboard',     icon:'<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>' },
        { page:'announcements', label:'Announcements', icon:'<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 11l19-9-9 19-2-8-8-2z"/></svg>' },
        { page:'boards',        label:'Boards',        icon:'<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>' },
        { page:'resources',     label:'Resources',     icon:'<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>' },
        { page:'messages',      label:'Messages',      icon:'<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>' },
        { page:'activity',      label:'Activity',      icon:'<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>' },
    ];

    function visibleTabs() {
        const hook = window.__CC_BOOT_HOOK__;
        const isStudent = state.profile?.role === 'student';
        const base = isStudent
            ? []
            : (hook?.facultyOnlyTabs?.() !== false ? FACULTY_TABS.slice() : []);
        return base.concat(hook?.extraTabs?.() || []);
    }

    function renderNavTabs() {
        const nav = document.querySelector('.cc-nav-tabs');
        if (!nav) return;
        nav.innerHTML = visibleTabs().map(t =>
            '<button class="cc-tab" data-page="' + t.page + '" type="button" role="tab">' + t.icon + '<span>' + t.label + '</span></button>'
        ).join('');
        wireTabs();
    }

    function buildShell() {
        const tabs = visibleTabs();
        return '<div class="cc-shell">' +
            '<nav class="cc-nav" role="tablist">' +
            '<div class="cc-nav-tabs">' +
            tabs.map(t => '<button class="cc-tab" data-page="' + t.page + '" type="button" role="tab">' + t.icon + '<span>' + t.label + '</span></button>').join('') +
            '</div></nav>' +
            '<main class="cc-main" id="cc-main"></main></div>';
    }

    function wireTabs() {
        document.querySelectorAll('.cc-tab').forEach(btn =>
            btn.addEventListener('click', () => navigate(btn.dataset.page)));
    }

    async function navigate(page) {
        state.page = page;
        document.querySelectorAll('.cc-tab').forEach(b => b.classList.toggle('active', b.dataset.page === page));
        const main = document.getElementById('cc-main');
        main.innerHTML = '<div class="cc-loading"><div class="cc-spinner"></div><span>Loading…</span></div>';
        try {
            const pages = {
                dashboard: renderDashboard,
                announcements: renderAnnouncements,
                boards: renderBoards,
                resources: renderResources,
                messages: renderMessages,
                activity: renderActivity,
                ...(window.__CC_BOOT_HOOK__?.extraPages || {}),
            };
            if (pages[page]) await pages[page](main);
            else main.innerHTML = '<div class="cc-page-wrap">' + emptyState('Page not found') + '</div>';
        } catch(e) {
            main.innerHTML = '<div class="cc-page-wrap"><div class="cc-alert cc-alert-danger">' + esc(e.message) + '</div></div>';
        }
    }
    window.CareerConnect.nav = page => navigate(page);
    if (window.__CC_BOOT_HOOK__) window.__CC_BOOT_HOOK__.nav = navigate;

    function initWebSockets() {
        const cfg = window.REVERB_CONFIG, uid = state.profile?.id;
        if (!cfg || !uid || typeof uid !== 'number') return;
        if (window.CareerConnectRealtime) { try { window.CareerConnectRealtime.init(cfg, uid); } catch(_) {} }
    }
    function wireRealtimeEvents() {
        window.addEventListener('careerconnect:announcement', e => {
            toast('New announcement: ' + (e.detail?.title || ''), 'info');
            if (state.page === 'dashboard' || state.page === 'announcements') navigate(state.page);
        });
        window.addEventListener('careerconnect:board-post', e => toast('New board post: ' + (e.detail?.title || ''), 'info'));
    }

    async function renderStudentDashboard(main) {
        let openJobs = 0, openIntern = 0, myApps = 0;
        try {
            const data = await api('/opportunities/bootstrap');
            openJobs = (data.jobs || []).filter(j => j.status === 'open').length;
            openIntern = (data.internships || []).filter(i => i.status === 'open').length;
            myApps = (data.applications || []).length;
        } catch (_) {}
        const name = state.profile?.name || 'Student';
        main.innerHTML = '<div class="cc-page-wrap">' +
            '<div class="cc-banner"><div class="cc-banner-left">' +
            '<div class="cc-banner-avatar">' + esc(name.charAt(0)) + '</div>' +
            '<div><h2>Welcome, ' + esc(name) + '</h2><p>Explore careers and track your applications.</p></div></div></div>' +
            '<div class="cc-metrics">' +
            '<div class="cc-metric"><div class="cc-metric-val" style="color:var(--primary)">' + openJobs + '</div><div class="cc-metric-label">Open jobs</div></div>' +
            '<div class="cc-metric"><div class="cc-metric-val" style="color:var(--accent)">' + openIntern + '</div><div class="cc-metric-label">Internships</div></div>' +
            '<div class="cc-metric"><div class="cc-metric-val" style="color:var(--info)">' + myApps + '</div><div class="cc-metric-label">My applications</div></div>' +
            '</div>' +
            '<div class="cc-quick-actions">' +
            '<button type="button" class="cc-btn-outline" style="--btn-color:var(--primary)" onclick="CareerConnect.nav(\'opportunities\')">Browse opportunities</button>' +
            '<button type="button" class="cc-btn-outline" style="--btn-color:var(--info)" onclick="CareerConnect.nav(\'opp-applications\')">My applications</button>' +
            '</div></div>';
    }

    /* ══════════════════════════════════════════════════════
       DASHBOARD
    ══════════════════════════════════════════════════════ */
    async function renderDashboard(main) {
        if (state.profile?.role === 'student') {
            return renderStudentDashboard(main);
        }
        const stats = await api('/dashboard/stats');
        const role  = state.profile?.role || 'faculty';
        const name  = state.profile?.name || 'Faculty';
        const perms = state.profile?.permissions || {};
        const dept  = state.profile?.department;

        const roleLabels = { admin:'Administrator', instructor:'Instructor', admission_officer:'Admission Officer', librarian:'Librarian', cashier:'Cashier' };
        const roleColors = { admin:'#7C3041', instructor:'#2563EB', admission_officer:'#D97706', librarian:'#16A34A', cashier:'#7C3AED' };
        const roleColor  = roleColors[role] || '#7C3041';

        const svgAnn = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 11l19-9-9 19-2-8-8-2z"/></svg>';
        const svgBrd = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>';
        const svgRes = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>';

        let html = '<div class="cc-page-wrap">';

        // Welcome banner
        html += '<div class="cc-banner">' +
            '<div class="cc-banner-left">' +
            '<div><h2>Welcome back, ' + esc(name.split(' ')[0]) + '</h2>' +
            '<p>' + esc(state.profile?.email || '') + '</p></div></div>' +
            (dept ? '<div class="cc-banner-chips"><span class="cc-chip">' + esc(dept) + '</span></div>' : '') +
            '</div>';

        // Metrics
        html += '<div class="cc-metrics">' +
            metricCard(svgAnn, 'Announcements', stats.total_announcements, '#7C3041') +
            metricCard(svgBrd, 'Boards',        stats.total_boards,        '#D97706') +
            metricCard(svgRes, 'Resources',     stats.total_resources,     '#16A34A') +
            '</div>';

        // Quick actions
        const qa = [];
        if (perms.announcements_create) qa.push({ label:'+ Announcement', page:'announcements', color:'var(--primary)' });
        if (perms.boards_create)        qa.push({ label:'+ Board',        page:'boards',        color:'#D97706' });
        if (perms.resources_create)     qa.push({ label:'+ Resource',     page:'resources',     color:'#16A34A' });
        if (perms.messages_send)        qa.push({ label:'+ Message',      page:'messages',      color:'#2563EB' });
        if (qa.length) {
            html += '<div class="cc-quick-actions">' +
                qa.map(a => '<button class="cc-btn cc-btn-outline" style="--btn-color:' + a.color + '" onclick="window.CareerConnect.nav(\'' + a.page + '\')" type="button">' + a.label + '</button>').join('') +
                '</div>';
        }

        // Two-column: recent announcements + recent board activity
        html += '<div class="cc-grid-2">';

        html += '<div class="cc-card"><div class="cc-card-head"><h3>Recent Announcements</h3>' +
            '<button class="cc-btn cc-btn-ghost cc-btn-sm" onclick="window.CareerConnect.nav(\'announcements\')" type="button">View all</button></div>' +
            '<div class="cc-list">';
        if (!stats.recent_announcements?.length) {
            html += emptyState('No announcements yet');
        } else {
            stats.recent_announcements.forEach(a => {
                const pc = a.priority==='urgent'?'var(--danger)':a.priority==='high'?'var(--warning)':'var(--success)';
                html += '<div class="cc-list-row">' +
                    '<div class="cc-dot" style="background:' + pc + '"></div>' +
                    '<div class="cc-list-main"><strong>' + esc(a.title) + '</strong>' +
                    '<span>' + fmtDate(a.published_at) + '</span></div>' +
                    '<span class="cc-badge cc-badge-' + (a.priority||'normal') + '">' + esc(a.priority||'normal') + '</span>' +
                    (a.is_pinned ? '<span class="cc-badge cc-badge-gold">Pinned</span>' : '') + '</div>';
            });
        }
        html += '</div></div>';

        html += '<div class="cc-card"><div class="cc-card-head"><h3>Recent Board Activity</h3>' +
            '<button class="cc-btn cc-btn-ghost cc-btn-sm" onclick="window.CareerConnect.nav(\'boards\')" type="button">View all</button></div>' +
            '<div class="cc-list">';
        if (!stats.recent_posts?.length) {
            html += emptyState('No board posts yet');
        } else {
            stats.recent_posts.forEach(p => {
                html += '<div class="cc-list-row">' +
                    '<div class="cc-avatar">' + (p.author?.name||'U')[0].toUpperCase() + '</div>' +
                    '<div class="cc-list-main"><strong>' + esc(p.title) + '</strong>' +
                    '<span>' + esc(p.board?.name||'Board') + ' · ' + esc(p.author?.name||'Faculty') + ' · ' + fmtDate(p.created_at) + '</span></div></div>';
            });
        }
        html += '</div></div></div>';

        // My department card
        if (dept) {
            html += '<div class="cc-card" id="dept-card">' +
                '<div class="cc-card-head"><h3>My Department</h3>' +
                '<button class="cc-btn cc-btn-ghost cc-btn-sm" id="btn-view-dept" type="button">View details</button></div>' +
                '<div class="cc-card-body" style="padding:.75rem 1.25rem;">' +
                '<p style="color:var(--text-muted);font-size:.85rem;">Department: <strong style="color:var(--text-main);">' + esc(dept) + '</strong></p></div>' +
                '<div id="dept-detail-panel"></div></div>';
        }

        html += '</div>';
        main.innerHTML = html;

        document.getElementById('btn-view-dept')?.addEventListener('click', async () => {
            const panel = document.getElementById('dept-detail-panel');
            if (panel.innerHTML) { panel.innerHTML = ''; return; }
            panel.innerHTML = '<div class="cc-loading" style="padding:1rem;"><div class="cc-spinner" style="width:18px;height:18px;border-width:3px;"></div><span>Loading…</span></div>';
            try {
                const depts = await api('/departments');
                const mine  = depts.find(d => d.code === dept);
                if (!mine) { panel.innerHTML = ''; return; }
                panel.innerHTML = await buildDeptDetail(mine.id);
            } catch(err) { panel.innerHTML = '<div class="cc-alert cc-alert-danger" style="margin:1rem;">' + esc(err.message) + '</div>'; }
        });
    }

    function metricCard(icon, label, value, color) {
        return '<div class="cc-metric">' +
            '<div class="cc-metric-icon" style="background:' + color + '18;color:' + color + ';">' + icon + '</div>' +
            '<div class="cc-metric-val" style="color:' + color + ';">' + (value??0) + '</div>' +
            '<div class="cc-metric-label">' + esc(label) + '</div></div>';
    }

    async function buildDeptDetail(id) {
        const data = await api('/departments/' + id);
        let html = '<div style="padding:1rem 1.25rem;border-top:1px solid var(--line);">';
        html += '<div class="cc-grid-2" style="margin-bottom:1rem;">';

        html += '<div><div class="cc-section-label">Announcements</div><div class="cc-list cc-list-bordered">';
        if (!data.announcements?.length) { html += emptyState('None'); }
        else data.announcements.forEach(a => {
            html += '<div class="cc-list-row"><div class="cc-dot" style="background:' + (a.priority==='urgent'?'var(--danger)':a.priority==='high'?'var(--warning)':'var(--success)') + '"></div>' +
                '<div class="cc-list-main"><strong>' + esc(a.title) + '</strong><span>' + fmtDate(a.published_at) + '</span></div>' +
                '<span class="cc-badge cc-badge-' + (a.priority||'normal') + '">' + esc(a.priority||'normal') + '</span></div>';
        });
        html += '</div></div>';

        html += '<div><div class="cc-section-label">Faculty (' + data.faculty_count + ')</div><div style="display:flex;flex-wrap:wrap;gap:.4rem;padding:.5rem 0;">';
        (data.faculty||[]).forEach(f => {
            html += '<div class="cc-faculty-chip"><div class="cc-avatar cc-avatar-sm">' + f.name[0].toUpperCase() + '</div><span>' + esc(f.name) + '</span><span class="cc-badge cc-badge-default" style="font-size:.6rem;">' + esc(f.role) + '</span></div>';
        });
        if (!data.faculty?.length) html += '<span style="color:var(--text-muted);font-size:.82rem;">No faculty listed</span>';
        html += '</div></div></div></div>';
        return html;
    }

    /* ══════════════════════════════════════════════════════
       ANNOUNCEMENTS
    ══════════════════════════════════════════════════════ */
    async function renderAnnouncements(main) {
        const data  = await api('/announcements');
        const items = data.data || data;
        const role  = state.profile?.role;
        const canCreate = ['admin','instructor','admission_officer'].includes(role);
        const pBorder = { urgent:'var(--danger)', high:'var(--warning)', normal:'var(--accent)', low:'var(--info)' };

        main.innerHTML = '<div class="cc-page-wrap">' +
            '<div class="cc-page-header">' +
            '<div><h1>Announcements</h1><p>Official notices with role-based visibility and priority levels.</p></div>' +
            (canCreate ? '<button class="cc-btn cc-btn-primary" id="btn-new-ann" type="button">+ Publish</button>' : '') +
            '</div>' +
            '<div class="cc-filter-bar">' +
            '<select id="ann-filter-p" class="cc-select"><option value="">All priorities</option><option value="urgent">Urgent</option><option value="high">High</option><option value="normal">Normal</option><option value="low">Low</option></select>' +
            '<input type="search" id="ann-filter-q" class="cc-input cc-input-search" placeholder="Search announcements…">' +
            '</div>' +
            '<div id="ann-list">' + renderAnnList(items, role, pBorder) + '</div>' +
            '<div id="ann-modal"></div></div>';

        document.getElementById('btn-new-ann')?.addEventListener('click', () => openAnnModal(null));
        document.getElementById('ann-filter-q')?.addEventListener('input', e => {
            const q = e.target.value.toLowerCase();
            document.getElementById('ann-list').innerHTML = renderAnnList(items.filter(a => a.title.toLowerCase().includes(q) || a.content?.toLowerCase().includes(q)), role, pBorder);
            wireAnnActions(items, role);
        });
        document.getElementById('ann-filter-p')?.addEventListener('change', e => {
            const p = e.target.value;
            document.getElementById('ann-list').innerHTML = renderAnnList(p ? items.filter(a => a.priority===p) : items, role, pBorder);
            wireAnnActions(items, role);
        });
        wireAnnActions(items, role);
    }

    function renderAnnList(items, role, pBorder) {
        if (!items?.length) return emptyState('No announcements found', 'Try adjusting your filters.');
        const canEdit = ['admin','instructor','admission_officer'].includes(role);
        return items.map((a, i) => {
            const border = pBorder?.[a.priority] || 'var(--accent)';
            return '<div class="cc-ann-card" style="animation-delay:' + (i*30) + 'ms;border-left-color:' + border + ';">' +
                '<div class="cc-ann-head">' +
                '<h3>' + esc(a.title) + '</h3>' +
                '<div class="cc-ann-badges">' +
                (a.is_pinned ? '<span class="cc-badge cc-badge-gold">📌 Pinned</span>' : '') +
                '<span class="cc-badge cc-badge-' + (a.priority||'normal') + '">' + esc(a.priority||'normal') + '</span>' +
                '<span class="cc-badge cc-badge-default">' + esc(a.visibility||'all') + '</span>' +
                '</div></div>' +
                '<p class="cc-ann-body">' + esc(trunc(a.content, 260)) + '</p>' +
                '<div class="cc-ann-meta">' +
                '<span>' + fmtDate(a.published_at) + '</span>' +
                (a.author ? '<span>by ' + esc(a.author.name) + '</span>' : '') +
                '<span>' + (a.views_count??0) + ' views</span>' +
                (a.expires_at ? '<span class="cc-ann-expires">Expires ' + fmtDate(a.expires_at) + '</span>' : '') +
                (canEdit ? '<div class="cc-ann-actions">' +
                    '<button class="cc-btn cc-btn-sm cc-btn-secondary ann-edit" data-id="' + a.id + '" type="button">Edit</button>' +
                    '<button class="cc-btn cc-btn-sm cc-btn-danger ann-del" data-id="' + a.id + '" type="button">Delete</button>' +
                    '</div>' : '') +
                '</div></div>';
        }).join('');
    }

    function wireAnnActions(items, role) {
        document.querySelectorAll('.ann-edit').forEach(btn => btn.addEventListener('click', e => {
            e.stopPropagation();
            const ann = items.find(a => a.id == btn.dataset.id);
            if (ann) openAnnModal(ann);
        }));
        document.querySelectorAll('.ann-del').forEach(btn => btn.addEventListener('click', async e => {
            e.stopPropagation();
            if (!confirm('Delete this announcement?')) return;
            btn.disabled = true; btn.textContent = '…';
            try {
                await api('/announcements/' + btn.dataset.id, { method:'DELETE' });
                toast('Announcement deleted', 'success');
                navigate('announcements');
            } catch(err) { toast(err.message||'Failed to delete', 'error'); btn.disabled=false; btn.textContent='Delete'; }
        }));
    }

    function openAnnModal(ann) {
        const modal = document.getElementById('ann-modal');
        const isEdit = !!ann;
        modal.innerHTML = '<div class="cc-overlay" id="ann-ov">' +
            '<div class="cc-modal"><div class="cc-modal-head">' +
            '<span>' + (isEdit?'Edit':'Publish') + ' Announcement</span>' +
            '<button class="cc-modal-x" id="ann-x" type="button">✕</button></div>' +
            '<div class="cc-modal-body"><form id="ann-form">' +
            '<div class="cc-form-group"><label>Title <span class="req">*</span></label>' +
            '<input name="title" required maxlength="255" value="' + esc(ann?.title||'') + '" placeholder="Announcement title…"></div>' +
            '<div class="cc-form-group"><label>Content <span class="req">*</span></label>' +
            '<textarea name="content" rows="5" required placeholder="Write your announcement…">' + esc(ann?.content||'') + '</textarea></div>' +
            '<div class="cc-form-row">' +
            '<div class="cc-form-group"><label>Priority</label><select name="priority">' +
            ['normal','high','urgent','low'].map(p => '<option value="' + p + '"' + ((ann?.priority||'normal')===p?' selected':'') + '>' + p.charAt(0).toUpperCase()+p.slice(1) + '</option>').join('') +
            '</select></div>' +
            '<div class="cc-form-group"><label>Visibility</label><select name="visibility" id="ann-vis">' +
            ['all','department','role'].map(v => '<option value="' + v + '"' + ((ann?.visibility||'all')===v?' selected':'') + '>' + {all:'All faculty',department:'My department',role:'Specific roles'}[v] + '</option>').join('') +
            '</select></div></div>' +
            '<div id="ann-roles" style="display:' + (ann?.visibility==='role'?'block':'none') + ';">' +
            '<div class="cc-form-group"><label>Target Roles <span class="req">*</span></label>' +
            '<div class="cc-check-group">' +
            ['instructor','admission_officer','librarian','cashier'].map(r =>
                '<label class="cc-check-label"><input type="checkbox" name="target_roles[]" value="' + r + '"' + (ann?.target_roles?.includes(r)?' checked':'') + '> ' + r.replace(/_/g,' ') + '</label>'
            ).join('') + '</div></div></div>' +
            '<div class="cc-form-row">' +
            '<div class="cc-form-group"><label>Expires at <span class="cc-optional">(optional)</span></label>' +
            '<input name="expires_at" type="datetime-local" value="' + (ann?.expires_at?ann.expires_at.slice(0,16):'') + '"></div>' +
            '<div class="cc-form-group cc-form-check-wrap"><label class="cc-check-label"><input type="checkbox" name="is_pinned" value="1"' + (ann?.is_pinned?' checked':'') + '> Pin this announcement</label></div>' +
            '</div>' +
            '<div class="cc-form-actions"><button type="button" class="cc-btn cc-btn-secondary" id="ann-cancel">Cancel</button>' +
            '<button type="submit" class="cc-btn cc-btn-primary">' + (isEdit?'Save changes':'Publish') + '</button></div>' +
            '</form></div></div></div>';

        const close = () => { modal.innerHTML = ''; };
        document.getElementById('ann-x').onclick = close;
        document.getElementById('ann-cancel').onclick = close;
        document.getElementById('ann-ov').addEventListener('click', e => { if (e.target.id==='ann-ov') close(); });
        document.getElementById('ann-vis').addEventListener('change', e => {
            document.getElementById('ann-roles').style.display = e.target.value==='role' ? 'block' : 'none';
        });
        charCounter(modal.querySelector('textarea[name="content"]'), 5000);

        document.getElementById('ann-form').onsubmit = async e => {
            e.preventDefault();
            if (!submitGuard(e.target, isEdit?'Saving…':'Publishing…')) return;
            const fd = new FormData(e.target);
            const body = { title:fd.get('title'), content:fd.get('content'), priority:fd.get('priority'), visibility:fd.get('visibility'), is_pinned:fd.get('is_pinned')==='1' };
            if (fd.get('expires_at')) body.expires_at = fd.get('expires_at');
            if (body.visibility==='role') body.target_roles = fd.getAll('target_roles[]');
            close();
            try {
                if (isEdit) await api('/announcements/'+ann.id, {method:'PUT', body});
                else        await api('/announcements', {method:'POST', body});
                toast(isEdit?'Announcement updated':'Announcement published', 'success');
                navigate('announcements');
            } catch(err) { toast(err.message||'Failed to save', 'error'); }
        };
    }

    /* ══════════════════════════════════════════════════════
       BOARDS
    ══════════════════════════════════════════════════════ */
    async function renderBoards(main) {
        const data   = await api('/boards');
        const boards = data.data || data;
        const role   = state.profile?.role;
        const canCreate = ['admin','instructor','admission_officer'].includes(role);

        main.innerHTML = '<div class="cc-page-wrap">' +
            '<div class="cc-page-header"><div><h1>Collaboration Boards</h1><p>Threaded discussions and department coordination channels.</p></div>' +
            (canCreate ? '<button class="cc-btn cc-btn-primary" id="btn-new-board" type="button">+ New Board</button>' : '') +
            '</div>' +
            '<div class="cc-board-grid" id="boards-grid">' +
            (!boards.length ? emptyState('No boards yet', 'Create the first board to get started.') :
                boards.map(b =>
                    '<div class="cc-board-card" data-id="' + b.id + '">' +
                    '<div class="cc-board-card-top">' +
                    '<div class="cc-board-icon-wrap"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg></div>' +
                    '<div class="cc-board-info"><div class="cc-board-name">' + esc(b.name) + '</div>' +
                    '<div class="cc-board-badges">' +
                    '<span class="cc-badge cc-badge-default">' + esc(b.visibility) + '</span>' +
                    (b.is_moderated ? '<span class="cc-badge cc-badge-warning">Moderated</span>' : '') +
                    '</div></div></div>' +
                    '<p class="cc-board-desc">' + esc(trunc(b.description||'Faculty collaboration board.',100)) + '</p>' +
                    '<div class="cc-board-footer"><span class="cc-text-muted">by ' + esc(b.creator?.name||'—') + '</span>' +
                    '<span class="cc-badge cc-badge-primary">' + (b.posts_count??0) + ' posts</span></div></div>'
                ).join('')
            ) +
            '</div>' +
            '<div id="board-detail"></div>' +
            '<div id="board-modal"></div></div>';

        document.querySelectorAll('.cc-board-card').forEach(c =>
            c.addEventListener('click', () => loadBoardDetail(c.dataset.id)));
        document.getElementById('btn-new-board')?.addEventListener('click', () => openBoardModal());
    }

    async function loadBoardDetail(boardId) {
        const el = document.getElementById('board-detail');
        el.innerHTML = '<div class="cc-loading" style="padding:1.5rem;"><div class="cc-spinner" style="width:20px;height:20px;border-width:3px;"></div><span>Loading board…</span></div>';
        el.scrollIntoView({ behavior:'smooth', block:'nearest' });

        const [board, postsData] = await Promise.all([api('/boards/'+boardId), api('/boards/'+boardId+'/posts')]);
        const posts    = postsData.data || postsData;
        const role     = state.profile?.role;
        const userId   = state.profile?.id;
        const canPost  = state.profile?.permissions?.boards_post || ['admin','instructor','admission_officer'].includes(role);
        const canComment = state.profile?.permissions?.boards_comment || ['admin','instructor','admission_officer','librarian','cashier'].includes(role);

        el.innerHTML = '<div class="cc-card" style="margin-top:1.25rem;">' +
            '<div class="cc-card-head">' +
            '<div><h3>' + esc(board.name) + '</h3>' +
            (board.description ? '<p style="color:rgba(255,255,255,.7);font-size:.78rem;margin:.15rem 0 0;">' + esc(board.description) + '</p>' : '') +
            '</div>' +
            '<div style="display:flex;gap:.5rem;align-items:center;">' +
            '<span class="cc-badge" style="background:rgba(255,255,255,.2);color:#fff;border-color:rgba(255,255,255,.3);">' + (board.posts_count??0) + ' posts</span>' +
            (board.is_moderated ? '<span class="cc-badge cc-badge-warning">Moderated</span>' : '') +
            (canPost ? '<button class="cc-btn cc-btn-ghost cc-btn-sm" id="btn-new-post" type="button">+ New Post</button>' : '') +
            '</div></div>' +
            '<div id="post-form-area"></div>' +
            '<div class="cc-list" id="posts-list">' +
            (!posts.length ? emptyState(board.is_moderated ? 'No approved posts yet' : 'No posts yet', board.is_moderated ? 'Posts require moderator approval before appearing.' : 'Be the first to post!') :
                posts.map(p => {
                    const isAuthor = p.author_id==userId || p.author?.id==userId;
                    const canEdit  = isAuthor || role==='admin';
                    return '<div class="cc-post-item" data-post-id="' + p.id + '">' +
                        '<div class="cc-post-header">' +
                        '<div class="cc-avatar">' + (p.author?.name||'U')[0].toUpperCase() + '</div>' +
                        '<div class="cc-post-meta">' +
                        '<strong>' + esc(p.title) + '</strong>' +
                        '<span>by ' + esc(p.author?.name||'—') + ' · ' + fmtDate(p.created_at) + ' · ' + (p.comments_count??0) + ' comments</span>' +
                        '</div>' +
                        '<div style="display:flex;gap:.35rem;align-items:center;flex-shrink:0;">' +
                        (p.is_pinned ? '<span class="cc-badge cc-badge-gold">Pinned</span>' : '') +
                        '<span class="cc-badge cc-badge-' + (p.status==='approved'?'success':'warning') + '">' + esc(p.status||'pending') + '</span>' +
                        '</div></div>' +
                        '<div class="cc-post-actions">' +
                        (canComment ? '<button class="cc-btn cc-btn-sm cc-btn-secondary post-reply-btn" data-post-id="' + p.id + '" type="button">Reply</button>' : '') +
                        (canEdit ? '<button class="cc-btn cc-btn-sm cc-btn-secondary post-edit-btn" data-post-id="' + p.id + '" data-title="' + esc(p.title) + '" data-content="' + esc(p.content||'') + '" type="button">Edit</button>' : '') +
                        (canEdit ? '<button class="cc-btn cc-btn-sm cc-btn-danger post-del-btn" data-post-id="' + p.id + '" type="button">Delete</button>' : '') +
                        '</div>' +
                        '<div class="cc-post-inline-form" id="pif-' + p.id + '"></div>' +
                        '</div>';
                }).join('')
            ) +
            '</div></div>';

        // New post form
        document.getElementById('btn-new-post')?.addEventListener('click', () => {
            const area = document.getElementById('post-form-area');
            if (area.innerHTML) { area.innerHTML = ''; return; }
            area.innerHTML = '<div class="cc-inline-form">' +
                '<form id="post-form">' +
                '<div class="cc-form-group"><label>Title <span class="req">*</span></label><input name="title" required maxlength="255" placeholder="Post title…"></div>' +
                '<div class="cc-form-group"><label>Content <span class="req">*</span></label><textarea name="content" rows="4" required placeholder="Share your thoughts…"></textarea></div>' +
                '<div class="cc-form-actions"><button type="button" class="cc-btn cc-btn-secondary cc-btn-sm" id="cancel-post">Cancel</button>' +
                '<button type="submit" class="cc-btn cc-btn-primary cc-btn-sm">Submit Post</button></div>' +
                '</form></div>';
            charCounter(area.querySelector('textarea'), 10000);
            document.getElementById('cancel-post').onclick = () => { area.innerHTML = ''; };
            document.getElementById('post-form').onsubmit = async e => {
                e.preventDefault();
                if (!submitGuard(e.target, 'Submitting…')) return;
                const body = Object.fromEntries(new FormData(e.target));
                area.innerHTML = '';
                try {
                    await api('/boards/'+boardId+'/posts', {method:'POST', body});
                    toast(board.is_moderated ? 'Post submitted — awaiting approval' : 'Post published!', 'success');
                    await loadBoardDetail(boardId);
                } catch(err) { toast(err.message||'Failed to submit', 'error'); }
            };
        });

        wireBoardPostActions(boardId);
        if (window.CareerConnectRealtime) window.CareerConnectRealtime.subscribeBoard(boardId, () => loadBoardDetail(boardId));
    }

    function wireBoardPostActions(boardId) {
        // Reply / comment
        document.querySelectorAll('.post-reply-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const pid = btn.dataset.postId;
                const wrap = document.getElementById('pif-'+pid);
                if (wrap.innerHTML) { wrap.innerHTML = ''; return; }
                wrap.innerHTML = '<div class="cc-inline-form cc-inline-form-reply">' +
                    '<form class="comment-form" data-post-id="' + pid + '">' +
                    '<div class="cc-form-group"><label>Comment</label><textarea name="content" rows="2" required maxlength="3000" placeholder="Write a comment…"></textarea></div>' +
                    '<div class="cc-form-actions"><button type="button" class="cc-btn cc-btn-secondary cc-btn-sm cancel-comment">Cancel</button>' +
                    '<button type="submit" class="cc-btn cc-btn-primary cc-btn-sm">Post Comment</button></div>' +
                    '</form></div>';
                charCounter(wrap.querySelector('textarea'), 3000);
                wrap.querySelector('.cancel-comment').onclick = () => { wrap.innerHTML = ''; };
                wrap.querySelector('.comment-form').onsubmit = async e => {
                    e.preventDefault();
                    if (!submitGuard(e.target, 'Posting…')) return;
                    const content = new FormData(e.target).get('content');
                    wrap.innerHTML = '';
                    try {
                        await api('/boards/'+boardId+'/posts/'+pid+'/comments', {method:'POST', body:{content}});
                        toast('Comment posted', 'success');
                        loadBoardDetail(boardId);
                    } catch(err) { toast(err.message||'Failed to post comment', 'error'); }
                };
            });
        });

        // Edit post
        document.querySelectorAll('.post-edit-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const pid = btn.dataset.postId;
                const wrap = document.getElementById('pif-'+pid);
                if (wrap.innerHTML) { wrap.innerHTML = ''; return; }
                wrap.innerHTML = '<div class="cc-inline-form">' +
                    '<form class="post-edit-form" data-post-id="' + pid + '">' +
                    '<div class="cc-form-group"><label>Title</label><input name="title" required maxlength="255" value="' + esc(btn.dataset.title) + '"></div>' +
                    '<div class="cc-form-group"><label>Content</label><textarea name="content" rows="3" required>' + esc(btn.dataset.content) + '</textarea></div>' +
                    '<div class="cc-form-actions"><button type="button" class="cc-btn cc-btn-secondary cc-btn-sm cancel-edit">Cancel</button>' +
                    '<button type="submit" class="cc-btn cc-btn-primary cc-btn-sm">Save Changes</button></div>' +
                    '</form></div>';
                wrap.querySelector('.cancel-edit').onclick = () => { wrap.innerHTML = ''; };
                wrap.querySelector('.post-edit-form').onsubmit = async e => {
                    e.preventDefault();
                    if (!submitGuard(e.target, 'Saving…')) return;
                    const body = Object.fromEntries(new FormData(e.target));
                    wrap.innerHTML = '';
                    try {
                        await api('/posts/'+pid, {method:'PUT', body});
                        toast('Post updated', 'success');
                        loadBoardDetail(boardId);
                    } catch(err) { toast(err.message||'Failed to update', 'error'); }
                };
            });
        });

        // Delete post
        document.querySelectorAll('.post-del-btn').forEach(btn => {
            btn.addEventListener('click', async () => {
                if (!confirm('Delete this post and all its comments?')) return;
                btn.disabled = true; btn.textContent = '…';
                try {
                    await api('/posts/'+btn.dataset.postId, {method:'DELETE'});
                    toast('Post deleted', 'success');
                    loadBoardDetail(boardId);
                } catch(err) { toast(err.message||'Failed to delete', 'error'); btn.disabled=false; btn.textContent='Delete'; }
            });
        });
    }

    function openBoardModal() {
        const modal = document.getElementById('board-modal');
        modal.innerHTML = '<div class="cc-overlay" id="board-ov">' +
            '<div class="cc-modal"><div class="cc-modal-head"><span>Create Board</span>' +
            '<button class="cc-modal-x" id="board-x" type="button">✕</button></div>' +
            '<div class="cc-modal-body"><form id="board-form">' +
            '<div class="cc-form-group"><label>Board Name <span class="req">*</span></label><input name="name" required maxlength="255" placeholder="e.g. Faculty Announcements"></div>' +
            '<div class="cc-form-group"><label>Description</label><textarea name="description" rows="3" placeholder="What is this board for?"></textarea></div>' +
            '<div class="cc-form-row">' +
            '<div class="cc-form-group"><label>Visibility</label><select name="visibility" id="board-vis">' +
            '<option value="all">All faculty</option><option value="department">Department only</option><option value="role">Specific roles</option></select></div>' +
            '<div class="cc-form-group"><label>Moderated?</label><select name="is_moderated">' +
            '<option value="0">No — open posting</option><option value="1">Yes — approve posts</option></select></div></div>' +
            '<div id="board-roles" style="display:none;">' +
            '<div class="cc-form-group"><label>Allowed Roles</label><div class="cc-check-group">' +
            ['instructor','admission_officer','librarian','cashier'].map(r =>
                '<label class="cc-check-label"><input type="checkbox" name="allowed_roles[]" value="' + r + '"> ' + r.replace(/_/g,' ') + '</label>'
            ).join('') + '</div></div></div>' +
            '<div class="cc-form-actions"><button type="button" class="cc-btn cc-btn-secondary" id="board-cancel">Cancel</button>' +
            '<button type="submit" class="cc-btn cc-btn-primary">Create Board</button></div>' +
            '</form></div></div></div>';

        const close = () => { modal.innerHTML = ''; };
        document.getElementById('board-x').onclick = close;
        document.getElementById('board-cancel').onclick = close;
        document.getElementById('board-ov').addEventListener('click', e => { if (e.target.id==='board-ov') close(); });
        document.getElementById('board-vis').addEventListener('change', e => {
            document.getElementById('board-roles').style.display = e.target.value==='role' ? 'block' : 'none';
        });
        document.getElementById('board-form').onsubmit = async e => {
            e.preventDefault();
            if (!submitGuard(e.target, 'Creating…')) return;
            const fd = new FormData(e.target);
            const body = { name:fd.get('name'), description:fd.get('description'), visibility:fd.get('visibility'), is_moderated:fd.get('is_moderated')==='1' };
            if (body.visibility==='role') body.allowed_roles = fd.getAll('allowed_roles[]');
            close();
            try {
                await api('/boards', {method:'POST', body});
                toast('Board created', 'success');
                navigate('boards');
            } catch(err) { toast(err.message||'Failed to create board', 'error'); }
        };
    }

    /* ══════════════════════════════════════════════════════
       RESOURCES (browse + manage merged)
    ══════════════════════════════════════════════════════ */
    async function renderResources(main) {
        const role     = state.profile?.role;
        const canManage = ['admin','instructor','librarian'].includes(role);
        const isAdmin   = role === 'admin';

        const [data, cats] = await Promise.all([api('/resources'), api('/resources/categories')]);
        const items = data.data || data;
        const typeColor = { pdf:'#DC2626', link:'#2563EB', video:'#7C3AED', document:'#D97706', guide:'#16A34A' };
        const typeLabel = { pdf:'PDF', link:'Link', video:'Video', document:'Doc', guide:'Guide' };

        let catOpts = '<option value="">All categories</option>';
        cats.forEach(c => { catOpts += '<option value="' + c.id + '">' + esc(c.name) + '</option>'; });

        let html = '<div class="cc-page-wrap">' +
            '<div class="cc-page-header"><div><h1>Career Resources</h1><p>Approved materials for faculty and institutional career support.</p></div>' +
            (canManage ? '<button class="cc-btn cc-btn-primary" id="btn-upload" type="button">+ Upload Resource</button>' : '') +
            '</div>';

        // Manage metrics for admins/managers
        if (canManage) {
            const svgAll  = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>';
            const svgOk   = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>';
            const svgWait = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>';
            const svgStar = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>';
            html += '<div class="cc-metrics" style="grid-template-columns:repeat(4,1fr);">' +
                metricCard(svgAll,  'Total',    items.length,                             '#7C3041') +
                metricCard(svgOk,   'Approved', items.filter(r=>r.is_approved).length,    '#16A34A') +
                metricCard(svgWait, 'Pending',  items.filter(r=>!r.is_approved).length,   '#D97706') +
                metricCard(svgStar, 'Featured', items.filter(r=>r.is_featured).length,    '#7C3AED') +
                '</div>';
        }

        html += '<div class="cc-filter-bar">' +
            '<select id="res-cat" class="cc-select">' + catOpts + '</select>' +
            '<input type="search" id="res-q" class="cc-input cc-input-search" placeholder="Search resources…">' +
            '</div>';

        if (canManage) {
            // Table view for managers
            html += '<div class="cc-card"><div class="cc-card-head"><h3>All Resources</h3></div>' +
                '<div class="cc-list" id="res-list">' + renderResTable(items, typeLabel, typeColor, isAdmin) + '</div></div>';
        } else {
            // Card grid for viewers
            html += '<div class="cc-res-grid" id="res-list">' + renderResCards(items, typeLabel, typeColor) + '</div>';
        }

        html += '<div id="upload-modal"></div></div>';
        main.innerHTML = html;

        document.getElementById('btn-upload')?.addEventListener('click', () => openUploadModal(cats));

        document.getElementById('res-cat').addEventListener('change', async e => {
            const cat = e.target.value;
            const filtered = await api('/resources' + (cat ? '?category='+cat : ''));
            const list = filtered.data || filtered;
            document.getElementById('res-list').innerHTML = canManage ? renderResTable(list, typeLabel, typeColor, isAdmin) : renderResCards(list, typeLabel, typeColor);
            if (isAdmin) wireResAdminActions();
        });
        document.getElementById('res-q').addEventListener('input', e => {
            const q = e.target.value.toLowerCase();
            const filtered = items.filter(r => r.title.toLowerCase().includes(q) || r.description?.toLowerCase().includes(q));
            document.getElementById('res-list').innerHTML = canManage ? renderResTable(filtered, typeLabel, typeColor, isAdmin) : renderResCards(filtered, typeLabel, typeColor);
            if (isAdmin) wireResAdminActions();
        });
        if (isAdmin) wireResAdminActions();
    }

    function renderResCards(items, typeLabel, typeColor) {
        if (!items?.length) return emptyState('No resources found', 'Try a different category or search term.');
        return items.map(r => {
            const bg = typeColor[r.resource_type] || 'var(--primary)';
            return '<div class="cc-res-card">' +
                '<div class="cc-res-card-top">' +
                '<div class="cc-res-icon" style="background:' + bg + ';">' + (typeLabel[r.resource_type]||'Res') + '</div>' +
                '<div><div class="cc-res-title">' + esc(r.title) + '</div>' +
                '<div style="display:flex;gap:.3rem;flex-wrap:wrap;margin-top:.25rem;">' +
                (r.category ? '<span class="cc-badge cc-badge-primary">' + esc(r.category.name) + '</span>' : '') +
                (r.is_featured ? '<span class="cc-badge cc-badge-gold">Featured</span>' : '') +
                '</div></div></div>' +
                '<p class="cc-res-desc">' + esc(trunc(r.description, 110)) + '</p>' +
                '<div class="cc-res-footer">' +
                '<span class="cc-text-muted" style="font-size:.72rem;">' + (r.downloads_count??0) + ' downloads · ' + (r.views_count??0) + ' views</span>' +
                (r.external_url ? '<a href="' + esc(r.external_url) + '" target="_blank" rel="noopener" class="cc-btn cc-btn-primary cc-btn-sm">Open</a>' : '') +
                '</div></div>';
        }).join('');
    }

    function renderResTable(items, typeLabel, typeColor, isAdmin) {
        if (!items?.length) return emptyState('No resources yet', 'Upload the first resource.');
        return items.map(r => {
            const bg = typeColor[r.resource_type] || 'var(--primary)';
            return '<div class="cc-list-row" data-res-id="' + r.id + '">' +
                '<div class="cc-res-icon cc-res-icon-sm" style="background:' + bg + ';">' + (typeLabel[r.resource_type]||'Res') + '</div>' +
                '<div class="cc-list-main">' +
                '<strong>' + esc(r.title) + '</strong>' +
                '<span>' + esc(trunc(r.description,80)) + ' · ' + (r.downloads_count??0) + ' downloads</span>' +
                '<div style="display:flex;gap:.3rem;margin-top:.25rem;flex-wrap:wrap;">' +
                (r.category ? '<span class="cc-badge cc-badge-primary">' + esc(r.category.name) + '</span>' : '') +
                (r.is_approved ? '<span class="cc-badge cc-badge-success">Approved</span>' : '<span class="cc-badge cc-badge-warning">Pending</span>') +
                (r.is_featured ? '<span class="cc-badge cc-badge-gold">Featured</span>' : '') +
                '</div></div>' +
                '<div style="display:flex;gap:.35rem;align-items:center;flex-shrink:0;">' +
                (r.external_url ? '<a href="' + esc(r.external_url) + '" target="_blank" rel="noopener" class="cc-btn cc-btn-secondary cc-btn-sm">Open</a>' : '') +
                (isAdmin && !r.is_approved ? '<button class="cc-btn cc-btn-sm cc-btn-success res-approve" data-id="' + r.id + '" type="button">Approve</button>' : '') +
                (isAdmin ? '<button class="cc-btn cc-btn-sm cc-btn-secondary res-feature" data-id="' + r.id + '" data-featured="' + (r.is_featured?'1':'0') + '" type="button">' + (r.is_featured?'Unfeature':'Feature') + '</button>' : '') +
                '</div></div>';
        }).join('');
    }

    function wireResAdminActions() {
        document.querySelectorAll('.res-approve').forEach(btn => {
            btn.addEventListener('click', async () => {
                btn.disabled=true; btn.textContent='…';
                try { await api('/resources/'+btn.dataset.id+'/approve', {method:'POST'}); toast('Resource approved','success'); navigate('resources'); }
                catch(err) { toast(err.message||'Failed','error'); btn.disabled=false; btn.textContent='Approve'; }
            });
        });
        document.querySelectorAll('.res-feature').forEach(btn => {
            btn.addEventListener('click', async () => {
                const isFeat = btn.dataset.featured==='1';
                btn.disabled=true;
                try { await api('/resources/'+btn.dataset.id+'/feature', {method:'POST', body:{is_featured:!isFeat}}); toast(isFeat?'Unfeatured':'Featured','success'); navigate('resources'); }
                catch(err) { toast(err.message||'Failed','error'); btn.disabled=false; }
            });
        });
    }

    function openUploadModal(cats) {
        const modal = document.getElementById('upload-modal');
        let catOpts = '<option value="">Select category…</option>';
        cats.forEach(c => { catOpts += '<option value="' + c.id + '">' + esc(c.name) + '</option>'; });

        modal.innerHTML = '<div class="cc-overlay" id="upload-ov">' +
            '<div class="cc-modal"><div class="cc-modal-head"><span>Upload Resource</span>' +
            '<button class="cc-modal-x" id="upload-x" type="button">✕</button></div>' +
            '<div class="cc-modal-body"><form id="upload-form">' +
            '<div class="cc-form-group"><label>Title <span class="req">*</span></label><input name="title" required maxlength="255" placeholder="Resource title…"></div>' +
            '<div class="cc-form-group"><label>Description <span class="req">*</span></label><textarea name="description" rows="3" required placeholder="Describe this resource…"></textarea></div>' +
            '<div class="cc-form-row">' +
            '<div class="cc-form-group"><label>Category <span class="req">*</span></label><select name="category_id" required>' + catOpts + '</select></div>' +
            '<div class="cc-form-group"><label>Type <span class="req">*</span></label><select name="resource_type" id="res-type" required>' +
            '<option value="link">Link</option><option value="pdf">PDF</option><option value="video">Video</option><option value="document">Document</option><option value="guide">Guide</option>' +
            '</select></div></div>' +
            '<div class="cc-form-group"><label id="url-label">External URL</label><input name="external_url" type="url" placeholder="https://…"></div>' +
            '<div class="cc-form-actions"><button type="button" class="cc-btn cc-btn-secondary" id="upload-cancel">Cancel</button>' +
            '<button type="submit" class="cc-btn cc-btn-primary">Upload Resource</button></div>' +
            '</form></div></div></div>';

        const close = () => { modal.innerHTML = ''; };
        document.getElementById('upload-x').onclick = close;
        document.getElementById('upload-cancel').onclick = close;
        document.getElementById('upload-ov').addEventListener('click', e => { if (e.target.id==='upload-ov') close(); });
        charCounter(modal.querySelector('textarea'), 2000);

        const typeSelect = modal.querySelector('#res-type');
        const urlInput   = modal.querySelector('input[name="external_url"]');
        const urlLabel   = modal.querySelector('#url-label');
        const updateUrl  = () => {
            const needs = ['link','video'].includes(typeSelect.value);
            urlInput.required = needs;
            urlLabel.innerHTML = 'External URL' + (needs ? ' <span class="req">*</span>' : ' <span class="cc-optional">(optional)</span>');
        };
        typeSelect.addEventListener('change', updateUrl); updateUrl();

        document.getElementById('upload-form').onsubmit = async e => {
            e.preventDefault();
            if (!submitGuard(e.target, 'Uploading…')) return;
            const body = Object.fromEntries(new FormData(e.target));
            if (!body.external_url) delete body.external_url;
            close();
            try {
                await api('/resources', {method:'POST', body});
                toast('Resource uploaded — pending approval', 'success');
                navigate('resources');
            } catch(err) { toast(err.message||'Failed to upload', 'error'); }
        };
    }

    /* ══════════════════════════════════════════════════════
       MESSAGES
    ══════════════════════════════════════════════════════ */
    async function renderMessages(main) {
        if (state.activeThreadId) return renderThreadView(main, state.activeThreadId);

        const role    = state.profile?.role;
        const canSend = state.profile?.permissions?.messages_send || ['admin','instructor'].includes(role);

        const [threadsData, directory] = await Promise.all([
            api('/messages/threads'),
            canSend ? api('/messages/directory').catch(()=>[]) : Promise.resolve([]),
        ]);
        state.threads = threadsData.data || threadsData;
        state.facultyDirectory = directory;

        main.innerHTML = '<div class="cc-page-wrap">' +
            '<div class="cc-page-header"><div><h1>Secure Messaging</h1><p>Encrypted institutional messages between faculty members.</p></div>' +
            (canSend ? '<button class="cc-btn cc-btn-primary" id="btn-new-thread" type="button">+ New Thread</button>' : '') +
            '</div>' +
            (!canSend ? '<div class="cc-alert cc-alert-info">Your role can read messages but cannot start new threads.</div>' : '') +
            '<div class="cc-card"><div class="cc-card-head"><h3>Conversations</h3>' +
            '<span class="cc-badge" style="background:rgba(255,255,255,.2);color:#fff;border-color:rgba(255,255,255,.3);">' + state.threads.length + '</span></div>' +
            '<div class="cc-list" id="thread-list">' +
            (!state.threads.length ? emptyState('No conversations yet', canSend ? 'Start a new thread to message a colleague.' : '') :
                state.threads.map(t => {
                    const preview = t.messages?.[0]?.content ? trunc(t.messages[0].content, 60) : 'No messages yet';
                    return '<div class="cc-thread-row" data-id="' + t.id + '">' +
                        '<div class="cc-thread-av">' + (t.subject||'M')[0].toUpperCase() + '</div>' +
                        '<div class="cc-thread-info"><div class="cc-thread-subj">' + esc(t.subject) + '</div>' +
                        '<div class="cc-thread-prev">' + esc(preview) + '</div></div>' +
                        '<span class="cc-thread-time">' + fmtDate(t.last_message_at||t.updated_at) + '</span></div>';
                }).join('')
            ) +
            '</div></div>' +
            '<div id="thread-modal"></div></div>';

        document.getElementById('btn-new-thread')?.addEventListener('click', openThreadModal);
        document.querySelectorAll('.cc-thread-row').forEach(r =>
            r.addEventListener('click', async () => { state.activeThreadId = r.dataset.id; await renderMessages(main); }));
    }

    async function renderThreadView(main, threadId) {
        const thread  = await api('/messages/threads/'+threadId);
        const canSend = state.profile?.permissions?.messages_send || ['admin','instructor'].includes(state.profile?.role);
        const myId    = state.profile?.id;

        const dirMap = {};
        (state.facultyDirectory||[]).forEach(u => { dirMap[u.id] = u.name; });
        if (thread.creator) dirMap[thread.creator.id] = thread.creator.name;
        const participantNames = (thread.participants||[]).map(id => dirMap[id]||('#'+id)).join(', ');

        main.innerHTML = '<div class="cc-page-wrap">' +
            '<div class="cc-page-header">' +
            '<div><h1>' + esc(thread.subject) + '</h1><p style="font-size:.8rem;color:var(--text-muted);">Participants: ' + esc(participantNames) + '</p></div>' +
            '<button class="cc-btn cc-btn-secondary" id="btn-back" type="button">← Back</button>' +
            '</div>' +
            '<div class="cc-card">' +
            '<div class="cc-msg-list" id="msg-list">' +
            (!thread.messages?.length ? '<div style="padding:2rem;text-align:center;color:var(--text-muted);">No messages yet. Start the conversation!</div>' :
                thread.messages.map(m => {
                    const mine = m.sender_id==myId || m.sender?.id==myId;
                    return '<div class="cc-msg' + (mine?' cc-msg-mine':'') + '">' +
                        '<div class="cc-msg-sender">' + esc(m.sender?.name||'Faculty') + '</div>' +
                        '<div class="cc-msg-body">' + esc(m.content) + '</div>' +
                        '<div class="cc-msg-time">' + fmtDate(m.created_at) + '</div></div>';
                }).join('')
            ) +
            '</div>' +
            (canSend ?
                '<div class="cc-msg-input"><form id="reply-form">' +
                '<textarea name="content" class="cc-msg-ta" rows="2" placeholder="Type a secure message…" maxlength="5000"></textarea>' +
                '<button type="submit" class="cc-btn cc-btn-primary">Send</button>' +
                '</form></div>' :
                '<div class="cc-msg-readonly">Read-only — your role cannot send messages</div>'
            ) +
            '</div></div>';

        document.getElementById('btn-back').onclick = async () => { state.activeThreadId = null; await renderMessages(main); };

        const replyForm = document.getElementById('reply-form');
        if (replyForm) {
            charCounter(replyForm.querySelector('textarea'), 5000);
            replyForm.onsubmit = async e => {
                e.preventDefault();
                const content = new FormData(e.target).get('content');
                if (!content?.trim()) return;
                if (!submitGuard(e.target, 'Sending…')) return;
                try {
                    await api('/messages/threads/'+threadId+'/send', {method:'POST', body:{content}});
                    e.target.reset();
                    state.activeThreadId = threadId;
                    await renderMessages(main);
                    toast('Message sent', 'success');
                } catch(err) {
                    toast(err.message||'Failed to send', 'error');
                    const btn = e.target.querySelector('[type="submit"]');
                    if (btn) { btn.disabled=false; btn.textContent='Send'; }
                }
            };
        }
        document.getElementById('msg-list')?.scrollTo(0, 99999);
        if (window.CareerConnectRealtime)
            window.CareerConnectRealtime.subscribeThread(threadId, async () => { state.activeThreadId=threadId; await renderMessages(main); });
    }

    function openThreadModal() {
        const modal = document.getElementById('thread-modal');
        let opts = state.facultyDirectory?.length
            ? state.facultyDirectory.map(u => '<option value="' + u.id + '">' + esc(u.name) + ' — ' + esc(u.role.replace(/_/g,' ')) + (u.department?' ('+esc(u.department)+')':'') + '</option>').join('')
            : '<option disabled>No faculty available</option>';

        modal.innerHTML = '<div class="cc-overlay" id="thread-ov">' +
            '<div class="cc-modal"><div class="cc-modal-head"><span>New Secure Thread</span>' +
            '<button class="cc-modal-x" id="thread-x" type="button">✕</button></div>' +
            '<div class="cc-modal-body"><form id="thread-form">' +
            '<div class="cc-form-group"><label>Subject <span class="req">*</span></label><input name="subject" required maxlength="255" placeholder="Thread subject…"></div>' +
            '<div class="cc-form-group"><label>Participants <span class="req">*</span></label>' +
            '<select name="participants" multiple required size="6" style="width:100%;padding:.5rem;border:1.5px solid var(--line);border-radius:var(--radius-sm);font-family:inherit;font-size:.85rem;">' + opts + '</select>' +
            '<p class="cc-form-hint">Hold Ctrl / Cmd to select multiple</p></div>' +
            '<div class="cc-form-actions"><button type="button" class="cc-btn cc-btn-secondary" id="thread-cancel">Cancel</button>' +
            '<button type="submit" class="cc-btn cc-btn-primary">Create Thread</button></div>' +
            '</form></div></div></div>';

        const close = () => { modal.innerHTML = ''; };
        document.getElementById('thread-x').onclick = close;
        document.getElementById('thread-cancel').onclick = close;
        document.getElementById('thread-ov').addEventListener('click', e => { if (e.target.id==='thread-ov') close(); });
        document.getElementById('thread-form').onsubmit = async e => {
            e.preventDefault();
            if (!submitGuard(e.target, 'Creating…')) return;
            const fd = new FormData(e.target);
            const participants = Array.from(fd.getAll('participants')).map(Number);
            if (!participants.length) { toast('Select at least one participant','error'); const b=e.target.querySelector('[type="submit"]'); if(b){b.disabled=false;b.textContent='Create Thread';} return; }
            close();
            try {
                await api('/messages/threads', {method:'POST', body:{subject:fd.get('subject'), participants}});
                toast('Thread created', 'success');
                navigate('messages');
            } catch(err) { toast(err.message||'Failed to create thread','error'); }
        };
    }

    /* ══════════════════════════════════════════════════════
       ACTIVITY
    ══════════════════════════════════════════════════════ */
    async function renderActivity(main) {
        const isAdmin = state.profile?.role === 'admin';
        const data    = await api('/activity?per_page=50');
        const items   = data.data || data || [];

        main.innerHTML = '<div class="cc-page-wrap">' +
            '<div class="cc-page-header"><div><h1>Activity Log</h1>' +
            '<p>' + (isAdmin ? 'Full system audit trail — all faculty actions.' : 'Your personal activity history.') + '</p></div>' +
            (isAdmin ? '<button class="cc-btn cc-btn-secondary" id="btn-analytics" type="button">Analytics</button>' : '') +
            '</div>' +
            (isAdmin ? '<div id="analytics-panel" class="hidden" style="margin-bottom:1.25rem;"></div>' : '') +
            '<div class="cc-filter-bar">' +
            '<select id="act-type" class="cc-select"><option value="">All actions</option><option value="created">Created</option><option value="updated">Updated</option><option value="deleted">Deleted</option><option value="posted">Posted</option><option value="commented">Commented</option></select>' +
            '<select id="act-entity" class="cc-select"><option value="">All entities</option><option value="announcement">Announcement</option><option value="board">Board</option><option value="post">Post</option><option value="resource">Resource</option><option value="comment">Comment</option></select>' +
            '<button class="cc-btn cc-btn-primary cc-btn-sm" id="act-filter" type="button">Filter</button>' +
            '</div>' +
            '<div class="cc-card"><div class="cc-card-head"><h3>Recent Activity</h3>' +
            '<span class="cc-badge" style="background:rgba(255,255,255,.2);color:#fff;border-color:rgba(255,255,255,.3);">' + items.length + ' records</span></div>' +
            '<div class="cc-list" id="act-list">' + renderActivityRows(items) + '</div></div></div>';

        document.getElementById('act-filter')?.addEventListener('click', async () => {
            const type   = document.getElementById('act-type').value;
            const entity = document.getElementById('act-entity').value;
            const params = new URLSearchParams();
            if (type)   params.set('type', type);
            if (entity) params.set('entity', entity);
            const filtered = await api('/activity?' + params.toString());
            document.getElementById('act-list').innerHTML = renderActivityRows(filtered.data||filtered||[]);
        });

        document.getElementById('btn-analytics')?.addEventListener('click', async () => {
            const panel = document.getElementById('analytics-panel');
            if (!panel.classList.contains('hidden')) { panel.classList.add('hidden'); return; }
            panel.innerHTML = '<div class="cc-loading" style="padding:1rem;"><div class="cc-spinner" style="width:18px;height:18px;border-width:3px;"></div><span>Loading analytics…</span></div>';
            panel.classList.remove('hidden');
            try {
                const [eng, del_] = await Promise.all([api('/analytics/engagement'), api('/analytics/announcement-delivery')]);
                let aHtml = '<div class="cc-grid-2">';
                aHtml += '<div class="cc-card" style="margin-bottom:0;"><div class="cc-card-head"><h3>Board Engagement</h3></div><div class="cc-list">';
                if (!eng.boards?.length) { aHtml += emptyState('No data'); }
                else eng.boards.forEach(b => {
                    aHtml += '<div class="cc-list-row"><div class="cc-list-main"><strong>' + esc(b.board_name) + '</strong>' +
                        '<span>' + b.total_posts + ' posts · ' + b.total_comments + ' comments · ' + b.total_post_views + ' views</span></div></div>';
                });
                aHtml += '</div></div>';
                aHtml += '<div class="cc-card" style="margin-bottom:0;"><div class="cc-card-head"><h3>Announcement Delivery</h3></div><div class="cc-list">';
                if (!del_.data?.length) { aHtml += emptyState('No data'); }
                else del_.data.slice(0,8).forEach(a => {
                    const pc = a.priority==='urgent'?'var(--danger)':a.priority==='high'?'var(--warning)':'var(--success)';
                    aHtml += '<div class="cc-list-row"><div class="cc-dot" style="background:' + pc + '"></div>' +
                        '<div class="cc-list-main"><strong>' + esc(a.title) + '</strong>' +
                        '<span>' + a.notifications_sent + ' sent · ' + a.notifications_read + ' read · ' + a.views_count + ' views</span></div>' +
                        '<span class="cc-badge cc-badge-' + (a.priority||'normal') + '">' + esc(a.priority) + '</span></div>';
                });
                aHtml += '</div></div></div>';
                panel.innerHTML = aHtml;
            } catch(err) { panel.innerHTML = '<div class="cc-alert cc-alert-danger">' + esc(err.message) + '</div>'; }
        });
    }

    function renderActivityRows(items) {
        if (!items?.length) return emptyState('No activity records');
        const meta = { created:{cls:'created',label:'Created'}, updated:{cls:'updated',label:'Updated'}, deleted:{cls:'deleted',label:'Deleted'}, posted:{cls:'created',label:'Posted'}, commented:{cls:'created',label:'Commented'} };
        return items.map(a => {
            const m = meta[a.action] || { cls:'', label:a.action };
            return '<div class="cc-act-row">' +
                '<div class="cc-act-dot ' + m.cls + '">' + m.label[0] + '</div>' +
                '<div class="cc-list-main">' +
                '<strong>' + m.label + ' <span style="color:var(--text-muted);font-weight:500;">' + esc(a.entity_type||'') + (a.entity_id?' #'+a.entity_id:'') + '</span></strong>' +
                (a.user ? '<span>' +
                    esc(a.user.name) + ' <span class="cc-badge cc-badge-primary" style="font-size:.6rem;">' + esc(a.user.role) + '</span></span>' : '') +
                '</div>' +
                '<span class="cc-act-time">' + fmtDate(a.created_at) + '</span></div>';
        }).join('');
    }

})(); // end IIFE
