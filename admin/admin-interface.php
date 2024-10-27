<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Handle form submissions
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_persona']) && isset($_POST['add_persona_nonce'])) {
        if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['add_persona_nonce'])), 'add_persona_action')) {
            wp_die('Nonce verification failed.'); // Handle the error appropriately
        }
        $personas = get_option('ai_content_pipelines_oneup_author_personas', []);
        $new_persona = [
            'persona' => isset($_POST['persona_name']) ? sanitize_text_field(wp_unslash($_POST['persona_name'])) : '',
            'writing_style' => isset($_POST['persona_style']) ? sanitize_textarea_field(wp_unslash($_POST['persona_style'])) : '',
            'tone' => isset($_POST['persona_tone']) ? sanitize_text_field(wp_unslash($_POST['persona_tone'])) : '',
            'emotions' => isset($_POST['persona_emotions']) ? array_map('sanitize_text_field', wp_unslash($_POST['persona_emotions'])) : [],
            'E-E-A-T' => [
                'Experience' => isset($_POST['persona_experience']) ? sanitize_textarea_field(wp_unslash($_POST['persona_experience'])) : '',
                'Expertise' => isset($_POST['persona_expertise']) ? sanitize_textarea_field(wp_unslash($_POST['persona_expertise'])) : '',
                'Authoritativeness' => isset($_POST['persona_authoritativeness']) ? sanitize_textarea_field(wp_unslash($_POST['persona_authoritativeness'])) : '',
                'Trustworthiness' => isset($_POST['persona_trustworthiness']) ? sanitize_textarea_field(wp_unslash($_POST['persona_trustworthiness'])) : '',
            ]
        ];
        $personas[] = $new_persona;
        update_option('ai_content_pipelines_oneup_author_personas', $personas);
    } elseif (isset($_POST['delete_persona']) && isset($_POST['delete_persona_nonce'])) {
        // Sanitize and unslash nonce before verification
        if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['delete_persona_nonce'])), 'delete_persona_action')) {
            wp_die('Nonce verification failed.'); // Handle the error appropriately
        }
        // Retrieve personas from the option
        $personas = get_option('ai_content_pipelines_oneup_author_personas', []);
        // Ensure persona index is sanitized and valid
        $index = isset($_POST['persona_index']) ? intval(wp_unslash($_POST['persona_index'])) : -1;
        if ($index >= 0 && isset($personas[$index])) {
            // Remove the persona at the specified index
            unset($personas[$index]);
            // Reindex the array and update the option
            update_option('ai_content_pipelines_oneup_author_personas', array_values($personas));
        }
    } elseif (isset($_POST['create_user']) && isset($_POST['create_user_nonce'])) {
        // Sanitize and unslash the nonce before verifying
        if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['create_user_nonce'])), 'create_user_action')) {
            wp_die('Nonce verification failed.'); // Handle the error appropriately
        }

        // Sanitize input data
        $username = isset($_POST['username']) ? sanitize_user(wp_unslash($_POST['username'])) : '';
        $email = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';
        $password = isset($_POST['password']) ? wp_unslash($_POST['password']) : '';
        $role = isset($_POST['role']) ? sanitize_text_field(wp_unslash($_POST['role'])) : '';
        $persona = isset($_POST['persona']) ? sanitize_text_field(wp_unslash($_POST['persona'])) : '';
        $industry = isset($_POST['industry']) ? sanitize_text_field(wp_unslash($_POST['industry'])) : '';
        $language = isset($_POST['language']) ? sanitize_text_field(wp_unslash($_POST['language'])) : '';
        $location = isset($_POST['location']) ? sanitize_text_field(wp_unslash($_POST['location'])) : '';
        $url = isset($_POST['url']) ? sanitize_text_field(wp_unslash($_POST['url'])) : '';
        $business_detail = isset($_POST['business_details']) ? $_POST['business_details'] : '';
        $domain_authority = isset($_POST['domain_authority']) ? sanitize_text_field(wp_unslash($_POST['domain_authority'])) : '';
        $content_strategy = isset($_POST['content_strategy']) ? sanitize_text_field(wp_unslash($_POST['content_strategy'])) : '';
        $access_token = sanitize_text_field($_POST['google_access_token']);
        $google_sites = isset($_POST['google_sites']) ? array_map('sanitize_text_field', $_POST['google_sites']) : [];


        // Check for required fields
        if (empty($username) || empty($email) || empty($password) || empty($role)) {
            wp_die('All fields are required.'); // Handle the error appropriately
        }

        // Create new user
        $user_id = wp_create_user($username, $password, $email);

        if (!is_wp_error($user_id)) {
            // Assign the role to the new user
            $user = new WP_User($user_id);
            $user->set_role($role);

            // Add user meta
            update_user_meta($user_id, 'first_name', $username);
            update_user_meta($user_id, '_assigned_persona', $persona);
            update_user_meta($user_id, '_assigned_industry', $industry);
            update_user_meta($user_id, '_assigned_language', $language);
            update_user_meta($user_id, '_assigned_location', $location);
            update_user_meta($user_id, '_assigned_url', $url);
            update_user_meta($user_id, '_assigned_business_detail', $business_detail);
            update_user_meta($user_id, '_assigned_domain_authority', $domain_authority);
            // update_user_meta($user_id, 'google_access_token', $access_token);
            // update_user_meta($user_id, 'google_sites', $google_sites);
            update_user_meta($user_id, '_assigned_content_strategy', $content_strategy);
            // Success message for creating the user
            echo '<div class="notice notice-success is-dismissible">
                <p>User created successfully.</p>
            </div>';
        } else {
            // Handle user creation error
            $error_message = $user_id->get_error_message();
            echo '<div class="notice notice-error is-dismissible">
                <p>Error creating user: ' . esc_html($error_message) . '</p>
            </div>';
        }
    } elseif (isset($_POST['update_user']) && isset($_POST['update_user_nonce'])) {
        // Sanitize and unslash nonce before verifying
        if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['update_user_nonce'])), 'update_user_action')) {
            wp_die('Nonce verification failed.'); // Handle the error appropriately
        }

        // Sanitize input data
        $user_id = isset($_POST['user_id']) ? intval(wp_unslash($_POST['user_id'])) : 0;
        $username = isset($_POST['username']) ? sanitize_user(wp_unslash($_POST['username'])) : '';
        $email = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';
        $password = isset($_POST['password']) ? wp_unslash($_POST['password']) : ''; // Do not sanitize passwords
        $role = isset($_POST['role']) ? sanitize_text_field(wp_unslash($_POST['role'])) : '';
        $persona = isset($_POST['persona']) ? sanitize_text_field(wp_unslash($_POST['persona'])) : '';
        $industry = isset($_POST['industry']) ? sanitize_text_field(wp_unslash($_POST['industry'])) : '';
        $language = isset($_POST['language']) ? sanitize_text_field(wp_unslash($_POST['language'])) : '';
        $location = isset($_POST['location']) ? sanitize_text_field(wp_unslash($_POST['location'])) : '';
        $url = isset($_POST['url']) ? sanitize_text_field(wp_unslash($_POST['url'])) : '';
        $business_detail = isset($_POST['business_details']) ? $_POST['business_details'] : '';
        $domain_authority = isset($_POST['domain_authority']) ? sanitize_text_field(wp_unslash($_POST['domain_authority'])) : '';
        $content_strategy = isset($_POST['content_strategy']) ? sanitize_text_field(wp_unslash($_POST['content_strategy'])) : '';

        // Check if user ID is valid
        if ($user_id > 0) {
            // Prepare user data for update
            $user_data = [
                'ID' => $user_id,
                'user_login' => $username,
                'user_email' => $email,
            ];
            // Update password only if provided
            if (!empty($password)) {
                $user_data['user_pass'] = $password;
            }
            // Update user information
            $user_id = wp_update_user($user_data);

            if (!is_wp_error($user_id)) {
                // Set user role and update persona
                $user = new WP_User($user_id);
                $user->set_role($role);
                update_user_meta($user_id, '_assigned_persona', $persona);
                update_user_meta($user_id, '_assigned_industry', $industry);
                update_user_meta($user_id, '_assigned_location', $location);
                update_user_meta($user_id, '_assigned_language', $language);
                update_user_meta($user_id, '_assigned_url', $url);
                update_user_meta($user_id, '_assigned_business_detail', $business_detail);
                update_user_meta($user_id, '_assigned_domain_authority', $domain_authority);
                update_user_meta($user_id, '_assigned_content_strategy', $content_strategy);
                // Success message for updating the user
                echo '<div class="notice notice-success is-dismissible">
                    <p>User updated successfully.</p>
                </div>';
            } else {
                // Handle error during user update
                $error_message = $user_id->get_error_message();
                echo '<div class="notice notice-error is-dismissible">
                    <p>Error updating user: ' . esc_html($error_message) . '</p>
                </div>';
            }
        }
    }    // Handle the form submission for adding, updating, or deleting content types
    elseif (isset($_POST['add_content_type']) && isset($_POST['add_content_type_nonce']) && ($_GET['page'] === 'author-personas' || $_GET['page'] === 'content-types')) {
        // Verify and sanitize nonce
        if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['add_content_type_nonce'])), 'add_content_type_action')) {
            wp_die('Nonce verification failed.');
        }
        // Retrieve existing content types (key-value pairs)
        $content_types = get_option('ai_content_pipelines_oneup_author_personas_content_types', []);
        // Sanitize and unslash the content type and template inputs
        $new_content_type = isset($_POST['content_type']) ? sanitize_text_field(wp_unslash($_POST['content_type'])) : '';
        $new_content_template = isset($_POST['content_template']) ? sanitize_textarea_field(wp_unslash($_POST['content_template'])) : '';

        // Check if the content type already exists
        if (array_key_exists($new_content_type, $content_types)) {
            // Update the existing content type's template
            $content_types[$new_content_type] = $new_content_template;
            echo '<div class="updated"><p>Content type updated successfully.</p></div>';
        } else {
            // Add the new content type and template to the array
            $content_types[$new_content_type] = $new_content_template;
            echo '<div class="updated"><p>New content type added successfully.</p></div>';
        }
        // Update the option with the new or updated content types
        update_option('ai_content_pipelines_oneup_author_personas_content_types', $content_types);
    } elseif (isset($_POST['delete_content_type']) && isset($_POST['delete_content_type_nonce'])) {
        // Sanitize and unslash the nonce before verifying
        if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['delete_content_type_nonce'])), 'delete_content_type_action')) {
            wp_die('Nonce verification failed.');
        }

        // Retrieve existing content types
        $content_types = get_option('ai_content_pipelines_oneup_author_personas_content_types', []);

        // Sanitize and unslash the content type key to delete
        $content_type_to_delete = isset($_POST['content_type_key']) ? sanitize_text_field(wp_unslash($_POST['content_type_key'])) : '';

        if (isset($content_types[$content_type_to_delete])) {
            // Remove the content type from the array
            unset($content_types[$content_type_to_delete]);

            // Update the database with the modified array
            update_option('ai_content_pipelines_oneup_author_personas_content_types', $content_types);

            // Success message
            echo '<div class="updated"><p>Content type deleted successfully.</p></div>';
        } else {
            // Handle case where content type does not exist
            echo '<div class="error"><p>Content type not found.</p></div>';
        }
    }
    $redirect_page = isset($_POST['_wp_http_referer']) ? esc_url_raw($_POST['_wp_http_referer']) : admin_url('admin.php?page=author-personas');

    // Redirect back to the original page after 2 seconds
    echo '
        <script type="text/javascript">
            setTimeout(function() {
                window.location.href = "' . $redirect_page . '";
            }, 2000);
        </script>
    ';
    
}

// Prebuilt personas
$prebuilt_personas = [
    [
        'persona' => 'Narrative - Optimistic - Joy, Enthusiasm, Amusement',
        'writing_style' => 'Narrative Writing',
        'tone' => 'Optimistic',
        'emotions' => ['Joy', 'Enthusiasm', 'Amusement'],
        'E-E-A-T' => [
            'Experience' => '5 years writing narrative content in technology.',
            'Expertise' => 'Bachelor\'s degree in Journalism. Published author.',
            'Authoritativeness' => 'Regular contributor to major tech blogs. Featured in TechCrunch.',
            'Trustworthiness' => 'Verified profile with multiple positive testimonials.'
        ]
    ],
    [
        'persona' => 'Descriptive - Reflective - Awe, Aesthetic Appreciation, Calmness',
        'writing_style' => 'Descriptive Writing',
        'tone' => 'Reflective',
        'emotions' => ['Awe', 'Aesthetic Appreciation', 'Calmness'],
        'E-E-A-T' => [
            'Experience' => '10 years as a nature writer.',
            'Expertise' => 'Master\'s degree in Environmental Science. Award-winning photographer.',
            'Authoritativeness' => 'Published in National Geographic and other reputed journals.',
            'Trustworthiness' => 'Certified by environmental organizations. Positive reader reviews.'
        ]
    ],
    [
        'persona' => 'Expository - Informative - Interest, Satisfaction, Calmness',
        'writing_style' => 'Expository Writing',
        'tone' => 'Informative',
        'emotions' => ['Interest', 'Satisfaction', 'Calmness'],
        'E-E-A-T' => [
            'Experience' => '8 years of writing educational content.',
            'Expertise' => 'PhD in Education. Former university lecturer.',
            'Authoritativeness' => 'Cited by academic publications. Contributor to educational platforms.',
            'Trustworthiness' => 'Peer-reviewed publications. Endorsed by educational institutions.'
        ]
    ],
    [
        'persona' => 'Persuasive - Enthusiastic - Admiration, Excitement, Amusement',
        'writing_style' => 'Persuasive Writing',
        'tone' => 'Enthusiastic',
        'emotions' => ['Admiration', 'Excitement', 'Amusement'],
        'E-E-A-T' => [
            'Experience' => '6 years of persuasive content writing in marketing.',
            'Expertise' => 'MBA in Marketing. Certified digital marketing specialist.',
            'Authoritativeness' => 'Featured in Forbes. Guest speaker at marketing conferences.',
            'Trustworthiness' => 'Multiple client testimonials. High engagement rates.'
        ]
    ],
    [
        'persona' => 'Creative - Lighthearted - Joy, Amusement, Surprise',
        'writing_style' => 'Creative Writing',
        'tone' => 'Lighthearted',
        'emotions' => ['Joy', 'Amusement', 'Surprise'],
        'E-E-A-T' => [
            'Experience' => '7 years of creative writing for entertainment blogs.',
            'Expertise' => 'Bachelor\'s degree in Creative Writing. Screenwriter.',
            'Authoritativeness' => 'Published in major entertainment magazines. Writer for popular TV shows.',
            'Trustworthiness' => 'Verified social media accounts. Positive audience feedback.'
        ]
    ],
    [
        'persona' => 'Objective - Detached - Objective, Calmness, Informative',
        'writing_style' => 'Objective Writing',
        'tone' => 'Detached',
        'emotions' => ['Objective', 'Calmness', 'Informative'],
        'E-E-A-T' => [
            'Experience' => '12 years of scientific writing.',
            'Expertise' => 'PhD in Molecular Biology. Research scientist.',
            'Authoritativeness' => 'Published in peer-reviewed journals. Conference speaker.',
            'Trustworthiness' => 'Member of scientific boards. Positive peer reviews.'
        ]
    ],
    [
        'persona' => 'Subjective - Nostalgic - Nostalgia, Admiration, Joy',
        'writing_style' => 'Subjective Writing',
        'tone' => 'Nostalgic',
        'emotions' => ['Nostalgia', 'Admiration', 'Joy'],
        'E-E-A-T' => [
            'Experience' => '15 years writing personal essays and memoirs.',
            'Expertise' => 'Published author of multiple memoirs. MFA in Creative Writing.',
            'Authoritativeness' => 'Featured in literary journals. Guest lecturer.',
            'Trustworthiness' => 'High reader ratings. Multiple positive reviews.'
        ]
    ],
    [
        'persona' => 'Review - Analytical - Objective, Interest, Satisfaction',
        'writing_style' => 'Review Writing',
        'tone' => 'Analytical',
        'emotions' => ['Objective', 'Interest', 'Satisfaction'],
        'E-E-A-T' => [
            'Experience' => '5 years as a product reviewer.',
            'Expertise' => 'Certified product specialist. Bachelor\'s degree in Consumer Science.',
            'Authoritativeness' => 'Contributor to major review sites. Featured in consumer reports.',
            'Trustworthiness' => 'Verified reviewer profile. Positive feedback from readers.'
        ]
    ],
    [
        'persona' => 'Poetic - Romantic - Romance, Adoration, Joy',
        'writing_style' => 'Poetic Writing',
        'tone' => 'Romantic',
        'emotions' => ['Romance', 'Adoration', 'Joy'],
        'E-E-A-T' => [
            'Experience' => '10 years writing poetry.',
            'Expertise' => 'Master\'s degree in Literature. Published poet.',
            'Authoritativeness' => 'Published in poetry anthologies. Poetry contest winner.',
            'Trustworthiness' => 'Verified by literary organizations. Positive reader reviews.'
        ]
    ],
    [
        'persona' => 'Technical - Objective - Informative, Calmness, Objective',
        'writing_style' => 'Technical Writing',
        'tone' => 'Objective',
        'emotions' => ['Informative', 'Calmness', 'Objective'],
        'E-E-A-T' => [
            'Experience' => '8 years of technical writing in IT.',
            'Expertise' => 'Certified IT professional. Bachelor\'s degree in Computer Science.',
            'Authoritativeness' => 'Published in tech journals. Speaker at tech conferences.',
            'Trustworthiness' => 'Verified by tech organizations. Positive client testimonials.'
        ]
    ],
    [
        'persona' => 'Narrative - Lighthearted - Amusement, Joy, Enthusiasm',
        'writing_style' => 'Narrative Writing',
        'tone' => 'Lighthearted',
        'emotions' => ['Amusement', 'Joy', 'Enthusiasm'],
        'E-E-A-T' => [
            'Experience' => '6 years writing for lifestyle blogs.',
            'Expertise' => 'Bachelor\'s degree in Communications. Freelance writer.',
            'Authoritativeness' => 'Contributor to popular lifestyle websites. Featured in online magazines.',
            'Trustworthiness' => 'Verified by freelance platforms. Positive reader engagement.'
        ]
    ],
    [
        'persona' => 'Descriptive - Contemplative - Awe, Reflective, Calmness',
        'writing_style' => 'Descriptive Writing',
        'tone' => 'Contemplative',
        'emotions' => ['Awe', 'Reflective', 'Calmness'],
        'E-E-A-T' => [
            'Experience' => '8 years as a travel writer.',
            'Expertise' => 'Bachelor\'s degree in Geography. Published travel articles.',
            'Authoritativeness' => 'Featured in travel magazines. Speaker at travel expos.',
            'Trustworthiness' => 'Certified travel writer. Positive reader testimonials.'
        ]
    ],
    [
        'persona' => 'Expository - Objective - Informative, Detached, Calmness',
        'writing_style' => 'Expository Writing',
        'tone' => 'Objective',
        'emotions' => ['Informative', 'Detached', 'Calmness'],
        'E-E-A-T' => [
            'Experience' => '10 years writing educational content.',
            'Expertise' => 'PhD in Education. Former school principal.',
            'Authoritativeness' => 'Cited by academic publications. Contributor to educational journals.',
            'Trustworthiness' => 'Peer-reviewed articles. Positive educator feedback.'
        ]
    ],
    [
        'persona' => 'Persuasive - Jubilant - Admiration, Enthusiasm, Excitement',
        'writing_style' => 'Persuasive Writing',
        'tone' => 'Jubilant',
        'emotions' => ['Admiration', 'Enthusiasm', 'Excitement'],
        'E-E-A-T' => [
            'Experience' => '5 years in advertising copywriting.',
            'Expertise' => 'Certified copywriter. Bachelor\'s degree in Marketing.',
            'Authoritativeness' => 'Featured in major advertising campaigns. Speaker at marketing workshops.',
            'Trustworthiness' => 'Client testimonials. High conversion rates.'
        ]
    ],
    [
        'persona' => 'Creative - Amused - Amusement, Joy, Surprise',
        'writing_style' => 'Creative Writing',
        'tone' => 'Amused',
        'emotions' => ['Amusement', 'Joy', 'Surprise'],
        'E-E-A-T' => [
            'Experience' => '7 years writing for comedy shows.',
            'Expertise' => 'Bachelor\'s degree in Creative Writing. Comedian.',
            'Authoritativeness' => 'Writer for popular comedy series. Featured in humor magazines.',
            'Trustworthiness' => 'Positive audience feedback. Verified social media accounts.'
        ]
    ],
    [
        'persona' => 'Objective - Reflective - Informative, Contemplative, Objective',
        'writing_style' => 'Objective Writing',
        'tone' => 'Reflective',
        'emotions' => ['Informative', 'Contemplative', 'Objective'],
        'E-E-A-T' => [
            'Experience' => '12 years in academic writing.',
            'Expertise' => 'PhD in Philosophy. University professor.',
            'Authoritativeness' => 'Published in academic journals. Speaker at academic conferences.',
            'Trustworthiness' => 'Peer-reviewed publications. Positive academic reviews.'
        ]
    ],
    [
        'persona' => 'Subjective - Sentimental - Nostalgia, Adoration, Joy',
        'writing_style' => 'Subjective Writing',
        'tone' => 'Sentimental',
        'emotions' => ['Nostalgia', 'Adoration', 'Joy'],
        'E-E-A-T' => [
            'Experience' => '15 years writing memoirs.',
            'Expertise' => 'Published author of multiple memoirs. MFA in Creative Writing.',
            'Authoritativeness' => 'Featured in literary journals. Guest lecturer.',
            'Trustworthiness' => 'High reader ratings. Multiple positive reviews.'
        ]
    ],
    [
        'persona' => 'Review - Critical - Disgust, Objective, Calmness',
        'writing_style' => 'Review Writing',
        'tone' => 'Critical',
        'emotions' => ['Disgust', 'Objective', 'Calmness'],
        'E-E-A-T' => [
            'Experience' => '5 years as a product reviewer.',
            'Expertise' => 'Certified product specialist. Bachelor\'s degree in Consumer Science.',
            'Authoritativeness' => 'Contributor to major review sites. Featured in consumer reports.',
            'Trustworthiness' => 'Verified reviewer profile. Positive feedback from readers.'
        ]
    ],
    [
        'persona' => 'Poetic - Sad - Sadness, Admiration, Nostalgia',
        'writing_style' => 'Poetic Writing',
        'tone' => 'Sad',
        'emotions' => ['Sadness', 'Admiration', 'Nostalgia'],
        'E-E-A-T' => [
            'Experience' => '10 years writing poetry.',
            'Expertise' => 'Master\'s degree in Literature. Published poet.',
            'Authoritativeness' => 'Published in poetry anthologies. Poetry contest winner.',
            'Trustworthiness' => 'Verified by literary organizations. Positive reader reviews.'
        ]
    ],
    [
        'persona' => 'Technical - Informative - Interest, Satisfaction, Objective',
        'writing_style' => 'Technical Writing',
        'tone' => 'Informative',
        'emotions' => ['Interest', 'Satisfaction', 'Objective'],
        'E-E-A-T' => [
            'Experience' => '8 years of technical writing in IT.',
            'Expertise' => 'Certified IT professional. Bachelor\'s degree in Computer Science.',
            'Authoritativeness' => 'Published in tech journals. Speaker at tech conferences.',
            'Trustworthiness' => 'Verified by tech organizations. Positive client testimonials.'
        ]
    ],
    [
        'persona' => 'Narrative - Pessimistic - Anger, Anxiety, Foreboding',
        'writing_style' => 'Narrative Writing',
        'tone' => 'Pessimistic',
        'emotions' => ['Anger', 'Anxiety', 'Foreboding'],
        'E-E-A-T' => [
            'Experience' => '6 years writing for political blogs.',
            'Expertise' => 'Bachelor\'s degree in Political Science. Freelance writer.',
            'Authoritativeness' => 'Contributor to major political websites. Featured in online news outlets.',
            'Trustworthiness' => 'Verified by freelance platforms. Positive reader engagement.'
        ]
    ],
    [
        'persona' => 'Descriptive - Aesthetic - Aesthetic Appreciation, Awe, Calmness',
        'writing_style' => 'Descriptive Writing',
        'tone' => 'Aesthetic',
        'emotions' => ['Aesthetic Appreciation', 'Awe', 'Calmness'],
        'E-E-A-T' => [
            'Experience' => '8 years as a nature writer.',
            'Expertise' => 'Master\'s degree in Environmental Science. Award-winning photographer.',
            'Authoritativeness' => 'Published in National Geographic and other reputed journals.',
            'Trustworthiness' => 'Certified by environmental organizations. Positive reader reviews.'
        ]
    ],
    [
        'persona' => 'Expository - Detached - Objective, Informative, Calmness',
        'writing_style' => 'Expository Writing',
        'tone' => 'Detached',
        'emotions' => ['Objective', 'Informative', 'Calmness'],
        'E-E-A-T' => [
            'Experience' => '10 years writing educational content.',
            'Expertise' => 'PhD in Education. Former school principal.',
            'Authoritativeness' => 'Cited by academic publications. Contributor to educational journals.',
            'Trustworthiness' => 'Peer-reviewed articles. Positive educator feedback.'
        ]
    ],
    [
        'persona' => 'Persuasive - Enthusiastic - Admiration, Excitement, Joy',
        'writing_style' => 'Persuasive Writing',
        'tone' => 'Enthusiastic',
        'emotions' => ['Admiration', 'Excitement', 'Joy'],
        'E-E-A-T' => [
            'Experience' => '6 years in advertising copywriting.',
            'Expertise' => 'Certified copywriter. Bachelor\'s degree in Marketing.',
            'Authoritativeness' => 'Featured in major advertising campaigns. Speaker at marketing workshops.',
            'Trustworthiness' => 'Client testimonials. High conversion rates.'
        ]
    ],
    [
        'persona' => 'Creative - Jubilant - Joy, Amusement, Enthusiasm',
        'writing_style' => 'Creative Writing',
        'tone' => 'Jubilant',
        'emotions' => ['Joy', 'Amusement', 'Enthusiasm'],
        'E-E-A-T' => [
            'Experience' => '7 years writing for comedy shows.',
            'Expertise' => 'Bachelor\'s degree in Creative Writing. Comedian.',
            'Authoritativeness' => 'Writer for popular comedy series. Featured in humor magazines.',
            'Trustworthiness' => 'Positive audience feedback. Verified social media accounts.'
        ]
    ],
    [
        'persona' => 'Objective - Analytical - Informative, Objective, Calmness',
        'writing_style' => 'Objective Writing',
        'tone' => 'Analytical',
        'emotions' => ['Informative', 'Objective', 'Calmness'],
        'E-E-A-T' => [
            'Experience' => '12 years in academic writing.',
            'Expertise' => 'PhD in Philosophy. University professor.',
            'Authoritativeness' => 'Published in academic journals. Speaker at academic conferences.',
            'Trustworthiness' => 'Peer-reviewed publications. Positive academic reviews.'
        ]
    ],
    [
        'persona' => 'Subjective - Reflective - Nostalgia, Joy, Admiration',
        'writing_style' => 'Subjective Writing',
        'tone' => 'Reflective',
        'emotions' => ['Nostalgia', 'Joy', 'Admiration'],
        'E-E-A-T' => [
            'Experience' => '15 years writing memoirs.',
            'Expertise' => 'Published author of multiple memoirs. MFA in Creative Writing.',
            'Authoritativeness' => 'Featured in literary journals. Guest lecturer.',
            'Trustworthiness' => 'High reader ratings. Multiple positive reviews.'
        ]
    ],
    [
        'persona' => 'Review - Enthusiastic - Joy, Satisfaction, Interest',
        'writing_style' => 'Review Writing',
        'tone' => 'Enthusiastic',
        'emotions' => ['Joy', 'Satisfaction', 'Interest'],
        'E-E-A-T' => [
            'Experience' => '5 years as a product reviewer.',
            'Expertise' => 'Certified product specialist. Bachelor\'s degree in Consumer Science.',
            'Authoritativeness' => 'Contributor to major review sites. Featured in consumer reports.',
            'Trustworthiness' => 'Verified reviewer profile. Positive feedback from readers.'
        ]
    ],
    [
        'persona' => 'Poetic - Entranced - Entrancement, Awe, Joy',
        'writing_style' => 'Poetic Writing',
        'tone' => 'Entranced',
        'emotions' => ['Entrancement', 'Awe', 'Joy'],
        'E-E-A-T' => [
            'Experience' => '10 years writing poetry.',
            'Expertise' => 'Master\'s degree in Literature. Published poet.',
            'Authoritativeness' => 'Published in poetry anthologies. Poetry contest winner.',
            'Trustworthiness' => 'Verified by literary organizations. Positive reader reviews.'
        ]
    ],
    [
        'persona' => 'Technical - Precise - Objective, Informative, Satisfaction',
        'writing_style' => 'Technical Writing',
        'tone' => 'Precise',
        'emotions' => ['Objective', 'Informative', 'Satisfaction'],
        'E-E-A-T' => [
            'Experience' => '8 years of technical writing in IT.',
            'Expertise' => 'Certified IT professional. Bachelor\'s degree in Computer Science.',
            'Authoritativeness' => 'Published in tech journals. Speaker at tech conferences.',
            'Trustworthiness' => 'Verified by tech organizations. Positive client testimonials.'
        ]
    ],
    [
        'persona' => 'Narrative - Foreboding - Fear, Anxiety, Confusion',
        'writing_style' => 'Narrative Writing',
        'tone' => 'Foreboding',
        'emotions' => ['Fear', 'Anxiety', 'Confusion'],
        'E-E-A-T' => [
            'Experience' => '6 years writing for political blogs.',
            'Expertise' => 'Bachelor\'s degree in Political Science. Freelance writer.',
            'Authoritativeness' => 'Contributor to major political websites. Featured in online news outlets.',
            'Trustworthiness' => 'Verified by freelance platforms. Positive reader engagement.'
        ]
    ],
    [
        'persona' => 'Descriptive - Calm - Calmness, Reflective, Satisfaction',
        'writing_style' => 'Descriptive Writing',
        'tone' => 'Calm',
        'emotions' => ['Calmness', 'Reflective', 'Satisfaction'],
        'E-E-A-T' => [
            'Experience' => '8 years as a nature writer.',
            'Expertise' => 'Master\'s degree in Environmental Science. Award-winning photographer.',
            'Authoritativeness' => 'Published in National Geographic and other reputed journals.',
            'Trustworthiness' => 'Certified by environmental organizations. Positive reader reviews.'
        ]
    ],
    [
        'persona' => 'Expository - Detached - Objective, Informative, Calmness',
        'writing_style' => 'Expository Writing',
        'tone' => 'Detached',
        'emotions' => ['Objective', 'Informative', 'Calmness'],
        'E-E-A-T' => [
            'Experience' => '10 years writing educational content.',
            'Expertise' => 'PhD in Education. Former school principal.',
            'Authoritativeness' => 'Cited by academic publications. Contributor to educational journals.',
            'Trustworthiness' => 'Peer-reviewed articles. Positive educator feedback.'
        ]
    ],
    [
        'persona' => 'Persuasive - Excited - Enthusiasm, Joy, Admiration',
        'writing_style' => 'Persuasive Writing',
        'tone' => 'Excited',
        'emotions' => ['Enthusiasm', 'Joy', 'Admiration'],
        'E-E-A-T' => [
            'Experience' => '6 years in advertising copywriting.',
            'Expertise' => 'Certified copywriter. Bachelor\'s degree in Marketing.',
            'Authoritativeness' => 'Featured in major advertising campaigns. Speaker at marketing workshops.',
            'Trustworthiness' => 'Client testimonials. High conversion rates.'
        ]
    ],
    [
        'persona' => 'Creative - Lighthearted - Amusement, Joy, Surprise',
        'writing_style' => 'Creative Writing',
        'tone' => 'Lighthearted',
        'emotions' => ['Amusement', 'Joy', 'Surprise'],
        'E-E-A-T' => [
            'Experience' => '7 years writing for comedy shows.',
            'Expertise' => 'Bachelor\'s degree in Creative Writing. Comedian.',
            'Authoritativeness' => 'Writer for popular comedy series. Featured in humor magazines.',
            'Trustworthiness' => 'Positive audience feedback. Verified social media accounts.'
        ]
    ],
    [
        'persona' => 'Objective - Informative - Objective, Detached, Calmness',
        'writing_style' => 'Objective Writing',
        'tone' => 'Informative',
        'emotions' => ['Objective', 'Detached', 'Calmness'],
        'E-E-A-T' => [
            'Experience' => '12 years in academic writing.',
            'Expertise' => 'PhD in Philosophy. University professor.',
            'Authoritativeness' => 'Published in academic journals. Speaker at academic conferences.',
            'Trustworthiness' => 'Peer-reviewed publications. Positive academic reviews.'
        ]
    ],
    [
        'persona' => 'Subjective - Romantic - Romance, Joy, Adoration',
        'writing_style' => 'Subjective Writing',
        'tone' => 'Romantic',
        'emotions' => ['Romance', 'Joy', 'Adoration'],
        'E-E-A-T' => [
            'Experience' => '15 years writing personal essays.',
            'Expertise' => 'Published author of multiple romance novels. MFA in Creative Writing.',
            'Authoritativeness' => 'Featured in literary journals. Guest lecturer.',
            'Trustworthiness' => 'High reader ratings. Multiple positive reviews.'
        ]
    ],
    [
        'persona' => 'Review - Analytical - Objective, Informative, Satisfaction',
        'writing_style' => 'Review Writing',
        'tone' => 'Analytical',
        'emotions' => ['Objective', 'Informative', 'Satisfaction'],
        'E-E-A-T' => [
            'Experience' => '5 years as a product reviewer.',
            'Expertise' => 'Certified product specialist. Bachelor\'s degree in Consumer Science.',
            'Authoritativeness' => 'Contributor to major review sites. Featured in consumer reports.',
            'Trustworthiness' => 'Verified reviewer profile. Positive feedback from readers.'
        ]
    ],
    [
        'persona' => 'Poetic - Nostalgic - Nostalgia, Joy, Admiration',
        'writing_style' => 'Poetic Writing',
        'tone' => 'Nostalgic',
        'emotions' => ['Nostalgia', 'Joy', 'Admiration'],
        'E-E-A-T' => [
            'Experience' => '10 years writing poetry.',
            'Expertise' => 'Master\'s degree in Literature. Published poet.',
            'Authoritativeness' => 'Published in poetry anthologies. Poetry contest winner.',
            'Trustworthiness' => 'Verified by literary organizations. Positive reader reviews.'
        ]
    ],
    [
        'persona' => 'Technical - Detailed - Objective, Informative, Satisfaction',
        'writing_style' => 'Technical Writing',
        'tone' => 'Detailed',
        'emotions' => ['Objective', 'Informative', 'Satisfaction'],
        'E-E-A-T' => [
            'Experience' => '8 years of technical writing in IT.',
            'Expertise' => 'Certified IT professional. Bachelor\'s degree in Computer Science.',
            'Authoritativeness' => 'Published in tech journals. Speaker at tech conferences.',
            'Trustworthiness' => 'Verified by tech organizations. Positive client testimonials.'
        ]
    ],
    [
        'persona' => 'Narrative - Reflective - Calmness, Satisfaction, Contemplative',
        'writing_style' => 'Narrative Writing',
        'tone' => 'Reflective',
        'emotions' => ['Calmness', 'Satisfaction', 'Contemplative'],
        'E-E-A-T' => [
            'Experience' => '6 years writing for lifestyle blogs.',
            'Expertise' => 'Bachelor\'s degree in Communications. Freelance writer.',
            'Authoritativeness' => 'Contributor to popular lifestyle websites. Featured in online magazines.',
            'Trustworthiness' => 'Verified by freelance platforms. Positive reader engagement.'
        ]
    ],
    [
        'persona' => 'Descriptive - Awe-Inspired - Awe, Aesthetic Appreciation, Calmness',
        'writing_style' => 'Descriptive Writing',
        'tone' => 'Awe-Inspired',
        'emotions' => ['Awe', 'Aesthetic Appreciation', 'Calmness'],
        'E-E-A-T' => [
            'Experience' => '8 years as a nature writer.',
            'Expertise' => 'Master\'s degree in Environmental Science. Award-winning photographer.',
            'Authoritativeness' => 'Published in National Geographic and other reputed journals.',
            'Trustworthiness' => 'Certified by environmental organizations. Positive reader reviews.'
        ]
    ],
    [
        'persona' => 'Expository - Objective - Informative, Objective, Calmness',
        'writing_style' => 'Expository Writing',
        'tone' => 'Objective',
        'emotions' => ['Informative', 'Objective', 'Calmness'],
        'E-E-A-T' => [
            'Experience' => '10 years writing educational content.',
            'Expertise' => 'PhD in Education. Former school principal.',
            'Authoritativeness' => 'Cited by academic publications. Contributor to educational journals.',
            'Trustworthiness' => 'Peer-reviewed articles. Positive educator feedback.'
        ]
    ],
    [
        'persona' => 'Persuasive - Joyful - Joy, Admiration, Enthusiasm',
        'writing_style' => 'Persuasive Writing',
        'tone' => 'Joyful',
        'emotions' => ['Joy', 'Admiration', 'Enthusiasm'],
        'E-E-A-T' => [
            'Experience' => '5 years in advertising copywriting.',
            'Expertise' => 'Certified copywriter. Bachelor\'s degree in Marketing.',
            'Authoritativeness' => 'Featured in major advertising campaigns. Speaker at marketing workshops.',
            'Trustworthiness' => 'Client testimonials. High conversion rates.'
        ]
    ],
    [
        'persona' => 'Creative - Optimistic - Joy, Amusement, Enthusiasm',
        'writing_style' => 'Creative Writing',
        'tone' => 'Optimistic',
        'emotions' => ['Joy', 'Amusement', 'Enthusiasm'],
        'E-E-A-T' => [
            'Experience' => '7 years writing for comedy shows.',
            'Expertise' => 'Bachelor\'s degree in Creative Writing. Comedian.',
            'Authoritativeness' => 'Writer for popular comedy series. Featured in humor magazines.',
            'Trustworthiness' => 'Positive audience feedback. Verified social media accounts.'
        ]
    ],
    [
        'persona' => 'Objective - Neutral - Objective, Informative, Detached',
        'writing_style' => 'Objective Writing',
        'tone' => 'Neutral',
        'emotions' => ['Objective', 'Informative', 'Detached'],
        'E-E-A-T' => [
            'Experience' => '12 years in academic writing.',
            'Expertise' => 'PhD in Philosophy. University professor.',
            'Authoritativeness' => 'Published in academic journals. Speaker at academic conferences.',
            'Trustworthiness' => 'Peer-reviewed publications. Positive academic reviews.'
        ]
    ],
    [
        'persona' => 'Subjective - Sentimental - Nostalgia, Joy, Admiration',
        'writing_style' => 'Subjective Writing',
        'tone' => 'Sentimental',
        'emotions' => ['Nostalgia', 'Joy', 'Admiration'],
        'E-E-A-T' => [
            'Experience' => '15 years writing personal essays.',
            'Expertise' => 'Published author of multiple memoirs. MFA in Creative Writing.',
            'Authoritativeness' => 'Featured in literary journals. Guest lecturer.',
            'Trustworthiness' => 'High reader ratings. Multiple positive reviews.'
        ]
    ],
    [
        'persona' => 'Review - Critical - Disgust, Objective, Informative',
        'writing_style' => 'Review Writing',
        'tone' => 'Critical',
        'emotions' => ['Disgust', 'Objective', 'Informative'],
        'E-E-A-T' => [
            'Experience' => '5 years as a product reviewer.',
            'Expertise' => 'Certified product specialist. Bachelor\'s degree in Consumer Science.',
            'Authoritativeness' => 'Contributor to major review sites. Featured in consumer reports.',
            'Trustworthiness' => 'Verified reviewer profile. Positive feedback from readers.'
        ]
    ],
    [
        'persona' => 'Poetic - Romantic - Romance, Joy, Adoration',
        'writing_style' => 'Poetic Writing',
        'tone' => 'Romantic',
        'emotions' => ['Romance', 'Joy', 'Adoration'],
        'E-E-A-T' => [
            'Experience' => '10 years writing poetry.',
            'Expertise' => 'Master\'s degree in Literature. Published poet.',
            'Authoritativeness' => 'Published in poetry anthologies. Poetry contest winner.',
            'Trustworthiness' => 'Verified by literary organizations. Positive reader reviews.'
        ]
    ],
    [
        'persona' => 'Technical - Informative - Informative, Objective, Satisfaction',
        'writing_style' => 'Technical Writing',
        'tone' => 'Informative',
        'emotions' => ['Informative', 'Objective', 'Satisfaction'],
        'E-E-A-T' => [
            'Experience' => '8 years of technical writing in IT.',
            'Expertise' => 'Certified IT professional. Bachelor\'s degree in Computer Science.',
            'Authoritativeness' => 'Published in tech journals. Speaker at tech conferences.',
            'Trustworthiness' => 'Verified by tech organizations. Positive client testimonials.'
        ]
    ]
];

$prebuilt_content_types = [
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

// Merge prebuilt personas with existing ones
if (empty(get_option('ai_content_pipelines_oneup_author_personas'))) {
    // Add the prebuilt personas to the 'ai_content_pipelines_oneup_author_personas' option
    update_option('ai_content_pipelines_oneup_author_personas', $prebuilt_personas);
}


if (empty(get_option('ai_content_pipelines_oneup_author_personas_content_types'))) {
    // Add the prebuilt personas to the 'ai_content_pipelines_oneup_author_personas' option
    update_option('ai_content_pipelines_oneup_author_personas_content_types', $prebuilt_content_types);
}


$personas = get_option('ai_content_pipelines_oneup_author_personas', []);
$roles = wp_roles()->roles;
$content_types = get_option('ai_content_pipelines_oneup_author_personas_content_types', []);
?>


<div class="wrap">
    <h1>AI Content Pipelines</h1>
    <h2 class="nav-tab-wrapper">
        <a href="#welcome-screen" class="nav-tab nav-tab-active">
           Welcome
        </a>
        <a href="#create-user" class="nav-tab">
            Business Setup
        </a>
        
        <a href="#generate-content" class="nav-tab" style="
    box-shadow: 2px 4px 6px 10px rgba(0.5, 0.3, 0.3, 0.3);
    scale: 1.1;
">
            Generate Content
        </a>
        <a href="#viewposts" class="nav-tab">
         View Content
        </a>
        <a href="#viewpostscalendar" class="nav-tab">
           Content Calendar
        </a>
        <a href="#notifications" class="nav-tab">Notifications</a>
        <a href="#settings" class="nav-tab">Settings</a>
        <!-- <a href="#content-types" class="nav-tab">
            Content Types
        </a>
        <a href="#manage-personas" class="nav-tab">
            Manage Personas
        </a> -->
    </h2>

    <div id="welcome-screen" class="tab-content">
    <h1>Welcome to AI Content Pipelines!</h1>
    <p>Thank you for choosing us to revolutionize your content creation process. Here's a quick guide to get you started:</p>

    <h2>Step 1: Set Up Your Business Profile</h2>
    <p>Attach your business details to your WordPress user by following these simple steps:</p>

    <h3>Enter Five Key Fields:</h3>
    <ul>
        <li><strong>Industry</strong></li>
        <li><strong>Location</strong></li>
        <li><strong>Persona</strong></li>
        <li><strong>Language</strong></li>
        <li><strong>Domain URL</strong></li>
    </ul>

    <h3>Click "Update User":</h3>
    <p>Save your entered information by clicking the <strong>Update User</strong> button.</p>

    <h3>Fetch Business Details:</h3>
    <p>Click the <strong>Fetch Business Details</strong> button next to <strong>Update User</strong> to auto-populate additional information:</p>
    <ul>
        <li><strong>Business Name</strong></li>
        <li><strong>Location</strong></li>
        <li><strong>Industry</strong></li>
        <li><strong>Last Updated Timestamp</strong></li>
    </ul>

    <h3>Demographic/Audience Data:</h3>
    <ul>
        <li>Age brackets for male and female audiences with percentage breakdowns</li>
        <li>Customer locations</li>
        <li>Biggest pain points</li>
    </ul>

    <h3>Keyword Data:</h3>
    <ul>
        <li>10 Local Keywords</li>
        <li>10 Parent Keywords</li>
        <li>10 Child Keywords</li>
    </ul>

    <h3>Review and Update:</h3>
    <p>Edit or add any information as needed. Click <strong>Update User</strong> again to save changes.</p>

    <h2>Step 2: Generate High-Quality Content</h2>
    <p>Navigate to the <strong>"Generate Content"</strong> tab:</p>
    <ul>
        <li>Access various workflow modes tailored to your needs.</li>
    </ul>

    <h3>Select Workflow Mode:</h3>
    <p>Choose the mode that best suits your project goals.</p>

    <h3>Fill in Required Fields:</h3>
    <p>Provide the necessary information in under 10 fields.</p>

    <h3>Start Generating Content:</h3>
    <p>Click to begin and let AI Content Pipelines do the rest!</p>

    <h2>Settings and Customization</h2>
    <ul>
        <li><strong>Content Type Templates:</strong> Modify templates to fit your specific needs.</li>
        <li><strong>Manage Personas:</strong> Add or edit personas to tailor content styles and voices.</li>
        <li><strong>Connect Social Media Accounts:</strong> Seamlessly integrate your social platforms for easy sharing.</li>
    </ul>

    <h2>Content Calendar</h2>
    <ul>
        <li><strong>View Scheduled Posts:</strong> Access the Content Calendar to see all your upcoming posts in one place.</li>
    </ul>

    <h2>Video Tutorial</h2>
    <p>For a comprehensive overview, <a href="https://www.youtube.com/embed/your-video-id" target="_blank">watch our quick tutorial video</a>. This video will walk you through each step, providing detailed instructions and tips to make the most of AI Content Pipelines.</p>

    <p>We're excited to have you on board! If you have any questions or need assistance, our support team is here to help. Happy content creating!</p>
</div>


    <div id="manage-personas" class="tab-content">
        <form method="post">
            <h2>Add New Persona</h2>
            <table class="form-table">
                <tr>
                    <th><label for="persona_name">Persona</label></th>
                    <td><input type="text" name="persona_name" id="persona_name" required></td>
                </tr>
                <tr>
                    <th><label for="persona_style">Writing Style</label></th>
                    <td><select name="persona_style" id="persona_style" required>
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
                                                        <form method="post" style="display:inline;">
                                                            <?php wp_nonce_field('delete_persona_action', 'delete_persona_nonce'); ?>
                                                            <input type="hidden" name="persona_index" value="<?php echo esc_attr($index); ?>">
                                                            <input type="submit" name="delete_persona" class="button button-secondary" value="Delete">
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


    <div id="create-user" class="tab-content" style="display: none;">
        <h2>Create/Update User</h2>

        <p style="margin-top: 10px; font-size: 16px; color: #333;">To complete the setup, find your user in the system and click "Update" to attach the business profile. This ensures all content is properly linked to your profile for accurate generation. Once this is done, you are ready to proceed with creating content.</p>
        <!-- Tab Navigation -->
        <h2 class="nav-tab-user-wrapper">
            <a href="#update-user-tab" class="nav-tab-user nav-tab-user-active">Update User</a>
            <a href="#create-user-tab" class="nav-tab-user">Create User</a>
        </h2>

        <!-- Create User Tab Content -->
        <div id="create-user-tab" class="user-tab-content" style="display: none">
            <form method="post">
                <table class="form-table">
                    <tr>
                        <th><label for="create_username">Username</label></th>
                        <td><input type="text" name="username" id="create_username" required></td>
                    </tr>
                    <tr>
                        <th><label for="create_email">Email</label></th>
                        <td><input type="email" name="email" id="create_email" required></td>
                    </tr>
                    <tr>
                        <th><label for="create_password">Password</label></th>
                        <td><input type="password" name="password" id="create_password" required></td>
                    </tr>
                    <tr>
                        <th><label for="create_role">Role</label></th>
                        <td>
                            <select name="role" id="create_role" required>
                                <?php foreach ($roles as $role_key => $role): ?>
                                                <option value="<?php echo esc_attr($role_key); ?>">
                                                    <?php echo esc_html($role['name']); ?>
                                                </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="create_persona">Persona</label></th>
                        <td>
                            <select name="persona" id="create_persona" required>
                                <?php foreach ($personas as $persona): ?>
                                                <option value="<?php echo esc_attr($persona['persona']); ?>">
                                                    <?php echo esc_html($persona['persona']); ?>
                                                </option>
                                <?php endforeach; ?>
                            </select>
                            <br/><br/>
                                <!-- Add the "Create a new persona" link -->
                                <a href="<?php echo esc_url(admin_url('admin.php?page=manage-personas')); ?>" class="button button-secondary" target="_blank">
                                    Persona not found? Create a new persona
                                </a>
                                                </td>
                    </tr>
                    <tr>
                        <th><label for="create_industry">Industry</label></th>
                        <td>
                            <select name="industry" id="create_industry">
                                <option value="General">General (No Industry Specific)</option>
                                <!-- Healthcare Practices -->
                                <optgroup label="Healthcare Practices">
                                    <option value="Dentists">Dentists</option>
                                    <option value="Chiropractors">Chiropractors</option>
                                    <option value="Physical Therapists">Physical Therapists</option>
                                    <option value="Optometrists">Optometrists</option>
                                    <option value="Podiatrists">Podiatrists</option>
                                    <option value="Psychologists">Psychologists</option>
                                    <option value="Occupational Therapists">Occupational Therapists</option>
                                    <option value="Speech Therapists">Speech Therapists</option>
                                    <option value="Dermatologists">Dermatologists</option>
                                    <option value="Cardiologists">Cardiologists</option>
                                    <option value="Family Medicine Practitioners">Family Medicine Practitioners</option>
                                    <option value="Orthodontists">Orthodontists</option>
                                    <option value="Pediatricians">Pediatricians</option>
                                    <option value="General Practitioners">General Practitioners</option>
                                    <option value="Oncologists">Oncologists</option>
                                    <option value="Ophthalmologists">Ophthalmologists</option>
                                    <option value="ENT Specialists">ENT (Ear, Nose, and Throat) Specialists</option>
                                    <option value="Gastroenterologists">Gastroenterologists</option>
                                    <option value="Urologists">Urologists</option>
                                    <option value="Dietitians/Nutritionists">Dietitians/Nutritionists</option>
                                    <option value="Acupuncturists">Acupuncturists</option>
                                    <option value="Homeopathic Practitioners">Homeopathic Practitioners</option>
                                </optgroup>

                                <!-- Retail & E-commerce -->
                                <optgroup label="Retail & E-commerce">
                                    <option value="Apparel Stores">Apparel Stores</option>
                                    <option value="Grocery Stores">Grocery Stores</option>
                                    <option value="Electronics Stores">Electronics Stores</option>
                                    <option value="Home Goods Retailers">Home Goods Retailers</option>
                                    <option value="Beauty & Cosmetic Stores">Beauty & Cosmetic Stores</option>
                                    <option value="Jewelry Stores">Jewelry Stores</option>
                                    <option value="Sports Equipment Stores">Sports Equipment Stores</option>
                                    <option value="Bookstores">Bookstores</option>
                                    <option value="Pet Stores">Pet Stores</option>
                                    <option value="Auto Parts Retailers">Auto Parts Retailers</option>
                                    <option value="Outdoor Equipment Stores">Outdoor Equipment Stores</option>
                                    <option value="Furniture Stores">Furniture Stores</option>
                                    <option value="Toy Stores">Toy Stores</option>
                                    <option value="Craft and Hobby Stores">Craft and Hobby Stores</option>
                                    <option value="Footwear Retailers">Footwear Retailers</option>
                                    <option value="Convenience Stores">Convenience Stores</option>
                                    <option value="Online Subscription Services">Online Subscription Services</option>
                                    <option value="Health and Wellness Retailers">Health and Wellness Retailers</option>
                                </optgroup>

                                <!-- Hospitality & Travel -->
                                <optgroup label="Hospitality & Travel">
                                    <option value="Hotels & Resorts">Hotels & Resorts</option>
                                    <option value="Bed and Breakfasts">Bed and Breakfasts</option>
                                    <option value="Vacation Rentals">Vacation Rentals</option>
                                    <option value="Restaurants">Restaurants</option>
                                    <option value="Cafes & Coffee Shops">Cafes & Coffee Shops</option>
                                    <option value="Bars & Pubs">Bars & Pubs</option>
                                    <option value="Catering Services">Catering Services</option>
                                    <option value="Event Venues">Event Venues</option>
                                    <option value="Travel Agencies">Travel Agencies</option>
                                    <option value="Tour Operators">Tour Operators</option>
                                    <option value="Cruise Lines">Cruise Lines</option>
                                    <option value="Airlines">Airlines</option>
                                    <option value="Car Rental Agencies">Car Rental Agencies</option>
                                    <option value="Theme Parks">Theme Parks</option>
                                    <option value="Casinos">Casinos</option>
                                    <option value="Spas & Wellness Centers">Spas & Wellness Centers</option>
                                    <option value="Nightclubs">Nightclubs</option>
                                    <option value="Food Trucks">Food Trucks</option>
                                </optgroup>

                                <!-- Financial Services -->
                                <optgroup label="Financial Services">
                                    <option value="Banks">Banks</option>
                                    <option value="Credit Unions">Credit Unions</option>
                                    <option value="Mortgage Lenders">Mortgage Lenders</option>
                                    <option value="Insurance Companies">Insurance Companies</option>
                                    <option value="Investment Firms">Investment Firms</option>
                                    <option value="Accountants & CPAs">Accountants & CPAs</option>
                                    <option value="Financial Advisors">Financial Advisors</option>
                                    <option value="Tax Preparers">Tax Preparers</option>
                                    <option value="Wealth Management Services">Wealth Management Services</option>
                                    <option value="Payment Processing Companies">Payment Processing Companies</option>
                                    <option value="Fintech Startups">Fintech Startups</option>
                                    <option value="Credit Repair Services">Credit Repair Services</option>
                                    <option value="Hedge Funds">Hedge Funds</option>
                                    <option value="Private Equity Firms">Private Equity Firms</option>
                                    <option value="Venture Capital Firms">Venture Capital Firms</option>
                                    <option value="Estate Planning Services">Estate Planning Services</option>
                                    <option value="Pension Funds">Pension Funds</option>
                                </optgroup>

                                <!-- Real Estate -->
                                <optgroup label="Real Estate">
                                    <option value="Residential Realtors">Residential Realtors</option>
                                    <option value="Commercial Real Estate Brokers">Commercial Real Estate Brokers</option>
                                    <option value="Property Management Companies">Property Management Companies</option>
                                    <option value="Real Estate Developers">Real Estate Developers</option>
                                    <option value="Real Estate Investment Trusts (REITs)">Real Estate Investment Trusts (REITs)</option>
                                    <option value="Home Inspectors">Home Inspectors</option>
                                    <option value="Appraisal Services">Appraisal Services</option>
                                    <option value="Real Estate Attorneys">Real Estate Attorneys</option>
                                    <option value="Mortgage Brokers">Mortgage Brokers</option>
                                    <option value="Title Companies">Title Companies</option>
                                    <option value="Vacation Property Rentals">Vacation Property Rentals</option>
                                    <option value="Corporate Housing Providers">Corporate Housing Providers</option>
                                    <option value="Real Estate Marketing Services">Real Estate Marketing Services</option>
                                    <option value="Interior Design for Real Estate">Interior Design for Real Estate</option>
                                    <option value="Real Estate Staging Companies">Real Estate Staging Companies</option>
                                    <option value="Auction Houses">Auction Houses</option>
                                </optgroup>

                                <!-- Education & Training -->
                                <optgroup label="Education & Training">
                                    <option value="Primary Schools">Primary Schools</option>
                                    <option value="Secondary Schools">Secondary Schools</option>
                                    <option value="Universities & Colleges">Universities & Colleges</option>
                                    <option value="Vocational Schools">Vocational Schools</option>
                                    <option value="Trade Schools">Trade Schools</option>
                                    <option value="Online Learning Platforms">Online Learning Platforms</option>
                                    <option value="Tutors & Test Prep Services">Tutors & Test Prep Services</option>
                                    <option value="Professional Certification Programs">Professional Certification Programs</option>
                                    <option value="Language Schools">Language Schools</option>
                                    <option value="Music Schools">Music Schools</option>
                                    <option value="Dance Studios">Dance Studios</option>
                                    <option value="Art Schools">Art Schools</option>
                                    <option value="Educational Consultancies">Educational Consultancies</option>
                                    <option value="Homeschooling Services">Homeschooling Services</option>
                                    <option value="Corporate Training Providers">Corporate Training Providers</option>
                                    <option value="Personal Development Coaches">Personal Development Coaches</option>
                                    <option value="Continuing Education Providers">Continuing Education Providers</option>
                                </optgroup>

                                <!-- Legal Services -->
                                <optgroup label="Legal Services">
                                    <option value="Personal Injury Attorneys">Personal Injury Attorneys</option>
                                    <option value="Family Law Attorneys">Family Law Attorneys</option>
                                    <option value="Corporate Law Firms">Corporate Law Firms</option>
                                    <option value="Criminal Defense Attorneys">Criminal Defense Attorneys</option>
                                    <option value="Immigration Lawyers">Immigration Lawyers</option>
                                    <option value="Patent & Intellectual Property Attorneys">Patent & Intellectual Property Attorneys</option>
                                    <option value="Real Estate Lawyers">Real Estate Lawyers</option>
                                    <option value="Employment Lawyers">Employment Lawyers</option>
                                    <option value="Bankruptcy Attorneys">Bankruptcy Attorneys</option>
                                    <option value="Estate Planning Lawyers">Estate Planning Lawyers</option>
                                    <option value="Tax Lawyers">Tax Lawyers</option>
                                    <option value="Medical Malpractice Lawyers">Medical Malpractice Lawyers</option>
                                    <option value="Class Action Firms">Class Action Firms</option>
                                    <option value="Environmental Law Attorneys">Environmental Law Attorneys</option>
                                    <option value="Contract Lawyers">Contract Lawyers</option>
                                    <option value="Paralegal Services">Paralegal Services</option>
                                    <option value="Legal Mediation Services">Legal Mediation Services</option>
                                    <option value="Notary Public Services">Notary Public Services</option>
                                </optgroup>

                                <!-- Construction & Trades -->
                                <optgroup label="Construction & Trades">
                                    <option value="General Contractors">General Contractors</option>
                                    <option value="Electricians">Electricians</option>
                                    <option value="Plumbers">Plumbers</option>
                                    <option value="HVAC Technicians">HVAC Technicians</option>
                                    <option value="Carpenters">Carpenters</option>
                                    <option value="Roofers">Roofers</option>
                                    <option value="Painters">Painters</option>
                                    <option value="Masons">Masons</option>
                                    <option value="Flooring Specialists">Flooring Specialists</option>
                                    <option value="Landscapers">Landscapers</option>
                                    <option value="Interior Designers">Interior Designers</option>
                                    <option value="Architects">Architects</option>
                                    <option value="Home Builders">Home Builders</option>
                                    <option value="Commercial Construction Firms">Commercial Construction Firms</option>
                                    <option value="Renovation Specialists">Renovation Specialists</option>
                                    <option value="Handyman Services">Handyman Services</option>
                                    <option value="Structural Engineers">Structural Engineers</option>
                                    <option value="Surveyors">Surveyors</option>
                                </optgroup>

                                <!-- Automotive Services -->
                                <optgroup label="Automotive Services">
                                    <option value="Car Dealerships">Car Dealerships</option>
                                    <option value="Auto Repair Shops">Auto Repair Shops</option>
                                    <option value="Tire Shops">Tire Shops</option>
                                    <option value="Auto Body Shops">Auto Body Shops</option>
                                    <option value="Car Wash & Detailing Services">Car Wash & Detailing Services</option>
                                    <option value="Auto Parts Stores">Auto Parts Stores</option>
                                    <option value="Auto Glass Repair Services">Auto Glass Repair Services</option>
                                    <option value="Towing Services">Towing Services</option>
                                    <option value="Vehicle Inspection Stations">Vehicle Inspection Stations</option>
                                    <option value="Motorcycle Dealerships">Motorcycle Dealerships</option>
                                    <option value="RV Dealerships">RV Dealerships</option>
                                    <option value="Boat Dealerships">Boat Dealerships</option>
                                    <option value="Auto Insurance Agencies">Auto Insurance Agencies</option>
                                    <option value="Car Rental Companies">Car Rental Companies</option>
                                    <option value="Mobile Mechanic Services">Mobile Mechanic Services</option>
                                    <option value="EV Charging Stations">Electric Vehicle (EV) Charging Stations</option>
                                </optgroup>

                                <!-- Technology & IT Services -->
                                <optgroup label="Technology & IT Services">
                                    <option value="Managed IT Services">Managed IT Services</option>
                                    <option value="Software Development Firms">Software Development Firms</option>
                                    <option value="Web Development Agencies">Web Development Agencies</option>
                                    <option value="Cloud Service Providers">Cloud Service Providers</option>
                                    <option value="Cybersecurity Companies">Cybersecurity Companies</option>
                                    <option value="Data Centers">Data Centers</option>
                                    <option value="IT Consulting Firms">IT Consulting Firms</option>
                                    <option value="Network Infrastructure Providers">Network Infrastructure Providers</option>
                                    <option value="App Development Firms">App Development Firms</option>
                                    <option value="IT Support Services">IT Support Services</option>
                                    <option value="SaaS Companies">SaaS (Software as a Service) Companies</option>
                                    <option value="Tech Repair Shops">Tech Repair Shops</option>
                                    <option value="Tech Staffing Agencies">Tech Staffing Agencies</option>
                                    <option value="Data Recovery Services">Data Recovery Services</option>
                                    <option value="Blockchain Development Firms">Blockchain Development Firms</option>
                                    <option value="Digital Transformation Consultants">Digital Transformation Consultants</option>
                                </optgroup>

                                <!-- Media & Entertainment -->
                                <optgroup label="Media & Entertainment">
                                    <option value="Film Production Companies">Film Production Companies</option>
                                    <option value="TV Networks">TV Networks</option>
                                    <option value="Radio Stations">Radio Stations</option>
                                    <option value="Music Production Studios">Music Production Studios</option>
                                    <option value="Event Planners">Event Planners</option>
                                    <option value="Live Event Venues">Live Event Venues</option>
                                    <option value="Talent Agencies">Talent Agencies</option>
                                    <option value="Videographers & Photographers">Videographers & Photographers</option>
                                    <option value="Digital Media Agencies">Digital Media Agencies</option>
                                    <option value="Social Media Influencers">Social Media Influencers</option>
                                    <option value="Public Relations Firms">Public Relations Firms</option>
                                    <option value="Graphic Design Studios">Graphic Design Studios</option>
                                    <option value="Animation Studios">Animation Studios</option>
                                    <option value="Streaming Services">Streaming Services</option>
                                    <option value="Podcast Production Companies">Podcast Production Companies</option>
                                    <option value="Publishing Houses">Publishing Houses</option>
                                    <option value="Magazines & Newspapers">Magazines & Newspapers</option>
                                    <option value="Advertising Agencies">Advertising Agencies</option>
                                    <option value="Video Game Development Firms">Video Game Development Firms</option>
                                </optgroup>

                                <!-- Manufacturing & Industrial -->
                                <optgroup label="Manufacturing & Industrial">
                                    <option value="Aerospace Manufacturers">Aerospace Manufacturers</option>
                                    <option value="Automotive Manufacturers">Automotive Manufacturers</option>
                                    <option value="Electronics Manufacturers">Electronics Manufacturers</option>
                                    <option value="Food & Beverage Manufacturers">Food & Beverage Manufacturers</option>
                                    <option value="Furniture Manufacturers">Furniture Manufacturers</option>
                                    <option value="Packaging Companies">Packaging Companies</option>
                                    <option value="Chemical Manufacturers">Chemical Manufacturers</option>
                                    <option value="Steel & Metal Fabricators">Steel & Metal Fabricators</option>
                                    <option value="Pharmaceuticals Manufacturers">Pharmaceuticals Manufacturers</option>
                                    <option value="Clothing & Apparel Manufacturers">Clothing & Apparel Manufacturers</option>
                                    <option value="Machine Tool Companies">Machine Tool Companies</option>
                                    <option value="Industrial Equipment Suppliers">Industrial Equipment Suppliers</option>
                                    <option value="Oil & Gas Refining">Oil & Gas Refining</option>
                                    <option value="Renewable Energy Manufacturers">Renewable Energy Manufacturers</option>
                                    <option value="Construction Materials Suppliers">Construction Materials Suppliers</option>
                                    <option value="Paper Products Manufacturers">Paper Products Manufacturers</option>
                                    <option value="Plastics Manufacturers">Plastics Manufacturers</option>
                                </optgroup>
                            </select>
                        </td>
                    </tr>

                        <tr>
                            <th><label for="create_language">Language</label></th>
                            <td>
                                <select name="language" id="create_language" required>
                                    <option value="English">English</option>
                                    <option value="Spanish">Spanish (Español)</option>
                                    <option value="French">French (Français)</option>
                                    <option value="German">German (Deutsch)</option>
                                    <option value="Chinese">Chinese (Simplified and Traditional) (中文)</option>
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
                                    <option value="Tamil">Tamil</option> <!-- Retaining the original Tamil option -->
                                </select>
                            </td>
                        </tr>

                    <tr>
                        <th><label for="create_location">Location</label></th>
                        <td><input type="text" name="location" id="create_location"></td>
                    </tr>
                    <tr>
                        <th><label for="create_url">Domain URL</label></th>
                        <td><input type="text" name="url" id="create_url"></td>
                    </tr>
                    <tr>
                        <th></th>
                        <td>
                            <!-- Add the Find Business Details button below the URL field -->
                            <button type="button" id="find_business_details" class="button">Find Business Details</button>
                        </td>
                    </tr>
                    <tr id="business_details_row" style="">
                        <th><label for="business_details">Business Details</label></th>
                        <td>
                        <!-- Textarea to display the fetched business details -->
                            <textarea name="business_details" id="business_details" rows="10" style="width: 100%;" placeholder="Business details will be displayed here..."></textarea>
                        </td>
                    </tr>

                    <tr>
                        <th></th>
                        <td>
                            <!-- Add the Find Business Details button below the URL field -->
                            <button type="button" id="find_domain_authority" class="button">Find Domain Authority</button>
                        </td>
                    </tr>
                    <tr id="domain_authority_row">
                        <th><label for="domain_authority">Domain Authority</label></th>
                        <td>
                        <!-- Textarea to display the fetched business details -->
                            <input name="domain_authority" id="domain_authority" placeholder="domain authority goes here.."></input>
                        </td>
                    </tr>
                    <tr id="content_strategy_row">
                        <th><label for="content_strategy">SEO Content Strategy</label></th>
                        <td>
                        <!-- Textarea to display the fetched business details -->
                            <textarea name="content_strategy" id="content_strategy" placeholder="Content strategy goes here..."></textarea>
                        </td>
                    </tr>
                </table>
                <p class="submit">
                    <?php wp_nonce_field('create_user_action', 'create_user_nonce'); ?>
                    <input type="submit" name="create_user" id="create_user" class="button button-primary"
                        value="Create User">
                </p>
            </form>
        </div>

        <!-- Update User Tab Content -->
        <div id="update-user-tab" class="user-tab-content">
        <form method="post" id="update-user-form" style="margin-top: 20px; display: none">
                                <input type="hidden" name="user_id" id="update_user_id" value="">
                                <table class="form-table" style="width: 50vw">
                                    <tr>
                                        <th><label for="update_username">Username</label></th>
                                        <td><input type="text" name="username" id="update_username" required></td>
                                    </tr>
                                    <tr>
                                        <th><label for="update_email">Email</label></th>
                                        <td><input type="email" name="email" id="update_email" required></td>
                                    </tr>
                                    <tr>
                                        <th><label for="update_password">Password</label></th>
                                        <td><input type="password" name="password" id="update_password"></td>
                                    </tr>
                                    <tr>
                                        <th><label for="update_role">Role</label></th>
                                        <td>
                                            <select name="role" id="update_role" required>
                                                <?php foreach ($roles as $role_key => $role): ?>
                                                                <option value="<?php echo esc_attr($role_key); ?>">
                                                                    <?php echo esc_html($role['name']); ?>
                                                                </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th><label for="update_persona">Persona</label>
                                        </th>
                                        <td>
                                            <select name="persona" id="update_persona" required>
                                                <?php foreach ($personas as $persona): ?>
                                                                <option value="<?php echo esc_attr($persona['persona']); ?>">
                                                                    <?php echo esc_html($persona['persona']); ?>
                                                                </option>
                                                <?php endforeach; ?>
                                            </select>
        <!-- Add the "Create a new persona" link -->
        <a href="<?php echo esc_url(admin_url('admin.php?page=manage-personas')); ?>" class="button" target="_blank">
            Persona not found? Create a new persona
        </a>
                                        </td>
                                    </tr>
                                    <tr>
                    <th><label for="update_industry">Industry</label></th>
                    <td>
                        <select name="industry" id="update_industry" required>
                            <option value="General">General  (No Industry Specific)</option>
                
                            <!-- Healthcare Practices -->
                            <optgroup label="Healthcare Practices">
                                <option value="Dentists">Dentists</option>
                                <option value="Chiropractors">Chiropractors</option>
                                <option value="Physical Therapists">Physical Therapists</option>
                                <option value="Optometrists">Optometrists</option>
                                <option value="Podiatrists">Podiatrists</option>
                                <option value="Psychologists">Psychologists</option>
                                <option value="Occupational Therapists">Occupational Therapists</option>
                                <option value="Speech Therapists">Speech Therapists</option>
                                <option value="Dermatologists">Dermatologists</option>
                                <option value="Cardiologists">Cardiologists</option>
                                <option value="Family Medicine Practitioners">Family Medicine Practitioners</option>
                                <option value="Orthodontists">Orthodontists</option>
                                <option value="Pediatricians">Pediatricians</option>
                                <option value="General Practitioners">General Practitioners</option>
                                <option value="Oncologists">Oncologists</option>
                                <option value="Ophthalmologists">Ophthalmologists</option>
                                <option value="ENT Specialists">ENT (Ear, Nose, and Throat) Specialists</option>
                                <option value="Gastroenterologists">Gastroenterologists</option>
                                <option value="Urologists">Urologists</option>
                                <option value="Dietitians/Nutritionists">Dietitians/Nutritionists</option>
                                <option value="Acupuncturists">Acupuncturists</option>
                                <option value="Homeopathic Practitioners">Homeopathic Practitioners</option>
                            </optgroup>
                
                            <!-- Retail & E-commerce -->
                            <optgroup label="Retail & E-commerce">
                                <option value="Apparel Stores">Apparel Stores</option>
                                <option value="Grocery Stores">Grocery Stores</option>
                                <option value="Electronics Stores">Electronics Stores</option>
                                <option value="Home Goods Retailers">Home Goods Retailers</option>
                                <option value="Beauty & Cosmetic Stores">Beauty & Cosmetic Stores</option>
                                <option value="Jewelry Stores">Jewelry Stores</option>
                                <option value="Sports Equipment Stores">Sports Equipment Stores</option>
                                <option value="Bookstores">Bookstores</option>
                                <option value="Pet Stores">Pet Stores</option>
                                <option value="Auto Parts Retailers">Auto Parts Retailers</option>
                                <option value="Outdoor Equipment Stores">Outdoor Equipment Stores</option>
                                <option value="Furniture Stores">Furniture Stores</option>
                                <option value="Toy Stores">Toy Stores</option>
                                <option value="Craft and Hobby Stores">Craft and Hobby Stores</option>
                                <option value="Footwear Retailers">Footwear Retailers</option>
                                <option value="Convenience Stores">Convenience Stores</option>
                                <option value="Online Subscription Services">Online Subscription Services</option>
                                <option value="Health and Wellness Retailers">Health and Wellness Retailers</option>
                            </optgroup>
                
                            <!-- Hospitality & Travel -->
                            <optgroup label="Hospitality & Travel">
                                <option value="Hotels & Resorts">Hotels & Resorts</option>
                                <option value="Bed and Breakfasts">Bed and Breakfasts</option>
                                <option value="Vacation Rentals">Vacation Rentals</option>
                                <option value="Restaurants">Restaurants</option>
                                <option value="Cafes & Coffee Shops">Cafes & Coffee Shops</option>
                                <option value="Bars & Pubs">Bars & Pubs</option>
                                <option value="Catering Services">Catering Services</option>
                                <option value="Event Venues">Event Venues</option>
                                <option value="Travel Agencies">Travel Agencies</option>
                                <option value="Tour Operators">Tour Operators</option>
                                <option value="Cruise Lines">Cruise Lines</option>
                                <option value="Airlines">Airlines</option>
                                <option value="Car Rental Agencies">Car Rental Agencies</option>
                                <option value="Theme Parks">Theme Parks</option>
                                <option value="Casinos">Casinos</option>
                                <option value="Spas & Wellness Centers">Spas & Wellness Centers</option>
                                <option value="Nightclubs">Nightclubs</option>
                                <option value="Food Trucks">Food Trucks</option>
                            </optgroup>
                
                            <!-- Financial Services -->
                            <optgroup label="Financial Services">
                                <option value="Banks">Banks</option>
                                <option value="Credit Unions">Credit Unions</option>
                                <option value="Mortgage Lenders">Mortgage Lenders</option>
                                <option value="Insurance Companies">Insurance Companies</option>
                                <option value="Investment Firms">Investment Firms</option>
                                <option value="Accountants & CPAs">Accountants & CPAs</option>
                                <option value="Financial Advisors">Financial Advisors</option>
                                <option value="Tax Preparers">Tax Preparers</option>
                                <option value="Wealth Management Services">Wealth Management Services</option>
                                <option value="Payment Processing Companies">Payment Processing Companies</option>
                                <option value="Fintech Startups">Fintech Startups</option>
                                <option value="Credit Repair Services">Credit Repair Services</option>
                                <option value="Hedge Funds">Hedge Funds</option>
                                <option value="Private Equity Firms">Private Equity Firms</option>
                                <option value="Venture Capital Firms">Venture Capital Firms</option>
                                <option value="Estate Planning Services">Estate Planning Services</option>
                                <option value="Pension Funds">Pension Funds</option>
                            </optgroup>
                
                            <!-- Real Estate -->
                            <optgroup label="Real Estate">
                                <option value="Residential Realtors">Residential Realtors</option>
                                <option value="Commercial Real Estate Brokers">Commercial Real Estate Brokers</option>
                                <option value="Property Management Companies">Property Management Companies</option>
                                <option value="Real Estate Developers">Real Estate Developers</option>
                                <option value="Real Estate Investment Trusts (REITs)">Real Estate Investment Trusts (REITs)</option>
                                <option value="Home Inspectors">Home Inspectors</option>
                                <option value="Appraisal Services">Appraisal Services</option>
                                <option value="Real Estate Attorneys">Real Estate Attorneys</option>
                                <option value="Mortgage Brokers">Mortgage Brokers</option>
                                <option value="Title Companies">Title Companies</option>
                                <option value="Vacation Property Rentals">Vacation Property Rentals</option>
                                <option value="Corporate Housing Providers">Corporate Housing Providers</option>
                                <option value="Real Estate Marketing Services">Real Estate Marketing Services</option>
                                <option value="Interior Design for Real Estate">Interior Design for Real Estate</option>
                                <option value="Real Estate Staging Companies">Real Estate Staging Companies</option>
                                <option value="Auction Houses">Auction Houses</option>
                            </optgroup>
                
                            <!-- Education & Training -->
                            <optgroup label="Education & Training">
                                <option value="Primary Schools">Primary Schools</option>
                                <option value="Secondary Schools">Secondary Schools</option>
                                <option value="Universities & Colleges">Universities & Colleges</option>
                                <option value="Vocational Schools">Vocational Schools</option>
                                <option value="Trade Schools">Trade Schools</option>
                                <option value="Online Learning Platforms">Online Learning Platforms</option>
                                <option value="Tutors & Test Prep Services">Tutors & Test Prep Services</option>
                                <option value="Professional Certification Programs">Professional Certification Programs</option>
                                <option value="Language Schools">Language Schools</option>
                                <option value="Music Schools">Music Schools</option>
                                <option value="Dance Studios">Dance Studios</option>
                                <option value="Art Schools">Art Schools</option>
                                <option value="Educational Consultancies">Educational Consultancies</option>
                                <option value="Homeschooling Services">Homeschooling Services</option>
                                <option value="Corporate Training Providers">Corporate Training Providers</option>
                                <option value="Personal Development Coaches">Personal Development Coaches</option>
                                <option value="Continuing Education Providers">Continuing Education Providers</option>
                            </optgroup>
                
                            <!-- Legal Services -->
                            <optgroup label="Legal Services">
                                <option value="Personal Injury Attorneys">Personal Injury Attorneys</option>
                                <option value="Family Law Attorneys">Family Law Attorneys</option>
                                <option value="Corporate Law Firms">Corporate Law Firms</option>
                                <option value="Criminal Defense Attorneys">Criminal Defense Attorneys</option>
                                <option value="Immigration Lawyers">Immigration Lawyers</option>
                                <option value="Patent & Intellectual Property Attorneys">Patent & Intellectual Property Attorneys</option>
                                <option value="Real Estate Lawyers">Real Estate Lawyers</option>
                                <option value="Employment Lawyers">Employment Lawyers</option>
                                <option value="Bankruptcy Attorneys">Bankruptcy Attorneys</option>
                                <option value="Estate Planning Lawyers">Estate Planning Lawyers</option>
                                <option value="Tax Lawyers">Tax Lawyers</option>
                                <option value="Medical Malpractice Lawyers">Medical Malpractice Lawyers</option>
                                <option value="Class Action Firms">Class Action Firms</option>
                                <option value="Environmental Law Attorneys">Environmental Law Attorneys</option>
                                <option value="Contract Lawyers">Contract Lawyers</option>
                                <option value="Paralegal Services">Paralegal Services</option>
                                <option value="Legal Mediation Services">Legal Mediation Services</option>
                                <option value="Notary Public Services">Notary Public Services</option>
                            </optgroup>
                
                            <!-- Construction & Trades -->
                            <optgroup label="Construction & Trades">
                                <option value="General Contractors">General Contractors</option>
                                <option value="Electricians">Electricians</option>
                                <option value="Plumbers">Plumbers</option>
                                <option value="HVAC Technicians">HVAC Technicians</option>
                                <option value="Carpenters">Carpenters</option>
                                <option value="Roofers">Roofers</option>
                                <option value="Painters">Painters</option>
                                <option value="Masons">Masons</option>
                                <option value="Flooring Specialists">Flooring Specialists</option>
                                <option value="Landscapers">Landscapers</option>
                                <option value="Interior Designers">Interior Designers</option>
                                <option value="Architects">Architects</option>
                                <option value="Home Builders">Home Builders</option>
                                <option value="Commercial Construction Firms">Commercial Construction Firms</option>
                                <option value="Renovation Specialists">Renovation Specialists</option>
                                <option value="Handyman Services">Handyman Services</option>
                                <option value="Structural Engineers">Structural Engineers</option>
                                <option value="Surveyors">Surveyors</option>
                            </optgroup>
                
                            <!-- Automotive Services -->
                            <optgroup label="Automotive Services">
                                <option value="Car Dealerships">Car Dealerships</option>
                                <option value="Auto Repair Shops">Auto Repair Shops</option>
                                <option value="Tire Shops">Tire Shops</option>
                                <option value="Auto Body Shops">Auto Body Shops</option>
                                <option value="Car Wash & Detailing Services">Car Wash & Detailing Services</option>
                                <option value="Auto Parts Stores">Auto Parts Stores</option>
                                <option value="Auto Glass Repair Services">Auto Glass Repair Services</option>
                                <option value="Towing Services">Towing Services</option>
                                <option value="Vehicle Inspection Stations">Vehicle Inspection Stations</option>
                                <option value="Motorcycle Dealerships">Motorcycle Dealerships</option>
                                <option value="RV Dealerships">RV Dealerships</option>
                                <option value="Boat Dealerships">Boat Dealerships</option>
                                <option value="Auto Insurance Agencies">Auto Insurance Agencies</option>
                                <option value="Car Rental Companies">Car Rental Companies</option>
                                <option value="Mobile Mechanic Services">Mobile Mechanic Services</option>
                                <option value="EV Charging Stations">Electric Vehicle (EV) Charging Stations</option>
                            </optgroup>
                
                            <!-- Technology & IT Services -->
                            <optgroup label="Technology & IT Services">
                                <option value="Managed IT Services">Managed IT Services</option>
                                <option value="Software Development Firms">Software Development Firms</option>
                                <option value="Web Development Agencies">Web Development Agencies</option>
                                <option value="Cloud Service Providers">Cloud Service Providers</option>
                                <option value="Cybersecurity Companies">Cybersecurity Companies</option>
                                <option value="Data Centers">Data Centers</option>
                                <option value="IT Consulting Firms">IT Consulting Firms</option>
                                <option value="Network Infrastructure Providers">Network Infrastructure Providers</option>
                                <option value="App Development Firms">App Development Firms</option>
                                <option value="IT Support Services">IT Support Services</option>
                                <option value="SaaS Companies">SaaS (Software as a Service) Companies</option>
                                <option value="Tech Repair Shops">Tech Repair Shops</option>
                                <option value="Tech Staffing Agencies">Tech Staffing Agencies</option>
                                <option value="Data Recovery Services">Data Recovery Services</option>
                                <option value="Blockchain Development Firms">Blockchain Development Firms</option>
                                <option value="Digital Transformation Consultants">Digital Transformation Consultants</option>
                            </optgroup>
                
                            <!-- Media & Entertainment -->
                            <optgroup label="Media & Entertainment">
                                <option value="Film Production Companies">Film Production Companies</option>
                                <option value="TV Networks">TV Networks</option>
                                <option value="Radio Stations">Radio Stations</option>
                                <option value="Music Production Studios">Music Production Studios</option>
                                <option value="Event Planners">Event Planners</option>
                                <option value="Live Event Venues">Live Event Venues</option>
                                <option value="Talent Agencies">Talent Agencies</option>
                                <option value="Videographers & Photographers">Videographers & Photographers</option>
                                <option value="Digital Media Agencies">Digital Media Agencies</option>
                                <option value="Social Media Influencers">Social Media Influencers</option>
                                <option value="Public Relations Firms">Public Relations Firms</option>
                                <option value="Graphic Design Studios">Graphic Design Studios</option>
                                <option value="Animation Studios">Animation Studios</option>
                                <option value="Streaming Services">Streaming Services</option>
                                <option value="Podcast Production Companies">Podcast Production Companies</option>
                                <option value="Publishing Houses">Publishing Houses</option>
                                <option value="Magazines & Newspapers">Magazines & Newspapers</option>
                                <option value="Advertising Agencies">Advertising Agencies</option>
                                <option value="Video Game Development Firms">Video Game Development Firms</option>
                            </optgroup>
                
                            <!-- Manufacturing & Industrial -->
                            <optgroup label="Manufacturing & Industrial">
                                <option value="Aerospace Manufacturers">Aerospace Manufacturers</option>
                                <option value="Automotive Manufacturers">Automotive Manufacturers</option>
                                <option value="Electronics Manufacturers">Electronics Manufacturers</option>
                                <option value="Food & Beverage Manufacturers">Food & Beverage Manufacturers</option>
                                <option value="Furniture Manufacturers">Furniture Manufacturers</option>
                                <option value="Packaging Companies">Packaging Companies</option>
                                <option value="Chemical Manufacturers">Chemical Manufacturers</option>
                                <option value="Steel & Metal Fabricators">Steel & Metal Fabricators</option>
                                <option value="Pharmaceuticals Manufacturers">Pharmaceuticals Manufacturers</option>
                                <option value="Clothing & Apparel Manufacturers">Clothing & Apparel Manufacturers</option>
                                <option value="Machine Tool Companies">Machine Tool Companies</option>
                                <option value="Industrial Equipment Suppliers">Industrial Equipment Suppliers</option>
                                <option value="Oil & Gas Refining">Oil & Gas Refining</option>
                                <option value="Renewable Energy Manufacturers">Renewable Energy Manufacturers</option>
                                <option value="Construction Materials Suppliers">Construction Materials Suppliers</option>
                                <option value="Paper Products Manufacturers">Paper Products Manufacturers</option>
                                <option value="Plastics Manufacturers">Plastics Manufacturers</option>
                            </optgroup>
                        </select>
                    </td>
                </tr>

                <tr>
                <th><label for="update_language">Language</label></th>
                <td>
                    <select name="language" id="update_language" required>
                        <option value="English">English</option>
                        <option value="Spanish">Spanish (Español)</option>
                        <option value="French">French (Français)</option>
                        <option value="German">German (Deutsch)</option>
                        <option value="Chinese">Chinese (Simplified and Traditional) (中文)</option>
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
                        <option value="Tamil">Tamil</option> <!-- Retaining the original Tamil option -->
                    </select>
                </td>
            </tr>
                
                <tr>
                    <th><label for="update_location">Location</label></th>
                    <td><input type="text" name="location" id="update_location"></td>
                </tr>
                <tr>
                    <th><label for="update_url">Domain URL</label></th>
                    <td><input type="text" name="url" id="update_url"></td>
                </tr>
                <tr>                     
                    <th></th>
                    <td>
                        <!-- Add the Find Business Details button below the URL field -->
                         <button type="button" id="find_update_business_details" class="button">Find Business Details</button>
                    </td>
                </tr>
                <tr>                     
                    <th></th>
                    <td>
                        <!-- Add the Find Business Details button below the URL field -->
                         <button type="button" id="find_update_domain_authority" class="button">Find Domain Authority</button>
                    </td>
                </tr>
                <tr id="update_domain_authority_row">
                <th><label for="update_domain_authority">Domain Authority</label></th>
                <td>
                    <!-- Textarea to display the fetched business details -->
                    <input name="domain_authority" id="update_domain_authority" placeholder="Domain Authority goes here.."></input>
                </td>
                </tr>
                <tr id="update_content_strategy_row">
                <th><label for="update_content_strategy">SEO Content Strategy</label></th>
                <td>
                    <!-- Textarea to display the fetched business details -->
                    <textarea name="content_strategy" id="update_content_strategy" placeholder="content strategy will be displayed here..."></textarea>
                </td>
                </tr>
                <tr>
                    <th></th>
                    <td>
                    <span class="submit">
                        <?php wp_nonce_field('update_user_action', 'update_user_nonce'); ?>
                        <input type="submit" name="update_user" id="update_user" class="button button-primary"
                            value="Update User">
                    </span>
                    </td>
                </tr>
            </table>

            <div id="update_business_details_row" style="width: 40vw">
                <th><label for="update_business_details">Business Details</label></th>
                <td>
                    <!-- Textarea to display the fetched business details -->
                    <textarea name="business_details" id="update_business_details" rows="35" style="width: 100%;" placeholder="Business details will be displayed here..."></textarea>
                </td>
            </div>
            
        </form>    
        <table class="widefat" cellspacing="0">
                <thead>
                    <tr>
                        <th class="manage-column column-cb check-column" scope="col">#</th>
                        <th class="manage-column column-username" scope="col">Username</th>
                        <th class="manage-column column-email" scope="col">Email</th>
                        <th class="manage-column column-role" scope="col">Role</th>
                        <th class="manage-column column-persona" scope="col">Persona</th>
                        <th class="manage-column column-industry" scope="col">Industry</th>
                        <th class="manage-column column-location" scope="col">Location</th>
                        <th class="manage-column column-url" scope="col">URL</th>
                        <th class="manage-column column-actions" scope="col">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $users = get_users();
                    foreach ($users as $index => $user):
                        $persona = get_user_meta($user->ID, '_assigned_persona', true);
                        $industry = get_user_meta($user->ID, '_assigned_industry', true);
                        $language = get_user_meta($user->ID, '_assigned_language', true);
                        $location = get_user_meta($user->ID, '_assigned_location', true);
                        $url = get_user_meta($user->ID, '_assigned_url', true);
                        $business_detail = get_user_meta($user->ID, '_assigned_business_detail', true);
                        $domain_authority = get_user_meta($user->ID, '_assigned_domain_authority', true);
                        $content_strategy = get_user_meta($user->ID, '_assigned_content_strategy', true);

                        ?>
                                    <tr>
                                        <th scope="row"><?php echo esc_html($index + 1); ?></th>
                                        <td><?php echo esc_html($user->user_login); ?></td>
                                        <td><?php echo esc_html($user->user_email); ?></td>
                                        <td>
                                            <?php
                                            $user_roles = $user->roles;
                                            $role_names = array_map(function ($role) {
                                                $role_obj = get_role($role);
                                                return $role_obj->name;
                                            }, $user_roles);
                                            echo esc_html(implode(', ', $role_names));
                                            ?>
                                        </td>
                                        <td><?php echo esc_html($persona); ?></td>
                                        <td><?php echo esc_html($industry); ?></td>
                                        <td><?php echo esc_html($location); ?></td>
                                        <td><?php echo esc_html($url); ?></td>
                                        <td>
                                            <button type="button" class="button update-user"
                                                data-user-id="<?php echo esc_attr($user->ID); ?>"
                                                data-username="<?php echo esc_attr($user->user_login); ?>"
                                                data-email="<?php echo esc_attr($user->user_email); ?>"
                                                data-role="<?php echo esc_attr(implode(', ', $user_roles)); ?>"
                                                data-industry="<?php echo esc_attr($industry); ?>"
                                                data-location="<?php echo esc_attr($location); ?>"
                                                data-persona="<?php echo esc_attr($persona); ?>"
                                                data-business="<?php echo esc_attr($business_detail); ?>"
                                                data-domainauthority="<?php echo esc_attr($domain_authority); ?>"
                                                data-strategy="<?php echo esc_attr($content_strategy); ?>"
                                                data-language="<?php echo esc_attr($language); ?>"
                                                data-url="<?php echo esc_attr($url); ?>">
                                               
                                                Update
                                            </button>
                                        </td>
                                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

        </div>
    </div>

    <div id="content-types" class="tab-content" style="display: none;">
        <h2>Manage Content Types</h2>
        <form method="post">
            <table class="form-table">
                <tr>
                    <th><label for="content_type">Content Type</label></th>
                    <td><input type="text" name="content_type" id="content_type" required></td>
                </tr>
                <tr>
                    <th><label for="content_template">Template</label></th>
                    <td><textarea name="content_template" id="content_template" required rows="5" cols="50"></textarea>
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
                                        <form method="post" style="display:inline;">
                                            <input type="hidden" name="content_type" value="<?php echo esc_attr($content_type); ?>">
                                            <textarea name="content_template" rows="5"
                                                cols="50"><?php echo esc_textarea($template); ?></textarea>
                                            <?php wp_nonce_field('add_content_type_action', 'add_content_type_nonce'); ?>
                                            <input type="submit" name="add_content_type" class="button button-primary" value="Update">
                                        </form>

                                        <!-- Form to delete the content type -->
                                        <form method="post" style="display:inline;">
                                            <?php wp_nonce_field('delete_content_type_action', 'delete_content_type_nonce'); ?>
                                            <input type="hidden" name="content_type_key" value="<?php echo esc_attr($content_type); ?>">
                                            <input type="submit" name="delete_content_type" class="button button-primary"
                                                value="Delete">
                                        </form>
                                    </td>
                                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div id="notifications" class="tab-content" style="display: none;">
        <h2>Notifications</h2>
        <ul id="notifications-list">
            <!-- Notifications will be appended here -->
        </ul>
    </div>
    <div id="settings" class="tab-content" style="display: none;">
        <h2>Settings</h2>
        <a href="<?php echo admin_url('admin.php?page=content-types'); ?>" class="button button-primary" style="margin-bottom: 20px;">Go to Content Types</a>
    <a href="<?php echo admin_url('admin.php?page=manage-personas'); ?>" class="button button-primary" style="margin-bottom: 20px;">Go to Manage Personas</a>

        <form method="post" action="options.php">
            <?php settings_fields('ai_content_pipelines_oneup_author_personas_settings_group'); ?>
            <?php do_settings_sections('ai_content_pipelines_oneup_author_personas_settings_group'); ?>
            <?php wp_nonce_field('author_personas_save_settings', 'author_personas_nonce'); ?>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">Your 1UP API Key</th>
                    <td><input type="text" name="ai_content_pipelines_oneup_openai_api_key"
                            value="<?php echo esc_attr(get_option('ai_content_pipelines_oneup_openai_api_key')); ?>" /></td>
                </tr>
                <tr valign="top">
                    <th scope="row">Twitter API Key</th>
                    <td><input type="text" name="ai_content_pipelines_oneup_twitter_api_key"
                            value="<?php echo esc_attr(get_option('ai_content_pipelines_oneup_twitter_api_key')); ?>" /></td>
                </tr>
                <tr valign="top">
                    <th scope="row">Twitter API Secret</th>
                    <td><input type="text" name="ai_content_pipelines_oneup_twitter_api_secret"
                            value="<?php echo esc_attr(get_option('ai_content_pipelines_oneup_twitter_api_secret')); ?>" /></td>
                </tr>
                <tr valign="top">
                    <th scope="row">Twitter Access Token</th>
                    <td><input type="text" name="ai_content_pipelines_oneup_twitter_access_token"
                            value="<?php echo esc_attr(get_option('ai_content_pipelines_oneup_twitter_access_token')); ?>" /></td>
                </tr>
                <tr valign="top">
                    <th scope="row">Twitter Access Token Secret</th>
                    <td><input type="text" name="ai_content_pipelines_oneup_twitter_access_token_secret"
                            value="<?php echo esc_attr(get_option('ai_content_pipelines_oneup_twitter_access_token_secret')); ?>" /></td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
        <button type="button" id="authorize_facebook" class="button" style="display:none;">Authorize
            Facebook</button>
        <button type="button" id="logout_facebook" class="button" style="display:none;">Logout
            Facebook</button>
        <div id="facebook_page_selection" style="display:none;">
            <label for="facebook_page_id">Select Page:</label>
            <select id="facebook_page_id" name="facebook_page_id"></select>
        </div>
        <button type="button" id="authorize_linkedin" class="button" style="display:none;">Authorize
            LinkedIn</button>
        <button type="button" id="logout_linkedin" class="button" style="display:none;">Logout
            LinkedIn</button>
    </div>
    <div id="viewposts" class="tab-content" style="display: none; height: 60vw">
        <h1>
            <a href="<?php echo esc_url(admin_url('edit.php?post_type=post')); ?>">View All Posts</a>
        </h1>
    </div>
    <div id="viewpostscalendar" class="tab-content" style="display: none; height: 100vh">
        <h1>
            <a href="<?php echo esc_url(admin_url('admin.php?page=content-calendar')); ?>">View Calendar Schedule</a>
        </h1>
    </div>
</div>

<div id="generate-content" class="tab-content" style="display: none;">
    <h2>Generate Scheduled Content Pipelines</h2>

    <!-- Workflow Mode Selection -->
    <form id="workflow-mode-selection-form">
        <table class="form-table">
            <tr>
                <th><label for="workflow_mode">Select Workflow Mode</label></th>
                <td>
                    <select name="workflow_mode" id="workflow_mode" required>
                        <option value="">Select Mode</option>
                        <option value="automated">Automated Calendar Workflow Mode</option>
                        <option value="industry_automated">Industry Workflow Mode</option>
                        <option value="super_automated">Buyer's Journey Workflow Mode</option>
                        <option value="full">Manual Workflow Mode</option>
                    </select>
                </td>
            </tr>
        </table>
    </form>

    <!-- Full Workflow Mode Form -->
    <form id="generate-content-form" method="post" style="display: none;">
        <!-- Full Workflow Mode -->
        <h3>Manual Workflow Mode</h3>
        <table class="form-table">
            <tr>
                <th><label for="content_strategy_full">Content Strategy</label></th>
                <td>
                    <select name="content_strategy_full" id="content_strategy_full" required>
                        <option value="">Select Strategy</option>
                        <option value="Single Article">Single Article</option>
                        <optgroup label="Content Clusters and Pillar Pages">
                            <option value="Topic Hubs and Resource Pages">Topic Hubs and Resource Pages</option>
                            <option value="Thematic Groups and Hub Pages">Thematic Groups and Hub Pages</option>
                            <option value="Cornerstone Content">Cornerstone Content</option>
                        </optgroup>
                        <optgroup label="Content Series">
                            <option value="Ongoing Content Campaigns">Ongoing Content Campaigns</option>
                            <option value="Serialized Content">Serialized Content</option>
                        </optgroup>
                        <optgroup label="Evergreen Content Creation">
                            <option value="Long-Lasting Content">Long-Lasting Content</option>
                            <option value="Seasonal Updates">Seasonal Updates</option>
                        </optgroup>
                        <optgroup label="Thought Leadership">
                            <option value="Industry Insights">Industry Insights</option>
                            <option value="Expert Opinions">Expert Opinions</option>
                        </optgroup>
                        <optgroup label="Keyword Clusters">
                            <option value="Semantic Keywords">Semantic Keywords</option>
                            <option value="Long-Tail Keywords">Long-Tail Keywords</option>
                        </optgroup>
                        <optgroup label="Buyers Journey">
                            <option value="Full Journey">Full Journey - Complete Funnel</option>
                            <option value="Awareness">Awareness - Top of Funnel (TOFU)</option>
                            <option value="Consideration">Consideration - Middle of Funnel (MOFU)</option>
                            <option value="Decision">Decision - Bottom of Funnel (BOFU)</option>
                        </optgroup>
                    </select>
                </td>
            </tr>
            <tr id="recommendation_row" style="display: none;">
                <th>Recommendation</th>
                <td id="recommendation_text"></td>
            </tr>
            <tr id="use_recommended_row" style="display: none;">
                <th></th>
                <td><button type="button" id="use_recommended_values" class="button">Use Recommended Values</button>
                </td>
            </tr>

            <tr>
                <th><label for="number_of_pieces">Number of Pieces</label></th>
                <td><input type="number" name="number_of_pieces" id="number_of_pieces" required></td>
            </tr>
            <tr>
                <th><label for="title">Working Title</label></th>
                <td><input type="text" name="title" id="title" required></td>
            </tr>
            <tr>
                <th><label for="goal">Goal(s)</label></th>
                <td>
                    <select name="goal" id="goal" required>
                        <option value="" disabled selected>Select your Goal</option>
                        <option value="Random">Random</option>
                        <option value="Generate Leads">Generate Leads</option>
                        <option value="Enhance SEO Performance">Enhance SEO Performance</option>
                        <option value="Establish Authority and Trust">Establish Authority and Trust</option>
                        <option value="Increase Brand Awareness">Increase Brand Awareness</option>
                        <option value="Foster Customer Engagement">Foster Customer Engagement</option>
                        <option value="Improve Customer Education">Improve Customer Education</option>
                        <option value="Boost Conversion Rates">Boost Conversion Rates</option>
                        <option value="Nurture Leads">Nurture Leads</option>
                    </select>
                </td>
            </tr>
            <tr id="goal-weightage-row" style="display:none;">
                <th><label>Goal Weightage</label></th>
                <td>
                    <input type="checkbox" id="select-all-goals-full"> Select All<br>
                    <input type="checkbox" id="equal-weightage-goals-full"> Equal Weightage
                    <div id="goal-weightage-container">
                        <div class="goal-weightage-item">
                            <input type="checkbox" class="goal-checkbox-full" name="goal_selected[]"
                                value="Generate Leads">
                            <label>Generate Leads:</label>
                            <input type="number" name="goal_weightage_full[Generate Leads]" value="1" min="0"
                                class="goal-weightage-input-full">
                        </div>
                        <div class="goal-weightage-item">
                            <input type="checkbox" class="goal-checkbox-full" name="goal_selected[]"
                                value="Enhance SEO Performance">
                            <label>Enhance SEO Performance:</label>
                            <input type="number" name="goal_weightage_full[Enhance SEO Performance]" value="1" min="0"
                                class="goal-weightage-input-full">
                        </div>
                        <div class="goal-weightage-item">
                            <input type="checkbox" class="goal-checkbox-full" name="goal_selected[]"
                                value="Establish Authority and Trust">
                            <label>Establish Authority and Trust:</label>
                            <input type="number" name="goal_weightage_full[Establish Authority and Trust]" value="1"
                                min="0" class="goal-weightage-input-full">
                        </div>
                        <div class="goal-weightage-item">
                            <input type="checkbox" class="goal-checkbox-full" name="goal_selected[]"
                                value="Increase Brand Awareness">
                            <label>Increase Brand Awareness:</label>
                            <input type="number" name="goal_weightage_full[Increase Brand Awareness]" value="1" min="0"
                                class="goal-weightage-input-full">
                        </div>
                        <div class="goal-weightage-item">
                            <input type="checkbox" class="goal-checkbox-full" name="goal_selected[]"
                                value="Foster Customer Engagement">
                            <label>Foster Customer Engagement:</label>
                            <input type="number" name="goal_weightage_full[Foster Customer Engagement]" value="1"
                                min="0" class="goal-weightage-input-full">
                        </div>
                        <div class="goal-weightage-item">
                            <input type="checkbox" class="goal-checkbox-full" name="goal_selected[]"
                                value="Improve Customer Education">
                            <label>Improve Customer Education:</label>
                            <input type="number" name="goal_weightage_full[Improve Customer Education]" value="1"
                                min="0" class="goal-weightage-input-full">
                        </div>
                        <div class="goal-weightage-item">
                            <input type="checkbox" class="goal-checkbox-full" name="goal_selected[]"
                                value="Boost Conversion Rates">
                            <label>Boost Conversion Rates:</label>
                            <input type="number" name="goal_weightage_full[Boost Conversion Rates]" value="1" min="0"
                                class="goal-weightage-input-full">
                        </div>
                        <div class="goal-weightage-item">
                            <input type="checkbox" class="goal-checkbox-full" name="goal_selected[]"
                                value="Nurture Leads">
                            <label>Nurture Leads:</label>
                            <input type="number" name="goal_weightage_full[Nurture Leads]" value="1" min="0"
                                class="goal-weightage-input-full">
                        </div>
                    </div>
                </td>
            </tr>

            <tr>
                <th><label for="target_audience">Target Audience</label></th>
                <td><input type="text" name="target_audience" id="target_audience" required></td>
            </tr>
            <tr>
                <th><label for="keywords">Primary and Secondary Keywords</label></th>
                <td><input type="text" name="keywords" id="keywords" required></td>
            </tr>
            <tr>
                <th><label for="search_intent">Search Intent</label></th>
                <td>
                    <select name="search_intent" id="search_intent" required>
                        <option value="" disabled selected>Select your Intent</option>
                        <option value="Random">Random</option>
                        <option value="Informational">Informational (Knowledge-based, Research)</option>
                        <option value="Navigational">Navigational (Direct, Go)</option>
                        <option value="Transactional">Transactional (Buy, Purchase)</option>
                        <option value="Commercial Investigation">Commercial Investigation (Comparison,
                            Consideration)
                        </option>
                        <option value="Local">Local (Near Me, Local Search)</option>
                    </select>
                </td>
            </tr>
            <tr id="search-intent-weightage-row" style="display:none;">
                <th><label>Search Intent Weightage</label></th>
                <td>
                    <input type="checkbox" id="select-all-search-intents-full"> Select All<br>
                    <input type="checkbox" id="equal-weightage-search-intents-full"> Equal Weightage
                    <div id="search-intent-weightage-container">
                        <div class="search-intent-weightage-item">
                            <input type="checkbox" class="search-intent-checkbox-full" name="search_intent_selected[]"
                                value="Informational">
                            <label>Informational:</label>
                            <input type="number" name="search_intent_weightage_full[Informational]" value="1" min="0"
                                class="search-intent-weightage-input-full">
                        </div>
                        <div class="search-intent-weightage-item">
                            <input type="checkbox" class="search-intent-checkbox-full" name="search_intent_selected[]"
                                value="Navigational">
                            <label>Navigational:</label>
                            <input type="number" name="search_intent_weightage_full[Navigational]" value="1" min="0"
                                class="search-intent-weightage-input-full">
                        </div>
                        <div class="search-intent-weightage-item">
                            <input type="checkbox" class="search-intent-checkbox-full" name="search_intent_selected[]"
                                value="Commercial">
                            <label>Commercial:</label>
                            <input type="number" name="search_intent_weightage_full[Commercial]" value="1" min="0"
                                class="search-intent-weightage-input-full">
                        </div>
                        <div class="search-intent-weightage-item">
                            <input type="checkbox" class="search-intent-checkbox-full" name="search_intent_selected[]"
                                value="Transactional">
                            <label>Transactional:</label>
                            <input type="number" name="search_intent_weightage_full[Transactional]" value="1" min="0"
                                class="search-intent-weightage-input-full">
                        </div>
                        <div class="search-intent-weightage-item">
                            <input type="checkbox" class="search-intent-checkbox-full" name="search_intent_selected[]"
                                value="Local">
                            <label>Local:</label>
                            <input type="number" name="search_intent_weightage_full[Local]" value="1" min="0"
                                class="search-intent-weightage-input-full">
                        </div>
                    </div>

                </td>
            </tr>
            <tr>
                <th><label for="links_full">Internal and External Links</label></th>
                <td>
                    <div id="links_full">
                        <div class="link-item_full">
                            <input type="text" name="link_text_full[]" placeholder="Anchor Text">
                            <input type="url" name="link_url_full[]" placeholder="URL">
                            <button type="button" class="remove-link button">Remove</button>
                        </div>
                    </div>
                    <button type="button" id="add-link-full" class="button">Add Link</button>
                </td>
            </tr>
            <tr>
                <th><label for="custom_image">Upload Custom Image</label></th>
                <td><input type="file" name="custom_image" id="custom_image" accept="image/*"></td>
            </tr>
            <tr>
                <th><label for="word_count">Maximum Tokens</label></th>
                <td>
                    <select name="word_count" id="word_count" required>
                        <option value="300">300 tokens (~225 words)</option>
                        <option value="400">400 tokens (~300 words)</option>
                        <option value="500">500 tokens (~375 words)</option>
                        <option value="600">600 tokens (~450 words)</option>
                        <option value="750">750 tokens (~560 words)</option>
                        <option value="1000">1000 tokens (~750 words)</option>
                        <option value="1500">1500 tokens (~1125 words)</option>
                        <option value="2000">2000 tokens (~1500 words)</option>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="full_workflow_content_type">Content-Type</label></th>
                <td>
                    <select name="full_workflow_content_type" id="full_workflow_content_type" required>
                        <option value="" disabled selected>Select your Content Type</option>
                        <option value="Random">Random</option>
                        <?php
                        $content_types = get_option('ai_content_pipelines_oneup_author_personas_content_types', []);
                        foreach ($content_types as $type => $template): ?>
                                        <option value="<?php echo esc_attr($type); ?>">
                                            <?php echo esc_html($type); ?>
                                        </option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr id="content-type-weightage-row-full" style="display:none;">
                <th><label>Content Type Weightage</label></th>
                <td>
                    <div class="flex-container">
                        <div class="flex-item">
                            <input type="checkbox" id="select-all-content-types-full"> Select All
                        </div>
                        <div class="flex-item">
                            <input type="checkbox" id="equal-weightage-content-types-full"> Equal Weightage
                        </div>
                    </div>
                    <div id="content-type-weightage-container-full">
                        <?php
                        $content_types = get_option('ai_content_pipelines_oneup_author_personas_content_types', []);
                        foreach ($content_types as $type => $template) {
                            echo '<div class="flex-container content-type-weightage-item">';
                            echo '<div class="flex-item">';
                            echo '<input type="checkbox" class="content-type-checkbox-full" name="content_type_selected_full[]" value="' . esc_attr($type) . '">';
                            echo '<label>' . esc_html($type) . ':</label>';
                            echo '</div>';
                            echo '<div class="flex-item">';
                            echo '<input type="number" name="content_type_weightage_full[' . esc_attr($type) . ']" value="1" min="0" class="weightage-input-full">';
                            echo '</div>';
                            echo '</div>';
                        }
                        ?>
                    </div>
                </td>
            </tr>
            <tr>
                <th><label for="schedule">Schedule</label></th>
                <td><input type="date" name="schedule" id="schedule" required></td>
            </tr>
            <tr>
                <th><label for="schedule_interval">Schedule Interval</label></th>
                <td>
                    <select name="schedule_interval" id="schedule_interval" required>
                        <option value="daily">Daily</option>
                        <option value="weekly">Weekly</option>
                        <option value="monthly">Monthly</option>
                        <option value="exact">Exact</option>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="language_full">Language</label></th>
                <td>
                    <select name="language_full" id="language_full" required>
                        <option value="English">English</option>
                        <option value="Spanish">Spanish (Español)</option>
                        <option value="French">French (Français)</option>
                        <option value="German">German (Deutsch)</option>
                        <option value="Chinese">Chinese (Simplified and Traditional) (中文)</option>
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
                        <option value="Tamil">Tamil</option> <!-- Retaining the original Tamil option -->
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="author">Author</label></th>
                <td>
                    <select name="author" id="author" required>
                        <option value="" disabled selected>Select your Author</option>
                        <option value="Random">Random</option>
                        <?php
                        $users = get_users();
                        foreach ($users as $user) {
                            echo '<option value="' . esc_attr($user->ID) . '">' . esc_html($user->display_name) . '</option>';
                        }
                        ?>
                    </select>
                </td>
            </tr>
            <tr id="user-weightage-row-full" style="display:none;">
                <th><label>User Weightage</label></th>
                <td>
                    <div class="flex-container">
                        <div class="flex-item">
                            <input type="checkbox" id="select-all-users-full"> Select All
                        </div>
                        <div class="flex-item">
                            <input type="checkbox" id="equal-weightage-users-full"> Equal Weightage
                        </div>
                    </div>
                    <div id="user-weightage-container-full">
                        <?php
                        foreach ($users as $user) {
                            echo '<div class="flex-container user-weightage-item">';
                            echo '<div class="flex-item">';
                            echo '<input type="checkbox" class="user-checkbox-full" name="user_selected[]" value="' . esc_attr($user->ID) . '">';
                            echo '<label>' . esc_html($user->display_name) . ':</label>';
                            echo '</div>';
                            echo '<div class="flex-item">';
                            echo '<input type="number" name="user_weightage_full[' . esc_attr($user->ID) . ']" value="1" min="0" class="weightage-input-full">';
                            echo '</div>';
                            echo '</div>';
                        }
                        ?>
                    </div>
                </td>
            </tr>
            <tr>
                <th><label for="post_to_social">Post to Social</label></th>
                <td>
                    <div id="post_to_social">
                        <div class="social-post-option" style="margin-right: 15px;">
                            <input type="checkbox" name="post_to_facebook" id="post_to_facebook" value="1">
                            <label for="post_to_facebook">
                                <img src="<?php echo esc_url(plugin_dir_url(dirname(__FILE__)) . 'assets/images/Facebook_f_logo_2019.svg'); ?>"
                                    alt="Facebook" style="width: 20px; vertical-align: middle; margin-right: 5px;">
                                Facebook
                            </label>
                        </div>
                        <div class="social-post-option" style="margin-right: 15px;">
                            <input type="checkbox" name="post_to_twitter" id="post_to_twitter" value="1">
                            <label for="post_to_twitter">
                                <img src="<?php echo esc_url(plugin_dir_url(dirname(__FILE__)) . 'assets/images/twitter_logo.png'); ?>"
                                    alt="Twitter" style="width: 20px; vertical-align: middle; margin-right: 5px;">
                                X (Twitter)
                            </label>
                        </div>
                        <div class="social-post-option">
                            <input type="checkbox" name="post_to_linkedin" id="post_to_linkedin" value="1">
                            <label for="post_to_linkedin">
                                <img src="<?php echo esc_url(plugin_dir_url(dirname(__FILE__)) . 'assets/images/LinkedIn_logo_initials.png'); ?>"
                                    alt="LinkedIn" style="width: 20px; vertical-align: middle; margin-right: 5px;">
                                LinkedIn
                            </label>
                        </div>
                    </div>
                </td>
            </tr>

            <tr>
                <th><label for="post_status">Post Status</label></th>
                <td>
                    <select name="post_status" id="post_status" required>
                        <option value="publish">Approved</option>
                        <option value="pending">Pending</option>
                    </select>
                </td>
            </tr>

            <tr id="admin_email_row" style="display:none;">
                <th><label for="admin_email">Admin Email for Notification</label></th>
                <td>
                    <input type="email" name="admin_email" id="admin_email" placeholder="Enter admin email">
                </td>
            </tr>

            <tr id="title_preview_row" style="display:none;">
    <th><label for="title_preview">Generated Titles Preview</label></th>
    <td>
        <!-- Use this div to dynamically insert checkboxes -->
        <div id="title_preview" style="width: 100%;"></div>
        <div id="remaining_articles_note" style="color: red; margin-top: 10px; font-weight: bold;"></div>
    </td>
</tr>

        </table>

        <a href="<?php echo esc_url(admin_url('edit.php?post_type=post')); ?>" class="button">View Posts</a>
        <button type="submit" id="generate_full_workflow" class="button button-primary">Generate Content without preview</button>
        <button type="button" id="preview_titles" class="button button-secondary">Preview Titles</button>
        <button type="button" id="generate_previewed_contents" class="button button-primary" style="display: none;">Generate Previewed Contents</button>

    </form>

    <!-- Automated Workflow Mode Form -->
    <form id="generate-automated-content-form" method="post" style="display: none;">
        <!-- Automated Workflow Mode -->
        <h3>Content Calendar is loading, Please wait......</h3>
         <table class="form-table" style="display: none">
        
        
            <tr>
                <th><label for="post_to_social">Post to Social</label></th>
                <td>
                    <div id="post_to_social_auto">
                        <div class="social-post-option" style="margin-right: 15px;">
                            <input type="checkbox" name="post_to_facebook" id="post_to_facebook_auto" value="1">
                            <label for="post_to_facebook_auto">
                                <img src="<?php echo esc_url(plugin_dir_url(dirname(__FILE__)) . 'assets/images/Facebook_f_logo_2019.svg'); ?>"
                                    alt="Facebook" style="width: 20px; vertical-align: middle; margin-right: 5px;">
                                Facebook
                            </label>
                        </div>
                        <div class="social-post-option" style="margin-right: 15px;">
                            <input type="checkbox" name="post_to_twitter" id="post_to_twitter_auto" value="1">
                            <label for="post_to_twitter_auto">
                                <img src="<?php echo esc_url(plugin_dir_url(dirname(__FILE__)) . 'assets/images/twitter_logo.png'); ?>"
                                    alt="Twitter" style="width: 20px; vertical-align: middle; margin-right: 5px;">
                                X (Twitter)
                            </label>
                        </div>
                        <div class="social-post-option">
                            <input type="checkbox" name="post_to_linkedin" id="post_to_linkedin_auto" value="1">
                            <label for="post_to_linkedin_auto">
                                <img src="<?php echo esc_url(plugin_dir_url(dirname(__FILE__)) . 'assets/images/LinkedIn_logo_initials.png'); ?>"
                                    alt="LinkedIn" style="width: 20px; vertical-align: middle; margin-right: 5px;">
                                LinkedIn
                            </label>
                        </div>
                    </div>
                </td>
            </tr>
            <tr>
                <th><label for="post_status_auto">Post Status</label></th>
                <td>
                    <select name="post_status_auto" id="post_status_auto" required>
                        <option value="publish">Publish</option>
                        <option value="pending">Pending</option>
                    </select>
                </td>
            </tr>
            <tr id="admin_email_row_auto" style="display:none;">
                <th><label for="admin_email">Admin Email for Notification</label></th>
                <td>
                    <input type="email" name="admin_email" id="admin_email_auto" placeholder="Enter admin email">
                </td>
            </tr>
        </table>

    </form>

    <form id="generate-super-automated-content-form" method="post" style="display: none;">
        <!-- Super Automated Workflow Mode -->
        <h3>Buyer's Journey Workflow Mode</h3>
        <table class="form-table">
            <tr>
                <th><label for="buyers_journey_super">Buyers Journey - Select Stage</label></th>
                <td>
                    <select name="buyers_journey_super" id="buyers_journey_super" required>
                        <option value="">Select Stage</option>
                        <option value="Full Journey">Full Journey - Complete Funnel</option>
                        <option value="Awareness">Awareness - Top of Funnel (TOFU)</option>
                        <option value="Consideration">Consideration - Middle of Funnel (MOFU)</option>
                        <option value="Decision">Decision - Bottom of Funnel (BOFU)</option>
                    </select>
                </td>
            </tr>
            <tr id="recommendation_row_super" style="display: none;">
                <th>Recommendation</th>
                <td id="recommendation_text_super"></td>
            </tr>
            <tr id="use_recommended_row_super" style="display: none;">
                <th></th>
                <td><button type="button" id="use_recommended_values_super" class="button">Use Recommended
                        Values</button></td>
            </tr>

            <tr>
                <th><label for="number_of_pieces_super">Number of Pieces</label></th>
                <td><input type="number" name="number_of_pieces_super" id="number_of_pieces_super" required></td>
            </tr>
            <tr>
                <th><label for="topic_super">Topic</label></th>
                <td><input type="text" name="topic_super" id="topic_super" required></td>
            </tr>
            <tr>
                <th><label for="links_super">Internal and External Links</label></th>
                <td>
                    <div id="links_super">
                        <div class="link-item_super">
                            <input type="text" name="link_text_super[]" placeholder="Anchor Text">
                            <input type="url" name="link_url_super[]" placeholder="URL">
                            <button type="button" class="remove-link button">Remove</button>
                        </div>
                    </div>
                    <button type="button" id="add-link-super" class="button">Add Link</button>
                </td>
            </tr>
            <tr>
                <th><label for="schedule_super">Schedule</label></th>
                <td><input type="date" name="schedule_super" id="schedule_super" required></td>
            </tr>
            <tr>
                <th><label for="schedule_interval_super">Schedule Interval</label></th>
                <td>
                    <select name="schedule_interval_super" id="schedule_interval_super" required>
                        <option value="daily">Daily</option>
                        <option value="weekly">Weekly</option>
                        <option value="monthly">Monthly</option>
                        <option value="exact">Exact</option>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="language_super">Language</label></th>
                <td>
                    <select name="language_super" id="language_super" required>
                        <option value="English">English</option>
                        <option value="Spanish">Spanish (Español)</option>
                        <option value="French">French (Français)</option>
                        <option value="German">German (Deutsch)</option>
                        <option value="Chinese">Chinese (Simplified and Traditional) (中文)</option>
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
                        <option value="Tamil">Tamil</option> <!-- Retaining the original Tamil option -->
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="author_super">Author</label></th>
                <td>
                    <select name="author_super" id="author_super" required>
                        <option value="" disabled selected>Select your Author</option>
                        <option value="Random">Random</option>
                        <?php
                        $users = get_users();
                        foreach ($users as $user) {
                            echo '<option value="' . esc_attr($user->ID) . '">' . esc_html($user->display_name) . '</option>';
                        }
                        ?>
                    </select>
                </td>
            </tr>
            <tr id="user-weightage-row-super" style="display:none;">
                <th><label>User Weightage</label></th>
                <td>
                    <div class="flex-container">
                        <div class="flex-item">
                            <input type="checkbox" id="select-all-users-super"> Select All
                        </div>
                        <div class="flex-item">
                            <input type="checkbox" id="equal-weightage-users-super"> Equal Weightage
                        </div>
                    </div>
                    <div id="user-weightage-container-super">
                        <?php
                        foreach ($users as $user) {
                            echo '<div class="flex-container user-weightage-item">';
                            echo '<div class="flex-item">';
                            echo '<input type="checkbox" class="user-checkbox-super" name="user_selected_super[]" value="' . esc_attr($user->ID) . '">';
                            echo '<label>' . esc_html($user->display_name) . ':</label>';
                            echo '</div>';
                            echo '<div class="flex-item">';
                            echo '<input type="number" name="user_weightage_super[' . esc_attr($user->ID) . ']" value="1" min="0" class="weightage-input-super">';
                            echo '</div>';
                            echo '</div>';
                        }
                        ?>
                    </div>
                </td>
            </tr>
            <tr>
                <th><label for="post_to_social">Post to Social</label></th>
                <td>
                    <div id="post_to_social_super">
                        <div class="social-post-option" style="margin-right: 15px;">
                            <input type="checkbox" name="post_to_facebook" id="post_to_facebook_super" value="1">
                            <label for="post_to_facebook_super">
                                <img src="<?php echo esc_url(plugin_dir_url(dirname(__FILE__)) . 'assets/images/Facebook_f_logo_2019.svg'); ?>"
                                    alt="Facebook" style="width: 20px; vertical-align: middle; margin-right: 5px;">
                                Facebook
                            </label>
                        </div>
                        <div class="social-post-option" style="margin-right: 15px;">
                            <input type="checkbox" name="post_to_twitter" id="post_to_twitter_super" value="1">
                            <label for="post_to_twitter_super">
                                <img src="<?php echo esc_url(plugin_dir_url(dirname(__FILE__)) . 'assets/images/twitter_logo.png'); ?>"
                                    alt="
                                    Twitter" style="width: 20px; vertical-align: middle; margin-right: 5px;">
                                X (Twitter)
                            </label>
                        </div>
                        <div class="social-post-option">
                            <input type="checkbox" name="post_to_linkedin" id="post_to_linkedin_super" value="1">
                            <label for="post_to_linkedin_super">
                                <img src="<?php echo esc_url(plugin_dir_url(dirname(__FILE__)) . 'assets/images/LinkedIn_logo_initials.png'); ?>"
                                    alt="LinkedIn" style="width: 20px; vertical-align: middle; margin-right: 5px;">
                                LinkedIn
                            </label>
                        </div>
                    </div>
                </td>
            </tr>
            <tr>
                <th><label for="post_status_super">Post Status</label></th>
                <td>
                    <select name="post_status_super" id="post_status_super" required>
                        <option value="publish">Publish</option>
                        <option value="pending">Pending</option>
                    </select>
                </td>
            </tr>
            <tr id="admin_email_row_super" style="display:none;">
                <th><label for="admin_email">Admin Email for Notification</label></th>
                <td>
                    <input type="email" name="admin_email" id="admin_email_super" placeholder="Enter admin email">
                </td>
            </tr>
        </table>

        <button type="submit" id="generate_super_automated" class="button button-primary">Generate Super Automated
            Content</button>
        <a href="<?php echo esc_url(admin_url('edit.php?post_type=post')); ?>" class="button">View Posts</a>
    </form>

    <form id="generate-industry-automated-content-form" method="post" style="display: none;">
        <!-- Industry Automated Workflow Mode -->
        <h3>Industry Workflow Mode</h3>
        <table class="form-table">
            <tr>
                <th><label for="buyers_journey_industry">Business Objectives</label></th>
                <td>
                    <select name="buyers_journey_industry" id="buyers_journey_industry" required>
                        <option value="">Select Goals</option>
                        <option value="Educate Customers">Educate Customers</option>
                        <option value="Enhance Customer Experience">Enhance Customer Experience</option>
                        <option value="Differentiate from Competitors">Differentiate from Competitors</option>
                        <option value="Acquire Customers">Acquire Customers</option>
                        <option value="Answer FAQs">Answer FAQs</option>
                    </select>
                </td>
            </tr>
            <tr id="recommendation_row_industry" style="display: none;">
                <th>Recommendation</th>
                <td id="recommendation_text_industry"></td>
            </tr>
            <tr id="use_recommended_row_industry" style="display: none;">
                <th></th>
                <td><button type="button" id="use_recommended_values_industry" class="button">Use Recommended
                        Values</button></td>
            </tr>

            <tr>
                <th><label for="number_of_pieces_industry">Number of Pieces</label></th>
                <td><input type="number" name="number_of_pieces_industry" id="number_of_pieces_industry" required></td>
            </tr>
            <tr>
                <th><label for="topic_industry">Topic</label></th>
                <td><input type="text" name="topic_industry" id="topic_industry" required></td>
            </tr>
            <tr>
                <th><label for="links_industry">Internal and External Links</label></th>
                <td>
                    <div id="links_industry">
                        <div class="link-item_industry">
                            <input type="text" name="link_text_industry[]" placeholder="Anchor Text">
                            <input type="url" name="link_url_industry[]" placeholder="URL">
                            <button type="button" class="remove-link button">Remove</button>
                        </div>
                    </div>
                    <button type="button" id="add-link-industry" class="button">Add Link</button>
                </td>
            </tr>
            <tr>
                <th><label for="schedule_industry">Schedule</label></th>
                <td><input type="date" name="schedule_industry" id="schedule_industry" required></td>
            </tr>
            <tr>
                <th><label for="schedule_interval_industry">Schedule Interval</label></th>
                <td>
                    <select name="schedule_interval_industry" id="schedule_interval_industry" required>
                        <option value="daily">Daily</option>
                        <option value="weekly">Weekly</option>
                        <option value="monthly">Monthly</option>
                        <option value="exact">Exact</option>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="language_industry">Language</label></th>
                <td>
                    <select name="language_industry" id="language_industry" required>
                        <option value="English">English</option>
                        <option value="Spanish">Spanish (Español)</option>
                        <option value="French">French (Français)</option>
                        <option value="German">German (Deutsch)</option>
                        <option value="Chinese">Chinese (Simplified and Traditional) (中文)</option>
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
                        <option value="Tamil">Tamil</option> <!-- Retaining the original Tamil option -->
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="author_industry">Author</label></th>
                <td>
                    <select name="author_industry" id="author_industry" required>
                        <option value="" disabled selected>Select your Author</option>
                        <?php
                        $users = get_users();
                        foreach ($users as $user) {
                            // Get the assigned industry for the user
                            $assigned_industry = get_user_meta($user->ID, '_assigned_industry', true);

                            // Only display users who have an assigned industry
                            if (!empty($assigned_industry)) {
                                // Display the user's name followed by the assigned industry
                                echo '<option value="' . esc_attr($user->ID) . '">' . esc_html($user->display_name . ' - ' . $assigned_industry) . '</option>';
                            }
                        }
                        ?>
                    </select>
                </td>
            </tr>
            <tr id="user-weightage-row-industry" style="display:none;">
                <th><label>User Weightage</label></th>
                <td>
                    <div class="flex-container">
                        <div class="flex-item">
                            <input type="checkbox" id="select-all-users-industry"> Select All
                        </div>
                        <div class="flex-item">
                            <input type="checkbox" id="equal-weightage-users-industry"> Equal Weightage
                        </div>
                    </div>
                    <div id="user-weightage-container-industry">
                        <?php
                        foreach ($users as $user) {
                            echo '<div class="flex-container user-weightage-item">';
                            echo '<div class="flex-item">';
                            echo '<input type="checkbox" class="user-checkbox-industry" name="user_selected_industry[]" value="' . esc_attr($user->ID) . '">';
                            echo '<label>' . esc_html($user->display_name) . ':</label>';
                            echo '</div>';
                            echo '<div class="flex-item">';
                            echo '<input type="number" name="user_weightage_industry[' . esc_attr($user->ID) . ']" value="1" min="0" class="weightage-input-super">';
                            echo '</div>';
                            echo '</div>';
                        }
                        ?>
                    </div>
                </td>
            </tr>
            <tr>
                <th><label for="post_to_social">Post to Social</label></th>
                <td>
                    <div id="post_to_social_industry">
                        <div class="social-post-option" style="margin-right: 15px;">
                            <input type="checkbox" name="post_to_facebook" id="post_to_facebook_industry" value="1">
                            <label for="post_to_facebook_industry">
                                <img src="<?php echo esc_url(plugin_dir_url(dirname(__FILE__)) . 'assets/images/Facebook_f_logo_2019.svg'); ?>"
                                    alt="Facebook" style="width: 20px; vertical-align: middle; margin-right: 5px;">
                                Facebook
                            </label>
                        </div>
                        <div class="social-post-option" style="margin-right: 15px;">
                            <input type="checkbox" name="post_to_twitter" id="post_to_twitter_industry" value="1">
                            <label for="post_to_twitter_industry">
                                <img src="<?php echo esc_url(plugin_dir_url(dirname(__FILE__)) . 'assets/images/twitter_logo.png'); ?>"
                                    alt="
                                    Twitter" style="width: 20px; vertical-align: middle; margin-right: 5px;">
                                X (Twitter)
                            </label>
                        </div>
                        <div class="social-post-option">
                            <input type="checkbox" name="post_to_linkedin" id="post_to_linkedin_industry" value="1">
                            <label for="post_to_linkedin_industry">
                                <img src="<?php echo esc_url(plugin_dir_url(dirname(__FILE__)) . 'assets/images/LinkedIn_logo_initials.png'); ?>"
                                    alt="LinkedIn" style="width: 20px; vertical-align: middle; margin-right: 5px;">
                                LinkedIn
                            </label>
                        </div>
                    </div>
                </td>
            </tr>
            <tr>
                <th><label for="post_status_industry">Post Status</label></th>
                <td>
                    <select name="post_status_industry" id="post_status_industry" required>
                        <option value="publish">Publish</option>
                        <option value="pending">Pending</option>
                    </select>
                </td>
            </tr>
            <tr id="admin_email_row_industry" style="display:none;">
                <th><label for="admin_email">Admin Email for Notification</label></th>
                <td>
                    <input type="email" name="admin_email" id="admin_email_industry" placeholder="Enter admin email">
                </td>
            </tr>
        </table>

        <button type="submit" id="generate_industry_automated" class="button button-primary">Generate industry Automated
            Content</button>
        <a href="<?php echo esc_url(admin_url('edit.php?post_type=post')); ?>" class="button">View Posts</a>
    </form>

    <div id="loading-message" style="display:none;">Loading...</div>



    <?php wp_nonce_field('generate_content_nonce', 'generate_content_nonce'); ?>
</div>

<?php
