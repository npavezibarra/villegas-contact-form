document.addEventListener('DOMContentLoaded', function () {
    const contactForm = document.getElementById('vcf-contactForm');
    if (!contactForm) return;

    contactForm.addEventListener('submit', function (e) {
        e.preventDefault(); // Previene el envío por defecto del formulario

        const form = e.target;
        const feedbackMessage = document.getElementById('vcf-feedbackMessage');
        const submitButton = document.getElementById('vcf-submitButton');
        const nameInput = document.getElementById('name');
        const userName = nameInput ? nameInput.value : '';

        const sendForm = (token = null) => {
            // 1. Recoger datos del formulario
            const formData = new FormData(form);
            formData.append('action', 'vcf_send_email');
            formData.append('nonce', vcf_ajax_obj.nonce);

            if (token) {
                formData.append('recaptcha_token', token);
            }

            // 3. Enviar datos via AJAX
            fetch(vcf_ajax_obj.ajax_url, {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // 4. Mostrar mensaje de éxito o redirigir
                        if (data.data.redirect_url) {
                            window.location.href = data.data.redirect_url;
                        } else {
                            // Update feedback message with name and emoji
                            const messageContent = `
                            <div class="flex-container" style="justify-content: center; flex-direction: column; text-align: center;">
                                <div style="font-size: 3rem; margin-bottom: 10px;">😊</div>
                                <div>
                                    <p style="margin: 0; font-weight: 700; font-size: 1.2rem;">¡Gracias ${userName}!</p>
                                    <p style="margin: 0; font-size: 1rem; font-weight: 300;">Tu mensaje ha sido enviado con éxito.</p>
                                </div>
                            </div>
                        `;
                            feedbackMessage.innerHTML = messageContent;
                            feedbackMessage.style.display = 'block';
                            form.style.display = 'none';
                        }
                    } else {
                        alert('Error: ' + data.data.message);
                        submitButton.textContent = 'Enviar Mensaje';
                        submitButton.disabled = false;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Hubo un error al enviar el mensaje. Por favor, inténtalo de nuevo.');
                    submitButton.textContent = 'Enviar Mensaje';
                    submitButton.disabled = false;
                });
        };

        // 2. Deshabilitar botón y mostrar carga
        submitButton.textContent = 'Enviando...';
        submitButton.disabled = true;

        if (vcf_ajax_obj.recaptcha_site_key && typeof grecaptcha !== 'undefined') {
            grecaptcha.ready(function () {
                grecaptcha.execute(vcf_ajax_obj.recaptcha_site_key, { action: 'submit' }).then(function (token) {
                    sendForm(token);
                });
            });
        } else {
            sendForm();
        }
    });
});
