document.addEventListener('click', event => {
  const button = event.target.closest('[data-password-toggle]');
  if (!button) return;

  const input = button.parentElement.querySelector('input');
  if (!input) return;

  const show = input.type === 'password';
  input.type = show ? 'text' : 'password';
  button.classList.toggle('is-visible', show);
  button.setAttribute('aria-pressed', String(show));
  const label = button.closest('label')?.querySelector('span')?.textContent.replace('*', '').trim().toLowerCase() || 'password';
  button.setAttribute('aria-label', `${show ? 'Hide' : 'Show'} ${label}`);
});
