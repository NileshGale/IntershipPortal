/**
 * CareerFlow - Student JS
 */

document.addEventListener('DOMContentLoaded', async () => {
    // 1. Verify Authentication & Role
    const auth = await checkAuth('student');
    if (!auth) return;

    // 2. Identify Current Page
    const path = window.location.pathname;
    
    if (path.includes('dashboard.html')) initDashboard();
    else if (path.includes('profile.html')) initProfile();
    else if (path.includes('documents.html')) initDocuments();
    else if (path.includes('opportunities.html')) initOpportunities();
    else if (path.includes('applications.html')) initApplications();
    else if (path.includes('interviews.html')) initInterviews();
    else if (path.includes('internship.html')) initInternship();
    else if (path.includes('placement.html')) initPlacement();
    else if (path.includes('notifications.html')) initNotifications();
});

// ── Dashboard ───────────────────────────────────────────────
async function initDashboard() {
    const r = await apiCall('student', 'dashboard');
    if (r.error) return;

    // Stats
    document.getElementById('stat-apps').textContent = r.stats.apps;
    document.getElementById('stat-short').textContent = r.stats.shortlisted;
    document.getElementById('stat-sel').textContent = r.stats.selected;
    document.getElementById('stat-cgpa').textContent = r.stats.cgpa || '-';

    // Current Status Card
    const csc = document.getElementById('currentStatusCard');
    if (csc) {
        if (r.placement && (r.placement.status === 'Accepted' || r.placement.status === 'Joined')) {
            csc.innerHTML = `
                <div class="alert alert-success mt-3" style="font-size:1.1rem">
                    🎉 Congratulations! You are placed at <strong>${r.placement.company_name}</strong>.
                    <a href="placement.html" class="btn btn-sm btn-outline ml-3">View Details & Offer Letter ↗</a>
                </div>`;
        } else if (r.placement && r.placement.status === 'Offered') {
            csc.innerHTML = `
                <div class="alert alert-info mt-3" style="font-size:1.1rem">
                    📌 You have a new placement offer from <strong>${r.placement.company_name}</strong>.
                    <a href="placement.html" class="btn btn-sm btn-outline ml-3">Review Offer ↗</a>
                </div>`;
        } else if (r.internship) {
            csc.innerHTML = `
                <div class="alert alert-info mt-3" style="font-size:1.1rem">
                    📌 You are currently doing an internship at <strong>${r.internship.company_name}</strong>.
                    <a href="internship.html" class="btn btn-sm btn-outline ml-3">View Details ↗</a>
                </div>`;
        } else {
            csc.innerHTML = `
                <div class="alert alert-warning mt-3">
                    You have not secured a placement or internship yet. Keep applying!
                    <a href="opportunities.html" class="btn btn-sm btn-primary ml-3">Browse Jobs ↗</a>
                </div>`;
        }
    }

    // Profile Alert
    const pAlert = document.getElementById('profileCompletionAlert');
    if (pAlert && r.profilePct < 100) {
        pAlert.innerHTML = `
            <div class="alert alert-warning mb-24">
                <strong>Attention:</strong> Your profile is only ${r.profilePct}% complete! 
                Please complete your profile to improve your chances of getting shortlisted.
                <a href="profile.html" class="btn btn-sm btn-primary" style="margin-left:auto">Complete Profile</a>
            </div>`;
    }

    // Upcoming Interviews
    const itb = document.getElementById('upcomingIntBody');
    if (itb) {
        itb.innerHTML = r.interviews.map(i => `
            <tr>
                <td>${formatDate(i.interview_date)}<br><small>${i.interview_time}</small></td>
                <td><strong>${i.company_name}</strong><br><small>${i.job_title}</small></td>
                <td>Round ${i.round_number}<br><small>${i.round_name||''}</small></td>
                <td><span class="badge ${i.mode==='Online'?'badge-purple':'badge-info'}">${i.mode}</span></td>
                <td>
                    ${i.mode==='Online' && i.meeting_link ? `<a href="${i.meeting_link}" target="_blank" class="btn btn-sm btn-primary">Join</a>` : i.venue||'-'}
                </td>
            </tr>
        `).join('') || '<tr><td colspan="5" class="text-center">No upcoming interviews</td></tr>';
    }

    // Recent Apps
    const atb = document.getElementById('recentAppsBody');
    if (atb) {
        atb.innerHTML = r.recentApps.map(a => `
            <tr>
                <td><strong>${a.title}</strong><br><small>${a.company_name}</small></td>
                <td><span class="badge ${a.type==='Placement'?'badge-purple':'badge-info'}">${a.type}</span></td>
                <td>${formatDate(a.applied_at)}</td>
                <td><span class="badge ${getBadgeClass(a.status)}">${a.status}</span></td>
            </tr>
        `).join('') || '<tr><td colspan="4" class="text-center">No recent applications</td></tr>';
    }

    // Announcements
    const annBox = document.getElementById('announcementsBox');
    if (annBox) {
        annBox.innerHTML = r.announcements.map(a => `
            <div style="padding:16px; border-bottom:1px solid var(--border)">
                <div style="display:flex; justify-content:space-between; margin-bottom:6px;">
                    <strong style="color:var(--text);">${a.title}</strong>
                    <span style="font-size:.75rem; color:var(--text-muted);">${formatDate(a.created_at)}</span>
                </div>
                <p style="font-size:.85rem; color:var(--text-muted); line-height:1.4">${a.content}</p>
            </div>
        `).join('') || '<div style="padding:16px; text-align:center; color:var(--text-muted)">No announcements available.</div>';
    }
}

// ── Profile ─────────────────────────────────────────────────
async function initProfile() {
    const res = await apiCall('student', 'get_profile');
    const p = res.profile || {};
    
    // Auto-fill form
    Object.keys(p).forEach(k => {
        const el = document.querySelector(`[name="${k}"]`);
        if (el) el.value = p[k];
    });
    
    // Status badges
    if (p.profile_verified) document.getElementById('verificationStatus').innerHTML = '<span class="badge badge-success">✓ Verified by TPO</span>';
    else document.getElementById('verificationStatus').innerHTML = '<span class="badge badge-warning">! Pending Verification</span>';
    
    document.getElementById('placementStatusBox').innerHTML = `
        Placement: <span class="badge ${getBadgeClass(p.placement_status)}">${p.placement_status||'Unplaced'}</span><br>
        Internship: <span class="badge ${getBadgeClass(p.internship_status)}">${p.internship_status||'None'}</span>
    `;

    // Handle updates
    const form = document.getElementById('profileForm');
    if (form) {
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const r = await apiCall('student', 'update_profile', new FormData(form));
            if (r.success) {
                showFlash('Profile updated successfully!');
                window.scrollTo(0, 0);
                setTimeout(()=>location.reload(), 1500);
            } else showFlash(r.message || 'Error updating profile', 'error');
        });
    }
}

// ── Documents ───────────────────────────────────────────────
window.deleteDocument = async (id, name) => {
    if(!confirm(`Delete document "${name}"?`)) return;
    const fd = new FormData(); fd.append('id', id);
    const r = await apiCall('student', 'delete_document', fd);
    if(r.success) initDocuments();
};

async function initDocuments() {
    const r = await apiCall('student', 'get_documents');
    const tbody = document.getElementById('docsBody');
    if (tbody) {
        tbody.innerHTML = r.documents.map(d => `
            <tr>
                <td><strong>${d.doc_type}</strong></td>
                <td><a href="/CareerFlow/uploads/documents/${d.file_name}" target="_blank">${d.original_name}</a></td>
                <td>${formatDate(d.uploaded_at)}</td>
                <td>
                    <a href="/CareerFlow/uploads/documents/${d.file_name}" target="_blank" class="btn btn-sm btn-outline" download="${d.original_name}">📥 Download</a>
                    <button class="btn btn-sm btn-danger" onclick="deleteDocument(${d.id}, '${d.original_name}')">🗑️ Delete</button>
                </td>
            </tr>
        `).join('') || '<tr><td colspan="4" class="text-center">No documents uploaded</td></tr>';
    }

    // Upload Handler
    const form = document.getElementById('uploadDocForm');
    if (form) {
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const btn = form.querySelector('button[type="submit"]');
            btn.disabled = true; btn.textContent = 'Uploading...';
            
            const r = await apiCall('student', 'upload_document', new FormData(form));
            if (r.success) {
                form.reset();
                initDocuments();
                showFlash('Document uploaded!');
            } else {
                showFlash(r.message, 'error');
            }
            btn.disabled = false; btn.textContent = 'Upload Document';
        });
    }
}

// ── Opportunities ───────────────────────────────────────────
window.applyForJob = async (id, title) => {
    const cl = prompt(`Applying for: ${title}\n(Optional) Enter your cover letter / notes:`,"");
    if (cl === null) return;
    
    const fd = new FormData(); fd.append('opportunity_id', id); fd.append('cover_letter', cl);
    const r = await apiCall('student', 'apply', fd);
    if (r.success) {
        alert('Application submitted successfully!');
        document.getElementById('searchForm').dispatchEvent(new Event('submit'));
    } else {
        alert(r.message || 'Application failed');
    }
};

async function initOpportunities() {
    const form = document.getElementById('searchForm');
    const box = document.getElementById('oppsBox');
    const dBox = document.getElementById('drivesBox');

    const load = async () => {
        const q = new URLSearchParams(new FormData(form)).toString();
        const r = await fetch(`${API_BASE}student.php?action=get_opportunities&${q}`).then(res=>res.json());
        
        if (dBox && r.drives) {
            dBox.innerHTML = r.drives.map(d => `
                <div class="card" style="margin-bottom:16px; border-left: 4px solid var(--primary);">
                    <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:12px;">
                        <div>
                            <h3 style="font-size:1.1rem; margin-bottom:4px;">${d.title} <span class="badge ${d.status==='Upcoming'?'badge-info':'badge-success'}">${d.status}</span></h3>
                            <div style="color:var(--primary); font-weight:600;">${d.company_name || 'Open Pool Drive'}</div>
                        </div>
                        <div style="text-align:right">
                            <strong style="color:var(--danger)">📅 ${formatDate(d.drive_date)}</strong><br>
                            <small>📍 ${d.venue || 'TBA'}</small>
                        </div>
                    </div>
                    <div style="font-size:.85rem; color:var(--text); margin-bottom:12px;">
                        <strong>Eligibility:</strong> ${d.eligibility_criteria || 'Not specified'}<br>
                        <strong>Rounds:</strong> ${d.rounds || 'Not specified'}
                    </div>
                    <p style="font-size:.88rem; margin-bottom:0">${d.description || ''}</p>
                </div>
            `).join('') || '<div class="text-center" style="padding:20px; color:var(--text-muted)">No upcoming campus drives.</div>';
        }

        box.innerHTML = r.opportunities.map(o => `
            <div class="card" style="margin-bottom:16px;">
                <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:12px;">
                    <div>
                        <h3 style="font-size:1.1rem; margin-bottom:4px;">${o.title}</h3>
                        <div style="color:var(--primary); font-weight:600;">${o.company_name}</div>
                    </div>
                    <span class="badge ${o.type==='Placement'?'badge-purple':'badge-info'}">${o.type}</span>
                </div>
                
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px; font-size:.85rem; color:var(--text-muted); margin-bottom:16px;">
                    <div>📍 ${o.location || 'Not specified'}</div>
                    <div>💰 ${o.type==='Placement' ? (o.salary || 'Competitive') : (o.stipend || 'Unpaid')}</div>
                    <div>⏳ ${o.type==='Internship' ? (o.duration || 'Not specified') : 'Full-time'}</div>
                    <div>🎯 ${o.positions} opening(s)</div>
                    ${o.deadline ? `<div style="grid-column:1/-1; color:var(--danger)">⏰ Apply By: ${formatDate(o.deadline)}</div>` : ''}
                </div>
                
                <p style="font-size:.88rem; margin-bottom:16px;">${o.description.substring(0,150)}...</p>
                
                <div style="display:flex; justify-content:space-between; align-items:center; border-top:1px solid var(--border); padding-top:12px;">
                    <span style="font-size:.75rem; color:var(--text-muted)">Posted: ${formatDate(o.created_at)}</span>
                    ${o.already_applied 
                        ? '<span class="badge badge-success">✓ Already Applied</span>'
                        : `<button class="btn btn-sm btn-primary" onclick="applyForJob(${o.id}, '${o.title}')">Apply Now</button>`}
                </div>
            </div>
        `).join('') || '<div class="card text-center" style="padding:40px; color:var(--text-muted)">No active opportunities available matching your criteria.</div>';
    };

    form.addEventListener('submit', (e) => { e.preventDefault(); load(); });
    load();
}

// ── Applications ────────────────────────────────────────────
async function initApplications() {
    const r = await apiCall('student', 'get_applications');
    const tbody = document.getElementById('appsBody');
    if (tbody) {
        tbody.innerHTML = r.applications.map(a => `
            <tr>
                <td><strong>${a.title}</strong><br><span class="badge ${a.type==='Placement'?'badge-purple':'badge-info'}">${a.type}</span></td>
                <td>${a.company_name}</td>
                <td>${formatDate(a.applied_at)}</td>
                <td><span class="badge ${getBadgeClass(a.status)}">${a.status}</span></td>
            </tr>
        `).join('') || '<tr><td colspan="4" class="text-center">You have not applied to any opportunities yet.</td></tr>';
    }
}

// ── Interviews ──────────────────────────────────────────────
window.respondInterview = async (id, resp) => {
    if(!confirm(`Are you sure you want to ${resp} this interview schedule?`)) return;
    const fd = new FormData(); fd.append('id', id); fd.append('response', resp);
    const r = await apiCall('student', 'respond_interview', fd);
    if(r.success) location.reload(); else alert('Failed');
};

async function initInterviews() {
    const r = await apiCall('student', 'get_interviews');
    const tbody = document.getElementById('interviewsBody');
    if (tbody) {
        tbody.innerHTML = r.interviews.map(i => `
            <tr>
                <td>${formatDate(i.interview_date)}<br><small>${i.interview_time}</small></td>
                <td><strong>${i.company_name}</strong><br><small>${i.job_title}</small></td>
                <td>Round ${i.round_number}<br><small>${i.round_name || '—'}</small></td>
                <td><span class="badge ${i.mode==='Online'?'badge-purple':'badge-info'}">${i.mode}</span></td>
                <td>
                    ${i.mode==='Online' 
                        ? (i.meeting_link ? `<a href="${i.meeting_link}" target="_blank" class="btn btn-xs btn-outline">Join Link ↗</a>` : 'Link Pending') 
                        : (i.venue || 'TBA')}
                </td>
                <td><span class="badge ${getBadgeClass(i.status)}">${i.status}</span></td>
                <td>
                    ${i.student_response 
                        ? `<span class="badge ${getBadgeClass(i.student_response)}">${i.student_response}</span>` 
                        : (i.status === 'Scheduled' && i.interview_date >= new Date().toISOString().split('T')[0] 
                            ? `<button class="btn btn-xs btn-success" onclick="respondInterview(${i.id}, 'accept')">Accept</button> <button class="btn btn-xs btn-danger" onclick="respondInterview(${i.id}, 'decline')">Decline</button>` 
                            : '—')}
                </td>
            </tr>
        `).join('') || '<tr><td colspan="7" class="text-center">No interviews scheduled yet.</td></tr>';
    }
}

// ── Internships ─────────────────────────────────────────────
async function initInternship() {
    const r = await apiCall('student', 'get_internship');
    const tbody = document.getElementById('internsBody');
    if (tbody) {
        tbody.innerHTML = r.internships.map(i => `
            <tr>
                <td><strong>${i.title}</strong><br><small>${i.company_name}</small></td>
                <td>${formatDate(i.start_date)} - ${i.end_date ? formatDate(i.end_date) : 'Present'}</td>
                <td>${i.stipend || 'Unpaid'}</td>
                <td><span class="badge ${getBadgeClass(i.status)}">${i.status}</span></td>
                <td>
                    ${i.completion_certificate 
                        ? `<a href="/CareerFlow/uploads/certificates/${i.completion_certificate}" target="_blank" class="btn btn-sm btn-outline">View Certificate</a>` 
                        : (i.status === 'Completed' ? `<button class="btn btn-sm btn-primary" onclick="showCertForm(${i.id})">Upload Cert</button>` : '—')}
                </td>
            </tr>
        `).join('') || '<tr><td colspan="5" class="text-center">No internship records found.</td></tr>';
    }

    const certForm = document.getElementById('certForm');
    if (certForm) {
        window.showCertForm = (id) => {
            document.getElementById('cert_int_id').value = id;
            certForm.style.display = 'block';
        };
        certForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const r = await apiCall('student', 'upload_certificate', new FormData(certForm));
            if(r.success) location.reload(); else alert(r.message||'Failed');
        });
    }
}

// ── Placements ──────────────────────────────────────────────
window.respondOffer = async (id, resp) => {
    const actionStr = resp === 'accept' ? 'Accept' : 'Decline';
    if (!confirm(`Are you sure you want to ${actionStr} this placement offer?`)) return;
    const fd = new FormData(); fd.append('id', id); fd.append('response', resp);
    const r = await apiCall('student', 'respond_offer', fd);
    if(r.success) {
        showFlash(`Offer ${actionStr}ed successfully!`, 'success');
        setTimeout(() => location.reload(), 1000);
    } else {
        showFlash(r.message || 'Failed to respond', 'error');
    }
};

async function initPlacement() {
    const r = await apiCall('student', 'get_placement');
    const tbody = document.getElementById('placementsBody');
    if (tbody) {
        tbody.innerHTML = r.placements.map(p => `
            <tr>
                <td><strong>${p.company_name}</strong><br><a href="${p.website}" target="_blank" style="font-size:.8rem">${p.website||''}</a></td>
                <td>${p.job_title}</td>
                <td>${p.salary || '—'}</td>
                <td>${formatDate(p.joining_date)}</td>
                <td><span class="badge ${getBadgeClass(p.status)}">${p.status}</span></td>
                <td>
                    ${p.offer_letter 
                        ? `<a href="/CareerFlow/uploads/offer_letters/${p.offer_letter}" target="_blank" class="btn btn-sm btn-outline" download>📥 Download</a>`
                        : '<span style="color:var(--text-muted); font-size:0.85rem">Pending HR Upload</span>'}
                    ${p.status === 'Offered' ? `<br><div style="margin-top:8px"><button class="btn btn-sm btn-success" onclick="respondOffer(${p.id}, 'accept')">Accept</button> <button class="btn btn-sm btn-danger" onclick="respondOffer(${p.id}, 'decline')">Decline</button></div>` : ''}
                </td>
            </tr>
        `).join('') || '<tr><td colspan="6" class="text-center">No placement records found. Keep applying!</td></tr>';
    }
}

// ── Notifications ───────────────────────────────────────────
window.markAllRead = async () => {
    const r = await apiCall('student', 'mark_read');
    if(r.success) {
        showFlash('All notifications marked as read', 'success');
        setTimeout(() => location.reload(), 1000);
    }
};

async function initNotifications() {
    const r = await apiCall('student', 'get_notifications');
    const box = document.getElementById('notifsBox');
    if (box) {
        box.innerHTML = r.notifications.map(n => `
            <div style="padding:16px 20px; border-bottom:1px solid var(--border); background:${n.is_read?'transparent':'var(--surface-2)'}; display:flex; gap:16px;">
                <div style="font-size:24px;">
                    ${n.type==='Success'?'✅':(n.type==='Error'?'❌':(n.type==='Warning'?'⚠️':'ℹ️'))}
                </div>
                <div style="flex:1;">
                    <div style="display:flex; justify-content:space-between; margin-bottom:4px;">
                        <strong style="color:var(--text);">${n.title}</strong>
                        <span style="font-size:.8rem; color:var(--text-muted);">${formatDate(n.created_at)}</span>
                    </div>
                    <p style="font-size:.9rem; color:var(--text); line-height:1.5; margin:0;">${n.message}</p>
                </div>
            </div>
        `).join('') || '<div class="text-center" style="padding:40px; color:var(--text-muted)">You have no notifications.</div>';
    }
}
