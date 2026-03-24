/**
 * CareerFlow - Main JS (API Integration & Shared UI)
 */

const API_BASE = '/CareerFlow/backend/api/';

// ── Authentication Check ────────────────────────────────────
async function checkAuth(requiredRole = null) {
    try {
        const res = await fetch(`${API_BASE}auth.php?action=check_session`);
        const data = await res.json();
        
        if (!data.loggedIn) {
            window.location.href = '/CareerFlow/frontend/pages/login.html';
            return null;
        }
        if (requiredRole && data.role !== requiredRole && data.role !== 'admin') {
            window.location.href = `/CareerFlow/frontend/pages/${data.role}/dashboard.html`;
            return null;
        }
        
        // Render shared UI elements
        renderSidebar(data.role, data.profile_completion);
        renderTopbar(data.name, data.role, data.unread_count, data.notifications);
        
        return data;
    } catch (e) {
        console.error('Auth check failed:', e);
        window.location.href = '/CareerFlow/frontend/pages/login.html';
        return null;
    }
}

// ── Generic Fetch Helper ────────────────────────────────────
async function apiCall(endpoint, action, formData = null) {
    try {
        let options = { method: 'POST' };
        if (formData) {
            formData.append('action', action);
            options.body = formData;
        } else {
            const fd = new FormData();
            fd.append('action', action);
            options.body = fd;
        }
        const res = await fetch(`${API_BASE}${endpoint}.php`, options);
        return await res.json();
    } catch (e) {
        console.error('API Error:', e);
        return { success: false, message: 'Network error occurred.' };
    }
}

// ── Shared UI Rendering ─────────────────────────────────────
function renderSidebar(role, profilePct = null) {
    const sidebar = document.getElementById('sidebar');
    if (!sidebar) return;
    
    let html = `
        <div class="sidebar-brand">
            <span class="brand-icon">🎓</span><span class="brand-text">CareerFlow</span>
            <button class="sidebar-close" onclick="toggleSidebar()">✕</button>
        </div>
    `;

    const path = window.location.pathname;
    
    if (role === 'admin') {
        html += `
            <nav class="sidebar-nav">
                <div class="nav-section-label">Main</div>
                <a href="dashboard.html" class="nav-link ${path.includes('dashboard')?'active':''}"><span class="nav-icon">📊</span> Dashboard</a>
                <a href="announcements.html" class="nav-link ${path.includes('announcements')?'active':''}"><span class="nav-icon">📢</span> Announcements</a>
                <div class="nav-section-label">Users</div>
                <a href="students.html" class="nav-link ${path.includes('students')?'active':''}"><span class="nav-icon">🎓</span> Students</a>
                <a href="companies.html" class="nav-link ${path.includes('companies')?'active':''}"><span class="nav-icon">🏢</span> Companies</a>
                <a href="users.html" class="nav-link ${path.includes('users')?'active':''}"><span class="nav-icon">👥</span> All Users</a>
                <div class="nav-section-label">Recruitment</div>
                <a href="opportunities.html" class="nav-link ${path.includes('opportunities')?'active':''}"><span class="nav-icon">💼</span> Opportunities</a>
                <a href="drives.html" class="nav-link ${path.includes('drives')?'active':''}"><span class="nav-icon">🚀</span> Placement Drives</a>
                <a href="interviews.html" class="nav-link ${path.includes('interviews')?'active':''}"><span class="nav-icon">🗓️</span> Interviews</a>
                <a href="placements.html" class="nav-link ${path.includes('placements')?'active':''}"><span class="nav-icon">🏆</span> Placements</a>
                <div class="nav-section-label">Analytics</div>
                <a href="reports.html" class="nav-link ${path.includes('reports')?'active':''}"><span class="nav-icon">📈</span> Reports</a>
            </nav>
        `;
    } else if (role === 'student') {
        if (profilePct !== null) {
            html += `
            <div style="padding:14px 18px;border-bottom:1px solid rgba(255,255,255,.1);">
                <div style="font-size:.8rem;color:#818cf8;margin-bottom:4px;">Profile Completion</div>
                <div class="progress-bar-wrap">
                    <div class="progress-bar" style="width:${profilePct}%;"></div>
                </div>
                <div style="font-size:.75rem;color:#c7d2fe;margin-top:4px;">${profilePct}% complete</div>
            </div>`;
        }
        html += `
            <nav class="sidebar-nav">
                <div class="nav-section-label">Overview</div>
                <a href="dashboard.html" class="nav-link ${path.includes('dashboard')?'active':''}"><span class="nav-icon">📊</span> Dashboard</a>
                <a href="profile.html" class="nav-link ${path.includes('profile')?'active':''}"><span class="nav-icon">👤</span> My Profile</a>
                <a href="documents.html" class="nav-link ${path.includes('documents')?'active':''}"><span class="nav-icon">📁</span> Documents</a>
                <div class="nav-section-label">Opportunities</div>
                <a href="opportunities.html" class="nav-link ${path.includes('opportunities')?'active':''}"><span class="nav-icon">💼</span> Browse Jobs</a>
                <a href="applications.html" class="nav-link ${path.includes('applications')?'active':''}"><span class="nav-icon">📋</span> My Applications</a>
                <a href="interviews.html" class="nav-link ${path.includes('interviews')?'active':''}"><span class="nav-icon">🗓️</span> Interviews</a>
                <div class="nav-section-label">Tracking</div>
                <a href="internship.html" class="nav-link ${path.includes('internship')?'active':''}"><span class="nav-icon">📌</span> Internship</a>
                <a href="placement.html" class="nav-link ${path.includes('placement')?'active':''}"><span class="nav-icon">🏆</span> Placement</a>
                <a href="notifications.html" class="nav-link ${path.includes('notifications')?'active':''}"><span class="nav-icon">🔔</span> Notifications</a>
            </nav>
        `;
    } else if (role === 'company') {
        html += `
            <nav class="sidebar-nav">
                <div class="nav-section-label">Main</div>
                <a href="dashboard.html" class="nav-link ${path.includes('dashboard')?'active':''}"><span class="nav-icon">📊</span> Dashboard</a>
                <a href="profile.html" class="nav-link ${path.includes('profile')?'active':''}"><span class="nav-icon">🏢</span> Company Profile</a>
                <div class="nav-section-label">Recruitment</div>
                <a href="opportunities.html" class="nav-link ${path.includes('opportunities')?'active':''}"><span class="nav-icon">💼</span> Opportunities</a>
                <a href="applications.html" class="nav-link ${path.includes('applications')?'active':''}"><span class="nav-icon">📋</span> Applicants</a>
                <a href="interviews.html" class="nav-link ${path.includes('interviews')?'active':''}"><span class="nav-icon">🗓️</span> Interviews</a>
                <a href="offers.html" class="nav-link ${path.includes('offers')?'active':''}"><span class="nav-icon">📄</span> Offers</a>
            </nav>
        `;
    }
    
    sidebar.innerHTML = html;
}

function renderTopbar(name, role, unreadCount, notifs) {
    const topbar = document.getElementById('topbar');
    if (!topbar) return;
    
    const pageTitle = document.title.split('|')[0].trim();
    let badgeHtml = unreadCount > 0 ? `<span class="notif-badge">${unreadCount}</span>` : '';
    
    let notifsHtml = `<div class="notif-header">Notifications</div>`;
    if (!notifs || notifs.length === 0) {
        notifsHtml += `<p class="notif-empty">No new notifications</p>`;
    } else {
        notifs.forEach(n => {
            const date = new Date(n.created_at).toLocaleString();
            notifsHtml += `
                <div class="notif-item ${n.type.toLowerCase()}">
                    <strong>${n.title}</strong>
                    <p>${n.message}</p>
                    <small>${date}</small>
                </div>
            `;
        });
    }

    const icon = role === 'admin' ? '🛡️' : (role === 'student' ? '👤' : '🏢');

    topbar.innerHTML = `
        <button class="hamburger" onclick="toggleSidebar()">☰</button>
        <div class="topbar-title">${pageTitle}</div>
        <div class="topbar-right">
            <div class="notif-wrapper">
                <button class="notif-btn" onclick="toggleNotifPanel()">🔔 ${badgeHtml}</button>
                <div class="notif-panel" id="notifPanel">${notifsHtml}</div>
            </div>
            <div class="profile-wrapper">
                <button class="profile-btn" onclick="toggleProfileMenu()">
                    ${icon} ${name} <span class="chevron">▾</span>
                </button>
                <div class="profile-menu" id="profileMenu">
                    ${role !== 'admin' ? `<a href="profile.html">${role==='student'?'My Profile':'Company Profile'}</a>` : ''}
                    <a href="#" onclick="logout()" class="logout-link">🚪 Logout</a>
                </div>
            </div>
        </div>
    `;
}

// ── Shared Actions ──────────────────────────────────────────
async function logout() {
    await apiCall('auth', 'logout');
    window.location.href = '/CareerFlow/frontend/pages/login.html';
}

function toggleSidebar() { document.getElementById('sidebar')?.classList.toggle('open'); }
function toggleNotifPanel() { 
    document.getElementById('notifPanel')?.classList.toggle('open'); 
    document.getElementById('profileMenu')?.classList.remove('open');
}
function toggleProfileMenu() { 
    document.getElementById('profileMenu')?.classList.toggle('open'); 
    document.getElementById('notifPanel')?.classList.remove('open');
}

// ── UI Helpers ──────────────────────────────────────────────
function getBadgeClass(status) {
    const map = {
        'Applied':'badge-gray', 'Shortlisted':'badge-warning', 'Selected':'badge-success', 
        'Rejected':'badge-danger', 'Interview Scheduled':'badge-info', 'Pending Approval':'badge-warning',
        'Open':'badge-success', 'Closed':'badge-gray', 'Active':'badge-info', 'Completed':'badge-success',
        'Terminated':'badge-danger', 'Offered':'badge-info', 'Accepted':'badge-success', 'Joined':'badge-success',
        'Declined':'badge-danger', 'Scheduled':'badge-info', 'Cancelled':'badge-danger', 'Internship':'badge-info',
        'Placement':'badge-purple'
    };
    return map[status] || 'badge-gray';
}

function formatDate(dateStr) {
    if (!dateStr) return '—';
    return new Date(dateStr).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
}

function showFlash(message, type = 'success') {
    let container = document.getElementById('toast-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toast-container';
        document.body.appendChild(container);
    }
    const div = document.createElement('div');
    div.className = `alert alert-${type}`;
    div.innerHTML = message;
    container.appendChild(div);
    setTimeout(() => {
        div.style.opacity = '0';
        setTimeout(() => div.remove(), 300);
    }, 4700);
}

// Global click handler to close dropdowns
document.addEventListener('click', function(e) {
    if (!e.target.closest('.notif-wrapper')) document.getElementById('notifPanel')?.classList.remove('open');
    if (!e.target.closest('.profile-wrapper')) document.getElementById('profileMenu')?.classList.remove('open');
});

// Setup tabs if they exist
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const target = this.dataset.tab;
            this.closest('.tabs').querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(tc => tc.classList.remove('active'));
            this.classList.add('active');
            document.getElementById(target)?.classList.add('active');
        });
    });
});