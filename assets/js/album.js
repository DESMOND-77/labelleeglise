/* Visionneuse lightbox pour la galerie Album.
   Navigation clavier : flèches gauche/droite, Échap pour fermer.
   Clic sur le fond ou sur la croix pour fermer.
   Accessible : focus sur le bouton fermer à l'ouverture, aria-modal. */
(function () {
  'use strict';

  var lightbox = document.getElementById('albumLightbox');
  if (!lightbox) { return; }

  var backdrop = lightbox.querySelector('[data-lightbox-close]');
  var closeBtn = lightbox.querySelector('.album-lightbox-close');
  var prevBtn = lightbox.querySelector('[data-lightbox-prev]');
  var nextBtn = lightbox.querySelector('[data-lightbox-next]');
  var imageEl = document.getElementById('lightboxImage');
  var titleEl = document.getElementById('lightboxTitle');
  var captionEl = document.getElementById('lightboxCaption');
  var counterEl = document.getElementById('lightboxCounter');

  var triggers = Array.prototype.slice.call(document.querySelectorAll('[data-lightbox]'));
  var currentIndex = -1;

  function open(index) {
    if (index < 0 || index >= triggers.length) { return; }
    currentIndex = index;
    var trigger = triggers[index];
    var img = trigger.querySelector('img');
    var caption = trigger.querySelector('figcaption');

    imageEl.src = img.src;
    imageEl.alt = img.alt;
    titleEl.textContent = img.alt;
    captionEl.textContent = caption ? caption.textContent : '';
    counterEl.textContent = (currentIndex + 1) + ' / ' + triggers.length;

    lightbox.removeAttribute('aria-hidden');
    lightbox.setAttribute('aria-modal', 'true');
    document.body.style.overflow = 'hidden';
    closeBtn.focus();
  }

  function close() {
    lightbox.setAttribute('aria-hidden', 'true');
    lightbox.removeAttribute('aria-modal');
    document.body.style.overflow = '';
    imageEl.src = '';
    imageEl.alt = '';
    captionEl.textContent = '';
    counterEl.textContent = '';
  }

  triggers.forEach(function (trigger, i) {
    trigger.addEventListener('click', function () { open(i); });
  });

  backdrop.addEventListener('click', close);
  closeBtn.addEventListener('click', close);

  prevBtn.addEventListener('click', function (e) {
    e.stopPropagation();
    open(currentIndex - 1);
  });
  nextBtn.addEventListener('click', function (e) {
    e.stopPropagation();
    open(currentIndex + 1);
  });

  document.addEventListener('keydown', function (e) {
    if (lightbox.getAttribute('aria-hidden') === 'true') { return; }
    if (e.key === 'Escape') { close(); }
    else if (e.key === 'ArrowLeft') { open(currentIndex - 1); }
    else if (e.key === 'ArrowRight') { open(currentIndex + 1); }
  });
})();
