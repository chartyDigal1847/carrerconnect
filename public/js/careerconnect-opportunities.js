/**
 * CareerConnect — recruitment opportunities (jobs, internships, applications).
 * Loaded after careerconnect.js; registers pages on window.CareerConnect.
 */
(function () {
    if (!window.CareerConnect || !window.__CC_BOOT_HOOK__) return;

    const hook = window.__CC_BOOT_HOOK__;
    const { api, esc, emptyState, toast, submitGuard, fmtDate, state } = hook;

    const opp = {
        jobs: [],
        internships: [],
        applications: [],
        activityLog: [],
        loaded: false,
    };

    function canOpp(key) {
        const p = state.profile?.permissions || {};
        if (p[key] === true) return true;
        const caps = state.profile?.capabilities || [];
        const map = {
            opportunities_read: 'opportunities.read',
            opportunities_apply: 'opportunities.apply',
            opportunities_manage: 'opportunities.manage',
            opportunities_approve: 'opportunities.approve',
            opportunities_delete: 'opportunities.delete',
            opportunities_reports: 'opportunities.reports',
        };
        const cap = map[key] || key;
        return caps.some(c => c === cap || (c.endsWith('.*') && cap.startsWith(c.replace('.*', ''))));
    }

    function isStudent() { return state.profile?.role === 'student'; }
    function isFaculty() { return !isStudent(); }

    async function loadOppData() {
        const data = await api('/opportunities/bootstrap');
        opp.jobs = (data.jobs || []).map(j => ({ ...j, status: (j.status || 'open').toLowerCase() }));
        opp.internships = (data.internships || []).map(i => ({ ...i, status: (i.status || 'open').toLowerCase() }));
        opp.applications = data.applications || [];
        opp.activityLog = data.activityLog || [];
        opp.loaded = true;
    }

    hook.extraTabs = function () {
        const tabs = [];
        if (canOpp('opportunities_read')) {
            tabs.push({ page: 'opportunities', label: 'Opportunities', icon: '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/></svg>' });
            tabs.push({ page: 'opp-applications', label: isStudent() ? 'My Applications' : 'Applications', icon: '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>' });
        }
        if (canOpp('opportunities_reports')) {
            tabs.push({ page: 'opp-reports', label: 'Reports', icon: '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>' });
        }
        return tabs;
    };

    hook.facultyOnlyTabs = function () {
        return isFaculty();
    };

    hook.defaultPage = function () {
        if (isStudent()) return 'opportunities';
        return 'dashboard';
    };

    hook.extraPages = {
        opportunities: renderOpportunities,
        'opp-applications': renderOppApplications,
        'opp-reports': renderOppReports,
    };

    hook.afterProfileLoad = async function () {
        if (canOpp('opportunities_read')) {
            try { await loadOppData(); } catch (e) { console.warn('[opportunities]', e); }
        }
    };

    async function renderOpportunities(main) {
        if (!opp.loaded) await loadOppData();
        const canManage = canOpp('opportunities_manage');
        const canApply = canOpp('opportunities_apply');
        const openJobs = opp.jobs.filter(j => j.status === 'open');
        const openIntern = opp.internships.filter(i => i.status === 'open');
        const myApplications = isStudent() ? opp.applications.length : null;

        main.innerHTML = '<div class="cc-page-wrap">' +
            '<div class="cc-page-header"><div><h1>Job &amp; Internship Opportunities</h1>' +
            '<p>Browse open roles and apply when you are ready.</p></div>' +
            (canManage ? '<button type="button" class="cc-btn cc-btn-primary" id="cc-opp-add">+ Add opportunity</button>' : '') +
            '</div>' +
            '<div class="cc-metrics cc-metrics-compact">' +
            metricCard('Open jobs', openJobs.length, 'Available roles') +
            metricCard('Internships', openIntern.length, 'Training placements') +
            (myApplications === null ? metricCard('Applications', opp.applications.length, 'Submitted candidates') : metricCard('My applications', myApplications, 'Submitted by you')) +
            '</div>' +
            '<div class="cc-filter-bar">' +
            '<input type="search" class="cc-input cc-input-search" id="cc-opp-search" placeholder="Search title or company…">' +
            '<select class="cc-select" id="cc-opp-type"><option value="all">All types</option><option value="job">Jobs</option><option value="internship">Internships</option></select>' +
            '</div>' +
            '<div class="cc-opp-grid" id="cc-opp-grid"></div></div>' +
            modalShell();

        function renderGrid() {
            const q = (document.getElementById('cc-opp-search')?.value || '').toLowerCase();
            const type = document.getElementById('cc-opp-type')?.value || 'all';
            const items = [];
            if (type !== 'internship') openJobs.forEach(j => items.push({ kind: 'job', ...j }));
            if (type !== 'job') openIntern.forEach(i => items.push({ kind: 'internship', ...i }));
            const filtered = items.filter(it =>
                !q || (it.title + it.companyName + it.location).toLowerCase().includes(q));

            const grid = document.getElementById('cc-opp-grid');
            if (!filtered.length) {
                grid.innerHTML = emptyState('No open opportunities', 'Check back later or adjust your filters.');
                return;
            }
            grid.innerHTML = filtered.map(it => {
                const meta = it.kind === 'job'
                    ? esc(it.companyName) + ' · ' + esc(it.location) + ' · ' + esc(it.jobType)
                    : esc(it.companyName) + ' · ' + esc(it.location) + ' · ' + esc(it.durationWeeks) + ' weeks';
                let actions = '<button type="button" class="cc-btn cc-btn-secondary cc-btn-sm" data-opp-view="' + it.kind + '-' + it.id + '">View</button>';
                if (canApply && it.status === 'open') {
                    actions += ' <button type="button" class="cc-btn cc-btn-primary cc-btn-sm" data-opp-apply="' + it.kind + '-' + it.id + '">Apply</button>';
                }
                if (canManage) {
                    actions += ' <button type="button" class="cc-btn cc-btn-secondary cc-btn-sm" data-opp-edit="' + it.kind + '-' + it.id + '">Edit</button>';
                    actions += ' <button type="button" class="cc-btn cc-btn-secondary cc-btn-sm" data-opp-toggle="' + it.kind + '-' + it.id + '">' + (it.status === 'open' ? 'Close' : 'Reopen') + '</button>';
                }
                if (canOpp('opportunities_delete')) {
                    actions += ' <button type="button" class="cc-btn cc-btn-danger cc-btn-sm" data-opp-del="' + it.kind + '-' + it.id + '">Delete</button>';
                }
                return '<article class="cc-opp-card"><div class="cc-opp-card-head">' +
                    '<span class="cc-badge cc-badge-' + (it.kind === 'job' ? 'primary' : 'gold') + '">' + (it.kind === 'job' ? 'Job' : 'Internship') + '</span>' +
                    '<span class="cc-badge cc-badge-success">' + esc(it.status) + '</span></div>' +
                    '<h3>' + esc(it.title) + '</h3><p class="cc-opp-meta">' + meta + '</p>' +
                    '<p class="cc-opp-desc">' + esc(trunc(it.description, 120)) + '</p>' +
                    '<div class="cc-opp-actions">' + actions + '</div></article>';
            }).join('');

            grid.querySelectorAll('[data-opp-view]').forEach(btn => btn.addEventListener('click', () => openView(btn.dataset.oppView)));
            grid.querySelectorAll('[data-opp-apply]').forEach(btn => btn.addEventListener('click', () => openApply(btn.dataset.oppApply)));
            grid.querySelectorAll('[data-opp-edit]').forEach(btn => btn.addEventListener('click', () => openEdit(btn.dataset.oppEdit)));
            grid.querySelectorAll('[data-opp-toggle]').forEach(btn => btn.addEventListener('click', () => toggleStatus(btn.dataset.oppToggle)));
            grid.querySelectorAll('[data-opp-del]').forEach(btn => btn.addEventListener('click', () => deleteOpp(btn.dataset.oppDel)));
        }

        document.getElementById('cc-opp-search')?.addEventListener('input', renderGrid);
        document.getElementById('cc-opp-type')?.addEventListener('change', renderGrid);
        document.getElementById('cc-opp-add')?.addEventListener('click', () => openEdit(null));
        renderGrid();
    }

    async function renderOppApplications(main) {
        if (!opp.loaded) await loadOppData();
        const canApprove = canOpp('opportunities_approve');
        const student = isStudent();

        main.innerHTML = '<div class="cc-page-wrap">' +
            '<div class="cc-page-header"><div><h1>' + (student ? 'My Applications' : 'Applications') + '</h1>' +
            '<p>' + (student ? 'Track status of your submissions.' : 'Review and manage applicant queue.') + '</p></div></div>' +
            (student ? '' : '<div class="cc-filter-bar">' +
            '<input type="search" class="cc-input cc-input-search" id="cc-app-search" placeholder="Search applicant or position…">' +
            '<select class="cc-select" id="cc-app-status"><option value="all">All statuses</option><option value="pending">Pending</option><option value="accepted">Accepted</option><option value="rejected">Rejected</option></select></div>') +
            '<div class="cc-list cc-list-bordered" id="cc-app-list"></div></div>';

        function renderList() {
            let list = opp.applications.slice();
            if (!student) {
                const q = (document.getElementById('cc-app-search')?.value || '').toLowerCase();
                const st = document.getElementById('cc-app-status')?.value || 'all';
                list = list.filter(a => {
                    const matchQ = !q || (a.studentName + a.position).toLowerCase().includes(q);
                    const matchS = st === 'all' || a.status === st;
                    return matchQ && matchS;
                });
            }
            const el = document.getElementById('cc-app-list');
            if (!list.length) {
                el.innerHTML = emptyState('No applications yet');
                return;
            }
            el.innerHTML = list.map(a => {
                const badge = a.status === 'accepted' ? 'success' : a.status === 'rejected' ? 'danger' : 'warning';
                let actions = '';
                if (canApprove) {
                    if (a.status !== 'accepted') actions += '<button type="button" class="cc-btn cc-btn-success cc-btn-sm" data-app-accept="' + a.id + '">Accept</button> ';
                    if (a.status !== 'rejected') actions += '<button type="button" class="cc-btn cc-btn-danger cc-btn-sm" data-app-reject="' + a.id + '">Reject</button> ';
                }
                if (canOpp('opportunities_delete')) {
                    actions += '<button type="button" class="cc-btn cc-btn-danger cc-btn-sm" data-app-del="' + a.id + '">Delete</button>';
                }
                return '<div class="cc-list-row"><div class="cc-list-main"><strong>' + esc(a.studentName) + '</strong>' +
                    '<span>' + esc(a.position) + ' · ' + esc(a.email) + ' · ' + esc(a.dateApplied || '') + '</span></div>' +
                    '<span class="cc-badge cc-badge-' + badge + '">' + esc(a.status) + '</span>' +
                    (actions ? '<div class="cc-row-actions">' + actions + '</div>' : '') + '</div>';
            }).join('');

            el.querySelectorAll('[data-app-accept]').forEach(b => b.addEventListener('click', () => setAppStatus(b.dataset.appAccept, 'accepted')));
            el.querySelectorAll('[data-app-reject]').forEach(b => b.addEventListener('click', () => setAppStatus(b.dataset.appReject, 'rejected')));
            el.querySelectorAll('[data-app-del]').forEach(b => b.addEventListener('click', () => deleteApp(b.dataset.appDel)));
        }

        document.getElementById('cc-app-search')?.addEventListener('input', renderList);
        document.getElementById('cc-app-status')?.addEventListener('change', renderList);
        renderList();
    }

    async function renderOppReports(main) {
        const data = await api('/opportunities/reports');
        const total = data.total_applications || 0;
        const rate = data.acceptance_rate || 0;

        main.innerHTML = '<div class="cc-page-wrap">' +
            '<div class="cc-page-header"><div><h1>Recruitment Reports</h1><p>Application pipeline and open role metrics.</p></div></div>' +
            '<div class="cc-metrics">' +
            metricCard('Total applications', total, 'Applicants') +
            metricCard('Accepted', data.accepted || 0, rate + '% acceptance') +
            metricCard('Pending', data.pending || 0, 'Awaiting review') +
            metricCard('Open jobs', data.open_jobs || 0, (data.closed_jobs || 0) + ' closed') +
            '</div>' +
            '<div class="cc-card"><div class="cc-card-head"><h3>Recent activity</h3></div><div class="cc-card-body" id="cc-rpt-activity">' +
            (opp.activityLog.length ? '' : emptyState('No recruitment activity yet')) + '</div></div></div>';

        const act = document.getElementById('cc-rpt-activity');
        if (opp.activityLog.length) {
            act.innerHTML = '<div class="cc-list">' + opp.activityLog.map(l =>
                '<div class="cc-list-row"><div class="cc-list-main"><strong>' + esc(l.message) + '</strong></div>' +
                '<span class="cc-act-time">' + fmtDate(l.at) + '</span></div>'
            ).join('') + '</div>';
        }
    }

    function metricCard(label, val, sub) {
        return '<div class="cc-metric"><div class="cc-metric-val">' + val + '</div>' +
            '<div class="cc-metric-label">' + esc(label) + '</div><p class="cc-metric-sub">' + esc(sub) + '</p></div>';
    }

    function trunc(s, n) { s = String(s || ''); return s.length > n ? s.slice(0, n) + '…' : s; }

    function modalShell() {
        return '<div class="cc-modal-overlay hidden" id="cc-opp-modal"><div class="cc-modal">' +
            '<div class="cc-modal-head"><h3 id="cc-opp-modal-title">Opportunity</h3>' +
            '<button type="button" class="cc-modal-close" id="cc-opp-modal-close">&times;</button></div>' +
            '<div class="cc-modal-body" id="cc-opp-modal-body"></div></div></div>';
    }

    function openModal(title, html) {
        let overlay = document.getElementById('cc-opp-modal');
        if (!overlay) {
            document.body.insertAdjacentHTML('beforeend', modalShell());
            overlay = document.getElementById('cc-opp-modal');
            document.getElementById('cc-opp-modal-close')?.addEventListener('click', () => overlay.classList.add('hidden'));
            overlay.addEventListener('click', e => { if (e.target === overlay) overlay.classList.add('hidden'); });
        }
        document.getElementById('cc-opp-modal-title').textContent = title;
        document.getElementById('cc-opp-modal-body').innerHTML = html;
        overlay.classList.remove('hidden');
    }

    function findOpp(key) {
        const [kind, id] = key.split('-');
        const num = parseInt(id, 10);
        return kind === 'job' ? opp.jobs.find(j => j.id === num) : opp.internships.find(i => i.id === num);
    }

    function openView(key) {
        const it = findOpp(key);
        if (!it) return;
        const kind = key.startsWith('job') ? 'Job' : 'Internship';
        openModal(it.title, '<p>' + esc(it.description) + '</p><p class="cc-opp-meta">' + esc(it.companyName) + ' · ' + esc(it.location) + '</p>');
    }

    function openApply(key) {
        const it = findOpp(key);
        if (!it) return;
        const isJob = key.startsWith('job');
        openModal('Apply — ' + it.title,
            '<form id="cc-apply-form"><div class="cc-form-group"><label>Phone (optional)</label><input class="cc-input" name="phone"></div>' +
            '<div class="cc-form-group"><label>Resume link (optional)</label><input class="cc-input" name="resume"></div>' +
            '<div class="cc-form-actions"><button type="submit" class="cc-btn cc-btn-primary">Submit application</button></div></form>');
        document.getElementById('cc-apply-form').onsubmit = async e => {
            e.preventDefault();
            const fd = new FormData(e.target);
            try {
                await api('/opportunities/applications', {
                    method: 'POST',
                    body: {
                        ...(isJob ? { jobId: it.id } : { internshipId: it.id }),
                        phone: fd.get('phone') || null,
                        resume: fd.get('resume') || null,
                    },
                });
                toast('Application submitted', 'success');
                document.getElementById('cc-opp-modal').classList.add('hidden');
                await loadOppData();
                hook.nav(state.page);
            } catch (err) { toast(err.message, 'error'); }
        };
    }

    function openEdit(key) {
        const existing = key ? findOpp(key) : null;
        const isIntern = key ? key.startsWith('internship') : false;
        openModal(existing ? 'Edit opportunity' : 'New opportunity',
            '<form id="cc-edit-opp-form">' +
            '<div class="cc-form-row"><div class="cc-form-group"><label>Type</label><select class="cc-select" name="kind" id="cc-opp-kind">' +
            '<option value="job">Job</option><option value="internship">Internship</option></select></div>' +
            '<div class="cc-form-group"><label>Work setup</label><select class="cc-select" name="workSetup"><option>hybrid</option><option>on-site</option><option>remote</option></select></div></div>' +
            '<div class="cc-form-group"><label>Title</label><input class="cc-input" name="title" required></div>' +
            '<div class="cc-form-row"><div class="cc-form-group"><label>Company</label><input class="cc-input" name="companyName" required></div>' +
            '<div class="cc-form-group"><label>Location</label><input class="cc-input" name="location" required></div></div>' +
            '<div class="cc-form-group cc-opp-job-only"><label>Job type</label><input class="cc-input" name="jobType" placeholder="full-time"></div>' +
            '<div class="cc-form-group cc-opp-intern-only hidden"><label>Duration (weeks)</label><input class="cc-input" type="number" name="durationWeeks" value="12"></div>' +
            '<div class="cc-form-group"><label>Description</label><textarea class="cc-input" name="description" rows="4" required></textarea></div>' +
            '<div class="cc-form-actions"><button type="submit" class="cc-btn cc-btn-primary">' + (existing ? 'Save' : 'Create') + '</button></div></form>');

        const form = document.getElementById('cc-edit-opp-form');
        const kindSel = document.getElementById('cc-opp-kind');
        kindSel.value = isIntern ? 'internship' : 'job';
        const syncFields = () => {
            const intern = kindSel.value === 'internship';
            form.querySelector('.cc-opp-intern-only')?.classList.toggle('hidden', !intern);
            form.querySelector('.cc-opp-job-only')?.classList.toggle('hidden', intern);
        };
        kindSel.addEventListener('change', syncFields);
        syncFields();

        if (existing) {
            form.querySelector('[name=title]').value = existing.title;
            form.querySelector('[name=companyName]').value = existing.companyName;
            form.querySelector('[name=location]').value = existing.location;
            form.querySelector('[name=workSetup]').value = existing.workSetup || 'hybrid';
            form.querySelector('[name=description]').value = existing.description || '';
            if (isIntern) form.querySelector('[name=durationWeeks]').value = existing.durationWeeks || 12;
            else form.querySelector('[name=jobType]').value = existing.jobType || 'full-time';
        }

        form.onsubmit = async e => {
            e.preventDefault();
            if (!submitGuard(form, 'Saving…')) return;
            const fd = new FormData(form);
            const intern = fd.get('kind') === 'internship';
            const body = {
                title: fd.get('title'),
                companyName: fd.get('companyName'),
                location: fd.get('location'),
                workSetup: fd.get('workSetup'),
                description: fd.get('description'),
            };
            try {
                if (intern) {
                    body.durationWeeks = Number(fd.get('durationWeeks') || 12);
                    if (existing && key.startsWith('internship')) {
                        await api('/opportunities/internships/' + existing.id, { method: 'PUT', body });
                    } else {
                        await api('/opportunities/internships', { method: 'POST', body });
                    }
                } else {
                    body.jobType = fd.get('jobType') || 'full-time';
                    if (existing && key.startsWith('job')) {
                        await api('/opportunities/jobs/' + existing.id, { method: 'PUT', body });
                    } else {
                        await api('/opportunities/jobs', { method: 'POST', body });
                    }
                }
                toast('Saved', 'success');
                document.getElementById('cc-opp-modal').classList.add('hidden');
                await loadOppData();
                hook.nav('opportunities');
            } catch (err) { toast(err.message, 'error'); }
            finally { const btn = form.querySelector('[type=submit]'); if (btn) { btn.disabled = false; btn.textContent = existing ? 'Save' : 'Create'; } }
        };
    }

    async function toggleStatus(key) {
        const it = findOpp(key);
        if (!it) return;
        const next = it.status === 'open' ? 'closed' : 'open';
        const path = key.startsWith('job') ? '/opportunities/jobs/' : '/opportunities/internships/';
        try {
            await api(path + it.id + '/status', { method: 'PATCH', body: { status: next } });
            await loadOppData();
            hook.nav('opportunities');
        } catch (err) { toast(err.message, 'error'); }
    }

    async function deleteOpp(key) {
        if (!confirm('Delete this opportunity?')) return;
        const it = findOpp(key);
        const path = key.startsWith('job') ? '/opportunities/jobs/' : '/opportunities/internships/';
        try {
            await api(path + it.id, { method: 'DELETE' });
            await loadOppData();
            hook.nav('opportunities');
        } catch (err) { toast(err.message, 'error'); }
    }

    async function setAppStatus(id, status) {
        try {
            await api('/opportunities/applications/' + id + '/status', { method: 'PATCH', body: { status } });
            await loadOppData();
            hook.nav('opp-applications');
            toast('Application ' + status, 'success');
        } catch (err) { toast(err.message, 'error'); }
    }

    async function deleteApp(id) {
        if (!confirm('Delete this application?')) return;
        try {
            await api('/opportunities/applications/' + id, { method: 'DELETE' });
            await loadOppData();
            hook.nav('opp-applications');
        } catch (err) { toast(err.message, 'error'); }
    }
})();
