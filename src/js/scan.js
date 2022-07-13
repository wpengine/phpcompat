import "../scss/scan.scss";
import jQuery from "jquery";
import { initQueue, runNextJob } from "./include/queue-manager";
import { initResults } from "./include/render";
import { downloadReport } from "./include/download";

(function ($) {
  if ("undefined" === typeof checkerList) {
    return;
  }

  window.phpcompat = {};
  window.phpcompat.queue = [];
  window.phpcompat.xhr = false;
  window.phpcompat.ticker = false;
  window.phpcompat.results = [];

  const activeOnlySwitch = $("input[type=radio][name=active_plugins]");
  const runButton = $("#runButton");

  init(checkerList);

  activeOnlySwitch.on("change", function () {
    init(checkerList);
    runButton.prop("disabled", false);
  });

  $("#cleanupButton").on("click", function (event) {
    event.preventDefault();
    runButton.prop("disabled", false);
    init(checkerList);
  });

  runButton.on("click", function (event) {
    event.preventDefault();
    runNextJob();
    $(this).prop("disabled", true);
  });

  $(document).on("click", ".wpe-pcc-php-version-errors", function (event) {
    event.preventDefault();
    const phpVersion = $(this).data("php-version");
    const reports = $(this).closest(".wpe-pcc-alert").find("#wpe_pcc_reports");
    const report = reports.find(`[data-php-version="${phpVersion}"]`);
    $(".wpe-pcc-php-version-report").not(report).hide();
    $(report).toggle();
  });

  $(document).on(
    "click",
    ".wpe-pcc-php-version-report-close",
    function (event) {
      event.preventDefault();
      $(this).closest(".wpe-pcc-php-version-report").hide();
    }
  );

  $("#developermode").on("change", function (event) {
    if ($(this).is(":checked")) {
      $("#developerMode").show();
      $("#wpe_pcc_results").hide();
    } else {
      $("#developerMode").hide();
      $("#wpe_pcc_results").show();
    }
  });

  $("#downloadReport").on("click", function (event) {
    event.preventDefault();
    downloadReport();
  });

  function init(itemsToScan) {
    var activeOnly = $("input[type=radio][name=active_plugins]:checked").val();
    initQueue(itemsToScan, activeOnly);
    initResults(itemsToScan, activeOnly);
  }
})(jQuery);
