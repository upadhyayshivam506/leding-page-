document.addEventListener('DOMContentLoaded', function () {
    var toggle = document.querySelector('[data-password-toggle]');
    var passwordField = document.getElementById('password');

    if (toggle && passwordField) {
        toggle.addEventListener('click', function () {
            var isPassword = passwordField.getAttribute('type') === 'password';
            passwordField.setAttribute('type', isPassword ? 'text' : 'password');
            toggle.classList.toggle('is-visible', isPassword);
            toggle.setAttribute('aria-label', isPassword ? 'Hide password' : 'Show password');
        });
    }

    var uploadInput = document.querySelector('[data-upload-input]');
    var uploadForm = document.querySelector('[data-upload-form]');

    if (uploadInput && uploadForm) {
        uploadInput.addEventListener('change', function () {
            if (uploadInput.files && uploadInput.files.length > 0) {
                uploadForm.submit();
            }
        });
    }
});
