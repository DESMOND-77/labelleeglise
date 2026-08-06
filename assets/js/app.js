/* ========================================================
   La Belle Église — app.js (léger)
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
            title: { display: true, text: "Répartition des membres par pôle", align: "start", font: { size: 13 }, color: "#8A8AA3", padding: { bottom: 14 } }
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
            backgroundColor: ["#4F46E5", "#6366F1"],
            borderRadius: 8,
            maxBarThickness: 70
          }]
        },
        options: {
          responsive: true,
          plugins: {
            legend: { display: false },
            title: { display: true, text: "Cumul des offrandes " + charts.finance.year, align: "start", font: { size: 13 }, color: "#8A8AA3", padding: { bottom: 14 } }
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
            borderColor: "#4F46E5",
            backgroundColor: "#4F46E526",
            tension: 0.4,
            fill: true,
            pointRadius: 3,
            pointBackgroundColor: "#4F46E5"
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
            backgroundColor: ["#22C55E", "#EF4444", "#E5E7EB"]
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

  /* ---------------- Démarrage ---------------- */

  document.addEventListener("DOMContentLoaded", function () {
    initCharts();
    initCarousel();
    initConfirms();
    initMenuToggle();
    initSidebarCollapse();
    initProfileMenu();
  });
})();
