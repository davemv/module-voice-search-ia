define(['jquery'], function ($) {
    'use strict';
    return function () {
        const btn = document.getElementById('voiceSearchBtn');
        if (!btn) return;
        const Rec = window.SpeechRecognition || window.webkitSpeechRecognition;
        if (!Rec) { btn.disabled = true; btn.innerText = 'Micrófono no soportado'; return; }
        const rec = new Rec(); rec.lang = 'es-AR'; rec.interimResults = false; rec.maxAlternatives = 1;

        rec.onresult = function (ev) {
            const text = ev.results[0][0].transcript;
            $.ajax({ url: '/voicesearch/ajax/search', type: 'POST', dataType: 'json', data: { voice_query: text } })
                .done(function (res) {
                    if (res && res.success && res.corrected) {
                        window.location.href = '/catalogsearch/result/?q=' + encodeURIComponent(res.corrected);
                    } else { alert('No se pudo procesar la búsqueda.'); }
                })
                .fail(function(){ alert('Error de red.'); });
        };
        rec.onerror = function () { alert('Error al reconocer la voz.'); };
        btn.addEventListener('click', function(){ rec.start(); });
    }
});