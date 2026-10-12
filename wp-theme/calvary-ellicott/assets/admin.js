/* "Choose PDF" and "Remove" buttons on the bulletin and sermon forms. */
jQuery(function ($) {
  $(document).on("click", ".ce-pick-pdf", function (event) {
    event.preventDefault();
    const target = $("#" + $(this).data("target"));
    const frame = wp.media({
      title: "Choose a PDF",
      button: { text: "Use this file" },
      library: { type: "application/pdf" },
      multiple: false
    });
    frame.on("select", function () {
      const file = frame.state().get("selection").first().toJSON();
      target.val(file.url).trigger("change");
    });
    frame.open();
  });
  $(document).on("click", ".ce-clear-pdf", function (event) {
    event.preventDefault();
    $("#" + $(this).data("target")).val("").trigger("change");
  });
});
