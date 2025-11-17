/* global SNAPPBOX_GLOBAL, jQuery, maplibregl */
(function($){
  'use strict';

  $(function(){

    // ---------- MAP ----------
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
      } catch(e) {
        // eslint-disable-next-line no-console
        console.error('Map init error:', e);
      }
    }

    // ---------- ORDER UI ----------
    var $ctx = $('#snappbox-admin-context');
    if (!$ctx.length) return;

    var resolvedNonce =
      (SNAPPBOX_GLOBAL && SNAPPBOX_GLOBAL.nonce) ||
      $ctx.data('nonce') ||
      '';

    var ctx = {
      ajaxUrl: (SNAPPBOX_GLOBAL && SNAPPBOX_GLOBAL.ajaxUrl) || '',
      nonce: resolvedNonce,
      currency: ($ctx.data('currency') || '').toString(),
      wooOrderId: parseInt($ctx.data('woo-order-id'), 10)
    };

    if (!ctx.ajaxUrl) {
      // eslint-disable-next-line no-console
      console.warn('SNAPPBOX: ajaxUrl is missing.');
    }
    if (!ctx.nonce) {
      // eslint-disable-next-line no-console
      console.warn('SNAPPBOX: nonce is missing. AJAX requests may fail.');
    }

    var $modal = $('#sb-pricing-modal');
    var $pricingMsg = $('#pricing-message');
    var $voucher = $('#sb-voucher-code');
    var $createBtn = $('#snappbox-create-order');
    var $loading = $('.loading');
    var $orderLoading = $('.ct-order-loading');
    var $cancelLoading = $('.cancel-order-loading');

    function rialToToman(v){ return parseInt(v, 10) / 10; }
    function fmt(n) {
      try {
        return new Intl.NumberFormat('en-IR', { maximumSignificantDigits: 3 }).format(n);
      } catch (_) {
        return n;
      }
    }
    function show(el){ el.removeAttr('hidden'); }
    function hide(el){ el.attr('hidden', true); }

    // Open/close modal
    $('.sb-modal__close').on('click', function(e){
      e.preventDefault();
      $voucher.val('');
      hide($modal);
    });

    function openModal(){
      show($modal);
    }

    // Pricing + voucher handler (both buttons share logic)
    $('#snappbox-pricing-order, #add-voucher-code').on('click', function(e){
      e.preventDefault();

      var orderId = $(this).data('order-id');
      var voucherCode = $voucher.val();

      show($loading);

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
        beforeSend: function(){
          $pricingMsg.text((SNAPPBOX_GLOBAL && SNAPPBOX_GLOBAL.i18n && SNAPPBOX_GLOBAL.i18n.priceFetching) || 'در حال دریافت قیمت...');
          $createBtn.attr('disabled', 'disabled');
        },
        success: function(response){
          openModal();
          $createBtn.removeAttr('disabled');

          if (response && response.success) {
            var fare = Number(response.data.finalCustomerFare);
            var totalFare = Number(response.data.totalFare);

            show($createBtn);

            var finalFare, totalFareDisplay, simbol;
            var hasTotal = response.data.totalFare != null && !isNaN(totalFare);

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

            $pricingMsg.html(htmlMsg);
          } else {
            hide($loading);
            var msg;
            if (response && response.data && response.data.voucherMessage) {
              msg = response.data.voucherMessage;
            } else {
              msg = (response && response.data && response.data.message)
                ? response.data.message
                : ((SNAPPBOX_GLOBAL && SNAPPBOX_GLOBAL.i18n && SNAPPBOX_GLOBAL.i18n.priceError) || 'خطا در دریافت قیمت.');
              hide($createBtn);
            }
            $pricingMsg.text(msg);
            $createBtn.attr('disabled', 'disabled');
          }
        },
        error: function(jqXHR, textStatus, errorThrown){
          // eslint-disable-next-line no-console
          console.error('AJAX error:', textStatus, errorThrown, jqXHR);
          $pricingMsg.text((SNAPPBOX_GLOBAL && SNAPPBOX_GLOBAL.i18n && SNAPPBOX_GLOBAL.i18n.requestError) || 'خطا در ارسال درخواست.');
          hide($loading);
        }
      });
    });

    // Create order
    $createBtn.on('click', function(e){
      e.preventDefault();

      var orderId = $(this).data('order-id');
      var voucherCode = $voucher.val();
      $('.vds-content').attr('hidden', 'hidden');

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
        beforeSend: function(){ show($orderLoading); },
        success: function(response){
          if (response && response.success && response.response && response.response.data &&
              (response.response.status_code === 201 || response.response.status_code === '201')) {
            $('.sb-modal__content, .sb-modal__content *').hide();
            $('.vds-content').removeAttr('hidden');
            $('#snappbox-response-victory').html('<span class="sb-success">' + (response.response.message || ((SNAPPBOX_GLOBAL && SNAPPBOX_GLOBAL.i18n && SNAPPBOX_GLOBAL.i18n.created) || 'Created')) + '</span>');
            window.location.reload();
          } else {
            var errMsg = (response && response.response) ? response.response.message : ((SNAPPBOX_GLOBAL && SNAPPBOX_GLOBAL.i18n && SNAPPBOX_GLOBAL.i18n.unknownError) || 'Unknown error');
            $('#snappbox-response').html('<span class="sb-error">Error: ' + errMsg + '</span>');
          }
          hide($orderLoading);
        },
        error: function(){
          $('#snappbox-response').text((SNAPPBOX_GLOBAL && SNAPPBOX_GLOBAL.i18n && SNAPPBOX_GLOBAL.i18n.orderSendErr) || 'Error sending order.');
          hide($orderLoading);
        }
      });
    });

    // Cancel order
    $('#snappbox-cancel-order').on('click', function(e){
      e.preventDefault();

      var orderId = $(this).data('order-id');

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
        beforeSend: function(){ show($cancelLoading); },
        success: function(response){
          if (response && response.success) {
            $('#snappbox-cancel-response').html('<span class="sb-success">' + response.data + '</span>');
            hide($cancelLoading);
            window.location.reload();
          } else {
            var msg = (response && response.data) ? response.data : 'خطا';
            $('#snappbox-cancel-response').html('<span class="sb-error">Error: ' + msg + '</span>');
            hide($cancelLoading);
          }
        },
        error: function(){
          $('#snappbox-cancel-response').text((SNAPPBOX_GLOBAL && SNAPPBOX_GLOBAL.i18n && SNAPPBOX_GLOBAL.i18n.cancelError) || 'Error cancelling order.');
          hide($cancelLoading);
        }
      });
    });

  });
})(jQuery);
