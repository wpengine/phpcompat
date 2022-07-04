import $ from "jquery";

export function showDownload() {
  $("#wpe-pcc-download-report").show();
}

export function hideDownload() {
  $("#wpe-pcc-download-report").hide();
}

export function downloadReport() {
  const rawData = $("#testResults").val();

  if (rawData.length) {
    var el = document.createElement("a");
    el.setAttribute(
      "href",
      "data:text/plain;charset=utf-8," + encodeURIComponent(rawData)
    );
    el.setAttribute("download", "report.txt");

    el.style.display = "none";
    document.body.appendChild(el);

    el.click();

    document.body.removeChild(el);
  }
}
