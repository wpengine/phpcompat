import Mustache from "mustache";
import $ from "jquery";
import compareVersions from "compare-versions";
const { __ } = wp.i18n;

export function initResults(itemsToScan, activeOnly) {
  const container = $("#wpe_pcc_results");
  const template = $("#result-template").html().toString();
  container.empty();

  if (itemsToScan.plugins.length) {
    container.append("<h3>" + __("Plugins", "wpe-php-compat") + "</h3>");
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
    container.append("<h3>" + __("Themes", "wpe-php-compat") + "</h3>");
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

  // Data to render the template.
  const view = {
    ...job,
  };

  const report = response.reports?.phpcs_phpcompatibilitywp?.report;

  // Success if no errors.
  view.status =
    report.totals.errors === 0 && report.totals.warnings === 0
      ? "success"
      : "error";

  // Create index for PHP Versions.
  const phpVersions = Object.keys(report.versions).sort(compareVersions);
  let rawReport = "";

  view.php = [];
  view.reports = [];

  phpVersions.forEach((phpVersion) => {
    view.php.push({
      phpversion: phpVersion,
      passed: report.compatible.includes(phpVersion),
    });

    // Extract all error messages from incompatible PHP versions.
    if (report.incompatible.includes(phpVersion)) {
      const messages = [];
      const files = report.versions[phpVersion].files;
      const fileReports = [];

      // In each file of the plugin, add error messages.
      Object.keys(files).forEach((file) => {
        if (files[file].messages.length) {
          fileReports.push(processFileReport(file, files[file]));

          files[file].messages.forEach((message) => {
            // Compile plain text with source file, line, error code and error text.
            messages.push(
              `${file}:${message.line}\n${message.source}\n${message.message}`
            );
          });
        }
      });

      // Override raw report with most recent PHP version
      rawReport =
        `${job.name} ${job.version}\n\n` + fileReports.join("\n\n\n") + "\n\n";

      view.reports.push({
        phpversion: phpVersion,
        messages: [rawReport],
      });
    } else {
      rawReport = `${job.name} ${job.version}\n\nCompatible with PHP ${phpVersion}`;
    }
  });

  view.has_errors = !(
    report.totals.errors === 0 && report.totals.warnings === 0
  );

  const output = Mustache.render(template, view);

  resultItem.replaceWith(output);

  const fullReport = $("#testResults").val();
  const updatedReport =
    fullReport + (fullReport.length ? "\n\n\n" : "") + rawReport;

  $("#testResults").val(updatedReport);
  $("#wpe-pcc-codeable-data").val(
    Buffer.from(updatedReport).toString("base64")
  );
}

export function processFileReport(fileName, fileReport) {
  const colWidths = fileReport.messages.reduce(
    (prev, current) => {
      return [
        `${current.line}`.length > prev[0] ? `${current.line}`.length : prev[0],
        `${current.type}`.length > prev[1] ? `${current.type}`.length : prev[1],
        `${current.message}`.length > prev[2]
          ? `${current.message}`.length
          : prev[2],
      ];
    },
    [0, 0, 0]
  );

  const uinqueLines = fileReport.messages.reduce((lines, message) => {
    if (-1 === lines.indexOf(message.line)) {
      lines.push(message.line);
    }
    return lines;
  }, []);

  const plainMessages = fileReport.messages.map((message) => {
    const col1 = message.line.toString().padStart(colWidths[0], " ");
    const col2 = message.type.toString().padEnd(colWidths[1], " ");
    const col3 = message.message.toString().padEnd(colWidths[2], " ");

    return ` ${col1} | ${col2} | ${col3} `;
  });

  const header = `FILE: ${fileName}`;
  const found = `FOUND ${fileReport.errors} ERRORS AND ${fileReport.warnings} WARNINGS AFFECTING ${uinqueLines.length} LINES`;
  const maxWidth = Math.max(
    header.length,
    found.length,
    plainMessages[0].length
  );
  const hr = new Array(maxWidth + 1).join("-");
  const output =
    `${header}\n${hr}\n${found}\n${hr}\n` +
    plainMessages.join("\n") +
    `\n${hr}`;

  return output;
}

export function updateResultFailure(response, job) {
  const resultItem = $(`#${job.type}_${job.slug}`);
  const template = $("#result-template").html().toString();
  const view = {
    ...job,
    status: "error",
    custom_error: true,
    response_status: response.status,
  };

  const output = Mustache.render(template, view);
  resultItem.replaceWith(output);
}
