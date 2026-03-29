window.togglePassword = function togglePassword() {
    const passwordInput = document.getElementById('password');
    const toggleIcon = document.getElementById('toggleIcon');

    if (!passwordInput || !toggleIcon) {
        return;
    }

    if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        toggleIcon.classList.remove('fa-eye');
        toggleIcon.classList.add('fa-eye-slash');
    } else {
        passwordInput.type = 'password';
        toggleIcon.classList.remove('fa-eye-slash');
        toggleIcon.classList.add('fa-eye');
    }
};

function showWarn(text) {
    const warning = document.getElementById('warn');
    const warnText = document.getElementById('warnText');

    if (!warning || !warnText) {
        return;
    }

    warning.classList.remove('hidden');
    warning.classList.add('block');
    warnText.textContent = text;

    const loginContainer = document.getElementById('loginContainer');
    if (loginContainer) {
        loginContainer.classList.remove('md:p-16');
        loginContainer.classList.add('md:px-16', 'md:py-7');
    }
}

window.closeWarn = function closeWarn() {
    const warning = document.getElementById('warn');
    if (warning) {
        warning.classList.add('hidden');
        warning.classList.remove('block');
    }

    const loginContainer = document.getElementById('loginContainer');
    if (loginContainer) {
        loginContainer.classList.remove('md:px-16', 'md:py-7');
        loginContainer.classList.add('md:p-16');
    }
};

document.addEventListener('DOMContentLoaded', function () {
    const loginForm = document.getElementById('loginForm');

    if (!loginForm) {
        return;
    }

    loginForm.addEventListener('submit', async function (e) {
        e.preventDefault();

        const submitButton = loginForm.querySelector('button[type="submit"]');
        const originalButtonText = submitButton ? submitButton.textContent : '';
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        const formData = new FormData(loginForm);

        if (submitButton) {
            submitButton.disabled = true;
            submitButton.textContent = 'Signing in...';
        }

        try {
            const response = await fetch(loginForm.action, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrfToken || '',
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
                body: formData,
            });

            let payload = {};
            try {
                payload = await response.json();
            } catch {
                payload = {};
            }

            if (response.ok && payload.success) {
                if (submitButton) {
                    submitButton.textContent = 'Success! Redirecting...';
                }

                window.location.href = payload.redirect;
                return;
            }

            if (response.status === 401) {
                showWarn('Invalid Credentials.');
            } else if (response.status === 403) {
                showWarn('Your account is not active.');
            } else if (response.status === 419) {
                showWarn('Your session has expired. Please refresh the page and try again.');
            } else {
                alert(payload.message || 'Login failed. Please try again.');
            }
        } catch (error) {
            console.error('Login request failed:', error);
            alert('An error occurred during login.');
        } finally {
            if (submitButton) {
                submitButton.disabled = false;
                submitButton.textContent = originalButtonText;
            }
        }
    });
});
