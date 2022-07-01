import Mustache from "mustache";
import $ from "jquery";
import compareVersions from "compare-versions";

export function initResults(itemsToScan, activeOnly) {
  const container = $("#wpe_pcc_results");
  const template = $("#result-template").html().toString();
  container.empty();

  if (itemsToScan.plugins.length) {
    container.append("<h3>Plugins</h3>");
    itemsToScan.plugins.forEach((plugin) => {
      if ("yes" === plugin.active || "no" === activeOnly) {
        const view = {
          ...plugin,
          type: "plugin",
          status: "pending",
        };

        const output = Mustache.render(template, view);
        container.append(output);
      }
    });
  }

  if (itemsToScan.themes.length) {
    container.append("<h3>Themes</h3>");
    itemsToScan.themes.forEach((theme) => {
      if ("yes" === theme.active || "no" === activeOnly) {
        const view = {
          ...theme,
          type: "theme",
          status: "pending",
        };

        const output = Mustache.render(template, view);
        container.append(output);
      }
    });
  }

  $(".wpe-pcc-results").show();
}

export function updateResult(response, job) {
  const resultItem = $(`#${job.type}_${job.slug}`);
  const template = $("#result-template").html().toString();
  const view = {
    ...job,
  };
  const report = response.reports?.phpcs_phpcompatibilitywp?.report;

  view.status =
    report.totals.errors === 0 && report.totals.warnings === 0
      ? "success"
      : "error";

  const phpVersions = Object.keys(report.versions).sort(compareVersions);
  view.php = [];
  view.reports = [];
  phpVersions.forEach((phpVersion) => {
    view.php.push({
      phpversion: phpVersion,
      passed: report.compatible.includes(phpVersion),
      error: report.incompatible.includes(phpVersion),
    });

    if (report.incompatible.includes(phpVersion)) {
      const messages = [];
      const files = report.versions[phpVersion].files;
      Object.keys(files).forEach((file) => {
        if (files[file].messages.length) {
          files[file].messages.forEach((message) => {
            messages.push(
              `${file}:${message.line}\n${message.source}\n${message.message}`
            );
          });
        }
      });

      view.reports.push({
        phpversion: phpVersion,
        messages: messages,
      });
    }
  });

  view.has_errors = !(
    report.totals.errors === 0 && report.totals.warnings === 0
  );

  const output = Mustache.render(template, view);

  resultItem.replaceWith(output);
}
