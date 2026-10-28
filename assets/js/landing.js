/* Vitrine publique — interactions légères (aucune dépendance).
   1. Marqueur `js` posé immédiatement (dégradation sans JS).
   2. Menu mobile accessible (aria-expanded, Échap, retour du focus).
   3. En-tête densifié après défilement.
   4. Carrousel d'accueil (images 6 s, vidéos jusqu'à la fin).
   5. Révélation au défilement (IntersectionObserver ; visible sans JS
      ni animation si prefers-reduced-motion).
   6. Modale de présentation d'un centre (<dialog> natif). */
(function () {
  'use strict';

  document.documentElement.classList.add('js');

  var reduceMotion = window.matchMedia
    && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  /* ---- 2. Menu mobile ---- */
  (function menu() {
    var toggle = document.querySelector('.lp-nav-toggle');
    var nav = document.getElementById('site-nav');
    if (!toggle || !nav) { return; }

    function setOpen(open) {
      toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
      toggle.setAttribute('aria-label', open ? 'Fermer le menu' : 'Ouvrir le menu');
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

    window.addEventListener('resize', function () {
      if (window.innerWidth > 1024) { setOpen(false); }
    });
  }());

  /* ---- 3. En-tête après défilement ---- */
  (function header() {
    var header = document.querySelector('.site-header');
    if (!header) { return; }
    var ticking = false;
    function update() {
      header.classList.toggle('is-scrolled', window.scrollY > 8);
      ticking = false;
    }
    window.addEventListener('scroll', function () {
      if (!ticking) {
        ticking = true;
        window.requestAnimationFrame(update);
      }
    }, { passive: true });
    update();
  }());

  /* ---- 4. Carrousel d'accueil ---- */
  (function carousel() {
    var root = document.querySelector('[data-hero-carousel]');
    if (!root) { return; }
    var slides = root.querySelectorAll('.lp-hero-slide');
    if (!slides.length) { return; }
    if (slides.length < 2) {
      if (slides[0].tagName === 'VIDEO') {
        slides[0].loop = true;
        slides[0].play().catch(function () {});
      }
      return;
    }
    if (reduceMotion) { return; }

    var DELAY = 6000;
    var current = 0;
    var timer = null;

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

    function next() {
      var prev = slides[current];
      prev.classList.remove('is-active');
      if (prev.tagName === 'VIDEO') { prev.pause(); }
      current = (current + 1) % slides.length;
      var el = slides[current];
      el.classList.add('is-active');
      if (el.tagName === 'VIDEO') { el.currentTime = 0; }
      schedule();
    }

    schedule();
  }());

  /* ---- 5. Révélation au défilement ---- */
  (function reveal() {
    var targets = document.querySelectorAll('[data-reveal]');
    if (!targets.length || reduceMotion || typeof IntersectionObserver === 'undefined') {
      return;
    }
    var observer = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          entry.target.classList.add('is-revealed');
          observer.unobserve(entry.target);
        }
      });
    }, { rootMargin: '0px 0px -8% 0px', threshold: 0.06 });

    Array.prototype.forEach.call(targets, function (el) { observer.observe(el); });
  }());

  /* ---- 6. Modale de centre ---- */
  (function centreModal() {
    var triggers = document.querySelectorAll('[data-centre-modal]');
    if (!triggers.length || typeof HTMLDialogElement === 'undefined') { return; }

    function openFor(btn) {
      var dlg = document.getElementById(btn.getAttribute('data-centre-modal'));
      if (dlg && !dlg.open) { dlg.showModal(); }
    }

    // Toute la carte est cliquable (sauf ses liens : itinéraire, contact).
    document.querySelectorAll('.lp-place.has-article').forEach(function (card) {
      var btn = card.querySelector('[data-centre-modal]');
      card.addEventListener('click', function (e) {
        if (e.target.closest('a')) { return; }
        openFor(btn);
      });
    });

    document.querySelectorAll('dialog.centre-modal').forEach(function (dlg) {
      dlg.addEventListener('click', function (e) {
        // Clic sur le fond (hors carte) ou sur la croix : fermeture.
        if (e.target === dlg || (e.target.closest && e.target.closest('[data-centre-modal-close]'))) {
          dlg.close();
        }
      });
    });
  }());
}());
