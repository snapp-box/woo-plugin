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
     * ORDER UI
     * ========================================================= */
    (function () {
      var $ctx = $('.snappbox-admin-context').first();
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

      function polygonContains(wkt, lng, lat) {
        var match = /^POLYGON\s*\(\((.+)\)\)$/i.exec($.trim(wkt || ''));
        if (!match) return false;
        var points = match[1].split(',').map(function (point) {
          return $.trim(point).split(/\s+/).map(Number);
        }).filter(function (point) {
          return point.length === 2 && isFinite(point[0]) && isFinite(point[1]);
        });
        if (points.length < 3) return false;

        var inside = false;
        for (var i = 0, j = points.length - 1; i < points.length; j = i++) {
          var xi = points[i][0], yi = points[i][1];
          var xj = points[j][0], yj = points[j][1];
          if (((yi > lat) !== (yj > lat)) &&
              (lng < ((xj - xi) * (lat - yi) / ((yj - yi) || 0.0000001)) + xi)) {
            inside = !inside;
          }
        }
        return inside;
      }

      function suggestAdminStore() {
        $('.address-selector').each(function () {
          var $select = $(this);
          var $scope = $select.closest('.column-snappbox_action');
          if (!$scope.length) $scope = $(document.body);
          var $rowContext = $scope.find('.snappbox-admin-context').first();
          var rawDestinationLat = $rowContext.attr('data-destination-lat');
          var rawDestinationLng = $rowContext.attr('data-destination-long');
          if ($.trim(rawDestinationLat || '') === '' || $.trim(rawDestinationLng || '') === '') return;
          var destinationLat = Number(rawDestinationLat);
          var destinationLng = Number(rawDestinationLng);
          if (!isFinite(destinationLat) || !isFinite(destinationLng)) return;

          var best = null, bestDistance = Infinity;
          $select.find('option').each(function () {
            var $option = $(this);
            var matches = polygonContains($option.attr('data-polygon'), destinationLng, destinationLat);
            $option.toggleClass('snappbox-store-match', matches)
              .toggleClass('snappbox-store-nonmatch', !matches);
            if (!matches) return;
            var lat = Number($option.attr('data-lat'));
            var lng = Number($option.attr('data-long'));
            var distance = Math.pow(lat - destinationLat, 2) + Math.pow(lng - destinationLng, 2);
            if (distance < bestDistance) {
              best = $option;
              bestDistance = distance;
            }
          });
          if (best) {
            best.prop('selected', true);
            var $modal = $select.closest('.sb-modal, #sb-pricing-modal');
            $modal.find('.selected-address').val(best.attr('data-address') || '');
            $modal.find('.selected-latitude').val(best.attr('data-lat') || '');
            $modal.find('.selected-longitude').val(best.attr('data-long') || '');
            $modal.find('.selected-name').val(best.attr('data-name') || '');
            $modal.find('.selected-contact-name').val(best.attr('data-contact-name') || '');
            $modal.find('.selected-contact-phonenumber').val(best.attr('data-phone') || '');
          }
        });
      }

      function branchDataFromOption($option) {
        if (!$option || !$option.length) return {};
        return {
          branchId: $option.val(),
          branchAddress: $option.attr('data-address') || '',
          branchName: $option.attr('data-name') || '',
          branchContactName: $option.attr('data-contact-name') || '',
          branchLatitude: $option.attr('data-lat') || '',
          branchLongitude: $option.attr('data-long') || '',
          phoneNumber: $option.attr('data-phone') || '',
          branchPlate: $option.attr('data-plate') || '',
          branchUnit: $option.attr('data-unit') || ''
        };
      }

      function syncSelectedBranch($select) {
        var $option = $select.find('option:selected').first();
        var data = branchDataFromOption($option);
        var $modal = $select.closest('.sb-modal, #sb-pricing-modal');
        $modal.find('.selected-address').val(data.branchAddress);
        $modal.find('.selected-latitude').val(data.branchLatitude);
        $modal.find('.selected-longitude').val(data.branchLongitude);
        $modal.find('.selected-name').val(data.branchName);
        $modal.find('.selected-contact-name').val(data.branchContactName);
        $modal.find('.selected-contact-phonenumber').val(data.phoneNumber);
        $modal.find('.selected-plate').val(data.branchPlate);
        $modal.find('.selected-unit').val(data.branchUnit);
        return data;
      }

      function openModal($btn) {
        var $scope = getRowOrPage($btn);
        var $modal = getModal($scope);
        show($modal);
        return $modal;
      }

      // Close modal
      $(document).on('click', '.sb-modal__close, .sb-close-btn', function (e) {
        e.preventDefault();
        e.stopPropagation();

        var $modal = $(this).closest('.sb-modal, #sb-pricing-modal');
        $modal.find('.sb-voucher-code, #sb-voucher-code').val('');
        hide($modal);
      });




      function getSnappboxPricing(extraData = {}, $btn) {
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
        $btn.find('.button-text').hide();

        var previousRequest = $modal.data('snappboxPricingRequest');
        if (previousRequest && previousRequest.readyState !== 4) {
          previousRequest.abort();
        }

        var pricingRequest = $.ajax({
          url: ctx.ajaxUrl,
          type: 'POST',
          dataType: 'json',
          data: $.extend(
            {
              action: 'snappb_get_pricing',
              order_id: orderId,
              voucher_code: voucherCode,
              nonce: ctx.nonce
            },
            extraData
          ),

          beforeSend: function () {
            if ($pricingMsg.length) {
              $pricingMsg.text(
                (window.SNAPPBOX_GLOBAL &&
                  SNAPPBOX_GLOBAL.i18n &&
                  SNAPPBOX_GLOBAL.i18n.priceFetching) ||
                'در حال دریافت قیمت...'
              );
            }

            if ($createBtn.length) {
              $createBtn.attr('disabled', 'disabled');
            }
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

            if ($createBtn.length) {
              $createBtn.removeAttr('disabled');
            }

            if (response && response.success) {

              var fare = Number(response.data && response.data.finalCustomerFare);
              var totalFare = Number(response.data && response.data.totalFare);

              if ($createBtn.length) {
                show($createBtn);
              }

              var finalFare, totalFareDisplay, simbol;

              var hasTotal =
                response.data &&
                response.data.totalFare != null &&
                !isNaN(totalFare);

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

              if (
                hasTotal &&
                !isNaN(totalFareDisplay) &&
                totalFareDisplay > 0 &&
                totalFareDisplay !== finalFare
              ) {
                htmlMsg =
                  'قیمت کل: <span class="sb-strike">' +
                  fmt(totalFareDisplay) +
                  ' ' +
                  simbol +
                  '</span><br>' +
                  'قیمت با تخفیف: ' +
                  fmt(finalFare) +
                  ' ' +
                  simbol;
              } else {
                htmlMsg =
                  'قیمت تخمینی: ' +
                  fmt(finalFare) +
                  ' ' +
                  simbol;
              }

              if ($pricingMsg.length) {
                $pricingMsg.html(htmlMsg);
              }

            } else {

              hide($loading);

              var msg;

              if (response?.data?.voucherMessage) {
                msg = response.data.voucherMessage;
              } else {
                msg =
                  response?.data?.message ||
                  (SNAPPBOX_GLOBAL?.i18n?.priceError ||
                    'خطا در دریافت قیمت.');

                if ($createBtn.length) {
                  hide($createBtn);
                }
              }

              if ($pricingMsg.length) {
                $pricingMsg.text(msg);
              }

              if ($createBtn.length) {
                $createBtn.attr('disabled', 'disabled');
              }
            }
          },

          error: function (jqXHR, textStatus, errorThrown) {
            if (textStatus === 'abort') return;
            console.error('AJAX error:', textStatus, errorThrown, jqXHR);

            if ($pricingMsg.length) {
              $pricingMsg.text(
                SNAPPBOX_GLOBAL?.i18n?.requestError ||
                'خطا در ارسال درخواست.'
              );
            }

            hide($loading);
          },
          complete: function () {
            if ($modal.data('snappboxPricingRequest') === pricingRequest) {
              $modal.removeData('snappboxPricingRequest');
            }
          }
        });
        $modal.data('snappboxPricingRequest', pricingRequest);
      }

      $(document).on('change.snappboxBranch', '.address-selector', function () {
        var $select = $(this);
        getSnappboxPricing(syncSelectedBranch($select), $select);
      });

      suggestAdminStore();





      function handlePricingClick(e) {
        e.preventDefault();
        e.stopPropagation();
        e.stopImmediatePropagation();
        var $btn = $(this);
        var $modal = getModal(getRowOrPage($btn));
        var $option = $modal.find('.address-selector option:selected').first();
        getSnappboxPricing(branchDataFromOption($option), $btn);
        return false;
      }

      // Stop the event on the actual button before WooCommerce's clickable
      // order-row handler can receive it.
      $('.snappbox-pricing-order, #add-voucher-code')
        .off('click.snappboxPricing')
        .on('click.snappboxPricing', handlePricingClick)
        .attr('data-snappbox-click-bound', '1');

      // Keep support for rows that another plugin inserts after page load.
      $(document).on(
        'click.snappboxPricing',
        '.snappbox-pricing-order:not([data-snappbox-click-bound]), #add-voucher-code:not([data-snappbox-click-bound])',
        handlePricingClick
      );


      $(document).on('click', '.snappbox-create-order, #snappbox-create-order', function (e) {
        e.preventDefault();
        e.stopPropagation();

        var $btn = $(this);
        var $scope = getRowOrPage($btn);
        var $modal = getModal($scope);

        var orderId = $btn.data('order-id') || ctx.wooOrderId;

        var $voucher = getVoucher($scope, $modal);
        var voucherCode = $voucher.length ? $voucher.val() : '';

        var $orderLoading = getLoading($btn);

        var $vdsContent = $modal.find('.vds-content').first();
        var $modalContent = $modal.find('.sb-modal__content').first();

        var $victory = $modal.find('#snappbox-response-victory, .snappbox-response-victory').first();
        if (!$victory.length) $victory = $('#snappbox-response-victory').first();

        var $footer = $modal.find(".sb-footer").first();
        var extraData = branchDataFromOption(
          $modal.find('.address-selector option:selected').first()
        );

        var $resp = $modal.find('#snappbox-response, .snappbox-response').first();
        if (!$resp.length) $resp = $('#snappbox-response').first();

        if ($vdsContent.length) $vdsContent.attr('hidden', 'hidden');
        show($orderLoading);

        $.ajax({
          url: ctx.ajaxUrl,
          type: 'POST',
          dataType: 'json',
          data: $.extend({
            action: 'snappb_create_order',
            order_id: orderId,
            voucher_code: voucherCode,
            nonce: ctx.nonce
          },
            extraData,
          ),
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
                $footer.hide();
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

            if (response && response.success == true) {
              $('#snappbox-cancel-response').html('<span class="sb-success">' + response.data + '</span>');
              hide($cancelLoading);
              $btn.hide();
              if (typeof window.ym === 'function') {
                window.ym(105087875, 'reachGoal', 'order-cancelation');
              }
              window.setTimeout(function () {
                window.location.reload();
              }, 1800);
            } else {
              var msg = (response && response.data) ? response.data : 'خطا';
              $('#snappbox-cancel-response').html('<span class="sb-error">Error: ' + msg + '</span>');
              hide($cancelLoading);
            }
            $btn.find('.button-text').show();
          },
          error: function () {
            $('#snappbox-cancel-response').text((SNAPPBOX_GLOBAL && SNAPPBOX_GLOBAL.i18n && SNAPPBOX_GLOBAL.i18n.cancelError) || 'Error cancelling order.');
            hide($cancelLoading);
            $btn.find('.button-text').show();
          }
        });
      });

    })();

  });

})(jQuery);
