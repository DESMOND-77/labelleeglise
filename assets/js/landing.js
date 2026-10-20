/* Vitrine publique : menu mobile accessible (aria-expanded, Échap, retour du focus). */
(function () {
  'use strict';
  var toggle = document.querySelector('.nav-toggle');
  var nav = document.getElementById('site-nav');
  if (!toggle || !nav) { return; }

  document.documentElement.classList.add('js');

  function setOpen(open) {
    toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    nav.classList.toggle('open', open);
  }

  toggle.addEventListener('click', function () {
    setOpen(toggle.getAttribute('aria-expanded') !== 'true');
  });

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && toggle.getAttribute('aria-expanded') === 'true') {
      setOpen(false);
      toggle.focus();
    }
  });

  nav.addEventListener('click', function (e) {
    if (e.target.closest('a')) { setOpen(false); }
  });
})();

/* Vitrine publique : carrousel d'accueil (images : 6 s ; vidéos : jusqu'à la fin). */
(function () {
  'use strict';
  var root = document.querySelector('[data-hero-carousel]');
  if (!root) { return; }
  var slides = root.querySelectorAll('.hero-slide');
  if (slides.length < 2) {
    if (slides[0] && slides[0].tagName === 'VIDEO') { slides[0].loop = true; slides[0].play().catch(function () {}); }
    return;
  }
  var reduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var DELAY = 4500;
  var current = 0;
  var timer = null;

  function show(i) {
    var prev = slides[current];
    prev.classList.remove('is-active');
    if (prev.tagName === 'VIDEO') { prev.pause(); }
    current = i;
    var el = slides[current];
    el.classList.add('is-active');
    if (el.tagName === 'VIDEO') { el.currentTime = 0; }
    schedule();
  }

  function next() { show((current + 1) % slides.length); }

  function schedule() {
    clearTimeout(timer);
    var el = slides[current];
    if (el.tagName === 'VIDEO') {
      el.onended = next;
      el.onerror = next;
      el.play().catch(function () { timer = setTimeout(next, DELAY); });
    } else {
      timer = setTimeout(next, DELAY);
    }
  }

  if (reduced) { return; }
  schedule();
})();

/* Vitrine publique : en-tête transparent au-dessus du hero visuel, plein après défilement. */
(function () {
  'use strict';
  var header = document.querySelector('.site-header');
  if (!header || !document.querySelector('.hero-media')) { return; }
  header.classList.add('over-hero');
  function update() { header.classList.toggle('is-scrolled', window.scrollY > 40); }
  window.addEventListener('scroll', update, { passive: true });
  update();
})();
