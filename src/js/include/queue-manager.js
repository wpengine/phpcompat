import { updateResultFailure, updateResult } from "./render";
import $ from "jquery";

export function initQueue(itemsToScan, activeOnly) {
  // Reset the queue.
  window.phpcompat.queue = [];

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
}

export function executeJob(job, cb) {
  // TODO indicate current plugin/theme in progress

  var endpoint = `https://staging.wptide.org/api/v1/audit/wporg/${job.type}/${job.slug}/${job.version}?reports=phpcs_phpcompatibilitywp`;

  // Only allow 1 concurrent request at a time
  if (false === window.phpcompat.xhr || window.phpcompat.xhr.readyState === 4) {
    window.phpcompat.xhr = $.ajax(endpoint, {
      dataType: "json",
      cache: false,
      beforeSend: () => {
        const resultItem = $(`#${job.type}_${job.slug}`);
        if (!resultItem.find(".spinner").length) {
          const spinner = $('<span class="spinner"></span>');
          spinner.show().appendTo(resultItem);
        }
      },
    }).done((response) => {
      if ("complete" === response.status) {
        updateResult(response, job);
      } else if ("pending" === response.status) {
        const now = new Date();
        console.log("Report is pending, retry in 5 seconds");
        // Retry in 5 seconds.
        job.retryAt = new Date(now.getTime() + 5000);
        window.phpcompat.queue.push(job);
      } else {
		// Unexpected behaviour. Stop scanning and display current status.
		updateResultFailure(response, job);
	  }
      cb();
    });
  }
}

export function runNextJob() {
  if (window.phpcompat.queue.length === 0) {
    // Nothing more to do.
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
    setTimeout(runNextJob, 1000);
  }
}
