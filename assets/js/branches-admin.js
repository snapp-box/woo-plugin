(function ($, window) {
  "use strict";

  var config = window.SNAPPBOX_BRANCHES || {};

  function showMessage(type, text) {
    var box = $("#snappbox-message").first();
    box.removeClass("success error").addClass(type);
    box.find(".snappbox-message-text").text(text);
    box.addClass("show");
    window.setTimeout(function () { box.removeClass("show"); }, 3000);
  }

  function polygonToCoordinates(wkt) {
    if (typeof wkt !== "string") throw new Error("Polygon must be a string.");
    var match = wkt.trim().match(/^POLYGON\s*\(\((.+)\)\)$/i);
    if (!match) throw new Error("Invalid WKT polygon.");
    return match[1].split(",").map(function (point) {
      var parts = point.trim().split(/\s+/);
      if (parts.length !== 2) throw new Error("Each coordinate must contain longitude and latitude.");
      return [Number(parts[0]), Number(parts[1])];
    });
  }

  $(function () {
    var modal = $("#branch-modal");
    var selectedBranchId = null;
    var selectedButton = null;

    function openModal(branch) {
      var mapObj = window.snappboxMaps && window.snappboxMaps.newMap;
      var polygon = "";
      modal.addClass("active");

      if (branch) {
        if (mapObj && mapObj.setMarkerLocked) mapObj.setMarkerLocked(true);
        if (branch.polygon) polygon = JSON.stringify(polygonToCoordinates(branch.polygon));
        $("#form-mode").val("edit");
        $("#modal-title").text(config.strings.editBranch);
        $("#branch-id").val(branch.id || "");
        $("#branch-name").val(branch.name || "");
        $("#contact-name").val(branch.contactName || "");
        $("#branch-phone").val(branch.contactPhoneNumber || "");
        $("#branch-address").val(branch.address || "");
        $("#branch-plate").val(branch.plate || "");
        $("#branch-unit").val(branch.unit || "");
        $("#polygon").val(polygon);
        $("#branch-default").prop("checked", branch.defaultAddress === true);
        $("#center-pin").hide();
        $("#save-branch-btn").text(config.strings.editBranch);

        var lat = parseFloat(branch.latitude);
        var lng = parseFloat(branch.longitude);
        if (mapObj) {
          window.setTimeout(function () {
            mapObj.map.resize();
            mapObj.map.setCenter([lng, lat]);
            mapObj.map.setZoom(15);
            mapObj.marker.setLngLat([lng, lat]);
          }, 10);
          mapObj.setPolygon(polygon ? JSON.parse(polygon) : null);
        }
        $("#latitude").val(lat);
        $("#longitude").val(lng);
        return;
      }

      if (mapObj) {
        if (mapObj.setMarkerLocked) mapObj.setMarkerLocked(false);
        mapObj.setPolygon(null);
      }
      $("#form-mode").val("create");
      $("#modal-title").text(config.strings.addNewBranch);
      $("#center-pin").show();
      $("#branch-id, #branch-name, #contact-name, #branch-phone, #branch-plate, #branch-unit, #latitude, #longitude, #polygon").val("");
      $("#branch-default").prop("checked", false);
      $("#save-branch-btn").text(config.strings.addBranch);
    }

    $("#open-branch-modal").on("click", function () { openModal(null); });
    $(document).on("click", ".js-edit-branch", function () { openModal($(this).data("branch")); });
    $(".branch-modal-close").on("click", function () { modal.removeClass("active"); });
    // modal.on("click", function (event) {
    //   if ($(event.target).is("#branch-modal")) modal.removeClass("active");
    // });

    $("#save-branch-btn").on("click", function () {
      var button = $(this);
      button.prop("disabled", true).text(config.strings.adding);
      $.ajax({
        url: config.ajaxUrl,
        type: "POST",
        dataType: "json",
        data: {
          action: "snappbox_save_branch", nonce: config.nonce,
          mode: $("#form-mode").val(), id: $("#branch-id").val(),
          name: $("#branch-name").val(), contactName: $("#contact-name").val(),
          contactPhoneNumber: $("#branch-phone").val(), latitude: $("#latitude").val(),
          longitude: $("#longitude").val(), address: $("#branch-address").val(),
          plate: $("#branch-plate").val(), unit: $("#branch-unit").val(),
          polygon: $("#polygon").val(), defaultAddress: $("#branch-default").is(":checked")
        }
      }).done(function (response) {
        if (response.success) {
          showMessage(response.data.message ? "error" : "success", response.data.message || config.strings.saved);
          if (response.data.success == true) {
            modal.removeClass("active");
            window.location.reload();
          }
        } else {
          showMessage("error", response.data && response.data.message ? response.data.message : config.strings.error);
        }
      }).fail(function () {
        showMessage("error", config.strings.connectionError);

      }).always(function () {
        button.prop("disabled", false);
      });
    });

    $(document).on("click", ".js-delete-branch", function (event) {
      event.preventDefault();
      selectedBranchId = $(this).data("id");
      selectedButton = $(this);
      $("#delete-branch-modal").addClass("show");
    });
    $(document).on("click", ".modal-close, .cancel-delete", function () {
      $("#delete-branch-modal").removeClass("show");
      selectedBranchId = null;
      selectedButton = null;
    });
    $(document).on("click", ".confirm-delete", function () {
      if (!selectedBranchId || !selectedButton) return;
      var button = selectedButton;
      var loader = button.find(".loader");
      var deleteText = button.find(".delete-text");
      loader.removeAttr("hidden");
      deleteText.hide();
      button.prop("disabled", true);
      $.ajax({
        url: config.ajaxUrl,
        type: "POST",
        dataType: "json",
        data: { action: "snappbox_delete_branch", nonce: config.nonce, id: selectedBranchId }
      }).done(function (response) {
        $("#delete-branch-modal").removeClass("show");
        if (response.success) {
          showMessage("success", config.strings.deleted);
          window.setTimeout(function () { window.location.reload(); }, 1000);
        } else {
          showMessage("error", response.data && response.data.message ? response.data.message : config.strings.error);
        }
      }).fail(function () {
        showMessage("error", config.strings.connectionError);
      }).always(function () {
        button.prop("disabled", false);
        loader.attr("hidden", true);
        deleteText.show();
      });
    });
  });
})(jQuery, window);
