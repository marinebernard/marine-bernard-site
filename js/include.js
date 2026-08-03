/* ── LOADER DE PAGE ── */
const loader = document.createElement('div');
loader.id = 'page-loader';
loader.innerHTML = `<svg viewBox="0 0 30 30" xmlns="http://www.w3.org/2000/svg">
  <circle cx="15" cy="15" r="4.5" fill="#FAF8FF"/>
  <ellipse cx="15" cy="6" rx="3.5" ry="5" fill="#8B6BB1" opacity="0.9"/>
  <ellipse cx="15" cy="24" rx="3.5" ry="5" fill="#8B6BB1" opacity="0.9"/>
  <ellipse cx="6" cy="15" rx="5" ry="3.5" fill="#C9A96E" opacity="0.85"/>
  <ellipse cx="24" cy="15" rx="5" ry="3.5" fill="#C9A96E" opacity="0.85"/>
  <circle cx="15" cy="15" r="3" fill="#2D1B69"/>
</svg>`;
document.body.prepend(loader);
window.addEventListener('load', () => {
  setTimeout(() => loader.classList.add('hidden'), 300);
});

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

  const trackingScript = document.createElement('script');
  trackingScript.src = '/js/tracking.js';
  document.body.appendChild(trackingScript);

  /* ── BOUTON RETOUR EN HAUT ── */
  const btn = document.createElement('button');
  btn.id = 'back-to-top';
  btn.setAttribute('aria-label', 'Retour en haut');
  btn.innerHTML = `<svg viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5"><path d="M18 15l-6-6-6 6"/></svg>`;
  document.body.appendChild(btn);
  window.addEventListener('scroll', () => {
    btn.classList.toggle('visible', window.scrollY > 400);
  });
  btn.addEventListener('click', () => {
    window.scrollTo({ top: 0, behavior: 'smooth' });
  });
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
