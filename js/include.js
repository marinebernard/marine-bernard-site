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
  if (body.classList.contains('rando')) {
    loadComponent('header', '/components/header-rando.html');
  } else {
    loadComponent('header', '/components/header-pro.html');
  }
  loadComponent('footer', '/components/footer.html');
  setTimeout(() => {
    const footer = document.getElementById('footer');
    if (footer && body.classList.contains('rando')) {
      footer.style.background = '#EAF3DE';
    }
  }, 100);
});
