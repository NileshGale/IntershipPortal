/**
 * CareerFlow - Auth JS
 */

document.addEventListener('DOMContentLoaded', async () => {
    const loginForm = document.getElementById('loginForm');
    const registerForm = document.getElementById('registerForm');
    const forgotForm = document.getElementById('forgotForm');

    // Make sure we're on an auth page, otherwise skip
    if (!loginForm && !registerForm && !forgotForm) {
        // Just check session to redirect to dashboard if logged in
        const res = await fetch('/CareerFlow/backend/api/auth.php?action=check_session');
        const data = await res.json();
        if (data.loggedIn) {
            window.location.href = `/CareerFlow/frontend/pages/${data.role}/dashboard.html`;
        }
        return;
    }

    if (loginForm) {
        loginForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const fd = new FormData(loginForm);
            const res = await apiCall('auth', 'login', fd);
            if (res.success) {
                window.location.href = `/CareerFlow/frontend/pages/${res.role}/dashboard.html`;
            } else {
                showFlash(res.message, 'error');
            }
        });
    }

    if (registerForm) {
        // Toggle fields based on role
        const roleSel = document.querySelector('select[name="role"]');
        if (roleSel) {
            roleSel.addEventListener('change', (e) => {
                const isC = e.target.value === 'company';
                document.getElementById('companyFields').style.display = isC ? 'block' : 'none';
                document.getElementById('studentFields').style.display = isC ? 'none' : 'block';
                
                // Toggle required attributes to prevent form validation errors
                document.querySelectorAll('#companyFields input').forEach(i => i.required = isC);
                document.querySelectorAll('#studentFields input, #studentFields select').forEach(i => i.required = !isC);
            });
            // trigger change on load
            roleSel.dispatchEvent(new Event('change'));
        }

        registerForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const pw = registerForm.querySelector('input[name="password"]').value;
            const cpw = registerForm.querySelector('input[name="confirm_password"]').value;
            if (pw !== cpw) return showFlash('Passwords do not match', 'error');
            if (pw.length < 8) return showFlash('Password must be at least 8 chars', 'error');

            const btn = document.getElementById('regSubmitBtn');
            btn.disabled = true;
            btn.textContent = 'Sending OTP...';

            const fd = new FormData(registerForm);
            const r = await apiCall('auth', 'register_send_otp', fd);
            
            btn.disabled = false;
            btn.textContent = 'Send OTP';

            if (r.success) {
                showFlash('OTP sent to your email!', 'success');
                document.getElementById('step1_reg').style.display = 'none';
                document.getElementById('step2_reg').style.display = 'block';
            } else {
                showFlash(r.message, 'error');
            }
        });

        const verifyBtn = document.getElementById('verifyRegOtpBtn');
        if (verifyBtn) {
            verifyBtn.addEventListener('click', async () => {
                const otp = document.getElementById('reg_otp_input').value;
                if (!otp) return showFlash('Please enter the OTP', 'error');

                verifyBtn.disabled = true;
                verifyBtn.textContent = 'Verifying...';

                const fd = new FormData();
                fd.append('otp', otp);
                const r = await apiCall('auth', 'register_verify_otp', fd);
                
                verifyBtn.disabled = false;
                verifyBtn.textContent = 'Verify & Create Account';

                if (r.success) {
                    alert('Registration successful! You can now login.');
                    window.location.href = 'login.html';
                } else {
                    showFlash(r.message || 'Invalid OTP', 'error');
                }
            });
        }
    }

    if (forgotForm) {
        const step1 = document.getElementById('step1');
        const step2 = document.getElementById('step2');
        const step3 = document.getElementById('step3');
        
        let storedEmail = '';

        document.getElementById('otpBtn').addEventListener('click', async () => {
            const email = document.getElementById('fp_email').value;
            if (!email) return showFlash('Enter your email', 'error');
            storedEmail = email;
            
            const btn = document.getElementById('otpBtn');
            btn.disabled = true;
            btn.textContent = 'Sending...';

            const fd = new FormData(); fd.append('email', email);
            const r = await apiCall('auth', 'send_otp', fd);
            
            if (r.success) {
                showFlash('OTP sent to your email', 'success');
                if (r.dev_otp) console.log('DEV OTP:', r.dev_otp); // For testing since no SMTP
                step1.style.display = 'none';
                step2.style.display = 'block';
            } else {
                showFlash(r.message, 'error');
                btn.disabled = false;
                btn.textContent = 'Send OTP';
            }
        });

        document.getElementById('verifyBtn').addEventListener('click', async () => {
            const otp = document.getElementById('fp_otp').value;
            if (!otp) return showFlash('Enter OTP', 'error');

            const fd = new FormData(); fd.append('email', storedEmail); fd.append('otp', otp);
            const r = await apiCall('auth', 'verify_otp', fd);
            
            if (r.success) {
                step2.style.display = 'none';
                step3.style.display = 'block';
            } else {
                showFlash(r.message, 'error');
            }
        });

        forgotForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const fd = new FormData(forgotForm);
            fd.append('email', storedEmail);
            const r = await apiCall('auth', 'reset_password', fd);
            if (r.success) {
                alert('Password reset successfully!');
                window.location.href = 'login.html';
            } else {
                showFlash(r.message, 'error');
            }
        });
    }
});
