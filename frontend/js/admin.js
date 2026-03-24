/**
 * CareerFlow - Admin JS
 */

document.addEventListener('DOMContentLoaded', async () => {
    // 1. Verify Authentication & Role
    const auth = await checkAuth('admin');
    if (!auth) return;

    // 2. Identify Current Page
    const path = window.location.pathname;
    
    if (path.includes('dashboard.html')) initDashboard();
    else if (path.includes('students.html')) initStudents();
    else if (path.includes('companies.html')) initCompanies();
    else if (path.includes('opportunities.html')) initOpportunities();
    else if (path.includes('drives.html')) initDrives();
    else if (path.includes('interviews.html')) initInterviews();
    else if (path.includes('placements.html')) initPlacements();
    else if (path.includes('announcements.html')) initAnnouncements();
    else if (path.includes('reports.html')) initReports();
    else if (path.includes('users.html')) initUsers();
    else if (path.includes('approvals.html')) initApprovals();
});

// ── Dashboard ───────────────────────────────────────────────
async function initDashboard() {
    const res = await apiCall('admin', 'dashboard_stats');
    if (!res.stats) return;

    // Render Stats
    document.getElementById('stat-students').textContent = res.stats.total_students;
    document.getElementById('stat-companies').textContent = res.stats.total_companies;
    document.getElementById('stat-opps').textContent = res.stats.open_opportunities;
    document.getElementById('stat-placed').textContent = res.stats.placed_students;

    // Render Recent Students
    const stb = document.getElementById('recentStudentsBody');
    if (stb) {
        stb.innerHTML = res.recentStudents.map(s => `
            <tr>
                <td>${s.first_name} ${s.last_name}</td>
                <td>${s.roll_number || '-'}</td>
                <td>${s.branch || '-'}</td>
                <td><span class="${getBadgeClass(s.placement_status)} badge">${s.placement_status}</span></td>
            </tr>
        `).join('') || '<tr><td colspan="4" class="text-center">No recent students found</td></tr>';
    }

    // Render Upcoming Interviews
    const itb = document.getElementById('upcomingIntBody');
    if (itb) {
        itb.innerHTML = res.upcomingInterviews.map(i => `
            <tr>
                <td>${formatDate(i.interview_date)}<br><small>${i.interview_time}</small></td>
                <td><strong>${i.company_name}</strong><br><small>${i.job_title}</small></td>
                <td>${i.student_name}</td>
                <td><span class="badge ${i.mode==='Online'?'badge-purple':'badge-info'}">${i.mode}</span></td>
            </tr>
        `).join('') || '<tr><td colspan="4" class="text-center">No upcoming interviews scheduled</td></tr>';
    }

    // Initialize Charts if Chart.js is loaded
    if (typeof Chart !== 'undefined') {
        const branchCtx = document.getElementById('branchChart');
        if (branchCtx && res.branchData.length) {
            new Chart(branchCtx, {
                type: 'bar',
                data: {
                    labels: res.branchData.map(b => b.branch),
                    datasets: [
                        { label: 'Total Students', data: res.branchData.map(b => b.total), backgroundColor: '#1e1b4b' },
                        { label: 'Placed Students', data: res.branchData.map(b => b.placed), backgroundColor: '#16a34a' }
                    ]
                },
                options: { responsive: true, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } } }
            });
        }

        const appCtx = document.getElementById('appTrendChart');
        if (appCtx && res.monthlyApps.length) {
            new Chart(appCtx, {
                type: 'line',
                data: {
                    labels: res.monthlyApps.map(m => m.month),
                    datasets: [{
                        label: 'Applications', data: res.monthlyApps.map(m => m.count),
                        borderColor: '#4f46e5', backgroundColor: 'rgba(79, 70, 229, 0.1)',
                        tension: 0.4, fill: true
                    }]
                },
                options: { responsive: true, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } } }
            });
        }
    }
}

// ── Shared Generic Delete/Verify ────────────────────────────
async function genericAction(action, id, entityName) {
    if (!confirm(`Are you sure you want to perform this action on ${entityName}?`)) return;
    const fd = new FormData(); fd.append('id', id);
    const r = await apiCall('admin', action, fd);
    if (r.success) window.location.reload();
    else alert('Action failed');
}

window.verifyStudent = (id) => genericAction('verify_student', id, 'this student');
window.deleteStudent = (id) => genericAction('delete_student', id, 'this student');
window.approveCompany = (id) => genericAction('approve_company', id, 'this company');
window.rejectCompany = (id) => genericAction('reject_company', id, 'this company');
window.deleteCompany = (id) => genericAction('delete_company', id, 'this company');
window.approveOpportunity = (id) => genericAction('approve_opportunity', id, 'this opportunity');
window.closeOpportunity = (id) => genericAction('close_opportunity', id, 'this opportunity');
window.deleteOpportunity = (id) => genericAction('delete_opportunity', id, 'this opportunity');
window.deleteAnnouncement = (id) => genericAction('delete_announcement', id, 'this announcement');
window.toggleUser = (id, active) => {
    const fd = new FormData(); fd.append('id', id); fd.append('is_active', active);
    apiCall('admin', 'toggle_user', fd).then(r => r.success ? window.location.reload() : alert('Failed'));
};

window.updateDriveStatus = async (id, status) => {
    const fd = new FormData(); fd.append('id', id); fd.append('status', status);
    const r = await apiCall('admin', 'update_drive_status', fd);
    if(r.success) window.location.reload();
}

// ── Students ────────────────────────────────────────────────
async function initStudents() {
    const form = document.getElementById('searchForm');
    const tbody = document.getElementById('studentsBody');

    const load = async () => {
        const q = new URLSearchParams(new FormData(form)).toString();
        const r = await fetch(`${API_BASE}admin.php?action=get_students&${q}`).then(res=>res.json());
        
        tbody.innerHTML = r.students.map(s => `
            <tr>
                <td><strong>${s.first_name} ${s.last_name}</strong><br><small>${s.email}</small></td>
                <td>${s.roll_number || '—'}</td>
                <td>${s.branch || '—'}</td>
                <td>${s.cgpa || '—'}</td>
                <td><span class="badge ${getBadgeClass(s.placement_status)}">${s.placement_status}</span></td>
                <td>
                    ${s.profile_verified 
                        ? '<span class="badge badge-success">Verified</span>'
                        : `<button class="btn btn-sm btn-success" onclick="verifyStudent(${s.id})" title="Verify Profile">✓ Verify</button>`}
                </td>
                <td>
                    <button class="btn btn-sm btn-danger" onclick="deleteStudent(${s.id})" title="Delete Account">🗑️</button>
                </td>
            </tr>
        `).join('') || '<tr><td colspan="7" class="text-center">No students found</td></tr>';
    };

    form.addEventListener('submit', (e) => { e.preventDefault(); load(); });
    load(); // initial load
}

// ── Companies ───────────────────────────────────────────────
async function initCompanies() {
    const form = document.getElementById('searchForm');
    const tbody = document.getElementById('companiesBody');

    const load = async () => {
        const q = new URLSearchParams(new FormData(form)).toString();
        const r = await fetch(`${API_BASE}admin.php?action=get_companies&${q}`).then(res=>res.json());
        
        tbody.innerHTML = r.companies.map(c => `
            <tr>
                <td><strong>${c.company_name}</strong><br><small>${c.industry || '—'}</small></td>
                <td>${c.hr_name || '—'}<br><small>${c.email}</small></td>
                <td>${c.website ? `<a href="${c.website}" target="_blank">Link ↗</a>` : '—'}</td>
                <td>
                    ${c.is_approved 
                        ? '<span class="badge badge-success">Approved</span>' 
                        : '<span class="badge badge-warning">Pending Approval</span>'}
                </td>
                <td>
                    ${!c.is_approved ? `
                        <button class="btn btn-sm btn-success" onclick="approveCompany(${c.id})" title="Approve">✓</button>
                        <button class="btn btn-sm btn-warning" onclick="rejectCompany(${c.id})" title="Reject">✕</button>
                    ` : ''}
                    <button class="btn btn-sm btn-danger" onclick="deleteCompany(${c.id})" title="Delete">🗑️</button>
                </td>
            </tr>
        `).join('') || '<tr><td colspan="5" class="text-center">No companies found</td></tr>';
    };

    form.addEventListener('submit', (e) => { e.preventDefault(); load(); });
    load();
}

// ── Opportunities ───────────────────────────────────────────
async function initOpportunities() {
    const form = document.getElementById('searchForm');
    const tbody = document.getElementById('oppsBody');

    const load = async () => {
        const q = new URLSearchParams(new FormData(form)).toString();
        const r = await fetch(`${API_BASE}admin.php?action=get_opportunities&${q}`).then(res=>res.json());
        
        tbody.innerHTML = r.opportunities.map(o => `
            <tr>
                <td><strong>${o.title}</strong><br>
                    <span class="badge ${o.type==='Placement'?'badge-purple':'badge-info'}">${o.type}</span>
                </td>
                <td>${o.company_name}</td>
                <td>${o.location || '—'}</td>
                <td>${o.type === 'Placement' ? (o.salary || '—') : (o.stipend || '—')}</td>
                <td><span class="badge ${getBadgeClass(o.status)}">${o.status}</span></td>
                <td>${o.app_count}</td>
                <td>
                    ${!o.is_approved 
                        ? `<button class="btn btn-sm btn-success" onclick="approveOpportunity(${o.id})">Approve</button>`
                        : (o.status === 'Open' ? `<button class="btn btn-sm btn-warning" onclick="closeOpportunity(${o.id})">Close</button>` : '—')
                    }
                    <button class="btn btn-sm btn-danger" onclick="deleteOpportunity(${o.id})" title="Delete">🗑️</button>
                </td>
            </tr>
        `).join('') || '<tr><td colspan="7" class="text-center">No opportunities found</td></tr>';
    };

    form.addEventListener('submit', (e) => { e.preventDefault(); load(); });
    load();
}

// ── Placement Drives ────────────────────────────────────────
window.viewApplicants = async (id) => {
    document.getElementById('applicantsModal').classList.add('open');
    const tbody = document.getElementById('appModalBody');
    tbody.innerHTML = '<tr><td colspan="4" class="text-center">Loading...</td></tr>';
    
    const r = await fetch(`${API_BASE}admin.php?action=get_drive_applicants&drive_id=${id}`).then(res=>res.json());
    if (r.applicants && r.applicants.length > 0) {
        tbody.innerHTML = r.applicants.map(a => `
            <tr>
                <td>${a.first_name} ${a.last_name}</td>
                <td>${a.roll_number || '-'}</td>
                <td>${a.branch || '-'}</td>
                <td>${a.cgpa || '-'}</td>
            </tr>
        `).join('');
    } else {
        tbody.innerHTML = '<tr><td colspan="4" class="text-center">No applicants yet.</td></tr>';
    }
};

async function initDrives() {
    const r = await apiCall('admin', 'get_drives');
    
    // Populate form dropdown
    const sel = document.querySelector('select[name="company_id"]');
    if (sel) {
        sel.innerHTML = '<option value="">-- None (Open for multiple) --</option>' + 
            r.companies.map(c => `<option value="${c.id}">${c.company_name}</option>`).join('');
    }

    // Populate table
    const tbody = document.getElementById('drivesBody');
    if (tbody) {
        tbody.innerHTML = r.drives.map(d => `
            <tr>
                <td><strong>${d.title}</strong><br><small>${d.company_name || 'Open Pool Drive'}</small></td>
                <td>${formatDate(d.drive_date)}</td>
                <td>${d.venue || 'TBA'}</td>
                <td>
                    <button class="btn btn-sm btn-outline" onclick="viewApplicants(${d.id})">${d.applicants || 0} Registered</button>
                </td>
                <td>
                    <select class="form-select" style="font-size:.8rem; padding:4px 8px; width:auto;" onchange="updateDriveStatus(${d.id}, this.value)">
                        <option value="Upcoming" ${d.status==='Upcoming'?'selected':''}>Upcoming</option>
                        <option value="Ongoing" ${d.status==='Ongoing'?'selected':''}>Ongoing</option>
                        <option value="Completed" ${d.status==='Completed'?'selected':''}>Completed</option>
                        <option value="Cancelled" ${d.status==='Cancelled'?'selected':''}>Cancelled</option>
                    </select>
                </td>
            </tr>
        `).join('') || '<tr><td colspan="5" class="text-center">No drives scheduled</td></tr>';
    }

    const form = document.getElementById('addDriveForm');
    if (form) {
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const res = await apiCall('admin', 'add_drive', new FormData(form));
            if (res.success) window.location.reload();
            else showFlash('Failed to add drive', 'error');
        });
    }
}

// ── Interviews ──────────────────────────────────────────────
async function initInterviews() {
    const r = await apiCall('admin', 'get_interviews');
    
    const tbody = document.getElementById('interviewsBody');
    if (tbody) {
        tbody.innerHTML = r.interviews.map(i => `
            <tr>
                <td>${formatDate(i.interview_date)}<br><small>${i.interview_time}</small></td>
                <td><strong>${i.company_name}</strong><br><small>${i.job_title}</small></td>
                <td>${i.student_name}<br><small>${i.student_email}</small></td>
                <td>Round ${i.round_number}<br><small>${i.round_name || '—'}</small></td>
                <td><span class="badge ${i.mode==='Online'?'badge-purple':'badge-info'}">${i.mode}</span></td>
                <td><span class="badge ${getBadgeClass(i.status)}">${i.status}</span></td>
                <td><span class="badge ${getBadgeClass(i.student_response)}">${i.student_response || 'Pending'}</span></td>
            </tr>
        `).join('') || '<tr><td colspan="7" class="text-center">No interviews found</td></tr>';
    }

    // Build Calendar
    const calGrid = document.querySelector('.calendar-grid');
    if (calGrid) {
        const today = new Date();
        const year = today.getFullYear();
        const month = today.getMonth();
        
        document.getElementById('calMonthYear').textContent = today.toLocaleDateString('en-US', {month:'long', year:'numeric'});
        
        const firstDay = new Date(year, month, 1).getDay();
        const daysInMon = new Date(year, month + 1, 0).getDate();
        
        let html = '';
        ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'].forEach(d => {
            html += `<div class="cal-day-header">${d}</div>`;
        });
        
        // Blank days before first day
        for (let i = 0; i < firstDay; i++) {
            html += `<div class="cal-day other-month"></div>`;
        }
        
        // Days
        for (let i = 1; i <= daysInMon; i++) {
            const dateStr = `${year}-${String(month+1).padStart(2,'0')}-${String(i).padStart(2,'0')}`;
            const isToday = (i === today.getDate() && month === today.getMonth());
            
            let evHtml = '';
            if (r.calendarEvents[dateStr]) {
                const evs = r.calendarEvents[dateStr].details.split('|');
                evHtml = evs.slice(0,3).map(e => `<div class="cal-event" title="${e}">${e}</div>`).join('');
                if (evs.length > 3) evHtml += `<div class="cal-event" style="background:var(--text-muted)">+${evs.length-3} more</div>`;
            }
            
            html += `<div class="cal-day ${isToday ? 'today' : ''}">
                <div style="text-align:right; font-weight:600; color:var(--text-muted);">${i}</div>
                ${evHtml}
            </div>`;
        }
        calGrid.innerHTML = html;
    }
}

// ── Placements ──────────────────────────────────────────────
async function initPlacements() {
    const r = await apiCall('admin', 'get_placements');
    
    document.getElementById('stat-placed').textContent = r.totalPlaced;
    document.getElementById('stat-pkg').textContent = r.avgSalary ? `₹${r.avgSalary} LPA` : '-';
    
    const tbody = document.getElementById('placementsBody');
    if (tbody) {
        tbody.innerHTML = r.placements.map(p => `
            <tr>
                <td><strong>${p.student_name}</strong><br><small>${p.roll_number || '-'} | ${p.branch || '-'}</small></td>
                <td><strong>${p.company_name}</strong><br><small>${p.job_title}</small></td>
                <td>${p.salary || '—'}</td>
                <td>${formatDate(p.joining_date)}</td>
                <td><span class="badge ${getBadgeClass(p.status)}">${p.status}</span></td>
                <td>
                    ${p.offer_letter 
                        ? `<a href="/CareerFlow/uploads/offer_letters/${p.offer_letter}" target="_blank" class="btn btn-sm btn-outline">View Letter</a>`
                        : '—'}
                </td>
            </tr>
        `).join('') || '<tr><td colspan="6" class="text-center">No placements recorded yet</td></tr>';
    }

    // ── Populate modal dropdowns ─────────────────────────────
    const listRes = await apiCall('admin', 'get_students_list');
    const allStudents = listRes.students || [];
    const allCompanies = listRes.companies || [];

    // Populate company select
    const compSel = document.getElementById('companySelect');
    if (compSel) {
        compSel.innerHTML = '<option value="">-- Select Company --</option>' +
            allCompanies.map(c => `<option value="${c.id}">${c.company_name}</option>`).join('');
    }

    // ── Searchable student input ─────────────────────────────
    const searchInput = document.getElementById('studentSearch');
    const dropdown = document.getElementById('studentDropdown');
    const hiddenId = document.getElementById('selectedStudentId');

    if (searchInput && dropdown) {
        searchInput.addEventListener('input', () => {
            const val = searchInput.value.toLowerCase().trim();
            hiddenId.value = ''; // clear selection when typing
            if (!val) { dropdown.classList.remove('open'); return; }

            const filtered = allStudents.filter(s => 
                `${s.first_name} ${s.last_name}`.toLowerCase().includes(val) ||
                (s.roll_number && s.roll_number.toLowerCase().includes(val))
            ).slice(0, 15);

            if (filtered.length === 0) {
                dropdown.innerHTML = '<div class="sd-item" style="color:var(--text-muted);cursor:default;">No students found</div>';
            } else {
                dropdown.innerHTML = filtered.map(s => `
                    <div class="sd-item" data-id="${s.id}">
                        <strong>${s.first_name} ${s.last_name}</strong>
                        <span style="float:right;color:var(--text-muted);font-size:.78rem;">${s.roll_number || ''} · ${s.branch || ''}</span>
                        ${s.placement_status === 'Placed' ? '<br><small style="color:var(--warning)">⚠ Already Placed</small>' : ''}
                    </div>
                `).join('');
            }
            dropdown.classList.add('open');
        });

        dropdown.addEventListener('click', (e) => {
            const item = e.target.closest('.sd-item');
            if (!item || !item.dataset.id) return;
            const s = allStudents.find(st => st.id == item.dataset.id);
            if (s) {
                searchInput.value = `${s.first_name} ${s.last_name} (${s.roll_number || 'N/A'})`;
                hiddenId.value = s.id;
                dropdown.classList.remove('open');
            }
        });

        // Close dropdown when clicking outside
        document.addEventListener('click', (e) => {
            if (!e.target.closest('.student-search-wrap')) dropdown.classList.remove('open');
        });
    }

    // ── Form submission ──────────────────────────────────────
    const form = document.getElementById('addPlacementForm');
    if (form) {
        form.addEventListener('submit', async (e) => {
            e.preventDefault();

            if (!hiddenId.value) {
                showFlash('Please select a student from the dropdown.', 'error');
                searchInput.focus();
                return;
            }

            const btn = document.getElementById('submitPlacementBtn');
            btn.disabled = true; btn.textContent = 'Saving...';

            const fd = new FormData(form);
            const res = await apiCall('admin', 'add_placement', fd);

            if (res.success) {
                showFlash('✅ Placement recorded successfully!');
                document.getElementById('placementModal').classList.remove('open');
                form.reset();
                hiddenId.value = '';
                // Reload the page to reflect new data
                setTimeout(() => window.location.reload(), 1000);
            } else {
                showFlash(res.message || 'Failed to record placement', 'error');
                btn.disabled = false; btn.textContent = '💾 Save Placement';
            }
        });
    }
}

// ── Announcements ───────────────────────────────────────────
async function initAnnouncements() {
    const r = await apiCall('admin', 'get_announcements');
    
    const tbody = document.getElementById('announcementsBody');
    if (tbody) {
        tbody.innerHTML = r.announcements.map(a => `
            <tr>
                <td><strong>${a.title}</strong><br><small style="color:var(--text-muted);">${a.content.substring(0,60)}...</small></td>
                <td><span class="badge ${a.target_role==='all'?'badge-purple':(a.target_role==='student'?'badge-info':'badge-warning')}">${a.target_role}</span></td>
                <td>${a.priority}</td>
                <td>${formatDate(a.created_at)}</td>
                <td><button class="btn btn-sm btn-danger" onclick="deleteAnnouncement(${a.id})">🗑️</button></td>
            </tr>
        `).join('') || '<tr><td colspan="5" class="text-center">No announcements</td></tr>';
    }

    const form = document.getElementById('addAnnouncementForm');
    if (form) {
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const res = await apiCall('admin', 'add_announcement', new FormData(form));
            if (res.success) window.location.reload();
            else showFlash('Failed to add', 'error');
        });
    }
}

// ── Reports ─────────────────────────────────────────────────
async function initReports() {
    const r = await apiCall('admin', 'get_reports');
    
    const pTbody = document.getElementById('placementBody');
    if (pTbody) {
        pTbody.innerHTML = r.placement.map(p => `<tr><td>${p.student_name}</td><td>${p.roll_number||'-'}</td><td>${p.branch||'-'}</td><td>${p.year||'-'}</td><td>${p.company_name}</td><td>${p.job_title}</td><td>${p.salary||'-'}</td><td>${formatDate(p.joining_date)}</td><td><span class="badge ${getBadgeClass(p.status)}">${p.status}</span></td></tr>`).join('');
    }

    const iTbody = document.getElementById('internshipBody');
    if (iTbody) {
        iTbody.innerHTML = r.internship.map(i => `<tr><td>${i.student_name}</td><td>${i.roll_number||'-'}</td><td>${i.branch||'-'}</td><td>${i.company_name}</td><td>${i.title}</td><td>${formatDate(i.start_date)} - ${formatDate(i.end_date)}</td><td>${i.stipend||'-'}</td><td><span class="badge ${getBadgeClass(i.status)}">${i.status}</span></td></tr>`).join('');
    }

    const cTbody = document.getElementById('companySummaryBody');
    if (cTbody) {
        cTbody.innerHTML = r.companySummary.map(c => `<tr><td><strong>${c.company_name}</strong></td><td>${c.total_opportunities}</td><td>${c.placements}</td><td>${c.internships}</td><td>${c.total_apps}</td><td><strong>${c.hired}</strong></td></tr>`).join('');
    }

    const bTbody = document.getElementById('branchStatsBody');
    if (bTbody) {
        bTbody.innerHTML = r.branchStats.map(b => `<tr><td><strong>${b.branch}</strong></td><td>${b.total}</td><td>${b.avg_cgpa||'-'}</td><td>${b.interned}</td><td>${b.placed}</td><td>${b.total>0? Math.round((b.placed/b.total)*100) : 0}%</td></tr>`).join('');
    }

    window.exportCSV = (tableId) => {
        let csv = [];
        const rows = document.querySelectorAll(`#${tableId} tr`);
        for (let i = 0; i < rows.length; i++) {
            let row = [], cols = rows[i].querySelectorAll("td, th");
            for (let j = 0; j < cols.length; j++) row.push('"' + cols[j].innerText.replace(/"/g, '""') + '"');
            csv.push(row.join(","));
        }
        const data = new Blob([csv.join("\n")], {type: "text/csv"});
        const link = document.createElement("a");
        link.href = window.URL.createObjectURL(data);
        link.download = `${tableId}_export.csv`;
        link.click();
    };
}

// ── Users ───────────────────────────────────────────────────
async function initUsers() {
    const r = await apiCall('admin', 'get_users');
    const tbody = document.getElementById('usersBody');
    
    if (tbody) {
        tbody.innerHTML = r.users.map(u => `
            <tr>
                <td><strong>${u.email}</strong></td>
                <td><span class="badge ${u.role==='admin'?'badge-purple':(u.role==='student'?'badge-info':'badge-warning')}">${u.role}</span></td>
                <td>${u.display_name}</td>
                <td>${formatDate(u.created_at)}</td>
                <td>
                    ${u.is_active 
                        ? '<span class="badge badge-success">Active</span>' 
                        : '<span class="badge badge-danger">Inactive</span>'}
                </td>
                <td>
                    ${u.id != r.current_user_id ? `
                        ${u.is_active 
                            ? `<button class="btn btn-sm btn-warning" onclick="toggleUser(${u.id}, 0)">Disable</button>` 
                            : `<button class="btn btn-sm btn-success" onclick="toggleUser(${u.id}, 1)">Enable</button>`}
                    ` : '<span style="color:var(--text-muted);font-size:.8rem;">Current Session</span>'}
                </td>
            </tr>
        `).join('') || '<tr><td colspan="6" class="text-center">No users</td></tr>';
    }
}

// ── Approvals ───────────────────────────────────────────────
async function initApprovals() {
    const r = await apiCall('admin', 'get_approvals');
    const cBody = document.getElementById('companyApprovalsBody');
    const sBody = document.getElementById('studentApprovalsBody');
    
    if (cBody && r.companies) {
        cBody.innerHTML = r.companies.map(c => `
            <tr>
                <td><strong>${c.company_name}</strong><br><small>${c.industry || '—'}</small></td>
                <td>${c.hr_name || '—'}</td>
                <td>${c.email}</td>
                <td>${formatDate(c.created_at)}</td>
                <td><span class="badge ${c.is_active ? 'badge-success' : 'badge-warning'}">${c.is_active ? 'Approved' : 'Pending'}</span></td>
                <td>
                    ${!c.is_active ? `
                        <button class="btn btn-sm btn-success" onclick="toggleUser(${c.id}, 1)">Approve</button>
                        <button class="btn btn-sm btn-danger" onclick="deleteUser(${c.id})">Reject (Delete)</button>
                    ` : '<span style="color:var(--text-muted);font-size:.8rem;">Approved</span>'}
                </td>
            </tr>
        `).join('') || '<tr><td colspan="6" class="text-center">No company approvals</td></tr>';
    }

    if (sBody && r.students) {
        sBody.innerHTML = r.students.map(s => `
            <tr>
                <td><strong>${s.first_name} ${s.last_name}</strong></td>
                <td>${s.roll_number || '—'} <br><small>${s.branch || '—'}</small></td>
                <td>${s.email}</td>
                <td>${formatDate(s.created_at)}</td>
                <td><span class="badge ${s.is_active ? 'badge-success' : 'badge-warning'}">${s.is_active ? 'Approved' : 'Pending'}</span></td>
                <td>
                    ${!s.is_active ? `
                        <button class="btn btn-sm btn-success" onclick="toggleUser(${s.id}, 1)">Approve</button>
                        <button class="btn btn-sm btn-danger" onclick="deleteUser(${s.id})">Reject (Delete)</button>
                    ` : '<span style="color:var(--text-muted);font-size:.8rem;">Approved</span>'}
                </td>
            </tr>
        `).join('') || '<tr><td colspan="6" class="text-center">No student approvals</td></tr>';
    }
}

// Global action to completely delete a pending registration
window.deleteUser = async (id) => {
    if (!confirm('Are you sure you want to REJECT and DELETE this registration? This cannot be undone.')) return;
    const fd = new FormData(); fd.append('id', id);
    const r = await apiCall('admin', 'delete_user', fd);
    if(r.success) window.location.reload();
    else alert('Failed to delete user');
};
