document.addEventListener('DOMContentLoaded', function() {

  function sendEvent(eventName, params) {
    if (typeof window.dataLayer !== 'undefined') {
      window.dataLayer.push({
        event: eventName,
        ...params
      });
    }
  }

  document.addEventListener('click', function(e) {
    const el = e.target.closest('[data-track]');
    if (!el) return;

    const track = el.getAttribute('data-track');
    const value = el.getAttribute('data-value') || '';

    switch(track) {
      case 'choix-univers':
        sendEvent('choix_univers', {
          univers: value,
          page_location: window.location.href
        });
        break;

      case 'passer-etape':
        sendEvent('passer_etape', {
          page_location: window.location.href
        });
        break;

      case 'clic-projet':
        sendEvent('clic_projet', {
          projet: value,
          page_location: window.location.href
        });
        break;

      case 'telecharger-cv':
        sendEvent('telecharger_cv', {
          page_location: window.location.href
        });
        break;

      case 'clic-email':
        sendEvent('clic_contact', {
          type: 'email',
          page_location: window.location.href
        });
        break;

      case 'clic-telephone':
        sendEvent('clic_contact', {
          type: 'telephone',
          page_location: window.location.href
        });
        break;

      case 'clic-rando':
        sendEvent('clic_rando', {
          page_location: window.location.href
        });
        break;

      case 'clic-photo':
        sendEvent('clic_photo', {
          photo: value,
          page_location: window.location.href
        });
        break;

      case 'clic-reseau':
      case 'clic-reseau-blog':
        sendEvent('clic_reseau_social', {
          reseau: value,
          source: track === 'clic-reseau-blog' ? 'blog' : 'footer',
          page_location: window.location.href
        });
        break;

      case 'switch-univers':
        sendEvent('switch_univers', {
          destination: value,
          page_location: window.location.href
        });
        break;

      case 'clic-contact-header':
        sendEvent('clic_contact', {
          type: 'header',
          page_location: window.location.href
        });
        break;

      case 'clic-projet-detail':
        sendEvent('clic_projet_detail', {
          page: value,
          page_location: window.location.href
        });
        break;
    }
  });

  sendEvent('page_vue', {
    page_title: document.title,
    page_location: window.location.href,
    univers: document.body.classList.contains('rando') ? 'rando' : 'pro'
  });

});
