async function loadComponent(id, path) {
  try {
    const res = await fetch(path);
    const html = await res.text();
    document.getElementById(id).innerHTML = html;
  } catch(e) {
    console.error('Erreur chargement composant:', path, e);
  }
}

document.addEventListener('DOMContentLoaded', () => {
  const body = document.body;
  const headerPath = body.classList.contains('rando')
    ? '/components/header-rando.html'
    : '/components/header-pro.html';

  loadComponent('header', headerPath).then(() => {
    const toggle = document.getElementById('headerToggle');
    const navWrap = document.querySelector('.header-nav-wrap');
    toggle?.addEventListener('click', () => {
      toggle.classList.toggle('open');
      navWrap?.classList.toggle('open');
    });
    navWrap?.querySelectorAll('a').forEach(a => a.addEventListener('click', () => {
      toggle?.classList.remove('open');
      navWrap.classList.remove('open');
    }));
  });

  loadComponent('footer', '/components/footer.html');
});

/* ── TRANSITION ENTRE PAGES (fondu de sortie) ── */
document.addEventListener('click', function(e) {
  const link = e.target.closest('a');
  if (!link) return;
  if (link.hostname !== window.location.hostname) return;
  if (link.target === '_blank') return;
  // Ancre vers la même page (ex: #contact) : laisse le défilement fluide
  // déjà en place gérer le clic, pas de fondu de sortie.
  if (link.pathname === window.location.pathname && link.hash) return;
  e.preventDefault();
  document.body.style.opacity = '0';
  document.body.style.transform = 'translateY(-8px)';
  document.body.style.transition = 'opacity 0.25s ease, transform 0.25s ease';
  setTimeout(() => { window.location = link.href; }, 260);
});
