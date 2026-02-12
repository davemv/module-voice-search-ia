define(['jquery'], function ($) {
  'use strict';

  /**
   * Coloca/mueve el botón fuera del form, como hermano dentro del mismo padre (.block-content).
   * También monta el autocomplete "recommended" y el speech.
   */
  function addButton(config) {
    if (window.NTT_VOICE_INIT) return;
    window.NTT_VOICE_INIT = true;

    // URLs (SIN hardcode)
    var ajaxUrl = (config && config.ajaxUrl) ? config.ajaxUrl : null;
    var searchUrl = (config && config.searchUrl) ? config.searchUrl : null;

    // recommended config (puede venir: { title: 'Recomendados', items: ['pants','jacket'] })
    var recommendedTitle = 'Recomendados';
    var recommendedItems = [];
    if (config && config.recommended) {
      if (config.recommended.title) recommendedTitle = config.recommended.title;
      if (Array.isArray(config.recommended.items)) recommendedItems = config.recommended.items;
    }

    function getFormAndParent() {
      var $form = $('form.form.minisearch').first();
      var $parent = $form.length ? $form.parent() : null;
      return { $form: $form, $parent: $parent };
    }

    function createButton() {
      return $('<button/>', {
        id: 'voiceSearchBtn',
        type: 'button',
        class: 'action voice-btn',
        'aria-label': 'Voice search',
        text: '🎙️'
      });
    }

    function ensureAutocompleteContainer($input) {
      var $ac = $('#search_autocomplete');
      if (!$ac.length) {
        $ac = $('<div/>', {
          id: 'search_autocomplete',
          class: 'search-autocomplete',
          style: 'display:none; position:absolute; z-index:9999;'
        }).insertAfter($input);
      }
      return $ac;
    }

    function renderRecommended($ac) {
      if (!recommendedItems.length) { $ac.hide(); return; }

      var safeTitle = $('<div>').text(recommendedTitle).html();
      var html = '<div class="ntt-voice-title">' + safeTitle + '</div>';
      html += '<ul role="listbox">';

      recommendedItems.forEach(function (term) {
        var safe = $('<div>').text(term).html();
        html += '<li class="ntt-voice-reco" role="option" data-term="' + safe + '">';
        html += '<span class="qs-option-name">' + safe + '</span>';
        html += '</li>';
      });

      html += '</ul>';
      $ac.html(html).show();
    }

    function bindRecommendedBehavior($input) {
      var $ac = ensureAutocompleteContainer($input);

      function hideRecommended() { $ac.hide(); }
      function showIfEmpty() { if (!$input.val()) renderRecommended($ac); }

      $input.on('focus', showIfEmpty);
      $input.on('input', function () {
        if ($input.val()) hideRecommended();
        else showIfEmpty();
      });
      $input.on('blur', function () { setTimeout(hideRecommended, 200); });

      $ac.off('mousedown.ntt').on('mousedown.ntt', 'li.ntt-voice-reco', function (e) {
        e.preventDefault();
        var term = $(this).data('term');
        $input.val(term);
        var $form = $input.closest('form');
        if ($form.length) $form.submit();
      });
    }

    function placeButtonAsSibling($btn) {
      var pair = getFormAndParent();
      var $form = pair.$form, $parent = pair.$parent;

      if ($form.length && $parent && $parent.length) {
        $btn.detach();
        $parent.append($btn);
        return;
      }
      if ($form.length) {
        $btn.detach();
        $form.after($btn);
        return;
      }

      var $input = $('#search');
      if (!$input.length) $input = $('input[name="q"]');
      if ($input.length) {
        $btn.detach();
        $input.after($btn);
      }
    }

    function redirectToSearch(query) {
      // fallback seguro si faltan URLs del backend
      if (!searchUrl) {
        window.location.href = '/catalogsearch/result/?q=' + encodeURIComponent(query);
        return;
      }
      var glue = (searchUrl.indexOf('?') >= 0) ? '&' : '?';
      window.location.href = searchUrl + glue + 'q=' + encodeURIComponent(query);
    }

    function initSpeech($btn) {
      var Rec = window.SpeechRecognition || window.webkitSpeechRecognition;
      if (!Rec) {
        $btn.prop('disabled', true).attr('title', 'Micrófono no soportado');
        return;
      }

      if (!ajaxUrl) {
        // Sin ajaxUrl no podemos normalizar; igual dejamos que busque “raw”
        $btn.attr('title', 'Sin ajaxUrl configurado; buscará sin normalizar');
      }

      var rec = new Rec();
      rec.lang = 'es-AR';
      rec.interimResults = false;
      rec.maxAlternatives = 1;

      rec.onresult = function (ev) {
        var text =
          (ev && ev.results && ev.results[0] && ev.results[0][0] && ev.results[0][0].transcript) ? ev.results[0][0].transcript : '';
        text = (text || '').trim();

        if (!text) return;

        // Si no hay ajax, buscamos directo
        if (!ajaxUrl) {
          redirectToSearch(text);
          return;
        }

        // Llamada AJAX al controller
        $.ajax({
          url: ajaxUrl,
          type: 'POST',
          dataType: 'json',
          data: { voice_query: text }
        }).done(function (res) {
          console.log(res, 'res')
          // Preferimos redirectUrl si el backend lo da (ideal porque lleva filtros)
          if (res && res.success && res.redirectUrl) {
            window.location.href = res.redirectUrl;
            return;
          }

          // Compat: si devuelve corrected
          if (res && res.success && res.corrected) {
            redirectToSearch(res.corrected);
            return;
          }

          // último fallback: raw
          redirectToSearch(text);
        }).fail(function () {
          // fallback: raw
          redirectToSearch(text);
        });
      };

      rec.onerror = function () {
        // fallback silencioso: no romper UX con alert
        console.warn('NTT Voice: error de reconocimiento');
      };

      $btn.off('click.ntt').on('click.ntt', function () {
        try {
          rec.start();
        } catch (e) {
          console.error('NTT Voice: rec.start error', e);
        }
      });
    }

    // ====== Exec ======
    var $input = $('#search');
    if (!$input.length) $input = $('input[name="q"]');

    var $btn = $('#voiceSearchBtn');
    if (!$btn.length) $btn = createButton();

    placeButtonAsSibling($btn);

    if ($input.length) bindRecommendedBehavior($input);

    initSpeech($btn);

    // ====== Observer ======
    var observer = new MutationObserver(function () {
      var pair = getFormAndParent();
      var $form = pair.$form;

      if ($form.length) {
        var $btnInside = $form.find('#voiceSearchBtn');
        if ($btnInside.length) {
          placeButtonAsSibling($btnInside);
          initSpeech($('#voiceSearchBtn'));
        }
      }

      var $btnNow = $('#voiceSearchBtn');
      if ($btnNow.length) {
        var expected = (pair.$parent && pair.$parent.length) ? pair.$parent[0] : null;
        if (expected && $btnNow.parent().length && $btnNow.parent()[0] !== expected) {
          placeButtonAsSibling($btnNow);
        }
      }
    });

    observer.observe(document.body, { childList: true, subtree: true });

    // Por seguridad: render recomendado inicial (oculto)
    if ($input.length && recommendedItems.length) {
      var $ac = ensureAutocompleteContainer($input);
      renderRecommended($ac);
      $ac.hide();
    }
    
    console.info('NTT Voice init OK. ajaxUrl=', ajaxUrl, 'searchUrl=', searchUrl, 'recommended=', recommendedItems);
  }

  return function (config) {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', function () { addButton(config || {}); });
    } else {
      addButton(config || {});
    }
  };
});
