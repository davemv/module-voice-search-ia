define(['jquery'], function ($) {
  'use strict';

  function addButton() {
    // Buscá el input de búsqueda de Magento (Luma/Blank tienen estos IDs/clases)
    var $input = $('#search') || $('input[name="q"]');
    if (!$input.length) return;

    // Evitá duplicar
    if ($('#voiceSearchBtn').length) return;

    // Crear botón
    var $btn = $('<button/>', {
      id: 'voiceSearchBtn',
      type: 'button',
      class: 'action primary',
      text: '🎙️'
    }).css({
      marginLeft: '6px',
      lineHeight: '30px',
      padding: '0 10px',
      cursor: 'pointer'
    });

    // Insertarlo al lado del input
    $input.after($btn);

    // Hookear reconocimiento de voz
    const Rec = window.SpeechRecognition || window.webkitSpeechRecognition;
    if (!Rec) {
      $btn.prop('disabled', true).attr('title', 'Micrófono no soportado');
      return;
    }
    const rec = new Rec();
    rec.lang = 'es-AR';
    rec.interimResults = false;
    rec.maxAlternatives = 1;

    rec.onresult = function (ev) {
      const text = ev.results[0][0].transcript;
      $.ajax({
        url: '/voicesearch/ajax/search',
        type: 'POST',
        dataType: 'json',
        data: { voice_query: text }
      }).done(function (res) {
        if (res && res.success && res.corrected) {
          window.location.href = '/catalogsearch/result/?q=' + encodeURIComponent(res.corrected);
        } else {
          alert('No se pudo procesar la búsqueda.');
        }
      }).fail(function () {
        alert('Error de red.');
      });
    };
    rec.onerror = function () { alert('Error al reconocer la voz.'); };

    $btn.on('click', function () { rec.start(); });
  }

  return function () {
    // Ejecutar al cargar DOM
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', addButton);
    } else {
      addButton();
    }
  };
});
