

window.togglePassword = function togglePassword() {
    const passwordInput = document.getElementById('password');
    const toggleIcon = document.getElementById('toggleIcon');

    if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        toggleIcon.classList.remove('fa-eye');
        toggleIcon.classList.add('fa-eye-slash');
    } else {
        passwordInput.type = 'password';
        toggleIcon.classList.remove('fa-eye-slash');
        toggleIcon.classList.add('fa-eye');
    }
}

function showWarn(text) {
    var warning = document.getElementById('warn');
    var warnText = document.getElementById('warnText');
    warning.classList.remove('hidden');
    warning.classList.add('block');
    warnText.textContent = text;

    //change container padding to accommodate notification
    var loginContainer = document.getElementById('loginContainer');
    loginContainer.classList.remove('md:p-16');
    loginContainer.classList.add('md:px-16', 'md:py-7');
}

window.closeWarn = function closeWarn() {
    var warning = document.getElementById('warn');
    warning.classList.add('hidden');
    warning.classList.remove('block');

    //restore original padding
    var loginContainer = document.getElementById('loginContainer');
    loginContainer.classList.remove('md:px-16', 'md:py-7');
    loginContainer.classList.add('md:p-16');
}

// AJAX stuff.. not too sure about majority of this (as of nov 2025)
$(document).ready(function () {
    $('#loginForm').submit(function (e) {
        e.preventDefault(); // Prevent default form submission... 

        var formData = $(this).serialize(); // Serialize form data
        var url = $(this).attr('action'); // Get the form's action URL
        var submitButton = $(this).find('button[type="submit"]');
        var originalButtonText = submitButton.text();


        // Disable submit button to prevent double submission
        submitButton.prop('disabled', true).text('Signing in...');

        $.ajax({
            type: 'POST',
            url: url,
            data: formData,
            dataType: 'json',
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                'X-Requested-With': 'XMLHttpRequest'
            },
            success: function (response) {
                if (response.success) {
                    // Show success message before redirect
                    submitButton.text('Success! Redirecting...');
                    window.location.href = response.redirect; // Redirect on success
                } else {
                    alert(response.message || 'Login failed. Please try again.'); // Display error message
                    submitButton.prop('disabled', false).text(originalButtonText);
                }
            },
            error: function (xhr, status, error) {
                console.error('AJAX Error:', xhr.responseText); // Log any AJAX errors

                var errorMessage = 'An error occurred during login.';

                // Try to parse error response
                if (xhr.status === 401) {
                    showWarn('Invalid Credentials.');
                } else if (xhr.status === 403) {
                    showWarn('Your account is not active.');
                } else if (xhr.status === 419) {
                    showWarn('Your session has expired. Please refresh the page and try again.');
                } else {
                    errorMessage = xhr.responseJSON.message;
                    alert(errorMessage);
                }

                //alert(errorMessage);
                submitButton.prop('disabled', false).text(originalButtonText);
            }
        });
    });
});