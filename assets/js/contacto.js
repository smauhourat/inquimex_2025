(function () {
    const form = document.getElementById('contact-form');
    if (!form) return;

    const csrfInput = document.getElementById('csrf_token');
    const submitBtn = document.getElementById('contact-submit');
    const msgSuccess = document.getElementById('form-msg-success');
    const msgError = document.getElementById('form-msg-error');

    function hideMessages() {
        msgSuccess.classList.remove('show');
        msgError.classList.remove('show');
    }

    function ensureToken() {
        return fetch('contacto/init.php')
            .then((r) => r.json())
            .then((d) => {
                csrfInput.value = d.csrf_token || '';
                return csrfInput.value;
            })
            .catch(() => '');
    }

    ensureToken();

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        hideMessages();

        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }

        submitBtn.disabled = true;
        submitBtn.textContent = 'Enviando...';

        const send = () => {
            const data = new FormData(form);
            return fetch('contacto/procesar.php', { method: 'POST', body: data })
                .then((r) => r.json());
        };

        (csrfInput.value ? Promise.resolve(csrfInput.value) : ensureToken())
            .then(send)
            .then((json) => {
                if (json.success) {
                    msgSuccess.textContent = json.message || '¡Mensaje enviado! Te responderemos a la brevedad.';
                    msgSuccess.classList.add('show');
                    form.reset();
                    ensureToken();
                } else {
                    msgError.textContent = json.message || 'Ocurrió un error al enviar. Por favor verificá tus datos.';
                    msgError.classList.add('show');
                }
            })
            .catch(() => {
                msgError.textContent = 'Error de conexión. Intentá nuevamente.';
                msgError.classList.add('show');
            })
            .finally(() => {
                submitBtn.disabled = false;
                submitBtn.textContent = 'Enviar Consulta';
            });
    });
})();
