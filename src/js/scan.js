import "../scss/scan.scss";
import jQuery from "jquery";
import { initQueue, runNextJob } from "./include/queue-manager";
import { renderResults } from "./include/render";

(function ($) {
  if ("undefined" === typeof checkerList) {
    return;
  }

  const activeOnlySwitch = $("input[type=radio][name=active_plugins]");

  const queue = [];

  var xhr = false;

  init(checkerList);

  activeOnlySwitch.on("change", function () {
    init(checkerList);
    console.log(queue);
  });

  $("#runButton").on("click", function (event) {
    //event.prevenDefault();
    runNextJob();
  });

  function init(itemsToScan) {
    var activeOnly = $(
      "input[type=radio][name=active_plugins]:checked"
    ).val();
    renderResults(itemsToScan, activeOnly);
    initQueue(itemsToScan, activeOnly);
  }
})(jQuery);
