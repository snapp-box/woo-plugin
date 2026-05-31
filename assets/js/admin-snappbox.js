/* global SNAPPBOX_GLOBAL, jQuery, maplibregl */
(function ($) {
  'use strict';

  $(function () {

    /* =========================================================
     * STOP ROW NAVIGATION WHEN USING MODAL (WITHOUT BREAKING BUTTONS)
     * ========================================================= */
    // Stop bubbling from modal to clickable row handlers
    $(document).on('click', '.sb-modal, #sb-pricing-modal', function (e) {
      e.stopPropagation();
    });

    // Stop bubbling for everything inside modal, but only preventDefault for real <a href="">
    $(document).on('click', '.sb-modal *, #sb-pricing-modal *', function (e) {
      // Always stop propagation so row click doesn't fire
      e.stopPropagation();

      // Allow our modal controls to work normally (we only stopped bubbling)
      if ($(e.target).closest('.add-voucher-code, .snappbox-create-order, #snappbox-create-order, .sb-modal__close, input, textarea, select, button').length) {
        return;
      }

      // Prevent navigation only for real links
      var $a = $(e.target).closest('a[href]');
      if ($a.length) e.preventDefault();
    });

    // Extra safety: if modal contents are inside a form, don't submit/navigate
    $(document).on('submit', '.sb-modal form, #sb-pricing-modal form', function (e) {
      e.preventDefault();
      e.stopPropagation();
    });
    $(".guidence-text a").click(function (e) {
      e.preventDefault();
      $(".guidence-map").css('display', 'none');
    });


    /* =========================================================
     * MAP
     * ========================================================= */
    var $map = $('#admin-osm-map');
    if ($map.length && typeof maplibregl !== 'undefined') {
      try {
        if (SNAPPBOX_GLOBAL && SNAPPBOX_GLOBAL.rtlPluginUrl) {
          maplibregl.setRTLTextPlugin(SNAPPBOX_GLOBAL.rtlPluginUrl, null, true);
        }

        var lat = parseFloat($map.data('lat'));
        var lng = parseFloat($map.data('lng'));

        if (!isNaN(lat) && !isNaN(lng)) {
          var map = new maplibregl.Map({
            container: 'admin-osm-map',
            style: (SNAPPBOX_GLOBAL && SNAPPBOX_GLOBAL.mapStyleUrl) || 'https://tile.snappmaps.ir/styles/snapp-style-v4.1.2/style.json',
            center: [lng, lat],
            zoom: 15,
            attributionControl: true
          });

          map.addControl(new maplibregl.NavigationControl({ visualizePitch: true }), 'top-right');

          new maplibregl.Popup({ closeOnClick: false })
            .setLngLat([lng, lat])
            .setHTML('<div style="direction:rtl;unicode-bidi:plaintext;">' + ((SNAPPBOX_GLOBAL && SNAPPBOX_GLOBAL.i18n && SNAPPBOX_GLOBAL.i18n.popupCustomer) || 'موقعیت مشتری') + '</div>')
            .addTo(map);
        }
      } catch (e) {
        // eslint-disable-next-line no-console
        console.error('Map init error:', e);
      }
    }

    /* =========================================================
     * ORDER UI
     * ========================================================= */
    (function () {
      var $ctx = $('#snappbox-admin-context');
      if (!$ctx.length) return;

      var resolvedNonce =
        (window.SNAPPBOX_GLOBAL && SNAPPBOX_GLOBAL.nonce) ||
        $ctx.data('nonce') ||
        '';

      var ctx = {
        ajaxUrl: (window.SNAPPBOX_GLOBAL && SNAPPBOX_GLOBAL.ajaxUrl) || '',
        nonce: resolvedNonce,
        currency: ($ctx.data('currency') || '').toString(),
        wooOrderId: parseInt($ctx.data('woo-order-id'), 10)
      };

      if (!ctx.ajaxUrl) console.warn('SNAPPBOX: ajaxUrl is missing.');
      if (!ctx.nonce) console.warn('SNAPPBOX: nonce is missing. AJAX requests may fail.');

      function rialToToman(v) { return parseInt(v, 10) / 10; }

      function fmt(n) {
        try {
          return new Intl.NumberFormat('en-IR', { maximumSignificantDigits: 3 }).format(n);
        } catch (_) {
          return n;
        }
      }

      function show($el) { if ($el && $el.length) $el.removeAttr('hidden'); }
      function hide($el) { if ($el && $el.length) $el.attr('hidden', true); }

      // "Row" = table cell wrapper in list page. On order details page it doesn't exist.
      function getRowOrPage($btn) {
        var $row = $btn.closest('.column-snappbox_action');
        return $row.length ? $row : $(document.body);
      }

      function showBtnText($btn) {
        var $btnText = $btn.closest('.button-text');
        return $btnText;
      }

      function getModal($scope) {
        // Prefer modal inside the row; fallback to a global modal
        var $m = $scope.find('.sb-modal').first();
        if (!$m.length) $m = $('#sb-pricing-modal');
        return $m;
      }

      function getPricingMsg($scope, $modal) {
        var $el = $modal.find('.pricing-message, #pricing-message').first();
        if (!$el.length) $el = $scope.find('.pricing-message, #pricing-message').first();
        if (!$el.length) $el = $('#pricing-message').first();
        return $el;
      }

      function getVoucher($scope, $modal) {
        var $el = $modal.find('.sb-voucher-code, #sb-voucher-code').first();
        if (!$el.length) $el = $scope.find('.sb-voucher-code, #sb-voucher-code').first();
        if (!$el.length) $el = $('#sb-voucher-code').first();
        return $el;
      }

      function getCreateBtn($scope, $modal) {
        var $el = $modal.find('.snappbox-create-order, #snappbox-create-order').first();
        if (!$el.length) $el = $scope.find('.snappbox-create-order, #snappbox-create-order').first();
        if (!$el.length) $el = $('#snappbox-create-order').first();
        return $el;
      }

      function getLoading($scope) {
        var $el = $scope.find('.loading');
        // if (!$el.length) $el = $('.loading');
        return $el;
      }

      function getOrderLoading($scope) {
        var $el = $scope.find('.ct-order-loading');
        if (!$el.length) $el = $('.ct-order-loading');
        return $el;
      }

      function getCancelLoading() {
        return $('.cancel-order-loading');
      }

      function openModal($btn) {
        var $scope = getRowOrPage($btn);
        var $modal = getModal($scope);
        show($modal);
        return $modal;
      }

      // Close modal
      $(document).on('click', '.sb-modal__close', function (e) {
        e.preventDefault();
        e.stopPropagation();

        var $modal = $(this).closest('.sb-modal, #sb-pricing-modal');
        $modal.find('.sb-voucher-code, #sb-voucher-code').val('');
        hide($modal);
      });

      $(document).on('click', '.snappbox-pricing-order, #add-voucher-code', function (e) {
        e.preventDefault();
        var $btn = $(this);
        var $clickedModal = $btn.closest('.sb-modal, #sb-pricing-modal');
        var $scope = $clickedModal.length ? $clickedModal : getRowOrPage($btn);

        var $modal = $clickedModal.length ? $clickedModal : getModal($scope);

        var $pricingMsg = getPricingMsg($scope, $modal);
        var $voucher = getVoucher($scope, $modal);
        var $createBtn = getCreateBtn($scope, $modal);
        var $loading = getLoading($scope);

        var $row = $btn.closest('.column-snappbox_action');
        var orderId =
          $btn.data('order-id') ||
          $modal.find('[data-order-id]').first().data('order-id') ||
          $row.find('[data-order-id]').first().data('order-id') ||
          $('#snappbox-create-order').data('order-id') ||
          ctx.wooOrderId;

        var voucherCode = $voucher.length ? $voucher.val() : '';

        show($loading);
        jQuery(this).find('.button-text').hide();
        $.ajax({
          url: ctx.ajaxUrl,
          type: 'POST',
          dataType: 'json',
          data: {
            action: 'snappb_get_pricing',
            order_id: orderId,
            voucher_code: voucherCode,
            nonce: ctx.nonce
          },
          beforeSend: function () {
            if ($pricingMsg.length) {
              $pricingMsg.text(
                (window.SNAPPBOX_GLOBAL && SNAPPBOX_GLOBAL.i18n && SNAPPBOX_GLOBAL.i18n.priceFetching) ||
                'در حال دریافت قیمت...'
              );
            }
            if ($createBtn.length) $createBtn.attr('disabled', 'disabled');
          },
          success: function (response) {
            if (!$clickedModal.length) {
              $modal = openModal($btn);
            } else {
              show($modal);
            }

            $pricingMsg = getPricingMsg($scope, $modal);
            $createBtn = getCreateBtn($scope, $modal);
            $btn.find('.button-text').show();
            if ($createBtn.length) $createBtn.removeAttr('disabled');

            if (response && response.success) {
              var fare = Number(response.data && response.data.finalCustomerFare);
              var totalFare = Number(response.data && response.data.totalFare);

              if ($createBtn.length) show($createBtn);

              var finalFare, totalFareDisplay, simbol;
              var hasTotal = response.data && response.data.totalFare != null && !isNaN(totalFare);

              if (ctx.currency === 'IRT') {
                finalFare = rialToToman(fare);
                totalFareDisplay = hasTotal ? rialToToman(totalFare) : undefined;
                simbol = 'تومان';
              } else {
                finalFare = fare;
                totalFareDisplay = hasTotal ? totalFare : undefined;
                simbol = 'ریال';
              }

              hide($loading);

              var htmlMsg;
              if (hasTotal && !isNaN(totalFareDisplay) && totalFareDisplay > 0 && totalFareDisplay !== finalFare) {
                htmlMsg =
                  'قیمت کل: <span class="sb-strike">' + fmt(totalFareDisplay) + ' ' + simbol + '</span>' +
                  '<br>' +
                  'قیمت با تخفیف: ' + fmt(finalFare) + ' ' + simbol;
              } else {
                htmlMsg = 'قیمت تخمینی: ' + fmt(finalFare) + ' ' + simbol;
              }

              if ($pricingMsg.length) $pricingMsg.html(htmlMsg);
            } else {
              hide($loading);

              var msg;
              if (response && response.data && response.data.voucherMessage) {
                msg = response.data.voucherMessage;
              } else {
                msg = (response && response.data && response.data.message)
                  ? response.data.message
                  : ((window.SNAPPBOX_GLOBAL && SNAPPBOX_GLOBAL.i18n && SNAPPBOX_GLOBAL.i18n.priceError) || 'خطا در دریافت قیمت.');
                if ($createBtn.length) hide($createBtn);
              }

              if ($pricingMsg.length) $pricingMsg.text(msg);
              if ($createBtn.length) $createBtn.attr('disabled', 'disabled');
            }
          },
          error: function (jqXHR, textStatus, errorThrown) {
            console.error('AJAX error:', textStatus, errorThrown, jqXHR);
            if ($pricingMsg.length) {
              $pricingMsg.text(
                (window.SNAPPBOX_GLOBAL && SNAPPBOX_GLOBAL.i18n && SNAPPBOX_GLOBAL.i18n.requestError) ||
                'خطا در ارسال درخواست.'
              );
            }
            hide($loading);
          }
        });
      });


      $(document).on('click', '.snappbox-create-order, #snappbox-create-order', function (e) {
        e.preventDefault();
        e.stopPropagation();

        var $btn = $(this);
        var $scope = getRowOrPage($btn);
        var $modal = getModal($scope);

        var orderId = $btn.data('order-id') || ctx.wooOrderId;
        var $voucher = getVoucher($scope, $modal);
        var voucherCode = $voucher.length ? $voucher.val() : '';

        var $orderLoading = getOrderLoading($scope);

        var $vdsContent = $modal.find('.vds-content').first();
        var $modalContent = $modal.find('.sb-modal__content').first();

        var $victory = $modal.find('#snappbox-response-victory, .snappbox-response-victory').first();
        if (!$victory.length) $victory = $('#snappbox-response-victory').first();

        var $resp = $modal.find('#snappbox-response, .snappbox-response').first();
        if (!$resp.length) $resp = $('#snappbox-response').first();

        if ($vdsContent.length) $vdsContent.attr('hidden', 'hidden');

        $.ajax({
          url: ctx.ajaxUrl,
          type: 'POST',
          dataType: 'json',
          data: {
            action: 'snappb_create_order',
            order_id: orderId,
            voucher_code: voucherCode,
            nonce: ctx.nonce
          },
          beforeSend: function () { show($orderLoading); },
          success: function (response) {
            var ok = !!(
              response &&
              response.success == true &&
              response.response
            );

            if (ok) {
              if ($modalContent.length) {
                $modalContent.find('*').hide();
                $modalContent.hide();
              } else {
                $modal.find('.sb-modal__content, .sb-modal__content *').hide();
              }

              if ($vdsContent.length) $vdsContent.removeAttr('hidden');

              var createdMsg =
                (response.response && response.response.message) ||
                ((window.SNAPPBOX_GLOBAL && SNAPPBOX_GLOBAL.i18n && SNAPPBOX_GLOBAL.i18n.created) || 'Created');

              if ($victory.length) {
                $victory.html('<span class="sb-success">' + createdMsg + '</span>');
              }

              if (typeof window.ym === 'function') {
                window.ym(105087875, 'reachGoal', 'create-order');
              }

              window.location.reload();
            } else {
              var errMsg =
                (response && response.response && response.response.message)
                  ? response.response.message
                  : ((window.SNAPPBOX_GLOBAL && SNAPPBOX_GLOBAL.i18n && SNAPPBOX_GLOBAL.i18n.unknownError) || 'Unknown error');

              if ($resp.length) $resp.html('<span class="sb-error">Error: ' + errMsg + '</span>');
            }

            hide($orderLoading);
          },
          error: function () {
            var msg = (window.SNAPPBOX_GLOBAL && SNAPPBOX_GLOBAL.i18n && SNAPPBOX_GLOBAL.i18n.orderSendErr) || 'Error sending order.';
            if ($resp.length) $resp.text(msg);
            hide($orderLoading);
          }
        });
      });


      $(document).on('click', '#snappbox-cancel-order', function (e) {
        e.preventDefault();

        var $btn = $(this);
        var orderId = $btn.data('order-id');

        var $cancelLoading = getCancelLoading();
        $btn.find('.button-text').hide();
        $.ajax({
          url: ctx.ajaxUrl,
          type: 'POST',
          dataType: 'json',
          data: {
            action: 'snappb_cancel_order',
            order_id: orderId,
            woo_order_id: ctx.wooOrderId,
            nonce: ctx.nonce
          },
          beforeSend: function () { show($cancelLoading); },
          success: function (response) {
            console.log(response)
            if (response && response.success == true) {
              $('#snappbox-cancel-response').html('<span class="sb-success">' + response.data + '</span>');
              hide($cancelLoading);
              ym(105087875, 'reachGoal', 'order-cancelation')
              window.location.reload();
            } else {
              var msg = (response && response.data) ? response.data : 'خطا';
              $('#snappbox-cancel-response').html('<span class="sb-error">Error: ' + msg + '</span>');
              hide($cancelLoading);
            }
          },
          error: function () {
            $('#snappbox-cancel-response').text((SNAPPBOX_GLOBAL && SNAPPBOX_GLOBAL.i18n && SNAPPBOX_GLOBAL.i18n.cancelError) || 'Error cancelling order.');
            hide($cancelLoading);
          }
        });
      });

    })();

  });

})(jQuery);
