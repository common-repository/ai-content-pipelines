<?php
/**
 * Plugin Name: AI Content Pipelines
 * Description: Adds automated author personas to WordPress users and integrates with multiple LLMs for content generation.
 * Version: 1.5
 * Author: 1UP Media
 * Author URI: https://1upmedia.com/
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: ai-content-pipelines
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

//require_once plugin_dir_path(__FILE__) . 'lib/Parsedown.php';
require_once plugin_dir_path(__FILE__) . 'includes/generate-scheduled-content.php';

class AI_Content_Pipelines_Oneup_AuthorPersonas
{
    public function __construct()
    {
        add_action('admin_menu', [$this, 'create_admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'ai_content_pipelines_oneup_enqueue_admin_scripts']);
        add_action('add_meta_boxes', [$this, 'ai_content_pipelines_oneup_add_persona_meta_box']);
        add_action('save_post', [$this, 'ai_content_pipelines_oneup_save_persona_meta']);
        //add_action('editpost', [$this, 'save_persona_meta']);
        add_filter('the_content', [$this, 'display_persona_info']);
        add_action('show_user_profile', [$this, 'user_profile_fields']);
        add_action('edit_user_profile', [$this, 'user_profile_fields']);
        add_action('personal_options_update', [$this, 'ai_content_pipelines_oneup_save_user_profile_fields']);
        add_action('edit_user_profile_update', [$this, 'ai_content_pipelines_oneup_save_user_profile_fields']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('wp_ajax_generate_content', [$this, 'generate_content']);
        add_action('publish_post_event', [$this, 'publish_post_function']);
    }

    public function ai_content_pipelines_oneup_save_persona_meta($post_id)
    {
        // Security check: Verify nonces
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        // Verify the nonce before proceeding
        if (!isset($_POST['check_post_update_nonce_field']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['check_post_update_nonce_field'])), 'check_post_update_nonce')) {
            return;
        }
        if (!isset($_POST['save_persona_meta_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['save_persona_meta_nonce'])), 'save_persona_meta_action')) {
            return; // If the nonce is invalid, exit the function
        }

        // Check user permissions
        if (!current_user_can('edit_post', $post_id)) {
            return; // Exit if the current user does not have permission to edit the post
        }

        return;


        // if (isset($_POST['post_author'])) {
        //     $post_author = intval($_POST['post_author']);
        //     wp_update_post(array('ID' => $post_id, 'post_author' => $post_author));
        // }

        // // Save custom meta fields
        // if (isset($_POST['author_persona'])) {
        //     update_post_meta($post_id, 'author_persona', sanitize_text_field($_POST['author_persona']));
        // }
        // if (isset($_POST['content_type'])) {
        //     update_post_meta($post_id, 'content_type', sanitize_text_field($_POST['content_type']));
        // }
        // if (isset($_POST['api_type'])) {
        //     update_post_meta($post_id, 'api_type', sanitize_text_field($_POST['api_type']));
        // }
        // if (isset($_POST['custom_prompt'])) {
        //     update_post_meta($post_id, 'custom_prompt', sanitize_text_field($_POST['custom_prompt']));
        // }
        // if (isset($_POST['scheduled_time'])) {
        //     update_post_meta($post_id, 'scheduled_time', sanitize_text_field($_POST['scheduled_time']));
        // }
        // if (isset($_POST['approval_status'])) {
        //     update_post_meta($post_id, 'approval_status', sanitize_text_field($_POST['approval_status']));
        // }

        // // Save taxonomies (categories and tags)
        // if (isset($_POST['post_category'])) {
        //     wp_set_post_terms($post_id, $_POST['post_category'], 'category');
        // }
        // if (isset($_POST['tax_input']['post_tag'])) {
        //     wp_set_post_terms($post_id, $_POST['tax_input']['post_tag'], 'post_tag');
        // }

        // // Save meta fields from the meta array (if applicable)
        // if (isset($_POST['meta'])) {
        //     foreach ($_POST['meta'] as $meta_id => $meta_value) {
        //         update_post_meta($post_id, sanitize_text_field($meta_value['key']), sanitize_text_field($meta_value['value']));
        //     }
        // }

        // // Save thumbnail/featured image (handled by WordPress by default)
        // if (isset($_POST['_thumbnail_id'])) {
        //     set_post_thumbnail($post_id, intval($_POST['_thumbnail_id']));
        // }

        // wp_send_json_success(['message' => 'Post updated successfully!']);

    }


    public function updateProgressToPoll($progress_message)
    {

        $progress = get_transient('ai_content_pipelines_oneup_content_generation_progress') ?: [];
        $progress[] = ['message' => $progress_message, 'isSeen' => false];
        set_transient('ai_content_pipelines_oneup_content_generation_progress', $progress, 600);
    }

    public function create_admin_menu()
    {
        add_menu_page(
            'AI Content Pipelines',
            'AI Content Pipelines',
            'manage_options',
            'author-personas',
            [$this, 'admin_interface'],
            'dashicons-admin-users',
            100
        );

        add_submenu_page(
            'author-personas',
            'Settings',
            'Settings',
            'manage_options',
            'author-personas-settings',
            [$this, 'settings_page']
        );

        add_submenu_page(
            'author-personas',           // Parent slug (main menu)
            'Content Calendar',          // Page title
            'Content Calendar',          // Menu title
            'manage_options',            // Capability
            'content-calendar',          // Menu slug
            [$this, 'render_content_calendar_page'] // Callback function to render the calendar page
        );

        add_submenu_page(
            'author-personas',
            'Content Types',
            'Content Types',
            'manage_options',
            'content-types',
            [$this, 'render_content_types_page'] // Renamed function to match the purpose
        );

        add_submenu_page(
            'author-personas',            // Parent slug (main menu)
            'Manage Personas',            // Page title
            'Manage Personas',            // Menu title
            'manage_options',             // Capability
            'manage-personas',            // Menu slug
            [$this, 'render_manage_personas_page'] // Callback function to render the "Manage Personas" page
        );
        add_submenu_page(
            'author-personas',           // Parent slug (main menu)
            'Google Search Console',     // Page title
            'Google Search Console',     // Menu title
            'manage_options',            // Capability
            'google-search-console',     // Menu slug
            [$this, 'render_google_search_console_page'] // Callback function to render the Google Search Console page
        );

        add_submenu_page(
            'author-personas', // Parent menu slug
            'Analytics Compare', // Page title
            'Analytics Compare', // Menu title
            'manage_options', // Capability
            'analytics-compare', // Menu slug
            [$this, 'render_analytics_compare_page'] // Callback function
        );
    }

    function render_analytics_compare_page()
    {
        ?>
        <div class="container mt-5">
            <div class="row">
                <div class="col-md-12">
                    <h1 class="mb-4">Google Analytics Comparison</h1>

                    <!-- Google Sign-In Button -->
                    <button type="button" id="google_signin_button" class="btn btn-primary mb-4">Sign in with Google</button>

                    <!-- Site Dropdown -->
                    <div id="gsc_list_sites_container" class="mb-4" style="display:none;">
                        <h3>Select a Site</h3>
                        <select id="site_select" class="form-select">
                            <option value="">Select a site</option>
                        </select>
                    </div>

                    <!-- Date Selection & Comparison Section -->
                    <div id="gsc_buttons" style="display:none;">
                        <h3>Compare Google Search Console Analytics</h3>

                        <div class="row mb-3">
                            <!-- Date Range 1 -->
                            <div class="col-md-6">
                                <h5>First Date Range</h5>
                                <div class="form-group">
                                    <label for="start_date_1">Start Date:</label>
                                    <input type="date" id="start_date_1" class="form-control">
                                </div>
                                <div class="form-group mt-2">
                                    <label for="end_date_1">End Date:</label>
                                    <input type="date" id="end_date_1" class="form-control">
                                </div>
                            </div>

                            <!-- Date Range 2 -->
                            <div class="col-md-6">
                                <h5>Second Date Range</h5>
                                <div class="form-group">
                                    <label for="start_date_2">Start Date:</label>
                                    <input type="date" id="start_date_2" class="form-control">
                                </div>
                                <div class="form-group mt-2">
                                    <label for="end_date_2">End Date:</label>
                                    <input type="date" id="end_date_2" class="form-control">
                                </div>
                            </div>
                        </div>

                        <!-- Submit Button -->
                        <button type="button" id="compare_button" class="btn btn-success">Compare Analytics</button>
                    </div>

                    <!-- Results Display -->
                    <div id="comparison_results" class="mt-5">
                        <h3>Comparison Results</h3>
                        <!-- Table for comparison results -->
                        <div id="analytics_table_container"></div>

                        <!-- Chart for visualizing comparison -->
                        <canvas id="comparisonChart" class="mt-4" style="max-height: 400px;"></canvas>
                    </div>

                    <!-- Hidden input to store Google Access Token -->
                    <input type="hidden" id="google_access_token" name="google_access_token">
                </div>
            </div>
        </div>
        <?php
    }


    public function render_google_search_console_page()
    {
        ?>
        <div class="wrap">
            <h1>Google Search Console</h1>

            <!-- Google Sign-In Button -->
            <button type="button" id="gsc_google_signin_button" class="button gsc-fancy-button">Sign in with Google</button>

            <!-- Site Dropdown -->
            <div id="gsc_list_sites_container" style="display:none;">
                <h3>Available Sites</h3>
                <select id="gsc_site_select" class="gsc-styled-select">
                    <option value="">Select a site</option>
                </select>
            </div>

            <!-- Get Analytics Section -->
            <div id="gsc_buttons" style="display:none;">
                <h3>Google Search Console Analytics</h3>

                <!-- Get Analytics Date Selection -->
                <div class="gsc-analytics-section">
                    <label for="gsc_start_date" class="gsc-fancy-label">Start Date:</label>
                    <input type="date" id="gsc_start_date" class="gsc-fancy-input">
                    <label for="gsc_end_date" class="gsc-fancy-label">End Date:</label>
                    <input type="date" id="gsc_end_date" class="gsc-fancy-input">
                    <button type="button" id="gsc_get_analytics_button" class="button gsc-fancy-button">Get Analytics</button>
                </div>
            </div>

            <!-- Results Display -->
            <div id="gsc_results" style="margin-top:20px;">
                <h3>Search Analytics Data</h3>
                <div id="gsc_analytics_table_container" class="gsc-fancy-table-container"></div>
            </div>

            <!-- Hidden input to store Google Access Token -->
            <input type="hidden" id="gsc_google_access_token" name="google_access_token">

            <!-- Compare Analytics Button (only visible if Google Auth token is present) -->
            <div id="gsc_compare_analytics_container" style="display:none;">
                <button type="button" id="gsc_compare_analytics_button" class="button gsc-fancy-button">Compare
                    Analytics</button>
            </div>
            <?php
    }



    public function render_content_types_page()
    {
        ?>
            <div class="wrap">
                <h1><?php esc_html_e('Content Calendar and Manage Content Types', 'ai-content-pipelines'); ?></h1>

                <!-- Content Calendar Part (If you want to add a calendar, it can go here) -->
                <!-- Your calendar HTML and JS code can be inserted here -->
                <!-- Manage Content Types Section -->
                <div id="content-types" class="tab-content">
                    <h2>Manage Content Types</h2>
                    <form method="post" action="<?php echo admin_url('admin.php?page=author-personas'); ?>">
                        <input type="hidden" name="_wp_http_referer" value="<?php echo esc_url($_SERVER['REQUEST_URI']); ?>" />
                        <table class="form-table">
                            <tr>
                                <th><label for="content_type">Content Type</label></th>
                                <td><input type="text" name="content_type" id="content_type" required></td>
                            </tr>
                            <tr>
                                <th><label for="content_template">Template</label></th>
                                <td><textarea name="content_template" id="content_template" required rows="5"
                                        cols="50"></textarea>
                                </td>
                            </tr>
                        </table>
                        <p class="submit">
                            <?php wp_nonce_field('add_content_type_action', 'add_content_type_nonce'); ?>
                            <input type="submit" name="add_content_type" id="add_content_type" class="button button-primary"
                                value="Add  Content Type">
                        </p>
                    </form>

                    <h2>Existing Content Types</h2>
                    <table class="widefat" cellspacing="0">
                        <thead>
                            <tr>
                                <th class="manage-column column-name" scope="col">Content Type</th>
                                <th class="manage-column column-template" scope="col">Template</th>
                                <th class="manage-column column-action" scope="col">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $content_types = get_option('ai_content_pipelines_oneup_author_personas_content_types', []);
                            foreach ($content_types as $content_type => $template): ?>
                                <tr>
                                    <td><?php echo esc_html($content_type); ?></td>
                                    <td>
                                        <div class="template-preview">
                                            <?php echo esc_html(wp_trim_words($template, 10, '...')); ?>
                                        </div>
                                        <a href="javascript:void(0);" class="expand-template">Expand ▼</a>
                                        <div class="template-full" style="display:none;">
                                            <textarea readonly rows="5" cols="50"><?php echo esc_textarea($template); ?></textarea>
                                        </div>
                                    </td>
                                    <td>
                                        <!-- Form to edit the content type and template -->
                                        <form method="post" style="display:inline;"
                                            action="<?php echo admin_url('admin.php?page=author-personas'); ?>">
                                            <input type="hidden" name="_wp_http_referer"
                                                value="<?php echo esc_url($_SERVER['REQUEST_URI']); ?>" />
                                            <input type="hidden" name="content_type" value="<?php echo esc_attr($content_type); ?>">
                                            <textarea name="content_template" rows="5"
                                                cols="50"><?php echo esc_textarea($template); ?></textarea>
                                            <?php wp_nonce_field('add_content_type_action', 'add_content_type_nonce'); ?>
                                            <input type="submit" name="add_content_type" class="button button-primary"
                                                value="Update">
                                        </form>

                                        <!-- Form to delete the content type -->
                                        <form method="post" style="display:inline;"
                                            action="<?php echo admin_url('admin.php?page=author-personas'); ?>">

                                            <?php wp_nonce_field('delete_content_type_action', 'delete_content_type_nonce'); ?>
                                            <input type="hidden" name="_wp_http_referer"
                                                value="<?php echo esc_url($_SERVER['REQUEST_URI']); ?>" />
                                            <input type="hidden" name="content_type_key"
                                                value="<?php echo esc_attr($content_type); ?>">
                                            <input type="submit" name="delete_content_type" class="button button-primary"
                                                value="Delete">
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php
    }

    public function render_manage_personas_page()
    {
        // Fetch existing personas
        $personas = get_option('ai_content_pipelines_oneup_author_personas', []); // Adjust this based on how you store personas

        ?>
            <div class="wrap">
                <h1><?php esc_html_e('Manage Personas', 'ai-content-pipelines'); ?></h1>

                <div id="manage-personas" class="tab-content">
                    <form method="post" action="<?php echo admin_url('admin.php?page=author-personas'); ?>">
                        <input type="hidden" name="_wp_http_referer" value="<?php echo esc_url($_SERVER['REQUEST_URI']); ?>" />

                        <h2>Add New Persona</h2>
                        <table class="form-table">
                            <tr>
                                <th><label for="persona_name">Persona</label></th>
                                <td><input type="text" name="persona_name" id="persona_name" required></td>
                            </tr>
                            <tr>
                                <th><label for="persona_style">Writing Style</label></th>
                                <td>
                                    <select name="persona_style" id="persona_style" required>
                                        <option value="">Select Writing Style</option>
                                        <option value="Narrative">Narrative</option>
                                        <option value="Descriptive">Descriptive</option>
                                        <option value="Expository">Expository</option>
                                        <option value="Persuasive">Persuasive</option>
                                        <option value="Analytical">Analytical</option>
                                        <option value="Reflective">Reflective</option>
                                        <option value="Technical">Technical</option>
                                        <option value="Journalistic">Journalistic</option>
                                        <option value="Creative">Creative</option>
                                        <option value="Conversational">Conversational</option>
                                        <option value="Academic">Academic</option>
                                        <option value="Business">Business</option>
                                        <option value="Scriptwriting">Scriptwriting</option>
                                        <option value="Epistolary">Epistolary</option>
                                        <option value="Satirical">Satirical</option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="persona_tone">Tone</label></th>
                                <td>
                                    <select name="persona_tone" id="persona_tone" required>
                                        <option value="">Select Tone</option>
                                        <option value="Formal">Formal</option>
                                        <option value="Informal">Informal</option>
                                        <option value="Optimistic">Optimistic</option>
                                        <option value="Pessimistic">Pessimistic</option>
                                        <option value="Joyful">Joyful</option>
                                        <option value="Sad">Sad</option>
                                        <option value="Serious">Serious</option>
                                        <option value="Humorous">Humorous</option>
                                        <option value="Sincere">Sincere</option>
                                        <option value="Ironic">Ironic</option>
                                        <option value="Sarcastic">Sarcastic</option>
                                        <option value="Condescending">Condescending</option>
                                        <option value="Objective">Objective</option>
                                        <option value="Subjective">Subjective</option>
                                        <option value="Angry">Angry</option>
                                        <option value="Calm">Calm</option>
                                        <option value="Enthusiastic">Enthusiastic</option>
                                        <option value="Apathetic">Apathetic</option>
                                        <option value="Compassionate">Compassionate</option>
                                        <option value="Critical">Critical</option>
                                        <option value="Respectful">Respectful</option>
                                        <option value="Disrespectful">Disrespectful</option>
                                        <option value="Inspirational">Inspirational</option>
                                        <option value="Melancholic">Melancholic</option>
                                        <option value="Nostalgic">Nostalgic</option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="persona_emotions_1">Emotion 1</label></th>
                                <td>
                                    <select name="persona_emotions[]" id="persona_emotions_1" required>
                                        <option value="">Select Emotion</option>
                                        <option value="Happiness">Happiness</option>
                                        <option value="Sadness">Sadness</option>
                                        <option value="Anger">Anger</option>
                                        <option value="Fear">Fear</option>
                                        <option value="Surprise">Surprise</option>
                                        <option value="Disgust">Disgust</option>
                                        <option value="Love">Love</option>
                                        <option value="Hate">Hate</option>
                                        <option value="Excitement">Excitement</option>
                                        <option value="Boredom">Boredom</option>
                                        <option value="Gratitude">Gratitude</option>
                                        <option value="Envy">Envy</option>
                                        <option value="Pride">Pride</option>
                                        <option value="Shame">Shame</option>
                                        <option value="Guilt">Guilt</option>
                                        <option value="Anxiety">Anxiety</option>
                                        <option value="Relief">Relief</option>
                                        <option value="Hope">Hope</option>
                                        <option value="Despair">Despair</option>
                                        <option value="Trust">Trust</option>
                                        <option value="Suspicion">Suspicion</option>
                                        <option value="Joy">Joy</option>
                                        <option value="Grief">Grief</option>
                                        <option value="Contentment">Contentment</option>
                                        <option value="Frustration">Frustration</option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="persona_emotions_2">Emotion 2</label></th>
                                <td>
                                    <select name="persona_emotions[]" id="persona_emotions_2" required>
                                        <option value="">Select Emotion</option>
                                        <option value="Happiness">Happiness</option>
                                        <option value="Sadness">Sadness</option>
                                        <option value="Anger">Anger</option>
                                        <option value="Fear">Fear</option>
                                        <option value="Surprise">Surprise</option>
                                        <option value="Disgust">Disgust</option>
                                        <option value="Love">Love</option>
                                        <option value="Hate">Hate</option>
                                        <option value="Excitement">Excitement</option>
                                        <option value="Boredom">Boredom</option>
                                        <option value="Gratitude">Gratitude</option>
                                        <option value="Envy">Envy</option>
                                        <option value="Pride">Pride</option>
                                        <option value="Shame">Shame</option>
                                        <option value="Guilt">Guilt</option>
                                        <option value="Anxiety">Anxiety</option>
                                        <option value="Relief">Relief</option>
                                        <option value="Hope">Hope</option>
                                        <option value="Despair">Despair</option>
                                        <option value="Trust">Trust</option>
                                        <option value="Suspicion">Suspicion</option>
                                        <option value="Joy">Joy</option>
                                        <option value="Grief">Grief</option>
                                        <option value="Contentment">Contentment</option>
                                        <option value="Frustration">Frustration</option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="persona_emotions_3">Emotion 3</label></th>
                                <td>
                                    <select name="persona_emotions[]" id="persona_emotions_3" required>
                                        <option value="">Select Emotion</option>
                                        <option value="Happiness">Happiness</option>
                                        <option value="Sadness">Sadness</option>
                                        <option value="Anger">Anger</option>
                                        <option value="Fear">Fear</option>
                                        <option value="Surprise">Surprise</option>
                                        <option value="Disgust">Disgust</option>
                                        <option value="Love">Love</option>
                                        <option value="Hate">Hate</option>
                                        <option value="Excitement">Excitement</option>
                                        <option value="Boredom">Boredom</option>
                                        <option value="Gratitude">Gratitude</option>
                                        <option value="Envy">Envy</option>
                                        <option value="Pride">Pride</option>
                                        <option value="Shame">Shame</option>
                                        <option value="Guilt">Guilt</option>
                                        <option value="Anxiety">Anxiety</option>
                                        <option value="Relief">Relief</option>
                                        <option value="Hope">Hope</option>
                                        <option value="Despair">Despair</option>
                                        <option value="Trust">Trust</option>
                                        <option value="Suspicion">Suspicion</option>
                                        <option value="Joy">Joy</option>
                                        <option value="Grief">Grief</option>
                                        <option value="Contentment">Contentment</option>
                                        <option value="Frustration">Frustration</option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="persona_experience">Experience</label></th>
                                <td>
                                    <textarea name="persona_experience" id="persona_experience" rows="2" required></textarea>
                                    <br><span>Example: "Jane Doe has been a practicing endocrinologist for over 20 years,
                                        specializing in diabetes management. Her extensive experience includes treating
                                        thousands of
                                        patients and conducting numerous clinical studies on diabetic care. Jane regularly
                                        speaks at
                                        medical conferences and contributes to leading health journals."</span>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="persona_expertise">Expertise</label></th>
                                <td>
                                    <textarea name="persona_expertise" id="persona_expertise" rows="2" required></textarea>
                                    <br><span>Example: "John Smith holds a master’s degree in marketing from XYZ University and
                                        is
                                        certified in Google Analytics and SEO by leading industry bodies. With over a decade of
                                        experience, John has worked with top marketing agencies, developing successful digital
                                        marketing strategies for global brands."</span>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="persona_authoritativeness">Authoritativeness</label></th>
                                <td>
                                    <textarea name="persona_authoritativeness" id="persona_authoritativeness" rows="2"
                                        required></textarea>
                                    <br><span>Example: "Dr. Emily Brown, a professor at ABC University’s Environmental Science
                                        Department, has published over 50 research papers on climate change, widely cited by
                                        other
                                        scientists and featured in prestigious journals such as Nature and Science. Emily’s work
                                        is
                                        a cornerstone in the field of environmental science."</span>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="persona_trustworthiness">Trustworthiness</label></th>
                                <td>
                                    <textarea name="persona_trustworthiness" id="persona_trustworthiness" rows="2"
                                        required></textarea>
                                    <br><span>Example: "Our financial expert, Sarah Lee, provides transparent and unbiased
                                        advice.
                                        Sarah discloses all potential conflicts of interest and follows a strict editorial
                                        policy to
                                        ensure accuracy and integrity. You can learn more about Sarah’s credentials and contact
                                        her
                                        directly via her detailed author bio."</span>
                                </td>
                            </tr>
                        </table>
                        <p class="submit">
                            <?php wp_nonce_field('add_persona_action', 'add_persona_nonce'); ?>
                            <input type="submit" name="add_persona" id="add_persona" class="button button-primary"
                                value="Add Persona">
                        </p>
                    </form>

                    <h2>Existing Personas</h2>
                    <table class="widefat" cellspacing="0">
                        <thead>
                            <tr>
                                <th class="manage-column column-cb check-column" scope="col">#</th>
                                <th class="manage-column column-name" scope="col">Persona</th>
                                <th class="manage-column column-style" scope="col">Writing Style</th>
                                <th class="manage-column column-tone" scope="col">Tone</th>
                                <th class="manage-column column-emotions" scope="col">Emotions</th>
                                <th class="manage-column column-experience" scope="col">Experience</th>
                                <th class="manage-column column-expertise" scope="col">Expertise</th>
                                <th class="manage-column column-authoritativeness" scope="col">Authoritativeness</th>
                                <th class="manage-column column-trustworthiness" scope="col">Trustworthiness</th>
                                <th class="manage-column column-action" scope="col">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            if (!empty($personas)) {
                                foreach ($personas as $index => $persona):
                                    ?>
                                    <tr>
                                        <th scope="row"><?php echo esc_html($index + 1); ?></th>
                                        <td><?php echo esc_html($persona['persona']); ?></td>
                                        <td><?php echo esc_html($persona['writing_style']); ?></td>
                                        <td><?php echo esc_html($persona['tone']); ?></td>
                                        <td><?php echo esc_html(implode(', ', $persona['emotions'])); ?></td>
                                        <td><?php echo esc_html($persona['E-E-A-T']['Experience']); ?></td>
                                        <td><?php echo esc_html($persona['E-E-A-T']['Expertise']); ?></td>
                                        <td><?php echo esc_html($persona['E-E-A-T']['Authoritativeness']); ?></td>
                                        <td><?php echo esc_html($persona['E-E-A-T']['Trustworthiness']); ?></td>
                                        <td>
                                            <form method="post" style="display:inline;"
                                                action="<?php echo admin_url('admin.php?page=author-personas'); ?>">
                                                <?php wp_nonce_field('delete_persona_action', 'delete_persona_nonce'); ?>
                                                <input type="hidden" name="persona_index" value="<?php echo esc_attr($index); ?>">
                                                <input type="submit" name="delete_persona" class="button button-secondary"
                                                    value="Delete">
                                            </form>
                                        </td>
                                    </tr>
                                    <?php
                                endforeach;
                            } else {
                                echo '<tr><td colspan="10">No personas found.</td></tr>';
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php
    }


    public function render_content_calendar_page()
    {
        echo '<div class="wrap">';
        echo '<h1>Content Calendar</h1>';
        // echo '<button id="all-good" class="button button-primary" style="margin-top:20px;">All good</button>';
        echo '<div id="content-calendar"></div>'; // This is where the FullCalendar will render
        echo '</div>';
    }






    public function register_settings()
    {
        register_setting('ai_content_pipelines_oneup_author_personas_settings_group', 'ai_content_pipelines_oneup_openai_api_key');
        register_setting('ai_content_pipelines_oneup_author_personas_settings_group', 'ai_content_pipelines_oneup_twitter_api_key');
        register_setting('ai_content_pipelines_oneup_author_personas_settings_group', 'ai_content_pipelines_oneup_twitter_api_secret');
        register_setting('ai_content_pipelines_oneup_author_personas_settings_group', 'ai_content_pipelines_oneup_twitter_access_token');
        register_setting('ai_content_pipelines_oneup_author_personas_settings_group', 'ai_content_pipelines_oneup_twitter_access_token_secret');
        register_setting('ai_content_pipelines_oneup_author_personas_settings_group', 'ai_content_pipelines_oneup_author_persona_language_setting');

    }

    public function settings_page()
    {
        ?>
            <div class="wrap">
                <h1>Content Pipeline Settings</h1>
                <form method="post" action="options.php">
                    <?php settings_fields('ai_content_pipelines_oneup_author_personas_settings_group'); ?>
                    <?php do_settings_sections('ai_content_pipelines_oneup_author_personas_settings_group'); ?>
                    <?php wp_nonce_field('author_personas_save_settings', 'author_personas_nonce'); ?>
                    <table class="form-table">
                        <tr valign="top">
                            <th scope="row">Your 1UP API Key</th>
                            <td><input type="text" name="ai_content_pipelines_oneup_openai_api_key"
                                    value="<?php echo esc_attr(get_option('ai_content_pipelines_oneup_openai_api_key')); ?>" />
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">Twitter API Key</th>
                            <td><input type="text" name="ai_content_pipelines_oneup_twitter_api_key"
                                    value="<?php echo esc_attr(get_option('ai_content_pipelines_oneup_twitter_api_key')); ?>" />
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">Twitter API Secret</th>
                            <td><input type="text" name="ai_content_pipelines_oneup_twitter_api_secret"
                                    value="<?php echo esc_attr(get_option('ai_content_pipelines_oneup_twitter_api_secret')); ?>" />
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">Twitter Access Token</th>
                            <td><input type="text" name="ai_content_pipelines_oneup_twitter_access_token"
                                    value="<?php echo esc_attr(get_option('ai_content_pipelines_oneup_twitter_access_token')); ?>" />
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">Twitter Access Token Secret</th>
                            <td><input type="text" name="ai_content_pipelines_oneup_twitter_access_token_secret"
                                    value="<?php echo esc_attr(get_option('ai_content_pipelines_oneup_twitter_access_token_secret')); ?>" />
                            </td>
                        </tr>
                    </table>
                    <?php submit_button(); ?>
                </form>
                <button type="button" id="authorize_facebook" class="button" style="display:none;">Authorize Facebook</button>
                <button type="button" id="logout_facebook" class="button" style="display:none;">Logout Facebook</button>
                <div id="facebook_page_selection" style="display:none;">
                    <label for="facebook_page_id">Select Page:</label>
                    <select id="facebook_page_id" name="facebook_page_id"></select>
                </div>
                <button type="button" id="authorize_linkedin" class="button" style="display:none;">Authorize LinkedIn</button>
                <button type="button" id="logout_linkedin" class="button" style="display:none;">Logout LinkedIn</button>

            </div>
            <?php
    }


    public function admin_interface()
    {
        require_once plugin_dir_path(__FILE__) . 'admin/admin-interface.php';
    }

    public function ai_content_pipelines_oneup_enqueue_admin_scripts()
    {

        wp_enqueue_script('jquery');

        wp_enqueue_script(
            'fullcalendar-js',
            plugin_dir_url(__FILE__) . 'assets/js/fullcalendar.min.js', // Local path to JS
            array('jquery'),
            '5.10.1',
            true
        );
        wp_enqueue_style(
            'fullcalendar-css',
            plugin_dir_url(__FILE__) . 'assets/css/fullcalendar.min.css', // Local path to CSS
            array(),
            '5.10.1'
        );

        // Enqueue admin JavaScript file
        wp_enqueue_script('author-personas-admin', plugin_dir_url(__FILE__) . 'assets/admin.js', ['jquery', 'wp-editor', 'wp-data', 'fullcalendar-js'], time(), true);

        // Enqueue toastr.js and toastr.css for notifications
        wp_enqueue_style('toastr-css', plugin_dir_url(__FILE__) . 'assets/css/toastr.min.css', [], '2.1.4');

        // Enqueue the local Toastr JS file from the assets/js/ directory
        wp_enqueue_script('toastr-js', plugin_dir_url(__FILE__) . 'assets/js/toastr.min.js', ['jquery'], '2.1.4', true);
        wp_enqueue_script('google-search-console-js', plugin_dir_url(__FILE__) . 'assets/js/google_search_console.js', array('jquery'), null, true);

        // Enqueue admin CSS file
        wp_enqueue_style('author-personas-admin', plugin_dir_url(__FILE__) . 'assets/admin.css', [], '1.0.0');

        // Enqueue Chart.js and custom scripts that depend on jQuery and other libraries
        wp_enqueue_script('chart-js', plugin_dir_url(__FILE__) . 'assets/js/chart.min.js', array('jquery'), null, true);
        wp_enqueue_script('google-analytics-compare', plugin_dir_url(__FILE__) . 'assets/js/google_analytics_compare.js', array('jquery', 'chart-js'), null, true);

        // Enqueue admin-specific scripts and styles

        // Localize script to pass dynamic PHP variables into the JS file
        wp_localize_script('author-personas-admin', 'myAjax', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('my_generic_action'),
            'linkedinAccessToken' => esc_js(get_option('ai_content_pipelines_oneup_linkedin_access_token')),
            'facebookAccessToken' => esc_js(get_option('ai_content_pipelines_oneup_facebook_access_token')),
            'facebookPageId' => esc_js(get_option('ai_content_pipelines_oneup_facebook_page_id')),
            'twitterApiSecret' => esc_js(get_option('ai_content_pipelines_oneup_twitter_api_secret')),
            'twitterApiKey' => esc_js(get_option('ai_content_pipelines_oneup_twitter_api_key')),
            'twitterAccessToken' => esc_js(get_option('ai_content_pipelines_oneup_twitter_access_token')),
            'twitterAccessTokenSecret' => esc_js(get_option('ai_content_pipelines_oneup_twitter_access_token_secret')),
            'pluginSettingsUrl' => esc_url(admin_url('admin.php?page=author-personas-settings')),
            'contentCalendarUrl' => admin_url('admin.php?page=content-calendar'),
        ));
    }





    public function ai_content_pipelines_oneup_add_persona_meta_box()
    {
        add_meta_box(
            'persona_meta_box',
            'Author Persona',
            [$this, 'render_persona_meta_box'],
            ['post', 'page'],
            'side',
            'high'
        );
    }

    public function render_persona_meta_box($post)
    {
        $selected_persona = get_post_meta($post->ID, '_selected_persona', true);
        $selected_content_type = get_post_meta($post->ID, '_selected_content_type', true);
        $selected_api = get_post_meta($post->ID, '_selected_api', true);
        $scheduled_time = get_post_meta($post->ID, '_scheduled_time', true);
        $approval_status = get_post_meta($post->ID, '_approval_status', true) ?: 'pending';
        $custom_prompt = get_post_meta($post->ID, '_custom_prompt', true);
        $personas = get_option('ai_content_pipelines_oneup_author_personas', []);
        $content_types = [
            'Review',
            'Editorial',
            'Interview',
            'How To',
            'Topic Introduction',
            'Opinion',
            'Research',
            'Case Study',
            'Short Report',
            'Think Piece',
            'Hard News',
            'First Person',
            'Service Piece',
            'Informational'
        ];
        $apis = ['OpenAI', 'Other LLM'];
        $users = get_users();

        echo '<label for="author_persona">Select Author Persona:</label>';
        echo '<select name="author_persona" id="author_persona">';
        foreach ($personas as $persona) {
            echo '<option value="' . esc_attr($persona['persona']) . '" ' . selected($selected_persona, $persona['persona'], false) . '>' . esc_html($persona['persona']) . '</option>';
        }
        echo '</select>';

        echo '<label for="content_type">Select Content Type:</label>';
        echo '<select name="content_type" id="content_type">';
        foreach ($content_types as $content_type) {
            echo '<option value="' . esc_attr($content_type) . '" ' . selected($selected_content_type, $content_type, false) . '>' . esc_html($content_type) . '</option>';
        }
        echo '</select>';

        echo '<label for="api_type">Select LLM API:</label>';
        echo '<select name="api_type" id="api_type">';
        foreach ($apis as $api) {
            echo '<option value="' . esc_attr($api) . '" ' . selected($selected_api, $api, false) . '>' . esc_html($api) . '</option>';
        }
        echo '</select>';

        echo '<label for="custom_prompt">Custom Prompt:</label>';
        echo '<input type="text" name="custom_prompt" id="custom_prompt" value="' . esc_attr($custom_prompt) . '">';

        echo '<label for="scheduled_time">Schedule Post:</label>';
        echo '<input type="date" name="scheduled_time" id="scheduled_time" value="' . esc_attr($scheduled_time) . '">';

        echo '<label for="approval_status">Approval Status:</label>';
        echo '<select name="approval_status" id="approval_status">';
        echo '<option value="pending" ' . selected($approval_status, 'pending', false) . '>Pending</option>';
        echo '<option value="approved" ' . selected($approval_status, 'approved', false) . '>Approved</option>';
        echo '</select>';

        echo '<label for="post_author">Select Author:</label>';
        echo '<select name="post_author" id="post_author">';
        foreach ($users as $user) {
            echo '<option value="' . esc_attr($user->ID) . '">' . esc_html($user->display_name) . '</option>';
        }
        echo '</select>';

        echo '<button type="button" id="generate_content" class="button button-primary">Generate Content</button>';
        echo '<div id="loading-spinner" style="display:none;">Loading...</div>';

        echo '<button type="button" id="find_related_posts" class="button button-secondary">Find Related Posts</button>';
        echo '<div id="related-posts-modal" style="display:none;">';
        echo '<button class="close-button">Close</button>';
        echo '<div class="content"></div>';
        echo '</div>';

        // Add nonce for saving post data
        wp_nonce_field('save_persona_meta_action', 'save_persona_meta_nonce');

        // You already have this for the AJAX:
        wp_nonce_field('generate_content_nonce', 'generate_content_nonce_field');
    }


    // public function save_persona_meta($post_id)
    // {

    //     if (!isset($_POST['save_persona_meta_nonce'])) {
    //         return; // Nonce not set, exit function
    //     }

    //     // Verify nonce
    //     if (!wp_verify_nonce($_POST['save_persona_meta_nonce'], 'save_persona_meta_action')) {
    //         return; // Nonce verification failed, exit function
    //     }

    //     if (isset($_POST['author_persona'])) {
    //         update_post_meta($post_id, '_selected_persona', sanitize_text_field($_POST['author_persona']));
    //     }
    //     if (isset($_POST['content_type'])) {
    //         update_post_meta($post_id, '_selected_content_type', sanitize_text_field($_POST['content_type']));
    //     }
    //     if (isset($_POST['api_type'])) {
    //         update_post_meta($post_id, '_selected_api', sanitize_text_field($_POST['api_type']));
    //     }
    //     if (isset($_POST['custom_prompt'])) {
    //         update_post_meta($post_id, '_custom_prompt', sanitize_text_field($_POST['custom_prompt']));
    //     }
    //     if (isset($_POST['scheduled_time'])) {
    //         update_post_meta($post_id, '_scheduled_time', sanitize_text_field($_POST['scheduled_time']));
    //         $scheduled_time = strtotime(sanitize_text_field($_POST['scheduled_time']));
    //         wp_schedule_single_event($scheduled_time, 'publish_post_event', [$post_id]);
    //     }
    //     if (isset($_POST['approval_status'])) {
    //         update_post_meta($post_id, '_approval_status', sanitize_text_field($_POST['approval_status']));
    //     }
    //     if (isset($_POST['post_author'])) {
    //         $author_id = intval($_POST['post_author']);
    //         wp_update_post([
    //             'ID' => $post_id,
    //             'post_author' => $author_id
    //         ]);
    //     }
    // }

    public function get_persona_info($persona_name)
    {
        $personas = get_option('ai_content_pipelines_oneup_author_personas', []);
        foreach ($personas as $persona) {
            if ($persona['persona'] === $persona_name) {
                return $persona;
            }
        }
        return null;
    }

    public function display_persona_info($content)
    {
        if (is_single() || is_page()) {
            global $post;

            // Check if the content is approved
            // if (get_post_meta($post->ID, '_approval_status', true) !== 'approved') {
            //     return '<p>This content is pending approval.</p>';
            // }

            $persona_name = get_post_meta($post->ID, '_selected_persona', true);
            $content_type = get_post_meta($post->ID, '_selected_content_type', true);
            $generated_content = get_post_meta($post->ID, '_generated_content', true);

            // Check if the persona name exists
            if ($persona_name) {
                $persona = $this->get_persona_info($persona_name);
                if ($persona) {
                    $schema = [
                        "@context" => "https://schema.org",
                        "@type" => "Article",
                        "author" => [
                            "@type" => "Person",
                            "name" => $persona['persona'],
                            "description" => $persona['E-E-A-T']['Experience']
                        ],
                        "articleSection" => $content_type,
                        "headline" => get_the_title($post->ID),
                        "datePublished" => get_the_date('c', $post->ID),
                        "dateModified" => get_the_modified_date('c', $post->ID),
                        "mainEntityOfPage" => get_permalink($post->ID),
                        "publisher" => [
                            "@type" => "Organization",
                            "name" => get_bloginfo('name'),
                            "logo" => [
                                "@type" => "ImageObject",
                                "url" => get_site_icon_url()
                            ]
                        ]
                    ];
                    $content .= '<script type="application/ld+json">' . wp_json_encode($schema) . '</script>';

                    $keywords = explode(' ', $persona['writing_style']);
                    $related_posts = $this->get_related_posts($keywords, $post->ID);
                    if ($related_posts) {
                        $content .= '<h3>Related Posts</h3><ul>';
                        foreach ($related_posts as $related_post) {
                            $content .= '<li><a href="' . get_permalink($related_post->ID) . '">' . get_the_title($related_post->ID) . '</a></li>';
                        }
                        $content .= '</ul>';
                    }
                }
            }

            // Avoid appending if the content is already present
            if (!empty($generated_content)) {
                if (strpos($content, $generated_content) === false) {
                    $content = $generated_content . $content;
                } elseif (strpos($content, $generated_content) !== false) {
                    // Remove duplicate generated content if already appended
                    $content = str_replace($generated_content, '', $content);
                    $content = $generated_content . $content;
                }
            }
        }
        return $content;
    }



    // public function get_related_posts($keywords, $current_post_id)
    // {
    //     $args = [
    //         's' => implode(' ', $keywords),
    //         'post__not_in' => [$current_post_id],
    //         'posts_per_page' => 5,
    //     ];
    //     $query = new WP_Query($args);
    //     return $query->posts;
    // }

    public function get_related_posts($keywords, $current_post_id)
    {
        // Using post__not_in can affect performance on large datasets. Consider alternative approaches.
        $args = [
            's' => implode(' ', $keywords),
            'posts_per_page' => 6, // Retrieve one extra post to handle manual exclusion
        ];

        $query = new WP_Query($args);
        $posts = $query->posts;

        // Manually exclude the current post, if it's in the results.
        $filtered_posts = array_filter($posts, function ($post) use ($current_post_id) {
            return $post->ID !== $current_post_id;
        });

        // Return only the number of posts you need
        return array_slice($filtered_posts, 0, 5);
    }


    public function generate_content()
    {
        ini_set('memory_limit', '256M'); // Increase memory limit
        ini_set('max_execution_time', '300');
        check_ajax_referer('generate_content_nonce', '_wpnonce');
        update_option('ai_content_pipelines_oneup_is_last_error', false);
        $this->updateProgressToPoll("Content generation started....");

        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        $persona_name = isset($_POST['persona']) ? sanitize_text_field(wp_unslash($_POST['persona'])) : '';
        $content_type = isset($_POST['content_type']) ? sanitize_text_field(wp_unslash($_POST['content_type'])) : '';
        $api_type = isset($_POST['api_type']) ? sanitize_text_field(wp_unslash($_POST['api_type'])) : '';
        $custom_prompt = isset($_POST['custom_prompt']) ? sanitize_text_field(wp_unslash($_POST['custom_prompt'])) : '';
        $author_id = isset($_POST['post_author']) ? intval(wp_unslash($_POST['post_author'])) : 0;
        $post_status = isset($_POST['post_status']) ? sanitize_text_field(wp_unslash($_POST['post_status'])) : 'pending';
        $schedule_time = isset($_POST['schedule_time']) ? wp_unslash($_POST['schedule_time']) : null;

        if ($schedule_time && strtotime($schedule_time)) {
            // Convert the valid date to Unix timestamp
            $schedule_time = strtotime($schedule_time);
        } else {
            // Invalid or missing date, use current time
            $schedule_time = time();
            $this->updateProgressToPoll("Invalid or no scheduled time provided, using current time.");
        }


        if (empty($post_id) || empty($persona_name) || empty($content_type) || empty($api_type) || empty($custom_prompt) || empty($author_id)) {
            wp_send_json_error(['message' => 'Invalid request parameters']);
            return;
        }

        $persona = $this->get_persona_info($persona_name);
        if (!$persona) {
            wp_send_json_error(['message' => 'Invalid persona']);
            return;
        }


        $persona_details = sprintf(
            "Persona: %s\nWriting Style: %s\nTone: %s\nEmotions: %s\nExperience: %s\nExpertise: %s\nAuthoritativeness: %s\nTrustworthiness: %s",
            $persona['persona'],
            $persona['writing_style'],
            $persona['tone'],
            implode(', ', $persona['emotions']),
            $persona['E-E-A-T']['Experience'],
            $persona['E-E-A-T']['Expertise'],
            $persona['E-E-A-T']['Authoritativeness'],
            $persona['E-E-A-T']['Trustworthiness']
        );

        $templates = [
            "Review" => "Review Template\n\nIntroduction:\n- Provide a brief overview of the product/service being reviewed.\n- Mention the main purpose or use case.\n\nPros and Cons:\n- List the key advantages and benefits of the product/service.\n- List any drawbacks or disadvantages.\n\nDetailed Review:\n- Describe the product/service in detail, including features, performance, and usability.\n- Compare it with similar products/services in the market.\n- Share any personal experiences or insights.\n\nConclusion:\n- Summarize your overall opinion.\n- Recommend whether the product/service is worth purchasing and for whom.",
            "Editorial" => "Editorial Template\n\nIntroduction:\n- Introduce the topic or issue you are addressing.\n- Provide some background information and context.\n\nMain Argument:\n- Present your main argument or viewpoint.\n- Use supporting evidence, examples, and anecdotes.\n\nCounterarguments:\n- Address potential counterarguments or opposing views.\n- Refute them with logical reasoning and evidence.\n\nConclusion:\n- Summarize your main points.\n- Reiterate your stance and suggest any calls to action or next steps.",
            "Interview" => "Interview Template\n\nIntroduction:\n- Introduce the interviewee and their background.\n- Mention the purpose of the interview.\n\nQuestions and Answers:\n- Ask a series of questions and provide the interviewee's answers.\n- Include follow-up questions based on the interviewee's responses.\n\nConclusion:\n- Summarize key points discussed in the interview.\n- Provide any final thoughts or reflections.",
            "How To" => "How To Template\n\nIntroduction:\n- Introduce the topic and explain the importance of the task.\n\nStep-by-Step Instructions:\n- List each step in a clear and logical order.\n- Provide detailed explanations and tips for each step.\n\nConclusion:\n- Summarize the process.\n- Offer any additional tips or considerations.",
            "Topic Introduction" => "Topic Introduction Template\n\nIntroduction:\n- Introduce the topic and its relevance.\n- Provide some background information.\n\nKey Points:\n- Outline the main points related to the topic.\n- Include any important facts or statistics.\n\nConclusion:\n- Summarize the key points.\n- Suggest any further reading or actions.",
            "Opinion" => "Opinion Template\n\nIntroduction:\n- Introduce the topic and your viewpoint.\n\nMain Points:\n- Present your main points and arguments.\n- Support your points with evidence and examples.\n\nConclusion:\n- Summarize your viewpoint.\n- Suggest any calls to action or next steps.",
            "Research" => "Research Template\n\nIntroduction:\n- Introduce the research topic and its importance.\n\nMethods:\n- Describe the research methods used.\n\nFindings:\n- Present the main findings.\n\nConclusion:\n- Summarize the findings and their implications.",
            "Case Study" => "Case Study Template\n\nIntroduction:\n- Introduce the subject of the case study.\n- Provide some background information.\n\nProblem:\n- Describe the problem or challenge faced.\n\nSolution:\n- Explain the solution implemented.\n\nResults:\n- Present the results of the solution.\n\nConclusion:\n- Summarize the key points and lessons learned.",
            "Short Report" => "Short Report Template\n\nIntroduction:\n- Introduce the topic of the report.\n\nMain Points:\n- Present the main points of the report.\n\nConclusion:\n- Summarize the main points.",
            "Think Piece" => "Think Piece Template\n\nIntroduction:\n- Introduce the topic and its relevance.\n\nMain Argument:\n- Present your main argument or viewpoint.\n\nSupporting Points:\n- Provide supporting points and evidence.\n\nConclusion:\n- Summarize your argument and suggest any calls to action.",
            "Hard News" => "Hard News Template\n\nIntroduction:\n- Summarize the main facts of the news story.\n\nDetails:\n- Provide detailed information about the news event.\n\nConclusion:\n- Summarize the key points and any potential implications.",
            "First Person" => "First Person Template\n\nIntroduction:\n- Introduce the topic and your personal connection to it.\n\nMain Story:\n- Tell your personal story or experience.\n\nConclusion:\n- Summarize the main points of your story.",
            "Service Piece" => "Service Piece Template\n\nIntroduction:\n- Introduce the service and its importance.\n\nDetails:\n- Describe the service and how it works.\n\nBenefits:\n- List the key benefits of the service.\n\nConclusion:\n- Summarize the main points and recommend the service.",
            "Informational" => "Informational Template\n\nIntroduction:\n- Introduce the topic and its importance.\n\nMain Points:\n- Present the main points and information.\n\nConclusion:\n- Summarize the main points."
        ];

        if (!isset($templates[$content_type])) {
            wp_send_json_error('Invalid content type');
        }

        $this->updateProgressToPoll("Generating content");
        $prompt = 'Generate content for the following prompt:' . "\n";
        $prompt .= 'Article Idea: ' . esc_html($custom_prompt) . "\n\n";
        $prompt .= 'Based on the author persona details:' . "\n";
        $prompt .= esc_html($persona_details) . "\n\n";
        $prompt .= esc_html($templates[$content_type]) . "\n";
        $prompt .= 'Output the content in HTML format:';


        $api_key = get_option('ai_content_pipelines_oneup_openai_api_key');
        $site_url = get_site_url();


        $request_body = wp_json_encode([
            'prompt' => $prompt,
            'api_key' => $api_key,
            'site_url' => $site_url
        ]);

        $response = wp_remote_post('https://ai.1upmedia.com:443/generate-content-from-prompt', [
            'headers' => [
                'Content-Type' => 'application/json'
            ],
            'body' => $request_body,
            'timeout' => 180
        ]);

        if (is_wp_error($response)) {
            wp_send_json_error(['message' => 'API request failed: ' . $response->get_error_message()]);
            return;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        if (isset($data['type'])) {
            switch ($data['type']) {
                case 'QuotaExceeded':
                    update_option('ai_content_pipelines_oneup_content_generation_in_progress', false);
                    update_option('ai_content_pipelines_oneup_is_last_error', true);
                    update_option('ai_content_pipelines_oneup_last_scheduled_timestamp', time());
                    wp_send_json_error(['type' => 'QuotaExceeded', 'message' => "API key invalid or free limit exhausted"]);
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
            wp_send_json_error(['message' => 'Invalid API response at content']);
            return;
        }

        $html_content = trim($data['content']);

        $this->updateProgressToPoll("Generatingh title...");
        $title_response = wp_remote_post('https://ai.1upmedia.com:443/generate-title-from-prompt', [
            'headers' => [
                'Content-Type' => 'application/json'
            ],
            'body' => wp_json_encode(['content' => $html_content, 'generated_titles' => [], 'stage' => null]),
            'timeout' => 60
        ]);

        if (is_wp_error($title_response)) {
            wp_send_json_error(['message' => 'API request for title failed: ' . $title_response->get_error_message()]);
            return;
        }

        $title_body = wp_remote_retrieve_body($title_response);
        $title_data = json_decode($title_body, true);

        if (!isset($title_data['title'])) {
            wp_send_json_error(['message' => 'Invalid API response for title']);
            return;
        }

        $title = trim($title_data['title']);


        $this->updateProgressToPoll("Generatingh tags...");

        $tags_response = wp_remote_post('https://ai.1upmedia.com:443/get-tags-from-prompt', [
            'headers' => [
                'Content-Type' => 'application/json'
            ],
            'body' => wp_json_encode(['content' => $html_content]),
            'timeout' => 60
        ]);

        if (is_wp_error($tags_response)) {
            wp_send_json_error(['message' => 'API request for tags failed: ' . $tags_response->get_error_message()]);
            return;
        }

        $tags_body = wp_remote_retrieve_body($tags_response);
        $tags_data = json_decode($tags_body, true);

        if (!isset($tags_data['tags'])) {
            wp_send_json_error(['message' => 'Invalid API response for tags']);
            return;
        }

        $tags = implode(', ', $tags_data['tags']);


        $this->updateProgressToPoll("Generatingh Alt text");

        $alt_text_response = wp_remote_post('https://ai.1upmedia.com:443/generate-image-alt-text', [
            'headers' => [
                'Content-Type' => 'application/json'
            ],
            'body' => wp_json_encode(['content' => $title]),
            'timeout' => 60
        ]);

        if (is_wp_error($alt_text_response)) {
            wp_send_json_error(['message' => 'API request for image alt text failed: ' . $alt_text_response->get_error_message()]);
            return;
        }

        $alt_text_body = wp_remote_retrieve_body($alt_text_response);
        $alt_text_data = json_decode($alt_text_body, true);

        if (!isset($alt_text_data['altText'])) {
            wp_send_json_error(['message' => 'Invalid API response for image alt text']);
            return;
        }

        $image_alt = trim($alt_text_data['altText']);


        $this->updateProgressToPoll("Image alt text: ");


        $this->updateProgressToPoll("Generating image..");


        $request_body_image = wp_json_encode([
            'content' => $custom_prompt
        ]);

        $response_image = wp_remote_post('https://ai.1upmedia.com:443/generate-image-from-prompt', [
            'headers' => [
                'Content-Type' => 'application/json'
            ],
            'body' => $request_body_image,
            'timeout' => 180
        ]);

        if (is_wp_error($response_image)) {
            wp_send_json_error(['message' => 'API request for image generation failed: ' . $response_image->get_error_message()]);
            return;
        }

        $body_image = wp_remote_retrieve_body($response_image);
        $data_image = json_decode($body_image, true);

        if (!isset($data_image['image']['data'])) {
            wp_send_json_error(['message' => 'Invalid API response for image generation']);
            return;
        }
        $this->updateProgressToPoll("Received image data..");
        $image_url = $data_image['image'];
        $image_data = base64_decode($data_image['image']['data']);


        $this->updateProgressToPoll("Uploading image..");
        $upload_dir = wp_upload_dir();

        $truncated_title = mb_strimwidth(sanitize_title($title), 0, 50, '');

        $image_filename = $truncated_title . '-' . uniqid() . '.png';
        $image_path = $upload_dir['path'] . '/' . $image_filename;

        // Initialize the WP_Filesystem global
        global $wp_filesystem;
        if (!function_exists('WP_Filesystem')) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
        }

        // Request filesystem credentials if not available
        WP_Filesystem();

        $file_written = $wp_filesystem->put_contents($image_path, $image_data, FS_CHMOD_FILE);



        if (!$file_written) {
            $this->updateProgressToPoll("Image file writing error..");
            wp_send_json_error(['message' => 'Failed to write image file']);
            return;
        }

        $attachment_data = [
            'guid' => $upload_dir['url'] . '/' . $image_filename,
            'post_mime_type' => 'image/png',
            'post_title' => sanitize_file_name($image_filename),
            'post_content' => '',
            'post_status' => 'inherit',
            'post_excerpt' => $image_alt, // Use alt text as caption
            'post_name' => sanitize_title_with_dashes($image_filename),
            'post_author' => get_current_user_id(), // Or use a specific author ID if needed
            'post_date' => current_time('mysql'),
            'post_date_gmt' => current_time('mysql', 1),
        ];

        $attachment_id = wp_insert_attachment($attachment_data, $image_path);

        if (is_wp_error($attachment_id)) {
            wp_send_json_error(['message' => 'Failed to insert attachment']);
            return;
        }

        // Generate attachment metadata
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        $attachment_metadata = wp_generate_attachment_metadata($attachment_id, $image_path);

        // Update the attachment metadata
        wp_update_attachment_metadata($attachment_id, $attachment_metadata);

        // Update the attachment alt text, caption, and description
        update_post_meta($attachment_id, '_wp_attachment_image_alt', $image_alt);
        update_post_meta($attachment_id, 'image_caption', $image_alt); // Caption
        update_post_meta($attachment_id, 'image_description', $image_alt); // Description

        update_post_meta($post_id, '_thumbnail_id', $attachment_id);

        $this->updateProgressToPoll("Getting categories..");

        $categories_response = wp_remote_post('https://ai.1upmedia.com:443/generate-category-from-prompt', [
            'headers' => [
                'Content-Type' => 'application/json'
            ],
            'body' => wp_json_encode(['content' => $html_content]),
            'timeout' => 60
        ]);

        if (is_wp_error($categories_response)) {
            wp_send_json_error(['message' => 'API request for categories failed: ' . $categories_response->get_error_message()]);
            return;
        }

        $categories_body = wp_remote_retrieve_body($categories_response);
        $categories_data = json_decode($categories_body, true);

        if (!isset($categories_data['categories'])) {
            wp_send_json_error(['message' => 'Invalid API response for categories']);
            return;
        }
        $this->updateProgressToPoll("Categories received....");

        $categories = array_map('trim', $categories_data['categories']);

        $generated_categories = [];
        foreach ($categories as $category) {
            $term = term_exists($category, 'category');
            if (!$term) {
                $term = wp_insert_term($category, 'category');
            }
            if (!is_wp_error($term)) {
                $generated_categories[] = $term['term_id'];
            }
        }
        $this->updateProgressToPoll("Adding categories....");

        if (empty($schedule_time) || !is_numeric($schedule_time)) {
            $schedule_time = time(); // Use current time if not set
            $this->updateProgressToPoll("No scheduled time provided, using current time.");
        }

        try {

            $post_data = [
                'ID' => $post_id, // ID of the existing post to update
                'post_title' => $title,
                'post_content' => $html_content,
                'post_type' => 'post',
                'post_date' => gmdate('Y-m-d H:i:s', $schedule_time),
                'post_author' => $author,
                'post_status' => $post_status, // Keep it as draft or change to desired status
                'post_category' => $generated_categories, // Array of category IDs
                'tags_input' => $tags // Comma-separated tags or array of tag names
            ];
        } catch (Exception $e) {
            $this->updateProgressToPoll('Error preparing post data: ' . $e->getMessage());
        }

        $this->updateProgressToPoll("Updating post....");

        $updated_post_id = wp_update_post($post_data);

        if (is_wp_error($updated_post_id)) {
            wp_send_json_error(['message' => 'Failed to update the post']);
            return;
        }

        // Set the featured image (assuming you've already uploaded the image)
        if ($attachment_id) {
            set_post_thumbnail($updated_post_id, $attachment_id);
        }

        $this->updateProgressToPoll("Finding buyers journey....");

        update_post_meta($updated_post_id, 'buyers_journey', ai_content_pipelines_oneup_find_buyers_journey_callback(true));
        update_post_meta($updated_post_id, '_template_type', $content_type);


        update_option('ai_content_pipelines_oneup_is_last_error', false);

        wp_send_json_success([
            'postId' => $updated_post_id,
            'title' => $title,
            'image_alt' => $image_alt,
            'content' => $html_content, // Send HTML content to be displayed
            'image_url' => $image_url,
            'categories' => $generated_categories,
            'tags' => $tags,
            'prompt' => $prompt
        ]);
    }


    public function user_profile_fields($user)
    {
        // Fetch personas and industries (you can replace industries with dynamic data if needed)
        $personas = get_option('ai_content_pipelines_oneup_author_personas', []);
        $assigned_persona = get_user_meta($user->ID, '_assigned_persona', true);

        // Default industries list
        $industries = [
            'Healthcare Practices' => [
                'Dentists',
                'Chiropractors',
                'Physical Therapists',
                'Optometrists',
                'Podiatrists',
                'Psychologists',
                'Occupational Therapists',
                'Speech Therapists',
                'Dermatologists',
                'Cardiologists',
                'Family Medicine Practitioners',
                'Orthodontists',
                'Pediatricians',
                'General Practitioners',
                'Oncologists',
                'Ophthalmologists',
                'ENT Specialists',
                'Gastroenterologists',
                'Urologists',
                'Dietitians/Nutritionists',
                'Acupuncturists',
                'Homeopathic Practitioners'
            ],
            'Retail & E-commerce' => [
                'Apparel Stores',
                'Grocery Stores',
                'Electronics Stores',
                'Home Goods Retailers',
                'Beauty & Cosmetic Stores',
                'Jewelry Stores',
                'Sports Equipment Stores',
                'Bookstores',
                'Pet Stores',
                'Auto Parts Retailers',
                'Outdoor Equipment Stores',
                'Furniture Stores',
                'Toy Stores',
                'Craft and Hobby Stores',
                'Footwear Retailers',
                'Convenience Stores',
                'Online Subscription Services',
                'Health and Wellness Retailers'
            ],
            'Hospitality & Travel' => [
                'Hotels & Resorts',
                'Bed and Breakfasts',
                'Vacation Rentals',
                'Restaurants',
                'Cafes & Coffee Shops',
                'Bars & Pubs',
                'Catering Services',
                'Event Venues',
                'Travel Agencies',
                'Tour Operators',
                'Cruise Lines',
                'Airlines',
                'Car Rental Agencies',
                'Theme Parks',
                'Casinos',
                'Spas & Wellness Centers',
                'Nightclubs',
                'Food Trucks'
            ],
            'Financial Services' => [
                'Banks',
                'Credit Unions',
                'Mortgage Lenders',
                'Insurance Companies',
                'Investment Firms',
                'Accountants & CPAs',
                'Financial Advisors',
                'Tax Preparers',
                'Wealth Management Services',
                'Payment Processing Companies',
                'Fintech Startups',
                'Credit Repair Services',
                'Hedge Funds',
                'Private Equity Firms',
                'Venture Capital Firms',
                'Estate Planning Services',
                'Pension Funds'
            ],
            'Real Estate' => [
                'Residential Realtors',
                'Commercial Real Estate Brokers',
                'Property Management Companies',
                'Real Estate Developers',
                'Real Estate Investment Trusts (REITs)',
                'Home Inspectors',
                'Appraisal Services',
                'Real Estate Attorneys',
                'Mortgage Brokers',
                'Title Companies',
                'Vacation Property Rentals',
                'Corporate Housing Providers',
                'Real Estate Marketing Services',
                'Interior Design for Real Estate',
                'Real Estate Staging Companies',
                'Auction Houses'
            ],
            'Education & Training' => [
                'Primary Schools',
                'Secondary Schools',
                'Universities & Colleges',
                'Vocational Schools',
                'Trade Schools',
                'Online Learning Platforms',
                'Tutors & Test Prep Services',
                'Professional Certification Programs',
                'Language Schools',
                'Music Schools',
                'Dance Studios',
                'Art Schools',
                'Educational Consultancies',
                'Homeschooling Services',
                'Corporate Training Providers',
                'Personal Development Coaches',
                'Continuing Education Providers'
            ],
            'Legal Services' => [
                'Personal Injury Attorneys',
                'Family Law Attorneys',
                'Corporate Law Firms',
                'Criminal Defense Attorneys',
                'Immigration Lawyers',
                'Patent & Intellectual Property Attorneys',
                'Real Estate Lawyers',
                'Employment Lawyers',
                'Bankruptcy Attorneys',
                'Estate Planning Lawyers',
                'Tax Lawyers',
                'Medical Malpractice Lawyers',
                'Class Action Firms',
                'Environmental Law Attorneys',
                'Contract Lawyers',
                'Paralegal Services',
                'Legal Mediation Services',
                'Notary Public Services'
            ],
            'Construction & Trades' => [
                'General Contractors',
                'Electricians',
                'Plumbers',
                'HVAC Technicians',
                'Carpenters',
                'Roofers',
                'Painters',
                'Masons',
                'Flooring Specialists',
                'Landscapers',
                'Interior Designers',
                'Architects',
                'Home Builders',
                'Commercial Construction Firms',
                'Renovation Specialists',
                'Handyman Services',
                'Structural Engineers',
                'Surveyors'
            ],
            'Automotive Services' => [
                'Car Dealerships',
                'Auto Repair Shops',
                'Tire Shops',
                'Auto Body Shops',
                'Car Wash & Detailing Services',
                'Auto Parts Stores',
                'Auto Glass Repair Services',
                'Towing Services',
                'Vehicle Inspection Stations',
                'Motorcycle Dealerships',
                'RV Dealerships',
                'Boat Dealerships',
                'Auto Insurance Agencies',
                'Car Rental Companies',
                'Mobile Mechanic Services',
                'EV Charging Stations'
            ],
            'Technology & IT Services' => [
                'Managed IT Services',
                'Software Development Firms',
                'Web Development Agencies',
                'Cloud Service Providers',
                'Cybersecurity Companies',
                'Data Centers',
                'IT Consulting Firms',
                'Network Infrastructure Providers',
                'App Development Firms',
                'IT Support Services',
                'SaaS Companies',
                'Tech Repair Shops',
                'Tech Staffing Agencies',
                'Data Recovery Services',
                'Blockchain Development Firms',
                'Digital Transformation Consultants'
            ],
            'Media & Entertainment' => [
                'Film Production Companies',
                'TV Networks',
                'Radio Stations',
                'Music Production Studios',
                'Event Planners',
                'Live Event Venues',
                'Talent Agencies',
                'Videographers & Photographers',
                'Digital Media Agencies',
                'Social Media Influencers',
                'Public Relations Firms',
                'Graphic Design Studios',
                'Animation Studios',
                'Streaming Services',
                'Podcast Production Companies',
                'Publishing Houses',
                'Magazines & Newspapers',
                'Advertising Agencies',
                'Video Game Development Firms'
            ],
            'Manufacturing & Industrial' => [
                'Aerospace Manufacturers',
                'Automotive Manufacturers',
                'Electronics Manufacturers',
                'Food & Beverage Manufacturers',
                'Furniture Manufacturers',
                'Packaging Companies',
                'Chemical Manufacturers',
                'Steel & Metal Fabricators',
                'Pharmaceuticals Manufacturers',
                'Clothing & Apparel Manufacturers',
                'Machine Tool Companies',
                'Industrial Equipment Suppliers',
                'Oil & Gas Refining',
                'Renewable Energy Manufacturers',
                'Construction Materials Suppliers',
                'Paper Products Manufacturers',
                'Plastics Manufacturers'
            ]
        ];
        $assigned_location = get_user_meta($user->ID, '_assigned_location', true);
        $assigned_industry = get_user_meta($user->ID, '_assigned_industry', true);
        $assigned_language = get_user_meta($user->ID, '_assigned_language', true);
        $assigned_url = get_user_meta($user->ID, '_assigned_url', true);
        $assigned_business_detail = get_user_meta($user->ID, '_assigned_business_detail', true);
        $assigned_domain_authority = get_user_meta($user->ID, '_assigned_domain_authority', true);
        $assigned_content_strategy = get_user_meta($user->ID, '_assigned_content_strategy', true);
        ?>
            <h3><?php esc_html_e("Author Persona & Industry", "ai-content-pipelines"); ?></h3>
            < class="form-table">
                <!-- Author Persona Section -->
                <tr>
                    <th><label for="author_persona"><?php esc_html_e('Select Persona', 'ai-content-pipelines'); ?></label></th>
                    <td>
                        <select name="author_persona" id="author_persona">
                            <?php foreach ($personas as $persona): ?>
                                <option value="<?php echo esc_attr($persona['persona']); ?>" <?php selected($assigned_persona, $persona['persona']); ?>><?php echo esc_html($persona['persona']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>

                <!-- Author Industry Section -->
                <tr>
                    <th><label for="author_industry"><?php esc_html_e('Select Industry', 'ai-content-pipelines'); ?></label>
                    </th>
                    <td>
                        <select name="author_industry" id="author_industry">
                            <option value="General">General (No Industry Specific)</option>
                            <?php foreach ($industries as $category => $industry_list): ?>
                                <optgroup label="<?php echo esc_attr($category); ?>">
                                    <?php foreach ($industry_list as $industry): ?>
                                        <option value="<?php echo esc_attr($industry); ?>" <?php selected($assigned_industry, $industry); ?>>
                                            <?php echo esc_html($industry); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </optgroup>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>

                <tr>
                    <th><label for="author_language"><?php esc_html_e('Author language', 'ai-content-pipelines'); ?></label>
                    </th>
                    <td>
                        <select name="author_language" id="author_language">
                            <option value="English">English</option>
                            <option value="Spanish">Spanish (Español)</option>
                            <option value="French">French (Français)</option>
                            <option value="German">German (Deutsch)</option>
                            <option value="Chinese">Chinese (中文)</option>
                            <option value="Japanese">Japanese (日本語)</option>
                            <option value="Korean">Korean (한국어)</option>
                            <option value="Portuguese">Portuguese (Português)</option>
                            <option value="Italian">Italian (Italiano)</option>
                            <option value="Dutch">Dutch (Nederlands)</option>
                            <option value="Russian">Russian (Русский)</option>
                            <option value="Arabic">Arabic (العربية)</option>
                            <option value="Hindi">Hindi (हिन्दी)</option>
                            <option value="Bengali">Bengali (বাংলা)</option>
                            <option value="Turkish">Turkish (Türkçe)</option>
                            <option value="Vietnamese">Vietnamese (Tiếng Việt)</option>
                            <option value="Polish">Polish (Polski)</option>
                            <option value="Romanian">Romanian (Română)</option>
                            <option value="Thai">Thai (ไทย)</option>
                            <option value="Swedish">Swedish (Svenska)</option>
                            <option value="Czech">Czech (Čeština)</option>
                        </select>
                    </td>
                </tr>

                <tr>
                    <th><label for="author_location"><?php esc_html_e('Location', 'ai-content-pipelines'); ?></label></th>
                    <td>
                        <input type="text" name="author_location" id="author_location"
                            value="<?php echo esc_attr($assigned_location); ?>"
                            placeholder="<?php esc_attr_e('Enter Location', 'ai-content-pipelines'); ?>" />
                    </td>
                </tr>

                <tr>
                    <th><label for="author_url"><?php esc_html_e('url', 'ai-content-pipelines'); ?></label></th>
                    <td>
                        <input type="text" name="author_url" id="author_url" value="<?php echo esc_attr($assigned_url); ?>"
                            placeholder="<?php esc_attr_e('Enter Url', 'ai-content-pipelines'); ?>" />
                    </td>
                </tr>

                <tr>
                    <th><label
                            for="author_business_detail"><?php esc_html_e('business_detail', 'ai-content-pipelines'); ?></label>
                    </th>
                    <td>
                        <input type="text" name="author_business_detail" id="author_business_detail"
                            value="<?php echo esc_attr($assigned_business_detail); ?>"
                            placeholder="<?php esc_attr_e('Enter business detail', 'ai-content-pipelines'); ?>" />
                    </td>
                </tr>

                <tr>
                    <th><label
                            for="author_domain_authority"><?php esc_html_e('domain_authority', 'ai-content-pipelines'); ?></label>
                    </th>
                    <td>
                        <input type="text" name="author_domain_authority" id="author_domain_authority"
                            value="<?php echo esc_attr($assigned_domain_authority); ?>"
                            placeholder="<?php esc_attr_e('Enter Domain Authority', 'ai-content-pipelines'); ?>" />
                    </td>
                </tr>

                <tr>
                    <th><label
                            for="author_content_strategy"><?php esc_html_e('content_strategy', 'ai-content-pipelines'); ?></label>
                    </th>
                    <td>
                        <input type="text" name="author_content_strategy" id="author_content_strategy"
                            value="<?php echo esc_attr($assigned_content_strategy); ?>"
                            placeholder="<?php esc_attr_e('Enter Content strategy', 'ai-content-pipelines'); ?>" />
                    </td>
                </tr>

                </table>
                <?php
                // Output nonce field
                wp_nonce_field('save_user_persona_nonce_action', 'save_user_persona_nonce');
    }



    public function ai_content_pipelines_oneup_save_user_profile_fields($user_id)
    {
        // Check if the current user has permission to edit the user
        if (!current_user_can('edit_user', $user_id)) {
            return false;
        }

        // Verify nonce
        if (!isset($_POST['save_user_persona_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['save_user_persona_nonce'])), 'save_user_persona_nonce_action')) {
            return false;
        }

        // Save the author persona if it is set
        if (isset($_POST['author_persona'])) {
            update_user_meta($user_id, '_assigned_persona', sanitize_text_field(wp_unslash($_POST['author_persona'])));
        }

        if (isset($_POST['author_industry'])) {
            update_user_meta($user_id, '_assigned_industry', sanitize_text_field(wp_unslash($_POST['author_industry'])));
        }

        if (isset($_POST['author_language'])) {
            update_user_meta($user_id, '_assigned_language', sanitize_text_field(wp_unslash($_POST['author_language'])));
        }

        if (isset($_POST['author_location'])) {
            update_user_meta($user_id, '_assigned_location', sanitize_text_field(wp_unslash($_POST['author_location'])));
        }

        if (isset($_POST['author_url'])) {
            update_user_meta($user_id, '_assigned_url', sanitize_text_field(wp_unslash($_POST['author_url'])));
        }

        if (isset($_POST['author_business_detail'])) {
            update_user_meta($user_id, '_assigned_business_detail', sanitize_text_field(wp_unslash($_POST['author_business_detail'])));
        }
        if (isset($_POST['author_domain_authority'])) {
            update_user_meta($user_id, '_assigned_domain_authority', sanitize_text_field(wp_unslash($_POST['author_domain_authority'])));
        }
        if (isset($_POST['author_content_strategy'])) {
            update_user_meta($user_id, '_assigned_content_strategy', sanitize_text_field(wp_unslash($_POST['author_content_strategy'])));
        }

        return;
    }

    public function publish_post_function($post_id)
    {
        $post = [
            'ID' => $post_id,
            'post_status' => 'publish',
        ];
        $result = wp_update_post($post);
        if (is_wp_error($result)) {
            // Return error message
            return $result->get_error_message();
        } else {
            // Return success message or the post ID
            return 'Post published successfully';
        }

    }
}

function ai_content_pipelines_oneup_add_persona_dropdown_to_add_new_user()
{
    $personas = get_option('ai_content_pipelines_oneup_author_personas', []);
    ?>
            <h2><?php esc_html_e('Persona', 'ai-content-pipelines'); ?></h2>
            <table class="form-table">
                <tr>
                    <th><label for="persona"><?php esc_html_e('Persona', 'ai-content-pipelines'); ?></label></th>
                    <td>
                        <select name="persona" id="persona" class="regular-text">
                            <?php foreach ($personas as $persona): ?>
                                <option value="<?php echo esc_attr($persona['persona']); ?>">
                                    <?php echo esc_html($persona['persona']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
            </table>
            <?php
}
add_action('user_new_form', 'ai_content_pipelines_oneup_add_persona_dropdown_to_add_new_user');

function ai_content_pipelines_oneup_enqueue_custom_admin_script()
{
    wp_enqueue_script('custom-admin-js', plugin_dir_url(__FILE__) . 'assets/custom-admin.js', array('jquery'), '1.0', true);
}
add_action('admin_enqueue_scripts', 'ai_content_pipelines_oneup_enqueue_custom_admin_script');

function ai_content_pipelines_oneup_enqueue_persona_meta_box_script()
{
    wp_enqueue_script('persona-meta-box', get_template_directory_uri() . '/js/persona-meta-box.js', ['jquery'], '1.0.0', true);
}
add_action('admin_enqueue_scripts', 'ai_content_pipelines_oneup_enqueue_persona_meta_box_script');

// function enqueue_admin_scripts()
// {
//     // Enqueue admin JavaScript file
//     wp_enqueue_script('author-personas-admin', plugin_dir_url(__FILE__) . 'assets/admin.js', ['jquery', 'wp-editor', 'wp-data'], '1.0.0', true);

//     // Enqueue toastr.js and toastr.css for notifications
//     wp_enqueue_script('toastr-js', 'https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js', array('jquery'), '2.1.4', true);
//     wp_enqueue_style('toastr-css', 'https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css', [], '2.1.4');

//     // Enqueue admin CSS file
//     wp_enqueue_style('author-personas-admin', plugin_dir_url(__FILE__) . 'assets/admin.css', [], '1.0.0');

//     // Localize script to pass dynamic PHP variables into the JS file
//     wp_localize_script('author-personas-admin', 'myAjax', array(
//         'ajaxurl' => admin_url('admin-ajax.php'),
//         'nonce' => wp_create_nonce('my_generic_action'),
//         'linkedinAccessToken' => esc_js(get_option('ai_content_pipelines_oneup_linkedin_access_token')),
//         'facebookAccessToken' => esc_js(get_option('ai_content_pipelines_oneup_facebook_access_token')),
//         'facebookPageId' => esc_js(get_option('facebook_page_id'))
//     ));
// }
// add_action('admin_enqueue_scripts', 'enqueue_admin_scripts');



add_filter('manage_posts_columns', 'ai_content_pipelines_oneup_add_template_column');
function ai_content_pipelines_oneup_add_template_column($columns)
{
    $columns['template'] = 'Template';
    $columns['buyersJourneyStages'] = "Buyers Journey Stages";
    return $columns;
}

add_action('manage_posts_custom_column', 'ai_content_pipelines_oneup_fill_template_column', 10, 2);
function ai_content_pipelines_oneup_fill_template_column($column_name, $post_id)
{
    if ($column_name === 'template') {
        // Assuming you store the template type as post meta
        $template_type = get_post_meta($post_id, '_template_type', true);
        echo esc_html($template_type);
    }

    if ($column_name === 'buyersJourneyStages') {
        // Get the template type
        $template_type = get_post_meta($post_id, '_template_type', true);

        // Define the mapping for template styles to buyer's journey stages
        $template_style_mapping = [
            'Awareness' => 'Top of the Funnel',
            'Consideration' => 'Middle of the Funnel',
            'Decision' => 'Bottom of the Funnel'
        ];

        // Check if the template style directly maps to a funnel stage
        if (array_key_exists($template_type, $template_style_mapping)) {
            // Display the corresponding stage based on the template type
            echo esc_html($template_style_mapping[$template_type]);
        } else {
            // If not directly mapped, check if 'buyers_journey' meta exists
            $stage = get_post_meta($post_id, 'buyers_journey', true);

            if ($stage) {
                // Map the short forms (MoF, ToF, BoF) to the full forms
                $stage_mapping = [
                    'ToF' => 'Top of the Funnel',
                    'MoF' => 'Middle of the Funnel',
                    'BoF' => 'Bottom of the Funnel'
                ];

                // Display the full form if the stage exists in the mapping
                echo esc_html($stage_mapping[$stage] ?? $stage);
            } else {
                // Display a button to find the buyer's journey if the meta field doesn't exist
                ?>
                        <button class="button find-journey" data-post-id="<?php echo esc_attr($post_id); ?>">Find Buyer's
                            Journey</button>
                        <span class="journey-status" id="journey-status-<?php echo esc_attr($post_id); ?>"></span>
                        <?php
            }
        }
    }
}



// This is an example of handling quick edit response
// add_action('wp_ajax_inline-save', 'handle_quick_edit_response');

// function handle_quick_edit_response()
// {
//     // Process the quick edit request
//     $post_id = intval($_POST['post_ID']);
//     $post_title = sanitize_text_field($_POST['post_title']);

//     // Update the post
//     $result = wp_update_post(
//         array(
//             'ID' => $post_id,
//             'post_title' => $post_title,
//         ),
//         true
//     );

//     // Check for errors
//     if (is_wp_error($result)) {
//         wp_send_json_error($result->get_error_message());
//     } else {
//         // Properly form the response
//         $response = array(
//             'ID' => $post_id,
//             'post_title' => $post_title,
//         );

//         // Send the JSON response
//         wp_send_json_success($response);
//     }
// }

add_action('post_submitbox_start', 'ai_content_pipelines_oneup_add_custom_hidden_inputs');
function ai_content_pipelines_oneup_add_custom_hidden_inputs()
{
    global $post;
    if ($post) {
        // Remove post_ID since WordPress handles it automatically
        // echo '<input type="hidden" id="post_ID" name="post_ID" value="' . esc_attr($post->ID) . '">';

        // Use a unique nonce field name instead of '_wpnonce'
        wp_nonce_field('check_post_update_nonce', 'check_post_update_nonce_field');
    }
}


function add_calendar_menu_page()
{
    add_menu_page(
        'Calendar View', // Page title
        'Content Calendar', // Menu title
        'manage_options', // Capability
        'content-calendar', // Menu slug
        'display_calendar_page', // Function to display the page
        'dashicons-calendar-alt', // Icon
        6 // Position
    );
}
add_action('admin_menu', 'add_calendar_menu_page');

function display_calendar_page()
{
    echo '<div id="calendar"></div>';
}

function fetch_calendar_posts()
{
    $start_date = $_POST['start'];
    $end_date = $_POST['end'];

    $args = array(
        'post_type' => 'post',
        'post_status' => array('publish', 'future'),
        'date_query' => array(
            array(
                'after' => $start_date,
                'before' => $end_date,
                'inclusive' => true,
            ),
        ),
    );

    $posts = new WP_Query($args);
    $events = array();

    if ($posts->have_posts()) {
        while ($posts->have_posts()) {
            $posts->the_post();

            $events[] = array(
                'title' => get_the_title(),
                'start' => get_the_date('Y-m-d'),
                'url' => get_permalink(),
                'editLink' => get_edit_post_link(),
            );
        }
    }

    wp_reset_postdata();
    wp_send_json($events);
}
add_action('wp_ajax_fetch_calendar_posts', 'fetch_calendar_posts');

function ai_content_pipelines_oneup_add_custom_schema_and_meta()
{
    if (is_single()) {
        global $post;
        $content_type = get_post_meta($post->ID, '_template_type', true);

        $title = esc_html(get_the_title($post));
        $author = esc_html(get_the_author_meta('display_name', $post->post_author));
        $excerpt = esc_attr(get_the_excerpt($post));
        $blogname = esc_html(get_bloginfo('name'));
        $datePublished = esc_attr(get_the_date('c', $post));
        $dateModified = esc_attr(get_the_modified_date('c', $post));
        $url = esc_url(get_permalink($post));
        $image = esc_url(get_the_post_thumbnail_url($post, 'full'));
        $author_url = esc_url(get_author_posts_url($post->post_author));
        $author_image = esc_url(get_avatar_url($post->post_author));
        $reading_time = '3 minutes'; // Placeholder, consider dynamic calculation

        $schema = '';
        switch ($content_type) {
            case 'Review':
                $schema = '
                {
                  "@context": "https://schema.org/",
                  "@type": "Review",
                  "itemReviewed": {
                    "@type": "Thing",
                    "name": "' . $title . '"
                  },
                  "author": {
                    "@type": "Person",
                    "name": "' . $author . '",
                    "url": "' . $author_url . '",
                    "image": "' . $author_image . '"
                  },
                  "reviewRating": {
                    "@type": "Rating",
                    "ratingValue": "5",
                    "bestRating": "5"
                  },
                  "publisher": {
                    "@type": "Organization",
                    "name": "' . $blogname . '"
                  },
                  "datePublished": "' . $datePublished . '",
                  "dateModified": "' . $dateModified . '"
                }';
                break;
            // Handle other cases similarly...
            default:
                $schema = '
            {
              "@context": "https://schema.org/",
              "@type": "Review",
              "itemReviewed": {
                "@type": "Thing",
                "name": "' . $title . '"
              },
              "author": {
                "@type": "Person",
                "name": "' . $author . '",
                "url": "' . $author_url . '",
                "image": "' . $author_image . '"
              },
              "reviewRating": {
                "@type": "Rating",
                "ratingValue": "5",
                "bestRating": "5"
              },
              "publisher": {
                "@type": "Organization",
                "name": "' . $blogname . '"
              },
              "datePublished": "' . $datePublished . '",
              "dateModified": "' . $dateModified . '"
            }';
                break;
        }

        $meta = '
        <title>' . $title . ' - ' . $blogname . '</title>
        <link rel="canonical" href="' . $url . '" />
        <meta property="og:locale" content="en_US" />
        <meta property="og:type" content="article" />
        <meta property="og:title" content="' . $title . ' - ' . $blogname . '" />
        <meta property="og:description" content="' . $excerpt . '" />
        <meta property="og:url" content="' . $url . '" />
        <meta property="og:site_name" content="' . $blogname . '" />
        <meta property="article:published_time" content="' . $datePublished . '" />
        <meta property="article:modified_time" content="' . $dateModified . '" />
        <meta property="og:image" content="' . $image . '" />
        <meta property="og:image:width" content="1024" />
        <meta property="og:image:height" content="1024" />
        <meta property="og:image:type" content="image/png" />
        <meta name="author" content="' . $author . '" />
        <meta name="twitter:card" content="summary_large_image" />
        <meta name="twitter:label1" content="Written by" />
        <meta name="twitter:data1" content="' . $author . '" />
        <meta name="twitter:label2" content="Est. reading time" />
        <meta name="twitter:data2" content="' . $reading_time . '" />';

        // Output the schema
        if (!empty($schema)) {
            // Escape the JSON content before outputting it
            echo '<script type="application/ld+json">' . wp_json_encode(json_decode($schema)) . '</script>';
        }


        // Output the meta tags
        echo wp_kses($meta, array(
            'title' => array(),
            'meta' => array(
                'property' => array(),
                'content' => array(),
                'name' => array(),
            ),
            'link' => array(
                'rel' => array(),
                'href' => array(),
            ),
        ));
    }
}
add_action('wp_head', 'ai_content_pipelines_oneup_add_custom_schema_and_meta');


// function check_post_update()
// {
//     if (!isset($_POST['_ajax_nonce']) || !wp_verify_nonce($_POST['_ajax_nonce'], 'my_generic_action')) {
//         wp_send_json_error(['message' => 'Invalid nonce']);
//         return;
//     }

//     if (!isset($_POST['post_id']) || !current_user_can('edit_post', $_POST['post_id'])) {
//         wp_send_json_error(['message' => 'Unauthorized']);
//     }

//     $post_id = intval($_POST['post_id']);
//     $post = get_post($post_id);

//     if (!$post) {
//         wp_send_json_error(['message' => 'Post not found.']);
//     }

//     // Prepare the post data
//     $post_data = [
//         'ID' => $post_id,
//         'post_title' => sanitize_text_field($_POST['post_title']),
//         'post_content' => wp_kses_post($_POST['content']),
//         'post_status' => sanitize_text_field($_POST['post_status']),
//         'post_name' => sanitize_title($_POST['post_name']),
//         'post_date' => sanitize_text_field($_POST['post_date']),
//         'post_password' => sanitize_text_field($_POST['post_password']),
//     ];

//     if (isset($_POST['post_visibility'])) {
//         $visibility = sanitize_text_field($_POST['post_visibility']);
//         if ($visibility === 'password') {
//             $post_data['post_password'] = sanitize_text_field($_POST['post_password']);
//             $post_data['post_status'] = 'private';
//         } elseif ($visibility === 'private') {
//             $post_data['post_status'] = 'private';
//         }
//     }


//     if (isset($_POST['post_category'])) {
//         $categories = array_map('intval', $_POST['post_category']);
//         wp_set_post_categories($post_id, $categories);
//     }

//     if (isset($_POST['tags_input'])) {
//         $tags = sanitize_text_field($_POST['tags_input']);
//         wp_set_post_tags($post_id, explode(',', $tags));
//     }


//     $updated_post_id = wp_update_post($post_data, true);

//     if (is_wp_error($updated_post_id)) {
//         wp_send_json_error(['message' => 'Failed to update post.']);
//     }

//     if (isset($_POST['featured_image_id']) && !empty($_POST['featured_image_id'])) {
//         $featured_image_id = intval($_POST['featured_image_id']);
//         set_post_thumbnail($post_id, $featured_image_id);
//     }

//     wp_send_json_success(['message' => 'Post updated successfully.', 'posy info' => $post]);
// }
// add_action('wp_ajax_check_post_update', 'check_post_update');



// // Save Twitter access tokens
// add_action('wp_ajax_save_twitter_access_token', function () {

//     if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'author_personas_nonce')) {
//         wp_send_json_error('Nonce verification failed.');
//         return;
//     }

//     if (isset($_POST['access_token']) && isset($_POST['access_token_secret'])) {
//         update_option('ai_content_pipelines_oneup_twitter_access_token', sanitize_text_field(wp_unslash($_POST['access_token'])));
//         update_option('ai_content_pipelines_oneup_twitter_access_token_secret', sanitize_text_field(wp_unslash($_POST['access_token_secret'])));
//         wp_send_json_success('Twitter access tokens saved.');
//     } else {
//         wp_send_json_error('Invalid data.');
//     }
// });

// // Remove Twitter access tokens
// add_action('wp_ajax_remove_twitter_access_token', function () {
//     delete_option('ai_content_pipelines_oneup_twitter_access_token');
//     delete_option('ai_content_pipelines_oneup_twitter_access_token_secret');
//     wp_send_json_success('Twitter access tokens removed.');
// });


add_action('wp_ajax_save_linkedin_access_token', 'ai_content_pipelines_oneup_save_linkedin_access_token');
function ai_content_pipelines_oneup_save_linkedin_access_token()
{
    if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'my_generic_action')) {
        wp_send_json_error('Nonce verification failed.');
        return;
    }

    if (isset($_POST['access_token'])) {
        $access_token = isset($_POST['access_token']) ? sanitize_text_field(wp_unslash($_POST['access_token'])) : '';
        update_option('ai_content_pipelines_oneup_linkedin_access_token', $access_token);
        wp_send_json_success('Access token saved successfully.');
    } else {
        wp_send_json_error('Access token not provided.');
    }
}

add_action('wp_ajax_remove_linkedin_access_token', 'ai_content_pipelines_oneup_remove_linkedin_access_token');

function ai_content_pipelines_oneup_remove_linkedin_access_token()
{
    if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'my_generic_action')) {
        wp_send_json_error('Nonce verification failed.');
        return;
    }

    delete_option('ai_content_pipelines_oneup_linkedin_access_token');
    wp_send_json_success('Access token removed successfully.');
}

// Save Facebook Access Token
add_action('wp_ajax_save_facebook_access_token', 'ai_content_pipelines_oneup_save_facebook_access_token');
function ai_content_pipelines_oneup_save_facebook_access_token()
{
    if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'my_generic_action')) {
        wp_send_json_error('Nonce verification failed.');
        return;
    }
    if (isset($_POST['access_token'])) {
        update_option('ai_content_pipelines_oneup_facebook_access_token', sanitize_text_field(wp_unslash($_POST['access_token'])));
        wp_send_json_success('Access token saved');
    } else {
        wp_send_json_error('No access token provided');
    }
}

// Remove Facebook Access Token
add_action('wp_ajax_remove_facebook_access_token', 'ai_content_pipelines_oneup_remove_facebook_access_token');
function ai_content_pipelines_oneup_remove_facebook_access_token()
{
    if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'my_generic_action')) {
        wp_send_json_error('Nonce verification failed.');
        return;
    }
    delete_option('ai_content_pipelines_oneup_facebook_access_token');
    delete_option('ai_content_pipelines_oneup_facebook_page_id');
    wp_send_json_success('Access token removed');
}


function ai_content_pipelines_oneup_load_facebook_pages()
{

    if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'my_generic_action')) {
        wp_send_json_error('Nonce verification failed.');
        return;
    }
    // Get the access token from the AJAX request
    $access_token = isset($_POST['access_token']) ? sanitize_text_field(wp_unslash($_POST['access_token'])) : '';

    if (empty($access_token)) {
        wp_send_json_error('Access token is required.');
    }

    // Prepare the API request
    $api_url = 'https://ai.1upmedia.com:443/facebook/getFacebookPages';

    $response = wp_remote_post($api_url, [
        'body' => wp_json_encode(['access_token' => $access_token]),
        'headers' => [
            'Content-Type' => 'application/json',
        ],
    ]);

    if (is_wp_error($response)) {
        wp_send_json_error('Error loading Facebook pages: ' . $response->get_error_message());
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    if (isset($data['success']) && $data['success']) {
        // Save each page's access token in the WordPress options
        foreach ($data['pages'] as $page) {
            $option_name = 'ai_content_pipelines_oneup_facebook_page_access_token__' . $page['pageId'];
            update_option($option_name, $page['pageAccessToken']);
        }

        wp_send_json_success($data['pages']);
    } else {
        wp_send_json_error('Failed to load Facebook pages: ' . $data['error']);
    }
}

// Add the action to handle the AJAX request
add_action('wp_ajax_load_facebook_pages', 'ai_content_pipelines_oneup_load_facebook_pages');
add_action('wp_ajax_nopriv_load_facebook_pages', 'ai_content_pipelines_oneup_load_facebook_pages');

function ai_content_pipelines_oneup_update_facebook_page_id()
{
    if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'my_generic_action')) {
        wp_send_json_error('Nonce verification failed.');
        return;
    }

    if (isset($_POST['facebook_page_id'])) {
        $facebook_page_id = isset($_POST['facebook_page_id']) ? sanitize_text_field(wp_unslash($_POST['facebook_page_id'])) : '';
        update_option('ai_content_pipelines_oneup_facebook_page_id', $facebook_page_id);
        wp_send_json_success('Facebook Page ID updated.');
    } else {
        wp_send_json_error('Facebook Page ID not provided.');
    }
}

// Add the action to handle the AJAX request for updating the page ID
add_action('wp_ajax_update_facebook_page_id', 'ai_content_pipelines_oneup_update_facebook_page_id');
add_action('wp_ajax_nopriv_update_facebook_page_id', 'ai_content_pipelines_oneup_update_facebook_page_id');


function ai_content_pipelines_dashboard_widget()
{
    wp_add_dashboard_widget(
        'ai_content_pipelines_widget', // Widget slug
        'AI Content Pipelines', // Title of the widget
        'AI_Content_Pipelines_Oneup_AuthorPersonas' // Display function
    );
}
add_action('wp_dashboard_setup', 'ai_content_pipelines_dashboard_widget');

function AI_Content_Pipelines_Oneup_AuthorPersonas()
{
    ?>
            <div class="ai-content-pipelines-widget">
                <h3>AI Content Pipelines</h3>
                <p>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=author-personas')); ?>"
                        class="button button-primary">
                        Generate Pipelines
                    </a>
                </p>
            </div>
            <?php
}


add_action('wp_ajax_upload_custom_image', 'ai_content_pipelines_oneup_upload_custom_image');
function ai_content_pipelines_oneup_upload_custom_image()
{
    check_ajax_referer('my_generic_action', 'nonce');

    if (!isset($_FILES['custom_image']) || empty($_FILES['custom_image']['name'])) {
        wp_send_json_error(['message' => 'No image file provided']);
        return;
    }

    require_once(ABSPATH . 'wp-admin/includes/file.php');
    require_once(ABSPATH . 'wp-admin/includes/media.php');
    require_once(ABSPATH . 'wp-admin/includes/image.php');

    $upload = wp_handle_upload($_FILES['custom_image'], ['test_form' => false]);

    if (isset($upload['error']) && !empty($upload['error'])) {
        wp_send_json_error(['message' => $upload['error']]);
        return;
    }

    $attachment_id = wp_insert_attachment([
        'guid' => $upload['url'],
        'post_mime_type' => $upload['type'],
        'post_title' => sanitize_file_name($upload['file']),
        'post_content' => '',
        'post_status' => 'inherit'
    ], $upload['file']);

    if (is_wp_error($attachment_id)) {
        wp_send_json_error(['message' => $attachment_id->get_error_message()]);
        return;
    }

    wp_update_attachment_metadata($attachment_id, wp_generate_attachment_metadata($attachment_id, $upload['file']));

    $image_info = [
        'id' => $attachment_id,
        'url' => wp_get_attachment_url($attachment_id),
        'title' => get_the_title($attachment_id),
        'alt' => get_post_meta($attachment_id, '_wp_attachment_image_alt', true)
    ];

    wp_send_json_success(['image_info' => $image_info]);
}

add_action('wp_ajax_find_buyers_journey', 'ai_content_pipelines_oneup_find_buyers_journey_callback');

function ai_content_pipelines_oneup_find_buyers_journey_callback($shouldReturn = false)
{

    if (!isset($_POST['_ajax_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_ajax_nonce'])), 'my_generic_action')) {
        if (!$shouldReturn) {
            wp_send_json_error('Invalid nonce');
            return;
        }
    }
    // Check if the required data is provided
    if (!isset($_POST['post_id'])) {
        wp_send_json_error(['message' => 'Invalid post ID']);
    }

    $post_id = intval($_POST['post_id']);
    $content = get_post_field('post_content', $post_id);

    if (!$content) {
        wp_send_json_error(['message' => 'Post content not found']);
    }

    // Make the API call to your backend to classify the funnel stage
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
        wp_send_json_error(['message' => 'API request failed: ' . esc_html($response->get_error_message())]);
    }

    // Retrieve and decode the response body
    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    // Validate the response data
    if (!isset($data['stage'])) {
        wp_send_json_error(['message' => 'Invalid API response']);
    }

    // Get the stage (MoF, ToF, or BoF)
    $stage = sanitize_text_field($data['stage']);

    // Update the post meta with the determined stage
    update_post_meta($post_id, 'buyers_journey', $stage);

    if (!$shouldReturn) {
        // Return success response with the stage
        wp_send_json_success(['stage' => $stage]);
    }

    return $stage;

    wp_die(); // Always die in functions hooked to AJAX actions
}

// Add the AJAX action hook
add_action('wp_ajax_fetch_business_details', 'fetch_business_details');

function fetch_business_details()
{
    // Check the nonce for security
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'my_generic_action')) {
        wp_send_json_error(['error' => 'Invalid nonce']);
        return;
    }

    // Get the URL from the AJAX request
    $url = isset($_POST['url']) ? sanitize_text_field($_POST['url']) : '';
    $location = isset($_POST['location']) ? sanitize_text_field($_POST['location']) : '';

    if (empty($url)) {
        wp_send_json_error(['error' => 'No URL provided']);
        return;
    }

    // Make the POST request to the external API
    $api_url = 'https://ai.1upmedia.com:443/get-business-details';

    $response = wp_remote_post($api_url, array(
        'body' => json_encode(['url' => $url, 'location' => $location]),
        'headers' => array(
            'Content-Type' => 'application/json'
        ),
        'timeout' => 45
    ));

    // Check for any errors in the response
    if (is_wp_error($response)) {
        wp_send_json_error(['error' => 'Failed to fetch business details from API']);
        return;
    }

    // Get the response body and decode it
    $response_body = wp_remote_retrieve_body($response);
    $business_details = json_decode($response_body, true);

    if (empty($business_details)) {
        wp_send_json_error(['error' => 'No business details found']);
        return;
    }

    // Return the business details to the frontend
    wp_send_json_success($business_details);
}




add_action('wp_ajax_get_authors_with_business_detail', 'get_authors_with_business_detail');
function get_authors_with_business_detail()
{
    // Check nonce for security
    check_ajax_referer('my_generic_action', 'nonce');

    $users_with_business_detail = [];

    // Get all users who have '_assigned_business_detail' set
    $users = get_users([
        'role__in' => ['administrator', 'editor', 'author'], // Adjust roles as necessary
        'meta_key' => '_assigned_business_detail',
        'meta_compare' => 'EXISTS'
    ]);

    foreach ($users as $user) {
        $business_detail = get_user_meta($user->ID, '_assigned_business_detail', true);

        // Only add the user if business_detail is not empty
        if (!empty($business_detail)) {
            $users_with_business_detail[] = [
                'id' => $user->ID,
                'display_name' => $user->display_name,
                'business_detail' => $business_detail,
            ];
        }
    }


    wp_send_json_success($users_with_business_detail);
}

// Hook into the post publishing event
add_action('publish_post', 'submit_post_to_indexnow', 10, 2);

function submit_post_to_indexnow($ID, $post)
{
    // Check if it's a post and published
    if ($post->post_type != 'post' || $post->post_status != 'publish') {
        return;
    }

    // IndexNow API key and file details
    $api_key = '7d963a4235614261ab79c1f201c072b8'; // Replace with actual API key
    $key_file = ABSPATH . $api_key . '.txt'; // File path
    $key_file_url = get_site_url() . '/' . $api_key . '.txt'; // URL to the file
    $host = get_site_url(); // Your website URL

    // Ensure the IndexNow key file exists
    if (!file_exists($key_file)) {
        // Create the key file with the API key as its content
        $file_content = $api_key;
        if (!file_put_contents($key_file, $file_content)) {
            error_log('Failed to create IndexNow key file.');
            return;
        }
        error_log('IndexNow key file created successfully.');
    }

    // Get the URL of the published post
    $post_url = get_permalink($ID);

    // Prepare the payload for the IndexNow API
    $payload = json_encode(array(
        'host' => $host,
        'key' => $api_key,
        'keyLocation' => $key_file_url,
        'urlList' => array($post_url)
    ));

    // Submit the published post URL to IndexNow
    $response = wp_remote_post('https://api.indexnow.org/indexnow', array(
        'method' => 'POST',
        'body' => $payload,
        'headers' => array(
            'Content-Type' => 'application/json; charset=utf-8',
        ),
    ));

    // Handle the response from IndexNow
    if (is_wp_error($response)) {
        error_log('IndexNow URL Submission failed: ' . $response->get_error_message());
    } else {
        $response_body = wp_remote_retrieve_body($response);
        $data = json_decode($response_body, true);

        if (isset($data['d']) && $data['d'] === null) {
            error_log('IndexNow submission successful for ' . $post_url);
        } else {
            error_log('IndexNow submission returned an unexpected response.');
        }
    }
}



new AI_Content_Pipelines_Oneup_AuthorPersonas();
?>