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
  function galleryWrite(box) {
    const urls = box.find(".ce-gallery-item").map(function () { return $(this).data("url"); }).get();
    box.find(".ce-gallery-value").val(urls.join("\n"));
  }
  $(document).on("click", ".ce-gallery-add", function (event) {
    event.preventDefault();
    const box = $(this).closest(".ce-gallery");
    const frame = wp.media({ title: "Add banner pictures", button: { text: "Add to banner" }, library: { type: "image" }, multiple: "add" });
    frame.on("select", function () {
      frame.state().get("selection").each(function (att) {
        const a = att.toJSON();
        const url = a.sizes && a.sizes.full ? a.sizes.full.url : a.url;
        if (box.find('.ce-gallery-item[data-url="' + url + '"]').length) return;
        box.find(".ce-gallery-grid").append('<figure class="ce-gallery-item" data-url="' + url + '"><img src="' + url + '" alt="" /><button type="button" class="ce-gallery-remove" aria-label="Remove">×</button></figure>');
      });
      galleryWrite(box);
    });
    frame.open();
  });
  $(document).on("click", ".ce-gallery-remove", function (event) {
    event.preventDefault();
    const box = $(this).closest(".ce-gallery");
    $(this).closest(".ce-gallery-item").remove();
    galleryWrite(box);
  });
  $(document).on("click", ".ce-clear-pdf", function (event) {
    event.preventDefault();
    $("#" + $(this).data("target")).val("").trigger("change");
  });
});
