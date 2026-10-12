/* ============================================================
   FICHIER : assets/js/quiz-premium.js
   RÔLE    : Effets du quiz olfactif (page quiz, résultats, section d'accueil) :
             - inclinaison 3D + reflet sur les cartes
             - bouton magnétique
             - apparition douce de la section d'accueil au scroll
   Sécurité : aucune injection HTML, aucun eval ; uniquement des classes
   et variables CSS posées sur des éléments déjà présents dans la page.
   ============================================================ */
(function () {
    'use strict';

    var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var fine   = window.matchMedia('(hover: hover) and (pointer: fine)').matches;
    if (reduce) { return; }

    /* --- Apparition de la section quiz de l'accueil (tous appareils) --- */
    var hq = document.querySelector('.hq');
    if (hq && 'IntersectionObserver' in window) {
        hq.classList.add('hq-anim');
        var io = new IntersectionObserver(function (entries, obs) {
            entries.forEach(function (e) {
                if (e.isIntersecting) { hq.classList.add('is-in'); obs.disconnect(); }
            });
        }, { threshold: 0.2 });
        io.observe(hq);
    }

    /* --- Le reste : souris uniquement --- */
    if (!fine) { return; }

    function tilt(el, max) {
        var frame = null;
        el.addEventListener('pointerenter', function () { el.classList.add('is-tilting'); });
        el.addEventListener('pointermove', function (e) {
            var r = el.getBoundingClientRect();
            if (!r.width || !r.height) { return; }
            var px = (e.clientX - r.left) / r.width;
            var py = (e.clientY - r.top) / r.height;
            if (frame) { cancelAnimationFrame(frame); }
            frame = requestAnimationFrame(function () {
                el.style.setProperty('--ry', ((px - 0.5) * 2 * max).toFixed(2) + 'deg');
                el.style.setProperty('--rx', ((0.5 - py) * 2 * max).toFixed(2) + 'deg');
                el.style.setProperty('--mx', (px * 100).toFixed(1) + '%');
                el.style.setProperty('--my', (py * 100).toFixed(1) + '%');
            });
        });
        el.addEventListener('pointerleave', function () {
            el.classList.remove('is-tilting');
            el.style.setProperty('--rx', '0deg');
            el.style.setProperty('--ry', '0deg');
        });
    }

    function magnetic(el) {
        el.addEventListener('pointermove', function (e) {
            var r = el.getBoundingClientRect();
            el.style.setProperty('--bx', ((e.clientX - (r.left + r.width / 2)) * 0.18).toFixed(1) + 'px');
            el.style.setProperty('--by', ((e.clientY - (r.top + r.height / 2)) * 0.28).toFixed(1) + 'px');
        });
        el.addEventListener('pointerleave', function () {
            el.style.setProperty('--bx', '0px');
            el.style.setProperty('--by', '0px');
        });
    }

    document.querySelectorAll('.quiz-shot').forEach(function (el) { tilt(el, 9); });
    document.querySelectorAll('.quiz-card').forEach(function (el) { tilt(el, 6); });
    document.querySelectorAll('.qr-card').forEach(function (el) { tilt(el, 7); });
    document.querySelectorAll('.hq-card').forEach(function (el) { tilt(el, 9); });
    document.querySelectorAll('#quizStart, .hq-btn').forEach(magnetic);
})();