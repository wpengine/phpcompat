import { updateResultFailure, updateResult } from "./render";
import $ from "jquery";
import { hideDownload, showDownload } from "./download";

const progress = $(".wpe-progress-active");
const progressCount = $(".wpe-pcc-progress-count");

export function initQueue(itemsToScan, activeOnly) {
  // Reset the queue.
  window.phpcompat.queue = [];
  window.phpcompat.results = [];
  window.phpcompat.total = 0;
  window.phpcompat.completed = 0;

  clearTimeout(window.phpcompat.ticker);
  if (window.phpcompat.xhr) {
    window.phpcompat.xhr.abort();
  }

  hideDownload();
  resetProgress();
  $("#testResults").val("");

  itemsToScan.plugins.forEach((plugin) => {
    if ("yes" === plugin.active || "no" === activeOnly) {
      window.phpcompat.queue.push({
        ...plugin,
        type: "plugin",
      });
    }
  });

  itemsToScan.themes.forEach((theme) => {
    if ("yes" === theme.active || "no" === activeOnly) {
      window.phpcompat.queue.push({
        ...theme,
        type: "theme",
      });
    }
  });

  window.phpcompat.total = window.phpcompat.queue.length;
}

export function executeJob(job, cb) {
  // TODO Change staging to live endpoint
  var endpoint = `https://staging.wptide.org/api/v1/audit/wporg/${job.type}/${job.slug}/${job.version}?reports=phpcs_phpcompatibilitywp`;

  // Only allow 1 concurrent request at a time
  if (
    false === window.phpcompat.xhr ||
    [0, 4].includes(window.phpcompat.xhr.readyState)
  ) {
    window.phpcompat.xhr = $.ajax(endpoint, {
      dataType: "json",
      beforeSend: () => {
        const resultItem = $(`#${job.type}_${job.slug}`);
        if (!resultItem.find(".spinner").length) {
          const spinner = $('<span class="spinner"></span>');
          spinner.show().appendTo(resultItem);
        }
      },
    })
      .done((response) => {
        if ("complete" === response.status) {
          window.phpcompat.results.push({ ...job, ...response });
          updateResult(response, job);
          updateProgress();
        } else if ("pending" === response.status) {
          const now = new Date();
          console.log("Report is pending, retry in 5 seconds");
          // Retry in 5 seconds.
          job.retryAt = new Date(now.getTime() + 5000);
          window.phpcompat.queue.push(job);
        } else {
          updateProgress();
          // Unexpected behaviour. Stop scanning and display current status.
          updateResultFailure(response, job);
        }
      })
      .fail((jqXHR) => {
        updateProgress();

        // If connection was lost during scan, show error message.
        if (!jqXHR.responseJSON && 0 === jqXHR.status && 0 === jqXHR.readyState) {
          updateResultFailure(
            {
              status: "failed",
              message: "The audit of this code was interrupted, please scan again.",
            },
            job
          );
        } else {
          updateResultFailure(
            jqXHR.responseJSON ?? {
              status: jqXHR.status,
              message: jqXHR.responseText,
            },
            job
          );
        }
      })
      .always(() => {
        cb();
      });
  }
}

export function runNextJob() {
  if (window.phpcompat.queue.length === 0) {
    resetProgress();
    showDownload();
    $(".wpe-pcc-information").show();
    return;
  }

  const now = new Date();

  // Pick up next job from queue.
  var job = window.phpcompat.queue.shift();

  // The job is new or the time has come.
  if ("undefined" === typeof job.retryAt || job.retryAt <= now) {
    // Run the job now.
    executeJob(job, () => {
      runNextJob();
    });
  } else {
    // Or put it back to the end of queue.
    window.phpcompat.queue.push(job);
    window.phpcompat.ticker = setTimeout(runNextJob, 1000);
  }
}

export function updateProgress() {
  window.phpcompat.completed++;
  progress.show();
  progressCount.text(`${window.phpcompat.completed} of ${window.phpcompat.total}`);
}

function resetProgress() {
  $(".wpe-pcc-spinner").hide();
  progressCount.text("");
  progress.hide();
}
