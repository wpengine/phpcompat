import { renderResults } from "./render";
import jQuery from "jquery";

export function initQueue(itemsToScan, activeOnly) {
  // Reset the queue.
  queue.length = 0;

  itemsToScan.plugins.forEach((plugin) => {
    if ("yes" === plugin.active || "no" === activeOnly) {
      queue.push({
        type: "plugin",
        slug: plugin.slug,
        version: plugin.version,
      });
    }
  });

  itemsToScan.themes.forEach((theme) => {
    if ("yes" === theme.active || "no" === activeOnly) {
      queue.push({
        type: "theme",
        slug: theme.slug,
        version: theme.version,
      });
    }
  });
}

export function executeJob(job, cb) {
  // TODO indicate current plugin/theme in progress

  var endpoint = `https://staging.wptide.org/api/v1/audit/wporg/${job.type}/${job.slug}/${job.version}?reports=phpcs_phpcompatibilitywp`;

  // Only allow 1 concurrent request at a time
  if (false === xhr || xhr.readyState === 4) {
    xhr = jQuery.ajax(endpoint, { dataType: "json", cache: false }).done(
      (response) => {
        // console.log(response);
        if ("complete" === response.status) {
          renderResults(response);
          console.log(
            "Report is ready",
            response.reports?.phpcs_phpcompatibilitywp?.report
          );
        } else if ("pending" === response.status) {
          const now = new Date();
          console.log("Report is pending, retry in 5 seconds");
          // Retry in 5 seconds.
          job.retryAt = new Date(now.getTime() + 5000);
          queue.push(job);
        }

        cb();
      }
    );
  }
}

export function runNextJob() {
  if (queue.length === 0) {
    // Nothing more to do.
    console.log("Bye!");
    return;
  }

  const now = new Date();

  // Pick up next job from queue.
  var job = queue.shift();
  console.log("Queue item", job);

  // The job is new or the time has come.
  if ("undefined" === typeof job.retryAt || job.retryAt <= now) {
    // Run the job now.
    console.log("Run it");
    executeJob(job, () => {
      runNextJob();
    });
  } else {
    // Or put it back to the end of queue.
    console.log("Now is", now, "wait for it until", job.retryAt);
    queue.push(job);
    setTimeout(runNextJob, 1000);
  }
}
