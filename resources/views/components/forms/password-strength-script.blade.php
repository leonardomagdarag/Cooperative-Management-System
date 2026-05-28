@php
    use App\Support\PersonPasswordPolicy;
@endphp

<script>
    (() => {
        window.__personPasswordForms ??= {};

        if (window.__personPasswordForms[@js($scope)]) {
            return;
        }

        window.__personPasswordForms[@js($scope)] = true;

        const minLength = {{ PersonPasswordPolicy::MIN_LENGTH }};
        const requirementMessage = @js(PersonPasswordPolicy::requirementMessage());
        const predictableMessage = @js(PersonPasswordPolicy::predictablePasswordMessage());
        const confirmationRequiredMessage = @js(PersonPasswordPolicy::confirmationRequiredMessage());
        const confirmationMismatchMessage = @js(PersonPasswordPolicy::confirmationMismatchMessage());

        const findRoot = () => document.querySelector(`[data-password-form=${JSON.stringify(@js($scope))}]`);

        const getNameTokens = (root) => ['first_name', 'mid_name', 'last_name']
            .map((id) => root.querySelector(`#${id}`)?.value || '')
            .map((value) => value.toLowerCase().replace(/[^a-z]+/g, ''))
            .filter((value) => value.length >= 3);

        const setState = (labelElement, barElement, label, labelClass, barClass, width) => {
            labelElement.textContent = label;
            labelElement.className = `min-w-16 text-sm font-semibold ${labelClass}`;
            barElement.className = `h-full rounded-full transition-all duration-200 ${barClass}`;
            barElement.style.width = width;
        };

        const update = () => {
            const root = findRoot();

            if (!root) {
                return;
            }

            const passwordInput = root.querySelector('#password');
            const confirmationInput = root.querySelector('#password_confirmation');
            const strengthLabel = root.querySelector('#{{ $labelId }}');
            const strengthBar = root.querySelector('#{{ $barId }}');

            if (!passwordInput || !confirmationInput || !strengthLabel || !strengthBar) {
                return;
            }

            const value = passwordInput.value || '';
            const normalized = value.toLowerCase();
            const hasLetters = /[A-Za-z]/.test(value);
            const hasNumbers = /\d/.test(value);
            const hasUppercase = /[A-Z]/.test(value);
            const hasSpecial = /[^A-Za-z0-9]/.test(value);
            const looksPredictable = getNameTokens(root).some((token) => normalized.includes(token) && /\d{3,}/.test(normalized));

            let passwordError = '';

            if (value && (value.length < minLength || !hasLetters || !hasNumbers)) {
                passwordError = requirementMessage;
            } else if (value && looksPredictable) {
                passwordError = predictableMessage;
            }

            passwordInput.setCustomValidity(passwordError);

            let confirmationError = '';
            if (value && !confirmationInput.value) {
                confirmationError = confirmationRequiredMessage;
            } else if (confirmationInput.value && confirmationInput.value !== value) {
                confirmationError = confirmationMismatchMessage;
            }

            confirmationInput.setCustomValidity(confirmationError);

            if (!value) {
                setState(strengthLabel, strengthBar, 'Weak', 'text-gray-700', 'bg-gray-300', '0%');
                return;
            }

            if (passwordError) {
                setState(strengthLabel, strengthBar, 'Too weak', 'text-red-700', 'bg-red-500', '20%');
                return;
            }

            if (hasUppercase && hasSpecial && value.length >= 8) {
                setState(strengthLabel, strengthBar, 'Strong', 'text-emerald-700', 'bg-emerald-500', '100%');
                return;
            }

            if (hasUppercase || hasSpecial) {
                setState(strengthLabel, strengthBar, 'Medium', 'text-cyan-700', 'bg-cyan-500', '72%');
                return;
            }

            setState(strengthLabel, strengthBar, 'Fair', 'text-amber-700', 'bg-amber-500', '50%');
        };

        const shouldRefresh = (target) => target instanceof HTMLElement
            && ['password', 'password_confirmation', 'first_name', 'mid_name', 'last_name'].includes(target.id);

        document.addEventListener('input', (event) => {
            const root = findRoot();

            if (root && root.contains(event.target) && shouldRefresh(event.target)) {
                update();
            }
        });

        document.addEventListener('blur', (event) => {
            const root = findRoot();

            if (root && root.contains(event.target) && shouldRefresh(event.target)) {
                update();
            }
        }, true);

        document.addEventListener('DOMContentLoaded', update);
        window.requestAnimationFrame(update);
        update();
    })();
</script>