(function ($) {
  if ("undefined" === typeof checkerList) {
    return;
  }

  var activePluginsSwitch = $("input[type=radio][name=active_plugins]");

  var queue = [];

  var xhr = false;

  // Fake data before https://github.com/10up/phpcompat/pull/2 is merged
  checkerList = {
    plugins: [
      {
        slug: "akismet",
        name: "Akismet Anti-Spam",
        version: "4.2.4",
        active: "no",
      },
      {
        slug: "debug-bar",
        name: "Debug Bar",
        version: "1.1.3",
        active: "yes",
      },
    ],
    themes: [
      {
        slug: "twentytwentytwo",
        name: "Twenty Twenty-Two",
        version: "1.2",
        active: "yes",
      },
      {
        slug: "twentytwenty",
        name: "Twenty Twenty",
        version: "2.0",
        active: "no",
      },
      {
        slug: "twentytwentyone",
        name: "Twenty Twenty-One",
        version: "1.6",
        active: "no",
      },
    ],
  };

  init(checkerList);

  activePluginsSwitch.on("change", function () {
    init(checkerList);
    console.log(queue);
  });

  $("#runButton").on("click", function (event) {
    //event.prevenDefault();
    runNextJob();
  });

  function init(itemsToScan) {
    var activePlugins = $(
      "input[type=radio][name=active_plugins]:checked"
    ).val();
    renderResults(itemsToScan, activePlugins);
    initQueue(itemsToScan, activePlugins);
  }

  function renderResults(itemsToScan, activePlugins) {
    // Todo in ENG-4
  }

  function initQueue(itemsToScan, activePlugins) {
    // Reset the queue.
    queue.length = 0;

    itemsToScan.plugins.forEach((plugin) => {
      if ("yes" === plugin.active || "no" === activePlugins) {
        queue.push({
          type: "plugin",
          slug: plugin.slug,
          version: plugin.version,
        });
      }
    });

    itemsToScan.themes.forEach((theme) => {
      if ("yes" === theme.active || "no" === activePlugins) {
        queue.push({
          type: "theme",
          slug: theme.slug,
          version: theme.version,
        });
      }
    });
  }

  function executeJob(job, cb) {
    // TODO indicate current plugin/theme in progress

    var endpoint = `https://staging.wptide.org/api/v1/audit/wporg/${job.type}/${job.slug}/${job.version}?reports=phpcs_phpcompatibilitywp`;

    // Only allow 1 concurrent request at a time
    if (false === xhr || xhr.readyState === 4) {
      xhr = $.ajax(endpoint, { dataType: "json", cache: false }).done(
        (response) => {
          console.log(response);
          if ("complete" === response.status) {
            // TODO Perform rendering of results here.
            console.log(response.reports?.phpcs_phpcompatibilitywp?.report);
          } else if ("pending" === response.status) {
            const now = new Date();
            // Retry in 5 seconds.
            job.retryAt = new Date(now.getTime() + 5000);
            queue.push(job);
          }

          cb();
        }
      );
    }
  }

  function runNextJob() {
    if (queue.length === 0) {
      // Nothing more to do.
      console.log("Bye!");
      return;
    }

    const now = new Date();

    // Pick up next job from queue.
    var job = queue.shift();
    console.log(job);

    // The job is new or the time has come.
    if ("undefined" === typeof job.retryAt || job.retryAt <= now) {
      // Run the job now.
      console.log("run it");
      executeJob(job, () => {
        runNextJob();
      });
    } else {
      // Or put it back to the end of queue.
      console.log("wait for it");
      queue.push(job);
      setTimeout(runNextJob, 1000);
    }
  }
})(jQuery);
