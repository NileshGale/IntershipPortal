/**
 * CareerFlow - Company JS
 */

document.addEventListener('DOMContentLoaded', async () => {
    // 1. Verify Authentication & Role
    const auth = await checkAuth('company');
    if (!auth) return;

    // 2. Identify Current Page
    const path = window.location.pathname;
    
    if (path.includes('dashboard.html')) initDashboard();
    else if (path.includes('profile.html')) initProfile();
    else if (path.includes('opportunities.html')) initOpportunities();
    else if (path.includes('applications.html')) initApplications();
    else if (path.includes('interviews.html')) initInterviews();
    else if (path.includes('offers.html')) initOffers();
});

// ── Dashboard ───────────────────────────────────────────────
async function initDashboard() {
    const r = await apiCall('company', 'dashboard');
    if (r.error) return;

    // Approval Warning
    if (!r.is_approved) {
        const w = document.getElementById('approvalWarning');
        if (w) w.innerHTML = `<div class="alert alert-warning mb-24">
            <strong>Account Pending Approval</strong><br>
            Your company profile is currently under review by the college placement cell. 
            You can complete your profile, but you cannot post opportunities until approved.
        </div>`;
    }

    // Stats
    document.getElementById('stat-active').textContent = r.stats.active_postings;
    document.getElementById('stat-apps').textContent = r.stats.new_applications;
    document.getElementById('stat-ints').textContent = r.stats.upcoming_interviews;
    document.getElementById('stat-hires').textContent = r.stats.total_hires;

    // Recent Apps
    const atb = document.getElementById('recentAppsBody');
    if (atb) {
        atb.innerHTML = (r.recentApps || []).map(a => `
            <tr>
                <td><strong>${a.student_name}</strong><br><small>${a.branch}</small></td>
                <td>${a.title}</td>
                <td>${formatDate(a.applied_at)}</td>
                <td><span class="badge ${getBadgeClass(a.status)}">${a.status}</span></td>
                <td><a href="applications.html?opp_id=${a.opportunity_id}" class="btn btn-xs btn-outline">Review</a></td>
            </tr>
        `).join('') || '<tr><td colspan="5" class="text-center">No recent applications</td></tr>';
    }

    // Upcoming Interviews
    const itb = document.getElementById('upcomingIntBody');
    if (itb) {
        itb.innerHTML = r.upcomingInterviews.map(i => `
            <tr>
                <td>${formatDate(i.interview_date)}<br><small>${i.interview_time}</small></td>
                <td><strong>${i.student_name}</strong><br><small>${i.job_title}</small></td>
                <td>${i.round_name || `Round ${i.round_number}`}</td>
                <td><span class="badge ${i.mode==='Online'?'badge-purple':'badge-info'}">${i.mode}</span></td>
                <td>
                    ${i.mode==='Online' && i.meeting_link ? `<a href="${i.meeting_link}" target="_blank" class="btn btn-xs btn-primary">Join</a>` : i.venue||'-'}
                </td>
            </tr>
        `).join('') || '<tr><td colspan="5" class="text-center">No upcoming interviews</td></tr>';
    }
}

// ── Profile ─────────────────────────────────────────────────
async function initProfile() {
    const res = await apiCall('company', 'get_profile');
    const c = res.profile || {};
    
    // Auto-fill form
    Object.keys(c).forEach(k => {
        const el = document.querySelector(`[name="${k}"]`);
        if (el) el.value = c[k];
    });

    if (c.is_approved) {
        document.getElementById('statusBadge').innerHTML = '<span class="badge badge-success" style="font-size:.9rem; padding:6px 12px;">✓ Verified Partner</span>';
    } else {
        document.getElementById('statusBadge').innerHTML = '<span class="badge badge-warning" style="font-size:.9rem; padding:6px 12px;">⏳ Pending Approval</span>';
    }

    // Handle updates
    const form = document.getElementById('profileForm');
    if (form) {
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const r = await apiCall('company', 'update_profile', new FormData(form));
            if (r.success) {
                showFlash('Company profile updated successfully!');
                window.scrollTo(0, 0);
            } else showFlash(r.message || 'Error updating profile', 'error');
        });
    }
}

// ── Opportunities ───────────────────────────────────────────
window.deleteOpportunity = async (id) => {
    if (!confirm('Are you sure you want to delete this listing? All applications associated with it will be orphaned or deleted.')) return;
    const fd = new FormData(); fd.append('id', id);
    const r = await apiCall('company', 'delete_opportunity', fd);
    if(r.success) initOpportunities(); else showFlash('Failed to delete', 'error');
};

async function initOpportunities() {
    const res = await apiCall('company', 'get_opportunities');
    
    if(!res.is_approved) {
        const btn = document.getElementById('postOppFormBtn');
        if (btn) { btn.disabled = true; btn.title = 'Account pending approval'; }
    }

    const tbody = document.getElementById('oppsBody');
    if (tbody) {
        tbody.innerHTML = res.opportunities.map(o => `
            <tr>
                <td><strong>${o.title}</strong><br><span class="badge ${o.type==='Placement'?'badge-purple':'badge-info'}">${o.type}</span></td>
                <td>${o.location || '—'}</td>
                <td>${o.positions}</td>
                <td><span class="badge ${getBadgeClass(o.status)}">${o.status}</span></td>
                <td>
                    ${o.is_approved 
                        ? '<span class="badge badge-success">Approved</span>' 
                        : '<span class="badge badge-warning">Pending Review</span>'}
                </td>
                <td><strong>${o.app_count}</strong> <a href="applications.html?opp_id=${o.id}" class="btn btn-xs btn-outline">View</a></td>
                <td>
                    <button class="btn btn-sm btn-danger" onclick="deleteOpportunity(${o.id})">🗑️</button>
                </td>
            </tr>
        `).join('') || '<tr><td colspan="7" class="text-center">No opportunities posted yet.</td></tr>';
    }

    // Form Handle
    const form = document.getElementById('postOpportunityForm');
    if (form) {
        // Toggle salary/stipend
        const typeSel = form.querySelector('[name="type"]');
        const salDiv = document.getElementById('salary_div');
        const stiDiv = document.getElementById('stipend_div');
        const durDiv = document.getElementById('duration_div');
        
        typeSel.addEventListener('change', (e) => {
            if (e.target.value === 'Internship') {
                salDiv.style.display = 'none';
                stiDiv.style.display = 'block';
                durDiv.style.display = 'block';
            } else {
                salDiv.style.display = 'block';
                stiDiv.style.display = 'none';
                durDiv.style.display = 'none';
            }
        });

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const r = await apiCall('company', 'add_opportunity', new FormData(form));
            if (r.success) {
                showFlash('Opportunity posted successfully!');
                form.reset();
                document.getElementById('oppModal').style.display = 'none';
                initOpportunities();
            } else showFlash(r.message || 'Failed to post', 'error');
        });
    }
}

// ── Applications ────────────────────────────────────────────
window.updateAppStatus = async (appId, status) => {
    const fd = new FormData(); fd.append('application_id', appId); fd.append('status', status);
    const r = await apiCall('company', 'update_application', fd);
    if(r.success) document.getElementById('filterForm').dispatchEvent(new Event('submit'));
};

window.scheduleInterview = (appId, studentName) => {
    document.getElementById('int_app_id').value = appId;
    document.getElementById('int_student_name').textContent = studentName;
    document.getElementById('scheduleModal').style.display = 'flex';
};

async function initApplications() {
    const r = await apiCall('company', 'get_opportunities'); // just for dropdown
    const oppFilter = document.getElementById('oppFilter');
    if (oppFilter) {
        oppFilter.innerHTML = '<option value="">All Postings</option>' + 
            r.opportunities.map(o => `<option value="${o.id}">${o.title}</option>`).join('');
            
        // Autoselect from URL if present
        const urlParams = new URLSearchParams(window.location.search);
        if(urlParams.has('opp_id')) {
            oppFilter.value = urlParams.get('opp_id');
        }
    }

    const form = document.getElementById('filterForm');
    const tbody = document.getElementById('appsBody');

    const load = async () => {
        const q = new URLSearchParams(new FormData(form)).toString();
        const res = await fetch(`${API_BASE}company.php?action=get_applications&${q}`).then(r=>r.json());
        
        tbody.innerHTML = res.applications.map(a => `
            <tr>
                <td><strong>${a.student_name}</strong><br>
                    <small>${a.student_email}</small>
                </td>
                <td>${a.branch} <br><small>Roll: ${a.roll_number || '-'}</small></td>
                <td><strong>${a.cgpa}</strong></td>
                <td>${a.title}</td>
                <td><span class="badge ${getBadgeClass(a.status)}">${a.status}</span></td>
                <td>
                    ${a.resume_file 
                        ? `<a href="/CareerFlow/uploads/documents/${a.resume_file}" target="_blank" class="btn btn-xs btn-outline">View Resume</a>` 
                        : 'No Resume'}
                </td>
                <td>
                    ${a.status === 'Applied' || a.status === 'Reviewed' ? `
                        <button class="btn btn-sm btn-success" onclick="updateAppStatus(${a.application_id}, 'Approved')" style="margin-bottom:4px">Approve</button><br>
                        <button class="btn btn-sm btn-danger" onclick="updateAppStatus(${a.application_id}, 'Rejected')">Reject</button>
                    ` : ''}

                    ${['Approved', 'Shortlisted', 'Interview Scheduled'].includes(a.status) ? 
                        `<button class="btn btn-sm btn-primary mt-1" onclick="scheduleInterview(${a.application_id}, '${a.student_name.replace(/'/g, "\\'")}')">Schedule Interview</button>` : ''}
                </td>
            </tr>
        `).join('') || '<tr><td colspan="7" class="text-center">No applications found</td></tr>';
    };

    if(form) {
        form.addEventListener('submit', (e) => { e.preventDefault(); load(); });
        load();
    }

    // Interview form handling
    const intForm = document.getElementById('scheduleIntForm');
    if (intForm) {
        intForm.querySelector('[name="mode"]').addEventListener('change', (e) => {
            const vDiv = document.getElementById('venue_div');
            const lDiv = document.getElementById('link_div');
            if (e.target.value === 'Online') { vDiv.style.display='none'; lDiv.style.display='block'; }
            else { vDiv.style.display='block'; lDiv.style.display='none'; }
        });

        intForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const res = await apiCall('company', 'schedule_interview', new FormData(intForm));
            if (res.success) {
                showFlash('Interview scheduled successfully');
                document.getElementById('scheduleModal').style.display = 'none';
                intForm.reset();
            } else showFlash(res.message, 'error');
        });
    }
}

// ── Interviews ──────────────────────────────────────────────
window.updateIntStatus = async (id, status) => {
    const fd = new FormData(); fd.append('interview_id', id); fd.append('status', status);
    const r = await apiCall('company', 'update_interview', fd);
    if(r.success) location.reload(); else alert('Failed');
};

async function initInterviews() {
    const res = await apiCall('company', 'get_interviews');
    const tbody = document.getElementById('interviewsBody');
    if (tbody) {
        tbody.innerHTML = res.interviews.map(i => `
            <tr>
                <td>${formatDate(i.interview_date)}<br><small>${i.interview_time}</small></td>
                <td><strong>${i.student_name}</strong><br><small>${i.student_email}</small></td>
                <td>${i.job_title}</td>
                <td>Round ${i.round_number}<br><small>${i.round_name||'-'}</small></td>
                <td><span class="badge ${i.mode==='Online'?'badge-purple':'badge-info'}">${i.mode}</span></td>
                <td><span class="badge ${getBadgeClass(i.status)}">${i.status}</span><br>
                    <small style="color:var(--text-muted)">Student: ${i.student_response||'Pending'}</small></td>
                <td>
                    <select class="form-select status-select" style="font-size:.8rem; padding:4px;" onchange="if(this.value) updateIntStatus(${i.id}, this.value)">
                        <option value="">Update...</option>
                        <option value="Completed">Mark Completed</option>
                        <option value="Cancelled">Cancel</option>
                    </select>
                </td>
            </tr>
        `).join('') || '<tr><td colspan="7" class="text-center">No interviews scheduled.</td></tr>';
    }
}

// ── Offers ──────────────────────────────────────────────────
window.extendOfferModal = (appId, studentName, title) => {
    document.getElementById('offer_app_id').value = appId;
    document.getElementById('offer_student_name').textContent = studentName;
    document.getElementById('offer_title').textContent = title;
    const jtField = document.getElementById('offer_job_title');
    if (jtField) jtField.value = title;
    document.getElementById('offerModal').style.display = 'flex';
};

async function initOffers() {
    // 1. Get eligible applications (Includes Interviewed, Approved, etc)
    const elRes = await fetch(`${API_BASE}company.php?action=get_applications`).then(r=>r.json());
    const eligibleApps = elRes.applications.filter(a => ['Approved', 'Shortlisted', 'Interviewed', 'Interview Scheduled'].includes(a.status));
    
    const candBody = document.getElementById('candidatesBody');
    if (candBody) {
        candBody.innerHTML = eligibleApps.map(a => `
            <tr>
                <td><strong>${a.student_name}</strong></td>
                <td>${a.branch}</td>
                <td>${a.title}</td>
                <td><span class="badge ${getBadgeClass(a.status)}">${a.status}</span></td>
                <td>
                    <button class="btn btn-sm btn-success" onclick="extendOfferModal(${a.application_id}, '${a.student_name}', '${a.title}')" style="margin-bottom:4px">Extend Offer</button><br>
                    <button class="btn btn-sm btn-danger" onclick="updateAppStatus(${a.application_id}, 'Rejected')">Reject</button>
                </td>
            </tr>
        `).join('') || '<tr><td colspan="5" class="text-center">No eligible candidates available to extend offers. Shortlist candidates first.</td></tr>';
    }

    // 2. Get existing offers (Placements/Internships extended by this company)
    const offRes = await apiCall('company', 'get_offers');
    const offBody = document.getElementById('offersBody');
    if (offBody) {
        const combined = [...offRes.placements, ...offRes.internships];
        offBody.innerHTML = combined.map(o => `
            <tr>
                <td><strong>${o.student_name}</strong></td>
                <td>${o.job_title || o.title}</td>
                <td><span class="badge ${o.salary?'badge-purple':'badge-info'}">${o.salary?'Placement':'Internship'}</span></td>
                <td>${o.salary || o.stipend || '—'}</td>
                <td>${formatDate(o.joining_date || o.start_date)}</td>
                <td><span class="badge ${getBadgeClass(o.status)}">${o.status}</span></td>
            </tr>
        `).join('') || '<tr><td colspan="6" class="text-center">No offers extended yet.</td></tr>';
    }

    // Handle Form Submit
    const form = document.getElementById('offerForm');
    if (form) {
        form.querySelector('[name="type"]').addEventListener('change', (e) => {
            if(e.target.value === 'internship') {
                document.getElementById('pkgLabel').textContent = 'Monthly Stipend (₹)';
                document.getElementById('endDiv').style.display = 'block';
            } else {
                document.getElementById('pkgLabel').textContent = 'Annual Package (LPA)';
                document.getElementById('endDiv').style.display = 'none';
            }
        });

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const btn = form.querySelector('button[type="submit"]');
            btn.disabled = true; btn.textContent = 'Extending...';
            
            const r = await apiCall('company', 'extend_offer', new FormData(form));
            if(r.success) location.reload();
            else {
                showFlash(r.message||'Failed', 'error');
                btn.disabled = false; btn.textContent = 'Confirm & Extend Offer';
            }
        });
    }
}
