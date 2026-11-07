(() => {
  const gateway = document.getElementById('gateway');
  const sealButton = document.getElementById('sealButton');
  const loginForm = document.getElementById('loginForm');
  const username = document.getElementById('username');
  const password = document.getElementById('password');
  const togglePassword = document.getElementById('togglePassword');
  const cancelLogin = document.getElementById('cancelLogin');
  const loginButton = document.getElementById('loginButton');

  function setPasswordVisible(visible) {
    password.type = visible ? 'text' : 'password';
    togglePassword.setAttribute('aria-pressed', String(visible));
    togglePassword.setAttribute('aria-label', visible ? 'Hide password' : 'Show password');
  }

  function setOpen(open, focus = true) {
    gateway.classList.toggle('is-open', open);
    loginForm.hidden = !open;
    sealButton.setAttribute('aria-expanded', String(open));
    sealButton.setAttribute('aria-label', open ? 'Close sign-in form' : 'Open sign-in form');
    if (!open) {
      password.value = '';
      setPasswordVisible(false);
    }
    if (focus) (open ? username : sealButton).focus({ preventScroll: true });
  }

  function updateViewport() {
    const viewport = window.visualViewport;
    if (viewport && Math.abs(viewport.scale - 1) > .01) return;
    const height = Math.round(viewport?.height ?? window.innerHeight);
    gateway.style.setProperty('--entry-height', `${height}px`);
    gateway.classList.toggle('is-compact', height < 560);
  }

  function resetSubmitState() {
    loginButton.disabled = false;
    sealButton.disabled = false;
    cancelLogin.disabled = false;
    loginButton.querySelector('span').textContent = 'Sign in';
    loginForm.removeAttribute('aria-busy');
  }

  setOpen(gateway.classList.contains('is-open'), false);
  updateViewport();
  sealButton.addEventListener('click', () => setOpen(!gateway.classList.contains('is-open')));
  cancelLogin.addEventListener('click', () => setOpen(false));
  togglePassword.addEventListener('click', () => setPasswordVisible(password.type === 'password'));
  loginForm.addEventListener('keydown', event => {
    if (event.key === 'Escape' && !loginButton.disabled) setOpen(false);
  });
  loginForm.addEventListener('submit', () => {
    loginButton.disabled = true;
    sealButton.disabled = true;
    cancelLogin.disabled = true;
    loginButton.querySelector('span').textContent = 'Signing in...';
    loginForm.setAttribute('aria-busy', 'true');
  });
  window.visualViewport?.addEventListener('resize', updateViewport);
  window.addEventListener('resize', updateViewport);
  window.addEventListener('pageshow', resetSubmitState);
})();
