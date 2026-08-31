jQuery(document).on("click", ".snapp-close", function () { jQuery("#snapp-modal").fadeOut(200); });
jQuery(document).on("click", "#snapp-modal", function (e) { if (e.target.id === "snapp-modal") { jQuery("#snapp-modal").fadeOut(200); } });
