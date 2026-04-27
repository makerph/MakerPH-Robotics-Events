<?php
/**
 * Plugin Name: MakerPH Robotics Competition Event Calendar
 * Description: Manage upcoming and recent robotics events Page.
 * Version: 1.1.2
 * Author: MakerPH Electronics
 */

if (!defined('ABSPATH')) exit;

// --- PLUGIN CONSTANTS ---
define('RCM_PLUGIN_NAME', 'MakerPH Robotics Competition Event Calendar');
define('RCM_PLUGIN_VERSION', '1.1.2');
define('RCM_PLUGIN_DESC', 'Manage upcoming and recent robotics events with automated WooCommerce integration.');

// 1. Register Custom Post Type
add_action('init', 'rcm_register_event_cpt');
function rcm_register_event_cpt() {
    register_post_type('robotics_event', [
        'labels' => ['name' => RCM_PLUGIN_NAME, 'singular_name' => 'Event'],
        'public' => true,
        'has_archive' => true,
        'publicly_queryable' => true,
        'menu_icon' => 'dashicons-performance',
        'supports' => ['title', 'editor', 'thumbnail'],
        'show_in_rest' => true,
    ]);
}

// 2. Admin Meta Boxes
add_action('add_meta_boxes', function() {
    add_meta_box('rcm_event_details', 'Event Details', 'rcm_event_meta_html', 'robotics_event', 'normal', 'high');
});

function rcm_event_meta_html($post) {
    $venue = get_post_meta($post->ID, '_event_venue', true);
    $event_date = get_post_meta($post->ID, '_event_date', true);
    $reg_date = get_post_meta($post->ID, '_reg_date', true);
    ?>
    <p><strong>Venue:</strong> <input type="text" name="event_venue" value="<?php echo esc_attr($venue); ?>" class="widefat"></p>
    <p><strong>Event Date:</strong> <input type="date" name="event_date" value="<?php echo esc_attr($event_date); ?>"></p>
    <p><strong>Registration Deadline:</strong> <input type="date" name="reg_date" value="<?php echo esc_attr($reg_date); ?>"></p>
    <?php
}

add_action('save_post', function($post_id) {
    if (isset($_POST['event_venue'])) update_post_meta($post_id, '_event_venue', $_POST['event_venue']);
    if (isset($_POST['event_date'])) update_post_meta($post_id, '_event_date', $_POST['event_date']);
    if (isset($_POST['reg_date'])) update_post_meta($post_id, '_reg_date', $_POST['reg_date']);
});

// 3. Show Event Meta on the Event Post Page
add_filter('the_content', 'rcm_append_event_info');
function rcm_append_event_info($content) {
    if (is_singular('robotics_event')) {
        $event_date = get_post_meta(get_the_ID(), '_event_date', true);
        $reg_date = get_post_meta(get_the_ID(), '_reg_date', true);
        $venue = get_post_meta(get_the_ID(), '_event_venue', true);

        $info_html = '<div class="rcm-event-card" style="background: #f8f9fa; padding: 20px; border-radius: 10px; border-left: 6px solid #222; margin-bottom: 25px;">';
        if ($event_date) $info_html .= '<strong>📅 Date:</strong> ' . date('F j, Y', strtotime($event_date)) . '<br>';
        if ($reg_date) $info_html .= '<strong>⏳ Registration Ends:</strong> ' . date('F j, Y', strtotime($reg_date)) . '<br>';
        if ($venue) $info_html .= '<strong>📍 Venue:</strong> ' . esc_html($venue);
        $info_html .= '</div>';

        return $info_html . $content;
    }
    return $content;
}

// 4. WooCommerce Hooks (Shop & My Account)
add_action('woocommerce_after_shop_loop', 'rcm_inject_to_woo');
add_action('woocommerce_account_dashboard', 'rcm_inject_to_woo');

function rcm_inject_to_woo() {
    echo '<div class="rcm-woo-section" style="clear:both; margin-top: 60px; padding-top: 30px; border-top: 1px solid #ddd;">';
    echo rcm_display_events();
    echo '</div>';
}

// 5. Shortcode and Rendering
add_shortcode('robotics_events', 'rcm_display_events');
function rcm_display_events() {
    $today = current_time('Y-m-d');
    $output = '<h2 style="text-transform: uppercase; letter-spacing: 1px;">Upcoming Events</h2>';
    $output .= rcm_render_table('upcoming', $today);
    $output .= '<h2 style="margin-top:50px; text-transform: uppercase; letter-spacing: 1px;">Past Competitions</h2>';
    $output .= rcm_render_table('recent', $today);
    return $output;
}

function rcm_render_table($type, $today) {
    $compare = ($type == 'upcoming') ? '>=' : '<';
    $args = [
        'post_type' => 'robotics_event',
        'posts_per_page' => 5,
        'meta_key' => '_event_date',
        'orderby' => 'meta_value',
        'order' => ($type == 'upcoming') ? 'ASC' : 'DESC',
        'meta_query' => [['key' => '_event_date', 'value' => $today, 'compare' => $compare, 'type' => 'DATE']]
    ];

    $query = new WP_Query($args);
    if (!$query->have_posts()) return '<p>No events to display at the moment.</p>';

    $html = '<div style="overflow-x:auto;"><table style="width:100%; border-collapse: collapse; margin-top: 15px;">';
    $html .= '<thead style="background:#000; color:#fff; text-align: left;"><tr>
                <th style="padding:15px;">Date</th>
                <th style="padding:15px;">Competition</th>
                <th style="padding:15px;">Poster</th>
                <th style="padding:15px;">Venue</th>
                ' . ($type == 'upcoming' ? '<th style="padding:15px;">Registration</th>' : '') . '
              </tr></thead><tbody>';

    while ($query->have_posts()) {
        $query->the_post();
        $id = get_the_ID();
        $img = get_the_post_thumbnail_url($id, 'thumbnail') ?: 'https://via.placeholder.com/60';
        
        $html .= '<tr style="border-bottom: 1px solid #eee;">';
        $html .= '<td style="padding:15px;">' . esc_html(get_post_meta($id, '_event_date', true)) . '</td>';
        $html .= '<td style="padding:15px;"><a href="'.get_permalink().'" style="color:#000; font-weight:bold;">' . get_the_title() . '</a></td>';
        $html .= '<td style="padding:15px;"><img src="'.$img.'" style="width:60px; border-radius:3px;"></td>';
        $html .= '<td style="padding:15px;">' . esc_html(get_post_meta($id, '_event_venue', true)) . '</td>';
        
        if ($type == 'upcoming') {
            $html .= '<td style="padding:15px;">' . esc_html(get_post_meta($id, '_reg_date', true)) . '</td>';
        }
        
        $html .= '</tr>';
    }
    $html .= '</tbody></table></div>';
    wp_reset_postdata();
    return $html;
}
