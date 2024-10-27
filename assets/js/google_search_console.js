jQuery(document).ready(function ($) {
  // Google Sign-In Flow

  // Show the Compare Analytics button if the Google Auth token is available
  $("#gsc_google_signin_button").on("click", function () {
    window.addEventListener(
      "message",
      function (event) {
        if (event.data.type === "googleAuthSuccess") {
          const accessToken = event.data.accessToken;
          if (accessToken) {
            $("#gsc_compare_analytics_container").show(); // Show button
          }
        }
      },
      false
    );
  });

  // Redirect to the Analytics Compare submenu
  $("#gsc_compare_analytics_button").on("click", function () {
    const siteUrl = $("#gsc_site_select").val();
    const accessToken = $("#gsc_google_access_token").val();

    if (siteUrl && accessToken) {
      // Redirect to the Analytics Compare submenu with siteUrl and accessToken as query parameters
      window.location.href = `admin.php?page=analytics-compare&siteUrl=${encodeURIComponent(
        siteUrl
      )}&accessToken=${encodeURIComponent(accessToken)}`;
    } else {
      alert("Please sign in and select a site before proceeding.");
    }
  });

  $("#gsc_google_signin_button").on("click", function () {
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
          $("#gsc_google_access_token").val(accessToken);

          // Automatically fetch and display sites in the dropdown
          fetch(
            `https://ai.1upmedia.com:443/google/sites?accessToken=${encodeURIComponent(
              accessToken
            )}`
          )
            .then((response) => response.json())
            .then((data) => {
              const siteSelect = $("#gsc_site_select");
              siteSelect.html('<option value="">Select a site</option>'); // Reset previous options
              data.forEach((site) => {
                siteSelect.append(
                  `<option value="${site.siteUrl}">${site.siteUrl} (${site.permissionLevel})</option>`
                );
              });

              // Show the dropdown and hide the sign-in button
              $("#gsc_list_sites_container").show();
              $("#gsc_google_signin_button").hide();

              // Show other buttons after site selection
              $("#gsc_site_select").on("change", function () {
                if ($("#gsc_site_select").val()) {
                  $("#gsc_buttons").show();
                } else {
                  $("#gsc_buttons").hide();
                }
              });
            })
            .catch((error) => {
              $("#gsc_analytics_table_container").text(
                "Error fetching sites: " + error
              );
            });
        }
      },
      false
    );
  });

  // Get Analytics Button Click
  $("#gsc_get_analytics_button").on("click", function () {
    const accessToken = $("#gsc_google_access_token").val();
    const siteUrl = $("#gsc_site_select").val();
    const startDate = $("#gsc_start_date").val();
    const endDate = $("#gsc_end_date").val();

    if (!accessToken || !siteUrl || !startDate || !endDate) {
      alert(
        "Please select a site, provide start and end dates, and sign in with Google first."
      );
      return;
    }

    fetch(
      `https://ai.1upmedia.com:443/google/sites/${encodeURIComponent(
        siteUrl
      )}/analytics`,
      {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          accessToken: accessToken,
          startDate: startDate,
          endDate: endDate,
        }),
      }
    )
      .then((response) => response.json())
      .then((data) => {
        let tableHtml =
          "<table><thead><tr><th>Query</th><th>Clicks</th><th>Impressions</th><th>CTR</th><th>Position</th><th>Ranking URL</th><th>Device</th><th>Country</th></tr></thead><tbody>";

        data.forEach((entry) => {
          const [query, rankingUrl, device, country] = entry.keys;

          tableHtml += `
                      <tr>
                          <td>${query}</td>
                          <td>${entry.clicks}</td>
                          <td>${entry.impressions}</td>
                          <td>${(entry.ctr * 100).toFixed(2)}%</td>
                          <td>${entry.position.toFixed(2)}</td>
                          <td><a href="${rankingUrl}" target="_blank">${rankingUrl}</a></td>
                          <td>${device}</td>
                          <td>${country.toUpperCase()}</td>
                      </tr>`;
        });

        tableHtml += "</tbody></table>";
        $("#gsc_analytics_table_container").html(tableHtml);
      })
      .catch((error) => {
        $("#gsc_analytics_table_container").text(
          "Error fetching analytics data: " + error
        );
      });
  });
});
