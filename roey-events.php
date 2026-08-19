<?php
/*
Plugin Name: Roey Events
Description: Event post type, automatic import and showcase display for upcoming shows.
Version: 1.6.0
Author: Site Admin
*/

if (!defined('ABSPATH')) {
    exit;
}

define('RLE_VERSION', '1.6.0');
define('RLE_URL', plugin_dir_url(__FILE__));
define('RLE_PATH', plugin_dir_path(__FILE__));

add_action('init', 'rle_register_event_post_type');
add_action('add_meta_boxes', 'rle_add_event_meta_box');
add_action('save_post_rle_event', 'rle_save_event_meta', 10, 2);
add_action('admin_enqueue_scripts', 'rle_admin_assets');
add_action('wp_enqueue_scripts', 'rle_front_assets');
add_action('wp_ajax_rle_fetch_event', 'rle_ajax_fetch_event');
add_filter('manage_rle_event_posts_columns', 'rle_event_columns');
add_action('manage_rle_event_posts_custom_column', 'rle_event_column_content', 10, 2);
add_filter('manage_edit-rle_event_sortable_columns', 'rle_event_sortable_columns');
add_action('pre_get_posts', 'rle_event_admin_orderby');
add_shortcode('roey_events', 'rle_events_shortcode');
add_shortcode('roey_events_showcase', 'rle_events_shortcode');
add_action('admin_menu', 'rle_add_settings_page');

register_activation_hook(__FILE__, 'rle_activate');

function rle_activate() {
    rle_register_event_post_type();
    flush_rewrite_rules();
}

function rle_register_event_post_type() {
    $labels = [
        'name' => 'אירועים',
        'singular_name' => 'אירוע',
        'menu_name' => 'אירועים',
        'add_new' => 'הוסף אירוע',
        'add_new_item' => 'הוסף אירוע חדש',
        'edit_item' => 'עריכת אירוע',
        'new_item' => 'אירוע חדש',
        'view_item' => 'צפייה באירוע',
        'search_items' => 'חיפוש אירועים',
        'not_found' => 'לא נמצאו אירועים',
        'not_found_in_trash' => 'לא נמצאו אירועים בפח',
        'all_items' => 'כל האירועים',
    ];

    register_post_type('rle_event', [
        'labels' => $labels,
        'public' => true,
        'show_ui' => true,
        'show_in_menu' => true,
        'show_in_rest' => true,
        'has_archive' => false,
        'rewrite' => ['slug' => 'event'],
        'menu_icon' => 'dashicons-calendar-alt',
        'supports' => ['title', 'thumbnail'],
        'capability_type' => 'post',
        'map_meta_cap' => true,
    ]);
}

function rle_add_settings_page() {
    add_submenu_page(
        'edit.php?post_type=rle_event',
        'הגדרות תצוגה',
        'הגדרות תצוגה',
        'manage_options',
        'rle-settings',
        'rle_render_settings_page'
    );
}

function rle_get_settings() {
    $defaults = [
        'hero_title' => 'רועי לוי',
        'board_title' => 'לוח הופעות',
        'hero_image' => '',
        'button_text' => 'רכישת כרטיסים',
        'sold_out_text' => 'אזלו הכרטיסים',
        'empty_text' => 'אין כרגע הופעות קרובות.',
    ];

    $settings = get_option('rle_settings', []);
    if (!is_array($settings)) {
        $settings = [];
    }

    return wp_parse_args($settings, $defaults);
}

function rle_render_settings_page() {
    if (!current_user_can('manage_options')) {
        return;
    }

    if (isset($_POST['rle_settings_nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['rle_settings_nonce'])), 'rle_save_settings')) {
        $settings = [
            'hero_title' => isset($_POST['hero_title']) ? sanitize_text_field(wp_unslash($_POST['hero_title'])) : '',
            'board_title' => isset($_POST['board_title']) ? sanitize_text_field(wp_unslash($_POST['board_title'])) : '',
            'hero_image' => isset($_POST['hero_image']) ? esc_url_raw(wp_unslash($_POST['hero_image'])) : '',
            'button_text' => isset($_POST['button_text']) ? sanitize_text_field(wp_unslash($_POST['button_text'])) : '',
            'sold_out_text' => isset($_POST['sold_out_text']) ? sanitize_text_field(wp_unslash($_POST['sold_out_text'])) : '',
            'empty_text' => isset($_POST['empty_text']) ? sanitize_text_field(wp_unslash($_POST['empty_text'])) : '',
        ];
        update_option('rle_settings', $settings);
        echo '<div class="notice notice-success is-dismissible"><p>ההגדרות נשמרו.</p></div>';
    }

    $settings = rle_get_settings();
    ?>
    <div class="wrap" dir="rtl">
        <h1>הגדרות תצוגת האירועים</h1>
        <form method="post">
            <?php wp_nonce_field('rle_save_settings', 'rle_settings_nonce'); ?>
            <table class="form-table" role="presentation">
                <tbody>
                    <tr>
                        <th scope="row"><label for="board_title">כותרת הלוח</label></th>
                        <td><input name="board_title" id="board_title" type="text" class="regular-text" value="<?php echo esc_attr($settings['board_title']); ?>"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="hero_image">לינק לתמונה הראשית המלאה</label></th>
                        <td><input name="hero_image" id="hero_image" type="url" class="large-text" value="<?php echo esc_attr($settings['hero_image']); ?>"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="button_text">טקסט כפתור</label></th>
                        <td><input name="button_text" id="button_text" type="text" class="regular-text" value="<?php echo esc_attr($settings['button_text']); ?>"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="sold_out_text">טקסט ללא לינק</label></th>
                        <td><input name="sold_out_text" id="sold_out_text" type="text" class="regular-text" value="<?php echo esc_attr($settings['sold_out_text']); ?>"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="empty_text">טקסט כשאין הופעות</label></th>
                        <td><input name="empty_text" id="empty_text" type="text" class="regular-text" value="<?php echo esc_attr($settings['empty_text']); ?>"></td>
                    </tr>
                </tbody>
            </table>
            <?php submit_button('שמור הגדרות'); ?>
        </form>
        <p><strong>Shortcode:</strong> <code>[roey_events]</code></p>
        <p><strong>עם ערכים ידניים:</strong> <code>[roey_events board_title="לוח הופעות" hero_image="https://example.com/hero.jpg"]</code></p>
    </div>
    <?php
}

function rle_add_event_meta_box() {
    add_meta_box(
        'rle_event_details',
        'פרטי האירוע',
        'rle_render_event_meta_box',
        'rle_event',
        'normal',
        'high'
    );
}

function rle_render_event_meta_box($post) {
    wp_nonce_field('rle_save_event', 'rle_event_nonce');

    $source_url = get_post_meta($post->ID, '_rle_source_url', true);
    $date = get_post_meta($post->ID, '_rle_date', true);
    $time = get_post_meta($post->ID, '_rle_time', true);
    $location = get_post_meta($post->ID, '_rle_location', true);
    $venue = get_post_meta($post->ID, '_rle_venue', true);
    $ticket_url = get_post_meta($post->ID, '_rle_ticket_url', true);
    $ticket_status = get_post_meta($post->ID, '_rle_ticket_status', true);
    if (!in_array($ticket_status, ['normal', 'last_tickets', 'sold_out'], true)) {
        $ticket_status = 'normal';
    }
    ?>
    <div class="rle-admin-grid">
        <div class="rle-admin-field rle-admin-field-wide">
            <label for="rle_source_url">לינק לעמוד האירוע</label>
            <div class="rle-admin-url-row">
                <input type="url" id="rle_source_url" name="rle_source_url" value="<?php echo esc_attr($source_url); ?>" placeholder="https://admin.comedyclub.co.il/event/...">
                <button type="button" class="button button-primary" id="rle-fetch-event">משוך מידע מהלינק</button>
            </div>
            <div id="rle-fetch-status"></div>
        </div>
        <div class="rle-admin-field">
            <label for="rle_date">תאריך</label>
            <input type="date" id="rle_date" name="rle_date" value="<?php echo esc_attr($date); ?>">
        </div>
        <div class="rle-admin-field">
            <label for="rle_time">שעה</label>
            <input type="time" id="rle_time" name="rle_time" value="<?php echo esc_attr($time); ?>">
        </div>
        <div class="rle-admin-field">
            <label for="rle_location">עיר</label>
            <input type="text" id="rle_location" name="rle_location" value="<?php echo esc_attr($location); ?>" placeholder="כפר סבא">
        </div>
        <div class="rle-admin-field">
            <label for="rle_venue">מקום</label>
            <input type="text" id="rle_venue" name="rle_venue" value="<?php echo esc_attr($venue); ?>" placeholder="היכל התרבות כפר סבא">
        </div>
        <div class="rle-admin-field rle-admin-field-wide">
            <label for="rle_ticket_url">לינק לרכישת כרטיסים</label>
            <input type="url" id="rle_ticket_url" name="rle_ticket_url" value="<?php echo esc_attr($ticket_url); ?>" placeholder="https://...">
        </div>
        <div class="rle-admin-field rle-admin-field-wide">
            <label>סטטוס כרטיסים</label>
            <div class="rle-admin-statuses">
                <label class="rle-admin-status">
                    <input type="radio" name="rle_ticket_status" value="normal" <?php checked($ticket_status, 'normal'); ?>>
                    <span>רגיל</span>
                </label>
                <label class="rle-admin-status rle-admin-status--last">
                    <input type="radio" name="rle_ticket_status" value="last_tickets" <?php checked($ticket_status, 'last_tickets'); ?>>
                    <span>כרטיסים אחרונים</span>
                </label>
                <label class="rle-admin-status rle-admin-status--sold">
                    <input type="radio" name="rle_ticket_status" value="sold_out" <?php checked($ticket_status, 'sold_out'); ?>>
                    <span>SOLD OUT</span>
                </label>
            </div>
        </div>
    </div>
    <?php
}

function rle_save_event_meta($post_id, $post) {
    if (!isset($_POST['rle_event_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['rle_event_nonce'])), 'rle_save_event')) {
        return;
    }

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    $old_source_url = get_post_meta($post_id, '_rle_source_url', true);
    $source_url = isset($_POST['rle_source_url']) ? esc_url_raw(wp_unslash($_POST['rle_source_url'])) : '';
    $date = isset($_POST['rle_date']) ? sanitize_text_field(wp_unslash($_POST['rle_date'])) : '';
    $time = isset($_POST['rle_time']) ? sanitize_text_field(wp_unslash($_POST['rle_time'])) : '';
    $location = isset($_POST['rle_location']) ? sanitize_text_field(wp_unslash($_POST['rle_location'])) : '';
    $venue = isset($_POST['rle_venue']) ? sanitize_text_field(wp_unslash($_POST['rle_venue'])) : '';
    $ticket_status = isset($_POST['rle_ticket_status']) ? sanitize_key(wp_unslash($_POST['rle_ticket_status'])) : 'normal';
    if (!in_array($ticket_status, ['normal', 'last_tickets', 'sold_out'], true)) {
        $ticket_status = 'normal';
    }
    $imported_title = get_post_meta($post_id, '_rle_imported_title', true);

    update_post_meta($post_id, '_rle_source_url', $source_url);
    update_post_meta($post_id, '_rle_date', $date);
    update_post_meta($post_id, '_rle_time', $time);
    update_post_meta($post_id, '_rle_location', $location);
    update_post_meta($post_id, '_rle_venue', $venue);
    update_post_meta($post_id, '_rle_ticket_status', $ticket_status);

    if ($source_url) {
        update_post_meta($post_id, '_rle_ticket_url', $source_url);
    } else {
        delete_post_meta($post_id, '_rle_ticket_url');
    }

    $should_fetch = $source_url && (
        $source_url !== $old_source_url ||
        !$imported_title ||
        !$date ||
        !$time ||
        !$location ||
        !$venue
    );

    if ($should_fetch) {
        $data = rle_fetch_event_data($source_url);

        if (!is_wp_error($data)) {
            if (!empty($data['title'])) {
                $imported_title = sanitize_text_field($data['title']);
                update_post_meta($post_id, '_rle_imported_title', $imported_title);
            }

            if (!$date && !empty($data['date'])) {
                $date = sanitize_text_field($data['date']);
                update_post_meta($post_id, '_rle_date', $date);
            }

            if (!$time && !empty($data['time'])) {
                $time = sanitize_text_field($data['time']);
                update_post_meta($post_id, '_rle_time', $time);
            }

            if (!$location && !empty($data['location'])) {
                $location = sanitize_text_field($data['location']);
                update_post_meta($post_id, '_rle_location', $location);
            }

            if (!$venue && !empty($data['venue'])) {
                $venue = sanitize_text_field($data['venue']);
                update_post_meta($post_id, '_rle_venue', $venue);
            }
        }
    }

    rle_update_event_title($post_id, $imported_title, $source_url);
}

function rle_update_event_title($post_id, $imported_title = '', $source_url = '') {
    $date = get_post_meta($post_id, '_rle_date', true);
    $location = trim((string) get_post_meta($post_id, '_rle_location', true));
    $venue = trim((string) get_post_meta($post_id, '_rle_venue', true));

    if (!$imported_title) {
        $imported_title = trim((string) get_post_meta($post_id, '_rle_imported_title', true));
    }

    if (!$source_url) {
        $source_url = trim((string) get_post_meta($post_id, '_rle_source_url', true));
    }

    $title = '';
    $formatted_date = '';

    if ($date) {
        $timestamp = strtotime($date . ' 12:00:00');
        $formatted_date = $timestamp ? wp_date('d.m.Y', $timestamp) : $date;
    }

    if ($formatted_date && $location) {
        $title = $formatted_date . ' - ' . $location;
    } elseif ($formatted_date && $venue) {
        $title = $formatted_date . ' - ' . $venue;
    } elseif ($imported_title) {
        $title = $imported_title;
    } elseif ($source_url) {
        $title = rle_title_from_url($source_url);
    } elseif ($formatted_date) {
        $title = 'אירוע - ' . $formatted_date;
    }

    $title = trim(wp_strip_all_tags((string) $title));

    if (!$title) {
        return;
    }

    $current_title = trim((string) get_post_field('post_title', $post_id));

    if ($current_title === $title) {
        return;
    }

    remove_action('save_post_rle_event', 'rle_save_event_meta', 10);
    wp_update_post([
        'ID' => $post_id,
        'post_title' => $title,
        'post_name' => sanitize_title($title),
    ]);
    add_action('save_post_rle_event', 'rle_save_event_meta', 10, 2);
}

function rle_title_from_url($url) {
    $path = trim((string) wp_parse_url($url, PHP_URL_PATH), '/');

    if (!$path) {
        return 'אירוע';
    }

    $segments = array_values(array_filter(explode('/', $path)));
    $slug = $segments ? urldecode((string) end($segments)) : '';
    $slug = preg_replace('/[-_](?:\d{1,2})[-_](?:\d{1,2})(?:[-_]\d{2,4})?$/u', '', $slug);
    $slug = str_replace(['-', '_'], ' ', $slug);
    $slug = preg_replace('/\s+/u', ' ', $slug);
    $slug = trim($slug);

    if (!$slug && count($segments) > 1) {
        $slug = urldecode((string) $segments[count($segments) - 2]);
        $slug = str_replace(['-', '_'], ' ', $slug);
    }

    return $slug ?: 'אירוע';
}

function rle_admin_assets($hook) {
    $screen = get_current_screen();
    if (!$screen || $screen->post_type !== 'rle_event') {
        return;
    }

    wp_enqueue_script('rle-admin', RLE_URL . 'assets/admin.js', ['jquery'], RLE_VERSION, true);
    wp_localize_script('rle-admin', 'RLEAdmin', [
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('rle_fetch_event'),
    ]);

    wp_register_style('rle-admin', false, [], RLE_VERSION);
    wp_enqueue_style('rle-admin');
    wp_add_inline_style('rle-admin', '.rle-admin-grid{display:grid;grid-template-columns:1fr 1fr;gap:18px;padding:8px 0}.rle-admin-field{display:flex;flex-direction:column;gap:7px}.rle-admin-field-wide{grid-column:1/-1}.rle-admin-field label{font-weight:600}.rle-admin-field input[type=text],.rle-admin-field input[type=url],.rle-admin-field input[type=date],.rle-admin-field input[type=time]{width:100%;min-height:40px}.rle-admin-url-row{display:flex;gap:10px;align-items:center}.rle-admin-url-row input{flex:1}#rle-fetch-status{margin-top:8px;font-weight:600}.rle-status-success{color:#008a20}.rle-status-error{color:#b32d2e}.rle-admin-statuses{display:flex;flex-wrap:wrap;gap:10px}.rle-admin-status{position:relative;display:inline-flex;cursor:pointer}.rle-admin-status input{position:absolute;opacity:0;pointer-events:none}.rle-admin-status span{display:inline-flex;align-items:center;justify-content:center;min-height:42px;padding:0 18px;border:1px solid #c3c4c7;border-radius:7px;background:#fff;font-weight:700}.rle-admin-status input:checked+span{border-color:#2271b1;background:#2271b1;color:#fff}.rle-admin-status--last input:checked+span{border-color:#dba617;background:#f1c54a;color:#111}.rle-admin-status--sold input:checked+span{border-color:#8f1d1d;background:#a62d2d;color:#fff}@media(max-width:782px){.rle-admin-grid{grid-template-columns:1fr}.rle-admin-field-wide{grid-column:auto}.rle-admin-url-row{align-items:stretch;flex-direction:column}}');
}

function rle_front_assets() {
    wp_register_style('rle-events', RLE_URL . 'assets/events.css', [], RLE_VERSION);
    wp_register_script('rle-events', RLE_URL . 'assets/events.js', [], RLE_VERSION, true);
}

function rle_ajax_fetch_event() {
    check_ajax_referer('rle_fetch_event', 'nonce');

    if (!current_user_can('edit_posts')) {
        wp_send_json_error(['message' => 'אין הרשאה לבצע את הפעולה.'], 403);
    }

    $url = isset($_POST['url']) ? esc_url_raw(wp_unslash($_POST['url'])) : '';
    if (!$url) {
        wp_send_json_error(['message' => 'יש להזין לינק לאירוע.'], 400);
    }

    $data = rle_fetch_event_data($url);
    if (is_wp_error($data)) {
        wp_send_json_error(['message' => $data->get_error_message()], 400);
    }

    $data['ticket_url'] = $url;

    wp_send_json_success($data);
}

function rle_fetch_event_data($url, $depth = 0) {
    if ($depth > 1 || !wp_http_validate_url($url)) {
        return new WP_Error('invalid_url', 'לינק האירוע אינו תקין.');
    }

    $response = wp_safe_remote_get($url, [
        'timeout' => 18,
        'redirection' => 5,
        'user-agent' => 'Mozilla/5.0 WordPress Event Importer',
        'headers' => [
            'Accept-Language' => 'he-IL,he;q=0.9,en;q=0.8',
        ],
        'limit_response_size' => 2500000,
    ]);

    if (is_wp_error($response)) {
        return $response;
    }

    $code = wp_remote_retrieve_response_code($response);
    if ($code < 200 || $code >= 400) {
        return new WP_Error('fetch_failed', 'לא ניתן לקרוא את עמוד האירוע.');
    }

    $html = wp_remote_retrieve_body($response);
    if (!$html) {
        return new WP_Error('empty_page', 'עמוד האירוע חזר ללא תוכן.');
    }

    $data = [
        'title' => '',
        'date' => '',
        'time' => '',
        'location' => '',
        'venue' => '',
        'ticket_url' => $url,
    ];

    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);
    libxml_clear_errors();
    $xpath = new DOMXPath($dom);

    foreach ($xpath->query('//script[@type="application/ld+json"]') as $node) {
        $json = trim($node->textContent);
        if (!$json) {
            continue;
        }
        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            continue;
        }
        $event = rle_find_event_schema($decoded);
        if (!$event) {
            continue;
        }
        if (!empty($event['name'])) {
            $data['title'] = wp_strip_all_tags((string) $event['name']);
        }
        if (!empty($event['startDate'])) {
            $parsed = rle_parse_event_datetime((string) $event['startDate']);
            if ($parsed) {
                $data['date'] = $parsed['date'];
                $data['time'] = $parsed['time'];
            }
        }
        if (!empty($event['location'])) {
            $location_data = rle_schema_location($event['location']);
            $data['venue'] = $location_data['venue'];
            $data['location'] = $location_data['city'];
        }
        if (!empty($event['offers'])) {
            $offer_url = rle_schema_offer_url($event['offers']);
            if ($offer_url) {
                $data['ticket_url'] = $offer_url;
            }
        }
        break;
    }

    if (!$data['title']) {
        $og_title = rle_meta_content($xpath, 'property', 'og:title');
        if (!$og_title) {
            $og_title = rle_meta_content($xpath, 'name', 'twitter:title');
        }
        if ($og_title) {
            $data['title'] = wp_strip_all_tags($og_title);
        } else {
            $title_nodes = $xpath->query('//title');
            if ($title_nodes->length) {
                $data['title'] = trim(wp_strip_all_tags($title_nodes->item(0)->textContent));
            }
        }
    }

    $body_text = preg_replace('/\s+/u', ' ', wp_strip_all_tags($html));

    if (!$data['date']) {
        $data['date'] = rle_extract_date_from_text($body_text);
    }

    if (!$data['date']) {
        $data['date'] = rle_extract_date_from_url($url);
    }

    if (!$data['time']) {
        $data['time'] = rle_extract_time_from_text($body_text);
    }

    if (!$data['location']) {
        $data['location'] = rle_extract_location_from_text($body_text);
    }

    if (!$data['location'] && $data['title']) {
        $data['location'] = rle_extract_location_from_title($data['title']);
    }

    if (!$data['venue']) {
        $data['venue'] = rle_extract_venue_from_text($body_text);
    }

    if ($depth === 0 && (!$data['date'] || !$data['time'] || !$data['location'] || !$data['venue'])) {
        foreach ($xpath->query('//iframe[@src]') as $iframe) {
            $iframe_url = trim($iframe->getAttribute('src'));
            if (!$iframe_url) {
                continue;
            }
            $iframe_url = rle_absolute_url($iframe_url, $url);
            if (!$iframe_url || !wp_http_validate_url($iframe_url)) {
                continue;
            }
            $iframe_data = rle_fetch_event_data($iframe_url, 1);
            if (is_wp_error($iframe_data)) {
                continue;
            }
            foreach (['title', 'date', 'time', 'location', 'venue'] as $key) {
                if (!$data[$key] && !empty($iframe_data[$key])) {
                    $data[$key] = $iframe_data[$key];
                }
            }
            if (!empty($iframe_data['ticket_url'])) {
                $data['ticket_url'] = $iframe_data['ticket_url'];
            }
            break;
        }
    }

    $data['title'] = sanitize_text_field($data['title']);
    $data['date'] = sanitize_text_field($data['date']);
    $data['time'] = sanitize_text_field($data['time']);
    $data['location'] = sanitize_text_field($data['location']);
    $data['venue'] = sanitize_text_field($data['venue']);
    $data['ticket_url'] = esc_url_raw($data['ticket_url']);

    return $data;
}

function rle_find_event_schema($value) {
    if (!is_array($value)) {
        return null;
    }

    if (isset($value['@type'])) {
        $types = is_array($value['@type']) ? $value['@type'] : [$value['@type']];
        foreach ($types as $type) {
            if (is_string($type) && strtolower($type) === 'event') {
                return $value;
            }
        }
    }

    foreach ($value as $item) {
        if (is_array($item)) {
            $found = rle_find_event_schema($item);
            if ($found) {
                return $found;
            }
        }
    }

    return null;
}

function rle_parse_event_datetime($value) {
    try {
        $date = new DateTime($value);
        $date->setTimezone(wp_timezone());
        return [
            'date' => $date->format('Y-m-d'),
            'time' => $date->format('H:i'),
        ];
    } catch (Exception $e) {
        return null;
    }
}

function rle_schema_location($location) {
    $response = [
        'venue' => '',
        'city' => '',
    ];

    if (is_string($location)) {
        $response['venue'] = $location;
        return $response;
    }

    if (!is_array($location)) {
        return $response;
    }

    if (!empty($location['name'])) {
        $response['venue'] = sanitize_text_field((string) $location['name']);
    }

    if (!empty($location['address'])) {
        if (is_string($location['address'])) {
            $response['city'] = sanitize_text_field((string) $location['address']);
        } elseif (is_array($location['address'])) {
            foreach (['addressLocality', 'addressRegion', 'streetAddress'] as $key) {
                if (!empty($location['address'][$key])) {
                    $response['city'] = sanitize_text_field((string) $location['address'][$key]);
                    break;
                }
            }
        }
    }

    return $response;
}

function rle_schema_offer_url($offers) {
    if (is_array($offers) && isset($offers['url']) && is_string($offers['url'])) {
        return esc_url_raw($offers['url']);
    }

    if (is_array($offers)) {
        foreach ($offers as $offer) {
            if (is_array($offer) && !empty($offer['url']) && is_string($offer['url'])) {
                return esc_url_raw($offer['url']);
            }
        }
    }

    return '';
}

function rle_meta_content($xpath, $attribute, $value) {
    $nodes = $xpath->query('//meta[translate(@' . $attribute . ', "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz")="' . strtolower($value) . '"]/@content');
    if ($nodes->length) {
        return trim($nodes->item(0)->nodeValue);
    }
    return '';
}

function rle_extract_date_from_text($text) {
    $patterns = [
        '/(?:תאריך|מתי|התחלה|date|start)\s*[:\-]?\s*(\d{1,2})[\.\/\-](\d{1,2})[\.\/\-](\d{4})/iu',
        '/\b(\d{1,2})[\.\/](\d{1,2})[\.\/](\d{4})\b/u',
        '/\b(20\d{2})-(\d{2})-(\d{2})\b/u',
    ];

    foreach ($patterns as $index => $pattern) {
        if (!preg_match($pattern, $text, $match)) {
            continue;
        }
        if ($index === 2) {
            $year = (int) $match[1];
            $month = (int) $match[2];
            $day = (int) $match[3];
        } else {
            $day = (int) $match[1];
            $month = (int) $match[2];
            $year = (int) $match[3];
        }
        if (checkdate($month, $day, $year)) {
            return sprintf('%04d-%02d-%02d', $year, $month, $day);
        }
    }

    return '';
}

function rle_extract_date_from_url($url) {
    $path = (string) wp_parse_url($url, PHP_URL_PATH);
    if (!preg_match('/(?:^|[-_\/])(\d{2})-(\d{2})(?:[-_\/]|$)/', $path, $match)) {
        return '';
    }

    $day = (int) $match[1];
    $month = (int) $match[2];
    $year = (int) wp_date('Y');
    if (!checkdate($month, $day, $year)) {
        return '';
    }

    $candidate = sprintf('%04d-%02d-%02d', $year, $month, $day);
    if ($candidate < wp_date('Y-m-d')) {
        $year++;
    }

    if (!checkdate($month, $day, $year)) {
        return '';
    }

    return sprintf('%04d-%02d-%02d', $year, $month, $day);
}

function rle_extract_time_from_text($text) {
    $patterns = [
        '/(?:התחלה|שעה|דלתות|time|starts?|begin)\s*[:\-]?\s*([01]?\d|2[0-3]):([0-5]\d)/iu',
        '/\b([01]?\d|2[0-3]):([0-5]\d)\b/u',
    ];

    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $text, $match)) {
            return sprintf('%02d:%02d', (int) $match[1], (int) $match[2]);
        }
    }

    return '';
}

function rle_extract_location_from_title($title) {
    $title = trim(wp_strip_all_tags((string) $title));

    if (!$title || !preg_match('/[א-ת]/u', $title)) {
        return '';
    }

    $patterns = [
        '/!\s*([^|–—]{2,60})$/u',
        '/\|\s*([^|]{2,60})$/u',
        '/[–—]\s*([^–—]{2,60})$/u',
    ];

    foreach ($patterns as $pattern) {
        if (!preg_match($pattern, $title, $match)) {
            continue;
        }

        $value = trim($match[1], " \t\n\r\0\x0B-–—|!");

        if ($value && preg_match('/[א-ת]/u', $value) && mb_strlen($value) <= 60) {
            return $value;
        }
    }

    return '';
}

function rle_extract_location_from_text($text) {
    $patterns = [
        '/(?:מיקום|איפה|location|city)\s*[:\-]?\s*([^|•\n\r]{2,90})/iu',
    ];

    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $text, $match)) {
            $value = trim($match[1]);
            $value = preg_split('/\s{2,}|(?:תאריך|מתי|התחלה|מחיר|כרטיס)/u', $value)[0];
            return trim($value, " \t\n\r\0\x0B:,-");
        }
    }

    return '';
}

function rle_extract_venue_from_text($text) {
    $patterns = [
        '/(?:אולם|מקום|venue)\s*[:\-]?\s*([^|•\n\r]{2,120})/iu',
    ];

    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $text, $match)) {
            $value = trim($match[1]);
            $value = preg_split('/\s{2,}|(?:תאריך|מתי|התחלה|מחיר|כרטיס)/u', $value)[0];
            return trim($value, " \t\n\r\0\x0B:,-");
        }
    }

    return '';
}

function rle_absolute_url($url, $base) {
    if (preg_match('#^https?://#i', $url)) {
        return $url;
    }

    if (strpos($url, '//') === 0) {
        $scheme = wp_parse_url($base, PHP_URL_SCHEME) ?: 'https';
        return $scheme . ':' . $url;
    }

    $scheme = wp_parse_url($base, PHP_URL_SCHEME);
    $host = wp_parse_url($base, PHP_URL_HOST);
    if (!$scheme || !$host) {
        return '';
    }

    if (strpos($url, '/') === 0) {
        return $scheme . '://' . $host . $url;
    }

    $path = wp_parse_url($base, PHP_URL_PATH) ?: '/';
    $dir = trailingslashit(dirname($path));
    return $scheme . '://' . $host . $dir . ltrim($url, '/');
}

function rle_event_columns($columns) {
    $new = [];
    foreach ($columns as $key => $label) {
        $new[$key] = $label;
        if ($key === 'title') {
            $new['rle_date'] = 'תאריך';
            $new['rle_time'] = 'שעה';
            $new['rle_location'] = 'עיר';
            $new['rle_venue'] = 'מקום';
        }
    }
    return $new;
}

function rle_event_column_content($column, $post_id) {
    if ($column === 'rle_date') {
        $date = get_post_meta($post_id, '_rle_date', true);
        if ($date) {
            $timestamp = strtotime($date . ' 12:00:00');
            echo esc_html(wp_date('d.m.Y', $timestamp));
        }
    }
    if ($column === 'rle_time') {
        echo esc_html(get_post_meta($post_id, '_rle_time', true));
    }
    if ($column === 'rle_location') {
        echo esc_html(get_post_meta($post_id, '_rle_location', true));
    }
    if ($column === 'rle_venue') {
        echo esc_html(get_post_meta($post_id, '_rle_venue', true));
    }
}

function rle_event_sortable_columns($columns) {
    $columns['rle_date'] = 'rle_date';
    return $columns;
}

function rle_event_admin_orderby($query) {
    if (!is_admin() || !$query->is_main_query() || $query->get('post_type') !== 'rle_event') {
        return;
    }

    if ($query->get('orderby') === 'rle_date') {
        $query->set('meta_key', '_rle_date');
        $query->set('orderby', 'meta_value');
    }
}

function rle_get_upcoming_events($limit) {
    $query = new WP_Query([
        'post_type' => 'rle_event',
        'post_status' => 'publish',
        'posts_per_page' => $limit,
        'meta_query' => [
            'date_clause' => [
                'key' => '_rle_date',
                'value' => wp_date('Y-m-d'),
                'compare' => '>=',
                'type' => 'DATE',
            ],
        ],
        'orderby' => [
            'date_clause' => 'ASC',
            'title' => 'ASC',
        ],
        'no_found_rows' => true,
    ]);

    $events = [];

    while ($query->have_posts()) {
        $query->the_post();
        $post_id = get_the_ID();
        $events[] = [
            'id' => $post_id,
            'title' => get_the_title(),
            'date' => get_post_meta($post_id, '_rle_date', true),
            'time' => get_post_meta($post_id, '_rle_time', true),
            'location' => get_post_meta($post_id, '_rle_location', true),
            'venue' => get_post_meta($post_id, '_rle_venue', true),
            'ticket_url' => get_post_meta($post_id, '_rle_ticket_url', true),
            'ticket_status' => get_post_meta($post_id, '_rle_ticket_status', true) ?: 'normal',
        ];
    }

    wp_reset_postdata();

    usort($events, static function ($a, $b) {
        return strcmp(($a['date'] ?: '') . ' ' . ($a['time'] ?: ''), ($b['date'] ?: '') . ' ' . ($b['time'] ?: ''));
    });

    return $events;
}

function rle_day_short_hebrew($timestamp) {
    $map = [
        'Sun' => 'א׳',
        'Mon' => 'ב׳',
        'Tue' => 'ג׳',
        'Wed' => 'ד׳',
        'Thu' => 'ה׳',
        'Fri' => 'ו׳',
        'Sat' => 'ש׳',
    ];

    $key = wp_date('D', $timestamp);
    return isset($map[$key]) ? $map[$key] : '';
}

function rle_events_shortcode($atts) {
    $settings = rle_get_settings();

    $atts = shortcode_atts([
        'board_title' => $settings['board_title'],
        'hero_image' => $settings['hero_image'],
        'button_text' => $settings['button_text'],
        'sold_out_text' => $settings['sold_out_text'],
        'empty_text' => $settings['empty_text'],
        'limit' => '-1',
        'section_id' => 'rle-events-' . wp_generate_password(6, false, false),
    ], $atts, 'roey_events');

    $limit = (int) $atts['limit'];
    if ($limit === 0) {
        $limit = -1;
    }

    $events = rle_get_upcoming_events($limit);

    wp_enqueue_style('rle-events');
    wp_enqueue_script('rle-events');

    ob_start();
    ?>
    <section class="rle-showcase" id="<?php echo esc_attr($atts['section_id']); ?>" dir="rtl">
        <div class="rle-showcase__sticky">
            <div class="rle-showcase__hero">
                <?php if ($atts['hero_image']) : ?>
                    <img class="rle-showcase__image" src="<?php echo esc_url($atts['hero_image']); ?>" alt="" loading="eager" decoding="async">
                <?php endif; ?>
                <div class="rle-showcase__shade"></div>
            </div>
        </div>
        <div class="rle-showcase__flow">
            <div class="rle-showcase__panel" data-rle-panel>
                <div class="rle-showcase__panel-inner">
                    <h2 class="rle-showcase__panel-title"><img src="/wp-content/uploads/2026/08/6eb728ab-4769-4d37-83ef-123d6b7dffed-e1786716607132.png" ></h2>
                    <?php if (!$events) : ?>
                        <div class="rle-showcase__empty"><?php echo esc_html($atts['empty_text']); ?></div>
                    <?php else : ?>
                        <div class="rle-showcase__list">
                            <?php foreach ($events as $event) :
                                $timestamp = $event['date'] ? strtotime($event['date'] . ' 12:00:00') : false;
                                $day_num = $timestamp ? wp_date('d', $timestamp) : '';
                                $month_num = $timestamp ? wp_date('m', $timestamp) : '';
                                $day_name = $timestamp ? rle_day_short_hebrew($timestamp) : '';
                                $location_line = trim($event['location']);
                                $venue_line = trim($event['venue']);
                                ?>
                                <article class="rle-row">
                                    <div class="rle-row__action">
                                        <?php if ($event['ticket_status'] === 'sold_out') : ?>
                                            <span class="rle-row__button rle-row__button--disabled">SOLD OUT</span>
                                        <?php elseif ($event['ticket_url']) : ?>
                                            <a class="rle-row__button" href="<?php echo esc_url($event['ticket_url']); ?>" data-rle-popup-url="<?php echo esc_url($event['ticket_url']); ?>"><?php echo esc_html($atts['button_text']); ?></a>
                                        <?php else : ?>
                                            <span class="rle-row__button rle-row__button--disabled"><?php echo esc_html($atts['sold_out_text']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="rle-row__details">
                                        <div class="rle-row__place-wrap">
                                            <?php if ($location_line) : ?>
                                                <div class="rle-row__place"><?php echo esc_html($location_line); ?></div>
                                            <?php endif; ?>
                                            <?php if ($event['ticket_status'] === 'sold_out') : ?>
                                                <span class="rle-row__badge rle-row__badge--sold">SOLD OUT</span>
                                            <?php elseif ($event['ticket_status'] === 'last_tickets') : ?>
                                                <span class="rle-row__badge rle-row__badge--last">כרטיסים אחרונים</span>
                                            <?php endif; ?>
                                        </div>
                                        <?php if ($venue_line) : ?>
                                            <div class="rle-row__meta">
                                                <span class="rle-row__venue"><?php echo esc_html($venue_line); ?></span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="rle-row__date">
                                        <div class="rle-row__date-main"><?php echo esc_html($day_num . '.' . $month_num); ?></div>
                                        <div class="rle-row__date-sub">
                                            <?php if ($day_name) : ?>
                                                <span>יום <?php echo esc_html($day_name); ?></span>
                                            <?php endif; ?>
                                            <?php if ($event['time']) : ?>
                                                <span><?php echo esc_html($event['time']); ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="rle-popup" data-rle-popup aria-hidden="true">
            <div class="rle-popup__backdrop" data-rle-popup-close></div>
            <div class="rle-popup__dialog" role="dialog" aria-modal="true" aria-label="רכישת כרטיסים">
                <button type="button" class="rle-popup__close" data-rle-popup-close aria-label="סגירה">×</button>
                <div class="rle-popup__loader" data-rle-popup-loader>טוען...</div>
                <iframe class="rle-popup__iframe" data-rle-popup-iframe src="about:blank" title="רכישת כרטיסים" allow="payment *; fullscreen *" referrerpolicy="strict-origin-when-cross-origin"></iframe>
            </div>
        </div>
    </section>
    <?php
    return ob_get_clean();
}