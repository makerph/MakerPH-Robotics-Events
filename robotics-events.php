<?php
/**
 * Plugin Name: MakerPH Robotics Competition Event Calendar
 * Description: Manage upcoming and recent robotics events with date range support.
 * Version: 1.1.6
 * Author: MakerPH Electronics
 */

if (!defined('ABSPATH')) exit;

// --- PLUGIN CONSTANTS ---
define('RCM_PLUGIN_NAME', 'MakerPH Robotics Competition Event Calendar');
define('RCM_PLUGIN_VERSION', '1.1.6');
define('RCM_PLUGIN_DESC', 'Manage upcoming and recent robotics events. Events also visible at User Dashboards');

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
    $start_date = get_post_meta($post->ID, '_event_date', true);
    $end_date = get_post_meta($post->ID, '_event_end_date', true);
    $reg_date = get_post_meta($post->ID, '_reg_date', true);
    ?>
    <p><strong>Venue:</strong> <input type="text" name="event_venue" value="<?php echo esc_attr($venue); ?>" class="widefat"></p>
    <div style="display: flex; gap: 20px;">
        <p><strong>Start Date:</strong><br><input type="date" name="event_date" value="<?php echo esc_attr($start_date); ?>"></p>
        <p><strong>End Date (Optional):</strong><br><input type="date" name="event_end_date" value="<?php echo esc_attr($end_date); ?>"></p>
    </div>
    <p><strong>Registration Deadline:</strong><br><input type="date" name="reg_date" value="<?php echo esc_attr($reg_date); ?>"></p>
    <p><small><i>Note: If it is a one-day event, you can leave End Date blank.</i></small></p>
    <?php
}

add_action('save_post', function($post_id) {
    if (isset($_POST['event_venue'])) update_post_meta($post_id, '_event_venue', $_POST['event_venue']);
    if (isset($_POST['event_date'])) update_post_meta($post_id, '_event_date', $_POST['event_date']);
    if (isset($_POST['event_end_date'])) update_post_meta($post_id, '_event_end_date', $_POST['event_end_date']);
    if (isset($_POST['reg_date'])) update_post_meta($post_id, '_reg_date', $_POST['reg_date']);
});

// 3. Helper function to format date ranges
function rcm_get_formatted_date_range($start, $end) {
    if (!$start) return '—';
    if (!$end || $start === $end) return date('M j, Y', strtotime($start));
    
    $s = strtotime($start);
    $e = strtotime($end);
    
    // If same month/year: "May 10 - 12, 2026"
    if (date('M Y', $s) === date('M Y', $e)) {
        return date('M j', $s) . ' – ' . date('j, Y', $e);
    }
    // Default: "May 30 - June 2, 2026"
    return date('M j', $s) . ' – ' . date('M j, Y', $e);
}

// 4. Show Event Meta on the Event Post Page
add_filter('the_content', 'rcm_append_event_info');
function rcm_append_event_info($content) {
    if (is_singular('robotics_event')) {
        $start = get_post_meta(get_the_ID(), '_event_date', true);
        $end = get_post_meta(get_the_ID(), '_event_end_date', true);
        $reg = get_post_meta(get_the_ID(), '_reg_date', true);
        $venue = get_post_meta(get_the_ID(), '_event_venue', true);

        $info_html = '<div class="rcm-event-card" style="background: #f8f9fa; padding: 20px; border-radius: 10px; border-left: 6px solid #222; margin-bottom: 25px;">';
        $info_html .= '<strong>📅 Date:</strong> ' . rcm_get_formatted_date_range($start, $end) . '<br>';
        if ($reg) $info_html .= '<strong>⏳ Registration Ends:</strong> ' . date('F j, Y', strtotime($reg)) . '<br>';
        if ($venue) $info_html .= '<strong>📍 Venue:</strong> ' . esc_html($venue);
        $info_html .= '</div>';

        return $info_html . $content;
    }
    return $content;
}

// 5. WooCommerce Hooks
add_action('woocommerce_after_shop_loop', 'rcm_inject_to_woo');
add_action('woocommerce_account_dashboard', 'rcm_inject_to_woo');

function rcm_inject_to_woo() {
    if ( is_front_page() || is_home() ) {
        return;
    }
    echo '<div class="rcm-woo-section" style="clear:both; margin-top: 60px; padding-top: 30px; border-top: 1px solid #ddd;">';
    echo rcm_display_events();
    echo '</div>';
}

// 6. Shortcode and Rendering
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
    $args = [
        'post_type' => 'robotics_event',
        'posts_per_page' => 10,
        'meta_key' => '_event_date',
        'orderby' => 'meta_value',
        'order' => ($type == 'upcoming') ? 'ASC' : 'DESC',
    ];

    if ($type == 'upcoming') {
        $args['meta_query'] = [
            'relation' => 'OR',
            ['key' => '_event_date', 'value' => $today, 'compare' => '>=', 'type' => 'DATE'],
            ['key' => '_event_end_date', 'value' => $today, 'compare' => '>=', 'type' => 'DATE']
        ];
    } else {
        $args['meta_query'] = [
            'relation' => 'AND',
            ['key' => '_event_date', 'value' => $today, 'compare' => '<', 'type' => 'DATE'],
            ['relation' => 'OR',
                ['key' => '_event_end_date', 'value' => $today, 'compare' => '<', 'type' => 'DATE'],
                ['key' => '_event_end_date', 'value' => '', 'compare' => '=']
            ]
        ];
    }

    $query = new WP_Query($args);
    if (!$query->have_posts()) return '<p style="font-size: 14px; color: #666;">No events to display.</p>';

    $html = '<div style="overflow-x:auto;"><table style="width:100%; border-collapse: collapse; margin-top: 10px; font-size: 14px; table-layout: fixed;">';
    
    // FIXED WIDTH HEADER: Ensures both tables look identical
    $html .= '<thead style="background:#000; color:#fff; text-align: left;"><tr>
                <th style="padding:8px 12px; font-size: 13px; font-weight: 600; width: 18%;">Date</th>
                <th style="padding:8px 12px; font-size: 13px; font-weight: 600; width: 30%;">Competition</th>
                <th style="padding:8px 12px; font-size: 13px; font-weight: 600; width: 12%;">Poster</th>
                <th style="padding:8px 12px; font-size: 13px; font-weight: 600; width: 22%;">Venue</th>
                <th style="padding:8px 12px; font-size: 13px; font-weight: 600; width: 18%;">Registration Ends</th>
              </tr></thead><tbody>';

    while ($query->have_posts()) {
        $query->the_post();
        $id = get_the_ID();
        $start = get_post_meta($id, '_event_date', true);
        $end = get_post_meta($id, '_event_end_date', true);
        $reg = get_post_meta($id, '_reg_date', true);
        $img = get_the_post_thumbnail_url($id, 'thumbnail') ?: 'https://via.placeholder.com/50';
        
        $html .= '<tr style="border-bottom: 1px solid #eee;">';
        $html .= '<td style="padding:10px 12px; word-wrap: break-word;">' . rcm_get_formatted_date_range($start, $end) . '</td>';
        $html .= '<td style="padding:10px 12px; word-wrap: break-word;"><a href="'.get_permalink().'" style="color:#000; font-weight:bold; text-decoration: none;">' . get_the_title() . '</a></td>';
        $html .= '<td style="padding:10px 12px;"><img src="'.$img.'" style="width:50px; height: auto; border-radius:3px; display: block;"></td>';
        $html .= '<td style="padding:10px 12px; word-wrap: break-word;">' . esc_html(get_post_meta($id, '_event_venue', true)) . '</td>';
        $html .= '<td style="padding:10px 12px; word-wrap: break-word;">' . ($reg ? date('M j, Y', strtotime($reg)) : '—') . '</td>';
        $html .= '</tr>';
    }
    $html .= '</tbody></table></div>';
    wp_reset_postdata();
    return $html;
}
