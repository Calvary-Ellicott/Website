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
  $(document).on("click", ".ce-pick-image", function (event) {
    event.preventDefault();
    const target = $("#" + $(this).data("target"));
    const frame = wp.media({ title: "Choose a picture", button: { text: "Use this picture" }, library: { type: "image" }, multiple: false });
    frame.on("select", function () {
      const file = frame.state().get("selection").first().toJSON();
      const url = file.sizes && file.sizes.large ? file.sizes.large.url : file.url;
      target.val(url).trigger("change");
      target.closest("td").find(".ce-image-preview").attr("src", url);
    });
    frame.open();
  });
  $(document).on("click", ".ce-clear-image", function (event) {
    event.preventDefault();
    const target = $("#" + $(this).data("target"));
    target.val("").trigger("change");
    target.closest("td").find(".ce-image-preview").attr("src", "");
  });
  $(document).on("click", ".ce-clear-pdf", function (event) {
    event.preventDefault();
    $("#" + $(this).data("target")).val("").trigger("change");
  });
});
