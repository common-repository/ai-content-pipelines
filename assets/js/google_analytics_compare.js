jQuery(document).ready(function ($) {
  // Google Sign-In Flow
  $("#google_signin_button").on("click", function () {
    // Open Google OAuth window
    const authWindow = window.open(
      "https://ai.1upmedia.com:443/google/auth", // Your Node.js /auth route
      "Google Auth",
      "width=600,height=400"
    );

    // Listen for message from the popup
    window.addEventListener(
      "message",
      function (event) {
        if (event.data.type === "googleAuthSuccess") {
          const accessToken = event.data.accessToken;
          $("#google_access_token").val(accessToken);

          // Automatically fetch and display sites in the dropdown
          fetch(
            `https://ai.1upmedia.com:443/google/sites?accessToken=${encodeURIComponent(
              accessToken
            )}`
          )
            .then((response) => response.json())
            .then((data) => {
              const siteSelect = $("#site_select");
              siteSelect.html('<option value="">Select a site</option>'); // Reset previous options
              data.forEach((site) => {
                siteSelect.append(
                  `<option value="${site.siteUrl}">${site.siteUrl} (${site.permissionLevel})</option>`
                );
              });

              // Show the dropdown and hide the sign-in button
              $("#gsc_list_sites_container").show();
              $("#google_signin_button").hide();

              // Show other buttons after site selection
              siteSelect.on("change", function () {
                if (siteSelect.val()) {
                  $("#gsc_buttons").show();
                } else {
                  $("#gsc_buttons").hide();
                }
              });
            })
            .catch((error) => {
              $("#comparison_results").text("Error fetching sites: " + error);
            });
        }
      },
      false
    );
  });

  // Get Analytics Comparison
  $("#compare_button").on("click", function () {
    const accessToken = $("#google_access_token").val();
    const siteUrl = $("#site_select").val();
    const startDate1 = $("#start_date_1").val();
    const endDate1 = $("#end_date_1").val();
    const startDate2 = $("#start_date_2").val();
    const endDate2 = $("#end_date_2").val();

    if (
      !accessToken ||
      !siteUrl ||
      !startDate1 ||
      !endDate1 ||
      !startDate2 ||
      !endDate2
    ) {
      alert(
        "Please sign in with Google, select a site, and provide both date ranges."
      );
      return;
    }

    // Fetch the comparison data from the backend
    fetch(`https://ai.1upmedia.com:443/google/compare-analytics`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        accessToken: accessToken,
        siteUrl: siteUrl,
        startDate1: startDate1,
        endDate1: endDate1,
        startDate2: startDate2,
        endDate2: endDate2,
      }),
    })
      .then((response) => response.json())
      .then((data) => {
        displayComparisonResults(data.comparedData);
        renderComparisonChart(data.comparedData);
      })
      .catch((error) => {
        $("#comparison_results").text(
          "Error fetching comparison data: " + error
        );
      });
  });

  // Display comparison results in table format
  function displayComparisonResults(comparedData) {
    let tableHtml =
      '<table class="table table-striped"><thead><tr><th>Query</th><th>Clicks (Range 1 → Range 2)</th><th>Impressions (Range 1 → Range 2)</th><th>CTR (Range 1 → Range 2)</th><th>Position (Range 1 → Range 2)</th><th>Performance</th></tr></thead><tbody>';

    comparedData.forEach((data) => {
      const range1Clicks = data.range1 ? data.range1.clicks : 0;
      const range1Impressions = data.range1 ? data.range1.impressions : 0;
      const range1Ctr = data.range1
        ? (data.range1.ctr * 100).toFixed(2) + "%"
        : "0%";
      const range1Position = data.range1 ? data.range1.position : "N/A";

      const range2Clicks = data.range2 ? data.range2.clicks : "N/A";
      const range2Impressions = data.range2 ? data.range2.impressions : "N/A";
      const range2Ctr = data.range2
        ? (data.range2.ctr * 100).toFixed(2) + "%"
        : "N/A";
      const range2Position = data.range2 ? data.range2.position : "N/A";

      const performanceEmoji = data.range2
        ? data.clicksDiff > 0
          ? '<span class="green-up-arrow">&#x2191;</span>'
          : data.clicksDiff < 0
          ? '<span class="red-down-arrow">&#x2193;</span>'
          : '<span class="green-up-arrow">&#x2191;</span>'
        : '<span class="red-down-arrow">&#x2193;</span>';

      tableHtml += `
              <tr>
                  <td>${data.query}</td>
                  <td>${range1Clicks} → ${range2Clicks}</td>
                  <td>${range1Impressions} → ${range2Impressions}</td>
                  <td>${range1Ctr} → ${range2Ctr}</td>
                  <td>${range1Position} → ${range2Position}</td>
                  <td>${performanceEmoji}</td>
              </tr>`;
    });

    tableHtml += "</tbody></table>";
    $("#analytics_table_container").html(tableHtml);
  }

  // Render comparison chart using Chart.js
  function renderComparisonChart(comparedData) {
    const validData = comparedData.filter(
      (data) => data.range1 && (data.range2 || data.range2 === null)
    );

    const sortedData = validData.sort(
      (a, b) => b.impressionsDiff - a.impressionsDiff
    );
    const top5Performing = sortedData.slice(0, 5);
    const top5Decreasing = sortedData.slice(-5).reverse();

    const selectedData = [...top5Performing, ...top5Decreasing];

    const labels = selectedData.map((data) => data.query);
    const clicksDataRange1 = selectedData.map((data) =>
      data.range1 ? data.range1.clicks : 0
    );
    const clicksDataRange2 = selectedData.map((data) =>
      data.range2 ? data.range2.clicks : 0
    );

    const ctx = $("#comparisonChart").get(0).getContext("2d");
    if (ctx.chart) {
      ctx.chart.destroy();
    }

    ctx.chart = new Chart(ctx, {
      type: "bar",
      data: {
        labels: labels,
        datasets: [
          {
            label: "Clicks (Range 1)",
            data: clicksDataRange1,
            backgroundColor: "rgba(75, 192, 192, 0.5)",
            borderColor: "rgba(75, 192, 192, 1)",
            borderWidth: 1,
          },
          {
            label: "Clicks (Range 2)",
            data: clicksDataRange2,
            backgroundColor: "rgba(153, 102, 255, 0.5)",
            borderColor: "rgba(153, 102, 255, 1)",
            borderWidth: 1,
          },
        ],
      },
      options: {
        responsive: true,
        scales: {
          y: {
            beginAtZero: true,
          },
        },
        plugins: {
          legend: {
            position: "top",
          },
        },
      },
    });
  }
});
