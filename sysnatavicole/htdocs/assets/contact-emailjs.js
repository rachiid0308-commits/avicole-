(() => {
  const form = document.getElementById('contact-form');
  const status = document.getElementById('contact-status');
  if (!form || !status) return;

  const submitButton = form.querySelector('button[type="submit"]');
  const serviceId = 'service_3wj9tdl';
  const templateId = 'template_6j0an7q';
  const publicKey = 'o0Q2kyzM1xb3UKmrC';
  const initialButtonText = submitButton ? submitButton.textContent : '';

  if (window.emailjs && typeof window.emailjs.init === 'function') {
    window.emailjs.init({ publicKey });
  }

  form.addEventListener('submit', async (event) => {
    event.preventDefault();
    if (!form.reportValidity()) return;

    if (!window.emailjs || typeof window.emailjs.sendForm !== 'function') {
      status.textContent = 'Le service d’envoi est indisponible. Réessayez plus tard.';
      return;
    }
    if (submitButton && submitButton.disabled) return;

    if (submitButton) {
      submitButton.disabled = true;
      submitButton.setAttribute('aria-busy', 'true');
    }
    status.textContent = 'Envoi de votre message en cours…';

    try {
      await window.emailjs.sendForm(serviceId, templateId, form);
      status.textContent = 'Votre message a bien été envoyé à l’administrateur.';
      form.reset();
    } catch (error) {
      console.error('EmailJS contact form send failed', error);
      status.textContent = 'L’envoi a échoué. Vérifiez votre connexion puis réessayez.';
    } finally {
      if (submitButton) {
        submitButton.disabled = false;
        submitButton.removeAttribute('aria-busy');
        submitButton.textContent = initialButtonText;
      }
    }
  });
})();
