jQuery(document).ready(function ($) {
  console.log("jQuery is loaded and working!");

  var pollInterval;

  function showToast(message) {
    toastr.options = {
      closeButton: true,
      debug: false,
      newestOnTop: true,
      progressBar: false,
      positionClass: "toast-top-right",
      preventDuplicates: true,
      onclick: null,
      showDuration: "500",
      hideDuration: "1000",
      timeOut: "5000",
      extendedTimeOut: "10000",
      showEasing: "swing",
      hideEasing: "linear",
      showMethod: "fadeIn",
      hideMethod: "fadeOut",
    };
    toastr.success(message);
  }
  let unreadCount = 0;
  function pollProgress() {
    $.ajax({
      url: myAjax.ajaxurl, // Update with localized script object
      method: "POST",
      data: {
        action: "get_content_generation_progress",
        _ajax_nonce: myAjax.nonce, // Add nonce here
      },
      success: function (response) {
        if (response.success && Array.isArray(response.data.progress)) {
          let allSeen = true;

          const recentMessages = response.data.progress.slice(-3);

          recentMessages.forEach((item) => {
            if (response.data.is_last_error) {
              toastr.error("Last content was not successful");
              return;
            }
            if (!item.isSeen && response.data.is_generating) {
              showToast(item.message);
              item.isSeen = true; // Mark message as seen
              unreadCount++;
            }
          });

          // $.post(myAjax.ajaxurl, {
          //   action: "update_content_generation_progress",
          //   progress: response.data.progress,
          //   _ajax_nonce: myAjax.nonce, // Add nonce here
          // });

          if (response.data.is_generating && allSeen) {
            showToast("Content generation in progress, please wait...");
          }

          if (
            !response.data.is_generating &&
            allSeen &&
            response.data.last_timestamp
          ) {
            const lastScheduled = new Date(response.data.last_timestamp * 1000);
            const now = new Date();
            const diffMinutes = Math.round((now - lastScheduled) / 60000);
            if (diffMinutes <= 3) {
              if (response.data.is_last_error) {
                toastr.error("Last content was not successful");
                return;
              }
              showToast(`Last post scheduled ${diffMinutes} minutes ago.`);
            }
            clearInterval(pollInterval); // Stop polling
            pollInterval = setInterval(pollProgress, 10000);
          }
        }
      },
    });
  }

  // Start polling when the document is ready
  clearInterval(pollInterval);
  pollInterval = setInterval(pollProgress, 10000);

  if (sessionStorage.getItem("quickEditUpdated") === "true") {
    showToast("Post updated successfully");

    // Scroll to the edited post
    var postId = sessionStorage.getItem("editedPostId");
    if (postId) {
      var postRow = $("#post-" + postId);
      if (postRow.length) {
        $("html, body").animate(
          {
            scrollTop: postRow.offset().top,
          },
          1000
        );
      }
    }

    // Clear session storage items
    sessionStorage.removeItem("quickEditUpdated");
    sessionStorage.removeItem("editedPostId");
  }

  $(".inline-edit-save .button-primary.save").on("click", function () {
    // Add a flag to session storage to show the toast message after page refresh
    sessionStorage.setItem("quickEditUpdated", "true");

    // Get the post ID from the quick edit form
    var postId = $(this).closest("tr").attr("id").replace("edit-", "");
    sessionStorage.setItem("editedPostId", postId);

    // Delay the page refresh by 5 seconds
    setTimeout(function () {
      location.reload();
      $(this).closest("form").submit();
    }, 5000);

    // Trigger the form submission for quick edit
  });

  // $("#publish").on("click", function (e) {
  //   e.preventDefault();

  //   var postId = $("#post_ID").val();
  //   var nonce = $("#_wpnonce").val();
  //   var button = $(this);

  //   button.prop("disabled", true).val("Updating...");

  //   var categories = [];
  //   $('input[name="post_category[]"]:checked').each(function () {
  //     categories.push($(this).val());
  //   });

  //   var tags = $("#tax-input-post_tag").val();

  //   var featuredImageId = $("#_thumbnail_id").val();

  //   var editedPermalink = $("#editable-post-name-full").text();

  //   var postStatus = $("#post_status").val();
  //   var postVisibility = $('input[name="visibility"]:checked').val();
  //   var postPassword = $("#post_password").val();
  //   var postDate =
  //     $("#aa").val() +
  //     "-" +
  //     $("#mm").val() +
  //     "-" +
  //     $("#jj").val() +
  //     " " +
  //     $("#hh").val() +
  //     ":" +
  //     $("#mn").val();

  //   var formData = {
  //     action: "check_post_update",
  //     post_id: postId,
  //     _ajax_nonce: myAjax.nonce, // Nonce verification
  //     post_title: $("#title").val(),
  //     content: tinymce.get("content").getContent(),
  //     post_status: $("#post_status").val(),
  //     post_category: categories,
  //     tags_input: tags,
  //     featured_image_id: featuredImageId, // Include the featured image ID
  //     post_name: editedPermalink,
  //     post_visibility: postVisibility,
  //     post_password: postPassword,
  //     post_date: postDate,
  //   };

  //   $.ajax({
  //     url: myAjax.ajaxurl,
  //     method: "POST",
  //     data: formData,
  //     success: function (response) {
  //       if (response.success) {
  //         toastr.success(response.data.message);
  //         if (wp.data && wp.data.dispatch) {
  //           wp.data
  //             .dispatch("core/editor")
  //             .savePost()
  //             .then(() => {
  //               wp.data.dispatch("core/editor").initializeEditorState();

  //               // Manually set the editor as clean
  //               window.onbeforeunload = null;

  //               button.prop("disabled", false).val("Update");
  //             });
  //         } else {
  //           // Fallback if wp.data.dispatch is not available
  //           window.location.href = window.location.href;
  //         }
  //         button.prop("disabled", false).val("Update");
  //       } else {
  //         toastr.error(response.data.message);
  //         button.prop("disabled", false).val("Update");
  //       }
  //     },
  //     error: function (jqXHR, textStatus, errorThrown) {
  //       toastr.error("AJAX request failed: " + textStatus + ": " + errorThrown);
  //       button.prop("disabled", false).val("Update");
  //     },
  //   });
  // });

  $("#generate_content")
    .off("click")
    .on("click", function () {
      var postID = $("#post_ID").val();
      var persona = $("#author_persona").val();
      var contentType = $("#content_type").val();
      var apiType = $("#api_type").val();
      var customPrompt = $("#custom_prompt").val();
      var nonce = $("#generate_content_nonce_field").val();
      var authorID = $("#post_author").val();
      var postStatus = $("#approval_status").val();
      var scheduleTime = $("#scheduled_time").val();

      var $button = $(this);
      $button.prop("disabled", true).text("Loading...");

      $.ajax({
        url: myAjax.ajaxurl,
        type: "POST",
        dataType: "json",
        data: {
          action: "generate_content",
          post_id: postID,
          persona: persona,
          content_type: contentType,
          api_type: apiType,
          custom_prompt: customPrompt,
          _wpnonce: nonce, // Nonce verification
          post_author: authorID,
          post_status: postStatus,
          schedule_time: scheduleTime,
        },
        success: function (response) {
          if (response.success) {
            alert(`Post created  
                View here:

                ${
                  window.location.origin +
                  "/wp-admin/post.php?post=" +
                  response.data.postId +
                  "&action=edit"
                }
                `);
            var editUrl = "/wp-admin/post.php?post=" + postID + "&action=edit";
            window.location.href = editUrl;
            // if (response.data && response.data.content) {
            //   tinymce.activeEditor.setContent(response.data.content);
            //   $("#content").val(response.data.content);
            //   $("label[for='title']").text("");
            //   if (response.data.title) {
            //     $("#title").val(response.data.title).trigger("change");
            //   } else {
            //     $("#title").val(persona).trigger("change");
            //   }
            //   if (response.data.tags) {
            //     $(".tagchecklist").empty();
            //     const tagsArray = response.data.tags.split(",");
            //     tagsArray.forEach((tag) => {
            //       $("#new-tag-post_tag").val(tag.trim()).trigger("change");
            //       $("#new-tag-post_tag")
            //         .closest(".ajaxtag")
            //         .find(".tagadd")
            //         .click();
            //     });
            //   }

            //   if (response.data.categories) {
            //     response.data.categories.forEach((category) => {
            //       let $categoryCheckbox = $(
            //         "input[name='post_category[]'][value='" + category + "']"
            //       );
            //       if ($categoryCheckbox.length === 0) {
            //         let $categoryList = $("#categorychecklist");
            //         let newCategoryHTML = `<li id="category-${category}" class="wpseo-term-unchecked"><label class="selectit"><input value="${category}" type="checkbox" name="post_category[]" id="in-category-${category}"> ${category}</label></li>`;
            //         $categoryList.append(newCategoryHTML);
            //         $categoryCheckbox = $(
            //           "input[name='post_category[]'][value='" + category + "']"
            //         );
            //       }
            //       $categoryCheckbox.prop("checked", true);
            //     });
            //   }

            //   if (response.data.image_url) {
            //     var base64image = response.data.image_url.data;

            //     wp.media.featuredImage.frame().open();

            //     wp.media.featuredImage.frame().on("open", function () {
            //       var attachment = wp.media.featuredImage.get();

            //       if (!attachment) {
            //         attachment = wp.media.attachment(
            //           "data:image/png;base64," + base64image
            //         );
            //         wp.media.featuredImage.get().set(attachment);
            //       }
            //     });
            //   }

            //   if (response.data.image_alt) {
            //     $("#image_description")
            //       .val(response.data.image_alt)
            //       .trigger("change");
            //   }
            // }
          } else {
            if (response.data.type === "QuotaExceeded") {
              toastr.error(
                "Insufficient credits. Redirecting to purchase page..."
              );
              setTimeout(() => {
                window.location.href =
                  "https://1upmedia.com/product/dam-access/";
              }, 5000);
            } else if (response.data && response.data.message) {
              alert("Error: " + response.data.message);
            } else {
              alert("Unknown error occurred");
            }
          }
        },
        error: function (jqXHR, textStatus, errorThrown) {
          toastr.error("AJAX request failed: " + errorThrown);
        },
        complete: function () {
          $button.prop("disabled", false).text("Generate Content");
        },
      });
    });

  $(".nav-tab").on("click", function (e) {
    e.preventDefault();
    $(".nav-tab").removeClass("nav-tab-active");
    $(this).addClass("nav-tab-active");

    $(".tab-content").hide();
    $($(this).attr("href")).show();
  });
  $("#find_related_posts").on("click", function (e) {
    e.preventDefault();
    $("#related-posts-modal .content").html(
      '<div class="spinner is-active"></div>'
    ); // Show spinner
    $("#related-posts-modal").show();
    fetchRelatedPosts();
  });

  // Close the modal
  $(document).on("click", ".close-button", function (e) {
    e.preventDefault();
    $("#related-posts-modal").hide();
    return false; // Prevent any default action
  });
  // Fetch related posts via AJAX
  function fetchRelatedPosts() {
    var postId = $("#post_ID").val();
    var nonce = $("#_wpnonce").val();

    $.ajax({
      url: myAjax.ajaxurl,
      method: "POST",
      data: {
        action: "find_related_posts",
        post_id: postId,
        _wpnonce: myAjax.nonce, // Nonce verification
      },
      success: function (response) {
        if (response.success) {
          $("#related-posts-modal .content").html(response.data.html);
        } else {
          $("#related-posts-modal .content").html(
            "<p>" + response.data.message + "</p>"
          );
        }
      },
      error: function (jqXHR, textStatus, errorThrown) {
        $("#related-posts-modal .content").html(
          "<p>Failed to fetch related posts: " +
            textStatus +
            " - " +
            errorThrown +
            "</p>"
        );
      },
    });
  }

  // Add related post to the current post
  $(document).on("click", ".add-to-reference", function (e) {
    e.preventDefault();
    var link = $(this).data("link");
    var title = $(this).data("title");
    var postContent = '<p><a href="' + link + '">' + title + "</a></p>";

    // Append the related post link to the TinyMCE editor
    if (typeof tinymce !== "undefined") {
      var editor = tinymce.get("content");
      if (editor) {
        var content = editor.getContent();
        if (content.indexOf("Related Posts:") === -1) {
          content += "<h2>Related Posts:</h2>" + postContent;
        } else {
          content = content.replace(
            /(Related Posts:.*?<\/h2>)/s,
            "$1" + postContent
          );
        }
        editor.setContent(content);
      }
    } else {
      // Fallback for plain text editor
      var textarea = $("#content");
      var content = textarea.val();
      if (content.indexOf("Related Posts:") === -1) {
        content += "<h2>Related Posts:</h2>" + postContent;
      } else {
        content = content.replace(
          /(Related Posts:.*?<\/h2>)/s,
          "$1" + postContent
        );
      }
      textarea.val(content);
    }
  });

  $(".find-journey").on("click", function () {
    var button = $(this);
    var postId = button.data("post-id");
    var statusSpan = $("#journey-status-" + postId);

    statusSpan.text("Fetching...");

    $.ajax({
      url: ajaxurl, // WordPress AJAX URL
      type: "POST",
      data: {
        action: "find_buyers_journey",
        post_id: postId,
        _ajax_nonce: myAjax.nonce,
      },
      success: function (response) {
        if (response.success) {
          statusSpan.text("Stage: " + response.data.stage);
          button.remove(); // Remove the button after the stage is found
        } else {
          statusSpan.text("Error: " + response.data.message);
        }
      },
      error: function () {
        statusSpan.text("Error fetching buyer's journey.");
      },
    });
  });
});

// admin.js

document
  .getElementById("workflow_mode")
  ?.addEventListener("change", function () {
    var selectedValue = this.value;
    if (selectedValue === "automated") {
      window.location.href = myAjax.contentCalendarUrl;
    }
  });

document
  .getElementById("viewpostscalendar")
  ?.addEventListener("click", function () {
    window.location.href = myAjax.contentCalendarUrl;
  });
document.addEventListener("DOMContentLoaded", function () {
  // LinkedIn authentication logic
  if (myAjax.linkedinAccessToken) {
    document.getElementById("logout_linkedin").style.display = "block";
  } else {
    document.getElementById("authorize_linkedin").style.display = "block";
  }

  document
    .getElementById("authorize_linkedin")
    .addEventListener("click", function (e) {
      e.preventDefault();
      const authWindow = window.open(
        "https://ai.1upmedia.com:443/linkedin/auth",
        "LinkedIn Auth",
        "width=600,height=400"
      );

      window.addEventListener(
        "message",
        function (event) {
          if (event.data.type === "linkedinAuthSuccess") {
            const accessToken = event.data.accessToken;
            var xhr = new XMLHttpRequest();
            xhr.open("POST", myAjax.ajaxurl, true);
            xhr.setRequestHeader(
              "Content-Type",
              "application/x-www-form-urlencoded"
            );
            xhr.onload = function () {
              document.getElementById("authorize_linkedin").style.display =
                "none";
              document.getElementById("logout_linkedin").style.display =
                "block";
            };
            xhr.send(
              "action=save_linkedin_access_token&access_token=" +
                encodeURIComponent(accessToken) +
                "&nonce=" +
                myAjax.nonce
            );
          }
        },
        false
      );
    });

  document
    .getElementById("logout_linkedin")
    .addEventListener("click", function (e) {
      e.preventDefault();
      var xhr = new XMLHttpRequest();
      xhr.open("POST", myAjax.ajaxurl, true);
      xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
      xhr.onload = function () {
        document.getElementById("logout_linkedin").style.display = "none";
        document.getElementById("authorize_linkedin").style.display = "block";
      };
      xhr.send("action=remove_linkedin_access_token&nonce=" + myAjax.nonce);
    });

  // Facebook authentication logic
  if (myAjax.facebookAccessToken) {
    document.getElementById("logout_facebook").style.display = "block";
    document.getElementById("facebook_page_selection").style.display = "block";
    loadFacebookPages(myAjax.facebookAccessToken);
  } else {
    document.getElementById("authorize_facebook").style.display = "block";
  }

  document
    .getElementById("authorize_facebook")
    .addEventListener("click", function (e) {
      e.preventDefault();
      const authWindow = window.open(
        "https://ai.1upmedia.com:443/facebook/auth",
        "Facebook Auth",
        "width=600,height=400"
      );

      window.addEventListener(
        "message",
        function (event) {
          if (event.data.type === "facebookAuthSuccess") {
            const accessToken = event.data.accessToken;
            var xhr = new XMLHttpRequest();
            xhr.open("POST", myAjax.ajaxurl, true);
            xhr.setRequestHeader(
              "Content-Type",
              "application/x-www-form-urlencoded"
            );
            xhr.onload = function () {
              document.getElementById("authorize_facebook").style.display =
                "none";
              document.getElementById("logout_facebook").style.display =
                "block";
              document.getElementById("facebook_page_selection").style.display =
                "block";
              loadFacebookPages(accessToken);
            };
            xhr.send(
              "action=save_facebook_access_token&access_token=" +
                encodeURIComponent(accessToken) +
                "&nonce=" +
                myAjax.nonce
            );
          }
        },
        false
      );
    });

  document
    .getElementById("logout_facebook")
    .addEventListener("click", function (e) {
      e.preventDefault();
      var xhr = new XMLHttpRequest();
      xhr.open("POST", myAjax.ajaxurl, true);
      xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
      xhr.onload = function () {
        document.getElementById("logout_facebook").style.display = "none";
        document.getElementById("authorize_facebook").style.display = "block";
        document.getElementById("facebook_page_selection").style.display =
          "none";
      };
      xhr.send("action=remove_facebook_access_token&nonce=" + myAjax.nonce);
    });

  // Function to load Facebook pages
  function loadFacebookPages(accessToken) {
    var xhr = new XMLHttpRequest();
    xhr.open("POST", myAjax.ajaxurl, true);
    xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
    xhr.onload = function () {
      var data = JSON.parse(xhr.responseText);
      if (data.success) {
        var facebookPageSelect = document.getElementById("facebook_page_id");
        facebookPageSelect.innerHTML = "";
        let firstPageId = null;

        data.data.forEach(function (page, index) {
          var option = document.createElement("option");
          option.value = page.pageId;
          option.text = page.pageName;
          if (page.pageId == myAjax.facebookPageId) {
            option.selected = true;
          }
          facebookPageSelect.add(option);
          if (index === 0) {
            firstPageId = page.pageId;
          }
        });

        if (!myAjax.facebookPageId && firstPageId) {
          facebookPageSelect.value = firstPageId;
          updateSelectedFacebookPage(firstPageId);
        }

        document.getElementById("facebook_page_selection").style.display =
          "block";
      } else {
        console.error("Failed to load Facebook pages:", data.data);
      }
    };
    xhr.send(
      "action=load_facebook_pages&access_token=" +
        encodeURIComponent(accessToken) +
        "&nonce=" +
        myAjax.nonce
    );

    // Update backend when user changes page selection
    document
      .getElementById("facebook_page_id")
      .addEventListener("change", function () {
        const selectedPageId = this.value;
        updateSelectedFacebookPage(selectedPageId);
      });
  }

  // Function to update selected Facebook page
  function updateSelectedFacebookPage(selectedPageId) {
    var xhr = new XMLHttpRequest();
    xhr.open("POST", myAjax.ajaxurl, true);
    xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
    xhr.onload = function () {
      console.log("Facebook Page ID updated successfully:", xhr.responseText);
    };
    xhr.send(
      "action=update_facebook_page_id&facebook_page_id=" +
        encodeURIComponent(selectedPageId) +
        "&nonce=" +
        myAjax.nonce
    );
  }
  // });

  // document
  //   .getElementById("google_signin_button")
  //   .addEventListener("click", function () {
  //     // Open Google OAuth window
  //     const authWindow = window.open(
  //       "https://ai.1upmedia.com:443/google/auth", // Calls your Node.js /auth route
  //       "Google Auth",
  //       "width=600,height=400"
  //     );

  //     // Listen for message from the popup
  //     window.addEventListener(
  //       "message",
  //       function (event) {
  //         if (event.data.type === "googleAuthSuccess") {
  //           const accessToken = event.data.accessToken;

  //           // Fetch the sites associated with the Google account from your Node.js backend
  //           fetch(
  //             `https://ai.1upmedia.com:443/google/sites?accessToken=${encodeURIComponent(
  //               accessToken
  //             )}`
  //           )
  //             .then((response) => response.json())
  //             .then((data) => {
  //               const sitesContainer =
  //                 document.getElementById("google_sites_list");
  //               sitesContainer.innerHTML = ""; // Clear previous entries
  //               if (data.length === 0) {
  //                 sitesContainer.innerHTML = "<p>OOPS, no site attached.</p>";
  //               } else {
  //                 data.forEach((site) => {
  //                   const checkbox = document.createElement("input");
  //                   checkbox.type = "checkbox";
  //                   checkbox.name = "google_sites[]";
  //                   checkbox.value = site.siteUrl;
  //                   sitesContainer.appendChild(checkbox);
  //                   sitesContainer.appendChild(
  //                     document.createTextNode(site.siteUrl)
  //                   );
  //                   sitesContainer.appendChild(document.createElement("br"));
  //                 });
  //               }
  //               document.getElementById("google_sites_container").style.display =
  //                 "block"; // Show the sites list
  //             })
  //             .catch((error) => {
  //               console.error("Error fetching Google sites:", error);
  //             });

  //           // Store the access token in a hidden input field to submit it with the form
  //           const accessTokenInput = document.createElement("input");
  //           accessTokenInput.type = "hidden";
  //           accessTokenInput.name = "google_access_token";
  //           accessTokenInput.value = accessToken;
  //           document.querySelector("form").appendChild(accessTokenInput);
  //         }
  //       },
  //       false
  //     );
});

document.addEventListener("DOMContentLoaded", function () {
  const tabs = document.querySelectorAll(".nav-tab-user");
  const tabContents = document.querySelectorAll(".user-tab-content");
  const updateButtons = document.querySelectorAll(".update-user");

  // Tab navigation
  tabs.forEach(function (tab) {
    tab.addEventListener("click", function (e) {
      e.preventDefault();

      tabs.forEach(function (t) {
        t.classList.remove("nav-tab-user-active");
      });
      tabContents.forEach(function (content) {
        content.style.display = "none";
      });

      this.classList.add("nav-tab-user-active");
      const target = this.getAttribute("href");
      document.querySelector(target).style.display = "block";
    });
  });

  // Update User button click
  updateButtons.forEach(function (button) {
    button.addEventListener("click", function () {
      // Populate the form with existing user data
      const userId = this.dataset.userId;
      const username = this.dataset.username;
      const email = this.dataset.email;
      const role = this.dataset.role;
      const persona = this.dataset.persona;
      const industry = this.dataset.industry;
      const location = this.dataset.location;
      const url = this.dataset.url;
      const businessDetails = this.dataset.business;
      const domainAuthority = this.dataset.domainauthority;
      const contentStrategy = this.dataset.strategy;
      const language = this.dataset.language || "English";

      document.getElementById("update_user_id").value = userId;
      document.getElementById("update_username").value = username;
      document.getElementById("update_email").value = email;
      document.getElementById("update_role").value = role;
      document.getElementById("update_persona").value = persona;
      document.getElementById("update_industry").value = industry;
      document.getElementById("update_location").value = location;
      document.getElementById("update_url").value = url;
      document.getElementById("update_business_details").value =
        businessDetails;
      document.getElementById("update_domain_authority").value =
        domainAuthority;
      document.getElementById("update_content_strategy").value =
        contentStrategy;
      document.getElementById("update_language").value = language;

      // Show the update form
      document.getElementById("update-user-form").style.display = "flex";

      // Switch to the Update User tab
      document.querySelector('a[href="#update-user-tab"]').click();
    });
  });
});

jQuery(document).ready(function ($) {
  var postStatus = document.getElementById("post_status");
  var adminEmailRow = document.getElementById("admin_email_row");

  var postStatusAuto = document.getElementById("post_status_auto");
  var adminEmailRowAuto = document.getElementById("admin_email_row_auto");

  var postStatusSuper = document.getElementById("post_status_super");
  var adminEmailRowSuper = document.getElementById("admin_email_row_super");

  // Function to show or hide the admin email input based on the selected post status
  function toggleAdminEmail() {
    // Handle for post_status
    if (postStatus && postStatus.value === "pending") {
      adminEmailRow.style.display = "table-row"; // Show the row if pending
    } else {
      adminEmailRow.style.display = "none"; // Hide the row otherwise
    }

    // Handle for post_status_auto
    if (postStatusAuto && postStatusAuto.value === "pending") {
      adminEmailRowAuto.style.display = "table-row"; // Show the row if pending
    } else {
      adminEmailRowAuto.style.display = "none"; // Hide the row otherwise
    }

    // Handle for post_status_super
    if (postStatusSuper && postStatusSuper.value === "pending") {
      adminEmailRowSuper.style.display = "table-row"; // Show the row if pending
    } else {
      adminEmailRowSuper.style.display = "none"; // Hide the row otherwise
    }
  }

  // Add event listeners to detect changes in post status
  postStatus.addEventListener("change", toggleAdminEmail);
  postStatusAuto.addEventListener("change", toggleAdminEmail);
  postStatusSuper.addEventListener("change", toggleAdminEmail);
  var linkedinAccessToken = myAjax.linkedinAccessToken;
  var facebookAccessToken = myAjax.facebookAccessToken;
  var facebookPageId = myAjax.facebookPageId;
  var twitterApiSecret = myAjax.twitterApiSecret;
  var twitterApiKey = myAjax.twitterApiKey;
  var twitterAccessToken = myAjax.twitterAccessToken;
  var twitterAccessTokenSecret = myAjax.twitterAccessTokenSecret;

  if (linkedinAccessToken) {
    // Show the Logout button if access token is available
    $("#logout_linkedin").show();
  } else {
    // Show the Authorize LinkedIn button if access token is not available
    $("#authorize_linkedin").show();
  }
  $("#authorize_linkedin").on("click", function (e) {
    e.preventDefault();

    const authWindow = window.open(
      "https://ai.1upmedia.com:443/linkedin/auth",
      "LinkedIn Auth",
      "width=600,height=400"
    );

    window.addEventListener(
      "message",
      function (event) {
        if (event.data.type === "linkedinAuthSuccess") {
          const accessToken = event.data.accessToken;

          // Optionally, you can save this access token via AJAX for use later
          $.post(
            ajaxurl,
            {
              action: "save_linkedin_access_token",
              access_token: accessToken,
              nonce: myAjax.nonce,
            },
            function (response) {
              // console.log('Access token saved:', response);
              // Hide the authorize button and show the logout button
              $("#authorize_linkedin").hide();
              $("#logout_linkedin").show();
            }
          );
        }
      },
      false
    );
  });

  $("#logout_linkedin").on("click", function (e) {
    e.preventDefault();

    // Remove the access token via AJAX
    $.post(
      ajaxurl,
      {
        action: "remove_linkedin_access_token",
        nonce: myAjax.nonce,
      },
      function (response) {
        //console.log('Access token removed:', response);

        // Hide the logout button and show the authorize button
        $("#logout_linkedin").hide();
        $("#authorize_linkedin").show();
      }
    );
  });

  if (facebookAccessToken) {
    // Show the Logout button if access token is available
    $("#logout_facebook").show();
    $("#facebook_page_selection").show();
    loadFacebookPages(facebookAccessToken);
  } else {
    // Show the Authorize Facebook button if access token is not available
    $("#authorize_facebook").show();
  }

  $("#authorize_facebook").on("click", function (e) {
    e.preventDefault();

    const authWindow = window.open(
      "https://ai.1upmedia.com:443/facebook/auth",
      "Facebook Auth",
      "width=600,height=400"
    );

    window.addEventListener(
      "message",
      function (event) {
        if (event.data.type === "facebookAuthSuccess") {
          const accessToken = event.data.accessToken;

          // Save the access token via AJAX
          $.post(
            ajaxurl,
            {
              action: "save_facebook_access_token",
              access_token: accessToken,
              nonce: myAjax.nonce,
            },
            function (response) {
              //console.log('Access token saved:', response);
              $("#authorize_facebook").hide();
              $("#logout_facebook").show();
              $("#facebook_page_selection").show();
              loadFacebookPages(accessToken);
            }
          );
        }
      },
      false
    );
  });

  $("#logout_facebook").on("click", function (e) {
    e.preventDefault();

    // Remove the access token via AJAX
    $.post(
      ajaxurl,
      {
        action: "remove_facebook_access_token",
      },
      function (response) {
        //console.log('Access token removed:', response);
        $("#logout_facebook").hide();
        $("#authorize_facebook").show();
        $("#facebook_page_selection").hide();
      }
    );
  });

  function loadFacebookPages(accessToken) {
    jQuery.ajax({
      url: ajaxurl, // This is a global variable in WordPress for AJAX URL
      type: "POST",
      data: {
        action: "load_facebook_pages",
        access_token: accessToken,
        nonce: myAjax.nonce,
      },
      success: function (data) {
        if (data.success) {
          $("#facebook_page_id").empty();
          let firstPageId = null;

          data.data.forEach(function (page, index) {
            const selected = page.pageId == facebookPageId ? "selected" : "";
            $("#facebook_page_id").append(
              `<option value="${page.pageId}" ${selected}>${page.pageName}</option>`
            );

            if (index === 0) {
              firstPageId = page.pageId;
            }
          });

          // Set default to the first page if facebookPageId is not set
          if (!facebookPageId && firstPageId) {
            $("#facebook_page_id").val(firstPageId);
            updateSelectedFacebookPage(firstPageId); // Update the backend with the first page as default
          }

          // Show the selection div
          $("#facebook_page_selection").show();
        } else {
          console.error("Failed to load Facebook pages:", data.data);
        }
      },
      error: function (error) {
        console.error("Error loading Facebook pages:", error);
      },
    });

    // Update the backend when user changes the selection
    $("#facebook_page_id").on("change", function () {
      const selectedPageId = $(this).val();
      updateSelectedFacebookPage(selectedPageId);
    });
  }

  function updateSelectedFacebookPage(selectedPageId) {
    jQuery.ajax({
      url: ajaxurl,
      type: "POST",
      data: {
        nonce: myAjax.nonce,
        action: "update_facebook_page_id",
        facebook_page_id: selectedPageId,
      },
      success: function (response) {
        //console.log('Facebook Page ID updated successfully:', response);
      },
      error: function (error) {
        console.error("Error updating Facebook Page ID:", error);
      },
    });
  }
  var pluginSettingsUrl = myAjax.pluginSettingsUrl;

  if (!linkedinAccessToken) {
    document.getElementById("post_to_linkedin").disabled = true;
    var linkedinLabel = document.querySelector("label[for='post_to_linkedin']");
    linkedinLabel.innerHTML += ` <span style="color: red;">(Authorize LinkedIn in the settings:  <a href="${pluginSettingsUrl}" target="_blank">Navigate to settings</a>)</span>`;
    document.getElementById("post_to_linkedin_auto").disabled = true;
    var linkedinLabelAuto = document.querySelector(
      "label[for='post_to_linkedin_auto']"
    );
    linkedinLabelAuto.innerHTML += ` <span style="color: red;">(Authorize LinkedIn in the settings:  <a href="${pluginSettingsUrl}" target="_blank">Navigate to settings</a>)</span>`;
    document.getElementById("post_to_linkedin_super").disabled = true;
    var linkedinLabelSuper = document.querySelector(
      "label[for='post_to_linkedin_super']"
    );
    linkedinLabelSuper.innerHTML += ` <span style="color: red;">(Authorize LinkedIn in the settings:  <a href="${pluginSettingsUrl}" target="_blank">Navigate to settings</a>)</span>`;
  }

  // Facebook check
  // Facebook check
  if (!facebookAccessToken || !facebookPageId) {
    document.getElementById("post_to_facebook").disabled = true;
    var facebookLabel = document.querySelector("label[for='post_to_facebook']");
    facebookLabel.innerHTML += ` <span style="color: red;">(Authorize Facebook in the settings: <a href="${pluginSettingsUrl}" target="_blank">Navigate to settings</a>)</span>`;
    document.getElementById("post_to_facebook_auto").disabled = true;
    var facebookLabelAuto = document.querySelector(
      "label[for='post_to_facebook_auto']"
    );
    facebookLabelAuto.innerHTML += ` <span style="color: red;">(Authorize Facebook in the settings: <a href="${pluginSettingsUrl}" target="_blank">Navigate to settings</a>)</span>`;
    document.getElementById("post_to_facebook_super").disabled = true;
    var facebookLabelSuper = document.querySelector(
      "label[for='post_to_facebook_super']"
    );
    facebookLabelSuper.innerHTML += ` <span style="color: red;">(Authorize Facebook in the settings: <a href="${pluginSettingsUrl}" target="_blank">Navigate to settings</a>)</span>`;
  }

  // Twitter check
  if (
    !twitterApiKey ||
    !twitterApiSecret ||
    !twitterAccessToken ||
    !twitterAccessTokenSecret
  ) {
    document.getElementById("post_to_twitter").disabled = true;
    var twitterLabel = document.querySelector("label[for='post_to_twitter']");
    twitterLabel.innerHTML += ` <span style="color: red;">(Authorize Twitter in the settings: <a href="${pluginSettingsUrl}" target="_blank">Navigate to settings</a>)</span>`;
    document.getElementById("post_to_twitter_auto").disabled = true;
    var twitterLabelAuto = document.querySelector(
      "label[for='post_to_twitter_auto']"
    );
    twitterLabelAuto.innerHTML += ` <span style="color: red;">(Authorize Twitter in the settings: <a href="${pluginSettingsUrl}" target="_blank">Navigate to settings</a>)</span>`;
    document.getElementById("post_to_twitter_super").disabled = true;
    var twitterLabelSuper = document.querySelector(
      "label[for='post_to_twitter_super']"
    );
    twitterLabelSuper.innerHTML += ` <span style="color: red;">(Authorize Twitter in the settings: <a href="${pluginSettingsUrl}" target="_blank">Navigate to settings</a>)</span>`;
  }

  const recommendations = {
    "Content Clusters and Pillar Pages": "Recommended: 15-20 articles",
    "Topic Hubs and Resource Pages": "Recommended: 25-30 articles",
    "Thematic Groups and Hub Pages": "Recommended: 20-25 articles",
    "Cornerstone Content": "Recommended: 10-15 articles",
    "Content Series": "Recommended: 12-16 articles",
    "Ongoing Content Campaigns": "Recommended: 30-40 articles",
    "Serialized Content": "Recommended: 15-20 articles",
    "Evergreen Content Creation": "Recommended: 20-25 articles",
    "Long-Lasting Content": "Recommended: 20-25 articles",
    "Seasonal Updates": "Recommended: 10-15 articles",
    "Thought Leadership": "Recommended: 15-20 articles",
    "Industry Insights": "Recommended: 15-20 articles",
    "Expert Opinions": "Recommended: 10-15 articles",
    "Keyword Clusters": "Recommended: 20-30 articles",
    "Semantic Keywords": "Recommended: 20-25 articles",
    "Long-Tail Keywords": "Recommended: 20-25 articles",
    "Full Journey": "Recommended: 20-30 articles",
    Awareness: "Recommended: 7-12 articles",
    Consideration: "Recommended: 7-12 articles",
    Decision: "Recommended: 7-12 articles",
  };

  const recommendations_for_buyers_journey = {
    "Full Journey": "Recommended: 20-30 articles",
    Awareness: "Recommended: 7-12 articles",
    Consideration: "Recommended: 7-12 articles",
    Decision: "Recommended: 7-12 articles",
  };

  const goalWeightage = {
    "Content Clusters and Pillar Pages": {
      "Generate Leads": 25,
      "Enhance SEO Performance": 20,
      "Establish Authority and Trust": 10,
      "Increase Brand Awareness": 10,
      "Foster Customer Engagement": 10,
      "Improve Customer Education": 15,
      "Boost Conversion Rates": 5,
      "Nurture Leads": 5,
    },
    "Topic Hubs and Resource Pages": {
      "Generate Leads": 20,
      "Enhance SEO Performance": 20,
      "Establish Authority and Trust": 10,
      "Increase Brand Awareness": 10,
      "Foster Customer Engagement": 10,
      "Improve Customer Education": 20,
      "Boost Conversion Rates": 5,
      "Nurture Leads": 5,
    },
    "Thematic Groups and Hub Pages": {
      "Generate Leads": 20,
      "Enhance SEO Performance": 10,
      "Establish Authority and Trust": 10,
      "Increase Brand Awareness": 30,
      "Foster Customer Engagement": 10,
      "Improve Customer Education": 15,
      "Boost Conversion Rates": 3,
      "Nurture Leads": 2,
    },
    "Cornerstone Content": {
      "Generate Leads": 20,
      "Enhance SEO Performance": 10,
      "Establish Authority and Trust": 35,
      "Increase Brand Awareness": 20,
      "Foster Customer Engagement": 10,
      "Improve Customer Education": 5,
      "Boost Conversion Rates": 5,
      "Nurture Leads": 5,
    },
    "Content Series": {
      "Generate Leads": 20,
      "Enhance SEO Performance": 10,
      "Establish Authority and Trust": 20,
      "Increase Brand Awareness": 30,
      "Foster Customer Engagement": 10,
      "Improve Customer Education": 5,
      "Boost Conversion Rates": 5,
      "Nurture Leads": 5,
    },
    "Ongoing Content Campaigns": {
      "Generate Leads": 30,
      "Enhance SEO Performance": 10,
      "Establish Authority and Trust": 15,
      "Increase Brand Awareness": 20,
      "Foster Customer Engagement": 5,
      "Improve Customer Education": 5,
      "Boost Conversion Rates": 10,
      "Nurture Leads": 5,
    },
    "Serialized Content": {
      "Generate Leads": 20,
      "Enhance SEO Performance": 10,
      "Establish Authority and Trust": 15,
      "Increase Brand Awareness": 25,
      "Foster Customer Engagement": 10,
      "Improve Customer Education": 10,
      "Boost Conversion Rates": 5,
      "Nurture Leads": 5,
    },
    "Evergreen Content Creation": {
      "Generate Leads": 10,
      "Enhance SEO Performance": 20,
      "Establish Authority and Trust": 30,
      "Increase Brand Awareness": 15,
      "Foster Customer Engagement": 10,
      "Improve Customer Education": 10,
      "Boost Conversion Rates": 3,
      "Nurture Leads": 2,
    },
    "Long-Lasting Content": {
      "Generate Leads": 10,
      "Enhance SEO Performance": 15,
      "Establish Authority and Trust": 30,
      "Increase Brand Awareness": 20,
      "Foster Customer Engagement": 10,
      "Improve Customer Education": 10,
      "Boost Conversion Rates": 3,
      "Nurture Leads": 2,
    },
    "Seasonal Updates": {
      "Generate Leads": 30,
      "Enhance SEO Performance": 15,
      "Establish Authority and Trust": 10,
      "Increase Brand Awareness": 20,
      "Foster Customer Engagement": 10,
      "Improve Customer Education": 5,
      "Boost Conversion Rates": 5,
      "Nurture Leads": 5,
    },
    "Thought Leadership": {
      "Generate Leads": 10,
      "Enhance SEO Performance": 0,
      "Establish Authority and Trust": 40,
      "Increase Brand Awareness": 20,
      "Foster Customer Engagement": 10,
      "Improve Customer Education": 10,
      "Boost Conversion Rates": 5,
      "Nurture Leads": 5,
    },
    "Industry Insights": {
      "Generate Leads": 10,
      "Enhance SEO Performance": 0,
      "Establish Authority and Trust": 35,
      "Increase Brand Awareness": 20,
      "Foster Customer Engagement": 10,
      "Improve Customer Education": 10,
      "Boost Conversion Rates": 5,
      "Nurture Leads": 10,
    },
    "Expert Opinions": {
      "Generate Leads": 10,
      "Enhance SEO Performance": 0,
      "Establish Authority and Trust": 40,
      "Increase Brand Awareness": 20,
      "Foster Customer Engagement": 10,
      "Improve Customer Education": 10,
      "Boost Conversion Rates": 5,
      "Nurture Leads": 5,
    },
    "Keyword Clusters": {
      "Generate Leads": 30,
      "Enhance SEO Performance": 20,
      "Establish Authority and Trust": 10,
      "Increase Brand Awareness": 10,
      "Foster Customer Engagement": 10,
      "Improve Customer Education": 10,
      "Boost Conversion Rates": 5,
      "Nurture Leads": 5,
    },
    "Semantic Keywords": {
      "Generate Leads": 30,
      "Enhance SEO Performance": 20,
      "Establish Authority and Trust": 10,
      "Increase Brand Awareness": 10,
      "Foster Customer Engagement": 10,
      "Improve Customer Education": 10,
      "Boost Conversion Rates": 5,
      "Nurture Leads": 5,
    },
    "Long-Tail Keywords": {
      "Generate Leads": 30,
      "Enhance SEO Performance": 20,
      "Establish Authority and Trust": 10,
      "Increase Brand Awareness": 10,
      "Foster Customer Engagement": 10,
      "Improve Customer Education": 10,
      "Boost Conversion Rates": 5,
      "Nurture Leads": 5,
    },
    "Full Journey": {
      "Generate Leads": 15,
      "Enhance SEO Performance": 10,
      "Establish Authority and Trust": 10,
      "Increase Brand Awareness": 20,
      "Foster Customer Engagement": 10,
      "Improve Customer Education": 15,
      "Boost Conversion Rates": 10,
      "Nurture Leads": 10,
    },
    Awareness: {
      "Generate Leads": 10,
      "Enhance SEO Performance": 10,
      "Establish Authority and Trust": 20,
      "Increase Brand Awareness": 30,
      "Foster Customer Engagement": 10,
      "Improve Customer Education": 10,
      "Boost Conversion Rates": 5,
      "Nurture Leads": 5,
    },
    Consideration: {
      "Generate Leads": 20,
      "Enhance SEO Performance": 20,
      "Establish Authority and Trust": 20,
      "Increase Brand Awareness": 10,
      "Foster Customer Engagement": 10,
      "Improve Customer Education": 20,
      "Boost Conversion Rates": 5,
      "Nurture Leads": 5,
    },
    Decision: {
      "Generate Leads": 30,
      "Enhance SEO Performance": 10,
      "Establish Authority and Trust": 10,
      "Increase Brand Awareness": 10,
      "Foster Customer Engagement": 10,
      "Improve Customer Education": 10,
      "Boost Conversion Rates": 15,
      "Nurture Leads": 5,
    },
  };

  const searchIntentWeightage = {
    "Content Clusters and Pillar Pages": {
      Informational: 40,
      Navigational: 20,
      Commercial: 20,
      Transactional: 10,
      Local: 10,
    },
    "Topic Hubs and Resource Pages": {
      Informational: 50,
      Navigational: 20,
      Commercial: 20,
      Transactional: 5,
      Local: 5,
    },
    "Thematic Groups and Hub Pages": {
      Informational: 40,
      Navigational: 30,
      Commercial: 20,
      Transactional: 5,
      Local: 5,
    },
    "Cornerstone Content": {
      Informational: 50,
      Navigational: 20,
      Commercial: 20,
      Transactional: 5,
      Local: 5,
    },
    "Content Series": {
      Informational: 40,
      Navigational: 30,
      Commercial: 20,
      Transactional: 5,
      Local: 5,
    },
    "Ongoing Content Campaigns": {
      Informational: 40,
      Navigational: 20,
      Commercial: 20,
      Transactional: 10,
      Local: 10,
    },
    "Serialized Content": {
      Informational: 40,
      Navigational: 30,
      Commercial: 20,
      Transactional: 5,
      Local: 5,
    },
    "Evergreen Content Creation": {
      Informational: 50,
      Navigational: 20,
      Commercial: 20,
      Transactional: 5,
      Local: 5,
    },
    "Long-Lasting Content": {
      Informational: 50,
      Navigational: 20,
      Commercial: 20,
      Transactional: 5,
      Local: 5,
    },
    "Seasonal Updates": {
      Informational: 30,
      Navigational: 30,
      Commercial: 20,
      Transactional: 10,
      Local: 10,
    },
    "Thought Leadership": {
      Informational: 40,
      Navigational: 30,
      Commercial: 20,
      Transactional: 5,
      Local: 5,
    },
    "Industry Insights": {
      Informational: 50,
      Navigational: 20,
      Commercial: 20,
      Transactional: 5,
      Local: 5,
    },
    "Expert Opinions": {
      Informational: 40,
      Navigational: 30,
      Commercial: 20,
      Transactional: 5,
      Local: 5,
    },
    "Keyword Clusters": {
      Informational: 50,
      Navigational: 20,
      Commercial: 20,
      Transactional: 5,
      Local: 5,
    },
    "Semantic Keywords": {
      Informational: 50,
      Navigational: 20,
      Commercial: 20,
      Transactional: 5,
      Local: 5,
    },
    "Long-Tail Keywords": {
      Informational: 50,
      Navigational: 20,
      Commercial: 20,
      Transactional: 5,
      Local: 5,
    },
    "Full Journey": {
      Informational: 50,
      Commercial: 20,
      Navigational: 15,
      Transactional: 10,
      Local: 5,
    },
    Awareness: {
      Informational: 70,
      Navigational: 20,
      Commercial: 10,
    },
    Consideration: {
      Informational: 40,
      Commercial: 30,
      Navigational: 20,
      Transactional: 10,
    },
    Decision: {
      Transactional: 40,
      Commercial: 30,
      Navigational: 20,
      Local: 10,
    },
  };

  $('a[href="#notifications"]').on("click", function () {
    fetchNotifications();
  });

  function fetchNotifications() {
    $.ajax({
      url: ajaxurl,
      method: "POST",
      data: {
        action: "get_content_generation_progress",
        _ajax_nonce: myAjax.nonce,
      },
      success: function (response) {
        if (response.success && Array.isArray(response.data.progress)) {
          var notificationsList = $("#notifications-list");
          notificationsList.empty(); // Clear existing notifications

          response.data.progress.forEach(function (item) {
            notificationsList.prepend("<li>" + item.message + "</li>");
          });
        }
      },
      error: function (error) {
        console.error("Error fetching notifications:", error);
      },
    });
  }
  $("#content_strategy_full").on("change", function () {
    const selectedOption = $(this).val();
    if (recommendations[selectedOption]) {
      $("#recommendation_text").text(recommendations[selectedOption]);
      $("#recommendation_row").show();
      $("#use_recommended_row").show();
    } else {
      $("#recommendation_row").hide();
      $("#use_recommended_row").hide();
    }
    resetTemplateWeightage("full");
    resetGoalWeightage("full");
    resetSearchIntentWeightage("full");
  });

  $("#content_strategy_auto").on("change", function () {
    const selectedOption = $(this).val();
    if (recommendations[selectedOption]) {
      $("#recommendation_text_auto").text(recommendations[selectedOption]);
      $("#recommendation_row_auto").show();
      $("#use_recommended_row_auto").show();
    } else {
      $("#recommendation_row_auto").hide();
      $("#use_recommended_row_auto").hide();
    }
    resetTemplateWeightage("auto");
    resetGoalWeightage("auto");
    resetSearchIntentWeightage("auto");
  });

  $("#use_recommended_values").on("click", function () {
    const selectedOption = $("#content_strategy_full").val();
    if (recommendations[selectedOption]) {
      const recommendedPieces =
        recommendations[selectedOption].match(/\d+/g)[0];
      $("#number_of_pieces").val(recommendedPieces);

      $("#full_workflow_content_type").val("Random");
      $("#goal").val("Random");
      $("#search_intent").val("Random");
      $("#author").val("Random");
      $("#author_auto").val("Random");
      $("#author_super").val("Random");
      $("#template_style").val("Random");
      updateTemplateWeightage("full", selectedOption);
      updateGoalWeightage("full", selectedOption);
      updateSearchIntentWeightage("full", selectedOption);
    }
  });

  $("#use_recommended_values_auto").on("click", function () {
    const selectedOption = $("#content_strategy_auto").val();
    if (recommendations[selectedOption]) {
      const recommendedPieces =
        recommendations[selectedOption].match(/\d+/g)[0];
      $("#number_of_pieces_auto").val(recommendedPieces);
      updateTemplateWeightage("auto", selectedOption);
      updateGoalWeightage("auto", selectedOption);
      updateSearchIntentWeightage("auto", selectedOption);
    }
  });

  function updateGoalWeightage(mode, strategy) {
    const weightage = goalWeightage[strategy];
    if (weightage) {
      Object.keys(weightage).forEach((goal) => {
        $(`input[name='goal_weightage_${mode}[${goal}]']`).val(weightage[goal]);
        $(`.goal-checkbox-${mode}[value='${goal}']`).prop("checked", true);
      });
    }
  }

  function updateSearchIntentWeightage(mode, strategy) {
    const weightage = searchIntentWeightage[strategy];
    if (weightage) {
      Object.keys(weightage).forEach((intent) => {
        $(`input[name='search_intent_weightage_${mode}[${intent}]']`).val(
          weightage[intent]
        );
        $(`.search-intent-checkbox-${mode}[value='${intent}']`).prop(
          "checked",
          true
        );
      });
    }
  }

  function resetGoalWeightage(mode) {
    $(`.goal-weightage-input-${mode}`).val(1);
    $(`.goal-checkbox-${mode}`).prop("checked", false);
    $(`#select-all-goals-${mode}`).prop("checked", false);
    $(`#equal-weightage-goals-${mode}`).prop("checked", false);
  }

  function resetSearchIntentWeightage(mode) {
    $(`.search-intent-weightage-input-${mode}`).val(1);
    $(`.search-intent-checkbox-${mode}`).prop("checked", false);
    $(`#select-all-search-intents-${mode}`).prop("checked", false);
    $(`#equal-weightage-search-intents-${mode}`).prop("checked", false);
  }

  function updateWeightageVisibility() {
    if ($("#author_auto").val() === "Random") {
      $("#user-weightage-row").show();
    } else {
      $("#user-weightage-row").hide();
    }

    if ($("#author").val() === "Random") {
      $("#user-weightage-row-full").show();
    } else {
      $("#user-weightage-row-full").hide();
    }

    if ($("#author_super").val() === "Random") {
      $("#user-weightage-row-super").show();
    } else {
      $("#user-weightage-row-super").hide();
    }

    if ($("#template_style").val() === "Random") {
      $("#content-type-weightage-row-auto").show();
    } else {
      $("#content-type-weightage-row-auto").hide();
    }

    if ($("#full_workflow_content_type").val() === "Random") {
      $("#content-type-weightage-row-full").show();
    } else {
      $("#content-type-weightage-row-full").hide();
    }

    if ($("#goal").val() === "Random") {
      $("#goal-weightage-row").show();
    } else {
      $("#goal-weightage-row").hide();
    }
    if ($("#goal_auto").val() === "Random") {
      $("#goal-weightage-row_auto").show();
    } else {
      $("#goal-weightage-row_auto").hide();
    }
    if ($("#goal_super").val() === "Random") {
      $("#goal-weightage-row_super").show();
    } else {
      $("#goal-weightage-row_super").hide();
    }

    if ($("#search_intent").val() === "Random") {
      $("#search-intent-weightage-row").show();
    } else {
      $("#search-intent-weightage-row").hide();
    }

    if ($("#search_intent_auto").val() === "Random") {
      $("#search-intent-weightage-row-auto").show();
    } else {
      $("#search-intent-weightage-row-auto").hide();
    }

    if ($("#search_intent_super").val() === "Random") {
      $("#search-intent-weightage-row-super").show();
    } else {
      $("#search-intent-weightage-row-super").hide();
    }
  }

  $("#author_auto").on("change", function () {
    updateWeightageVisibility();
  });

  $("#author").on("change", function () {
    updateWeightageVisibility();
  });

  $("#author_super").on("change", function () {
    updateWeightageVisibility();
  });

  $("#template_style").on("change", function () {
    updateWeightageVisibility();
  });

  $("#full_workflow_content_type").on("change", function () {
    updateWeightageVisibility();
  });

  $("#goal_super").on("change", function () {
    updateWeightageVisibility();
  });
  $("#goal_auto").on("change", function () {
    updateWeightageVisibility();
  });
  $("#goal").on("change", function () {
    updateWeightageVisibility();
  });

  $("#search_intent_auto").on("change", function () {
    updateWeightageVisibility();
  });

  $("#search_intent_super").on("change", function () {
    updateWeightageVisibility();
  });

  $("#search_intent").on("change", function () {
    updateWeightageVisibility();
  });

  $("#select-all-users").on("change", function () {
    $(".user-checkbox").prop("checked", this.checked);
  });

  $("#equal-weightage-users").on("change", function () {
    if ($(this).is(":checked")) {
      $(".weightage-input").val(1);
    }
  });

  $(".user-checkbox").on("change", function () {
    if (!$(this).is(":checked")) {
      $(this).closest(".user-weightage-item").find(".weightage-input").val(0);
      $("#select-all-users").prop("checked", false);
    }
  });

  $("#select-all-users-full").on("change", function () {
    $(".user-checkbox-full").prop("checked", this.checked);
  });

  $("#equal-weightage-users-full").on("change", function () {
    if ($(this).is(":checked")) {
      $(".weightage-input").val(1);
    }
  });
  $(".user-checkbox-full").on("change", function () {
    if (!$(this).is(":checked")) {
      $(this)
        .closest(".user-weightage-item")
        .find(".weightage-input-full")
        .val(0);
      $("#select-all-users-full").prop("checked", false);
    }
  });

  $("#select-all-content-types-full").on("change", function () {
    $(".content-type-checkbox-full").prop("checked", this.checked);
  });

  $("#equal-weightage-content-types-full").on("change", function () {
    if ($(this).is(":checked")) {
      $(".weightage-input-full").val(1);
    }
  });

  $(".content-type-checkbox-full").on("change", function () {
    if (!$(this).is(":checked")) {
      $(this)
        .closest(".content-type-weightage-item")
        .find(".weightage-input-full")
        .val(0);
      $("#select-all-content-types-full").prop("checked", false);
    }
  });

  $("#select-all-users-super").on("change", function () {
    $(".user-checkbox-super").prop("checked", this.checked);
  });

  $("#equal-weightage-users-super").on("change", function () {
    if ($(this).is(":checked")) {
      $(".weightage-input-super").val(1);
    }
  });

  $(".user-checkbox-super").on("change", function () {
    if (!$(this).is(":checked")) {
      $(this)
        .closest(".user-weightage-item")
        .find(".weightage-input-super")
        .val(0);
      $("#select-all-users-super").prop("checked", false);
    }
  });

  $(".weightage-input-super").on("input", function () {
    $("#equal-weightage-users-super").prop("checked", false);
  });

  $(".goal-checkbox-full").on("change", function () {
    if (!$(this).is(":checked")) {
      $(this)
        .closest(".goal-weightage-item")
        .find(".goal-weightage-input-full")
        .val(0);
      $("#select-all-goals-full").prop("checked", false);
    }
  });
  $(".goal-checkbox-auto").on("change", function () {
    if (!$(this).is(":checked")) {
      $(this)
        .closest(".goal-weightage-item")
        .find(".goal-weightage-input-auto")
        .val(0);
      $("#select-all-goals-auto").prop("checked", false);
    }
  });
  $(".goal-checkbox-super").on("change", function () {
    if (!$(this).is(":checked")) {
      $(this)
        .closest(".goal-weightage-item")
        .find(".goal-weightage-input-super")
        .val(0);
      $("#select-all-goals-super").prop("checked", false);
    }
  });

  $(".search-intent-checkbox-full").on("change", function () {
    if (!$(this).is(":checked")) {
      $(this)
        .closest(".search-intent-weightage-item")
        .find(".search-intent-weightage-input-full")
        .val(0);
      $("#select-all-search-intents-full").prop("checked", false);
    }
  });

  $(".search-intent-checkbox-auto").on("change", function () {
    if (!$(this).is(":checked")) {
      $(this)
        .closest(".search-intent-weightage-item")
        .find(".search-intent-weightage-input-auto")
        .val(0);
      $("#select-all-search-intents-auto").prop("checked", false);
    }
  });

  $(".search-intent-checkbox-super").on("change", function () {
    if (!$(this).is(":checked")) {
      $(this)
        .closest(".search-intent-weightage-item")
        .find(".search-intent-weightage-input-super")
        .val(0);
      $("#select-all-search-intents-super").prop("checked", false);
    }
  });

  $(".weightage-input-full").on("input", function () {
    $("#equal-weightage-content-types-full").prop("checked", false);
    $("#equal-weightage-goals-full").prop("checked", false);
    $("#equal-weightage-search-intents-full").prop("checked", false);
  });

  $(".weightage-input-auto").on("input", function () {
    $("#equal-weightage-templates").prop("checked", false);
  });

  $("#select-all-content-types-auto").on("change", function () {
    $(".content-type-checkbox-auto").prop("checked", this.checked);
  });

  $("#equal-weightage-content-types-auto").on("change", function () {
    if ($(this).is(":checked")) {
      $(".weightage-input-auto").val(1);
    }
  });

  $(".weightage-input").on("input", function () {
    $("#equal-weightage-users").prop("checked", false);
  });

  $(".weightage-input-auto").on("input", function () {
    $("#equal-weightage-content-types-auto").prop("checked", false);
  });

  $(".content-type-checkbox-auto").on("change", function () {
    if (!$(this).is(":checked")) {
      $(this)
        .closest(".template-weightage-item")
        .find(".weightage-input-auto")
        .val(0);
      $("#select-all-content-types-auto").prop("checked", false);
    }
  });

  $("#buyers_journey_super").on("change", function () {
    const selectedOption = $(this).val();
    if (recommendations_for_buyers_journey[selectedOption]) {
      $("#recommendation_text_super").text(
        recommendations_for_buyers_journey[selectedOption]
      );
      $("#recommendation_row_super").show();
      $("#use_recommended_row_super").show();
    } else {
      $("#recommendation_row_super").hide();
      $("#use_recommended_row_super").hide();
    }
  });

  $("#use_recommended_values_super").on("click", function () {
    const selectedOption = $("#buyers_journey_super").val();
    if (recommendations_for_buyers_journey[selectedOption]) {
      const recommendedPieces =
        recommendations_for_buyers_journey[selectedOption].match(/\d+/g)[0];
      $("#number_of_pieces_super").val(recommendedPieces);
    }
  });

  updateWeightageVisibility();

  $("#generate_full_workflow").on("click", function (e) {
    e.preventDefault();
    var button = $(this);
    var form = document.querySelector("#generate-content-form"); // Get the form element

    // console.log(form, form.checkValidity());
    // Check if the form is valid
    if (form.checkValidity()) {
      button.prop("disabled", true).text("Loading..."); // Disable the button and show loading text
    } else {
      form.reportValidity(); // This will show the validation errors

      // Scroll to the first invalid input field
      var firstInvalidField = form.querySelector(":invalid");
      if (firstInvalidField) {
        firstInvalidField.scrollIntoView({
          behavior: "smooth",
          block: "center",
        });
        firstInvalidField.focus(); // Optionally, set focus on the invalid field
      }

      return; // Prevent further execution if the form is invalid
    }

    var contentTypeWeightage = {};
    $(".content-type-checkbox-full:checked").each(function () {
      var contentType = $(this).val();
      contentTypeWeightage[contentType] = parseInt(
        $(
          "input[name='content_type_weightage_full[" + contentType + "]']"
        ).val()
      );
    });

    const goalWeightage = {};
    $(".goal-checkbox-full:checked").each(function () {
      var goal = $(this).val();
      goalWeightage[goal] = parseInt(
        $("input[name='goal_weightage_full[" + goal + "]']").val()
      );
    });

    const searchIntentWeightage = {};
    $(".search-intent-checkbox-full:checked").each(function () {
      var intent = $(this).val();
      searchIntentWeightage[intent] = parseInt(
        $("input[name='search_intent_weightage_full[" + intent + "]']").val()
      );
    });

    var userWeightage = {};
    $(".user-checkbox-full:checked").each(function () {
      var userId = $(this).val();
      userWeightage[userId] = parseInt(
        $("input[name='user_weightage_full[" + userId + "]']").val()
      );
    });

    var linkTexts = $("input[name='link_text_full[]']")
      .map(function () {
        return $(this).val();
      })
      .get();
    var linkUrls = $("input[name='link_url_full[]']")
      .map(function () {
        return $(this).val();
      })
      .get();

    var customImage = document.getElementById("custom_image").files[0];
    var imageInfo = [];

    if (customImage) {
      var imageData = new FormData();
      imageData.append("action", "upload_custom_image"); // Specify the action field
      imageData.append("custom_image", customImage);
      imageData.append("nonce", myAjax.nonce);

      $.ajax({
        url: ajaxurl,
        type: "POST",
        data: imageData,
        processData: false, // Important for FormData
        contentType: false, // Important for FormData
        async: false, // Make sure this completes before continuing
        success: function (response) {
          // console.log(response);
          if (response.success) {
            imageInfo = response.data.image_info; // Assuming your server returns image info
          } else {
            console.error("Image upload failed: " + response.data.message);
            toastr.error("Image upload failed: " + response.data.message);
          }
        },
        fail: function (jqXHR, textStatus, errorThrown) {
          console.error("Image upload failed:", textStatus, errorThrown);
          toastr.error(
            "Image upload failed: " + textStatus + ": " + errorThrown
          );
        },
      });
    }

    var data = {
      action: "generate_full_workflow_content",
      nonce: myAjax.nonce,
      title: $("#title").val(),
      content_strategy: $("#content_strategy_full").val(),
      goal: $("#goal").val(),
      goal_weightage: goalWeightage,
      target_audience: $("#target_audience").val(),
      keywords: $("#keywords").val(),
      search_intent: $("#search_intent").val(),
      search_intent_weightage: searchIntentWeightage,
      language: $("#language_full").val(),
      link_text: linkTexts,
      link_url: linkUrls,
      word_count: $("#word_count").val(),
      tone: $("#tone").val(),
      content_type: $("#full_workflow_content_type").val(),
      content_type_weightage: contentTypeWeightage,
      number_of_pieces: $("#number_of_pieces").val(),
      schedule: $("#schedule").val(),
      schedule_interval: $("#schedule_interval").val(),
      post_status: $("#post_status").val(),
      author: $("#author").val(),
      user_weightage: userWeightage,
      titles_to_generate: getCheckedTitles() || [],
    };
    if (customImage) {
      data.image_info = imageInfo;
    }
    if ($("#post_status").val() === "pending") {
      data.admin_email = $("#admin_email").val(); // Append the admin email for the primary post status
    }
    $.post(ajaxurl, data, function (response) {
      if (response.success) {
        response.data.responses.forEach((content, index) => {
          toastr.success(`Content ${index + 1} scheduled successfully.`);
        });
      } else {
        if (response.data.type === "QuotaExceeded") {
          toastr.error("Insufficient credits. Redirecting to purchase page...");
          setTimeout(() => {
            window.location.href = "https://1upmedia.com/product/dam-access/";
          }, 5000);
        } else {
          toastr.error("Error: " + response.data.message);
        }
      }
      button.prop("disabled", false).text("Generate Full Workflow Content");
    }).fail(function (jqXHR, textStatus, errorThrown) {
      console.error("AJAX request failed:", textStatus, errorThrown);
      toastr.error("AJAX request failed: " + textStatus + ": " + errorThrown);
      button.prop("disabled", false).text("Generate Full Workflow Content");
    });
  });

  $("#generate_automated").on("click", function (e) {
    e.preventDefault();
    var button = $(this);
    var form = document.querySelector("#generate-automated-content-form"); // Get the form element

    // Check if the form is valid
    if (form.checkValidity()) {
      button.prop("disabled", true).text("Loading..."); // Disable the button and show loading text
    } else {
      form.reportValidity(); // This will show the validation errors

      // Scroll to the first invalid input field
      var firstInvalidField = form.querySelector(":invalid");
      if (firstInvalidField) {
        firstInvalidField.scrollIntoView({
          behavior: "smooth",
          block: "center",
        });
        firstInvalidField.focus(); // Optionally, set focus on the invalid field
      }

      return; // Prevent further execution if the form is invalid
    }

    var userWeightage = {};
    $(".user-checkbox:checked").each(function () {
      var userId = $(this).val();
      userWeightage[userId] = parseInt(
        $("input[name='user_weightage[" + userId + "]']").val()
      );
    });

    var templateWeightage = {};
    $(".content-type-checkbox-auto:checked").each(function () {
      var templateId = $(this).val();
      templateWeightage[templateId] = parseInt(
        $("input[name='template_weightage_auto[" + templateId + "]']").val()
      );
    });

    var linkTexts = $("input[name='link_text_auto[]']")
      .map(function () {
        return $(this).val();
      })
      .get();
    var linkUrls = $("input[name='link_url_auto[]']")
      .map(function () {
        return $(this).val();
      })
      .get();

    var data = {
      action: "generate_automated_content",
      nonce: myAjax.nonce,
      topic: $("#topic").val(),
      content_strategy: $("#content_strategy_auto").val(),
      template_style: $("#template_style").val(),
      number_of_pieces_auto: $("#number_of_pieces_auto").val(),
      schedule_auto: $("#schedule_auto").val(),
      schedule_interval_auto: $("#schedule_interval_auto").val(),
      post_status_auto: $("#post_status_auto").val(),
      author_auto: $("#author_auto").val(),
      search_intent: "Random",
      search_intent_weightage: {},
      language: $("#language_auto").val(),
      goal: "Random",
      word_count: $("#word_count_auto").val(),
      goal_weightage: {},
      user_weightage: userWeightage,
      template_weightage: templateWeightage,
      link_text_auto: linkTexts,
      link_url_auto: linkUrls,
    };

    if ($("#post_status_auto").val() === "pending") {
      data.admin_email = $("#admin_email_auto").val(); // Append the admin email for auto status
    }

    $.post(ajaxurl, data, function (response) {
      if (response.success) {
        response.data.responses.forEach((content, index) => {
          toastr.success(`Content ${index + 1} scheduled successfully.`);
        });
      } else {
        if (response.data.type === "QuotaExceeded") {
          toastr.error("Insufficient credits. Redirecting to purchase page...");
          setTimeout(() => {
            window.location.href = "https://1upmedia.com/product/dam-access/";
          }, 5000); // 10 seconds
        } else {
          toastr.error("Error: " + response.data.message);
        }
      }
      button.prop("disabled", false).text("Generate Automated Content");
    }).fail(function (jqXHR, textStatus, errorThrown) {
      console.error("AJAX request failed:", textStatus, errorThrown);
      toastr.error("AJAX request failed: " + textStatus + ": " + errorThrown);
      button.prop("disabled", false).text("Generate Automated Content");
    });
  });

  $("#generate_super_automated").on("click", function (e) {
    e.preventDefault();
    var button = $(this);
    var form = document.querySelector("#generate-super-automated-content-form"); // Get the form element

    // Check if the form is valid
    if (form.checkValidity()) {
      button.prop("disabled", true).text("Loading..."); // Disable the button and show loading text
    } else {
      form.reportValidity(); // This will show the validation errors

      // Scroll to the first invalid input field
      var firstInvalidField = form.querySelector(":invalid");
      if (firstInvalidField) {
        firstInvalidField.scrollIntoView({
          behavior: "smooth",
          block: "center",
        });
        firstInvalidField.focus(); // Optionally, set focus on the invalid field
      }

      return; // Prevent further execution if the form is invalid
    }

    var userWeightage = {};
    $(".user-checkbox-super:checked").each(function () {
      var userId = $(this).val();
      userWeightage[userId] = parseInt(
        $("input[name='user_weightage_super[" + userId + "]']").val()
      );
    });

    const goalWeightage = {};
    $(".goal-checkbox-super:checked").each(function () {
      var goal = $(this).val();
      goalWeightage[goal] = parseInt(
        $("input[name='goal_weightage_super[" + goal + "]']").val()
      );
    });

    const searchIntentWeightage = {};
    $(".search-intent-checkbox-super:checked").each(function () {
      var intent = $(this).val();
      searchIntentWeightage[intent] = parseInt(
        $("input[name='search_intent_weightage_super[" + intent + "]']").val()
      );
    });

    var linkTexts = $("input[name='link_text_super[]']")
      .map(function () {
        return $(this).val();
      })
      .get();
    var linkUrls = $("input[name='link_url_super[]']")
      .map(function () {
        return $(this).val();
      })
      .get();

    var data = {
      action: "generate_super_automated_content",
      nonce: myAjax.nonce,
      topic: $("#topic_super").val(),
      buyers_journey: $("#buyers_journey_super").val(),
      number_of_pieces_super: $("#number_of_pieces_super").val(),
      schedule_super: $("#schedule_super").val(),
      schedule_interval_super: $("#schedule_interval_super").val(),
      post_status_super: $("#post_status_super").val(),
      author_super: $("#author_super").val(),
      search_intent: "Random",
      search_intent_weightage: searchIntentWeightage,
      goal: "Random",
      goalWeightage: goalWeightage,
      user_weightage: userWeightage,
      language: $("#language_super").val(),
      link_text_super: linkTexts,
      link_url_super: linkUrls,
    };
    if ($("#post_status_super").val() === "pending") {
      data.admin_email = $("#admin_email_super").val(); // Append the admin email for super status
    }

    $.post(ajaxurl, data, function (response) {
      if (response.success) {
        response.data.responses.forEach((content, index) => {
          toastr.success(`Content ${index + 1} scheduled successfully.`);
        });
      } else {
        if (response.data.type === "QuotaExceeded") {
          toastr.error("Insufficient credits. Redirecting to purchase page...");
          setTimeout(() => {
            window.location.href = "https://1upmedia.com/product/dam-access/";
          }, 5000); // 10 seconds
        } else {
          toastr.error("Error: " + response.data.message);
        }
      }
      button.prop("disabled", false).text("Generate Super Automated Content");
    }).fail(function (jqXHR, textStatus, errorThrown) {
      console.error("AJAX request failed:", textStatus, errorThrown);
      toastr.error("AJAX request failed: " + textStatus + ": " + errorThrown);
      button.prop("disabled", false).text("Generate Super Automated Content");
    });
  });

  $("#generate_industry_automated").on("click", function (e) {
    e.preventDefault();
    var button = $(this);
    var form = document.querySelector(
      "#generate-industry-automated-content-form"
    ); // Get the form element

    // Check if the form is valid
    if (form.checkValidity()) {
      button.prop("disabled", true).text("Loading..."); // Disable the button and show loading text
    } else {
      form.reportValidity(); // This will show the validation errors

      // Scroll to the first invalid input field
      var firstInvalidField = form.querySelector(":invalid");
      if (firstInvalidField) {
        firstInvalidField.scrollIntoView({
          behavior: "smooth",
          block: "center",
        });
        firstInvalidField.focus(); // Optionally, set focus on the invalid field
      }

      return; // Prevent further execution if the form is invalid
    }

    var userWeightage = {};
    $(".user-checkbox-industry:checked").each(function () {
      var userId = $(this).val();
      userWeightage[userId] = parseInt(
        $("input[name='user_weightage_industry[" + userId + "]']").val()
      );
    });

    const goalWeightage = {};
    $(".goal-checkbox-industry:checked").each(function () {
      var goal = $(this).val();
      goalWeightage[goal] = parseInt(
        $("input[name='goal_weightage_industry[" + goal + "]']").val()
      );
    });

    const searchIntentWeightage = {};
    $(".search-intent-checkbox-industry:checked").each(function () {
      var intent = $(this).val();
      searchIntentWeightage[intent] = parseInt(
        $(
          "input[name='search_intent_weightage_industry[" + intent + "]']"
        ).val()
      );
    });

    var linkTexts = $("input[name='link_text_industry[]']")
      .map(function () {
        return $(this).val();
      })
      .get();
    var linkUrls = $("input[name='link_url_industry[]']")
      .map(function () {
        return $(this).val();
      })
      .get();

    var data = {
      action: "generate_industry_automated_content",
      nonce: myAjax.nonce,
      topic: $("#topic_industry").val(),
      buyers_journey: "Full Journey",
      number_of_pieces_industry: $("#number_of_pieces_industry").val(),
      schedule_industry: $("#schedule_industry").val(),
      schedule_interval_industry: $("#schedule_interval_industry").val(),
      post_status_industry: $("#post_status_industry").val(),
      author_industry: $("#author_industry").val(),
      language: $("#language_industry").val(),
      search_intent: "Random",
      search_intent_weightage: searchIntentWeightage,
      goal: $("#buyers_journey_industry").val(),
      goalWeightage: goalWeightage,
      user_weightage: userWeightage,
      link_text_industry: linkTexts,
      link_url_industry: linkUrls,
    };
    if ($("#post_status_industry").val() === "pending") {
      data.admin_email = $("#admin_email_industry").val(); // Append the admin email for super status
    }

    $.post(ajaxurl, data, function (response) {
      if (response.success) {
        response.data.responses.forEach((content, index) => {
          toastr.success(`Content ${index + 1} scheduled successfully.`);
        });
      } else {
        if (response.data.type === "QuotaExceeded") {
          toastr.error("Insufficient credits. Redirecting to purchase page...");
          setTimeout(() => {
            window.location.href = "https://1upmedia.com/product/dam-access/";
          }, 5000); // 10 seconds
        } else {
          toastr.error("Error: " + response.data.message);
        }
      }
      button
        .prop("disabled", false)
        .text("Generate industry Automated Content");
    }).fail(function (jqXHR, textStatus, errorThrown) {
      console.error("AJAX request failed:", textStatus, errorThrown);
      toastr.error("AJAX request failed: " + textStatus + ": " + errorThrown);
      button
        .prop("disabled", false)
        .text("Generate industry Automated Content");
    });
  });

  let previouslyGeneratedTitles = []; // Array to store titles and their checked state

  $("#preview_titles").on("click", function (e) {
    e.preventDefault();
    var button = $(this);
    var form = document.querySelector("#generate-content-form"); // Get the form element
    $("#generate_previewed_contents").show();

    // Check if the form is valid
    if (form.checkValidity()) {
      button.prop("disabled", true).text("Loading..."); // Disable the button and show loading text
    } else {
      form.reportValidity(); // Show validation errors

      // Scroll to the first invalid input field
      var firstInvalidField = form.querySelector(":invalid");
      if (firstInvalidField) {
        firstInvalidField.scrollIntoView({
          behavior: "smooth",
          block: "center",
        });
        firstInvalidField.focus(); // Optionally, set focus on the invalid field
      }

      return; // Prevent further execution if the form is invalid
    }

    var contentTypeWeightage = {};
    $(".content-type-checkbox-full:checked").each(function () {
      var contentType = $(this).val();
      contentTypeWeightage[contentType] = parseInt(
        $(
          "input[name='content_type_weightage_full[" + contentType + "]']"
        ).val()
      );
    });

    const goalWeightage = {};
    $(".goal-checkbox-full:checked").each(function () {
      var goal = $(this).val();
      goalWeightage[goal] = parseInt(
        $("input[name='goal_weightage_full[" + goal + "]']").val()
      );
    });

    const searchIntentWeightage = {};
    $(".search-intent-checkbox-full:checked").each(function () {
      var intent = $(this).val();
      searchIntentWeightage[intent] = parseInt(
        $("input[name='search_intent_weightage_full[" + intent + "]']").val()
      );
    });

    var userWeightage = {};
    $(".user-checkbox-full:checked").each(function () {
      var userId = $(this).val();
      userWeightage[userId] = parseInt(
        $("input[name='user_weightage_full[" + userId + "]']").val()
      );
    });

    var data = {
      action: "get_preview_titles",
      nonce: myAjax.nonce,
      title: $("#title").val(),
      content_strategy: $("#content_strategy_full").val(),
      goal: $("#goal").val(),
      goal_weightage: goalWeightage,
      target_audience: $("#target_audience").val(),
      keywords: $("#keywords").val(),
      search_intent: $("#search_intent").val(),
      search_intent_weightage: searchIntentWeightage,
      tone: $("#tone").val(),
      content_type: $("#full_workflow_content_type").val(),
      content_type_weightage: contentTypeWeightage,
      number_of_pieces: $("#number_of_pieces").val(),
      language: $("#language_full").val(),
      existing_titles: previouslyGeneratedTitles.map((item) => item.title), // Pass only the titles to the server
    };

    $.post(ajaxurl, data, function (response) {
      if (response.success) {
        console.log(response);
        $("#title_preview_row").show();
        let titlesDisplayArea = document.querySelector("#title_preview");

        let htmlContentforTitles = "";
        const n = parseInt($("#number_of_pieces").val()); // Get the number of pieces

        // Count already "Checked" titles
        let checkedCount = previouslyGeneratedTitles.filter(
          (item) => item.checkedStatus === "Checked"
        ).length;

        // Loop through the new titles and add them to the array
        response.data.titles.forEach(function (title, index) {
          const uniqueId = `title_${previouslyGeneratedTitles.length + index}`;

          // Logic for deciding the initial check status for the new titles
          let checkedStatus = "Not decided"; // Default to "Not decided"

          // If the title was previously generated and marked "Unchecked", keep it unchecked
          const existingTitle = previouslyGeneratedTitles.find(
            (item) => item.title === title
          );

          if (existingTitle && existingTitle.checkedStatus === "Unchecked") {
            checkedStatus = "Unchecked"; // Keep previously unchecked titles unchecked
          } else if (checkedCount < n) {
            // Only check new titles if less than n titles are checked
            checkedStatus = "Checked";
            checkedCount++;
          }

          // Add the title and its checked status to the array
          previouslyGeneratedTitles.push({
            title: title,
            checkedStatus: checkedStatus,
          });

          // Build the HTML for the title preview
          htmlContentforTitles += `<label style="display: block; margin-bottom: 5px;">
            <input type="checkbox" name="generated_titles[]" value="${title}" ${
            checkedStatus === "Checked" ? "checked" : ""
          } data-index="${previouslyGeneratedTitles.length - 1}">
            <input type="text" id="${uniqueId}" value="${title}" style="width: 80%;" />
            <button type="button" class="update-title-btn" data-index="${
              previouslyGeneratedTitles.length - 1
            }" data-id="${uniqueId}">Update</button>
          </label>`;
        });

        console.log("Title display area", htmlContentforTitles);
        titlesDisplayArea.innerHTML += htmlContentforTitles;

        // Add event listener for checkboxes to update the checkedStatus in the array
        document
          .querySelectorAll('input[name="generated_titles[]"]')
          .forEach(function (checkbox) {
            checkbox.addEventListener("change", function () {
              const index = this.getAttribute("data-index");

              // Update checkedStatus based on whether it's checked or unchecked
              if (this.checked) {
                previouslyGeneratedTitles[index].checkedStatus = "Checked";
              } else {
                previouslyGeneratedTitles[index].checkedStatus = "Unchecked";
              }

              // Update the remaining articles note whenever the checkbox state changes
              updateRemainingArticlesNote();
            });
          });

        // Add event listener for "Update" buttons
        document
          .getElementById("title_preview")
          .addEventListener("click", function (event) {
            if (event.target.classList.contains("update-title-btn")) {
              // Get the index and inputId from the clicked button
              const index = event.target.getAttribute("data-index");
              const inputId = event.target.getAttribute("data-id");

              // Log for debugging
              console.log("Index:", index);
              console.log("Input ID:", inputId);

              // Find the input element by ID
              const inputElement = document.getElementById(inputId);
              if (!inputElement) {
                console.error("Input element not found for id:", inputId);
                return;
              }

              // Get the updated title from the input field
              const updatedTitle = inputElement.value;

              // Update the title in the previouslyGeneratedTitles array
              if (previouslyGeneratedTitles[index]) {
                previouslyGeneratedTitles[index].title = updatedTitle;
                console.log(
                  "Updated title in array:",
                  previouslyGeneratedTitles[index].title
                );
              } else {
                console.error("No title found at index:", index);
                return;
              }

              // Log the updated input element
              console.log("Updated input element value:", inputElement.value);

              // Alert the user
              alert(`Title updated to: ${updatedTitle}`);
            }
          });

        // Call this function to initialize the remaining articles note on first render
        updateRemainingArticlesNote();
      } else {
        alert("Error: " + response?.data?.error);
      }
      button.prop("disabled", false).text("Load more");
    });
  });

  function updateRemainingArticlesNote() {
    const numberOfPieces = parseInt($("#number_of_pieces").val(), 10); // Get the original number of articles
    const checkedBoxesCount = document.querySelectorAll(
      'input[name="generated_titles[]"]:checked'
    ).length; // Count checked boxes

    let remainingArticlesNote = ""; // This will store the message to display
    let noteColor = ""; // Variable to store the color

    if (checkedBoxesCount < numberOfPieces) {
      const remaining = numberOfPieces - checkedBoxesCount;
      remainingArticlesNote = `Note: You need to select ${remaining} more article(s).`;
      noteColor = "red"; // Set color to yellow for fewer articles
    } else if (checkedBoxesCount > numberOfPieces) {
      const excess = checkedBoxesCount - numberOfPieces;
      remainingArticlesNote = `Note: You have selected ${excess} more article(s) than your original number of articles. It will generate based on the selected number of checkboxes.`;
      noteColor = "red"; // Set color to red for exceeding articles
    } else {
      remainingArticlesNote = `Note: You have selected exactly ${numberOfPieces} article(s).`;
      noteColor = "green"; // Set color to green for exact match
    }

    // Update the note in the DOM and change its color
    $("#remaining_articles_note")
      .text(remainingArticlesNote)
      .css("color", noteColor);
  }

  $("#generate_previewed_contents").on("click", function (e) {
    e.preventDefault();
    var button = $(this);
    var form = document.querySelector("#generate-content-form"); // Get the form element

    // console.log(form, form.checkValidity());
    // Check if the form is valid
    if (form.checkValidity()) {
      button.prop("disabled", true).text("Loading..."); // Disable the button and show loading text
    } else {
      form.reportValidity(); // This will show the validation errors

      // Scroll to the first invalid input field
      var firstInvalidField = form.querySelector(":invalid");
      if (firstInvalidField) {
        firstInvalidField.scrollIntoView({
          behavior: "smooth",
          block: "center",
        });
        firstInvalidField.focus(); // Optionally, set focus on the invalid field
      }

      return; // Prevent further execution if the form is invalid
    }

    var contentTypeWeightage = {};
    $(".content-type-checkbox-full:checked").each(function () {
      var contentType = $(this).val();
      contentTypeWeightage[contentType] = parseInt(
        $(
          "input[name='content_type_weightage_full[" + contentType + "]']"
        ).val()
      );
    });

    const goalWeightage = {};
    $(".goal-checkbox-full:checked").each(function () {
      var goal = $(this).val();
      goalWeightage[goal] = parseInt(
        $("input[name='goal_weightage_full[" + goal + "]']").val()
      );
    });

    const searchIntentWeightage = {};
    $(".search-intent-checkbox-full:checked").each(function () {
      var intent = $(this).val();
      searchIntentWeightage[intent] = parseInt(
        $("input[name='search_intent_weightage_full[" + intent + "]']").val()
      );
    });

    var userWeightage = {};
    $(".user-checkbox-full:checked").each(function () {
      var userId = $(this).val();
      userWeightage[userId] = parseInt(
        $("input[name='user_weightage_full[" + userId + "]']").val()
      );
    });

    var linkTexts = $("input[name='link_text_full[]']")
      .map(function () {
        return $(this).val();
      })
      .get();
    var linkUrls = $("input[name='link_url_full[]']")
      .map(function () {
        return $(this).val();
      })
      .get();

    var customImage = document.getElementById("custom_image").files[0];
    var imageInfo = [];

    if (customImage) {
      var imageData = new FormData();
      imageData.append("action", "upload_custom_image"); // Specify the action field
      imageData.append("custom_image", customImage);
      imageData.append("nonce", myAjax.nonce);

      $.ajax({
        url: ajaxurl,
        type: "POST",
        data: imageData,
        processData: false, // Important for FormData
        contentType: false, // Important for FormData
        async: false, // Make sure this completes before continuing
        success: function (response) {
          // console.log(response);
          if (response.success) {
            imageInfo = response.data.image_info; // Assuming your server returns image info
          } else {
            console.error("Image upload failed: " + response.data.message);
            toastr.error("Image upload failed: " + response.data.message);
          }
        },
        fail: function (jqXHR, textStatus, errorThrown) {
          console.error("Image upload failed:", textStatus, errorThrown);
          toastr.error(
            "Image upload failed: " + textStatus + ": " + errorThrown
          );
        },
      });
    }

    var data = {
      action: "generate_full_workflow_content_with_previewed_titles",
      nonce: myAjax.nonce,
      title: $("#title").val(),
      content_strategy: $("#content_strategy_full").val(),
      goal: $("#goal").val(),
      goal_weightage: goalWeightage,
      target_audience: $("#target_audience").val(),
      keywords: $("#keywords").val(),
      search_intent: $("#search_intent").val(),
      search_intent_weightage: searchIntentWeightage,
      link_text: linkTexts,
      link_url: linkUrls,
      word_count: $("#word_count").val(),
      tone: $("#tone").val(),
      content_type: $("#full_workflow_content_type").val(),
      content_type_weightage: contentTypeWeightage,
      language: $("#language_full").val(),
      number_of_pieces: $("#number_of_pieces").val(),
      schedule: $("#schedule").val(),
      schedule_interval: $("#schedule_interval").val(),
      post_status: $("#post_status").val(),
      author: $("#author").val(),
      user_weightage: userWeightage,
      titles_to_generate: getCheckedTitles(previouslyGeneratedTitles) || [],
    };
    console.log(data.titles_to_generate);
    if (customImage) {
      data.image_info = imageInfo;
    }
    if ($("#post_status").val() === "pending") {
      data.admin_email = $("#admin_email").val(); // Append the admin email for the primary post status
    }
    $.post(ajaxurl, data, function (response) {
      if (response.success) {
        response.data.responses.forEach((content, index) => {
          toastr.success(`Content ${index + 1} scheduled successfully.`);
        });
      } else {
        if (response.data.type === "QuotaExceeded") {
          toastr.error("Insufficient credits. Redirecting to purchase page...");
          setTimeout(() => {
            window.location.href = "https://1upmedia.com/product/dam-access/";
          }, 5000);
        } else {
          toastr.error("Error: " + response.data.message);
        }
      }
      button.prop("disabled", false).text("Generate Full Workflow Content");
    }).fail(function (jqXHR, textStatus, errorThrown) {
      console.error("AJAX request failed:", textStatus, errorThrown);
      toastr.error("AJAX request failed: " + textStatus + ": " + errorThrown);
      button.prop("disabled", false).text("Generate Full Workflow Content");
    });
  });
  // Example of using the previouslyGeneratedTitles array in another function:
  function getCheckedTitles(title_array) {
    console.log(title_array);

    return title_array
      ?.filter((item) => item.checkedStatus === "Checked")
      .map((item) => item.title);
  }

  $("#add-link-super").on("click", function () {
    var newLink = `
        <div class="link-item_super">
            <input type="text" name="link_text_super[]" placeholder="Anchor Text">
            <input type="url" name="link_url_super[]" placeholder="URL">
            <button type="button" class="remove-link button">Remove</button>
        </div>`;
    $("#links_super").append(newLink);
  });

  $(document).on("click", ".remove-link", function () {
    $(this).closest(".link-item_super").remove();
  });

  $("#select-all-users-super").on("change", function () {
    $(".user-checkbox-super").prop("checked", this.checked);
  });

  $("#equal-weightage-users-super").on("change", function () {
    if ($(this).is(":checked")) {
      $(".weightage-input-super").val(1);
    }
  });

  $(".user-checkbox-super").on("change", function () {
    if (!$(this).is(":checked")) {
      $(this)
        .closest(".user-weightage-item")
        .find(".weightage-input-super")
        .val(0);
      $("#select-all-users-super").prop("checked", false);
    }
  });

  $(".weightage-input-super").on("input", function () {
    $("#equal-weightage-users-super").prop("checked", false);
  });

  $("#add-link-full").on("click", function () {
    var newLink = `
        <div class="link-item_full">
            <input type="text" name="link_text_full[]" placeholder="Anchor Text">
            <input type="url" name="link_url_full[]" placeholder="URL">
            <button type="button" class="remove-link button">Remove</button>
        </div>`;
    $("#links_full").append(newLink);
  });

  $(document).on("click", ".remove-link", function () {
    $(this).closest(".link-item_full").remove();
  });

  $("#add-link-auto").on("click", function () {
    var newLink = `
        <div class="link-item_auto">
            <input type="text" name="link_text_auto[]" placeholder="Anchor Text">
            <input type="url" name="link_url_auto[]" placeholder="URL">
            <button type="button" class="remove-link button">Remove</button>
        </div>`;
    $("#links_auto").append(newLink);
  });

  $(document).on("click", ".remove-link", function () {
    $(this).closest(".link-item_auto").remove();
  });

  $("#workflow_mode").on("change", function () {
    if ($(this).val() === "full") {
      $("#generate-content-form").show();
      $("#generate-automated-content-form").hide();
      $("#generate-super-automated-content-form").hide();
      $("#generate-industry-automated-content-form").hide();
    } else if ($(this).val() === "automated") {
      $("#generate-content-form").hide();
      $("#generate-automated-content-form").show();
      $("#generate-super-automated-content-form").hide();
      $("#generate-industry-automated-content-form").hide();
    } else if ($(this).val() === "super_automated") {
      $("#generate-content-form").hide();
      $("#generate-automated-content-form").hide();
      $("#generate-super-automated-content-form").show();
      $("#generate-industry-automated-content-form").hide();
    } else if ($(this).val() === "industry_automated") {
      $("#generate-content-form").hide();
      $("#generate-automated-content-form").hide();
      $("#generate-super-automated-content-form").hide();
      $("#generate-industry-automated-content-form").show();
    } else {
      $("#generate-content-form").hide();
      $("#generate-automated-content-form").hide();
      $("#generate-super-automated-content-form").hide();
      $("#generate-industry-automated-content-form").hide();
    }
  });

  $("#select-all-content-types-full").on("change", function () {
    $(".content-type-checkbox-full").prop("checked", this.checked);
  });

  $("#equal-weightage-content-types-full").on("change", function () {
    if ($(this).is(":checked")) {
      $(".weightage-input-full").val(1);
    }
  });

  $(".content-type-checkbox-full").on("change", function () {
    if (!$(this).is(":checked")) {
      $(this)
        .closest(".content-type-weightage-item")
        .find(".weightage-input-full")
        .val(0);
      $("#select-all-content-types-full").prop("checked", false);
    }
  });

  $(".weightage-input-full").on("input", function () {
    $("#equal-weightage-content-types-full").prop("checked", false);
  });

  function resetTemplateWeightage(mode) {
    if (mode === "full") {
      $(".content-type-checkbox-full").prop("checked", false);
      $(".weightage-input-full").val(1);
      $("#select-all-content-types-full").prop("checked", false);
      $("#equal-weightage-content-types-full").prop("checked", false);
      $(`#full_workflow_content_type option[value="Random"]`).text(
        "Select Your Choice"
      );
      $(`#search_intent option[value="Random"]`).text("Select your Choice");
      $(`#goal option[value="Random"]`).text("Select Your Choice");
    } else if (mode === "auto") {
      $(".content-type-checkbox-auto").prop("checked", false);
      $(".weightage-input-auto").val(1);
      $("#select-all-content-types-auto").prop("checked", false);
      $("#equal-weightage-content-types-auto").prop("checked", false);
      $(`#template_style option[value="Random"]`).text("Select Your Choice");
    }
  }

  function updateTemplateWeightage(mode, strategy) {
    var weightage = {};
    if (mode === "full") {
      weightage = getWeightageForStrategy(strategy, "full");
    } else if (mode === "auto") {
      weightage = getWeightageForStrategy(strategy, "auto");
    }

    if (mode === "full") {
      for (const [type, percent] of Object.entries(weightage)) {
        $(`input[name='content_type_weightage_full[${type}]']`).val(percent);
        $(`input[name='content_type_selected_full[]'][value='${type}']`).prop(
          "checked",
          true
        );
      }
      $(`#full_workflow_content_type option[value="Random"]`).text(
        "Using Content Strategy"
      );
      $(`#search_intent option[value="Random"]`).text("Using Content Strategy");
      $(`#goal option[value="Random"]`).text("Using Content Strategy");
    } else if (mode === "auto") {
      for (const [type, percent] of Object.entries(weightage)) {
        $(`input[name='template_weightage_auto[${type}]']`).val(percent);
        $(`input[name='content_type_selected_auto[]'][value='${type}']`).prop(
          "checked",
          true
        );
      }
      $(`#template_style option[value="Random"]`).text(
        "Using Content Strategy"
      );
    }
  }

  $(".expand-template").on("click", function () {
    var $this = $(this);
    var $fullTemplate = $this.siblings(".template-full");
    var $preview = $this.siblings(".template-preview");

    if ($fullTemplate.is(":visible")) {
      $fullTemplate.hide();
      $preview.show();
      $this.text("Expand ▼");
    } else {
      $fullTemplate.show();
      $preview.hide();
      $this.text("Collapse ▲");
    }
  });

  function getWeightageForStrategy(strategy, mode) {
    const weightage = {
      "Content Clusters and Pillar Pages": {
        Review: 5,
        Editorial: 10,
        Interview: 5,
        "How To": 20,
        "Topic Introduction": 15,
        Opinion: 10,
        Research: 15,
        "Case Study": 5,
        "Short Report": 5,
        "Think Piece": 5,
        "Hard News": 2,
        "First Person": 2,
        "Service Piece": 3,
        Informational: 3,
      },
      "Topic Hubs and Resource Pages": {
        Review: 10,
        Editorial: 10,
        Interview: 5,
        "How To": 15,
        "Topic Introduction": 20,
        Opinion: 10,
        Research: 10,
        "Case Study": 5,
        "Short Report": 5,
        "Think Piece": 5,
        "Hard News": 2,
        "First Person": 2,
        "Service Piece": 3,
        Informational: 3,
      },
      "Thematic Groups and Hub Pages": {
        Review: 5,
        Editorial: 10,
        Interview: 5,
        "How To": 20,
        "Topic Introduction": 15,
        Opinion: 15,
        Research: 10,
        "Case Study": 5,
        "Short Report": 5,
        "Think Piece": 5,
        "Hard News": 2,
        "First Person": 2,
        "Service Piece": 3,
        Informational: 3,
      },
      "Cornerstone Content": {
        Review: 5,
        Editorial: 10,
        Interview: 10,
        "How To": 15,
        "Topic Introduction": 20,
        Opinion: 10,
        Research: 15,
        "Case Study": 5,
        "Short Report": 5,
        "Think Piece": 5,
        "Hard News": 2,
        "First Person": 2,
        "Service Piece": 3,
        Informational: 3,
      },
      "Content Series": {
        Review: 10,
        Editorial: 15,
        Interview: 5,
        "How To": 10,
        "Topic Introduction": 15,
        Opinion: 15,
        Research: 10,
        "Case Study": 5,
        "Short Report": 5,
        "Think Piece": 5,
        "Hard News": 2,
        "First Person": 2,
        "Service Piece": 3,
        Informational: 3,
      },
      "Ongoing Content Campaigns": {
        Review: 10,
        Editorial: 10,
        Interview: 10,
        "How To": 15,
        "Topic Introduction": 15,
        Opinion: 10,
        Research: 10,
        "Case Study": 5,
        "Short Report": 5,
        "Think Piece": 5,
        "Hard News": 2,
        "First Person": 2,
        "Service Piece": 3,
        Informational: 3,
      },
      "Serialized Content": {
        Review: 10,
        Editorial: 15,
        Interview: 10,
        "How To": 10,
        "Topic Introduction": 15,
        Opinion: 15,
        Research: 10,
        "Case Study": 5,
        "Short Report": 5,
        "Think Piece": 5,
        "Hard News": 2,
        "First Person": 2,
        "Service Piece": 3,
        Informational: 3,
      },
      "Evergreen Content Creation": {
        Review: 10,
        Editorial: 10,
        Interview: 10,
        "How To": 20,
        "Topic Introduction": 15,
        Opinion: 10,
        Research: 10,
        "Case Study": 5,
        "Short Report": 5,
        "Think Piece": 5,
        "Hard News": 2,
        "First Person": 2,
        "Service Piece": 3,
        Informational: 3,
      },
      "Long-Lasting Content": {
        Review: 10,
        Editorial: 10,
        Interview: 10,
        "How To": 15,
        "Topic Introduction": 15,
        Opinion: 10,
        Research: 10,
        "Case Study": 5,
        "Short Report": 5,
        "Think Piece": 5,
        "Hard News": 2,
        "First Person": 2,
        "Service Piece": 3,
        Informational: 3,
      },
      "Seasonal Updates": {
        Review: 5,
        Editorial: 10,
        Interview: 5,
        "How To": 15,
        "Topic Introduction": 20,
        Opinion: 15,
        Research: 5,
        "Case Study": 5,
        "Short Report": 5,
        "Think Piece": 5,
        "Hard News": 5,
        "First Person": 2,
        "Service Piece": 2,
        Informational: 2,
      },
      "Thought Leadership": {
        Review: 5,
        Editorial: 15,
        Interview: 10,
        "How To": 10,
        "Topic Introduction": 10,
        Opinion: 15,
        Research: 15,
        "Case Study": 5,
        "Short Report": 5,
        "Think Piece": 5,
        "Hard News": 2,
        "First Person": 2,
        "Service Piece": 1,
        Informational: 1,
      },
      "Industry Insights": {
        Review: 10,
        Editorial: 15,
        Interview: 10,
        "How To": 10,
        "Topic Introduction": 10,
        Opinion: 10,
        Research: 15,
        "Case Study": 5,
        "Short Report": 5,
        "Think Piece": 5,
        "Hard News": 2,
        "First Person": 2,
        "Service Piece": 1,
        Informational: 1,
      },
      "Expert Opinions": {
        Review: 5,
        Editorial: 15,
        Interview: 10,
        "How To": 10,
        "Topic Introduction": 10,
        Opinion: 15,
        Research: 10,
        "Case Study": 5,
        "Short Report": 5,
        "Think Piece": 10,
        "Hard News": 2,
        "First Person": 2,
        "Service Piece": 1,
        Informational: 1,
      },
      "Keyword Clusters": {
        Review: 10,
        Editorial: 10,
        Interview: 10,
        "How To": 20,
        "Topic Introduction": 15,
        Opinion: 10,
        Research: 10,
        "Case Study": 5,
        "Short Report": 5,
        "Think Piece": 5,
        "Hard News": 2,
        "First Person": 2,
        "Service Piece": 3,
        Informational: 3,
      },
      "Semantic Keywords": {
        Review: 10,
        Editorial: 10,
        Interview: 10,
        "How To": 20,
        "Topic Introduction": 15,
        Opinion: 10,
        Research: 10,
        "Case Study": 5,
        "Short Report": 5,
        "Think Piece": 5,
        "Hard News": 2,
        "First Person": 2,
        "Service Piece": 3,
        Informational: 3,
      },
      "Long-Tail Keywords": {
        Review: 10,
        Editorial: 10,
        Interview: 10,
        "How To": 20,
        "Topic Introduction": 15,
        Opinion: 10,
        Research: 10,
        "Case Study": 5,
        "Short Report": 5,
        "Think Piece": 5,
        "Hard News": 2,
        "First Person": 2,
        "Service Piece": 3,
        Informational: 3,
      },
      "Full Journey": {
        "How To": 15,
        "Topic Introduction": 15,
        Informational: 15,
        Editorial: 10,
        Interview: 10,
        Research: 10,
        Review: 8,
        "Case Study": 8,
        "Short Report": 4,
        "Think Piece": 3,
        Opinion: 2,
        "Service Piece": 2,
        "Hard News": 1,
        "First Person": 1,
      },
      Awareness: {
        "How To": 20,
        "Topic Introduction": 20,
        Informational: 20,
        Editorial: 15,
        Research: 10,
        Opinion: 5,
        Interview: 5,
        "Think Piece": 5,
      },
      Consideration: {
        Editorial: 20,
        Interview: 15,
        Research: 15,
        "Case Study": 10,
        Review: 10,
        "How To": 10,
        "Topic Introduction": 10,
        Opinion: 5,
        "Think Piece": 5,
      },
      Decision: {
        Review: 25,
        "Case Study": 20,
        "Short Report": 15,
        "Service Piece": 10,
        "Hard News": 10,
        Informational: 10,
        Editorial: 5,
        "First Person": 5,
      },
    };

    return weightage[strategy] || {};
  }
});

// document.addEventListener("DOMContentLoaded", function () {
//   var calendarEl = document.getElementById("post-calendar-tab");
//   console.log("Calendar tab..");

//   var calendar = new FullCalendar.Calendar(calendarEl, {
//     initialView: "dayGridMonth",
//     headerToolbar: {
//       left: "prev,next today",
//       center: "title",
//       right: "dayGridMonth",
//     },
//     events: function (fetchInfo, successCallback, failureCallback) {
//       jQuery.ajax({
//         url: myAjax.ajaxurl,
//         method: "POST",
//         data: {
//           action: "fetch_post_data",
//           start: fetchInfo.startStr,
//           end: fetchInfo.endStr,
//         },
//         success: function (response) {
//           var events = [];
//           jQuery.each(response.data, function (index, post) {
//             events.push({
//               title: post.title,
//               start: post.date, // Date of the post
//               url: post.url, // URL to view the post
//               extendedProps: {
//                 edit_url: post.edit_url, // URL to edit the post
//                 post_id: post.id, // Post ID
//               },
//             });
//           });
//           successCallback(events);
//         },
//         error: function () {
//           failureCallback();
//         },
//       });
//     },
//     eventClick: function (info) {
//       info.jsEvent.preventDefault();

//       var editUrl = info.event.extendedProps.edit_url;
//       var viewUrl = info.event.url;
//       if (confirm('Edit post? Click "Cancel" to view the post.')) {
//         window.open(editUrl, "_blank");
//       } else {
//         window.open(viewUrl, "_blank");
//       }
//     },
//     eventDrop: function (info) {
//       var newDate = info.event.start.toISOString().slice(0, 10);
//       var postId = info.event.extendedProps.post_id;

//       if (!postId) {
//         alert("Post ID is missing. Cannot update the post.");
//         info.revert();
//         return;
//       }

//       jQuery.ajax({
//         url: myAjax.ajaxurl,
//         method: "POST",
//         data: {
//           action: "update_post_date",
//           post_id: postId,
//           new_date: newDate,
//           nonce: myAjax.nonce,
//         },
//         success: function (response) {
//           if (response.success) {
//             alert("Post date updated successfully!");
//           } else {
//             alert("Error updating post date: " + response.data.message);
//             info.revert();
//           }
//         },
//         error: function () {
//           alert("AJAX error while updating post date.");
//           info.revert();
//         },
//       });
//     },
//   });

//   // Initially render the calendar
//   calendar.render();

//   document.addEventListener("visibilitychange", function () {
//     if (!document.hidden) {
//       setTimeout(function () {
//         calendar.updateSize(); // Recalculate the calendar layout when the tab becomes visible
//         window.dispatchEvent(new Event("resize")); // Trigger resize event to adjust layout
//         calendar.refetchEvents(); // Re-fetch the events after visibility change
//       }, 200); // Small delay to ensure visibility is fully changed
//     }
//   });
//   // Event listener for the button that makes the calendar visible
//   document
//     .getElementById("viewpostscalendar")
//     .addEventListener("click", function () {
//       document.getElementById("post-calendar-tab").style.display = "block";
//       console.log("Calendar clicked");
//       // Show the hidden calendar
//       setTimeout(function () {
//         calendar.updateSize();
//         window.dispatchEvent(new Event("resize"));
//         calendar.refetchEvents(); // Fetch events again
//       }, 100);
//     });
// });

document.addEventListener("DOMContentLoaded", function () {
  var calendarEl = document.getElementById("post-calendar");
  console.log("Calendar.");

  var calendar = new FullCalendar.Calendar(calendarEl, {
    initialView: "dayGridMonth",
    headerToolbar: {
      left: "prev,next today",
      center: "title",
      right: "dayGridMonth",
    },
    editable: true, // Enable drag-and-drop
    events: function (fetchInfo, successCallback, failureCallback) {
      // Use jQuery's full namespace
      jQuery.ajax({
        url: myAjax.ajaxurl, // Use localized variable for ajaxurl
        method: "POST",
        data: {
          action: "fetch_post_data",
          start: fetchInfo.startStr,
          end: fetchInfo.endStr,
        },
        success: function (response) {
          var events = [];
          jQuery.each(response.data, function (index, post) {
            console.log("Post ID:", post.id); // Log to check if post.id is set
            events.push({
              title: post.title,
              start: post.date, // Date of the post
              url: post.url, // URL to view the post
              extendedProps: {
                edit_url: post.edit_url, // URL to edit the post
                post_id: post.id, // Post ID
              },
            });
          });
          successCallback(events);
        },
        error: function () {
          failureCallback();
        },
      });
    },
    eventClick: function (info) {
      info.jsEvent.preventDefault(); // Prevent the browser from following the link

      // Get the post ID and build the correct edit URL
      var postId = info.event.extendedProps.post_id;
      if (postId) {
        var editUrl = "/wp-admin/post.php?post=" + postId + "&action=edit";
        window.open(editUrl, "_blank"); // Open the post's edit page in a new tab
      } else {
        console.error("Post ID not found for the event");
      }
    },
    eventDrop: function (info) {
      // Check the post_id before making the AJAX call
      console.log("Dropped Post ID:", info.event.extendedProps.post_id);
      console.log("New Date:", info.event.start.toISOString().slice(0, 10));

      var newDate = info.event.start.toISOString().slice(0, 10); // Get new date in YYYY-MM-DD format
      var postId = info.event.extendedProps.post_id; // Get the post ID

      if (!postId) {
        alert("Post ID is missing. Cannot update the post.");
        info.revert();
        return;
      }

      // Make an AJAX call to update the post date in WordPress
      jQuery.ajax({
        url: myAjax.ajaxurl,
        method: "POST",
        data: {
          action: "update_post_date", // Custom action to update the post date
          post_id: postId,
          new_date: newDate,
          nonce: myAjax.nonce, // Nonce for security
        },
        success: function (response) {
          if (response.success) {
            alert("Post date updated successfully!");
          } else {
            alert("Error updating post date: " + response.data.message);
            info.revert(); // Revert the event back to its original position
          }
        },
        error: function () {
          alert("AJAX error while updating post date.");
          info.revert(); // Revert the event back to its original position
        },
      });
    },
  });

  calendar.render();
});

jQuery(document).ready(function ($) {
  // Handle Find Business Details button click
  $("#find_business_details").on("click", function () {
    var button = $(this);
    var url = $("#create_url").val(); // Get the URL from the URL input field
    var location = $("#create_location").val(); // Get the Location from the Location input field
    var textarea = $("#business_details"); // Find the textarea where the business details will be displayed

    if (!url) {
      alert("Please enter a valid URL.");
      return;
    }

    // Disable the button to prevent multiple clicks
    button.prop("disabled", true).text("Loading...");

    // Send the AJAX request to the PHP backend
    $.ajax({
      url: ajaxurl, // WordPress AJAX URL
      method: "POST",
      data: {
        action: "fetch_business_details", // Custom action hook
        url: url,
        location: location,
        nonce: myAjax.nonce, // Pass nonce for security
      },
      success: function (response) {
        if (response.success) {
          // Display the business details in the textarea and show the row
          textarea.val(response.data.detail); // Display the fetched business details
          $("#business_details_row").show(); // Show the textarea row
        } else {
          textarea.val(
            "Error fetching business details: " + response.data.error
          );
          $("#business_details_row").show(); // Show the textarea row
        }
      },
      error: function () {
        textarea.val("AJAX request failed.");
        $("#business_details_row").show(); // Show the textarea row
      },
      complete: function () {
        // Re-enable the button
        button.prop("disabled", false).text("Find Business Details");
      },
    });
  });

  $("#find_domain_authority").on("click", function () {
    var button = $(this);
    var url = $("#create_url").val(); // Get the URL from the URL input field
    var location = $("#create_location").val(); // Get the Location from the Location input field
    var textarea = $("#domain_authority"); // Find the textarea where the business details will be displayed

    var textareacontentstrategy = $("#content_strategy"); // Find the textarea where the business details will be displayed

    if (!url) {
      alert("Please enter a valid URL.");
      return;
    }

    // Disable the button to prevent multiple clicks
    button.prop("disabled", true).text("Loading...");

    // Send the AJAX request to the PHP backend
    $.ajax({
      url: ajaxurl, // WordPress AJAX URL
      method: "POST",
      data: {
        action: "get_domain_authority", // Custom action hook
        site_url: url,
        nonce: myAjax.nonce, // Pass nonce for security
      },
      success: function (response) {
        if (response.success) {
          // Display the business details in the textarea and show the row
          textarea.val(response.data.domain_authority); // Display the fetched business details

          // Assuming textarea.val(response.data.domain_authority); already sets the DA value
          const domainAuthorityFromServer = parseInt(
            response.data.domain_authority,
            10
          ); // Fetch the DA value from response
          let strategy = ""; // Initialize an empty strategy variable

          // Determine strategy based on DA value
          if (
            domainAuthorityFromServer >= 0 &&
            domainAuthorityFromServer <= 10
          ) {
            strategy =
              "Prioritize long-tail keywords (informational or niche) with low competition. Generate educational content to drive initial traffic.";
          } else if (
            domainAuthorityFromServer >= 11 &&
            domainAuthorityFromServer <= 20
          ) {
            strategy =
              "Continue focusing on long-tail keywords while introducing some niche transactional terms. Generate a mix of FAQs, guides, and how-to articles.";
          } else if (
            domainAuthorityFromServer >= 21 &&
            domainAuthorityFromServer <= 30
          ) {
            strategy =
              "Target slightly more competitive long-tail keywords. Create content focused on customer acquisition and answering common customer questions.";
          } else if (
            domainAuthorityFromServer >= 31 &&
            domainAuthorityFromServer <= 40
          ) {
            strategy =
              "Introduce medium-competition keywords. Generate SEO-optimized blogs and in-depth articles for thought leadership and deeper insights.";
          } else if (
            domainAuthorityFromServer >= 41 &&
            domainAuthorityFromServer <= 50
          ) {
            strategy =
              "Expand the keyword strategy to include a balanced mix of long-tail and short-tail keywords. Focus on deeper insights, storytelling, and content optimization.";
          } else if (
            domainAuthorityFromServer >= 51 &&
            domainAuthorityFromServer <= 60
          ) {
            strategy =
              "Begin targeting competitive, transactional keywords. Organize content around keyword clustering, focusing on buyer decision-making and differentiation.";
          } else if (
            domainAuthorityFromServer >= 61 &&
            domainAuthorityFromServer <= 70
          ) {
            strategy =
              "Prioritize short-tail keywords while generating content with a focus on complex, authoritative topics to enhance brand authority.";
          } else if (
            domainAuthorityFromServer >= 71 &&
            domainAuthorityFromServer <= 80
          ) {
            strategy =
              "Analyze and optimize past content while producing new content targeting high-competition keywords with strong conversion potential.";
          } else if (
            domainAuthorityFromServer >= 81 &&
            domainAuthorityFromServer <= 90
          ) {
            strategy =
              "Focus on high-value transactional keywords with content geared toward maximizing visibility and customer engagement.";
          } else if (
            domainAuthorityFromServer >= 91 &&
            domainAuthorityFromServer <= 100
          ) {
            strategy =
              "Leverage domain authority to dominate highly competitive keywords and buyer decision content, ensuring content is optimized for maximum engagement and visibility.";
          } else {
            strategy = "Invalid Domain Authority value."; // Handle cases where DA is outside the expected range
          }

          // Set the strategy in the content strategy textarea
          textareacontentstrategy.val(strategy);

          $("#domain_authority_row").show(); // Show the textarea row
          $("#content_strategy_row").show();
        } else {
          textarea.val(
            "Error fetching business details: " + response.data.error
          );
          $("#domain_authority_row").show(); // Show the textarea row
          $("#content_strategy_row").show(); // Show the textarea row
        }
      },
      error: function () {
        textarea.val("AJAX request failed.");
        $("#domain_authority_row").show(); // Show the textarea row
        $("#content_strategy_row").show(); // Show the textarea row
      },
      complete: function () {
        // Re-enable the button
        button.prop("disabled", false).text("Find Domain Authority");
      },
    });
  });
});

jQuery(document).ready(function ($) {
  // Handle Find Business Details button click for the Update Form
  $("#find_update_business_details").on("click", function () {
    var button = $(this);
    var url = $("#update_url").val(); // Get the URL from the Update URL input field
    var location = $("#update_location").val(); // Get the Location from the Update Location input field
    var textarea = $("#update_business_details"); // Find the textarea where the business details will be displayed

    if (!url) {
      alert("Please enter a valid URL.");
      return;
    }

    // Disable the button to prevent multiple clicks
    button.prop("disabled", true).text("Loading...");

    // Send the AJAX request to the PHP backend
    $.ajax({
      url: ajaxurl, // WordPress AJAX URL
      method: "POST",
      data: {
        action: "fetch_business_details", // Custom action hook
        url: url,
        location: location,
        nonce: myAjax.nonce, // Pass nonce for security
      },
      success: function (response) {
        if (response.success) {
          // Display the business details in the textarea and show the row
          textarea.val(response.data.detail); // Display the fetched business details
          $("#update_business_details_row").show(); // Show the textarea row
        } else {
          textarea.val(
            "Error fetching business details: " + response.data.error
          );
          $("#update_business_details_row").show(); // Show the textarea row
        }
      },
      error: function () {
        textarea.val("AJAX request failed.");
        $("#update_business_details_row").show(); // Show the textarea row
      },
      complete: function () {
        // Re-enable the button
        button.prop("disabled", false).text("Find Business Details");
      },
    });
  });

  $("#find_update_domain_authority").on("click", function () {
    var button = $(this);
    var url = $("#update_url").val(); // Get the URL from the Update URL input field
    var location = $("#update_location").val(); // Get the Location from the Update Location input field
    var textarea = $("#update_domain_authority"); // Find the textarea where the business details will be displayed
    var textareacontentstrategy = $("#update_content_strategy");

    if (!url) {
      alert("Please enter a valid URL.");
      return;
    }

    // Disable the button to prevent multiple clicks
    button.prop("disabled", true).text("Loading...");

    // Send the AJAX request to the PHP backend
    $.ajax({
      url: ajaxurl, // WordPress AJAX URL
      method: "POST",
      data: {
        action: "get_domain_authority", // Custom action hook
        site_url: url,
        nonce: myAjax.nonce, // Pass nonce for security
      },
      success: function (response) {
        if (response.success) {
          // Display the business details in the textarea and show the row
          textarea.val(response.data.domain_authority); // Display the fetched business details

          // Assuming textarea.val(response.data.domain_authority); already sets the DA value
          const domainAuthorityFromServer = parseInt(
            response.data.domain_authority,
            10
          ); // Fetch the DA value from response
          let strategy = ""; // Initialize an empty strategy variable

          // Determine strategy based on DA value
          if (
            domainAuthorityFromServer >= 0 &&
            domainAuthorityFromServer <= 10
          ) {
            strategy =
              "Prioritize long-tail keywords (informational or niche) with low competition. Generate educational content to drive initial traffic.";
          } else if (
            domainAuthorityFromServer >= 11 &&
            domainAuthorityFromServer <= 20
          ) {
            strategy =
              "Continue focusing on long-tail keywords while introducing some niche transactional terms. Generate a mix of FAQs, guides, and how-to articles.";
          } else if (
            domainAuthorityFromServer >= 21 &&
            domainAuthorityFromServer <= 30
          ) {
            strategy =
              "Target slightly more competitive long-tail keywords. Create content focused on customer acquisition and answering common customer questions.";
          } else if (
            domainAuthorityFromServer >= 31 &&
            domainAuthorityFromServer <= 40
          ) {
            strategy =
              "Introduce medium-competition keywords. Generate SEO-optimized blogs and in-depth articles for thought leadership and deeper insights.";
          } else if (
            domainAuthorityFromServer >= 41 &&
            domainAuthorityFromServer <= 50
          ) {
            strategy =
              "Expand the keyword strategy to include a balanced mix of long-tail and short-tail keywords. Focus on deeper insights, storytelling, and content optimization.";
          } else if (
            domainAuthorityFromServer >= 51 &&
            domainAuthorityFromServer <= 60
          ) {
            strategy =
              "Begin targeting competitive, transactional keywords. Organize content around keyword clustering, focusing on buyer decision-making and differentiation.";
          } else if (
            domainAuthorityFromServer >= 61 &&
            domainAuthorityFromServer <= 70
          ) {
            strategy =
              "Prioritize short-tail keywords while generating content with a focus on complex, authoritative topics to enhance brand authority.";
          } else if (
            domainAuthorityFromServer >= 71 &&
            domainAuthorityFromServer <= 80
          ) {
            strategy =
              "Analyze and optimize past content while producing new content targeting high-competition keywords with strong conversion potential.";
          } else if (
            domainAuthorityFromServer >= 81 &&
            domainAuthorityFromServer <= 90
          ) {
            strategy =
              "Focus on high-value transactional keywords with content geared toward maximizing visibility and customer engagement.";
          } else if (
            domainAuthorityFromServer >= 91 &&
            domainAuthorityFromServer <= 100
          ) {
            strategy =
              "Leverage domain authority to dominate highly competitive keywords and buyer decision content, ensuring content is optimized for maximum engagement and visibility.";
          } else {
            strategy = "Invalid Domain Authority value."; // Handle cases where DA is outside the expected range
          }

          // Set the strategy in the content strategy textarea
          textareacontentstrategy.val(strategy);

          $("#update_domain_authority_row").show(); // Show the textarea row
          $("#update_content_strategy_row").show(); // Show the textarea row
        } else {
          textarea.val(
            "Error fetching business details: " + response.data.error
          );
          $("#update_domain_authority_row").show(); // Show the textarea row
          $("#update_content_strategy_row").show(); // Show the textarea row
        }
      },
      error: function () {
        textarea.val("AJAX request failed.");
        $("#update_domain_authority_row").show(); // Show the textarea row
        $("#update_content_strategy_row").show(); // Show the textarea row
      },
      complete: function () {
        // Re-enable the button
        button.prop("disabled", false).text("Find Domain Authority");
      },
    });
  });
});

jQuery(document).ready(function ($) {
  var calendarEl = document.getElementById("content-calendar");
  // Initialize an empty array to store both existing posts and preview titles events
  var allEvents = [];

  var instructions = ` 
  <div style="background-color: #fff; padding: 20px; border-radius: 5px; margin-bottom: 10px; line-height: 1.4; box-shadow: 0px 2px 8px rgba(0, 0, 0, 0.1);">
    <h3 style="margin-top: 0; font-weight: bold;">Instructions:</h3>
    <p style="margin: 5px 0;">
      <strong>Generate 365 Days:</strong> Click to auto-generate content titles for the year.
    </p>
    <p style="margin: 5px 0;">
      <strong>Review the Calendar:</strong> Ensure content aligns with your goals.
    </p>
    <p style="margin: 5px 0;">
      <strong>Confirm to Create:</strong> Creates 30 articles (monthly plan) or 365 (annual plan).
    </p>
    <p style="margin: 5px 0;">
      <strong>Edit Days:</strong> Click any day to add or adjust content directly.
    </p>
    <div style="margin-top: 15px;">
      <button id="generate-365days" class="button button-primary" style="margin-bottom: 10px; margin-right: 10px; background-color: #4caf50;">Generate 365 Days of Content</button>
      <button id="confirm-to-create" class="button button-primary" style="margin-bottom: 10px; background-color: #1a91bc;">Confirm to Create</button>
      <button id="clear-preview-titles" class="button" style="margin-bottom: 10px; margin-left: 10px; background-color: #ffcccc; color: #d9534f;">Clear Preview Titles</button>
      <button id="get-remaining-credits" class="button" style="margin-bottom: 10px; margin-left: 10px; background-color: ##f4f42c; color: #d454ff;">Get remaining credits</button>
    </div>
  </div>
  `;
  $(calendarEl).before(instructions);

  // Initialize FullCalendar
  var calendar = new FullCalendar.Calendar(calendarEl, {
    initialView: "dayGridMonth",
    editable: true, // Enable drag-and-drop for preview titles and posts
    eventSources: [
      {
        id: "existing-posts", // Existing posts
        events: function (fetchInfo, successCallback, failureCallback) {
          $.ajax({
            url: myAjax.ajaxurl,
            method: "POST",
            data: {
              action: "fetch_post_data", // Your action to fetch existing posts
              start: fetchInfo.startStr,
              end: fetchInfo.endStr,
              nonce: myAjax.nonce,
            },
            success: function (response) {
              var postEvents = [];
              $.each(response.data, function (index, post) {
                postEvents.push({
                  title: post.title,
                  start: post.date, // Date of the post
                  editable: true, // Enable editing for existing posts
                  backgroundColor: "#2196f3", // Blue color for existing posts
                  borderColor: "#2196f3", // Blue border
                  textColor: "#fff", // White text for visibility
                  extendedProps: {
                    url: post.url, // URL to view the post
                    post_id: post.id, // Post ID
                    edit_url: post.edit_url, // URL to edit the post
                  },
                });
              });
              // Add existing posts to the global allEvents array
              allEvents = allEvents.concat(postEvents);
              successCallback(postEvents); // Add the existing posts events to the calendar
            },
            error: function () {
              failureCallback();
            },
          });
        },
      },
      {
        id: "preview-titles", // Preview titles
        events: function (fetchInfo, successCallback, failureCallback) {
          // Only add unique events to avoid duplication
          let uniquePreviewEvents = allEvents.filter(
            (event) =>
              !allEvents.some(
                (existingEvent) => existingEvent.title === event.title
              )
          );

          allEvents = allEvents.concat(uniquePreviewEvents); // Add only unique preview events to allEvents
          successCallback(uniquePreviewEvents); // Preview events are added here
        },
      },
    ],
    eventDidMount: function (info) {
      // Create Edit and Delete buttons and append to the event element on click
      $(info.el).on("click", function (e) {
        // Show the form when the event is clicked
        showClickForm(e, info);
      });
    },
    eventDrop: function (info) {
      // Update the post date if it's an existing post
      var newDate = info.event.start.toISOString().slice(0, 10); // Get new date in YYYY-MM-DD format
      var postId = info.event.extendedProps.post_id; // Get the post ID

      if (postId) {
        // If it's an existing post, update the date in the WordPress backend
        jQuery.ajax({
          url: myAjax.ajaxurl,
          method: "POST",
          data: {
            action: "update_post_date", // Custom action to update the post date
            post_id: postId,
            new_date: newDate,
            nonce: myAjax.nonce, // Nonce for security
          },
          success: function (response) {
            if (response.success) {
              alert("Post date updated successfully!");
            } else {
              alert("Error updating post date: " + response.data.message);
              info.revert(); // Revert the event back to its original position
            }
          },
          error: function () {
            alert("AJAX error while updating post date.");
            info.revert(); // Revert the event back to its original position
          },
        });
      } else {
        // For preview events, just allow drag and drop without updating the backend
        console.log(
          "Preview title moved to: " + info.event.start.toISOString()
        );
      }
    },
    dayCellDidMount: function (arg) {
      // Add an "Add" button in the bottom-right corner of each day cell
      var addButton = document.createElement("button");
      addButton.innerText = "+";
      addButton.className = "add-event-btn";
      addButton.style.position = "absolute";
      addButton.style.bottom = "5px";
      addButton.style.right = "5px";
      addButton.style.borderRadius = "50%";
      addButton.style.backgroundColor = "#4caf50";
      addButton.style.color = "#fff";
      addButton.style.border = "none";
      addButton.style.cursor = "pointer";
      addButton.style.width = "25px";
      addButton.style.height = "25px";
      addButton.style.zIndex = "10";

      // Append the button to each day cell
      arg.el.style.position = "relative";
      arg.el.appendChild(addButton);

      // Add click event listener for the button
      addButton.addEventListener("click", function (e) {
        e.preventDefault(); // Prevent default behavior
        showGoalForm(e, arg.date.toISOString().slice(0, 10));
      });
    },
  });

  calendar.render();

  // Sorting logic to sort events (existing and preview) by date, goal, and alphabetically
  function sortAllEvents() {
    allEvents.sort(function (a, b) {
      // Sort by date first (earlier dates first)
      if (a.start < b.start) return -1;
      if (a.start > b.start) return 1;

      console.log("before", a, b);

      // Same day: Sort by goal (events without a goal should appear last)
      if (!a.backgroundColor && b.backgroundColor) return 1;
      if (a.backgroundColor && !b.backgroundColor) return -1;

      // Both have goals: Sort alphabetically within the same goal
      if (a.extendedProps.goal === b.extendedProps.goal) {
        return a.title.localeCompare(b.title);
      }

      // Otherwise, sort by goal name alphabetically
      return a.backgroundColor.localeCompare(b.backgroundColor);
    });
  }

  // Render sorted events to the calendar
  function renderSortedEvents() {
    sortAllEvents(); // Sort events before rendering them
    calendar.getEventSources().forEach((source) => source.remove()); // Remove old events
    calendar.addEventSource(allEvents); // Add the sorted events back
    calendar.refetchEvents(); // Refresh the calendar with sorted events
  }

  // Right-click handler for the calendar cells
  $(calendarEl).on("contextmenu", ".fc-daygrid-day", function (e) {
    e.preventDefault(); // Prevent the default right-click menu

    var clickedDate = $(this).attr("data-date");
    showGoalForm(e, clickedDate); // Trigger the goal form on right-click
  });

  function submitGoalForm(startDate, numArticles) {
    var selectedAuthor = $("#author-select").val();
    var selectedGoal = $("#goal-select").val();

    if (!selectedAuthor) {
      alert("Please select an author");
      return;
    }

    if (!selectedGoal) {
      alert("Please select a goal");
      return;
    }

    $("#loading-message").show();
    $("#button-group").hide();

    $.ajax({
      url: myAjax.ajaxurl,
      method: "POST",
      data: {
        action: "get_calendar_preview",
        num_articles: numArticles,
        start: startDate,
        goal: selectedGoal,
        author_id: selectedAuthor,
        nonce: myAjax.nonce,
      },
      success: function (response) {
        if (response.success) {
          var newPreviewEvents = [];
          $.each(response.data, function (index, article) {
            var eventColor;
            switch (article.goal) {
              case "Educate Customers":
                eventColor = "#4caf50"; // Green
                break;
              case "Enhance Customer Experience":
                eventColor = "#8386ff"; // Blue
                break;
              case "Differentiate from Competitors":
                eventColor = "#ff9800"; // Orange
                break;
              case "Acquire Customers":
                eventColor = "#f44336"; // Red
                break;
              case "Answer FAQs":
                eventColor = "#9c27b0"; // Purple
                break;
              default:
                eventColor = "#8b3aec"; // Default color
            }

            newPreviewEvents.push({
              title: article.title,
              start: article.date,
              editable: true,
              backgroundColor: eventColor,
              borderColor: eventColor,
              textColor: "#fff",
              extendedProps: {
                author_id: article.author_id,
                goal: article.goal,
                business_details: article.business_details,
              },
            });
          });

          // Add the new preview events to the array and re-render the calendar
          allEvents = allEvents.concat(newPreviewEvents);
          renderSortedEvents(); // Sort and render events
          $("#goalForm").remove(); // Close the form after successful submission
        } else {
          alert("Error loading events: " + response.data.error);
        }
      },
      error: function () {
        alert("Error generating content.");
      },
      complete: function () {
        $("#loading-message").hide();
        $("#button-group").show();
      },
    });
  }

  $(document).on("click", "#generate-365days", function () {
    loadAuthors(showGenerateForm); // Show the form for selecting the start date
  });

  $("#confirm-to-create").on("click", function () {
    var confirmMessage = `Once you confirm, all the title-previewed articles will be created based on the generated calendar.
        Please note that this action CANNOT be undone. Any changes to the articles will need to be made manually after creation.
        Do you wish to proceed?`;

    if (confirm(confirmMessage)) {
      createConfirmedContent(); // Call the function to create content
    }
  });

  $(document).on("click", "#clear-preview-titles", function () {
    if (
      confirm(
        "Are you sure you want to clear the preview titles for this month? This action cannot be undone."
      )
    ) {
      clearPreviewTitlesForCurrentMonth();
    }
  });

  $("#get-remaining-credits").on("click", function () {
    $.ajax({
      url: ajaxurl, // WordPress automatically defines this for AJAX
      method: "POST",
      data: {
        action: "get_remaining_credits",
      },
      success: function (response) {
        if (response.success) {
          alert("Remaining Credits: " + response.data.remaining_credits);
        } else {
          alert("Error: " + response.data.message);
        }
      },
      error: function () {
        alert("An error occurred while fetching the credits.");
      },
    });
  });

  // Call renderSortedEvents after events are fetched and preview titles are generated
  function createConfirmedContent() {
    var titlesWithDetails = []; // Collect all event details

    // Get all events from the calendar
    var allEvents = calendar.getEvents(); // Fetch all events from FullCalendar instance

    // Iterate over the allEvents array to gather all the necessary details (titles, dates, author_id, goal, business_details)
    $.each(allEvents, function (index, event) {
      var eventStartDate = event.start;

      // Log the event start for debugging
      console.log("Event Start:", eventStartDate);

      // Check if event.start is valid
      if (eventStartDate) {
        // Ensure that event.start is a Date object or convert it
        if (!(eventStartDate instanceof Date)) {
          eventStartDate = new Date(eventStartDate); // Convert to Date object if it's not already
        }

        // Check if eventStartDate is a valid date
        if (!isNaN(eventStartDate.getTime())) {
          // Format date as YYYY-MM-DD
          var formattedDate = eventStartDate.toISOString().slice(0, 10);

          // Only include events that have a goal (which indicates a preview title)
          if (event.extendedProps && event.extendedProps.goal) {
            titlesWithDetails.push({
              title: event.title,
              date: formattedDate, // Use formatted date
              author_id: event.extendedProps.author_id, // Get the author ID from the event
              goal: event.extendedProps.goal, // Get the goal from the event
              business_details: event.extendedProps.business_details, // Get the business details from the event
            });
          }
        } else {
          console.error("Invalid Date:", eventStartDate);
        }
      } else {
        console.error("event.start is undefined or null for event:", event);
      }
    });

    // Check if there are any previewed titles to send
    if (titlesWithDetails.length === 0) {
      alert("No preview titles to generate content from.");
      return;
    }

    // Perform the AJAX call to send previewed titles
    $.ajax({
      url: myAjax.ajaxurl,
      method: "POST",
      data: {
        action: "generate_content_in_calendar",
        nonce: myAjax.nonce,
        titles_with_details: titlesWithDetails, // Add the array of event details to the data
      },
      success: function (response) {
        if (response.success) {
          alert("Content generated successfully!");
        } else {
          console.log("Error: " + response.data.message);
        }
      },
      error: function () {
        console.log("Error generating content.");
      },
    });
  }

  function showClickForm(event, info) {
    var formHtml = `
    <div id="clickForm" style="position: absolute; top: ${event.pageY}px; left: ${event.pageX}px; background: white; padding: 10px; border: 1px solid #ccc; z-index: 100; box-shadow: 0px 4px 8px rgba(0,0,0,0.1);">
      <button id="edit-event" class="button button-primary">Edit</button>
      <button id="delete-event" class="button button-secondary" style="background-color: #f44336; color: #fff;">Delete</button>
      <button id="close-clickForm" class="button button-secondary" style="margin-top: 10px;">Close</button>
    </div>
  `;

    // Remove any existing forms
    $("#clickForm").remove();

    // Append the new form to the body
    $("body").append(formHtml);

    // Add event listener for the Edit button
    $("#edit-event").on("click", function () {
      if (info.event.extendedProps.post_id) {
        // Open the post edit page for existing posts
        window.open(
          "/wp-admin/post.php?post=" +
            info.event.extendedProps.post_id +
            "&action=edit",
          "_blank"
        );
      } else {
        // Edit the preview title for preview events
        var newTitle = prompt("Edit article title:", info.event.title);
        if (newTitle) {
          info.event.setProp("title", newTitle); // Update the title
        }
      }
      hideClickForm(); // Hide the form after action
    });

    // Add event listener for the Delete button
    $("#delete-event").on("click", function () {
      if (confirm("Are you sure you want to delete this event?")) {
        if (info.event.extendedProps.post_id) {
          // Existing posts cannot be deleted from the calendar
          alert("You cannot delete existing posts directly from the calendar.");
        } else {
          // Remove the preview title for preview events
          info.event.remove();
        }
      }
      hideClickForm(); // Hide the form after action
    });

    // Add event listener for the Close button
    $("#close-clickForm").on("click", function () {
      hideClickForm(); // Close the form when clicked
    });
  }

  function hideClickForm() {
    // Remove the form
    $("#clickForm").remove();
  }

  function showGoalForm(event, dateStr) {
    // Fetch and populate the authors in the form
    loadAuthors(function (authors) {
      var authorOptions = authors
        .map(function (author) {
          return `<option value="${author.id}">${author.display_name}</option>`;
        })
        .join("");

      // Create and show the popup form
      var formHtml = `
        <div id="goalForm" style="position: absolute; top: ${event.pageY}px; left: ${event.pageX}px; background: white; padding: 10px; border: 1px solid #ccc; z-index: 100;">
          <label for="author-select">Select Author:</label>
          <select id="author-select">
              <option value="">Select Author</option>
              ${authorOptions}
          </select><br/><br/>
          <label for="goal-select">Select Goal:</label>
          <select id="goal-select">
              <option value="">Select Goals</option>
              <option value="Educate Customers">Educate Customers</option>
              <option value="Enhance Customer Experience">Enhance Customer Experience</option>
              <option value="Differentiate from Competitors">Differentiate from Competitors</option>
              <option value="Acquire Customers">Acquire Customers</option>
              <option value="Answer FAQs">Answer FAQs</option>
          </select><br/><br/>
          <div id="button-group">
            <button id="7days-btn">7 Days</button>
            <button id="14days-btn">14 Days</button>
            <button id="1month-btn">1 Month</button>
          </div>
          <button id="close-popup">Close</button>
          <div id="loading-message" style="display:none; margin-top:10px; color:blue;">Loading, please wait...</div>
        </div>
      `;

      // Remove existing form if present
      $("#goalForm").remove();

      // Append the form to the body
      $("body").append(formHtml);

      // Add event listeners for the buttons
      $("#7days-btn").on("click", function () {
        submitGoalForm(dateStr, "7");
      });
      $("#1month-btn").on("click", function () {
        submitGoalForm(dateStr, "30");
      });
      $("#14days-btn").on("click", function () {
        submitGoalForm(dateStr, "14");
      });

      // Close the form
      $("#close-popup").on("click", function () {
        $("#goalForm").remove();
      });
    });
  }

  // Fetch authors with assigned business details
  function loadAuthors(callback) {
    $.ajax({
      url: myAjax.ajaxurl,
      method: "POST",
      data: {
        action: "get_authors_with_business_detail",
        nonce: myAjax.nonce,
      },
      success: function (response) {
        if (response.success) {
          callback(response.data);
        } else {
          alert("Error loading authors: " + response.data.error);
        }
      },
      error: function () {
        alert("Error fetching authors.");
      },
    });
  }

  function showGenerateForm(authors) {
    var authorOptions = authors
      .map(function (author) {
        return `<option value="${author.id}">${author.display_name}</option>`;
      })
      .join("");
    // Create the form HTML
    var formHtml = `
      <div id="generate365Form" style="position: absolute; top: 100px; left: 50%; transform: translateX(-50%); background: white; padding: 20px; border: 1px solid #ccc; z-index: 100;">
        <h3>Generate 365 Days Content</h3>
        <label for="start-date">Start Date:</label>
        <input type="date" id="start-date" required><br/><br/>
        <label for="author-select">Select Author:</label>
        <select id="author-select" required>
          <option value="">Select Author</option>
          ${authorOptions}
        </select><br/><br/>
        <button id="start-generation" class="button button-primary">Start</button>
        <button id="close-generate-form" class="button">Close</button>
        <div id="loading-message" style="display:none; margin-top:10px; color:blue;">Processing, please wait...</div>
      </div>
    `;

    // Remove any existing form
    $("#generate365Form").remove();

    // Append the form to the body
    $("body").append(formHtml);

    // Add event listeners
    $("#start-generation").on("click", function () {
      var startDate = $("#start-date").val();
      var selectedAuthor = $("#author-select").val();
      if (!startDate) {
        alert("Please select a start date");
        return;
      }
      if (!selectedAuthor) {
        alert("Please select an author");
        return;
      }
      processBatch365(startDate, selectedAuthor, 30, 12); // Start the batch processing for 365 days
    });

    $("#close-generate-form").on("click", function () {
      $("#generate365Form").remove(); // Close the form
    });
  }

  // Function to handle batch processing of 12 * 30 days
  function processBatch365(
    startDate,
    selectedAuthor,
    numArticles,
    remainingBatches
  ) {
    if (remainingBatches <= 0) {
      $("#loading-message").hide();
      $("#generate365Form").remove();
      calendar.refetchEvents(); // Refresh calendar
      alert("365 days content generation completed!");
      return;
    }

    $("#loading-message").show();

    // Perform the AJAX request for the current batch of 30 days
    $.ajax({
      url: myAjax.ajaxurl,
      method: "POST",
      data: {
        action: "get_calendar_preview",
        num_articles: numArticles, // 30 articles per batch
        start: startDate,
        goal: "365DaysContent", // Set a default goal for the batch content
        author_id: selectedAuthor, // Change dynamically if necessary
        nonce: myAjax.nonce,
      },
      success: function (response) {
        if (response.success) {
          var newPreviewEvents = [];
          $.each(response.data, function (index, article) {
            var eventColor = "#4caf50"; // Set a default color for batch events

            newPreviewEvents.push({
              title: article.title,
              start: article.date,
              editable: true,
              backgroundColor: eventColor,
              borderColor: eventColor,
              textColor: "#fff",
              extendedProps: {
                author_id: selectedAuthor, // Change dynamically if necessary
                goal: "365DaysContent",
                business_details: article.business_details,
              },
            });
          });

          allEvents = allEvents.concat(newPreviewEvents);
          sortAllEvents(); // Sort events before rendering them
          calendar.getEventSourceById("preview-titles").remove();
          calendar.addEventSource({
            id: "preview-titles",
            events: newPreviewEvents,
          });

          console.log("Going to render..");
          renderSortedEvents();

          // Calculate the next start date (30 days later)
          var nextStartDate = new Date(startDate);
          nextStartDate.setDate(nextStartDate.getDate() + numArticles);
          processBatch365(
            nextStartDate.toISOString().slice(0, 10),
            selectedAuthor,
            30,
            remainingBatches - 1
          );
        } else {
          alert("Error loading events: " + response.data.error);
        }
      },
      error: function () {
        alert("Error generating content.");
      },
    });
  }

  // Clear preview titles button click event

  function clearPreviewTitlesForCurrentMonth() {
    // Get the current start and end dates of the calendar's visible month
    var view = calendar.view;
    var currentMonth = view.currentStart.getMonth(); // Get the current visible month's index (0-11)
    var currentYear = view.currentStart.getFullYear(); // Get the current visible year

    // Filter the preview events to only remove events that are within the exact current month
    allEvents = allEvents.filter(function (event) {
      var eventDate;

      // Ensure that event.start is a Date object or convert it to a Date object
      if (event.start instanceof Date) {
        eventDate = event.start;
      } else {
        eventDate = new Date(event.start);
      }

      // Check if the event's year and month match the current visible month
      var eventMonth = eventDate.getMonth();
      var eventYear = eventDate.getFullYear();

      // Keep events that are not in the current visible month and year
      return !(eventMonth === currentMonth && eventYear === currentYear);
    });

    // Remove the existing preview titles source
    calendar.getEventSourceById("preview-titles").remove();

    // Add the updated preview events to the calendar
    calendar.addEventSource({
      id: "preview-titles",
      events: allEvents,
    });

    // Refresh the calendar
    calendar.refetchEvents();
    alert("Preview titles for this month have been cleared.");
  }
});

// Use event delegation to handle "All good" button clicks
