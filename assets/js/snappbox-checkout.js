(function ($) {
    "use strict";

    var SB_STATE = {
      selectedDate: null, 
      selectedTime: null  
    };
  

    function getChosenShippingMethods() {
      var vals = [];
      $('input[name^="shipping_method["][type="radio"]:checked').each(function () {
        vals.push($(this).val() || "");
      });
      $('input[name^="shipping_method["][type="hidden"]').each(function () {
        vals.push($(this).val() || "");
      });
      $('select[name^="shipping_method["]').each(function () {
        vals.push($(this).val() || "");
      });
      return vals;
    }
  
    function isSnappBoxSelected() {
      return getChosenShippingMethods().some(function (v) {
        return /^snappbox_shipping_method(?::|$)/.test(v || "");
      });
    }
  
    function positionMapSection() {
      var $map = $("#snappbox-map-section");
      var $addrField = $("#billing_address_1_field");
      if (!$map.length || !$addrField.length) return;
  
      if ($addrField.next()[0] !== $map[0]) {
        $map.insertAfter($addrField);
      }
    }
  
    function toggleMapSection() {
      var $section = $("#snappbox-map-section");
      if (!$section.length) return;
  
      if (isSnappBoxSelected()) {
        $section.show();
        if (window.snappboxMap) {
          setTimeout(function () {
            try { window.snappboxMap.resize(); } catch (e) {}
          }, 50);
        }
      } else {
        $section.hide();
        $(
          "#customer_latitude, #customer_longitude, #customer_city, #customer_address, #customer_postcode, #customer_state, #customer_country"
        ).val("");
      }
    }
  
    
    function renderInto($row) {
      if (!window.SNAPPB_DELIVERY_DATES) return;
  
      var $box    = $row.find(".snappbox-checkout-box");
      var $grid   = $row.find("#snappbox_day_grid");
      var $hidden = $row.find("input.snappbox-day-hidden");
      var $time   = $row.find("select.snappbox-time");
  
      if (!$box.length || !$grid.length || !$hidden.length || !$time.length) return;
  
      if (!$grid.data("sbInit")) {
        $grid.data("sbInit", true);
        var cands = SNAPPB_DELIVERY_DATES.candidates || [];
        $grid.empty();
  
        cands.forEach(function (c, idx) {
          var $label = $('<label class="snappbox-day-card" />');
          var $input = $('<input type="radio" name="snappbox_day_choice" />')
            .val(c.date_iso)
            .attr("data-date", c.date_iso);
  
          var shouldCheck = (SB_STATE.selectedDate ? SB_STATE.selectedDate === c.date_iso : idx === 0);
          if (shouldCheck) $input.prop("checked", true);
  
          $label.append($input);
          $label.append('<div class="day-title">' + c.label.title + "</div>");
          $label.append('<div class="day-date">' + c.label.d + "</div>");
          $label.append('<div class="day-month">' + c.label.month + "</div>");
          $grid.append($label);
        });
      }
  
      applySelected($row);
    }
  
    function applySelected($row) {
      var $grid   = $row.find("#snappbox_day_grid");
      var $hidden = $row.find("input.snappbox-day-hidden");
      var $time   = $row.find("select.snappbox-time");
  
      if (SB_STATE.selectedDate) {
        var $radioForState = $grid.find('input[type="radio"][data-date="' + SB_STATE.selectedDate + '"]');
        if ($radioForState.length) {
          $radioForState.prop("checked", true);
        }
      }
  
      $grid.find(".snappbox-day-card").each(function () {
        var checked = $(this).find('input[type="radio"]').prop("checked");
        $(this).toggleClass("snappbox-selected", !!checked);
      });
  
      var $sel = $grid.find('input[type="radio"]:checked');
      var dateKey = $sel.data("date");
  
      if (dateKey) {
        SB_STATE.selectedDate = String(dateKey);
        $hidden.val(SB_STATE.selectedDate);
        fillTimes($time, SB_STATE.selectedDate);
      }
  
      if (SB_STATE.selectedTime) {
        var exists = $time.find('option[value="' + SB_STATE.selectedTime.replace(/"/g, '\\"') + '"]').length > 0;
        if (exists) {
          $time.val(SB_STATE.selectedTime);
        } else {
          SB_STATE.selectedTime = null;
        }
      }
    }
  
    function fillTimes($timeSel, dateKey) {
      var slots = (SNAPPB_DELIVERY_DATES.timesByDate || {})[dateKey] || [];
      var previous = $timeSel.val();
  
      $timeSel.empty();
      slots.forEach(function (s) {
        $("<option/>", { value: s, text: s }).appendTo($timeSel);
      });
  
      if (SB_STATE.selectedTime && $timeSel.find('option[value="' + SB_STATE.selectedTime.replace(/"/g, '\\"') + '"]').length) {
        $timeSel.val(SB_STATE.selectedTime);
      } else if (previous && $timeSel.find('option[value="' + previous.replace(/"/g, '\\"') + '"]').length) {
        $timeSel.val(previous);
        SB_STATE.selectedTime = previous;
      } else if ($timeSel.find("option").length) {
        SB_STATE.selectedTime = $timeSel.find("option").first().val();
        $timeSel.val(SB_STATE.selectedTime);
      } else {
        SB_STATE.selectedTime = null;
      }
    }
  
    function mountRow() {
      var $row = $("tr.snappbox-delivery-tr");
      if (!$row.length) {
        toggleMapSection();
        positionMapSection();
        return;
      }
      if (isSnappBoxSelected()) {
        $row.show();
        renderInto($row);
      } else {
        $row.hide();
      }
      toggleMapSection();
      positionMapSection();
    }

    $(function () {
      toggleMapSection();
      positionMapSection();
      mountRow();
  
      $(document.body)
        .off("click.snappbox", ".snappbox-day-card")
        .on("click.snappbox", ".snappbox-day-card", function () {
          var $row = $(this).closest("tr.snappbox-delivery-tr");
          var $input = $(this).find('input[type="radio"]');
          if (!$input.prop("checked")) $input.prop("checked", true).trigger("change");
          SB_STATE.selectedDate = String($input.data("date") || "");
          applySelected($row);
        });
  
      $(document.body)
        .off("change.snappbox", 'input[name="snappbox_day_choice"]')
        .on("change.snappbox", 'input[name="snappbox_day_choice"]', function () {
          SB_STATE.selectedDate = String($(this).data("date") || "");
          applySelected($(this).closest("tr.snappbox-delivery-tr"));
        });
  
      $(document.body)
        .off("change.snappbox", "select.snappbox-time")
        .on("change.snappbox", "select.snappbox-time", function () {
          SB_STATE.selectedTime = $(this).val() || null;
        });
  
      var reapplyAll = function () {
        toggleMapSection();
        positionMapSection();
        mountRow(); 
      };
  
      $(document.body).on(
        "updated_checkout updated_shipping_method updated_wc_div change",
        reapplyAll
      );
      $(document.body).on("change", 'input[name^="shipping_method["]', reapplyAll);
  
      var obs = new MutationObserver(function () { positionMapSection(); });
      if (document.body) {
        obs.observe(document.body, { childList: true, subtree: true });
      }
    });
  })(jQuery);
  