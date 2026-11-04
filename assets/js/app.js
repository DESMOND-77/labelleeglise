/* ========================================================
   La Belle Église Intenationale Franceville - app.js (léger)
   Graphiques Chart.js, carrousel, confirmations.
   ======================================================== */

(function () {
  "use strict";

  var charts = window.__LBEGF_CHARTS__ || null;
  var registered = {};

  function makeChart(id, config) {
    var el = document.getElementById(id);
    if (!el) return null;
    if (registered[id]) { registered[id].destroy(); delete registered[id]; }
    // Mêmes police et couleur de texte que le reste de l'application
    Chart.defaults.font.family = "'DM Sans', sans-serif";
    Chart.defaults.color = "#5B6472";
    var c = new Chart(el, config);
    registered[id] = c;
    return c;
  }

  function initCharts() {
    if (!charts) return;

    // Barre de comparaison des pôles (accueil)
    if (charts.bar) {
      makeChart("barChart", {
        type: "bar",
        data: {
          labels: charts.bar.labels,
          datasets: [{
            label: "Membres",
            data: charts.bar.data,
            backgroundColor: charts.bar.colors,
            borderRadius: 8,
            maxBarThickness: 38
          }]
        },
        options: {
          responsive: true,
          plugins: {
            legend: { display: false },
            title: { display: true, text: "Répartition des membres par pôle", align: "start", font: { size: 13 }, color: "#5B6472", padding: { bottom: 14 } }
          },
          scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
        }
      });
    }

    // Mini-graphes d'évolution (accueil)
    if (charts.mini) {
      charts.mini.forEach(function (m) {
        makeChart(m.id, {
          type: "line",
          data: {
            labels: m.labels,
            datasets: [{
              data: m.data,
              borderColor: m.color,
              backgroundColor: m.color + "26",
              tension: 0.4,
              fill: true,
              pointRadius: 3,
              pointBackgroundColor: m.color
            }]
          },
          options: {
            responsive: true,
            plugins: { legend: { display: false } },
            scales: {
              y: { beginAtZero: true, ticks: { precision: 0, font: { size: 9 } } },
              x: { ticks: { font: { size: 9 } } }
            }
          }
        });
      });
    }

// Finances : Bacentas vs Centres
    if (charts.finance) {
      makeChart("financeChart", {
        type: "bar",
        data: {
          labels: ["Bacentas", "Centres"],
          datasets: [{
            data: [charts.finance.bacentas, charts.finance.centres],
            backgroundColor: ["#C9A84C", "#2B3A67"],
            borderRadius: 8,
            maxBarThickness: 70
          }]
        },
        options: {
          responsive: true,
          plugins: {
            legend: { display: false },
            title: { display: true, text: "Cumul des offrandes " + charts.finance.year, align: "start", font: { size: 13 }, color: "#5B6472", padding: { bottom: 14 } }
          },
          scales: { y: { beginAtZero: true, ticks: { callback: function (v) { return Number(v).toLocaleString("fr-FR"); } } } }
        }
      });
    }

    // Suivi hebdomadaire : % de réalisation par semaine
    if (charts.suivi) {
      makeChart("suiviChart", {
        type: "line",
        data: {
          labels: charts.suivi.map(function (s) { return s.week; }),
          datasets: [{
label: "% réalisation",
            data: charts.suivi.map(function (s) { return s.pct; }),
            borderColor: "#2B3A67",
            backgroundColor: "#2B3A6726",
            tension: 0.4,
            fill: true,
            pointRadius: 3,
            pointBackgroundColor: "#2B3A67"
          }]
        },
        options: {
          responsive: true,
          plugins: { legend: { display: false } },
          scales: { y: { beginAtZero: true, max: 100, ticks: { callback: function (v) { return v + "%"; } } } }
        }
      });
    }

    // Fiche profil : présence (donut)
    if (charts.doughnut) {
      makeChart("profileChart", {
        type: "doughnut",
        data: {
          labels: ["Présent", "Absent", "Non renseigné"],
          datasets: [{
data: [charts.doughnut.present, charts.doughnut.absent, charts.doughnut.none],
            backgroundColor: ["#5E8C4A", "#B4533C", "#E7E0D1"]
          }]
        },
        options: { responsive: true, plugins: { legend: { position: "bottom" } } }
      });
    }
  }

  /* ---------------- Carrousel (accueil) ---------------- */

  var carouselTimer = null;
  var carouselIndex = 0;

  function initCarousel() {
    var track = document.getElementById("carouselTrack");
    if (!track) return;
    clearInterval(carouselTimer);
    carouselIndex = 0;

    var dots = Array.prototype.slice.call(document.querySelectorAll("#carouselDots span"));
    var total = track.children.length;
    if (total <= 1) return;

    function show(i) {
      carouselIndex = (i + total) % total;
      track.style.transform = "translateX(-" + (carouselIndex * 100) + "%)";
      dots.forEach(function (d, idx) { d.classList.toggle("active", idx === carouselIndex); });
    }

    var prev = document.getElementById("carouselPrev");
    var next = document.getElementById("carouselNext");
    if (prev) prev.addEventListener("click", function () { show(carouselIndex - 1); });
    if (next) next.addEventListener("click", function () { show(carouselIndex + 1); });
    dots.forEach(function (d) {
      d.addEventListener("click", function () { show(parseInt(d.getAttribute("data-dot"), 10)); });
    });

    carouselTimer = setInterval(function () { show(carouselIndex + 1); }, 4500);
  }

  /* ---------------- Confirmations de suppression ---------------- */

  function initConfirms() {
    document.addEventListener("click", function (e) {
      var el = e.target.closest ? e.target.closest("[data-confirm]") : null;
      if (!el) return;
      var msg = el.getAttribute("data-confirm") || "Confirmer ?";
      if (!window.confirm(msg)) {
        e.preventDefault();
        e.stopPropagation();
      }
    });
  }

  /* ---------------- Sélecteurs de date à envoi automatique ----------------
     <input type="date|month" data-autosubmit> envoie son formulaire (GET) quand la date est
     complète. Chrome émet `change` à chaque état intermédiaire valide pendant la frappe
     (jour « 1 » avant « 15 », année 0002 avant 2026) : un envoi immédiat recharge la page dès
     la première touche et efface la saisie. On attend donc la fin de la frappe au clavier ;
     un choix dans le calendrier (aucune touche juste avant) part tout de suite, Entrée aussi. */

  var AUTOSUBMIT_DELAY = 700; // ms sans frappe avant envoi
  var KEYBOARD_WINDOW = 1500; // ms : un `change` plus récent qu'une touche vient du clavier

  function initDateAutoSubmit() {
    var timer = null;
    var lastKeyAt = 0;

    function target(e) {
      var el = e.target;
      return el && el.matches && el.matches("input[data-autosubmit]") ? el : null;
    }

    // Saisie effacée ou partielle : Chrome renvoie "" ; année en cours de frappe : 0002, 0020, 0202 ;
    // année à 5-6 chiffres (Chrome en accepte jusqu'à 6) : faute de frappe, pas une date à envoyer.
    function isComplete(el) {
      var m = /^(\d{4})-\d{2}(-\d{2})?$/.exec(el.value);
      return m !== null && parseInt(m[1], 10) >= 1900;
    }

    function send(el) {
      timer = null;
      if (!el.form || !isComplete(el) || el.value === el.defaultValue) return;
      // requestSubmit() applique min/max : une date hors bornes affiche l'erreur au lieu de partir.
      if (typeof el.form.requestSubmit === "function") el.form.requestSubmit();
      else el.form.submit();
    }

    function onValueChange(e) {
      var el = target(e);
      if (!el) return;
      clearTimeout(timer);
      if (!isComplete(el)) return;
      if (Date.now() - lastKeyAt > KEYBOARD_WINDOW) {
        send(el);
        return;
      }
      timer = setTimeout(function () { send(el); }, AUTOSUBMIT_DELAY);
    }

    document.addEventListener("keydown", function (e) {
      var el = target(e);
      if (!el) return;
      lastKeyAt = Date.now();
      if (e.key === "Enter") {
        e.preventDefault();
        clearTimeout(timer);
        send(el);
      }
    });
    document.addEventListener("input", onValueChange);
    document.addEventListener("change", onValueChange);
  }

  /* ---------------- Année à 4 chiffres ----------------
     Chrome accepte jusqu'à 6 chiffres dans le segment « année » des champs date : une touche de
     trop (« 20261-10-15 ») passe la validation native, puis l'INSERT MySQL échoue (erreur 500) et
     la saisie est perdue. On bloque l'envoi du formulaire avec un message clair. */

  function initDateYearGuard() {
    document.addEventListener("input", function (e) {
      var el = e.target;
      if (!el || !el.matches || !el.matches('input[type="date"], input[type="datetime-local"], input[type="month"]')) return;
      el.setCustomValidity(/^\d{5,}-/.test(el.value) ? "L'année doit comporter 4 chiffres." : "");
    });
  }

  function initBusBalance() {
    var select = document.getElementById("busCentreSelect");
    var amount = document.getElementById("busAmount");
    var hint = document.querySelector("[data-bus-balance]");
    if (!select || !amount || !hint) return;
    var balances = {};
    try { balances = JSON.parse(select.getAttribute("data-bus-balances") || "{}"); } catch (e) { return; }
    function update() {
      var balance = Number(balances[select.value] || 0);
      amount.max = String(balance);
      hint.innerHTML = "Solde disponible : <strong>" + balance.toLocaleString("fr-FR") + " FCFA</strong>";
    }
    select.addEventListener("change", update);
    update();
  }

/* ---------------- Menu mobile ---------------- */

  function initMenuToggle() {
    var toggle = document.getElementById("menuToggle");
    var sidebar = document.getElementById("appSidebar");
    if (!toggle || !sidebar) return;
    toggle.addEventListener("click", function () {
      sidebar.classList.remove("collapsed");
      sidebar.classList.toggle("open");
    });
    document.addEventListener("click", function (e) {
      if (sidebar.classList.contains("open") &&
          !sidebar.contains(e.target) && e.target.id !== "menuToggle") {
        sidebar.classList.remove("open");
      }
    });
  }

  /* ---------------- Repli de la sidebar (desktop) ---------------- */

  function initSidebarCollapse() {
    var btn = document.getElementById("sidebarCollapse");
    var sidebar = document.getElementById("appSidebar");
    if (!btn || !sidebar) return;

    // Restaure l'état mémorisé.
    if (localStorage.getItem("lbegf_sidebar") === "collapsed") {
      sidebar.classList.add("collapsed");
      btn.querySelector("i").className = "fa-solid fa-chevron-right";
    }

    btn.addEventListener("click", function () {
      var collapsed = sidebar.classList.toggle("collapsed");
      btn.querySelector("i").className = collapsed
        ? "fa-solid fa-chevron-right"
        : "fa-solid fa-chevron-left";
      localStorage.setItem("lbegf_sidebar", collapsed ? "collapsed" : "expanded");
    });
  }

  /* ---------------- Position de scroll du menu (persiste entre pages) ---------------- */
  // Application multi-pages (chaque clic recharge la page) : le navigateur
  // ne mémorise pas nativement le scroll d'un conteneur interne comme
  // .side-nav (contrairement au scroll de la fenêtre). Sans ce correctif,
  // atteindre un onglet bas de liste ramène le menu tout en haut à chaque
  // clic. On restaure donc la position juste avant l'affichage, et on
  // enregistre en continu pendant le scroll.

  function initSidebarScrollRestore() {
    var nav = document.querySelector(".side-nav");
    if (!nav) return;
    var KEY = "lbegf_sidebar_scroll";

    var saved = sessionStorage.getItem(KEY);
    if (saved !== null) {
      nav.scrollTop = parseInt(saved, 10) || 0;
    }

    nav.addEventListener("scroll", function () {
      sessionStorage.setItem(KEY, String(nav.scrollTop));
    }, { passive: true });
  }

  /* ---------------- Menu profil ---------------- */

  function initProfileMenu() {
    var menu = document.getElementById("profileMenu");
    var trigger = document.getElementById("profileTrigger");
    if (!menu || !trigger) return;

    trigger.addEventListener("click", function (e) {
      e.stopPropagation();
      var open = menu.classList.toggle("open");
      trigger.setAttribute("aria-expanded", open ? "true" : "false");
    });

    document.addEventListener("click", function (e) {
      if (menu.classList.contains("open") && !menu.contains(e.target)) {
        menu.classList.remove("open");
        trigger.setAttribute("aria-expanded", "false");
      }
    });

    document.addEventListener("keydown", function (e) {
      if (e.key === "Escape" && menu.classList.contains("open")) {
        menu.classList.remove("open");
        trigger.setAttribute("aria-expanded", "false");
        trigger.focus();
      }
    });
  }

  /* ---------------- Menu notifications ---------------- */

  function initNotifMenu() {
    var menu = document.getElementById("notifMenu");
    var trigger = document.getElementById("notifTrigger");
    if (!menu || !trigger) return;

    trigger.addEventListener("click", function (e) {
      e.stopPropagation();
      var open = menu.classList.toggle("open");
      trigger.setAttribute("aria-expanded", open ? "true" : "false");
    });

    document.addEventListener("click", function (e) {
      if (menu.classList.contains("open") && !menu.contains(e.target)) {
        menu.classList.remove("open");
        trigger.setAttribute("aria-expanded", "false");
      }
    });

    document.addEventListener("keydown", function (e) {
      if (e.key === "Escape" && menu.classList.contains("open")) {
        menu.classList.remove("open");
        trigger.setAttribute("aria-expanded", "false");
        trigger.focus();
      }
    });
  }

  /* ---------------- Démarrage ---------------- */

  // Écouteurs délégués sur `document` : enregistrés hors de la chaîne ci-dessous pour qu'un
  // échec de initCharts() (CDN Chart.js indisponible) n'empêche pas les sélecteurs de date.
  initDateAutoSubmit();
  initDateYearGuard();

  document.addEventListener("DOMContentLoaded", function () {
    initCharts();
    initCarousel();
    initConfirms();
    initBusBalance();
    initMenuToggle();
    initSidebarCollapse();
    initSidebarScrollRestore();
    initProfileMenu();
    initNotifMenu();
  });
})();
