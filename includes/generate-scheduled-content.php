<?php
if (!defined('ABSPATH')) {
  exit;
}


class AI_Content_Pipelines_Oneup_QuotaExceededException extends Exception
{
}



function ai_content_pipelines_oneup_get_content_generation_progress()
{
  if (!isset($_POST['_ajax_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_ajax_nonce'])), 'my_generic_action')) {
    wp_send_json_error('Invalid nonce');
    return;
  }

  $progress = get_transient('ai_content_pipelines_oneup_content_generation_progress') ?: [];
  $is_generating = get_option('ai_content_pipelines_oneup_content_generation_in_progress', false);
  $last_timestamp = get_option('ai_content_pipelines_oneup_last_scheduled_timestamp', false);
  $is_last_error = get_option('ai_content_pipelines_oneup_is_last_error', false);
  wp_send_json_success([
    'progress' => $progress,
    'is_generating' => $is_generating,
    'last_timestamp' => $last_timestamp,
    'is_last_error' => $is_last_error,
  ]);
}

add_action('wp_ajax_get_content_generation_progress', 'ai_content_pipelines_oneup_get_content_generation_progress');

// function enqueue_toastr()
// {
//   wp_enqueue_style('toastr-css', plugin_dir_url(dirname(__FILE__)) . 'admin/css/toastr.min.css', [], '2.1.4');
//   wp_enqueue_script('toastr-js', plugin_dir_url(dirname(__FILE__)) . 'admin/js/toastr.min.js', ['jquery'], '2.1.4', true);
// }
// add_action('admin_enqueue_scripts', 'enqueue_toastr');


function ai_content_pipelines_oneup_custom_mime_types($mimes)
{
  $mimes['svg'] = 'image/svg+xml'; // Example for SVG files
  return $mimes;
}
add_filter('upload_mimes', 'ai_content_pipelines_oneup_custom_mime_types');


function ai_content_pipelines_oneup_enqueue_admin_styles()
{
  wp_enqueue_style('admin-css', plugin_dir_url(__FILE__) . 'assets/admin.css', [], '1.0.0');
}
add_action('admin_enqueue_scripts', 'ai_content_pipelines_oneup_enqueue_admin_styles');


function ai_content_pipelines_oneup_update_content_generation_progress()
{

  if (!isset($_POST['_ajax_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_ajax_nonce'])), 'my_generic_action')) {
    wp_send_json_error('Invalid nonce');
    return;
  }


  if (isset($_POST['progress'])) {
    $progress = sanitize_text_field(wp_unslash($_POST['progress']));


    // Mark all items as seen
    foreach ($progress as $item) {
      $item['isSeen'] = true;
    }

    set_transient('ai_content_pipelines_oneup_content_generation_progress', $progress, 600);
    wp_send_json_success();
  } else {
    throw new Exception('No progress data available');
  }
}
add_action('wp_ajax_update_content_generation_progress', 'ai_content_pipelines_oneup_update_content_generation_progress');


class AI_Content_Pipelines_Oneup_GenerateScheduledContent
{
  private $api_key;

  public function __construct()
  {
    $this->api_key = get_option('ai_content_pipelines_oneup_openai_api_key');
    add_action('wp_ajax_generate_full_workflow_content', [$this, 'generate_full_workflow_content']);
    add_action('wp_ajax_generate_full_workflow_content_with_previewed_titles', [$this, 'generate_full_workflow_content_with_previewed_titles']);
    add_action('wp_ajax_generate_automated_content', [$this, 'generate_automated_content']);
    add_action('wp_ajax_generate_super_automated_content', [$this, 'generate_super_automated_content']);
    add_action('wp_ajax_generate_industry_automated_content', [$this, 'generate_industry_automated_content']);
    add_action('wp_ajax_nopriv_generate_industry_automated_content', [$this, 'generate_industry_automated_content']);
    add_action('wp_ajax_find_related_posts', [$this, 'find_related_posts']);
    add_action('wp_ajax_get_preview_titles', [$this, 'handle_get_preview_titles']);
    add_action('wp_ajax_fetch_post_data', [$this, 'fetch_post_data']);
    add_action('wp_ajax_update_post_date', [$this, 'update_post_date']);
    add_action('wp_ajax_nopriv_get_preview_titles', [$this, 'handle_get_preview_titles']);
    add_action('wp_ajax_get_calendar_preview', [$this, 'get_calendar_preview']);
    add_action('wp_ajax_generate_content_in_calendar', [$this, 'generate_content_in_calendar']);
    add_action('wp_ajax_get_remaining_credits', [$this, 'get_remaining_credits_action']);
    add_action('wp_ajax_nopriv_get_remaining_credits', [$this, 'get_remaining_credits_action']);
    add_action('wp_ajax_get_domain_authority', [$this, 'get_domain_authority']);
    add_action('wp_ajax_nopriv_get_domain_authority', [$this, 'get_domain_authority']);
  }

  // AJAX handler for fetching calendar preview


  // Hook to handle AJAX request
  // If it's accessible by non-logged-in users

  function get_domain_authority()
  {
    // Verify nonce for security
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'my_generic_action')) {
      wp_send_json_error(['error' => 'Invalid nonce']);
      return;
    }

    // Get the site URL from the POST request
    $site_url = sanitize_text_field($_POST['site_url']);

    // Prepare API request
    $api_url = 'https://ai.1upmedia.com:443/get-domain-authority';

    $response = wp_remote_post($api_url, array(
      'method' => 'POST',
      'body' => json_encode(array('site_url' => $site_url)),
      'headers' => array(
        'Content-Type' => 'application/json',
      ),
    ));

    // Handle API response
    if (is_wp_error($response)) {
      wp_send_json_error('Error querying domain authority API.');
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    if (isset($data['detail']) && !empty($data['detail'])) {
      $domain_authority = $data['detail'][0]['domain_authority'];
      wp_send_json_success(array('domain_authority' => $domain_authority));
    } else {
      wp_send_json_error('Invalid response from the API.');
    }

    // Terminate script
    wp_die();
  }

  function get_calendar_preview()
  {
    check_ajax_referer('my_generic_action', 'nonce');


    $this->updateProgressToPoll("Please Wait, Titles are being generated...");
    // Accept the author ID and goal from the request
    $author_id = isset($_POST['author_id']) ? intval($_POST['author_id']) : 0;
    $goal = isset($_POST['goal']) ? sanitize_text_field($_POST['goal']) : '';

    // Get the author's business details
    $business_details = get_user_meta($author_id, '_assigned_business_detail', true);

    if (empty($business_details)) {
      wp_send_json_error(['error' => 'Business details not found for this author']);
      return;
    }

    $num_articles = intval($_POST['num_articles']);
    $start_date = $_POST['start'];

    // Prepare data for the external API request
    $api_data = [
      'author_id' => $author_id,
      'goal' => $goal,
      'business_details' => $business_details,
      'num_articles' => $num_articles,
      'start_date' => $start_date
    ];

    // Make the external API request to generate preview titles
    $response = wp_remote_post('https://ai.1upmedia.com:443/generate-preview-titles-from-business-details', [
      'body' => json_encode($api_data),
      'headers' => [
        'Content-Type' => 'application/json'
      ],
      'timeout' => 30
    ]);

    if (is_wp_error($response)) {
      wp_send_json_error(['error' => 'API request failed: ' . $response->get_error_message()]);
      return;
    }

    $response_body = wp_remote_retrieve_body($response);
    $decoded_response = json_decode($response_body, true);

    if (isset($decoded_response['error'])) {
      wp_send_json_error(['error' => $decoded_response['error']]);
      return;
    }

    // Assume the titles come as a single string with "1. title1, 2. title2, ..."
    $titles_string = isset($decoded_response['titles']) ? $decoded_response['titles'] : '';

    // Split the string by commas to get individual titles
    $titles_array = explode(',', $titles_string);
    $titles_array = array_map('trim', $titles_array); // Trim any leading/trailing spaces
    $titles_array = array_map(function ($title) {
      $title = trim($title, " \t\n\r\0\x0B\"'"); // Remove quotes, spaces, and newlines
      $title = preg_replace('/[^\w\s\-\'!?,.]/u', '', $title); // Remove unwanted special characters
      return $title;
    }, $titles_array);
    $events = [];
    $current_date = strtotime($start_date);

    foreach ($titles_array as $i => $title) {
      // Strip any numbering from the title if necessary
      $title = preg_replace('/^\d+\.\s*/', '', $title); // Removes "1. ", "2. ", etc.

      $date = date('Y-m-d', $current_date);
      $events[] = [
        'title' => $title,
        'date' => $date,
        'author_id' => $author_id,
        'goal' => $goal,
        'business_details' => $business_details
      ];
      $current_date = strtotime('+1 day', $current_date); // Increment date by 1 day
    }

    wp_send_json_success($events);
  }




  // AJAX handler for generating content

  function generate_content_in_calendar()
  {
    update_option('ai_content_pipelines_oneup_content_generation_in_progress', true);
    update_option('ai_content_pipelines_oneup_is_last_error', false);
    $this->updateProgressToPoll("Calendar content generation started. You can leave this page if you want..");

    // Verify nonce
    check_ajax_referer('my_generic_action', 'nonce');

    // Retrieve the titles_with_details array from the AJAX request
    $titles_with_details = isset($_POST['titles_with_details']) ? $_POST['titles_with_details'] : [];

    // Placeholder for the response
    $responses = [];

    // Define default parameters
    $post_status = 'publish'; // Approved post status
    $selected_content_type = 'Review'; // Fixed content type
    $admin_email = ''; // Empty admin email

    // Loop through each element in the titles_with_details array
    foreach ($titles_with_details as $event) {
      try {
        // Extract details from each event
        $generated_title = sanitize_text_field($event['title']);
        $this->updateProgressToPoll("Generating content for the title: " . $generated_title);
        $scheduled_time = strtotime(sanitize_text_field($event['date'])); // Scheduled date
        $author_id = intval($event['author_id']);
        $goal = sanitize_text_field($event['goal']);
        $business_details = sanitize_textarea_field($event['business_details']);

        // Validate the data before proceeding
        if (empty($generated_title) || empty($scheduled_time) || empty($author_id)) {
          throw new Exception('Missing required data for scheduling post.');
        }

        $goal_content = $goal;

        if ($goal === "365DaysContent") {
          // Define the content allocation for 365DaysContent
          $content_allocation = [
            'Educate Customers' => 30,  // 30% chance
            'Acquire Customers' => 25,  // 25% chance
            'Enhance Customer Experience' => 20,  // 20% chance
            'Answer FAQs' => 15,  // 15% chance
            'Differentiate from Competitors' => 10 // 10% chance
          ];

          // Create an array with entries based on percentages
          $weighted_goals = [];
          foreach ($content_allocation as $goal_name => $percentage) {
            // Add the goal name multiple times based on the percentage
            for ($i = 0; $i < $percentage; $i++) {
              $weighted_goals[] = $goal_name;
            }
          }

          // Randomly pick one goal from the weighted list
          $goal_content = $weighted_goals[array_rand($weighted_goals)];
        }

        $search_intent_allocation = [
          'Informational' => 40,  // 40% chance
          'Navigational' => 25,   // 25% chance
          'Transactional' => 20,  // 20% chance
          'Commercial' => 15      // 15% chance
        ];

        // Create a weighted list for search intents
        $weighted_search_intents = [];
        foreach ($search_intent_allocation as $intent => $percentage) {
          // Add the search intent multiple times based on the percentage
          for ($i = 0; $i < $percentage; $i++) {
            $weighted_search_intents[] = $intent;
          }
        }

        // Randomly pick one search intent from the weighted list
        $search_intent = $weighted_search_intents[array_rand($weighted_search_intents)];

        // Use the title as the prompt for content generation
        $content = $this->generate_content_from_prompt(
          "Please generate the content in well-structured HTML format based on the following information:
      
          Title: \"" . $generated_title . "\"
      
          Goal: \"" . $goal_content . "\"

          Search Intent: \"" . $search_intent . "\"
      
          Business Information: \"" . $business_details . "\"
      
          Make sure to incorporate relevant keywords from the business details for SEO optimization, aligning with the title's theme.
      
          Ensure the content is clear, engaging, and formatted properly for web display, following best SEO practices."
        );

        $this->updateProgressToPoll("Generating Categories for the title: " . $generated_title);

        $category = $this->generate_category_from_prompt($content);

        $this->updateProgressToPoll("Generating Tags for the title: " . $generated_title);

        $tags = $this->get_tags_from_prompt($content);

        $this->updateProgressToPoll("Generating Image for the title: " . $generated_title);


        // Handle image generation (using provided image info or generating a new one)
        $image_info = $this->generate_image_from_prompt($generated_title);

        // Schedule the content with the generated data
        $post_id = $this->schedule_content(
          $content,
          $scheduled_time, // Ensure scheduled time is formatted properly
          $generated_title,
          $category,
          $image_info,
          $post_status,
          $author_id,
          $selected_content_type,
          $tags,
          $admin_email
        );

        // Check if post creation was successful
        if (is_wp_error($post_id)) {
          throw new Exception('Failed to create post: ' . $post_id->get_error_message());
        }

        // Store the response for each post
        $responses[] = [
          'ID' => $post_id,
          'content' => $content,
          'category' => $category,
          'title' => $generated_title,
          'scheduled_time' => date('Y-m-d H:i:s', $scheduled_time)
        ];

        // Update progress (optional, depending on your setup)
        $this->updateProgressToPoll("Content for title '$generated_title' scheduled successfully.");

      } catch (Exception $e) {
        // Log the error (if needed) and continue with the next item
        error_log("Error scheduling content for '$generated_title': " . $e->getMessage());
        $this->updateProgressToPoll("Error scheduling content for '$generated_title': " . $e->getMessage());
        update_option('ai_content_pipelines_oneup_content_generation_in_progress', false);
        update_option('ai_content_pipelines_oneup_last_scheduled_timestamp', time());
        update_option('ai_content_pipelines_oneup_is_last_error', false);
        // Add the error to the response to inform the client
        $responses[] = [
          'title' => $generated_title,
          'error' => $e->getMessage()
        ];
        wp_send_json_error($responses, 400, 20);
      }
    }

    $this->updateProgressToPoll("Interlinking in progress....");
    $this->interlink_content();

    update_option('ai_content_pipelines_oneup_content_generation_in_progress', false);
    update_option('ai_content_pipelines_oneup_last_scheduled_timestamp', time());
    update_option('ai_content_pipelines_oneup_is_last_error', false);

    // Return the success response with the array of post IDs and other data
    wp_send_json_success($responses);
  }



  public function fetch_post_data()
  {
    // Get start and end dates from AJAX request
    $start_date = isset($_POST['start']) ? sanitize_text_field($_POST['start']) : '';
    $end_date = isset($_POST['end']) ? sanitize_text_field($_POST['end']) : '';

    // Query posts within the date range
    $args = array(
      'post_type' => 'post',
      'date_query' => array(
        array(
          'after' => $start_date,
          'before' => $end_date,
          'inclusive' => true,
        ),
      ),
      'posts_per_page' => -1
    );
    $query = new WP_Query($args);

    $posts = array();
    if ($query->have_posts()) {
      while ($query->have_posts()) {
        $query->the_post();
        $posts[] = array(
          'id' => get_the_ID(), // Add post ID to the response
          'title' => get_the_title(),
          'date' => get_the_date('Y-m-d'),
          'url' => get_permalink(),
          'edit_url' => get_edit_post_link()
        );
      }
    }

    wp_send_json_success($posts);
  }



  // Add AJAX action hook to update the post date


  function update_post_date()
  {
    // Verify nonce for security
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'my_generic_action')) {
      wp_send_json_error(['message' => 'Invalid nonce']);
      return;
    }

    // Get the post ID and new date from the AJAX request
    $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
    $new_date = isset($_POST['new_date']) ? sanitize_text_field($_POST['new_date']) : '';

    if (!$post_id || !$new_date) {
      wp_send_json_error(['message' => 'Invalid post ID or date']);
      return;
    }

    // Update the post date
    $updated_post = wp_update_post(array(
      'ID' => $post_id,
      'post_date' => $new_date . ' 00:00:00', // Set the new date
      'post_date_gmt' => get_gmt_from_date($new_date . ' 00:00:00'),
    ));

    if (is_wp_error($updated_post)) {
      wp_send_json_error(['message' => 'Failed to update post date']);
    } else {
      wp_send_json_success(['message' => 'Post date updated successfully']);
    }
  }



  public function updateProgressToPoll($progress_message)
  {

    $progress = get_transient('ai_content_pipelines_oneup_content_generation_progress') ?: [];
    $progress[] = ['message' => $progress_message, 'isSeen' => false];
    set_transient('ai_content_pipelines_oneup_content_generation_progress', $progress, 6000);
  }


  public function generate_full_workflow_content()
  {

    ini_set('memory_limit', '256M'); // Increase memory limit

    $this->updateProgressToPoll("Full workflow contents started generating...");

    if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'my_generic_action')) {
      update_option('ai_content_pipelines_oneup_content_generation_in_progress', true);
      update_option('ai_content_pipelines_oneup_is_last_error', true);
      $this->updateProgressToPoll("Nonce error");
      update_option('ai_content_pipelines_oneup_content_generation_in_progress', false);
      wp_send_json_error('Invalid nonce');
      return;
    }

    $this->updateProgressToPoll("Nonce successful");

    update_option('ai_content_pipelines_oneup_content_generation_in_progress', true);
    update_option('ai_content_pipelines_oneup_is_last_error', false);
    try {
      $title = isset($_POST['title']) ? sanitize_text_field(wp_unslash($_POST['title'])) : '';
      $goal = isset($_POST['goal']) ? sanitize_text_field(wp_unslash($_POST['goal'])) : '';
      $goal_weightage = isset($_POST['goal_weightage']) ? array_map('sanitize_text_field', wp_unslash($_POST['goal_weightage'])) : [];
      $target_audience = isset($_POST['target_audience']) ? sanitize_text_field(wp_unslash($_POST['target_audience'])) : '';
      $keywords = isset($_POST['keywords']) ? sanitize_text_field(wp_unslash($_POST['keywords'])) : '';
      $search_intent = isset($_POST['search_intent']) ? sanitize_text_field(wp_unslash($_POST['search_intent'])) : '';
      $search_intent_weightage = isset($_POST['search_intent_weightage']) ? array_map('sanitize_text_field', wp_unslash($_POST['search_intent_weightage'])) : [];
      $links = isset($_POST['link_text']) && isset($_POST['link_url']) ?
        $this->sanitize_links(
          array_map('sanitize_text_field', wp_unslash($_POST['link_text'])),
          array_map('esc_url_raw', wp_unslash($_POST['link_url']))
        ) : [];
      $word_count = isset($_POST['word_count']) ? intval(wp_unslash($_POST['word_count'])) : 0;
      $tone = isset($_POST['tone']) ? sanitize_text_field(wp_unslash($_POST['tone'])) : '';
      $content_type = isset($_POST['content_type']) ? sanitize_text_field(wp_unslash($_POST['content_type'])) : '';
      $content_type_weightage = isset($_POST['content_type_weightage']) ? array_map('sanitize_text_field', wp_unslash($_POST['content_type_weightage'])) : [];
      $number_of_pieces = isset($_POST['number_of_pieces']) ? intval(wp_unslash($_POST['number_of_pieces'])) : 0;
      $schedule = isset($_POST['schedule']) ? sanitize_text_field(wp_unslash($_POST['schedule'])) : gmdate('Y-m-d');
      $schedule_interval = isset($_POST['schedule_interval']) ? sanitize_text_field(wp_unslash($_POST['schedule_interval'])) : '';
      $post_status = isset($_POST['post_status']) ? sanitize_text_field(wp_unslash($_POST['post_status'])) : '';
      $author = isset($_POST['author']) ? sanitize_text_field(wp_unslash($_POST['author'])) : '';
      $user_weightage = isset($_POST['user_weightage']) ? array_map('sanitize_text_field', wp_unslash($_POST['user_weightage'])) : [];
      $content_strategy = isset($_POST['content_strategy']) ? sanitize_text_field(wp_unslash($_POST['content_strategy'])) : '';
      $admin_email = isset($_POST['admin_email']) ? sanitize_email(wp_unslash($_POST['admin_email'])) : '';
      $language = isset($_POST['language']) ? sanitize_text_field(wp_unslash($_POST['language'])) : 'English';

      // Get the image data
      $this->updateProgressToPoll("Getting Image data...");

      // Fetch and sanitize the image info, or set it to null if not present
      // try {

      // $image_info = !isset($_POST['image_info']) ? [] : $_POST['image_info'];

      //$this->updateProgressToPoll($image_info);

      // Ensure image_info is valid and properly structured
      // if (!empty($image_info) && is_array($image_info)) {
      //   $image_info = [
      //     'id' => isset($image_info['id']) ? intval($image_info['id']) : 0, // Attachment ID
      //     'url' => isset($image_info['url']) ? esc_url_raw($image_info['url']) : '', // URL of the image
      //     'title' => isset($image_info['title']) ? sanitize_text_field($image_info['title']) : '', // Title of the image
      //     'alt' => isset($image_info['alt']) ? sanitize_text_field($image_info['alt']) : '', // Alt text of the image
      //     'description' => 'Uploaded Image' // Default description for the image
      //   ];

      //   // Log or take action if both ID and URL are missing/invalid
      //   if ($image_info['id'] === 0 && empty($image_info['url'])) {
      //     $this->updateProgressToPoll("Image data is invalid: Missing both ID and URL.");
      //   }
      // } else {
      //   // Handle case where no image info is provided
      //   $this->updateProgressToPoll("Image not uploaded, will fetch from the AI...");
      // }


      $this->updateProgressToPoll("checking goals..");
      // Random selection logic for goals
      if ($goal === 'Random') {
        if (empty($goal_weightage)) {
          $all_goals = [
            "Generate Leads",
            "Enhance SEO Performance",
            "Establish Authority and Trust",
            "Increase Brand Awareness",
            "Foster Customer Engagement",
            "Improve Customer Education",
            "Boost Conversion Rates",
            "Nurture Leads"
          ];

          foreach ($all_goals as $g) {
            $goal_weightage[$g] = 1;
          }
        }
        $goals = array_keys($goal_weightage);
      } else {
        $goals = [$goal];
      }

      $this->updateProgressToPoll("checking search intent..");
      // Random selection logic for search intents
      if ($search_intent === 'Random') {
        if (empty($search_intent_weightage)) {
          $all_search_intents = ["Informational", "Navigational", "Commercial", "Transactional", "Local"];
          foreach ($all_search_intents as $intent) {
            $search_intent_weightage[$intent] = 1;
          }
        }
        $search_intents = array_keys($search_intent_weightage);
      } else {
        $search_intents = [$search_intent];
      }

      $this->updateProgressToPoll("checking content type..");
      // If content_type is Random and no content type weightage is provided, assign equal weightage to all content types
      if ($content_type === 'Random') {
        if (empty($content_type_weightage)) {
          // Use the keys from the content types option (which are the names of the content types)
          $content_type_weightage = array_fill_keys(array_keys(get_option('ai_content_pipelines_oneup_author_personas_content_types', [])), 1);
        }
        // Get the content type names from the weightage array keys
        $content_types = array_keys($content_type_weightage);
      } else {
        // If not 'Random', just use the selected content type
        $content_types = [$content_type];
      }

      // If author is Random and no users are selected, assign equal weightage to all users
      if ($author === 'Random') {
        if (empty($user_weightage)) {
          $all_users = get_users();
          foreach ($all_users as $user) {
            $user_weightage[$user->ID] = 1;
          }
        }
        $users = array_keys($user_weightage);
      } else {
        $users = [$author];
      }

      $responses = [];
      $failed_contents = [];
      $retries = 2;


      function get_weighted_random_item($items, $weightages)
      {
        $total_weight = array_sum($weightages);
        $random_weight = wp_rand(0, $total_weight - 1);

        foreach ($items as $item) {
          if ($random_weight < $weightages[$item]) {
            return $item;
          }
          $random_weight -= $weightages[$item];
        }

        return $items[array_rand($items)];
      }

      $generated_titles = [];
      $template_styles = $this->get_template_styles();

      for ($i = 0; $i < $number_of_pieces; $i++) {
        $scheduled_time = $this->calculate_scheduled_time($schedule, $schedule_interval, $i);

        if ($content_type === 'Random') {
          $selected_content_type = get_weighted_random_item($content_types, $content_type_weightage);
        } else {
          $selected_content_type = $content_type;
        }

        if ($goal === 'Random') {
          $selected_goal = get_weighted_random_item($goals, $goal_weightage);
        } else {
          $selected_goal = $goal;
        }

        if ($search_intent === 'Random') {
          $selected_search_intent = get_weighted_random_item($search_intents, $search_intent_weightage);
        } else {
          $selected_search_intent = $search_intent;
        }

        if ($author === 'Random') {
          $author_id = get_weighted_random_item($users, $user_weightage);
        } else {
          $author_id = intval($author);
        }

        if (!$author_id) {
          throw new Exception('No valid author ID selected');
        }

        try {
          $content_type_template = isset($template_styles[$selected_content_type]) ? $template_styles[$selected_content_type] : '';
          $prompt = $this->build_full_workflow_prompt($title, $selected_goal, $target_audience, $keywords, $selected_search_intent, $links, $word_count, $tone, $selected_content_type, $content_strategy, $content_type_template, $language);
          $this->updateProgressToPoll("Generating content $i of $number_of_pieces...");
          $content = $this->generate_content_from_prompt($prompt);
          $this->updateProgressToPoll("Generating category $i of $number_of_pieces...");
          $category = $this->generate_category_from_prompt($content);
          $this->updateProgressToPoll("Generating title $i of $number_of_pieces...");
          $generated_title = $this->generate_title_from_prompt($content, $generated_titles, $content_strategy, $language);
          $generated_titles[] = $generated_title;
          $this->updateProgressToPoll("Generating image $i of $number_of_pieces...");
          $image_info = $this->generate_image_from_prompt($generated_title);
          $this->updateProgressToPoll("Generating title $i of $number_of_pieces...");
          $tags = $this->get_tags_from_prompt($content);
          $post_id = $this->schedule_content($content, $scheduled_time, $generated_title, $category, $image_info, $post_status, $author_id, $selected_content_type, $tags, $admin_email);

          // Generate vector representation of the content

          $responses[] = ['ID' => $post_id, 'content' => $content, 'category' => $category];
          $this->updateProgressToPoll("Content $i scheduled successfully.");
        } catch (AI_Content_Pipelines_Oneup_QuotaExceededException $e) {
          update_option('ai_content_pipelines_oneup_content_generation_in_progress', false);
          update_option('ai_content_pipelines_oneup_last_scheduled_timestamp', time());
          update_option('ai_content_pipelines_oneup_is_last_error', true);
          wp_send_json_error(['type' => 'QuotaExceeded', 'message' => $e->getMessage()]);
          return;
        } catch (Exception $e) {
          $failed_contents[] = [
            'prompt' => $prompt,
            'scheduled_time' => $scheduled_time,
            'retries' => $retries,
            'post_status' => $post_status,
            'author' => $author_id
          ];
          $this->updateProgressToPoll("Content $i failed to schedule, retrying..." . $e->getMessage());
        }
      }

      $failed_count = 0;

      // Retry failed contents
      while (!empty($failed_contents) && $retries > 0) {
        foreach ($failed_contents as $key => $failed_content) {
          try {
            $content = $this->generate_content_from_prompt($failed_content['prompt']);
            $category = $this->generate_category_from_prompt($content);
            $generated_title = $this->generate_title_from_prompt($content, $generated_titles, $content_strategy, $language);
            $generated_titles[] = $generated_title;
            $image_info = $this->generate_image_from_prompt($generated_title);
            $tags = $this->get_tags_from_prompt($content);
            $post_id = $this->schedule_content($content, $failed_content['scheduled_time'], $generated_title, $category, $image_info, $failed_content['post_status'], $failed_content['author'], $selected_content_type, $tags, $admin_email);

            // Generate vector representation of the content

            $responses[] = ['ID' => $post_id, 'content' => $content, 'category' => $category];
            unset($failed_contents[$key]);
            $this->updateProgressToPoll("Failed content $key retried successfully.");
          } catch (Exception $e) {
            $this->updateProgressToPoll("Content $i failed to schedule, retrying..." . $e->getMessage());
            $failed_contents[$key]['retries']--;
            if ($failed_contents[$key]['retries'] <= 0) {
              $failed_count += 1;
              unset($failed_contents[$key]);
              $this->updateProgressToPoll("Failed content $key could not be scheduled.");
            }
          }
        }
        $retries--;
      }

      // Interlink the content
      $this->updateProgressToPoll("Interlinking in progress....");
      $this->interlink_content();
      if ($failed_count > 0) {
        $this->updateProgressToPoll("Few contents were unsuccessful");
        update_option('ai_content_pipelines_oneup_content_generation_in_progress', false);
        update_option('ai_content_pipelines_oneup_last_scheduled_timestamp', time());
        update_option('ai_content_pipelines_oneup_is_last_error', true);
        wp_send_json_error(['message' => $failed_content . 'contents could not be scheduled.', 'responses' => $responses, 'failed_contents' => $failed_contents]);
      } else {
        $this->updateProgressToPoll("Task completed successfully");
        update_option('ai_content_pipelines_oneup_content_generation_in_progress', false);
        update_option('ai_content_pipelines_oneup_is_last_error', false);
        update_option('ai_content_pipelines_oneup_last_scheduled_timestamp', time());
        wp_send_json_success(['message' => 'Content scheduled successfully', 'responses' => $responses]);
      }
    } catch (Exception $e) {
      $this->updateProgressToPoll("Few contents were unsuccessful" . $e->getMessage());
      update_option('ai_content_pipelines_oneup_content_generation_in_progress', false);
      update_option('ai_content_pipelines_oneup_last_scheduled_timestamp', time());
      update_option('ai_content_pipelines_oneup_is_last_error', true);
      wp_send_json_error(['message' => $e->getMessage()]);
    }
  }


  public function generate_automated_content()
  {
    ini_set('memory_limit', '256M'); // Increase memory limit

    if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'my_generic_action')) {
      update_option('ai_content_pipelines_oneup_content_generation_in_progress', true);
      update_option('ai_content_pipelines_oneup_is_last_error', true);
      $this->updateProgressToPoll("Nonce error");
      wp_send_json_error('Invalid nonce');
      return;
    }

    $this->updateProgressToPoll("Started generating Semi-automated contents.....");


    update_option('ai_content_pipelines_oneup_content_generation_in_progress', true);
    update_option('ai_content_pipelines_oneup_is_last_error', false);
    try {
      $topic = isset($_POST['topic']) ? sanitize_text_field(wp_unslash($_POST['topic'])) : '';
      $template_style = isset($_POST['template_style']) ? sanitize_text_field(wp_unslash($_POST['template_style'])) : '';
      $number_of_pieces = isset($_POST['number_of_pieces_auto']) ? intval(wp_unslash($_POST['number_of_pieces_auto'])) : 0;
      $schedule = isset($_POST['schedule_auto']) ? sanitize_text_field(wp_unslash($_POST['schedule_auto'])) : gmdate('Y-m-d');
      $schedule_interval = isset($_POST['schedule_interval_auto']) ? sanitize_text_field(wp_unslash($_POST['schedule_interval_auto'])) : '';
      $post_status = isset($_POST['post_status_auto']) ? sanitize_text_field(wp_unslash($_POST['post_status_auto'])) : '';
      $author = isset($_POST['author_auto']) ? sanitize_text_field(wp_unslash($_POST['author_auto'])) : '';
      $user_weightage = isset($_POST['user_weightage']) ? array_map('sanitize_text_field', wp_unslash($_POST['user_weightage'])) : [];
      $template_weightage = isset($_POST['template_weightage']) ? array_map('sanitize_text_field', wp_unslash($_POST['template_weightage'])) : [];
      $link_texts = isset($_POST['link_text_auto']) ? array_map('sanitize_text_field', wp_unslash($_POST['link_text_auto'])) : [];
      $link_urls = isset($_POST['link_url_auto']) ? array_map('esc_url_raw', wp_unslash($_POST['link_url_auto'])) : [];
      $goal = isset($_POST['goal']) ? sanitize_text_field(wp_unslash($_POST['goal'])) : '';
      $goal_weightage = [];
      $search_intent = isset($_POST['search_intent']) ? sanitize_text_field(wp_unslash($_POST['search_intent'])) : '';
      $search_intent_weightage = [];
      $content_strategy = isset($_POST['content_strategy']) ? sanitize_text_field(wp_unslash($_POST['content_strategy'])) : '';
      $admin_email = isset($_POST['admin_email']) ? sanitize_email(wp_unslash($_POST['admin_email'])) : '';
      $language = isset($_POST['language']) ? sanitize_text_field(wp_unslash($_POST['language'])) : 'English';

      $this->updateProgressToPoll("checking goals..");
      if ($goal === 'Random') {
        if (empty($goal_weightage)) {
          $all_goals = ["Generate Leads", "Thought Leadership", "Brand Awareness", "Customer Education"];
          foreach ($all_goals as $g) {
            $goal_weightage[$g] = 1;
          }
        }
        $goals = array_keys($goal_weightage);
      } else {
        $goals = [$goal];
      }

      $this->updateProgressToPoll("checking search intent..");
      // Random selection logic for search intents
      if ($search_intent === 'Random') {
        if (empty($search_intent_weightage)) {
          $all_search_intents = ["Informational", "Navigational", "Commercial", "Transactional", "Local"];
          foreach ($all_search_intents as $intent) {
            $search_intent_weightage[$intent] = 1;
          }
        }
        $search_intents = array_keys($search_intent_weightage);
      } else {
        $search_intents = [$search_intent];
      }

      $this->updateProgressToPoll("checking Author..");
      // If author is Random and no users are selected, assign equal weightage to all users
      if ($author === 'Random') {
        if (empty($user_weightage)) {
          $all_users = get_users();
          foreach ($all_users as $user) {
            $user_weightage[$user->ID] = 1;
          }
        }
        $users = array_keys($user_weightage);
      } else {
        $users = [$author];
      }

      $this->updateProgressToPoll("checking template..");
      // If template_style is Random and no template weightage is provided, assign equal weightage to all templates
      if ($template_style === 'Random') {
        if (empty($template_weightage)) {
          $all_templates = $this->get_template_styles();
          foreach ($all_templates as $key => $value) {
            $template_weightage[$key] = 1;
          }
        }
        $template_styles = array_keys($template_weightage);
      } else {
        $template_styles = [$template_style];
      }

      $responses = [];
      $failed_contents = [];
      $retries = 2;

      function get_weighted_random_item($items, $weightages)
      {
        $total_weight = array_sum($weightages);
        $random_weight = wp_rand(0, $total_weight - 1);

        foreach ($items as $item) {
          if ($random_weight < $weightages[$item]) {
            return $item;
          }
          $random_weight -= $weightages[$item];
        }

        return $items[array_rand($items)];
      }


      $generated_titles = [];
      $template_styles_for_prompt = $this->get_template_styles();

      for ($i = 0; $i < $number_of_pieces; $i++) {

        $j = $i + 1;

        $scheduled_time = $this->calculate_scheduled_time($schedule, $schedule_interval, $i);

        if ($template_style === 'Random') {
          $selected_template_style = get_weighted_random_item($template_styles, $template_weightage);
        } else {
          $selected_template_style = $template_style;
        }

        $this->updateProgressToPoll("template: " . $selected_template_style);
        if ($author === 'Random') {
          $author_id = get_weighted_random_item($users, $user_weightage);
        } else {
          $author_id = intval($author);
        }

        if (!$author_id) {
          throw new Exception('No valid author ID selected');
        }

        $this->updateProgressToPoll("Author: " . $author_id);


        if ($goal === 'Random') {
          $selected_goal = get_weighted_random_item($goals, $goal_weightage);
        } else {
          $selected_goal = $goal;
        }

        $this->updateProgressToPoll("goal: " . $selected_goal);


        if ($search_intent === 'Random') {
          $selected_search_intent = get_weighted_random_item($search_intents, $search_intent_weightage);
        } else {
          $selected_search_intent = $search_intent;
        }

        $this->updateProgressToPoll("search intent: " . $search_intent);
        try {

          $content_type_template = isset($template_styles_for_prompt[$selected_template_style]) ? $template_styles_for_prompt[$selected_template_style] : '';
          $this->updateProgressToPoll("Generating Content {$j}...");
          $links = $this->sanitize_links($link_texts, $link_urls);
          $prompt = $this->build_automated_prompt($topic, $selected_template_style, $links, $selected_goal, $selected_search_intent, $content_strategy, $content_type_template, $language);
          $content = $this->generate_content_from_prompt($prompt);
          $this->updateProgressToPoll("fetching categories for content {$j}...");
          $category = $this->generate_category_from_prompt($content);
          $this->updateProgressToPoll("fetching title for content {$j}...");
          $generated_title = $this->generate_title_from_prompt($content, $generated_titles, $content_strategy, $language);
          $generated_titles[] = $generated_title;
          $this->updateProgressToPoll("creating image for content {$j}...");
          $image_info = $this->generate_image_from_prompt($generated_title);
          $tags = $this->get_tags_from_prompt($content);
          $post_id = $this->schedule_content($content, $scheduled_time, $generated_title, $category, $image_info, $post_status, $author_id, $selected_template_style, $tags, $admin_email);

          // Generate vector representation of the content

          $responses[] = ['ID' => $post_id, 'content' => $content, 'category' => $category, 'template_style' => $selected_template_style, 'user' => $author_id];
          // Log progress
          $this->updateProgressToPoll("Content {$j} scheduled successfully");
        } catch (AI_Content_Pipelines_Oneup_QuotaExceededException $e) {
          update_option('ai_content_pipelines_oneup_content_generation_in_progress', false);
          update_option('ai_content_pipelines_oneup_is_last_error', true);
          update_option('ai_content_pipelines_oneup_last_scheduled_timestamp', time());
          wp_send_json_error(['type' => 'QuotaExceeded', 'message' => $e->getMessage()]);
          return;
        } catch (Exception $e) {
          $this->updateProgressToPoll("Content {$j} failed, will be retried later..{$template_style}..{$e->getMessage()}");
          $failed_contents[] = ['topic' => $topic, 'template_style' => $selected_template_style, 'scheduled_time' => $scheduled_time, 'retries' => $retries, 'selected_goal' => $selected_goal, 'selected_search_intent' => $selected_search_intent];
        }
      }

      $failed_count = 0;
      // Retry failed contents
      for ($j = 0; $j < $retries; $j++) {
        foreach ($failed_contents as $key => $failed_content) {
          try {
            $links = $this->sanitize_links($link_texts, $link_urls);
            $prompt = $this->build_automated_prompt($failed_content['topic'], $failed_content['template_style'], $links, $failed_content['selected_goal'], $failed_content['selected_search_intent'], $content_strategy, $language);
            $content = $this->generate_content_from_prompt($prompt);
            $category = $this->generate_category_from_prompt($content);
            $generated_title = $this->generate_title_from_prompt($content, $generated_titles, $content_strategy, $language);
            $generated_titles[] = $generated_title;
            $image_info = $this->generate_image_from_prompt($generated_title);
            $tags = $this->get_tags_from_prompt($content);
            $post_id = $this->schedule_content($content, $failed_content['scheduled_time'], $generated_title, $category, $image_info, $post_status, $author_id, $selected_template_style, $tags, $admin_email);

            // Generate vector representation of the content

            $responses[] = ['ID' => $post_id, 'content' => $content, 'category' => $category, 'template' => $template_style];
            $this->updateProgressToPoll("Content $i scheduled successfully.");
            unset($failed_contents[$key]);
          } catch (Exception $e) {
            if ($failed_content['retries'] > 0) {
              $failed_contents[$key]['retries']--;
            } else {
              $failed_count += 1;
              unset($failed_contents[$key]);
            }
          }
        }
      }

      $this->updateProgressToPoll("Interlinking in progress....");
      $this->interlink_content();
      $this->updateProgressToPoll("Interlinking in progress....");

      if ($failed_count > 0) {
        $this->updateProgressToPoll("Few contents were unsuccessful");
        update_option('ai_content_pipelines_oneup_content_generation_in_progress', false);
        update_option('ai_content_pipelines_oneup_is_last_error', true);
        update_option('ai_content_pipelines_oneup_last_scheduled_timestamp', time());
        wp_send_json_error(['message' => $failed_count . 'Some content could not be scheduled.', 'responses' => $responses, 'failed_contents' => $failed_contents]);
      } else {
        $this->updateProgressToPoll("Generation Successful");
        update_option('ai_content_pipelines_oneup_content_generation_in_progress', false);
        update_option('ai_content_pipelines_oneup_is_last_error', false);
        update_option('ai_content_pipelines_oneup_last_scheduled_timestamp', time());
        wp_send_json_success(['message' => 'Content scheduled successfully', 'responses' => $responses]);
      }
    } catch (Exception $e) {
      $this->updateProgressToPoll("Few contents were unsuccessful" . $e->getMessage());
      update_option('ai_content_pipelines_oneup_content_generation_in_progress', false);
      update_option('ai_content_pipelines_oneup_is_last_error', true);
      update_option('ai_content_pipelines_oneup_last_scheduled_timestamp', time());
      wp_send_json_error(['message' => $e->getMessage(), 'responses so far' => $responses]);
    }
  }


  public function generate_super_automated_content()
  {
    ini_set('memory_limit', '256M'); // Increase memory limit

    if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'my_generic_action')) {
      wp_send_json_error('Invalid nonce');
      update_option('ai_content_pipelines_oneup_content_generation_in_progress', false);
      update_option('ai_content_pipelines_oneup_is_last_error', true);
      $this->updateProgressToPoll("Nonce error");
      return;
    }

    update_option('ai_content_pipelines_oneup_content_generation_in_progress', true);
    update_option('ai_content_pipelines_oneup_is_last_error', false);
    try {
      $topic = isset($_POST['topic']) ? sanitize_text_field(wp_unslash($_POST['topic'])) : '';
      $buyers_journey = isset($_POST['buyers_journey']) ? sanitize_text_field(wp_unslash($_POST['buyers_journey'])) : '';
      $number_of_pieces = isset($_POST['number_of_pieces_super']) ? intval(wp_unslash($_POST['number_of_pieces_super'])) : 0;
      $schedule = isset($_POST['schedule_super']) ? sanitize_text_field(wp_unslash($_POST['schedule_super'])) : gmdate('Y-m-d');
      $schedule_interval = isset($_POST['schedule_interval_super']) ? sanitize_text_field(wp_unslash($_POST['schedule_interval_super'])) : '';
      $post_status = isset($_POST['post_status_super']) ? sanitize_text_field(wp_unslash($_POST['post_status_super'])) : '';
      $author = isset($_POST['author_super']) ? sanitize_text_field(wp_unslash($_POST['author_super'])) : '';
      $user_weightage = isset($_POST['user_weightage']) ? array_map('sanitize_text_field', wp_unslash($_POST['user_weightage'])) : [];
      $link_texts = isset($_POST['link_text_super']) ? array_map('sanitize_text_field', wp_unslash($_POST['link_text_super'])) : [];
      $link_urls = isset($_POST['link_url_super']) ? array_map('esc_url_raw', wp_unslash($_POST['link_url_super'])) : [];
      $search_intent = isset($_POST['search_intent']) ? sanitize_text_field(wp_unslash($_POST['search_intent'])) : '';
      $search_intent_weightage = isset($_POST['search_intent_weightage']) ? array_map('sanitize_text_field', wp_unslash($_POST['search_intent_weightage'])) : [];
      $goal = isset($_POST['goal']) ? sanitize_text_field(wp_unslash($_POST['goal'])) : '';
      $goal_weightage = isset($_POST['goal_weightage']) ? array_map('sanitize_text_field', wp_unslash($_POST['goal_weightage'])) : [];
      $admin_email = isset($_POST['admin_email']) ? sanitize_email(wp_unslash($_POST['admin_email'])) : '';
      $language = isset($_POST['language']) ? sanitize_text_field(wp_unslash($_POST['language'])) : 'English';


      if ($goal === 'Random') {
        if (empty($goal_weightage)) {
          $all_goals = ["Generate Leads", "Thought Leadership", "Brand Awareness", "Customer Education"];
          foreach ($all_goals as $g) {
            $goal_weightage[$g] = 1;
          }
        }
        $goals = array_keys($goal_weightage);
      } else {
        $goals = [$goal];
      }

      // Random selection logic for search intents
      if ($search_intent === 'Random') {
        if (empty($search_intent_weightage)) {
          $all_search_intents = ["Informational", "Navigational", "Commercial", "Transactional", "Local"];
          foreach ($all_search_intents as $intent) {
            $search_intent_weightage[$intent] = 1;
          }
        }
        $search_intents = array_keys($search_intent_weightage);
      } else {
        $search_intents = [$search_intent];
      }


      if ($buyers_journey === 'Full Journey') {
        $number_of_pieces = ceil($number_of_pieces / 3) * 3;// Ensure it's a multiple of 3
        $buyers_journey_stages = ['Awareness', 'Consideration', 'Decision'];
      } else {
        $buyers_journey_stages = [$buyers_journey];
      }
      // If author is Random and no users are selected, assign equal weightage to all users
      if ($author === 'Random') {
        if (empty($user_weightage)) {
          $all_users = get_users();
          foreach ($all_users as $user) {
            $user_weightage[$user->ID] = 1;
          }
        }
        $users = array_keys($user_weightage);
      } else {
        $users = [$author];
      }

      $responses = [];
      $failed_contents = [];
      $retries = 2;

      function get_weighted_random_item($items, $weightages)
      {
        $total_weight = array_sum($weightages);
        $random_weight = wp_rand(0, $total_weight - 1);

        foreach ($items as $item) {
          if ($random_weight < $weightages[$item]) {
            return $item;
          }
          $random_weight -= $weightages[$item];
        }

        return $items[array_rand($items)];
      }

      $generated_titles = [];

      $template_styles = $this->get_template_styles();

      for ($i = 0; $i < $number_of_pieces; $i++) {

        $current_journey_stage = $buyers_journey_stages[$i % count($buyers_journey_stages)];

        $j = $i + 1;

        $scheduled_time = $this->calculate_scheduled_time($schedule, $schedule_interval, $i);

        if ($author === 'Random') {
          $author_id = get_weighted_random_item($users, $user_weightage);
        } else {
          $author_id = intval($author);
        }

        if (!$author_id) {
          throw new Exception('No valid author ID selected');
        }

        if ($goal === 'Random') {
          $selected_goal = get_weighted_random_item($goals, $goal_weightage);
        } else {
          $selected_goal = $goal;
        }

        if ($search_intent === 'Random') {
          $selected_search_intent = get_weighted_random_item($search_intents, $search_intent_weightage);
        } else {
          $selected_search_intent = $search_intent;
        }

        try {

          $content_type_template = isset($template_styles[$$current_journey_stage]) ? $template_styles[$$current_journey_stage] : '';
          $this->updateProgressToPoll("Generating Content {$j}...");
          $links = $this->sanitize_links($link_texts, $link_urls);
          $prompt = $this->build_super_automated_prompt($topic, $current_journey_stage, $links, $generated_titles, $selected_goal, $selected_search_intent, $content_type_template, $language);
          $content = $this->generate_content_from_prompt($prompt);
          $this->updateProgressToPoll("fetching categories for content {$j}...");
          $category = $this->generate_category_from_prompt($content);
          $this->updateProgressToPoll("fetching title for content {$j}...");
          $generated_title = $this->generate_title_from_prompt($content, $generated_titles, $current_journey_stage, $language);
          $generated_titles[] = $generated_title;
          $this->updateProgressToPoll("creating image for content {$j}...");
          $image_info = $this->generate_image_from_prompt($generated_title);
          $tags = $this->get_tags_from_prompt($content);
          $post_id = $this->schedule_content($content, $scheduled_time, $generated_title, $category, $image_info, $post_status, $author_id, $current_journey_stage, $tags, $admin_email);

          // Log progress
          $this->updateProgressToPoll("Content {$j} scheduled successfully");

          $responses[] = ['ID' => $post_id, 'content' => $content, 'category' => $category, 'buyers_journey' => $current_journey_stage, 'user' => $author_id];
        } catch (AI_Content_Pipelines_Oneup_QuotaExceededException $e) {
          update_option('ai_content_pipelines_oneup_content_generation_in_progress', false);
          update_option('ai_content_pipelines_oneup_is_last_error', true);
          update_option('ai_content_pipelines_oneup_last_scheduled_timestamp', time());
          wp_send_json_error(['type' => 'QuotaExceeded', 'message' => $e->getMessage()]);
          return;
        } catch (Exception $e) {
          $failed_contents[] = ['topic' => $topic, 'buyers_journey' => $current_journey_stage, 'scheduled_time' => $scheduled_time, 'retries' => $retries, 'selected_goal' => $selected_goal, 'selected_search_intent' => $selected_search_intent, 'content_type_template' => $content_type_template];
        }
      }

      $failed_count = 0;

      // Retry failed contents
      for ($j = 0; $j < $retries; $j++) {
        foreach ($failed_contents as $key => $failed_content) {
          try {
            $links = $this->sanitize_links($link_texts, $link_urls);
            $prompt = $this->build_super_automated_prompt($failed_content['topic'], $failed_content['buyers_journey'], $links, $generated_titles, $failed_content['selected_goal'], $failed_content['selected_goal'], $failed_content['content_type_template'], $language);
            $content = $this->generate_content_from_prompt($prompt);
            $category = $this->generate_category_from_prompt($content);
            $generated_title = $this->generate_title_from_prompt($content, $generated_titles, $failed_content['buyers_journey'], $language);
            $generated_titles[] = $generated_title;
            $image_info = $this->generate_image_from_prompt($generated_title);
            $tags = $this->get_tags_from_prompt($content);
            $post_id = $this->schedule_content($content, $failed_content['scheduled_time'], $generated_title, $category, $image_info, $post_status, $author_id, $failed_content['buyers_journey'], $tags, $admin_email);

            // Log progress

            $this->updateProgressToPoll("Failed content {$key} retried successfully.");
            $responses[] = ['ID' => $post_id, 'content' => $content, 'category' => $category, 'buyers_journey' => $buyers_journey];
            unset($failed_contents[$key]);
          } catch (Exception $e) {
            if ($failed_content['retries'] > 0) {
              $failed_contents[$key]['retries']--;
            } else {
              $failed_count += 1;
              unset($failed_contents[$key]);
            }
          }
        }
      }

      $this->updateProgressToPoll("Interlinking in progress....");
      $this->interlink_content();

      if ($failed_count > 0) {
        $this->updateProgressToPoll("Some contents failed...");
        update_option('ai_content_pipelines_oneup_content_generation_in_progress', false);
        update_option('ai_content_pipelines_oneup_is_last_error', true);
        update_option('ai_content_pipelines_oneup_last_scheduled_timestamp', time());
        wp_send_json_error(['message' => 'Some content could not be scheduled.', 'responses' => $responses, 'failed_contents' => $failed_contents]);
      } else {
        $this->updateProgressToPoll("Generation Successful");
        update_option('ai_content_pipelines_oneup_content_generation_in_progress', false);
        update_option('ai_content_pipelines_oneup_is_last_error', false);
        update_option('ai_content_pipelines_oneup_last_scheduled_timestamp', time());
        wp_send_json_success(['message' => 'Content scheduled successfully', 'responses' => $responses]);
      }
    } catch (Exception $e) {
      $this->updateProgressToPoll("Some contents failed..." . $e->getMessage());
      update_option('ai_content_pipelines_oneup_content_generation_in_progress', false);
      update_option('ai_content_pipelines_oneup_is_last_error', true);
      update_option('ai_content_pipelines_oneup_last_scheduled_timestamp', time());
      wp_send_json_error(['message' => $e->getMessage(), 'responses so far' => $responses]);
    }
    $this->updateProgressToPoll("Task completed..");
    update_option('ai_content_pipelines_oneup_content_generation_in_progress', false);
    update_option('ai_content_pipelines_oneup_is_last_error', false);
    update_option('ai_content_pipelines_oneup_last_scheduled_timestamp', time());
  }

  public function generate_industry_automated_content()
  {
    ini_set('memory_limit', '256M'); // Increase memory limit
    $this->updateProgressToPoll("Industry content stared...");
    if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'my_generic_action')) {
      wp_send_json_error('Invalid nonce');
      update_option('ai_content_pipelines_oneup_content_generation_in_progress', false);
      update_option('ai_content_pipelines_oneup_is_last_error', true);
      $this->updateProgressToPoll("Nonce error");
      return;
    }


    update_option('ai_content_pipelines_oneup_content_generation_in_progress', true);
    update_option('ai_content_pipelines_oneup_is_last_error', false);
    try {
      $topic = isset($_POST['topic']) ? sanitize_text_field(wp_unslash($_POST['topic'])) : '';
      $buyers_journey = isset($_POST['buyers_journey']) ? sanitize_text_field(wp_unslash($_POST['buyers_journey'])) : '';
      $number_of_pieces = isset($_POST['number_of_pieces_industry']) ? intval(wp_unslash($_POST['number_of_pieces_industry'])) : 0;
      $schedule = isset($_POST['schedule_']) ? sanitize_text_field(wp_unslash($_POST['schedule_industry'])) : gmdate('Y-m-d');
      $schedule_interval = isset($_POST['schedule_interval_industry']) ? sanitize_text_field(wp_unslash($_POST['schedule_interval_industry'])) : '';
      $post_status = isset($_POST['post_status_industry']) ? sanitize_text_field(wp_unslash($_POST['post_status_industry'])) : '';
      $author = isset($_POST['author_industry']) ? sanitize_text_field(wp_unslash($_POST['author_industry'])) : '';
      $user_weightage = isset($_POST['user_weightage']) ? array_map('sanitize_text_field', wp_unslash($_POST['user_weightage'])) : [];
      $link_texts = isset($_POST['link_text_industry']) ? array_map('sanitize_text_field', wp_unslash($_POST['link_text_industry'])) : [];
      $link_urls = isset($_POST['link_url_industry']) ? array_map('esc_url_raw', wp_unslash($_POST['link_url_industry'])) : [];
      $search_intent = isset($_POST['search_intent']) ? sanitize_text_field(wp_unslash($_POST['search_intent'])) : '';
      $search_intent_weightage = isset($_POST['search_intent_weightage']) ? array_map('sanitize_text_field', wp_unslash($_POST['search_intent_weightage'])) : [];
      $goal = isset($_POST['goal']) ? sanitize_text_field(wp_unslash($_POST['goal'])) : '';
      $goal_weightage = isset($_POST['goal_weightage']) ? array_map('sanitize_text_field', wp_unslash($_POST['goal_weightage'])) : [];
      $admin_email = isset($_POST['admin_email']) ? sanitize_email(wp_unslash($_POST['admin_email'])) : '';
      $language = isset($_POST['language']) ? sanitize_text_field(wp_unslash($_POST['language'])) : 'English';
      $this->updateProgressToPoll("Language is set to: " . $language);

      if ($goal === 'Random') {
        if (empty($goal_weightage)) {
          $all_goals = ["Generate Leads", "Thought Leadership", "Brand Awareness", "Customer Education"];
          foreach ($all_goals as $g) {
            $goal_weightage[$g] = 1;
          }
        }
        $goals = array_keys($goal_weightage);
      } else {
        $goals = [$goal];
      }

      // Random selection logic for search intents
      if ($search_intent === 'Random') {
        if (empty($search_intent_weightage)) {
          $all_search_intents = ["Informational", "Navigational", "Commercial", "Transactional", "Local"];
          foreach ($all_search_intents as $intent) {
            $search_intent_weightage[$intent] = 1;
          }
        }
        $search_intents = array_keys($search_intent_weightage);
      } else {
        $search_intents = [$search_intent];
      }


      if ($buyers_journey === 'Full Journey') {
        //$number_of_pieces = ceil($number_of_pieces / 3) * 3;// Ensure it's a multiple of 3
        $buyers_journey_stages = ['Awareness', 'Consideration', 'Decision'];
      } else {
        $buyers_journey_stages = [$buyers_journey];
      }
      // If author is Random and no users are selected, assign equal weightage to all users
      if ($author === 'Random') {
        if (empty($user_weightage)) {
          $all_users = get_users();
          foreach ($all_users as $user) {
            $user_weightage[$user->ID] = 1;
          }
        }
        $users = array_keys($user_weightage);
      } else {
        $users = [$author];
      }

      $responses = [];
      $failed_contents = [];
      $retries = 2;

      function get_weighted_random_item($items, $weightages)
      {
        $total_weight = array_sum($weightages);
        $random_weight = wp_rand(0, $total_weight - 1);

        foreach ($items as $item) {
          if ($random_weight < $weightages[$item]) {
            return $item;
          }
          $random_weight -= $weightages[$item];
        }

        return $items[array_rand($items)];
      }

      $generated_titles = [];

      $template_styles = $this->get_template_styles();

      for ($i = 0; $i < $number_of_pieces; $i++) {

        $current_journey_stage = $buyers_journey_stages[$i % count($buyers_journey_stages)];

        $j = $i + 1;

        $scheduled_time = $this->calculate_scheduled_time($schedule, $schedule_interval, $i);

        if ($author === 'Random') {
          $author_id = get_weighted_random_item($users, $user_weightage);
        } else {
          $author_id = intval($author);
        }

        if (!$author_id) {
          throw new Exception('No valid author ID selected');
        }

        if ($goal === 'Random') {
          $selected_goal = get_weighted_random_item($goals, $goal_weightage);
        } else {
          $selected_goal = $goal;
        }

        if ($search_intent === 'Random') {
          $selected_search_intent = get_weighted_random_item($search_intents, $search_intent_weightage);
        } else {
          $selected_search_intent = $search_intent;
        }



        try {
          $user_location = get_user_meta($author_id, '_assigned_location', 'Global');
          $user_industry = get_user_meta($author_id, '_assigned_industry', 'General');
          $content_type_template = isset($template_styles[$$current_journey_stage]) ? $template_styles[$$current_journey_stage] : '';
          $this->updateProgressToPoll("Generating Content {$j}...");
          $links = $this->sanitize_links($link_texts, $link_urls);
          $prompt = $this->build_super_automated_prompt($topic . "Industry: " . $user_industry . 'Location: ' . $user_location . " IMPORTANT! -> Please generate the content in" . $language . "Language.", $current_journey_stage, $links, $generated_titles, $selected_goal, $selected_search_intent, $content_type_template);
          // $this->updateProgressToPoll($prompt);
          $content = $this->generate_content_from_prompt($prompt);
          $this->updateProgressToPoll("fetching categories for content {$j}...");
          $category = $this->generate_category_from_prompt($content);
          $this->updateProgressToPoll("fetching title for content {$j}...");
          $generated_title = $this->generate_title_from_prompt($content, $generated_titles, null, $language);
          $generated_titles[] = $generated_title;
          $this->updateProgressToPoll("creating image for content {$j}...");
          $image_info = $this->generate_image_from_prompt($generated_title);
          $tags = $this->get_tags_from_prompt($content);
          $post_id = $this->schedule_content($content, $scheduled_time, $generated_title, $category, $image_info, $post_status, $author_id, $current_journey_stage, $tags, $admin_email);

          // Log progress
          $this->updateProgressToPoll("Content {$j} scheduled successfully");

          $responses[] = ['ID' => $post_id, 'content' => $content, 'category' => $category, 'buyers_journey' => $current_journey_stage, 'user' => $author_id];
        } catch (AI_Content_Pipelines_Oneup_QuotaExceededException $e) {
          update_option('ai_content_pipelines_oneup_content_generation_in_progress', false);
          update_option('ai_content_pipelines_oneup_is_last_error', true);
          update_option('ai_content_pipelines_oneup_last_scheduled_timestamp', time());
          wp_send_json_error(['type' => 'QuotaExceeded', 'message' => $e->getMessage()]);
          return;
        } catch (Exception $e) {
          $failed_contents[] = ['topic' => $topic, 'buyers_journey' => $current_journey_stage, 'scheduled_time' => $scheduled_time, 'retries' => $retries, 'selected_goal' => $selected_goal, 'selected_search_intent' => $selected_search_intent, 'content_type_template' => $content_type_template];
        }
      }

      $failed_count = 0;

      // Retry failed contents
      for ($j = 0; $j < $retries; $j++) {
        foreach ($failed_contents as $key => $failed_content) {
          try {
            $links = $this->sanitize_links($link_texts, $link_urls);
            $prompt = $this->build_super_automated_prompt($failed_content['topic'], $failed_content['buyers_journey'], $links, $generated_titles, $failed_content['selected_goal'], $failed_content['selected_goal'], $failed_content['content_type_template']);
            $content = $this->generate_content_from_prompt($prompt);
            $category = $this->generate_category_from_prompt($content);
            $generated_title = $this->generate_title_from_prompt($content, $generated_titles, $failed_content['buyers_journey'], $language);
            $generated_titles[] = $generated_title;
            $image_info = $this->generate_image_from_prompt($generated_title);
            $tags = $this->get_tags_from_prompt($content);
            $post_id = $this->schedule_content($content, $failed_content['scheduled_time'], $generated_title, $category, $image_info, $post_status, $author_id, $failed_content['buyers_journey'], $tags, $admin_email);

            // Log progress

            $this->updateProgressToPoll("Failed content {$key} retried successfully.");
            $responses[] = ['ID' => $post_id, 'content' => $content, 'category' => $category, 'buyers_journey' => $buyers_journey];
            unset($failed_contents[$key]);
          } catch (Exception $e) {
            if ($failed_content['retries'] > 0) {
              $failed_contents[$key]['retries']--;
            } else {
              $failed_count += 1;
              unset($failed_contents[$key]);
            }
          }
        }
      }

      $this->updateProgressToPoll("Interlinking in progress....");
      $this->interlink_content();

      if ($failed_count > 0) {
        $this->updateProgressToPoll("Some contents failed...");
        update_option('ai_content_pipelines_oneup_content_generation_in_progress', false);
        update_option('ai_content_pipelines_oneup_is_last_error', true);
        update_option('ai_content_pipelines_oneup_last_scheduled_timestamp', time());
        wp_send_json_error(['message' => 'Some content could not be scheduled.', 'responses' => $responses, 'failed_contents' => $failed_contents]);
      } else {
        $this->updateProgressToPoll("Generation Successful");
        update_option('ai_content_pipelines_oneup_content_generation_in_progress', false);
        update_option('ai_content_pipelines_oneup_is_last_error', false);
        update_option('ai_content_pipelines_oneup_last_scheduled_timestamp', time());
        wp_send_json_success(['message' => 'Content scheduled successfully', 'responses' => $responses]);
      }
    } catch (Exception $e) {
      $this->updateProgressToPoll("Some contents failed..." . $e->getMessage());
      update_option('ai_content_pipelines_oneup_content_generation_in_progress', false);
      update_option('ai_content_pipelines_oneup_is_last_error', true);
      update_option('ai_content_pipelines_oneup_last_scheduled_timestamp', time());
      wp_send_json_error(['message' => $e->getMessage(), 'responses so far' => $responses]);
    }
    $this->updateProgressToPoll("Task completed..");
    update_option('ai_content_pipelines_oneup_content_generation_in_progress', false);
    update_option('ai_content_pipelines_oneup_is_last_error', false);
    update_option('ai_content_pipelines_oneup_last_scheduled_timestamp', time());
    wp_send_json_success(['message' => 'Content scheduled successfully', 'responses' => $responses]);
  }


  private function interlink_content()
  {
    global $wpdb;
    // Not caching because we always need the updated query
    // updates happen every time before executing the following line 
    $new_posts = $wpdb->get_results("SELECT ID, post_content FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id WHERE pm.meta_key = 'interlinked' AND pm.meta_value = 'false'");

    // Get all existing posts with content embeddings
    $all_posts = $wpdb->get_results("SELECT p.ID, p.post_content, pm.meta_value as content_embedding FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id WHERE pm.meta_key = 'content_embedding'");

    $content_embeddings = [];
    foreach ($all_posts as $post) {
      $content_embeddings[$post->ID] = json_decode($post->content_embedding);
    }

    try {
      $this->updateProgressToPoll("Generated content is being interlinked...");
      foreach ($new_posts as $new_post) {
        $this->updateProgressToPoll("interlinking post {$new_post->ID}...");

        $new_post_embedding = json_decode(get_post_meta($new_post->ID, 'content_embedding', true));
        if (!$new_post_embedding) {
          $retries = 3;
          while ($retries > 0) {
            try {
              $new_post_embedding = $this->generate_embedding($new_post->post_content);
              update_post_meta($new_post->ID, 'content_embedding', wp_json_encode($new_post_embedding));
              break; // Exit the retry loop on success
            } catch (Exception $e) {
              $retries--;
              if ($retries <= 0) {
                throw new Exception('Failed to generate embedding for new post after multiple attempts: ' . $e->getMessage());
              }
            }
          }
        }

        $similarities = [];

        foreach ($all_posts as $post) {
          if ($new_post->ID !== $post->ID) {
            $similarities[$post->ID] = $this->cosine_similarity($new_post_embedding, $content_embeddings[$post->ID]);
          }
        }

        arsort($similarities);

        // Get top 3 similar content
        $top_similar_ids = array_slice(array_keys($similarities), 0, 10);

        $interlinks = [];
        $tags_to_use = [];
        foreach ($top_similar_ids as $id) {
          $permalink = get_permalink($id);
          $title = get_the_title($id);
          $percentage_match = round($similarities[$id] * 100, 2);
          if ($percentage_match > 80) {
            $tags = wp_get_post_tags($id, ['fields' => 'names']);
            $tag_list = !empty($tags) ? ' (' . implode(', ', $tags) . ')' : '';
            $featured_image_url = get_the_post_thumbnail_url($id, 'thumbnail');
            $image_html = $featured_image_url ? "<img src='{$featured_image_url}' alt='{$title}' style='width:50px;height:auto;margin-right:10px;vertical-align:middle;' />" : '';
            $interlinks[] = "<div style='display:flex;align-items:center;'>{$image_html}<a href='{$permalink}'>{$title} ({$percentage_match}% match)</a></div>";
            $tags_to_use[] = $tag_list;
          }
        }

        // Update the content with interlinks

        $retries = 3;
        while ($retries > 0) {
          try {
            $updated_content_before = $this->insert_interlinks($new_post->post_content, $interlinks, $tags_to_use);
            break; // Exit the retry loop on success
          } catch (Exception $e) {
            $retries--;
            if ($retries <= 0) {
              $this->updateProgressToPoll("error" . $e->getMessage());
              throw new Exception('Failed to insert interlinks after multiple attempts: ' . $e->getMessage());
            }
          }
        }



        $updated_content = $updated_content_before . "\n\nRelated Posts:\n" . implode("\n", $interlinks);

        wp_update_post([
          'ID' => $new_post->ID,
          'post_content' => $updated_content,
        ]);

        $this->updateProgressToPoll("Posting to linkedin...");
        $this->post_to_linkedin($new_post->ID);
        $this->post_to_facebook($new_post->ID);
        $this->post_to_twitter($new_post->ID);

        update_post_meta($new_post->ID, 'interlinked', 'true');
      }
    } catch (Exception $e) {
      $this->updateProgressToPoll("error" . $e->getMessage());
      throw new Exception("Embedding error: " . esc_html($e->getMessage()));
    }
  }

  private function upload_image_to_linkedin($image_url, $access_token, $user_urn)
  {
    // Step 1: Register the Image
    $register_response = wp_remote_post('https://api.linkedin.com/v2/assets?action=registerUpload', [
      'body' => wp_json_encode([
        "registerUploadRequest" => [
          "recipes" => ["urn:li:digitalmediaRecipe:feedshare-image"],
          "owner" => $user_urn,
          "serviceRelationships" => [
            [
              "relationshipType" => "OWNER",
              "identifier" => "urn:li:userGeneratedContent"
            ]
          ]
        ]
      ]),
      'headers' => [
        'Authorization' => 'Bearer ' . $access_token,
        'Content-Type' => 'application/json',
      ],
    ]);

    if (is_wp_error($register_response)) {
      error_log('Error registering image with LinkedIn: ' . $register_response->get_error_message());
      return false;
    }

    $register_body = wp_remote_retrieve_body($register_response);
    $register_data = json_decode($register_body, true);

    if (!isset($register_data['value']['uploadMechanism']['com.linkedin.digitalmedia.uploading.MediaUploadHttpRequest']['uploadUrl'])) {
      error_log('Error: Upload URL not found in LinkedIn response.');
      return false;
    }

    $upload_url = $register_data['value']['uploadMechanism']['com.linkedin.digitalmedia.uploading.MediaUploadHttpRequest']['uploadUrl'];
    $image_urn = $register_data['value']['asset'];

    // Step 2: Upload the Image
    $response = wp_remote_get($image_url, [
      'timeout' => 60, // Set timeout for the request
    ]);

    // Check if the request failed
    if (is_wp_error($response)) {
      error_log('Error fetching image: ' . $response->get_error_message());
      return false;
    }

    // Retrieve the image data from the response
    $image_data = wp_remote_retrieve_body($response);

    if (empty($image_data)) {
      error_log('Failed to retrieve image data from response.');
      return false;
    }
    $upload_result = wp_remote_post($upload_url, [
      'body' => $image_data,
      'headers' => [
        'Authorization' => 'Bearer ' . $access_token,
        'Content-Type' => 'image/png', // or appropriate MIME type
      ],
    ]);

    if (is_wp_error($upload_result)) {
      error_log('Error uploading image to LinkedIn: ' . $upload_result->get_error_message());
      return false;
    }

    return $image_urn;
  }

  // Include TwitterOAuth library

  function post_to_twitter($postID)
  {
    $this->updateProgressToPoll("Posting to Twitter Post ID: " . $postID);

    // Twitter API credentials from settings
    $api_key = get_option('ai_content_pipelines_oneup_twitter_api_key');
    $api_secret = get_option('ai_content_pipelines_oneup_twitter_api_secret');
    $access_token = get_option('ai_content_pipelines_oneup_twitter_access_token');
    $access_token_secret = get_option('ai_content_pipelines_oneup_twitter_access_token_secret');

    if (!$api_key || !$api_secret || !$access_token || !$access_token_secret) {
      $this->updateProgressToPoll('Twitter API credentials are not available.');
      return false;
    }

    // Retrieve post data
    $post = get_post($postID);
    $title = $post ? wp_strip_all_tags($post->post_title) : "Default Title";
    $content = $post ? wp_strip_all_tags($post->post_content) : "Default Content";

    // Format the title to appear as bold (Twitter doesn't support bold, so we use uppercase)
    $formatted_title = strtoupper($title);

    // Retrieve the post URL (slug)
    $post_url = get_permalink($postID);

    // Combine the title and URL
    $text = "**" . $formatted_title . "**\n" . $post_url . "\n";

    // Calculate remaining characters for content
    $remaining_characters = 280 - strlen($text);

    // Trim the content to fit within the remaining characters
    if (strlen($content) > $remaining_characters) {
      $content = substr($content, 0, $remaining_characters - 3) . '...'; // Leave room for the ellipsis
    }

    // Append the content to the tweet text
    $text .= $content;

    // Retrieve the post's featured image
    $image_url = '';
    if (has_post_thumbnail($postID)) {
      $image_id = get_post_thumbnail_id($postID);
      $image_url = wp_get_attachment_url($image_id);
    }

    // Prepare the data to send to the Node.js server
    $data = [
      'text' => $text,
      'media_url' => $image_url,
      'api_key' => $api_key,
      'api_secret' => $api_secret,
      'access_token' => $access_token,
      'access_token_secret' => $access_token_secret,
    ];

    // Send the data to your Node.js server
    $response = wp_remote_post('https://ai.1upmedia.com:443/twitter/tweet', [
      'body' => wp_json_encode($data),
      'headers' => [
        'Content-Type' => 'application/json',
      ],
      'timeout' => 30
    ]);

    if (is_wp_error($response)) {
      $this->updateProgressToPoll('Error posting to Twitter: ' . $response->get_error_message());
      return false;
    }

    $this->updateProgressToPoll('Successfully posted to Twitter');
    return true;
  }


  private function trim_content_for_linkedin($content, $tags)
  {
    // Remove all HTML tags except anchor tags
    $content = wp_strip_all_tags($content, true);

    // Remove tags if present
    $content_without_tags = trim(str_replace($tags, '', $content));

    // Check if the content without tags is within the limit
    if (strlen($content_without_tags) <= 2999) {
      return $content_without_tags;
    }

    // Trim the content to the allowed length if it still exceeds the limit
    return mb_substr($content_without_tags, 0, 2999);
  }


  private function post_to_linkedin($postID)
  {
    $this->updateProgressToPoll("Posting to LinkedIn Post ID: " . $postID);
    $access_token = get_option('ai_content_pipelines_oneup_linkedin_access_token');

    if (!$access_token) {
      $this->updateProgressToPoll('LinkedIn access token is not available.');
      error_log('LinkedIn access token is not available.');
      return false;
    }

    // Retrieve post data
    $post = get_post($postID);
    if (!$post) {
      error_log('Post not found with ID: ' . $postID);
      return false;
    }

    $updated_content = apply_filters('the_content', $post->post_content);
    $generated_title = get_the_title($postID);

    // Combine title, content, and tags
    $tags = get_the_tags($postID);
    $tag_list = '';
    if ($tags) {
      foreach ($tags as $tag) {
        $tag_list .= ' #' . $tag->name;
      }
    }

    // Combine the content
    $full_content = $generated_title . "\n\n" . $updated_content . "\n\n" . $tag_list;

    $request_body = array(
      'content' => $full_content, // Pass the combined title, content, and tags in the body
    );

    // Send content to external API to generate LinkedIn-friendly version
    $markup_api_url = "https://ai.1upmedia.com:443/linkedin/markup";
    $response = wp_remote_post($markup_api_url, array(
      'timeout' => 180,
      'body' => wp_json_encode($request_body), // Pass the body in JSON format
      'headers' => array(
        'Content-Type' => 'application/json',
      ),
    ));


    if (is_wp_error($response)) {
      $this->updateProgressToPoll('Failed to fetch content from the external API. Using original content.');
      error_log('Failed to fetch content from the external API: ' . $response->get_error_message());
    } else {
      $response_body = wp_remote_retrieve_body($response);
      $json_data = json_decode($response_body, true);

      if (isset($json_data['success']) && $json_data['success'] && isset($json_data['linkedinContent'])) {
        $this->updateProgressToPoll('Fetched processed content for LinkedIn.');
        $full_content = $json_data['linkedinContent'];
      } else {
        $this->updateProgressToPoll('API response is invalid. Using original content.');
        error_log('Invalid API response for LinkedIn content.');
      }
    }


    // Check if content exceeds the limit and trim if necessary
    if (strlen($full_content) > 2999) {
      $this->updateProgressToPoll('Content length exceeds the limit, trimming content.');
      $full_content = $this->trim_content_for_linkedin($full_content, $tag_list);
    }

    // Get featured image and upload it to LinkedIn
    $user_urn = $this->get_linkedin_user_urn($access_token);
    if (!$user_urn) {
      error_log('Failed to retrieve LinkedIn user URN.');
      return false;
    }

    $image_urn = null;
    if (has_post_thumbnail($postID)) {
      $thumbnail_id = get_post_thumbnail_id($postID);
      $thumbnail_url = wp_get_attachment_url($thumbnail_id);
      $image_urn = $this->upload_image_to_linkedin($thumbnail_url, $access_token, $user_urn);
      if (!$image_urn) {
        error_log('Failed to upload image to LinkedIn.');
        $this->updateProgressToPoll('Failed to upload image to LinkedIn.');
      }
    }

    // Prepare LinkedIn post data
    $post_data = [
      'author' => $user_urn,
      'lifecycleState' => 'PUBLISHED',
      'specificContent' => [
        'com.linkedin.ugc.ShareContent' => [
          'shareCommentary' => [
            'text' => $full_content,
          ],
          'shareMediaCategory' => $image_urn ? 'IMAGE' : 'NONE',
          'media' => $image_urn ? [
            [
              'status' => 'READY',
              'description' => [
                'text' => $generated_title,
              ],
              'media' => $image_urn,
              'title' => [
                'text' => $generated_title,
              ],
            ]
          ] : []
        ],
      ],
      'visibility' => [
        'com.linkedin.ugc.MemberNetworkVisibility' => 'PUBLIC',
      ],
    ];

    // Post to LinkedIn
    $response = wp_remote_post('https://api.linkedin.com/v2/ugcPosts', [
      'body' => wp_json_encode($post_data),
      'headers' => [
        'Authorization' => 'Bearer ' . $access_token,
        'Content-Type' => 'application/json',
        'X-Restli-Protocol-Version' => '2.0.0',
      ],
    ]);

    if (is_wp_error($response)) {
      $this->updateProgressToPoll('Error posting to LinkedIn: ' . $response->get_error_message());
      return false;
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    if (isset($data['id'])) {
      $this->updateProgressToPoll('LinkedIn posted successfully: ' . $data['id']);
      return $data['id'];
    } else {
      $data_json = wp_json_encode($data, JSON_PRETTY_PRINT);
      $this->updateProgressToPoll('Error posting to LinkedIn: ' . $data_json);
      error_log('Error posting to LinkedIn: ' . $data_json);
      return false;
    }
  }



  private function get_linkedin_user_urn($access_token)
  {
    $response = wp_remote_get('https://api.linkedin.com/v2/userinfo', [
      'headers' => [
        'Authorization' => 'Bearer ' . $access_token,
      ],
    ]);

    if (is_wp_error($response)) {
      error_log('Error retrieving LinkedIn user info: ' . $response->get_error_message());
      return false;
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    if (isset($data['sub'])) {
      return 'urn:li:person:' . $data['sub'];
    } else {
      error_log('Error retrieving LinkedIn user URN: ' . print_r($data, true));
      return false;
    }
  }

  function get_facebook_page_access_token($pageId)
  {
    $option_name = 'ai_content_pipelines_oneup_facebook_page_access_token__' . $pageId;
    $page_access_token = get_option($option_name);

    if (!$page_access_token) {
      error_log('Page Access Token not found for Page ID: ' . $pageId);
      return false;
    }

    return $page_access_token;
  }
  private function post_to_facebook($postID)
  {
    $this->updateProgressToPoll("Posting to Facebook Post Id: " . $postID);
    $access_token = get_option('ai_content_pipelines_oneup_facebook_access_token');
    $page_id = get_option('ai_content_pipelines_oneup_facebook_page_id'); // Ensure you store the selected Facebook Page ID

    if (!$access_token || !$page_id) {
      $this->updateProgressToPoll('Facebook access token or page ID is not available.');
      error_log('Facebook access token or page ID is not available.');
      return false;
    }

    // Retrieve post data
    $post = get_post($postID);
    if (!$post) {
      error_log('Post not found with ID: ' . $postID);
      return false;
    }

    $updated_content = apply_filters('the_content', $post->post_content);
    $generated_title = get_the_title($postID);

    // Combine title and content
    $full_content = $generated_title . "\n\n" . $updated_content;

    // Get featured image
    $image_url = null;
    if (has_post_thumbnail($postID)) {
      $thumbnail_id = get_post_thumbnail_id($postID);
      $image_url = wp_get_attachment_url($thumbnail_id);
    }

    // Prepare data to send to Node.js API

    $post_data = [
      'pageId' => $page_id,
      'accessToken' => $this->get_facebook_page_access_token($page_id),
      'imageUrl' => $image_url,
      'caption' => $full_content,
    ];

    // Send data to Node.js API
    $response = wp_remote_post('https://ai.1upmedia.com:443/facebook/postImageToFacebookPage', [
      'body' => wp_json_encode($post_data),
      'headers' => [
        'Content-Type' => 'application/json',
      ],
      'timeout' => 30
    ]);

    if (is_wp_error($response)) {
      $this->updateProgressToPoll('Error posting to Facebook: ' . $response->get_error_message());
      error_log('Error posting to Facebook: ' . $response->get_error_message());
      return false;
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    if (isset($data['postId'])) {
      $this->updateProgressToPoll('Facebook post published successfully: ' . $data['postId']);
      return $data['postId'];
    } else {
      $data_json = wp_json_encode($data, JSON_PRETTY_PRINT);
      $this->updateProgressToPoll('Error posting to Facebook: ' . $data_json);
      error_log('Error posting to Facebook: ' . $data_json);
      return false;
    }
  }


  // private function cosine_similarity($vectorA, $vectorB)
  // {
  //   $this->updateProgressToPoll("A : " . $vectorA . " B : " . $vectorB);
  //   $dotProduct = 0;
  //   $magnitudeA = 0;
  //   $magnitudeB = 0;

  //   for ($i = 0; $i < count($vectorA); $i++) {
  //     $dotProduct += $vectorA[$i] * $vectorB[$i];
  //     $magnitudeA += $vectorA[$i] * $vectorA[$i];
  //     $magnitudeB += $vectorB[$i] * $vectorB[$i];
  //   }

  //   $magnitudeA = sqrt($magnitudeA);
  //   $magnitudeB = sqrt($magnitudeB);

  //   if ($magnitudeA * $magnitudeB == 0) {
  //     return 0;
  //   }

  //   return $dotProduct / ($magnitudeA * $magnitudeB);
  // }

  private function cosine_similarity($vectorA, $vectorB)
  {

    if (empty($vectorA) || empty($vectorB)) {
      return 0;
    }
    $url = 'https://ai.1upmedia.com:443/cosine-similarity';

    $request_body = wp_json_encode([
      'vectorA' => $vectorA,
      'vectorB' => $vectorB
    ]);

    $response = wp_remote_post($url, [
      'headers' => [
        'Content-Type' => 'application/json'
      ],
      'body' => $request_body,
      'timeout' => 60
    ]);

    if (is_wp_error($response)) {
      throw new Exception('API request for cosine similarity failed: ' . esc_html($response->get_error_message()));
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    if (!isset($data['similarity'])) {
      throw new Exception('Invalid API response for cosine similarity');
    }

    return $data['similarity'];
  }
  private function insert_interlinks($content, $interlinks, $keywords_array)
  {
    $url = 'https://ai.1upmedia.com:443/insert-interlinks';

    $interlinks_text = implode(", ", array_map(function ($link) {
      return $link;
    }, $interlinks));


    $request_body = wp_json_encode([
      'content' => $content,
      'interlinks' => $interlinks_text,
      'keywordsArray' => implode(', ', $keywords_array)
    ]);

    $response = wp_remote_post($url, [
      'headers' => [
        'Content-Type' => 'application/json'
      ],
      'body' => $request_body,
      'timeout' => 60
    ]);


    if (is_wp_error($response)) {
      $this->updateProgressToPoll("error" . $response->get_error_message());
      throw new Exception('API request for interlinks failed: ' . esc_html($response->get_error_message()));
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    if (!isset($data['updatedContent'])) {
      throw new Exception('Invalid API response for interlinks');
    }

    $content = trim($data['updatedContent']);

    return $content;
  }

  private function calculate_scheduled_time($start_date, $interval, $index)
  {
    switch ($interval) {
      case 'daily':
        return strtotime("+{$index} days", strtotime($start_date));
      case 'weekly':
        return strtotime("+{$index} weeks", strtotime($start_date));
      case 'monthly':
        return strtotime("+{$index} months", strtotime($start_date));
      case 'exact':  // New case for posting exactly on the scheduled date
        return strtotime($start_date);
      default:
        return strtotime("+{$index} days", strtotime($start_date));
    }
  }

  private function remove_surrounding_quotes($text)
  {
    return trim($text, "\"");
  }

  private function build_full_workflow_prompt($title, $goal, $target_audience, $keywords, $search_intent, $links, $word_count, $tone, $content_type, $content_strategy, $content_type_prompt_template = "", $language = "English")
  {
    $url = 'https://ai.1upmedia.com:443/build-full-workflow-prompt';

    $request_body = wp_json_encode([
      'title' => $title,
      'goal' => $goal,
      'target_audience' => $target_audience,
      'keywords' => $keywords,
      'search_intent' => $search_intent,
      'links' => $links,
      'word_count' => $word_count,
      'tone' => $tone,
      'content_type' => $content_type,
      'content_strategy' => $content_strategy,
      'content_type_prompt_template' => $content_type_prompt_template,
      'language' => $language
    ]);

    $response = wp_remote_post($url, [
      'headers' => [
        'Content-Type' => 'application/json'
      ],
      'body' => $request_body,
      'timeout' => 180
    ]);

    if (is_wp_error($response)) {
      throw new Exception('API request failed: ' . esc_html($response->get_error_message()));
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    if (!isset($data['prompt'])) {
      throw new Exception('Invalid API response');
    }

    return $data['prompt'];
  }


  private function build_automated_prompt($topic, $template_style, $links, $goal, $search_intent, $content_strategy, $content_type_template, $language = "English")
  {
    $url = 'https://ai.1upmedia.com:443/build-automated-prompt';

    $request_body = wp_json_encode([
      'topic' => $topic,
      'template_style' => $template_style,
      'links' => $links,
      'goal' => $goal,
      'search_intent' => $search_intent,
      'content_strategy' => $content_strategy,
      'content_type_prompt_template' => $content_type_template,
      'language' => $language,
    ]);

    $response = wp_remote_post($url, [
      'headers' => [
        'Content-Type' => 'application/json'
      ],
      'body' => $request_body,
      'timeout' => 180
    ]);

    if (is_wp_error($response)) {
      throw new Exception('API request failed: ' . esc_html($response->get_error_message()));
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    if (!isset($data['prompt'])) {
      throw new Exception('Invalid API response');
    }

    return $data['prompt'];
  }

  private function build_super_automated_prompt($topic, $buyers_journey, $links, $generated_titles = [], $goal = '', $search_intent = '', $content_type_template = '', $language = "English")
  {
    $url = 'https://ai.1upmedia.com:443/build-super-automated-prompt';

    $request_body = wp_json_encode([
      'topic' => $topic,
      'buyers_journey' => $buyers_journey,
      'links' => $links,
      'generated_titles' => $generated_titles,
      'goal' => $goal,
      'search_intent' => $search_intent,
      'content_type_prompt_template' => $content_type_template,
      'language' => $language
    ]);

    $response = wp_remote_post($url, [
      'headers' => [
        'Content-Type' => 'application/json'
      ],
      'body' => $request_body,
      'timeout' => 180
    ]);

    if (is_wp_error($response)) {
      throw new Exception('API request failed: ' . esc_html($response->get_error_message()));
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    if (!isset($data['prompt'])) {
      throw new Exception('Invalid API response');
    }

    return $data['prompt'];
  }

  private function sanitize_links($link_texts, $link_urls)
  {
    $links = [];
    for ($i = 0; $i < count($link_texts); $i++) {
      $link_text = sanitize_text_field($link_texts[$i]);
      $link_url = esc_url_raw($link_urls[$i]);
      if (!empty($link_text) && !empty($link_url)) {
        $links[] = "<a href='{$link_url}' alt='{$link_text}' target='_blank'>{$link_text}</a>";
      }
    }
    return implode(', ', $links);
  }

  private function schedule_content($content, $scheduled_time, $title, $categories, $image_info, $post_status, $author, $template_style, $tags, $admin_email = '')
  {
    if (!is_array($categories)) {
      $categories = [$categories];
    }

    // Convert category names to category IDs
    $category_ids = array_map([$this, 'get_category_id'], $categories);

    $post_id = wp_insert_post([
      'post_title' => $title,
      'post_content' => $content,
      'post_status' => $post_status,
      'post_type' => 'post',
      'post_category' => $category_ids,
      'tags_input' => $tags,
      'post_date' => gmdate('Y-m-d H:i:s', $scheduled_time),
      'post_author' => $author
    ]);

    if ($image_info) {
      // Check if the image is AI-generated or user-uploaded
      if (isset($image_info['id'])) {
        // User-uploaded image
        set_post_thumbnail($post_id, $image_info['id']);
      } else {
        // AI-generated image
        $this->set_featured_image($post_id, $image_info);
      }
    }

    $retries = 3;
    while ($retries > 0) {
      try {
        $embedding = $this->generate_embedding($content);
        update_post_meta($post_id, 'content_embedding', wp_json_encode($embedding));
        break; // Exit the retry loop on success
      } catch (Exception $e) {
        $retries--;
        if ($retries <= 0) {
          throw new Exception('Failed to generate embedding after multiple attempts: ' . esc_html($e->getMessage()));
        }
      }
    }

    // In your content generation function
    $template_type = $template_style; // Get the template type dynamically
    $template_style_mapping = [
      'Awareness' => 'ToF', // Top of Funnel
      'Consideration' => 'MoF', // Middle of Funnel
      'Decision' => 'BoF' // Bottom of Funnel
    ];

    if (array_key_exists($template_style, $template_style_mapping)) {
      // If the template style matches a predefined stage, set the buyer's journey directly
      $buyers_journey = $template_style_mapping[$template_style];
    } else {
      // Otherwise, make an API call to classify the buyer's journey
      try {
        $buyers_journey = $this->classify_funnel_stage($content);
      } catch (Exception $e) {
        $buyers_journey = 'Unknown'; // Fallback if classification fails
        error_log('Error classifying funnel stage: ' . $e->getMessage());
      }
    }

    update_post_meta($post_id, '_template_type', $template_type);
    update_post_meta($post_id, 'interlinked', 'false');
    update_post_meta($post_id, 'buyers_journey', $buyers_journey);

    if (!empty($admin_email)) {
      // Send email to admin for primary post status with post URL
      wp_mail($admin_email, 'Post Pending Approval', 'A post is pending your approval. View it here: ' . get_permalink($post_id));
    }

    return $post_id;
  }


  private function generate_content_from_prompt($prompt)
  {
    // Retrieve the API key from the WordPress options
    $api_key = get_option('ai_content_pipelines_oneup_openai_api_key');

    // Retrieve the site URL from WordPress
    $site_url = get_site_url();

    $url = 'https://ai.1upmedia.com:443/generate-content-from-prompt';

    $request_body = wp_json_encode([
      'prompt' => $prompt,
      'api_key' => $api_key,
      'site_url' => $site_url
    ]);

    $response = wp_remote_post($url, [
      'headers' => [
        'Content-Type' => 'application/json'
      ],
      'body' => $request_body,
      'timeout' => 180
    ]);

    if (is_wp_error($response)) {
      throw new Exception('API request failed: ' . esc_html($response->get_error_message()));
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    if (isset($data['type'])) {
      switch ($data['type']) {
        case 'QuotaExceeded':
          throw new AI_Content_Pipelines_Oneup_QuotaExceededException();
        case 'Validation Error':
          throw new Exception('Validation Error: ' . esc_html($data['error']));
        case 'Content Generation Error':
          throw new Exception('Content Generation Error: ' . esc_html($data['error']));
        case 'Database Update Error':
          throw new Exception('Database Update Error: ' . esc_html($data['error']));
        default:
          throw new Exception('API request failed: ' . esc_html($data['error']));
      }
    }


    if (!isset($data['content'])) {
      throw new Exception('Invalid API response');
    }

    $content = trim($data['content']);

    return $content;
  }

  private function generate_category_from_prompt($content)
  {
    $url = 'https://ai.1upmedia.com:443/generate-category-from-prompt';

    $request_body = wp_json_encode([
      'content' => $content
    ]);

    $response = wp_remote_post($url, [
      'headers' => [
        'Content-Type' => 'application/json'
      ],
      'body' => $request_body,
      'timeout' => 60
    ]);

    if (is_wp_error($response)) {
      throw new Exception('API request for category failed: ' . esc_html($response->get_error_message()));
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    if (!isset($data['categories'])) {
      throw new Exception('Invalid API response for category');
    }

    return $data['categories'];
  }
  private function generate_title_from_prompt($content, $generated_titles, $stage = null, $language = "English")
  {
    $url = 'https://ai.1upmedia.com:443/generate-title-from-prompt';

    $request_body = wp_json_encode([
      'content' => $content,
      'generated_titles' => $generated_titles,
      'stage' => $stage,
      'language' => $language
    ]);

    $response = wp_remote_post($url, [
      'headers' => [
        'Content-Type' => 'application/json'
      ],
      'body' => $request_body,
      'timeout' => 60
    ]);

    if (is_wp_error($response)) {
      throw new Exception('API request for title failed: ' . esc_html($response->get_error_message()));
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    if (!isset($data['title'])) {
      throw new Exception('Invalid API response for title');
    }

    return $this->remove_surrounding_quotes(trim($data['title']));
  }

  private function generate_embedding($text)
  {
    $url = 'https://ai.1upmedia.com:443/generate-embedding';

    $request_body = wp_json_encode([
      'text' => $text
    ]);

    $response = wp_remote_post($url, [
      'headers' => [
        'Content-Type' => 'application/json'
      ],
      'body' => $request_body,
      'timeout' => 60
    ]);

    if (is_wp_error($response)) {
      throw new Exception('API request for embedding failed: ' . esc_html($response->get_error_message()));
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    if (!isset($data['embedding'])) {
      throw new Exception('Invalid API response for embedding');
    }

    return $data['embedding'];
  }

  private function get_category_id($category)
  {
    $term = term_exists($category, 'category');
    if ($term !== 0 && $term !== null) {
      return $term['term_id'];
    } else {
      $new_term = wp_insert_term($category, 'category');
      return $new_term['term_id'];
    }
  }

  private function get_tags_from_prompt($content)
  {
    $url = 'https://ai.1upmedia.com:443/get-tags-from-prompt';

    $request_body = wp_json_encode([
      'content' => $content
    ]);

    $response = wp_remote_post($url, [
      'headers' => [
        'Content-Type' => 'application/json'
      ],
      'body' => $request_body,
      'timeout' => 60
    ]);

    if (is_wp_error($response)) {
      throw new Exception('API request for tags failed: ' . esc_html($response->get_error_message()));
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    if (!isset($data['tags'])) {
      throw new Exception('Invalid API response for tags');
    }

    return $data['tags'];
  }

  private function get_template_styles()
  {

    $content_types = get_option('ai_content_pipelines_oneup_author_personas_content_types', []);

    if (!empty($content_types)) {
      return $content_types;
    }

    return [
      "Review" => "Review Template\n\nIntroduction:\n- Provide a brief overview of the product/service being reviewed.\n  - Mention the main purpose or use case.\n  - Include any initial impressions or unique aspects that stand out.\n  - Highlight who the target audience is for this product/service.\n\nPros and Cons:\n- List the key advantages and benefits of the product/service.\n  - Explain how these benefits enhance the user experience.\n  - Provide specific examples or scenarios where these benefits are evident.\n- List any drawbacks or disadvantages.\n  - Provide context for these drawbacks and how they might affect the user experience.\n  - Mention any specific situations where these drawbacks are most noticeable.\n- Explain how these pros and cons impact the overall user experience.\n  - Discuss the balance between the pros and cons.\n  - Highlight any mitigating factors or solutions for the drawbacks.\n\nDetailed Review:\n- Describe the product/service in detail, including features, performance, and usability.\n  - Highlight key features and how they function.\n  - Discuss the overall performance and reliability.\n  - Evaluate the usability and user-friendliness.\n  - Include any setup or installation processes and how user-friendly they are.\n- Compare it with similar products/services in the market, highlighting what makes it unique.\n  - Identify key competitors and compare features and performance.\n  - Discuss pricing in comparison to similar products/services.\n  - Highlight any unique selling points or differentiators.\n- Share any personal experiences or insights.\n  - Include anecdotes or specific examples from your own use.\n  - Discuss any long-term usage impressions and how the product/service has held up over time.\n- Include specific examples or scenarios where the product/service excels or falls short.\n  - Provide real-world applications and outcomes.\n  - Mention any feedback or results from others who have used the product/service.\n- Provide any tips or recommendations for potential users.\n  - Offer advice on how to get the most out of the product/service.\n  - Suggest any best practices or usage tips.\n\nUser Feedback:\n- [Placeholder for quotes or paraphrases from other users or expert reviews retrieved via an API call]\n  - Summarize common praises and criticisms from other users.\n  - Include any notable expert opinions or endorsements.\n- Discuss any common themes or differing opinions from other reviews.\n  - Highlight recurring issues or frequently mentioned benefits.\n  - Mention any specific aspects that receive mixed feedback.\n\nExternal Comparisons:\n- [Placeholder for insights from top articles or reviews for similar products/services retrieved via an API call]\n  - Cite reviews or articles from reputable sources for additional perspective.\n  - Discuss how the product/service stacks up against competitors based on these sources.\n- [Placeholder for user reviews or expert opinions on the product/service retrieved via an API call]\n  - Include expert opinions to add credibility.\n  - Summarize the general consensus from these sources.\n\nConclusion:\n- Summarize your overall opinion.\n  - Recap the main points discussed in the review.\n  - Emphasize the most significant benefits and drawbacks.\n- Recommend whether the product/service is worth purchasing and for whom.\n  - Suggest the target audience who would benefit most from the product/service.\n  - Mention any specific use cases where the product/service excels.\n- Suggest any improvements or additional considerations for potential buyers.\n  - Provide constructive feedback on how the product/service could be improved.\n  - Highlight any upcoming features or updates that could address current drawbacks.",
      "Editorial" => "Editorial Template\n\nIntroduction:\n- Introduce the topic or issue you are addressing.\n  - Provide a hook to grab the reader's attention.\n  - Explain why this topic is important or relevant now.\n- Provide some background information and context.\n  - Discuss the history or development of the issue.\n  - Mention any key events or turning points.\n- Include any initial impressions or unique aspects that stand out.\n\nMain Argument:\n- Present your main argument or viewpoint.\n  - Clearly state your thesis or main point.\n  - Break down your argument into key supporting points.\n- Use supporting evidence, examples, and anecdotes.\n  - Provide statistics, facts, or quotes from experts.\n  - Share real-world examples or personal stories.\n- Include quotes or data from reputable sources to back up your points.\n  - Cite studies, reports, or articles that support your argument.\n  - Ensure sources are current and relevant.\n\nCounterarguments:\n- Address potential counterarguments or opposing views.\n  - Identify common objections to your viewpoint.\n  - Acknowledge valid points made by the opposition.\n- Refute them with logical reasoning and evidence.\n  - Use data or examples to counter these arguments.\n  - Explain why these counterarguments are flawed or incomplete.\n- Provide quotes or data from reputable sources to support your refutations.\n  - Cite evidence that undermines the opposing view.\n  - Highlight any biases or inaccuracies in the opposing arguments.\n\nConclusion:\n- Summarize your main points.\n  - Recap the key arguments made in your editorial.\n  - Reinforce the significance of your viewpoint.\n- Reiterate your stance and suggest any calls to action or next steps.\n  - Encourage readers to take specific actions or consider your perspective.\n  - Offer potential solutions or ways forward.\n- Encourage readers to share their thoughts or engage in further discussion.\n  - Pose questions to the audience to prompt interaction.\n  - Invite readers to comment or debate the issue.",
      "Interview" => "Interview Template\n\nIntroduction:\n- Introduce the interviewee and their background.\n  - Provide a brief biography, including their current role and notable achievements.\n  - Mention the purpose of the interview and why the interviewee was chosen.\n- Set the context for the interview.\n  - Explain the main topics or themes that will be covered.\n  - Highlight any relevant events or developments that make this interview timely or important.\n  - Provide any necessary background information or context for readers unfamiliar with the topic.\n\nQuestions and Answers:\n- Start with introductory questions to set the stage.\n  - Ask about the interviewee's background and experiences.\n  - Discuss their journey and key milestones in their career.\n  - Ask about early influences and inspirations.\n- Dive into the main topics of the interview.\n  - Ask open-ended questions that encourage detailed responses.\n  - Follow up with probing questions to delve deeper into specific points.\n  - Use a mix of question types: factual, opinion-based, and hypothetical.\n  - Explore the interviewee's thoughts on current industry trends and developments.\n- Include questions that address challenges and solutions.\n  - Ask about specific challenges the interviewee has faced.\n  - Discuss how they overcame these challenges and what they learned.\n  - Inquire about any failures or setbacks and how they dealt with them.\n  - Explore any innovative solutions or strategies they have implemented.\n- Incorporate questions about future plans and perspectives.\n  - Ask about the interviewee's vision for the future.\n  - Discuss upcoming projects or goals.\n  - Get their take on industry trends and future developments.\n  - Inquire about any predictions they have for the future of their field.\n- Include follow-up questions based on the interviewee's responses.\n  - Be flexible and adapt to the flow of the conversation.\n  - Allow the interviewee to elaborate on interesting points.\n  - Clarify or expand on any ambiguous or intriguing answers.\n- Personal and Philosophical Questions:\n  - Ask about the interviewee's personal philosophy or approach to their work.\n  - Inquire about their work-life balance and how they manage stress.\n  - Discuss their views on leadership, innovation, and creativity.\n  - Explore their thoughts on broader societal or ethical issues related to their field.\n\nConclusion:\n- Summarize key points discussed in the interview.\n  - Highlight the most important insights or takeaways.\n  - Provide a brief recap of the main topics covered.\n- Provide any final thoughts or reflections from the interviewee.\n  - Ask if they have any additional comments or messages for the audience.\n  - Inquire about any advice they would give to aspiring professionals in their field.\n- Mention any relevant links or resources.\n  - Provide links to the interviewee's work, social media, or related content.\n  - Suggest further reading or resources for interested readers.\n- Thank the interviewee for their time and participation.\n  - Express appreciation for their insights and contributions.\n  - Mention how the interview has added value to the topic or industry discussion.",
      "How To" => "How To Template\n\nIntroduction:\n- Introduce the topic and explain the importance of the task.\n  - Provide a brief overview of what the guide will cover.\n  - Mention any prerequisites or necessary background knowledge.\n  - Explain the benefits of completing this task.\n  - Highlight any common misconceptions or challenges related to the task.\n  - Set the tone for the guide, whether it's formal, casual, or instructional.\n\nStep-by-Step Instructions:\n- List each step in a clear and logical order.\n  - Start with an overview of the process and what to expect.\n  - Break down each step into sub-steps if necessary.\n  - Use clear and concise language to avoid confusion.\n  - Include any necessary warnings or cautions to ensure safety and success.\n- Provide detailed explanations and tips for each step.\n  - Include screenshots, diagrams, or images to illustrate key points.\n  - Offer alternative methods or shortcuts where applicable.\n  - Highlight common mistakes and how to avoid them.\n  - Provide troubleshooting tips for common issues.\n  - Mention any tools or materials needed for each step.\n  - Include time estimates for each step to help with planning.\n- Incorporate expert advice or best practices.\n  - Include quotes or insights from professionals in the field.\n  - Reference any relevant studies or data that support the steps.\n\nConclusion:\n- Summarize the process.\n  - Recap the main steps and key points.\n  - Highlight the benefits of completing the task.\n- Offer any additional tips or considerations.\n  - Provide recommendations for further reading or related tasks.\n  - Mention any tools or resources that can help with the task.\n  - Suggest ways to maintain or build on the completed task.\n- Encourage readers to share their experiences or ask questions.\n  - Invite readers to comment or provide feedback.\n  - Suggest ways to apply the knowledge gained from the guide.\n  - Include a call-to-action, such as sharing the guide or trying out the task.",
      "Topic Introduction" => "Topic Introduction Template\n\nIntroduction:\n- Introduce the topic and its relevance.\n  - Provide a brief overview of the topic.\n  - Explain why this topic is important or relevant now.\n  - Mention any recent events or developments related to the topic.\n  - Highlight the target audience for this topic.\n  - Set expectations for what the reader will learn.\n\nBackground Information:\n- Provide some background information.\n  - Discuss the history or origin of the topic.\n  - Mention key milestones or developments.\n  - Include any necessary definitions or explanations of key terms.\n  - Reference any notable figures or organizations associated with the topic.\n  - Discuss the evolution of the topic over time.\n\nKey Points:\n- Outline the main points related to the topic.\n  - Break down the topic into subtopics or categories.\n  - Provide detailed explanations for each key point.\n  - Include relevant statistics, data, or research findings.\n  - Highlight any controversies or differing opinions related to the topic.\n  - Use quotes or insights from experts to support your points.\n  - Provide real-world examples or case studies where applicable.\n  - Discuss practical applications and implications of the topic.\n\nCurrent Trends and Future Directions:\n- Discuss current trends related to the topic.\n  - Highlight any recent changes or emerging trends.\n  - Mention any ongoing research or developments.\n  - Discuss potential future directions or advancements.\n  - Include predictions or expectations from experts.\n  - Examine the potential impact of these trends on various sectors.\n\nConclusion:\n- Summarize the key points discussed in the introduction.\n  - Recap the main takeaways for the reader.\n  - Emphasize the importance of the topic and its future impact.\n- Suggest further reading or resources.\n  - Provide links to additional articles, books, or research papers.\n  - Mention any organizations or experts to follow for more information.\n- Encourage readers to share their thoughts or ask questions.\n  - Invite readers to comment or engage in a discussion.\n  - Suggest ways to apply the knowledge gained from the introduction.\n  - Include a call-to-action to explore related topics or participate in relevant activities.",
      "Opinion" => "Opinion Template\n\nIntroduction:\n- Introduce the topic and your viewpoint.\n  - Provide a brief overview of the topic.\n  - Clearly state your opinion or stance on the topic.\n  - Explain why this topic is important or relevant now.\n  - Mention any recent events or developments related to the topic.\n  - Highlight the target audience for this opinion piece.\n  - Set the tone for the piece, whether it's formal, casual, or passionate.\n\nMain Points:\n- Present your main points and arguments.\n  - Break down your opinion into key supporting points.\n  - Provide detailed explanations and reasoning for each point.\n  - Use evidence, examples, and anecdotes to support your arguments.\n  - Include relevant statistics, data, or research findings.\n  - Address any potential counterarguments and refute them with logical reasoning.\n  - Use quotes or insights from experts to bolster your arguments.\n  - Provide real-world examples or case studies where applicable.\n  - Connect your arguments to broader social, economic, or political issues.\n  - Anticipate reader questions or concerns and address them proactively.\n\nCounterarguments:\n- Address potential counterarguments or opposing views.\n  - Identify common objections to your viewpoint.\n  - Acknowledge valid points made by the opposition.\n  - Refute these counterarguments with logical reasoning and evidence.\n  - Use data or examples to counter these arguments.\n  - Explain why these counterarguments are flawed or incomplete.\n  - Highlight the potential consequences of adopting opposing viewpoints.\n\nConclusion:\n- Summarize your viewpoint.\n  - Recap the main points and arguments made in your opinion piece.\n  - Reinforce the significance of your stance.\n  - Highlight the implications of your viewpoint for the reader.\n  - End with a strong, memorable statement that reinforces your position.\n- Suggest any calls to action or next steps.\n  - Encourage readers to take specific actions or consider your perspective.\n  - Offer potential solutions or ways forward.\n  - Provide actionable advice or recommendations.\n- Encourage readers to share their thoughts or engage in further discussion.\n  - Invite readers to comment or debate the issue.\n  - Suggest ways to apply the knowledge gained from the opinion piece.\n  - Include a call-to-action to explore related topics or participate in relevant activities.",
      "Research" => "Research Template\n\nIntroduction:\n- Introduce the research topic and its importance.\n  - Provide a brief overview of the topic.\n  - Explain why this research is relevant or necessary now.\n  - Mention any recent events or developments that highlight the importance of the research.\n  - Set the scope and objectives of the research.\n  - Pose key research questions or hypotheses.\n\nLiterature Review:\n- Summarize existing research on the topic.\n  - Discuss key studies and their findings.\n  - Highlight gaps or limitations in the current knowledge.\n  - Mention any conflicting findings or debates in the literature.\n  - Provide context for how your research will contribute to the field.\n  - Include theoretical frameworks or models that inform the research.\n\nMethods:\n- Describe the research methods used.\n  - Explain the research design (e.g., qualitative, quantitative, mixed methods).\n  - Detail the data collection methods (e.g., surveys, experiments, interviews).\n  - Describe the sample population and how it was selected.\n  - Mention any tools or instruments used for data collection.\n  - Explain the data analysis methods and any statistical techniques applied.\n  - Discuss any ethical considerations or approvals obtained for the study.\n\nFindings:\n- Present the main findings of the research.\n  - Use tables, graphs, or charts to illustrate key results.\n  - Provide detailed explanations of the findings.\n  - Discuss any patterns, trends, or significant results.\n  - Highlight any unexpected or surprising findings.\n  - Compare your findings with those from previous studies.\n  - Include quotes or excerpts from qualitative data where applicable.\n\nDiscussion:\n- Interpret the findings and their implications.\n  - Discuss how the findings contribute to the field.\n  - Highlight the practical or theoretical implications of the research.\n  - Address any limitations of the study and suggest areas for future research.\n  - Mention any potential applications or recommendations based on the findings.\n  - Relate the findings to the original research questions or hypotheses.\n\nConclusion:\n- Summarize the key points and findings of the research.\n  - Recap the significance of the research and its contributions.\n  - Suggest next steps or future directions for research.\n  - Highlight any broader implications or potential impact of the research.\n- Encourage readers to explore the topic further.\n  - Provide links to additional resources, articles, or studies.\n  - Invite readers to share their thoughts or ask questions about the research.\n  - Suggest ways to apply the knowledge gained from the research.",
      "Case Study" => "Case Study Template\n\nIntroduction:\n- Introduce the subject of the case study.\n  - Provide a brief overview of the organization, individual, or project being studied.\n  - Mention the purpose of the case study and its relevance.\n  - Highlight the key issues or challenges addressed in the case study.\n  - Set the context for the case study by providing necessary background information.\n  - Outline the structure of the case study for the reader.\n\nProblem:\n- Describe the problem or challenge faced.\n  - Explain the nature and scope of the problem.\n  - Provide details on how the problem was identified.\n  - Include any relevant data or statistics to illustrate the problem.\n  - Discuss the impact of the problem on the organization or individual.\n  - Mention any previous attempts to address the problem.\n  - Highlight any key stakeholders affected by the problem.\n\nSolution:\n- Explain the solution implemented to address the problem.\n  - Describe the strategies, actions, or interventions taken.\n  - Provide a step-by-step account of how the solution was implemented.\n  - Mention any tools, techniques, or methodologies used.\n  - Include the roles and responsibilities of individuals or teams involved.\n  - Discuss any challenges or obstacles encountered during implementation.\n  - Highlight any innovative or unique aspects of the solution.\n  - Explain the rationale behind the chosen solution.\n\nResults:\n- Present the results of the solution.\n  - Use data, metrics, or key performance indicators (KPIs) to quantify the outcomes.\n  - Provide before-and-after comparisons to illustrate the impact of the solution.\n  - Include testimonials, quotes, or feedback from stakeholders.\n  - Discuss any unexpected or secondary benefits of the solution.\n  - Compare the results with the initial objectives or expectations.\n  - Visualize key results using charts, graphs, or infographics.\n\nConclusion:\n- Summarize the key points and lessons learned from the case study.\n  - Recap the problem, solution, and results.\n  - Highlight the significance of the case study and its contributions to the field.\n  - Mention any limitations of the case study or areas for future research.\n  - Provide recommendations based on the case study findings.\n  - Discuss the broader implications of the case study for the industry or field.\n- Encourage readers to apply the insights gained from the case study.\n  - Suggest ways the solution can be adapted or applied in other contexts.\n  - Invite readers to share their thoughts or ask questions about the case study.\n  - Include a call-to-action to explore related topics or participate in relevant activities.",
      "Short Report" => "Short Report Template\n\nIntroduction:\n- Introduce the topic of the report.\n  - Provide a brief overview of the topic.\n  - Explain the purpose of the report and its relevance.\n  - Mention any recent events or developments related to the topic.\n  - Set the scope and objectives of the report.\n  - Pose key questions or hypotheses that the report will address.\n\nMain Points:\n- Present the main points of the report.\n  - Break down the topic into key points or sections.\n  - Provide detailed explanations and insights for each point.\n  - Include relevant statistics, data, or research findings.\n  - Highlight any significant trends, patterns, or developments.\n  - Use charts, graphs, or tables to illustrate key points.\n  - Discuss any implications or impacts of the findings.\n  - Mention any supporting evidence or examples to bolster your points.\n  - Include quotes or insights from experts or stakeholders.\n\nAnalysis:\n- Analyze the findings in depth.\n  - Discuss the reasons behind the trends or patterns observed.\n  - Compare the findings with previous studies or benchmarks.\n  - Highlight any anomalies or unexpected results.\n  - Discuss the reliability and validity of the data sources.\n  - Address any potential biases or limitations in the data.\n\nDiscussion:\n- Interpret the findings and their significance.\n  - Discuss the implications of the main points for the topic or field.\n  - Highlight any potential challenges or opportunities identified in the report.\n  - Address any limitations or gaps in the report's findings.\n  - Suggest areas for future research or investigation.\n  - Relate the findings to broader contexts or trends.\n  - Propose practical applications or recommendations based on the findings.\n\nConclusion:\n- Summarize the key points and findings of the report.\n  - Recap the significance of the report and its contributions.\n  - Provide recommendations based on the report's findings.\n  - Suggest next steps or actions for stakeholders or readers.\n  - Offer a forward-looking perspective on the topic.\n- Encourage readers to explore the topic further.\n  - Provide links to additional resources, articles, or studies.\n  - Invite readers to share their thoughts or ask questions about the report.\n  - Include a call-to-action to explore related topics or participate in relevant activities.",
      "Think Piece" => "Think Piece Template\n\nIntroduction:\n- Introduce the topic and its relevance.\n  - Provide a brief overview of the topic.\n  - Explain why this topic is important or relevant now.\n  - Mention any recent events or developments related to the topic.\n  - Set the context for the think piece by providing necessary background information.\n  - Pose key questions or issues that the think piece will explore.\n  - Outline the structure of the think piece for the reader.\n\nMain Argument:\n- Present your main argument or viewpoint.\n  - Clearly state your perspective on the topic.\n  - Provide a rationale for your viewpoint, including any theoretical or conceptual frameworks.\n  - Include relevant data, statistics, or research findings to support your argument.\n  - Use quotes or insights from experts to bolster your points.\n  - Address any common misconceptions or counterarguments related to the topic.\n  - Highlight the implications of your argument for the broader field or society.\n  - Discuss the potential future impact or developments related to your argument.\n  - Explore any ethical, social, or economic dimensions tied to your argument.\n\nSupporting Points:\n- Provide supporting points and evidence.\n  - Break down your main argument into key supporting points.\n  - Offer detailed explanations and examples for each point.\n  - Include anecdotes, case studies, or real-world examples to illustrate your points.\n  - Discuss any trends, patterns, or developments that support your argument.\n  - Use visual aids like charts, graphs, or infographics to enhance your points.\n  - Incorporate insights from multidisciplinary perspectives to add depth to your argument.\n  - Highlight any practical applications or real-world implications of your points.\n\nCounterarguments:\n- Address potential counterarguments or opposing views.\n  - Identify common objections to your viewpoint.\n  - Acknowledge valid points made by the opposition.\n  - Refute these counterarguments with logical reasoning and evidence.\n  - Use data or examples to counter these arguments.\n  - Explain why these counterarguments are flawed or incomplete.\n  - Discuss the potential consequences of adopting opposing viewpoints.\n  - Highlight any areas of consensus or common ground with opposing viewpoints.\n  - Offer a balanced perspective by considering alternative viewpoints.\n\nConclusion:\n- Summarize your argument and its significance.\n  - Recap the main points and evidence presented in the think piece.\n  - Reinforce the importance of your viewpoint.\n  - Highlight the broader implications of your argument for the field or society.\n  - Suggest any calls to action or next steps for readers.\n  - Offer a forward-looking perspective on the topic.\n  - Provide practical recommendations or insights based on your argument.\n- Encourage readers to reflect on the topic and form their own opinions.\n  - Invite readers to share their thoughts or engage in further discussion.\n  - Provide links to additional resources, articles, or studies for further reading.\n  - Include a call-to-action to explore related topics or participate in relevant activities.",
      "Hard News" => "Hard News Template\n\nHeadline:\n- Write a compelling headline that summarizes the main event or issue.\n  - Ensure the headline is concise and grabs the reader's attention.\n  - Include key details such as who, what, where, and when.\n\nIntroduction:\n- Summarize the main facts of the news story.\n  - Provide a brief overview of the event or issue.\n  - Mention the most important details, including who, what, where, when, why, and how.\n  - Highlight any immediate implications or significance of the event.\n  - Set the tone and urgency of the article.\n\nDetails:\n- Provide detailed information about the news event.\n  - Expand on the key details mentioned in the introduction.\n  - Include quotes from witnesses, officials, or experts.\n  - Provide background information to give context to the event.\n  - Mention any relevant statistics, data, or research findings.\n  - Discuss any related events or developments.\n  - Use visual elements like images, charts, or infographics to support the text.\n\nImpact and Reactions:\n- Discuss the impact of the event on the community, organization, or individuals involved.\n  - Include reactions from key stakeholders, such as government officials, experts, or affected individuals.\n  - Highlight any immediate actions taken or planned in response to the event.\n  - Provide insights into potential long-term implications or consequences.\n  - Compare reactions across different groups or regions.\n\nAnalysis and Commentary:\n- Offer analysis or commentary on the event.\n  - Discuss the broader context or significance of the event.\n  - Mention any trends, patterns, or precedents related to the event.\n  - Include expert opinions or perspectives to provide depth.\n  - Address any controversies or differing viewpoints surrounding the event.\n  - Explore potential future developments and their implications.\n\nConclusion:\n- Summarize the key points and details of the news story.\n  - Recap the significance of the event and its implications.\n  - Mention any ongoing developments or future updates to watch for.\n  - Provide a final thought or takeaway for the reader.\n- Encourage readers to stay informed about the topic.\n  - Suggest related articles or resources for further reading.\n  - Invite readers to share their thoughts or engage in discussion about the event.\n  - Include a call-to-action to follow up on any future developments or updates.",
      "First Person" => "First Person Template\n\nIntroduction:\n- Introduce the topic and your personal connection to it.\n  - Provide a brief overview of the topic or experience.\n  - Explain why this topic or experience is important to you.\n  - Set the context for your story by providing necessary background information.\n  - Pose any key questions or issues that your story will explore.\n  - Outline the structure of your narrative for the reader.\n\nMain Story:\n- Tell your personal story or experience.\n  - Describe the setting and characters involved.\n  - Provide a detailed account of the events or experiences.\n  - Include specific details, anecdotes, and sensory descriptions to bring your story to life.\n  - Highlight any challenges or obstacles you faced.\n  - Discuss your thoughts, feelings, and reactions during the events.\n  - Incorporate dialogue or quotes to add authenticity.\n  - Explore the turning points or significant moments that shaped your experience.\n  - Discuss any actions you took to overcome challenges or achieve goals.\n\nLessons Learned:\n- Reflect on the lessons you learned from your experience.\n  - Discuss any insights or realizations you gained.\n  - Explain how the experience impacted you personally.\n  - Mention any changes in your perspective or behavior as a result.\n  - Relate the lessons to broader themes or issues.\n  - Provide examples of how these lessons have influenced your life since the experience.\n\nBroader Context:\n- Provide context for your experience within a larger framework.\n  - Discuss any relevant cultural, social, or historical background.\n  - Connect your story to broader themes or trends.\n  - Include expert opinions or perspectives to add depth.\n  - Address any controversies or differing viewpoints related to your experience.\n  - Relate your personal experience to common experiences or societal issues.\n\nConclusion:\n- Summarize the key points and takeaways from your story.\n  - Recap the significance of your experience and its implications.\n  - Provide any final thoughts or reflections.\n  - Suggest any calls to action or next steps for readers.\n  - Encourage readers to reflect on their own experiences and share their thoughts.\n  - Provide links to additional resources, articles, or studies for further reading.\n  - Include a call-to-action to explore related topics or participate in relevant activities.",
      "Service Piece" => "Service Piece Template\n\nIntroduction:\n- Introduce the service and its importance.\n  - Provide a brief overview of the service.\n  - Explain why this service is important or relevant to the audience.\n  - Mention any recent trends or developments related to the service.\n  - Outline the structure of the piece for the reader.\n\nDetails:\n- Describe the service and how it works.\n  - Provide a step-by-step explanation of the service.\n  - Include specific features and functionalities.\n  - Mention any prerequisites or requirements for using the service.\n  - Provide visual aids like images, diagrams, or screenshots to illustrate key points.\n  - Highlight any unique aspects or innovations associated with the service.\n  - Discuss the technology or methodology behind the service.\n\nBenefits:\n- List the key benefits of the service.\n  - Discuss how the service addresses specific needs or problems.\n  - Provide examples or case studies demonstrating the benefits.\n  - Include testimonials or quotes from users or experts.\n  - Compare the service to similar options in the market.\n  - Highlight any cost savings or efficiency gains associated with the service.\n  - Explain how the service improves user experience or outcomes.\n\nUsage Tips:\n- Offer practical tips for using the service effectively.\n  - Provide best practices and recommendations.\n  - Discuss common mistakes to avoid.\n  - Include tips for troubleshooting common issues.\n  - Mention any additional resources or support available to users.\n  - Provide advanced tips for experienced users.\n\nUser Feedback:\n- Share feedback from users who have utilized the service.\n  - Include a mix of positive and constructive feedback.\n  - Discuss any recurring themes or common experiences.\n  - Highlight any improvements made based on user feedback.\n  - Provide insights from user reviews or surveys.\n  - Include quotes or stories that illustrate user satisfaction or challenges.\n\nConclusion:\n- Summarize the main points and benefits of the service.\n  - Recap why the service is valuable to the audience.\n  - Provide any final thoughts or reflections.\n  - Suggest any calls to action or next steps for readers.\n  - Encourage readers to try the service and share their experiences.\n  - Provide links to additional resources, articles, or the service website for further information.\n  - Include a call-to-action to explore related services or participate in relevant activities.",
      "Informational" => "Informational Template\n\nIntroduction:\n- Introduce the topic and its importance.\n  - Provide a brief overview of the topic.\n  - Explain why this topic is important or relevant to the audience.\n  - Mention any recent trends or developments related to the topic.\n  - Outline the structure of the piece for the reader.\n\nMain Points:\n- Present the main points and information.\n  - Break down the topic into key points or sections.\n  - Provide detailed explanations and insights for each point.\n  - Include relevant statistics, data, or research findings.\n  - Highlight any significant trends, patterns, or developments.\n  - Use visual aids like charts, graphs, or infographics to enhance understanding.\n  - Discuss the implications or impact of the information presented.\n  - Include quotes or insights from experts or stakeholders.\n  - Provide examples or case studies to illustrate key points.\n\nContext and Background:\n- Provide context and background information.\n  - Explain the history or origin of the topic.\n  - Discuss any relevant cultural, social, or historical background.\n  - Mention any key events or milestones related to the topic.\n  - Include any controversies or differing viewpoints.\n  - Discuss the evolution of the topic over time.\n\nPractical Applications:\n- Discuss the practical applications or relevance of the information.\n  - Explain how the information can be applied in real-life situations.\n  - Provide examples of how the information is used in practice.\n  - Mention any tools, techniques, or methods related to the topic.\n  - Discuss any potential benefits or challenges associated with the applications.\n  - Highlight any future trends or innovations related to the topic.\n\nConclusion:\n- Summarize the main points and takeaways from the piece.\n  - Recap the significance of the information and its implications.\n  - Provide any final thoughts or reflections.\n  - Suggest any calls to action or next steps for readers.\n  - Encourage readers to explore the topic further and provide additional resources or links for further reading.\n  - Invite readers to share their thoughts or engage in discussion about the topic.\n  - Include a call-to-action to stay informed about related topics or developments.\n  - Suggest ways for readers to apply the information in their own lives or work.",
      "Full Journey" => "Full Journey Template\n\nIntroduction:\n- Introduce the topic and its importance.\n  - Provide a brief overview of the topic.\n  - Explain why this topic is important or relevant to the audience.\n  - Mention any recent trends or developments related to the topic.\n  - Outline the structure of the piece for the reader.\n\nStages:\n- Awareness:\n  - Describe the top of the funnel stage, its goals, and strategies of the topic.\n  - Explain how the audience becomes aware of the topic.\n  - Discuss key tactics for capturing attention and generating interest.\n  - Provide examples of effective awareness strategies.\n  - Highlight common challenges and how to overcome them.\n- Consideration:\n  - Describe the middle of the funnel stage, its goals, and strategies of the given topic.\n  - Explain how the audience evaluates their options.\n  - Discuss key tactics for educating and nurturing prospects.\n  - Provide examples of effective consideration strategies.\n  - Highlight common challenges and how to overcome them.\n- Decision:\n  - Describe the bottom of the funnel stage, its goals, and strategies.\n  - Explain how the audience makes their final decision.\n  - Discuss key tactics for converting prospects into customers.\n  - Provide examples of effective decision strategies.\n  - Highlight common challenges and how to overcome them.\n\nDetailed Strategies:\n- Provide specific content strategies and templates for each stage.\n  - Awareness:\n    - Discuss content types such as blog posts, social media updates, and infographics.\n    - Provide tips for creating engaging and informative content.\n    - Highlight best practices for each content type.\n    - Include examples of successful awareness campaigns.\n  - Consideration:\n    - Discuss content types such as white papers, case studies, and webinars.\n    - Provide tips for creating educational and persuasive content.\n    - Highlight best practices for each content type.\n    - Include examples of successful consideration campaigns.\n  - Decision:\n    - Discuss content types such as product demos, customer testimonials, and pricing guides.\n    - Provide tips for creating convincing and compelling content.\n    - Highlight best practices for each content type.\n    - Include examples of successful decision campaigns.\n  - Include templates for each content type to facilitate creation.\n\nMeasurement and Analysis:\n- Explain how to measure the effectiveness of the content at each stage.\n  - Discuss key metrics to track for awareness, consideration, and decision stages.\n  - Provide tips for analyzing data and optimizing content strategies.\n  - Highlight tools and technologies that can aid in measurement and analysis.\n  - Discuss the importance of continuous improvement based on insights.\n  - Provide examples of how to adjust strategies based on data analysis.\n\nConclusion:\n- Summarize the full journey strategy and its benefits.\n  - Recap the importance of understanding and addressing each stage of the journey.\n  - Provide any final thoughts or reflections.\n  - Suggest any calls to action or next steps for readers.\n  - Encourage readers to implement the strategies discussed and track their progress.\n  - Provide links to additional resources, articles, or case studies for further reading.\n  - Invite readers to share their thoughts or engage in discussion about the topic.\n  - Include a call-to-action to stay informed about related topics or developments.\n  - Suggest ways for readers to apply the information in their own lives or work.",
      "Awareness" => "Awareness Template\n\nIntroduction:\n- Introduce the topic and its importance.\n  - Provide a brief overview of the topic.\n  - Explain why this topic is important or relevant to the audience.\n  - Mention any recent trends or developments related to the topic.\n  - Outline the structure of the piece for the reader.\n\nGoals:\n- Increase brand awareness.\n  - Describe how the topic contributes to raising awareness of a brand or issue.\n  - Highlight the benefits of increased awareness for the brand or issue.\n- Improve customer education.\n  - Explain how the topic helps educate potential customers.\n  - Discuss the importance of customer education in the awareness stage.\n\nContent Approaches:\n- Describe different approaches to creating awareness content.\n  - Discuss the importance of storytelling in making the content relatable.\n  - Highlight the role of visuals, such as images and videos, in engaging the audience.\n  - Mention the effectiveness of using real-life examples and case studies to illustrate key points.\n\nEngagement Techniques:\n- Explain how to engage the audience effectively.\n  - Discuss the use of interactive elements such as polls, quizzes, and surveys.\n  - Highlight the importance of addressing audience questions and feedback.\n  - Provide tips for encouraging audience participation and sharing.\n\nMeasuring Impact:\n- Explain how to measure the impact of awareness content.\n  - Discuss key metrics to track, such as engagement rates and audience reach.\n  - Provide tips for analyzing data to assess the effectiveness of the content.\n  - Highlight tools and methods for gathering audience feedback.\n\nConclusion:\n- Summarize the importance of awareness in the customer journey.\n  - Recap the key points discussed in the piece.\n  - Provide any final thoughts or reflections.\n  - Suggest any calls to action or next steps for readers.\n  - Encourage readers to apply the insights gained from the piece in their own work.\n  - Invite readers to share their thoughts or experiences related to the topic.\n  - Include a call-to-action to stay informed about related topics or developments.",
      "Consideration" => "Consideration Template\n\nIntroduction:\n- Introduce the topic and its importance in the buyer's journey.\n  - Provide a brief overview of the topic.\n  - Explain why this stage is crucial for converting potential customers.\n  - Mention any recent trends or developments related to the topic.\n  - Outline the structure of the piece for the reader.\n\nGoals:\n- Foster customer engagement.\n  - Describe how the topic helps to engage potential customers.\n  - Highlight the benefits of increased engagement for the brand or issue.\n- Establish authority and trust.\n  - Explain how the topic helps to build trust and credibility with potential customers.\n  - Discuss the importance of establishing authority in the consideration stage.\n- Improve customer education.\n  - Explain how the topic helps educate potential customers.\n  - Discuss the importance of customer education in the consideration stage.\n\nContent Approaches:\n- Describe different approaches to creating consideration content.\n  - Discuss the importance of providing detailed, informative content that addresses customer needs.\n  - Highlight the role of testimonials, case studies, and expert opinions in building trust.\n  - Mention the effectiveness of using comparisons and product demonstrations to illustrate key points.\n  - Include detailed walkthroughs or guides on using the product or service.\n  - Provide comprehensive FAQs addressing common customer questions and concerns.\n\nEngagement Techniques:\n- Explain how to engage the audience effectively in the consideration stage.\n  - Discuss the use of interactive elements such as webinars, Q&A sessions, and live demos.\n  - Highlight the importance of addressing audience questions and feedback.\n  - Provide tips for encouraging audience participation and deeper exploration of the topic.\n  - Suggest ways to personalize interactions to better address individual customer needs.\n\nMeasuring Impact:\n- Explain how to measure the impact of consideration content.\n  - Discuss key metrics to track, such as engagement rates and conversion rates.\n  - Provide tips for analyzing data to assess the effectiveness of the content.\n  - Highlight tools and methods for gathering audience feedback.\n  - Discuss how to use feedback to refine and improve content strategies.\n  - Provide examples of how to adjust strategies based on data analysis.\n\nConclusion:\n- Summarize the importance of the consideration stage in the buyer's journey.\n  - Recap the key points discussed in the piece.\n  - Provide any final thoughts or reflections.\n  - Suggest any calls to action or next steps for readers.\n  - Encourage readers to apply the insights gained from the piece in their own work.\n  - Invite readers to share their thoughts or experiences related to the topic.\n  - Include a call-to-action to stay informed about related topics or developments.",
      "Decision" => "Decision Template\n\nIntroduction:\n- Introduce the topic and its importance in the buyer's journey.\n  - Provide a brief overview of the topic.\n  - Explain why this stage is crucial for converting potential customers into buyers.\n  - Mention any recent trends or developments related to the topic.\n  - Outline the structure of the piece for the reader.\n\nGoals:\n- Generate leads and conversions.\n  - Describe how the topic helps to convert potential customers into leads and buyers.\n  - Highlight the benefits of increased conversions for the brand or issue.\n- Boost conversion rates.\n  - Explain how the topic helps to improve conversion rates.\n  - Discuss the importance of optimizing the decision stage for better conversions.\n- Nurture leads.\n  - Explain how the topic helps to nurture leads towards making a purchase decision.\n  - Discuss the importance of lead nurturing in the decision stage.\n\nContent Approaches:\n- Describe different approaches to creating decision content.\n  - Discuss the importance of providing detailed, persuasive content that addresses customer needs and concerns.\n  - Highlight the role of product demos, free trials, and customer testimonials in influencing purchase decisions.\n  - Mention the effectiveness of using case studies, ROI calculations, and pricing guides to illustrate key points.\n  - Include detailed comparisons of product/service features and benefits.\n  - Provide comprehensive FAQs addressing common customer questions and concerns.\n  - Offer exclusive deals, discounts, or limited-time offers to incentivize purchases.\n  - Highlight the importance of clear and transparent communication in decision content.\n  - Discuss the role of trust-building elements, such as security assurances and guarantees.\n\nEngagement Techniques:\n- Explain how to engage the audience effectively in the decision stage.\n  - Discuss the use of interactive elements such as live demos, personalized consultations, and one-on-one meetings.\n  - Highlight the importance of addressing audience questions and feedback promptly.\n  - Provide tips for encouraging audience participation and deeper exploration of the topic.\n  - Suggest ways to personalize interactions to better address individual customer needs and preferences.\n  - Emphasize the importance of follow-up communication to maintain engagement.\n\nMeasuring Impact:\n- Explain how to measure the impact of decision content.\n  - Discuss key metrics to track, such as conversion rates, lead-to-customer ratio, and sales growth.\n  - Provide tips for analyzing data to assess the effectiveness of the content.\n  - Highlight tools and methods for gathering audience feedback and sales data.\n  - Discuss how to use feedback to refine and improve content strategies.\n  - Provide examples of how to adjust strategies based on data analysis.\n  - Emphasize the importance of continuous monitoring and optimization.\n\nConclusion:\n- Summarize the importance of the decision stage in the buyer's journey.\n  - Recap the key points discussed in the piece.\n  - Provide any final thoughts or reflections.\n  - Suggest any calls to action or next steps for readers.\n  - Encourage readers to apply the insights gained from the piece in their own work.\n  - Invite readers to share their thoughts or experiences related to the topic.\n  - Include a call-to-action to stay informed about related topics or developments.",
    ];
  }

  private function generate_image_from_prompt($content)
  {
    // Define the URL to your Node.js API endpoint
    $url = 'https://ai.1upmedia.com:443/generate-image-from-prompt';

    // Prepare the request body
    $request_body = wp_json_encode([
      'content' => $content
    ]);

    // Make the HTTP request to your Node.js API
    $response = wp_remote_post($url, [
      'headers' => [
        'Content-Type' => 'application/json'
      ],
      'body' => $request_body,
      'timeout' => 180
    ]);

    // Check if the request failed
    if (is_wp_error($response)) {
      throw new Exception('API request for image generation failed: ' . esc_html($response->get_error_message()));
    }

    // Retrieve and decode the response body
    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    // Validate the response data
    if (!isset($data['image']['data'])) {
      throw new Exception('Invalid API response for image generation');
    }

    // Decode the base64 image data
    $image_data = base64_decode($data['image']['data']);
    $image_title = $data['image']['title'];
    $image_alt = $data['image']['alt'];

    return [
      'data' => $image_data,
      'title' => $image_title,
      'alt' => $image_alt,
      'description' => "Generated Image"
    ];
  }


  private function classify_funnel_stage($content)
  {
    // Define the URL to your Node.js API endpoint
    $url = 'https://ai.1upmedia.com:443/classify-funnel-stage';

    // Prepare the request body
    $request_body = wp_json_encode([
      'content' => $content
    ]);

    // Make the HTTP request to your Node.js API
    $response = wp_remote_post($url, [
      'headers' => [
        'Content-Type' => 'application/json'
      ],
      'body' => $request_body,
      'timeout' => 180
    ]);

    // Check if the request failed
    if (is_wp_error($response)) {
      throw new Exception('API request for funnel stage classification failed: ' . esc_html($response->get_error_message()));
    }

    // Retrieve and decode the response body
    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    // Validate the response data
    if (!isset($data['stage'])) {
      throw new Exception('Invalid API response for funnel stage classification');
    }

    // Return the stage (MoF, ToF, or BoF)
    return sanitize_text_field($data['stage']);
  }

  // private function generate_image_from_prompt($content)
  // {
  //   $prompt_image = "Generate an image related to the following content and provide an alt text for it:\nContent: {$content}";

  //   $request_body_image = wp_json_encode([
  //     'model' => 'dall-e-3',
  //     'prompt' => $content,
  //     'n' => 1,
  //     'size' => '1024x1024',
  //     'response_format' => 'b64_json'
  //   ]);

  //   $response_image = wp_remote_post('https://api.openai.com/v1/images/generations', [
  //     'headers' => [
  //       'Authorization' => 'Bearer ' . $this->api_key,
  //       'Content-Type' => 'application/json'
  //     ],
  //     'body' => $request_body_image,
  //     'timeout' => 180
  //   ]);

  //   if (is_wp_error($response_image)) {
  //     throw new Exception('API request for image generation failed: ' . $response_image->get_error_message());
  //   }

  //   $body_image = wp_remote_retrieve_body($response_image);
  //   $data_image = json_decode($body_image, true);

  //   if (!isset($data_image['data'][0]['b64_json'])) {
  //     throw new Exception('Invalid API response for image generation');
  //   }

  //   $image_data = base64_decode($data_image['data'][0]['b64_json']);
  //   $image_alt = $this->generate_image_alt_text($content);
  //   $image_title = $image_alt;

  //   return [
  //     'data' => $image_data,
  //     'title' => $image_title,
  //     'alt' => $image_alt,
  //     'description' => "Generated Image"
  //   ];
  // }
  private function generate_image_alt_text($content)
  {
    $url = 'https://ai.1upmedia.com:443/generate-image-alt-text';

    $request_body = wp_json_encode([
      'content' => $content
    ]);

    $response = wp_remote_post($url, [
      'headers' => [
        'Content-Type' => 'application/json'
      ],
      'body' => $request_body,
      'timeout' => 60
    ]);

    if (is_wp_error($response)) {
      throw new Exception('API request for image alt text failed: ' . esc_html($response->get_error_message()));
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    if (!isset($data['altText'])) {
      throw new Exception('Invalid API response for image alt text: ' . esc_html(print_r($data, true)));
    }

    return $this->remove_surrounding_quotes(trim($data['altText']));
  }

  private function set_featured_image($post_id, $image_info)
  {
    $upload_dir = wp_upload_dir();
    $image_data = $image_info['data'];
    $image_title = sanitize_text_field($image_info['title']);
    $image_alt = sanitize_text_field($image_info['alt']);
    $image_description = sanitize_text_field($image_info['description']);
    $filename = uniqid() . '.png';

    // Initialize the WordPress filesystem
    require_once(ABSPATH . 'wp-admin/includes/file.php');
    WP_Filesystem();
    global $wp_filesystem;

    if (wp_mkdir_p($upload_dir['path'])) {
      $file = $upload_dir['path'] . '/' . $filename;
    } else {
      $file = $upload_dir['basedir'] . '/' . $filename;
    }

    // Use WP_Filesystem to write the file
    $wp_filesystem->put_contents($file, $image_data, FS_CHMOD_FILE);

    $wp_filetype = wp_check_filetype($filename, null);
    $attachment = [
      'post_mime_type' => $wp_filetype['type'],
      'post_title' => $image_title,
      'post_content' => $image_description,
      'post_status' => 'inherit'
    ];

    $attach_id = wp_insert_attachment($attachment, $file, $post_id);

    require_once(ABSPATH . 'wp-admin/includes/image.php');

    $attach_data = wp_generate_attachment_metadata($attach_id, $file);
    wp_update_attachment_metadata($attach_id, $attach_data);

    update_post_meta($post_id, '_wp_attachment_image_alt', $image_alt);
    update_post_meta($attach_id, '_wp_attachment_image_alt', $image_alt);
    set_post_thumbnail($post_id, $attach_id);
  }


  private function sanitize_generated_content($content)
  {
    // Remove unwanted HTML tags using regex
    $content = preg_replace('/<meta[^>]+>/i', '', $content);
    $content = preg_replace('/<!doctype[^>]+>/i', '', $content);

    // Remove all br tags within p tags
    $content = preg_replace('/<p>\s*(<br\s*\/?>\s*)+\s*<\/p>/', '', $content);

    // Allow only certain HTML tags and attributes
    $allowed_html = [
      'p' => [],
      'h1' => [],
      'h2' => [],
      'h3' => [],
      'ul' => [],
      'ol' => [],
      'li' => [],
      'a' => [
        'href' => [],
      ],
      'strong' => [],
      'em' => [],
      'br' => [],
    ];

    $content = wp_kses($content, $allowed_html);

    // Remove any leading/trailing whitespace from each line
    $content = implode("\n", array_map('trim', explode("\n", $content)));

    // Remove multiple consecutive line breaks
    $content = preg_replace('/(\s*<br\s*\/?>\s*){2,}/', '<br>', $content);

    // Remove leading and trailing whitespace from the entire content
    $content = trim($content);

    // Ensure there are no consecutive blank lines
    $content = preg_replace('/(\r?\n){2,}/', "\n", $content);

    return $content;
  }

  public function find_related_posts()
  {
    if (!isset($_POST['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'my_generic_action')) {
      wp_send_json_error(['message' => 'Invalid nonce']);
      return;
    }

    $post_id = isset($_POST['post_id']) ? intval(wp_unslash($_POST['post_id'])) : 0;

    if ($post_id === 0) {
      wp_send_json_error(['message' => 'Invalid post ID']);
      return;
    }

    $new_post = get_post($post_id);

    if (!$new_post) {
      wp_send_json_error(['message' => 'Invalid post ID']);
    }

    $new_post_embedding = json_decode(get_post_meta($new_post->ID, 'content_embedding', true));
    if (!$new_post_embedding) {
      $new_post_embedding = $this->generate_embedding($new_post->post_content);
      update_post_meta($new_post->ID, 'content_embedding', wp_json_encode($new_post_embedding));
    }
    // Retrieve all posts with embeddings
    // usage of meta query for fetching is mandatory
    $all_posts = get_posts([
      'post_type' => 'post',
      'numberposts' => -1,
      'post_status' => ['publish', 'draft', 'pending', 'future', 'private'],
      'meta_query' => [
        [
          'key' => 'content_embedding',
          'compare' => 'EXISTS',
        ],
      ],
    ]);

    $content_embeddings = [];
    foreach ($all_posts as $post) {
      $content_embeddings[$post->ID] = json_decode(get_post_meta($post->ID, 'content_embedding', true));
    }

    $similarities = [];
    foreach ($all_posts as $post) {
      if ($post->ID !== $post_id) {
        $similarity = $this->cosine_similarity($new_post_embedding, $content_embeddings[$post->ID]);
        if ($similarity > 0) {
          $similarities[$post->ID] = $similarity;
        }
      }
    }

    arsort($similarities);

    $related_posts = array_slice(array_keys($similarities), 0, 5, true);

    ob_start();
    echo '<ul>';
    foreach ($related_posts as $related_post_id) {
      $related_post = get_post($related_post_id);
      $similarity_percentage = round($similarities[$related_post_id] * 100, 2);
      echo '<li><a href="' . esc_url(get_permalink($related_post_id)) . '">' . esc_html($related_post->post_title) . ' (' . esc_html($similarity_percentage) . '% match)</a>';
      echo ' <button type="button" class="button button-small add-to-reference" data-link="' . esc_url(get_permalink($related_post_id)) . '" data-title="' . esc_attr($related_post->post_title) . '">Add to Reference</button></li>';
    }
    echo '</ul>';
    $html = ob_get_clean();

    wp_send_json_success(['html' => $html]);
  }




  function handle_get_preview_titles()
  {
    ini_set('memory_limit', '256M'); // Increase memory limit

    // Check the nonce for security
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'my_generic_action')) {
      wp_send_json_error(['error' => 'Invalid nonce']);
      return;
    }
    $existing_titles = isset($_POST['existing_titles']) ? array_map('sanitize_text_field', $_POST['existing_titles']) : [];
    $title = isset($_POST['title']) ? sanitize_text_field(wp_unslash($_POST['title'])) : '';
    $content_strategy = isset($_POST['content_strategy']) ? sanitize_text_field(wp_unslash($_POST['content_strategy'])) : '';
    $goal = isset($_POST['goal']) ? sanitize_text_field(wp_unslash($_POST['goal'])) : '';
    $goal_weightage = isset($_POST['goal_weightage']) ? array_map('sanitize_text_field', wp_unslash($_POST['goal_weightage'])) : [];
    $target_audience = isset($_POST['target_audience']) ? sanitize_text_field(wp_unslash($_POST['target_audience'])) : '';
    $keywords = isset($_POST['keywords']) ? sanitize_text_field(wp_unslash($_POST['keywords'])) : '';
    $search_intent = isset($_POST['search_intent']) ? sanitize_text_field(wp_unslash($_POST['search_intent'])) : '';
    $search_intent_weightage = isset($_POST['search_intent_weightage']) ? array_map('sanitize_text_field', wp_unslash($_POST['search_intent_weightage'])) : [];
    $tone = isset($_POST['tone']) ? sanitize_text_field(wp_unslash($_POST['tone'])) : '';
    $content_type = isset($_POST['content_type']) ? sanitize_text_field(wp_unslash($_POST['content_type'])) : '';
    $content_type_weightage = isset($_POST['content_type_weightage']) ? array_map('sanitize_text_field', wp_unslash($_POST['content_type_weightage'])) : [];
    $number_of_pieces = isset($_POST['number_of_pieces']) ? intval(wp_unslash($_POST['number_of_pieces'])) : 0;
    $language = isset($_POST['language']) ? sanitize_text_field(wp_unslash($_POST['language'])) : 'English';


    if ($goal === 'Random') {
      if (empty($goal_weightage)) {
        $all_goals = [
          "Generate Leads",
          "Enhance SEO Performance",
          "Establish Authority and Trust",
          "Increase Brand Awareness",
          "Foster Customer Engagement",
          "Improve Customer Education",
          "Boost Conversion Rates",
          "Nurture Leads"
        ];

        foreach ($all_goals as $g) {
          $goal_weightage[$g] = 1;
        }
      }
      $goals = array_keys($goal_weightage);
    } else {
      $goals = [$goal];
    }



    $this->updateProgressToPoll("checking search intent..");
    // Random selection logic for search intents
    if ($search_intent === 'Random') {
      if (empty($search_intent_weightage)) {
        $all_search_intents = ["Informational", "Navigational", "Commercial", "Transactional", "Local"];
        foreach ($all_search_intents as $intent) {
          $search_intent_weightage[$intent] = 1;
        }
      }
      $search_intents = array_keys($search_intent_weightage);
    } else {
      $search_intents = [$search_intent];
    }


    $this->updateProgressToPoll("checking content type..");
    // If content_type is Random and no content type weightage is provided, assign equal weightage to all content types
    if ($content_type === 'Random') {
      if (empty($content_type_weightage)) {
        // Use the keys from the content types option (which are the names of the content types)
        $content_type_weightage = array_fill_keys(array_keys(get_option('ai_content_pipelines_oneup_author_personas_content_types', [])), 1);
      }
      // Get the content type names from the weightage array keys
      $content_types = array_keys($content_type_weightage);
    } else {
      // If not 'Random', just use the selected content type
      $content_types = [$content_type];
    }


    function get_weighted_random_item($items, $weightages)
    {
      $total_weight = array_sum($weightages);
      $random_weight = wp_rand(0, $total_weight - 1);

      foreach ($items as $item) {
        if ($random_weight < $weightages[$item]) {
          return $item;
        }
        $random_weight -= $weightages[$item];
      }

      return $items[array_rand($items)];
    }


    if ($content_type === 'Random') {
      $selected_content_type = get_weighted_random_item($content_types, $content_type_weightage);
    } else {
      $selected_content_type = $content_type;
    }

    if ($goal === 'Random') {
      $selected_goal = get_weighted_random_item($goals, $goal_weightage);
    } else {
      $selected_goal = $goal;
    }

    if ($search_intent === 'Random') {
      $selected_search_intent = get_weighted_random_item($search_intents, $search_intent_weightage);
    } else {
      $selected_search_intent = $search_intent;
    }
    $template_styles = $this->get_template_styles();

    $content_type_template = isset($template_styles[$selected_content_type]) ? $template_styles[$selected_content_type] : '';


    // Add content strategy, goal, and target audience details
    $prompt = "Main title: {$title}";
    $prompt .= "Content Strategy: {$content_strategy}\n";
    $prompt .= "Goal: {$selected_goal}\n";
    $prompt .= "Target Audience: {$target_audience}\n";

    // Add keywords and search intent
    $prompt .= "Keywords: {$keywords}\n";
    $prompt .= "Search Intent: {$selected_search_intent}\n";

    // Add tone and content type
    $prompt .= "Tone: {$tone}\n";
    $prompt .= "Content Type: {$selected_content_type}\n\n";

    if (strtolower($language) !== 'english') {
      $prompt .= "\n(!IMPORTANT -> Please generate the content in {$language} language.)\n";
    }

    if (!empty($existing_titles)) {
      $existing_titles_list = implode(", ", $existing_titles);
      $prompt .= "\nPlease avoid these titles: {$existing_titles_list}\n";
    }
    // Provide instructions for generating titles

    // Log the prompt (optional for debugging)
    // $this->updateProgressToPoll("Generated prompt: {$prompt}");

    // Now you can use this $prompt in your API request to OpenAI





    // Prepare the data for the API request
    $api_url = 'https://ai.1upmedia.com:443/get-preview-titles';
    $post_data = json_encode([
      'count' => $number_of_pieces,
      'prompt' => $prompt
    ]);

    // Send the request to the external Node.js backend
    $response = wp_remote_post($api_url, [
      'body' => $post_data,
      'headers' => [
        'Content-Type' => 'application/json'
      ],
      'timeout' => 450
    ]);

    // Check for errors in the response
    if (is_wp_error($response)) {
      wp_send_json_error(['error' => 'Failed to fetch titles from the API']);
      return;
    }

    // Decode the response body
    $response_body = wp_remote_retrieve_body($response);
    $titles_data = json_decode($response_body, true);

    if (empty($titles_data['titles'])) {
      wp_send_json_error(['error' => 'No titles generated']);
      return;
    }

    // Use preg_match_all to extract all titles wrapped in double quotes
    preg_match_all('/\d+\.\s*"([^"]+)"/', $titles_data['titles'], $matches);

    if (empty($matches[1])) {
      wp_send_json_error(['error' => 'No valid titles found']);
      return;
    }

    // Remove leading numbers and escape titles
    $titles = array_map(function ($title) {
      // Trim whitespace and escape any special characters
      return htmlspecialchars(trim($title), ENT_QUOTES);
    }, $matches[1]);

    // Send the cleaned titles back to the frontend
    wp_send_json_success(['titles' => $titles]);



  }

  function generate_full_workflow_content_with_previewed_titles()
  {
    ini_set('memory_limit', '256M'); // Increase memory limit
    $this->updateProgressToPoll("Full workflow contents started generating...");

    if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'my_generic_action')) {
      update_option('ai_content_pipelines_oneup_content_generation_in_progress', true);
      update_option('ai_content_pipelines_oneup_is_last_error', true);
      $this->updateProgressToPoll("Nonce error");
      update_option('ai_content_pipelines_oneup_content_generation_in_progress', false);
      wp_send_json_error('Invalid nonce');
      return;
    }

    $this->updateProgressToPoll("Nonce successful");

    update_option('ai_content_pipelines_oneup_content_generation_in_progress', true);
    update_option('ai_content_pipelines_oneup_is_last_error', false);

    try {
      // Fetch and sanitize the incoming data
      $goal = isset($_POST['goal']) ? sanitize_text_field(wp_unslash($_POST['goal'])) : '';
      $goal_weightage = isset($_POST['goal_weightage']) ? array_map('sanitize_text_field', wp_unslash($_POST['goal_weightage'])) : [];
      $target_audience = isset($_POST['target_audience']) ? sanitize_text_field(wp_unslash($_POST['target_audience'])) : '';
      $keywords = isset($_POST['keywords']) ? sanitize_text_field(wp_unslash($_POST['keywords'])) : '';
      $search_intent = isset($_POST['search_intent']) ? sanitize_text_field(wp_unslash($_POST['search_intent'])) : '';
      $search_intent_weightage = isset($_POST['search_intent_weightage']) ? array_map('sanitize_text_field', wp_unslash($_POST['search_intent_weightage'])) : [];
      $links = isset($_POST['link_text']) && isset($_POST['link_url']) ?
        $this->sanitize_links(
          array_map('sanitize_text_field', wp_unslash($_POST['link_text'])),
          array_map('esc_url_raw', wp_unslash($_POST['link_url']))
        ) : [];
      $word_count = isset($_POST['word_count']) ? intval(wp_unslash($_POST['word_count'])) : 0;
      $tone = isset($_POST['tone']) ? sanitize_text_field(wp_unslash($_POST['tone'])) : '';
      $content_type = isset($_POST['content_type']) ? sanitize_text_field(wp_unslash($_POST['content_type'])) : '';
      $content_type_weightage = isset($_POST['content_type_weightage']) ? array_map('sanitize_text_field', wp_unslash($_POST['content_type_weightage'])) : [];
      $schedule = isset($_POST['schedule']) ? sanitize_text_field(wp_unslash($_POST['schedule'])) : gmdate('Y-m-d');
      $schedule_interval = isset($_POST['schedule_interval']) ? sanitize_text_field(wp_unslash($_POST['schedule_interval'])) : '';
      $post_status = isset($_POST['post_status']) ? sanitize_text_field(wp_unslash($_POST['post_status'])) : '';
      $author = isset($_POST['author']) ? sanitize_text_field(wp_unslash($_POST['author'])) : '';
      $user_weightage = isset($_POST['user_weightage']) ? array_map('sanitize_text_field', wp_unslash($_POST['user_weightage'])) : [];
      $content_strategy = isset($_POST['content_strategy']) ? sanitize_text_field(wp_unslash($_POST['content_strategy'])) : '';
      $admin_email = isset($_POST['admin_email']) ? sanitize_email(wp_unslash($_POST['admin_email'])) : '';
      $titles_to_generate = isset($_POST['titles_to_generate']) ? array_map('sanitize_text_field', wp_unslash($_POST['titles_to_generate'])) : [];
      $language = isset($_POST['language']) ? sanitize_text_field(wp_unslash($_POST['language'])) : 'English';
      // Validate that titles are provided
      if (empty($titles_to_generate)) {
        wp_send_json_error(['message' => 'No titles to generate content for.']);
        return;
      }

      $this->updateProgressToPoll("Getting Image data...");
      // Process image data

      $this->updateProgressToPoll("checking goals..");
      // Random selection logic for goals
      if ($goal === 'Random') {
        if (empty($goal_weightage)) {
          $all_goals = [
            "Generate Leads",
            "Enhance SEO Performance",
            "Establish Authority and Trust",
            "Increase Brand Awareness",
            "Foster Customer Engagement",
            "Improve Customer Education",
            "Boost Conversion Rates",
            "Nurture Leads"
          ];

          foreach ($all_goals as $g) {
            $goal_weightage[$g] = 1;
          }
        }
        $goals = array_keys($goal_weightage);
      } else {
        $goals = [$goal];
      }

      $this->updateProgressToPoll("checking search intent..");
      // Random selection logic for search intents
      if ($search_intent === 'Random') {
        if (empty($search_intent_weightage)) {
          $all_search_intents = ["Informational", "Navigational", "Commercial", "Transactional", "Local"];
          foreach ($all_search_intents as $intent) {
            $search_intent_weightage[$intent] = 1;
          }
        }
        $search_intents = array_keys($search_intent_weightage);
      } else {
        $search_intents = [$search_intent];
      }

      $this->updateProgressToPoll("checking content type..");
      // If content_type is Random and no content type weightage is provided, assign equal weightage to all content types
      if ($content_type === 'Random') {
        if (empty($content_type_weightage)) {
          // Use the keys from the content types option (which are the names of the content types)
          $content_type_weightage = array_fill_keys(array_keys(get_option('ai_content_pipelines_oneup_author_personas_content_types', [])), 1);
        }
        // Get the content type names from the weightage array keys
        $content_types = array_keys($content_type_weightage);
      } else {
        // If not 'Random', just use the selected content type
        $content_types = [$content_type];
      }

      // If author is Random and no users are selected, assign equal weightage to all users
      if ($author === 'Random') {
        if (empty($user_weightage)) {
          $all_users = get_users();
          foreach ($all_users as $user) {
            $user_weightage[$user->ID] = 1;
          }
        }
        $users = array_keys($user_weightage);
      } else {
        $users = [$author];
      }
      // Logic for random selection of goals, search intents, content types, and authors

      $responses = [];
      $failed_contents = [];
      $retries = 2;

      // Function to randomly select based on weightage
      function get_weighted_random_item($items, $weightages)
      {
        $total_weight = array_sum($weightages);
        $random_weight = wp_rand(0, $total_weight - 1);

        foreach ($items as $item) {
          if ($random_weight < $weightages[$item]) {
            return $item;
          }
          $random_weight -= $weightages[$item];
        }

        return $items[array_rand($items)];
      }

      $template_styles = $this->get_template_styles();

      // Generate content for each title provided in 'titles_to_generate'
      foreach ($titles_to_generate as $index => $generated_title) {
        $scheduled_time = $this->calculate_scheduled_time($schedule, $schedule_interval, $index);

        if ($content_type === 'Random') {
          $selected_content_type = get_weighted_random_item($content_types, $content_type_weightage);
        } else {
          $selected_content_type = $content_type;
        }

        if ($goal === 'Random') {
          $selected_goal = get_weighted_random_item($goals, $goal_weightage);
        } else {
          $selected_goal = $goal;
        }

        if ($search_intent === 'Random') {
          $selected_search_intent = get_weighted_random_item($search_intents, $search_intent_weightage);
        } else {
          $selected_search_intent = $search_intent;
        }

        if ($author === 'Random') {
          $author_id = get_weighted_random_item($users, $user_weightage);
        } else {
          $author_id = intval($author);
        }

        if (!$author_id) {
          throw new Exception('No valid author ID selected');
        }

        try {
          $content_type_template = isset($template_styles[$selected_content_type]) ? $template_styles[$selected_content_type] : '';
          $prompt = $this->build_titled_workflow_prompt($generated_title, $selected_goal, $target_audience, $keywords, $selected_search_intent, $links, $word_count, $tone, $selected_content_type, $content_strategy, $content_type_template, $language);
          $this->updateProgressToPoll("Generating content for title: $generated_title");
          $content = $this->generate_content_from_prompt($prompt);
          $category = $this->generate_category_from_prompt($content);
          $tags = $this->get_tags_from_prompt($content);
          $image_info = $this->generate_image_from_prompt($generated_title);
          $post_id = $this->schedule_content($content, $scheduled_time, $generated_title, $category, $image_info, $post_status, $author_id, $selected_content_type, $tags, $admin_email);
          $responses[] = ['ID' => $post_id, 'content' => $content, 'category' => $category];

          $this->updateProgressToPoll("Content for title '$generated_title' scheduled successfully.");
        } catch (AI_Content_Pipelines_Oneup_QuotaExceededException $e) {
          update_option('ai_content_pipelines_oneup_content_generation_in_progress', false);
          update_option('ai_content_pipelines_oneup_last_scheduled_timestamp', time());
          update_option('ai_content_pipelines_oneup_is_last_error', true);
          wp_send_json_error(['type' => 'QuotaExceeded', 'message' => $e->getMessage()]);
          return;
        } catch (Exception $e) {
          $failed_contents[] = [
            'prompt' => $prompt,
            'scheduled_time' => $scheduled_time,
            'retries' => $retries,
            'post_status' => $post_status,
            'author' => $author_id
          ];
          $this->updateProgressToPoll("Content $generated_title failed to schedule, retrying..." . $e->getMessage());
        }
      }

      // Interlink the content
      $this->updateProgressToPoll("Interlinking in progress....");
      $this->interlink_content();
      if (count($failed_contents) > 0) {
        $this->updateProgressToPoll("Few contents were unsuccessful");
        update_option('ai_content_pipelines_oneup_content_generation_in_progress', false);
        update_option('ai_content_pipelines_oneup_last_scheduled_timestamp', time());
        update_option('ai_content_pipelines_oneup_is_last_error', true);
        wp_send_json_error(['message' => count($failed_contents) . 'contents could not be scheduled.', 'responses' => $responses, 'failed_contents' => $failed_contents]);
      } else {
        $this->updateProgressToPoll("Task completed successfully");
        update_option('ai_content_pipelines_oneup_content_generation_in_progress', false);
        update_option('ai_content_pipelines_oneup_is_last_error', false);
        update_option('ai_content_pipelines_oneup_last_scheduled_timestamp', time());
        wp_send_json_success(['message' => 'Content scheduled successfully', 'responses' => $responses]);
      }
    } catch (Exception $e) {
      $this->updateProgressToPoll("Few contents were unsuccessful" . $e->getMessage());
      update_option('ai_content_pipelines_oneup_content_generation_in_progress', false);
      update_option('ai_content_pipelines_oneup_last_scheduled_timestamp', time());
      update_option('ai_content_pipelines_oneup_is_last_error', true);
      wp_send_json_error(['message' => $e->getMessage()]);
    }
  }

  private function build_titled_workflow_prompt($title, $goal, $target_audience, $keywords, $search_intent, $links, $word_count, $tone, $content_type, $content_strategy, $content_type_prompt_template = "", $language = "English")
  {
    // Convert the $links array into a formatted string
    $formatted_links = '';
    if (!empty($links) && is_array($links)) {
      foreach ($links as $link_text => $link_url) {
        $formatted_links .= $link_text . ' (' . $link_url . '), ';
      }
      // Remove the last comma and space
      $formatted_links = rtrim($formatted_links, ', ');
    }

    $prompt = "";

    if (strtolower($language) !== 'english') {
      $prompt .= "\n(!IMPORTANT -> Please generate the content in {$language} language.)\n";
    }
    // Construct the prompt
    $prompt .= "Generate content for the following prompt and output the content in HTML format without the Main Title or H1 Heading (As I already have a main title, generate sub-titles if needed):\n";
    $prompt .= "Title: " . $title . "\n";
    $prompt .= "Goal: " . $goal . "\n";
    $prompt .= "Target Audience: " . $target_audience . "\n";
    $prompt .= "Keywords (Use these keywords to Boost SEO): " . $keywords . "\n";
    $prompt .= "Search Intent: " . $search_intent . "\n";
    $prompt .= "Links (use these links in relevant places to Boost SEO): " . $formatted_links . "\n";
    $prompt .= "Word Count: " . $word_count . "\n";
    $prompt .= "Tone: " . $tone . "\n";
    $prompt .= "Content Strategy: " . $content_strategy . "\n";
    $prompt .= "Content Type: " . $content_type . "\n";

    // If a content type prompt template is provided, append it to the prompt
    if (!empty($content_type_prompt_template)) {
      $prompt .= "Template: " . $content_type_prompt_template . "\n";
    }

    return $prompt;
  }


  function get_remaining_credits_action()
  {
    // Get API key and site URL
    $api_key = get_option('ai_content_pipelines_oneup_openai_api_key');
    $site_url = get_site_url();

    // API URL of the Node.js backend
    $api_url = 'https://ai.1upmedia.com:443/get-remaining-credits'; // Replace with the actual IP or domain of your Node.js backend

    // Prepare data for the POST request
    $post_data = array(
      'body' => json_encode(array(
        'api_key' => $api_key,
        'site_url' => $site_url
      )),
      'headers' => array(
        'Content-Type' => 'application/json',
      ),
      'timeout' => 150
    );

    // Make the POST request to the Node.js API
    $response = wp_remote_post($api_url, $post_data);

    // Check for any errors
    if (is_wp_error($response)) {
      wp_send_json_error(array('message' => 'Failed to retrieve remaining credits'));
      return;
    }

    // Decode the response body
    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    // Check if we have a valid response
    if (isset($data['remaining_credits'])) {
      wp_send_json_success(array('remaining_credits' => $data['remaining_credits']));
    } else {
      wp_send_json_error(array('message' => 'Invalid response from Node.js server'));
    }
  }




}
new AI_Content_Pipelines_Oneup_GenerateScheduledContent();