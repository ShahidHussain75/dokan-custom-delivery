<?php
 
/**
* Plugin Name: Dokan Custom Addon For Food delivery
* Plugin URI: https://github.com/ShahidHussain75
* Description: Custom functionality for Dokan including grouped products support
* Version: 1.0.0
* Author: Shahid Hussain
* Author URI: https://github.com/ShahidHussain75
* Text Domain: dokan-custom
* Requires at least: 5.8
* Requires PHP: 7.2
*/
 
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}
 
function themeslug_enqueue_style() {
    wp_enqueue_style( 'my-css', plugins_url( 'style.css', __FILE__ ), false );
    // Add cart nonce
    wp_localize_script('jquery', 'myAjax', array(
        'cartNonce' => wp_create_nonce('woocommerce-cart')
    ));
   
}
 
add_action( 'wp_enqueue_scripts', 'themeslug_enqueue_style' );


/**
 * Display products in chunks for lazy loading
 */
function display_products_chunk($tag, $seller_id, $page = 1, $per_page = 2, $search = '') {
    $product_args = [
        'post_type'      => 'product',
        'posts_per_page' => $per_page,
        'paged'          => $page,
        'author'         => $seller_id,
        's'              => $search,
        'tax_query'      => [[
            'taxonomy' => 'product_tag',
            'field'    => 'id',
            'terms'    => $tag->term_id,
      ]],
        'orderby'        => 'menu_order title', // Respect backend sorting
        'order'          => 'ASC',
    ];

        // If we're searching, remove the tax query
    if (!empty($search)) {
        unset($product_args['tax_query']);
    }
    $product_query = new WP_Query($product_args);
    $total_pages = $product_query->max_num_pages;
    $has_more = ($page < $total_pages);

    ob_start();
    if ($product_query->have_posts()) {
        while ($product_query->have_posts()) {
            $product_query->the_post();
            $product = wc_get_product(get_the_ID());
            $price_html = get_product_price_html($product);
            $description = $product->get_description();
			$words = explode(' ', strip_tags($description)); // remove HTML and split into words
			$short_description = implode(' ', array_slice($words, 0, 2)) . '...';

echo '<div class="product-card">
    <a href="#" class="quick-view-button" data-product-id="' . get_the_ID() . '">
        <div class="title_same">
            <h3 class="product-name">' . get_the_title() . '</h3>
            <p class="product-price">' . $price_html . '</p>';

if (!empty($description)) {
    echo '<p class="description-1">' . $short_description . '</p>';
}

echo '      </div>
        <div class="main_light">
            <img src="' . get_the_post_thumbnail_url() . '" alt="' . get_the_title() . '" class="product-image">
            <button class="add-button">
                <img src="' . content_url('/uploads/2025/03/Rectangle-47.png') . '" alt="Add to Cart">
            </button>
        </div>
    </a>
</div>';
        }
        wp_reset_postdata();
    }
    $html = ob_get_clean();

    return ['html' => $html, 'has_more' => $has_more];
}
/**
 * AJAX handler for lazy loading products
 */
add_action('wp_ajax_load_more_products', 'load_more_products');
add_action('wp_ajax_nopriv_load_more_products', 'load_more_products');

function load_more_products() {
    error_log('AJAX load_more_products called.'); // Check if function is hit

    check_ajax_referer('dokan-custom', 'security');
    
    $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
    $tag_id = isset($_POST['tag_id']) ? intval($_POST['tag_id']) : 0;
    $vendor_id = isset($_POST['vendor_id']) ? intval($_POST['vendor_id']) : 0;

    $tag = get_term($tag_id, 'product_tag');
    error_log("Page: $page, Tag ID: $tag_id, Vendor ID: $vendor_id");

    if (!$tag || is_wp_error($tag)) {
        wp_send_json_error('Invalid tag');
    }

    $result = display_products_chunk($tag, $vendor_id, $page, 4);
    error_log('Result from display_products_chunk: ' . print_r($result, true));

    error_log('Tag object: ' . print_r($tag, true));

    wp_send_json([
        'success' => true,
        'html' => $result['html'],
        'has_more' => $result['has_more'],
        'new_page' => $page + 1
    ]);
    wp_die();
}

/**
 * AJAX handler for loading product modal
 */
add_action('wp_ajax_load_product_modal', 'load_product_modal');
add_action('wp_ajax_nopriv_load_product_modal', 'load_product_modal');

function load_product_modal() {
    check_ajax_referer('woocommerce-cart', '_wpnonce');
    
    if (!isset($_POST['product_id'])) {
        wp_send_json_error(['message' => 'Invalid request']);
        return;
    }
    
    $product_id = intval($_POST['product_id']);
    $product = wc_get_product($product_id);
    
    if (!$product) {
        wp_send_json_error(['message' => 'Product not found']);
        return;
    }

    ob_start();
    $description = $product->get_description();
    // Get price HTML
    $price_html = $product->get_price_html();
    ?>
    <div class="popup-product-image loading-element">
        <img 
        src="<?php echo get_the_post_thumbnail_url($product_id); ?>" 
        alt="<?php echo esc_attr($product->get_name()); ?>" 
        class="product-image" 
        onload="this.parentNode.classList.add('loaded'); this.parentNode.classList.remove('loading-element');"
        onerror="this.parentNode.classList.add('loaded'); this.parentNode.classList.remove('loading-element');"/>
    </div>
    <div class="popup-product-details-container"> 
    <div class="popup-product-details">
        <h2 class="loading-element"><?php echo esc_html($product->get_name()); ?></h2>
        <p class="price loading-element"><?php echo $price_html; ?></p>
        <p class="description"> <?php echo $description; ?></p>
        <?php 
        // Variations
        if ($product->is_type('variable')) {
            echo '<div class="variable-grouped-products loading-element">';
            echo get_variations_html($product);
            echo '</div>';
        }
        
        // Product addons
        echo '<div class="product-addons-container loading-element">';
        echo get_product_addons_html($product_id);
        echo '</div>';
        ?>
        </div>
        <div class="add-to-cart-container loading-element">
            <div class="quantity-controls">
                <div class="quantity-wrapper">
                    <button type="button" class="minus-qty">-</button>
                    <input type="number" class="qty-input" value="1" min="1" max="99">
                    <button type="button" class="plus-qty">+</button>
                </div>
            </div>
            <?php
if ( $product->is_type('variable') ) {
    $available_variations = $product->get_available_variations();
    $first_variation = reset($available_variations); // Get the first variation
    $variation_id = $first_variation['variation_id'];
    $variation_obj = wc_get_product($variation_id);
    $price_html = $variation_obj->get_price_html(); // Get formatted price (with currency symbol)
} 
// Fallback to product price or 0
else if ( empty( $price_html ) ) {
    $price_html = $product->get_price_html();

    if ( empty( $price_html ) ) {
        $price_html = wc_price( 0 ); // Show €0.00 (or your currency)
    }
}
else {
    $price_html = $product->get_price_html();
}
?>

<button class="add-to-cart-popup" data-product-id="<?php echo esc_attr($product->get_id()); ?>">
    <?php echo $price_html; ?>
</button>
        </div>
    </div>
    <?php
    
    $modal_html = ob_get_clean();
    
    // Add a small delay to simulate loading for better UX
    usleep(200000); // 200ms delay
    
    wp_send_json_success(['modal_html' => $modal_html]);
}
/**
 * Helper function to get product price HTML
 */
function get_product_price_html($product) {
   if ($product->is_type('variable')) {
        $variations = $product->get_available_variations();
        $min_price = $product->get_variation_price('min');
        $max_price = $product->get_variation_price('max');
        $min_regular_price = $product->get_variation_regular_price('min');
        $max_regular_price = $product->get_variation_regular_price('max');
        
        // Check if any variation is on sale
        $is_on_sale = $product->is_on_sale();
        
        if ($is_on_sale) {
            // Format with sale price styling
            if ($min_price === $max_price) {
                // Single price point
                $regular_price = ($min_regular_price === $max_regular_price) ? 
                    wc_price($min_regular_price) : 
                    wc_price($min_regular_price) . ' - ' . wc_price($max_regular_price);
                    
                return '<del>' . $regular_price . '</del> <span style="color: #e74c3c; text-decoration: underline;">' . wc_price($min_price) . '</span>';
            } else {
                // Price range
                $regular_range = wc_price($min_regular_price) . ' - ' . wc_price($max_regular_price);
                $sale_range = wc_price($min_price) . ' - ' . wc_price($max_price);
                
                return '<del>' . $regular_range . '</del> <span style="color: #e74c3c; text-decoration: underline;">' . $sale_range . '</span>';
            }
        } else {
            // Regular price formatting
            if ($min_price === $max_price) {
                return wc_price($min_price);
            } else {
                return wc_price($min_price) . ' - ' . wc_price($max_price);
            }
        }
    } else {
        // For simple products, use default WooCommerce formatting
        return $product->get_price_html();
    }
}

/**
 * Helper function to get variations html
 */
function get_variations_html($product) {
    ob_start();
    
    $variations = $product->get_available_variations();
    if (!empty($variations)) {
        echo '<h4>Variante auswählen:</h4>';
        echo '<div class="variation-checkboxes">';
        
        // Find the lowest price variation
        $lowest_price = PHP_FLOAT_MAX;
        $lowest_price_variation_id = null;

        foreach ($variations as $variation) {
            $variation_price = floatval($variation['display_price']);
            if ($variation_price < $lowest_price) {
                $lowest_price = $variation_price;
                $lowest_price_variation_id = $variation['variation_id'];
            }
        }
        
        echo '<!-- Selected Lowest Price Variation ID: ' . $lowest_price_variation_id . ' -->';

        foreach ($variations as $variation) {
            $variation_obj = wc_get_product($variation['variation_id']);
            $variation_name = implode(', ', $variation['attributes']);
            $regular_price = $variation['display_regular_price'];
            $sale_price = $variation['display_price'];
            
            // Check if this is the lowest price variation
            $is_lowest_price = ($variation['variation_id'] == $lowest_price_variation_id);
            
            $is_checked = $is_lowest_price ? ' checked="checked"' : '';
            
             echo '<div class="variation-item' . ($is_lowest_price ? ' selected' : '') . '">
                <label class="checkbox-container product-addon-item variation-addon-item">
                    <input type="radio" 
						name="variation_id" 
						value="' . esc_attr($variation['variation_id']) . '"
						data-price="' . esc_attr($sale_price) . '" 
						data-regular-price="' . esc_attr($regular_price) . '"
						data-sale-price="' . esc_attr($sale_price) . '"
						class="variation-radio product-addon-radio" 
						data-is-default="' . ($is_lowest_price ? 'true' : 'false') . '"' . $is_checked . '>
  					<span class="checkmark"></span>
                    <span class="variation-name addon-name">' . esc_html($variation_name) . '</span>';
            
            if ($sale_price < $regular_price) {
                echo '<span class="variation-price addon-price"><del>' . wc_price($regular_price) . '</del> ' . wc_price($sale_price) . '</span>';
            } else {
                echo '<span class="variation-price addon-price">' . wc_price($regular_price) . '</span>';
            }
            
            echo '</label>
                </div>';
        }
        
        echo '</div>';
    }
    
    return ob_get_clean();
}

/**
 * Helper function to get product addons HTML
 */
function get_product_addons_html($product_id) {
    ob_start();
    
    $product_addons = get_post_meta($product_id, '_product_addons', true);
    
    if (!empty($product_addons) && is_array($product_addons)) {
        foreach ($product_addons as $group_index => $addon_group) {
            // Check if the group is valid and has a name (or is a heading)
            if (empty($addon_group['name']) && (!isset($addon_group['type']) || $addon_group['type'] !== 'heading')) {
                continue; // Skip invalid groups unless it's a heading
            }
            
            // Handle headings separately if they don't have options but you want to display them
            if (isset($addon_group['type']) && $addon_group['type'] === 'heading') {
                echo '<div class="product-addons-group">';
                echo '<h4 class="addon-group-title">' . esc_html($addon_group['name']) . '</h4>';
                echo '</div>';
                continue; // Skip to the next group
            }
            
            $base_input_name = 'addon-' . $product_id . '-' . $group_index;
            
            $is_required = !empty($addon_group['required']) ? "1" : '';
            $required_label = !empty($addon_group['required'])
                            ? '<span class="required-label" style="color:red; font-size: 0.7em; margin-left: 8px;">(Required)</span>'
                            : '';
            
            echo '<div class="product-addons-group">';
            echo '<h4 class="addon-group-title">' . esc_html($addon_group['name']) . $required_label . '</h4>';
            echo '<div class="product-addons-options">';
            
            // Use a switch to handle different input types
            switch ($addon_group['type']) {
                case 'checkbox':
                    // Checkboxes typically have multiple options
                    if (!empty($addon_group['options']) && is_array($addon_group['options'])) {
                        foreach ($addon_group['options'] as $option_index => $option) {
                            // Ensure option is visible if visibility is set
                            if (isset($option['visibility']) && $option['visibility'] === 0) {
                                continue;
                            }
                            
                            $price = isset($option['price']) ? floatval($option['price']) : 0;
                            $option_label = isset($option['label']) ? esc_html($option['label']) : '';
 						    $price_type = isset($option['price_type']) ? $option['price_type'] : 'flat';  // 'flat' or 'percentage'
                           
                            // The value attribute should be the option's value or label string, as plugin expects this in $_POST
                            $label = isset($option['value']) && $option['value'] !== '' ? esc_attr($option['value']) : $option_label;
                            $input_value_attribute = strtolower(str_replace(' ', '-', $label));
                            
                            // Correct Checkbox name format: addon-<product_id>-<group_index>[]
                            $input_name = $base_input_name;
                            $input_id = $base_input_name . '-' . $option_index; // Unique ID for label linking
                            
                            if ($price_type === 'percentage_based') {
								// Get the product price (you'll need to get this from your product object)
								$product_price = wc_get_product($product_id)->get_price(); // Assuming $product is available
								// Or if you have the product ID: $product_price = wc_get_product($product_id)->get_price();

								// Calculate the actual addon price based on percentage
								$actual_addon_price = ($product_price * $price) / 100;
								$price_display = wc_price($actual_addon_price); // Show calculated price with currency
							} else {
								$price_display = wc_price($price); // Normal currency format
							}


                            echo '<label class="product-addon-item">';
                            echo '<input type="checkbox"
                                    id="' . esc_attr($input_id) . '"
                                    class="product-addon-checkbox" ' . ($is_required ? 'required' : '') . '
                                    name="' . esc_attr($input_name) . '"
                                    value="' . $input_value_attribute . '"
                                    data-price="' . esc_attr($price) . '"
                                    data-price-type="' . esc_attr($price_type) . '" 
                                    data-required="' . esc_attr($is_required) . '">'
                                                                                     ;
                            
                            echo '<span class="addon-name">' . $option_label . '</span>';
                            if ($price > 0) {
                                echo '<span class="addon-price">+' . $price_display . '</span>';
                            }
                            echo '</label>';
                        }
                    }
                    break;
                
                case 'multiple_choice': // Add case for radio button (assuming display is radiobutton)
                    // Multiple choice with options, often rendered as radio buttons
                    if (!empty($addon_group['options']) && is_array($addon_group['options'])) {
                        foreach ($addon_group['options'] as $option_index => $option) {
                            // Ensure option is visible if visibility is set
                            if (isset($option['visibility']) && $option['visibility'] === 0) {
                                continue;
                            }
                            
                            $price = isset($option['price']) ? floatval($option['price']) : 0;
                            $option_label = isset($option['label']) ? esc_html($option['label']) : '';
                            $price_type = isset($option['price_type']) ? $option['price_type'] : 'flat';  // 'flat' or 'percentage'
                         
                            // The value attribute should be the option's value or label string
                            $label = isset($option['value']) && $option['value'] !== '' ? esc_attr($option['value']) : $option_label;
                            $input_value_attribute = strtolower(str_replace(' ', '-', $label));
                            
                            // Correct Radio button name format: addon-<product_id>-<group_index> (NO [])
                            $input_name = $base_input_name;
                            $input_id = $base_input_name . '-' . $option_index; // Unique ID
                            
 							if ($price_type === 'percentage_based') {
								// Get the product price (you'll need to get this from your product object)
								$product_price = wc_get_product($product_id)->get_price(); // Assuming $product is available
								// Or if you have the product ID: $product_price = wc_get_product($product_id)->get_price();

								// Calculate the actual addon price based on percentage
								$actual_addon_price = ($product_price * $price) / 100;
								$price_display = wc_price($actual_addon_price); // Show calculated price with currency
							} else {
								$price_display = wc_price($price); // Normal currency format
							}


                            echo '<label class="product-addon-item">';
                            echo '<input type="radio"
                                    id="' . esc_attr($input_id) . '"
                                    class="product-addon-radio" ' . ($is_required ? 'required' : '') . '
                                    name="' . esc_attr($input_name) . '"
                                    value="' . $input_value_attribute . '"
                                    data-price="' . esc_attr($price) . '"
 									data-price-type="' . esc_attr($price_type) . '" 
                                    data-required="' . esc_attr($is_required) . '">';
                            
                            echo '<span class="addon-name">' . $option_label . '</span>';
                            if ($price > 0) {
                                echo '<span class="addon-price">+' . $price_display . '</span>';
                            }
                            echo '</label>';
                        }
                    }
                    break;
                    
                default:
                    break;
            }
            
            echo '</div></div>';
        }
    }
    
    return ob_get_clean();
}


/**
 * Custom Dokan Seller Profile Enhancement
 */

// Add the custom content after seller profile frame
add_action('dokan_store_profile_frame_after', 'custom_content_after_seller_profile', 10, 2);

function custom_content_after_seller_profile($store_user_data, $store_info) {
    $store_user = get_user_by('id', $store_user_data->ID);
    $profile_img_class = 'some-custom-class';
    
    // Get the rating data properly
    $rating = dokan_get_readable_seller_rating($store_user->ID);
    if (is_string($rating)) {
        // If rating is returned as a string (old format), convert it to array format
        $rating = array(
            'rating' => 0,
            'count' => 0
        );
    }

    // Ensure rating is in correct format
    if (!is_array($rating)) {
        $rating = array(
            'rating' => 0,
            'count' => 0
        );
    }
    
    $store_slug = dokan_get_store_url($store_user->ID);
    $reviews_url = trailingslashit($store_slug) . 'reviews/';
    $dokan_store_times = !empty($store_info['dokan_store_time']) ? $store_info['dokan_store_time'] : [];
    $current_time = dokan_current_datetime();
    $today = strtolower($current_time->format('l')); // Get current day
    

    $allowed_days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
    $opening_time = '';
    $closing_time = '';
    $first_open_day = '';
    $last_open_day = '';
    $has_found_first = false;
   
echo '<div class="main_part">';
echo '<div class="main_vendor_prf_container">';
    // Profile section with two columns layout
  echo '<div class="main_vendor_prf">
        <div class="points">
            <div class="profile-img ' . esc_attr($profile_img_class) . '">
                <img src="' . esc_url(get_avatar_url($store_user->ID)) . '" 
                     alt="' . esc_attr($store_user->display_name) . '" 
                     width="150" height="150">
            </div>';

     // Store timing section
// echo '<div class="store-timing-below-logo">';

// // Initialize variables to track timing groups
// $timing_groups = [];
// $current_group = null;
// $group_days = [];
// $prev_opening_time = '';
// $prev_closing_time = '';

// // Loop through each allowed day
// foreach ($allowed_days as $day) {
//     // Check if day has valid timing
//     if (isset($dokan_store_times[$day]) && 
//         !empty($dokan_store_times[$day]['opening_time']) && 
//         !empty($dokan_store_times[$day]['closing_time'])) {
        
//         $opening_time = $dokan_store_times[$day]['opening_time'][0];
//         $closing_time = $dokan_store_times[$day]['closing_time'][0];

//         // Create a key for this timing
//         $timing_key = 'open_' . md5($opening_time . $closing_time);

//     } else {
//         // If no timings, mark as closed
//         $timing_key = 'closed';
//         $opening_time = '';
//         $closing_time = '';
//     }

//     // Start new group if timing changed
//     if ($timing_key != $current_group) {
//         if (!empty($current_group)) {
//             // Save the previous group
//             $timing_groups[] = [
//                 'days' => $group_days,
//                 'opening_time' => $prev_opening_time,
//                 'closing_time' => $prev_closing_time,
//                 'is_closed' => ($current_group === 'closed')
//             ];
//         }
//         // Start new group
//         $current_group = $timing_key;
//         $group_days = [$day];
//         $prev_opening_time = $opening_time;
//         $prev_closing_time = $closing_time;
//     } else {
//         // Add day to current group
//         $group_days[] = $day;
//     }
// }

// // Add the last group if exists
// if (!empty($current_group)) {
//     $timing_groups[] = [
//         'days' => $group_days,
//         'opening_time' => $prev_opening_time,
//         'closing_time' => $prev_closing_time,
//         'is_closed' => ($current_group === 'closed')
//     ];
// }

// // Function to format day names
// function format_day_range($days) {
//     $german_abbr = [
//         'monday' => 'Mo',
//         'tuesday' => 'Di',
//         'wednesday' => 'Mi',
//         'thursday' => 'Do',
//         'friday' => 'Fr',
//         'saturday' => 'Sa',
//         'sunday' => 'So'
//     ];
//     if (count($days) == 1) {
//         return $german_abbr[strtolower($days[0])] ?? ucfirst(substr($days[0], 0, 3));
//     }
//     // Check if days are consecutive
//     $day_order = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
//     $day_indices = array_map(function($day) use ($day_order) {
//         return array_search(strtolower($day), $day_order);
//     }, $days);
//     sort($day_indices);
//     $is_consecutive = true;
//     for ($i = 1; $i < count($day_indices); $i++) {
//         if ($day_indices[$i] - $day_indices[$i-1] != 1) {
//             $is_consecutive = false;
//             break;
//         }
//     }
//     if ($is_consecutive) {
//         $first_day = $german_abbr[$day_order[$day_indices[0]]];
//         $last_day = $german_abbr[$day_order[end($day_indices)]];
//         return $first_day . ' - ' . $last_day;
//     } else {
//         // Non-consecutive days, list them separately
//         return implode(', ', array_map(function($day) use ($german_abbr) {
//             return $german_abbr[strtolower($day)] ?? ucfirst(substr($day, 0, 3));
//         }, $days));
//     }
// }

// // Display each timing group
// if (!empty($timing_groups)) {
//     foreach ($timing_groups as $group) {
//         $days_display = format_day_range($group['days']);
//         if ($group['is_closed']) {
//             // Closed day(s) styling
//             echo '<div class="store-timing-box">
//                     <i class="fas fa-times-circle"></i>
//                     <span>' 
//                         . esc_html($days_display) . ' - Geschlossen'
//                     . '</span>
//                   </div>';
//         } else {
//             // Open day(s) with timing
//             $formatted_opening_time = date('H:i', strtotime($group['opening_time']));
//             $formatted_closing_time = date('H:i', strtotime($group['closing_time']));
//             echo '<div class="store-timing-box">
//                     <i class="fas fa-clock"></i>
//                     <span>' 
//                         . esc_html($days_display) . ': ' 
//                         . esc_html($formatted_opening_time) . ' - ' 
//                         . esc_html($formatted_closing_time) 
//                     . '</span>
//                   </div>';
//         }
//     }
// } else {
//     echo '<p>' . __('No store hours available', 'dokan-lite') . '</p>';
// }


echo '</div>'; 
// Vendor name and rating section
// Vendor name and rating section
echo '<div class="col-1">
   
    <div class="align">
        <ul class="rating_vendor">
            <li style="list-style:none;">';

// Get seller rating data
$seller_rating = dokan_get_seller_rating($store_user->ID);
$rating_value = !empty($seller_rating['rating']) ? $seller_rating['rating'] : 0;
$rating_count = !empty($seller_rating['count']) ? $seller_rating['count'] : 0;

// Rating display section with dynamic rating
// if ($rating_count > 0) {
//     echo '<div class="rating-wrapper">
//         <i class="fas fa-star" style="color: #FFB800;"></i>
//         <span class="rating-number">' . number_format($rating_value, 1) . '</span>
//         <span class="rating-divider">/</span>
//         <span class="rating-max">5</span>
//         <span class="rating-count">(' . $rating_count . ' Bewertungen)</span>
//     </div>';
// } else {
//     echo '<div class="rating-wrapper">
//         <i class="fas fa-star" style="color: #FFB800;"></i>
//         <span class="rating-number">0.0</span>
//         <span class="rating-divider">/</span>
//         <span class="rating-max">5</span>
//         <span class="rating-count">(Keine Bewertungen)</span>
//     </div>';
// }

echo        '</li>
        </ul>

    </div>
</div>
</div>';
echo '<div class="restaurant-info-row">';
 echo '<div class="name_of_vendor" style="width:100%;margin:0;color:#005959"><h1 style="margin:0;color:#005959">' . esc_html($store_info['store_name']) . '</h1></div>';
    echo '<div class="info-left">';
        // Rating Section
       echo '<div class="rating-section show-reviews-popup" style="cursor:pointer">';
            echo '<i class="fas fa-star rating-star"></i>';
            if ($rating_count > 0) {
                echo '<span class="rating-text">' . number_format($rating_value, 1) . '</span>';
                echo '<span class="rating-count">(' . $rating_count . ')</span>';
            } else {
                echo '<span class="rating-text">0.0</span>';
                echo '<span class="rating-count">(5)</span>';
            }
        echo '</div>';
         $postal_code = WC()->session->get('postal_code');
        $vendor_id = get_query_var('author') ?: (function_exists('dokan_get_current_user_id') ? dokan_get_current_user_id() : null);

		$min_order_value = 0;
		$postal_prices = [];

		if (is_numeric($vendor_id)) {
			$postal_prices = get_user_meta($vendor_id, '_vendor_postal_prices', true);

			if (is_array($postal_prices)) {
				foreach ($postal_prices as $entry) {
					if (isset($entry['postal_code']) && $entry['postal_code'] === $postal_code) {
						$min_order_value = floatval($entry['min_order']);
						break;
					}
				}
			}

		}
       $min_order_amount = number_format($min_order_value, 2, ',', '');
        // Delivery Info Section
        echo '<div class="delivery-info">';
            // Minimum order amount (you can make this dynamic from store settings)
//             $min_order_amount = get_user_meta($store_user->ID, '_dokan_store_min_order_amount', true) ?: '20,00';
            echo '<div class="delivery-item">';
                echo '<i class="fas fa-wallet"></i>';
                echo '<span class="label">Min.</span>';
                echo '<span class="value">' . esc_html($min_order_amount) . ' €</span>';
            echo '</div>';
        echo '</div>';
    echo '</div>';
    
    // Right section with buttons
    echo '<div class="info-right">';
        echo '<button class="about-us-btn" onclick="openAboutModal()">';
            echo '<i class="fas fa-info-circle"></i>';
            echo 'Über uns';
        echo '</button>';
    echo '</div>';
echo '</div>';

echo '<div class="aboutus-modal-overlay" id="aboutModal">';
    echo '<div class="aboutus-modal-content">';
        echo '<div class="aboutus-modal-header">';
            echo '<h2 class="aboutus-modal-title">Über uns</h2>';
            echo '<button class="aboutus-modal-close" onclick="closeAboutModal()">';
                echo '<i class="fas fa-times"></i>';
            echo '</button>';
        echo '</div>';
        echo '<div class="aboutus-modal-body">';
            
            // Map Section with Address Box
            echo '<div class="map-section">';
                echo '<div class="address-box">';
                    echo '<div class="address-header">';
                        echo '<span class="address-title">Adresse</span>';
                        // Store status check
                        $store_user_check = get_user_by('id', get_query_var('author'));
                        $is_store_closed = ($store_user_check && function_exists('dokan_is_store_open') && !dokan_is_store_open($store_user_check->ID));
                        echo '<span class="store-status ' . ($is_store_closed ? 'closed' : 'open') . '" id="storeStatus">';
                            echo $is_store_closed ? 'Geschlossen' : 'Open';
                        echo '</span>';
                    echo '</div>';
                    echo '<div class="address-text">';
                        // Display store address
                       if (!empty($store_info['address'])) {
							$address_parts = [];

							if (!empty($store_info['address']['street_1'])) {
								$address_parts[] = $store_info['address']['street_1'];
							}
							if (!empty($store_info['address']['zip']) && !empty($store_info['address']['city'])) {
								$address_parts[] = $store_info['address']['zip'] . ' ' . $store_info['address']['city'];
							}

							// Join and encode for URL
							$full_address = implode(', ', $address_parts);
							$encoded_address = urlencode($full_address);
                            $directions_url = "https://www.google.com/maps/dir/?api=1&destination=" . $encoded_address;
							echo '<a class="address-name" href="https://www.google.com/maps/search/?api=1&query=' . $encoded_address . '" target="_blank">';
							echo esc_html($full_address); 
							echo '</a>';
  
						   echo  '<a class="address-direction" href="https://www.google.com/maps/dir/?api=1&destination=' . $encoded_address . '" target="_blank" class="get-directions-btn" title="Get Directions">';
							 echo  '<img src="/wp-content/uploads/2025/06/direction.png" alt="Directions" style="width: 20px; height: 20px; vertical-align: middle;">';
						   echo  '</a>';

						} else {
							echo '---';
						}
                    echo '</div>';
					 echo '</div>';  
                     echo '<div class="map-controls">';
						 echo '<button id="zoom-in">+</button>';
						 echo '<button id="zoom-out">-</button>';
					 echo '</div>';
               echo '<div id="mapContainer"></div>'; 
            echo '</div>';
            
            
    
            echo '<div class="section">';
                echo '<div class="section-header">';
                    echo '<i class="fas fa-clock section-icon"></i>';
                    echo '<h3 class="section-title">Lieferzeiten</h3>';
                echo '</div>';
                echo '<div class="timing-list">';
                
                // Use your existing timing groups logic
                $day_names_full = [
       		 'monday'    => 'Montag',
			'tuesday'   => 'Dienstag',
			'wednesday' => 'Mittwoch',
			'thursday'  => 'Donnerstag',
			'friday'    => 'Freitag',
			'saturday'  => 'Samstag',
			'sunday'    => 'Sonntag'

                ];
                echo '<div class="timing-section-wrapper">';
                // Create individual day entries for the modal
                $all_days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
                foreach ($all_days as $day) {
                    echo '<div class="timing-item">';
                        echo '<span class="timing-day">' . $day_names_full[$day] . '</span>';
                        
                        // Check if day has valid timing
                        if (isset($dokan_store_times[$day]) && 
                            !empty($dokan_store_times[$day]['opening_time']) && 
                            !empty($dokan_store_times[$day]['closing_time'])) {
                            
                            $opening_time = $dokan_store_times[$day]['opening_time'][0];
                            $closing_time = $dokan_store_times[$day]['closing_time'][0];
                            $formatted_opening_time = date('H:i', strtotime($opening_time));
                            $formatted_closing_time = date('H:i', strtotime($closing_time));
                            
                            echo '<span class="timing-hours">' . esc_html($formatted_opening_time) . ' - ' . esc_html($formatted_closing_time) . '</span>';
                        } else {
                            echo '<span class="timing-hours closed">Geschlossen für Lieferungen</span>';
                        }
                    echo '</div>';
                }
                
                echo '</div>';
            echo '</div>';
            echo '</div>';
            // Business Details Section
            echo '<div class="section">';
                echo '<div class="section-header">';
                    echo '<i class="fas fa-building section-icon"></i>';
                    echo '<h3 class="section-title">Unternehmensangaben</h3>';
                echo '</div>';
             echo '<div class="business-section-wrapper">';
                echo '<div class="business-info">';
                    // Store name
                    echo '<div class="business-item">';
                        echo '<strong>' . esc_html($store_info['store_name']) . '</strong>';
                    echo '</div>';
                    echo '<p>';
                        echo 'Laziza bringt den Zauber Südasiens nach Bad Marienberg-Langenbach – mit jeder Gabel ein Hauch von Süden, mit jedem Biss ein unvergessliches Geschmackserlebnis. ';
                    echo '</p>';

 echo '<p>';
                        echo 'Ob feuriges Curry, knusprige Pizza oder hausgemachte Burger – wir stillen die Sehnsucht nach authentischem Essen, das man nicht vergisst.';
                    echo '</p>';
                    
                    // Store description or default business info
                    echo '<div class="business-item">';
                        if (!empty($store_info['store_desc'])) {
                            echo '<span>' . wp_kses_post($store_info['store_desc']) . '</span>';
                        } else {
                            echo '<span>Professioneller Essenslieferservice<br>';
                            if (!empty($store_info['address'])) {
                                if (!empty($store_info['address']['street_1'])) {
                                    echo esc_html($store_info['address']['street_1']) . '<br>';
                                }
                                if (!empty($store_info['address']['zip']) && !empty($store_info['address']['city'])) {
                                    echo esc_html($store_info['address']['zip']) . ' ' . esc_html($store_info['address']['city']);
                                }
                            }
                            echo '</span>';
                        }
                    echo '</div>';
                    
                    // Legal representative (if available)
                    echo '<div class="business-item">';
                        echo '<span>Gesetzlicher Vertreter: ' . esc_html($store_user->display_name) . '</span>';
                    echo '</div>';
                    
                    // Contact email
                    if (!empty($store_user->user_email)) {
                        echo '<div class="business-item">';
                            echo '<a href="mailto:' . esc_attr($store_user->user_email) . '" class="business-link">Senden Sie uns eine E-Mail</a>';
                        echo '</div>';
                    }
                    
                    // Phone/Fax
                    if (!empty($store_info['phone'])) {
                        echo '<div class="business-item">';
                            echo '<span>Telefon: ' . esc_html($store_info['phone']) . '</span>';
                        echo '</div>';
                    }
                   
                echo '</div>';
            echo '</div>';
            echo '</div>';
        echo '</div>';
    echo '</div>';
echo '</div>';
    // Tag tabs and products section
    $seller_id = $store_user->ID;
    
    $args = array(
        'taxonomy'   => 'product_tag',
        'orderby'    => 'name',
        'hide_empty' => true,
    );
    $tags = get_terms($args);

    // Reorder tags to place "Popular" first
    usort($tags, function($a, $b) {
        if ($a->name == 'Popular') return -1;
        if ($b->name == 'Popular') return 1;
        return 0;
    });

    // Create tag tabs
    echo '<div class="category-tabs">';
    
    // Custom live search bar
    echo '<div class="live-search-container">
        <input type="text" id="live-search-input" class="live-search-input" placeholder="Produkte suchen..." autocomplete="off">
        <i class="fa fa-search search-icon"></i>
    </div>';
    echo '<div class="category-menu-wrapper">';
	
    echo '<button class="hamburger-toggle" aria-label="Toggle Categories">&#9776;</button>';

    echo '<div class="custom-category-menu">';
    
    
    // Add mobile menu styles
    
    
    foreach ($tags as $index => $tag) {
        // Check if there are products under this tag
        $product_args = array(
            'post_type' => 'product',
            'posts_per_page' => 1,
            'author' => $seller_id,
            'tax_query' => array(
                array(
                    'taxonomy' => 'product_tag',
                    'field'    => 'id',
                    'terms'    => $tag->term_id,
                    'operator' => 'IN',
                ),
            ),
        );
        $product_query = new WP_Query($product_args);
        
        // Display tab if there are products under the tag
        if ($product_query->have_posts()) {
            $active_class = ($index === 0) ? 'active' : ''; // Mark first tab (Popular) as active by default
            echo '<button class="category-tab ' . esc_attr($active_class) . '" data-tag="' . esc_attr($tag->term_id) . '" data-tag-name="' . esc_attr($tag->name) . '">' 
                . esc_html($tag->name) . 
            '</button>';
        }
        wp_reset_postdata();
    }


    echo '</div>';
    echo '</div>';
    echo '</div>'; // Properly close the category-menu-wrapper

   // Add products container
echo '<div id="products-container">';

$any_products_found = false;

foreach ($tags as $tag) {
    // Check if tag has at least 1 product
    $product_args = [
        'post_type' => 'product',
        'posts_per_page' => 1,
        'author' => $seller_id,
        'tax_query' => [[
            'taxonomy' => 'product_tag',
            'field' => 'id',
            'terms' => $tag->term_id,
        ]],
    ];
    
    $product_query = new WP_Query($product_args);

    if ($product_query->have_posts()) {
        $any_products_found = true;
        
        echo '<div class="category-section" data-tag="' . esc_attr($tag->term_id) . '">';
        echo '<div class="section-title-wrapper">' ;
        echo '<h2 class="section-title">' . esc_html($tag->name) . '</h2>';
        echo '<p>' . wp_kses_post($tag->description) . '</p>';
        echo  '</div>';
        echo '<div id="tag-' . esc_attr($tag->term_id) . '" class="product-grid">';
        
        // Display initial products
        $result = display_products_chunk($tag, $seller_id, 1, 50);
        echo $result['html'];
        
        echo '</div>'; // Close product-grid
        
        // Add load more button if needed
        if ($result['has_more']) {
            echo '<div class="load-more-wrapper">
                <button class="load-more-tag btn btn-secondary"
                    data-tag="' . esc_attr($tag->term_id) . '"
                    data-vendor="' . esc_attr($seller_id) . '"
                    data-page="2">
                    Load More ' . esc_html($tag->name) . '
                    <span class="loading-indicator" style="display: none;">...</span>
                </button>
            </div>';
        }
        
        echo '</div>'; // Close category-section
    }
    wp_reset_postdata();
}

if (!$any_products_found) {
    echo '<div id="no-products-message" class="section-title">No products found for your search.</div>';
}

echo '</div>'; // Close products-container
    // Loop through tags and display products
 echo '<div class="product-quick-view-popup" id="dynamic-product-modal" style="display: none;">
    <div class="product-quick-view-popup-content">
        <span class="close-popup">&times;</span>
        <div class="modal-body">
            <!-- Product content will be loaded here dynamically -->
            <div class="loading-spinner">Loading...</div>
        </div>
    </div>
</div>';
    
    // Get all products by this seller
    $args = array(
        'post_type' => 'product',
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'author' => $seller_id,
    );
    $products = new WP_Query($args);

    // Initialize comments array
    $comments = array();

    // Get comments for all products
    if ($products->have_posts()) {
        $product_ids = wp_list_pluck($products->posts, 'ID');
        
        // Get comments for all products with correct parameters
        $comments = get_comments(array(
            'post__in' => $product_ids,
            'status' => 'approve',
            'type' => 'review',  // This was missing - specifically get review type comments
            'hierarchical' => 'threaded',  // Include threaded comments
            'orderby' => 'comment_date_gmt',  // Order by date
            'order' => 'DESC'  // Most recent first
        ));
    }

  echo '<div class="reviews-popup-overlay" id="reviews-popup">
        <div class="reviews-popup-content">
            <span class="close-popup-reviews">&times;</span>
            <h2>Bewertungen</h2>
            <div class="reviews-container">
                <div class="rating-summary">
                    <div class="rating-summary-top">
                        <div class="rating-big">' . number_format($rating_value, 1) . '</div>
                        <div class="rating-stars">';
                        for ($i = 1; $i <= 5; $i++) {
                            echo '<i class="fas fa-star"></i>';
                        }
                    echo '</div>
                    <span class="rating-count-all">Alle Bewertungen (' . $rating_count . '+)</span>
                    </div>

                    <div class="rating-bars">';
                    $rating_data = custom_get_seller_rating_counts($store_user->ID);
                    $rating_counts = $rating_data['counts'];
                    $total_count = $rating_data['total'];

                    for ($i = 5; $i >= 1; $i--) {
                        $count = isset($rating_counts[$i]) ? $rating_counts[$i] : 0;
                        $percentage = $total_count > 0 ? ($count / $total_count) * 100 : 0;
                        
                        echo '<div class="rating-bar-row">
                            <span class="star-label">' . $i . ' <i class="fas fa-star" style="color: #FFB800;"></i></span>
                            <div class="progress-bar">
                                <div class="progress" style="width: ' . $percentage . '%"></div>
                            </div>
                            <span class="count">(' . $count . ')</span>
                        </div>';
                    }
                    echo '</div>
                </div>

                <div class="review-tabs">
                    <button class="review-tab active">Top Bewertungen</button>
                    <button class="review-tab">Neueste</button>
                    <button class="review-tab">Höchste Bewertung</button>
                    <button class="review-tab">Niedrigste Bewertung</button>
                </div>

                <div class="reviews-list">';
                
                if (!empty($comments)) {
                    foreach ($comments as $comment) {
                        $rating = get_comment_meta($comment->comment_ID, 'rating', true);
                        $vendor_id = get_comment_meta($comment->comment_ID, '_vendor_id', true);
                        $liked_products = get_vendor_liked_products($comment->comment_ID);
                        
                        echo '<div class="review-item" data-rating="' . esc_attr($rating) . '" data-date="' . esc_attr($comment->comment_date) . '">';
                        echo '<div class="review-header">
                                <div class="reviewer-info">
                                    <span class="reviewer-name">' . esc_html($comment->comment_author) . '</span>
                                    <div class="review-rating">';
                                    for ($i = 1; $i <= 5; $i++) {
                                        if ($i <= $rating) {
                                            echo '<i class="fas fa-star"></i>';
                                        } else {
                                            echo '<i class="far fa-star"></i>';
                                        }
                                    }
                            echo '</div>
                                </div>
                                <div class="review-date">vor ' . human_time_diff(strtotime($comment->comment_date), current_time('timestamp')) . '</div>
                            </div>
                           
                            <div class="review-content">' . esc_html($comment->comment_content) . '</div>';
                            
                            // Display liked products only if they exist and belong to this vendor
                            if (!empty($liked_products)) {
                                echo '<div class="reviewed-products" data-slider-id="' . esc_attr(uniqid()) . '">';
                                echo '<div class="reviewed-header">';
                                echo '<span class="reviewed-label">' . count($liked_products) . ' Gerichte gefallen</span>';
                                echo '<div class="slider-navigation">';
                                echo '<button class="nav-btn prev-btn"><i class="fas fa-chevron-left"></i></button>';
                                echo '<button class="nav-btn next-btn"><i class="fas fa-chevron-right"></i></button>';
                                echo '</div>';
                                echo '</div>';
                                echo '<div class="reviewed-products-slider">';
                                
                                foreach ($liked_products as $product) {
                                    echo '<div class="reviewed-product" data-product-id="' . esc_attr($product['id']) . '">';
                                    echo '<div class="reviewed-product-info">';
                                    echo '<h4 class="reviewed-product-title">' . esc_html($product['name']) . '</h4>';
                                    echo '<span class="reviewed-product-price">' . wc_price($product['price']) . '</span>';
                                    echo '</div>';
                                    echo '<div class="reviewed-product-image">';
                                    echo '<img src="' . esc_url($product['image']) . '" alt="' . esc_attr($product['name']) . '">';
                                    echo '</div>';
                                    echo '</div>';
                                }
                                
                                echo '</div>';
                                echo '</div>';
                            }
                            
                            echo '<div class="review-helpful">
                                <button class="helpful-btn" data-comment-id="' . $comment->comment_ID . '">
                                    <i class="' . (get_comment_meta($comment->comment_ID, 'user_' . get_current_user_id() . '_helpful', true) ? 'fas' : 'far') . ' fa-thumbs-up"></i> 
                                    Hilfreich <span class="helpful-count">(' . (get_comment_meta($comment->comment_ID, 'helpful_count', true) ?: '0') . ')</span>
                                </button>
                            </div>
                        </div>';
                    }
                } else {
                    echo '<p class="no-reviews">Noch keine Bewertungen vorhanden.</p>';
                }
                
                echo '</div>
            </div>
        </div>
    </div>';

    // Add AJAX handlers for helpful functionality
    add_action('wp_ajax_toggle_review_helpful', 'handle_toggle_review_helpful');
    add_action('wp_ajax_nopriv_toggle_review_helpful', 'handle_toggle_review_helpful');

    function handle_toggle_review_helpful() {
        check_ajax_referer('woocommerce-cart', 'security');
        
        $comment_id = isset($_POST['comment_id']) ? intval($_POST['comment_id']) : 0;
        $user_id = get_current_user_id();

        if (!$comment_id) {
            wp_send_json_error(array('message' => 'Invalid comment ID'));
            return;
        }

        if (!$user_id) {
            wp_send_json_error(array('message' => 'Please log in to mark reviews as helpful'));
            return;
        }

        // Check if user has already marked this review as helpful
        $user_helpful_key = 'user_' . $user_id . '_helpful';
        $has_liked = get_comment_meta($comment_id, $user_helpful_key, true);
        $helpful_count = intval(get_comment_meta($comment_id, 'helpful_count', true)) ?: 0;

        if ($has_liked) {
            // Remove helpful mark
            delete_comment_meta($comment_id, $user_helpful_key);
            update_comment_meta($comment_id, 'helpful_count', max(0, $helpful_count - 1));
            $is_helpful = false;
        } else {
            // Add helpful mark
            add_comment_meta($comment_id, $user_helpful_key, true, true);
            update_comment_meta($comment_id, 'helpful_count', $helpful_count + 1);
            $is_helpful = true;
        }

        // Get updated count
        $new_count = intval(get_comment_meta($comment_id, 'helpful_count', true)) ?: 0;

        wp_send_json_success(array(
            'count' => $new_count,
            'liked' => $is_helpful
        ));
    }

    // Switch from PHP to HTML
    ?>
<link
  rel="stylesheet"
  href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"

  crossorigin=""
/>
<script
  src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"

  crossorigin=""
></script>
 <script src="https://maps.googleapis.com/maps/api/js?key=AIzaSyDL9J82iDhcUWdQiuIvBYa0t5asrtz3Swk&libraries=places"></script>
<?php
$postal_code = WC()->session ? WC()->session->get('postal_code') : '';
$postal_label = WC()->session ? WC()->session->get('postal_label') : '';
$pickUp_Order = WC()->session ? WC()->session->get('is_pickup_order') : '';
?>
<script>
    const storeAddress = <?php echo json_encode($full_address); ?>;
    var myAjax = {
        ajaxUrl: '<?php echo admin_url('admin-ajax.php'); ?>',  // The AJAX URL for WordPress
        cartNonce: '<?php echo wp_create_nonce('woocommerce-cart'); ?>', // Nonce for security
    };
    var postalCode = "<?php echo esc_js($postal_code); ?>";
    var postalLabel = "<?php echo esc_js($postal_label); ?>";
    var isPickupOrder = "<?php echo esc_js($pickUp_Order); ?>";
    
	  function openAboutModal() {
		const modal = document.getElementById('aboutModal');
		modal.classList.add('active');
	 	document.body.style.overflow = 'hidden';
		}

	function closeAboutModal() {
		const modal = document.getElementById('aboutModal');
		modal.classList.remove('active');
		document.body.style.overflow = 'auto';
	}
 
    jQuery(document).ready(function($) {
  if (storeAddress) {
    $.get('https://nominatim.openstreetmap.org/search', {
      q: storeAddress,
      format: 'json',
      limit: 1
    }, function(data) {
      if (data && data.length > 0) {
        const lat = parseFloat(data[0].lat);
        const lon = parseFloat(data[0].lon);

        const map = L.map("mapContainer", {
          center: [lat, lon],
          zoom: 13,
          scrollWheelZoom: false,
          doubleClickZoom: false,
          boxZoom: false,
          keyboard: false,
          touchZoom: false,
          zoomControl: false
        });

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
          attribution: '&copy; OpenStreetMap contributors'
        }).addTo(map);

        L.marker([lat, lon])
          .addTo(map)
          .bindPopup(storeAddress)
          .openPopup();

        // Zoom buttons
        document.getElementById("zoom-in").addEventListener("click", () => map.zoomIn());
        document.getElementById("zoom-out").addEventListener("click", () => map.zoomOut());
      } else {
        console.error("Address not found via Nominatim.");
      }
    });
  } else {
    console.error("No store address provided.");
  }

	// Close modal when clicking outside
	document.getElementById('aboutModal').addEventListener('click', function(e) {
		if (e.target === this) {
			closeAboutModal();
		}
	});
	// Close modal with Escape key
	document.addEventListener('keydown', function(e) {
		if (e.key === 'Escape') {
			closeAboutModal();
		}
	});
        setTimeout(() => {
        originalProducts = $('#products-container').html();
        initProductSearch();
    }, 500);



    let currentModalProductId = null;
    const modal = $('#dynamic-product-modal');
    const modalBody = modal.find('.modal-body');
    const skeletonLoaderHTML = `
        <div class="product-modal-skeleton">
            <div class="skeleton-image"></div>
            <div class="skeleton-details">
                <div class="skeleton-line skeleton-title"></div>
                <div class="skeleton-line skeleton-price"></div>
                <div class="skeleton-line skeleton-block"></div>
                <div class="skeleton-line skeleton-block"></div>
                <div class="skeleton-add-to-cart">
                    <div class="skeleton-qty"></div>
                    <div class="skeleton-button"></div>
                </div>
            </div>
        </div>`;

    // Store this skeleton as the initial/reset state
    const modalTemplate = skeletonLoaderHTML;
    let loading = false;

 function openProductModal(productId) {
    if (loading) return;
    loading = true;

    if (!productId || (productId === currentModalProductId && modal.is(':visible'))) {
        if (modal.is(':visible')) modal.fadeIn(100);
        loading = false;
        return;
    }

    currentModalProductId = productId;
    modalBody.html(skeletonLoaderHTML);
    modalBody.addClass('is-loading');
    modal.fadeIn(300);

    $.ajax({
        url: myAjax.ajaxUrl,
        type: 'POST',
        data: {
            action: 'load_product_modal',
            product_id: productId,
            _wpnonce: myAjax.cartNonce
        },
        success: function(response) {
            if (response.success) {
                modalBody.fadeOut(150, function () {
                    $(this).html(response.data.modal_html).fadeIn(200);
                    modalBody.removeClass('is-loading');
                    loading = false;
                });
            } else {
                modalBody.removeClass('is-loading');
                modalBody.html('<div class="error">' + (response.data.message || 'Product not found') + '</div>');
                loading = false;
            }
        },
        error: function () {
            modalBody.removeClass('is-loading');
            modalBody.html('<div class="error">Error loading product details. Please try again.</div>');
            loading = false;
        }
    });
}

    // Quick View Button Click
$(document).on('click', '.quick-view-button', function(e) {
    e.preventDefault();
    openProductModal($(this).data('product-id'));
});

 $(document).on('click', '.reviewed-product', function(e) {
    e.preventDefault();
    e.stopPropagation();
    openProductModal($(this).data('product-id'));
});

// Close Modal
$(document).on('click', '.close-popup, .reviews-popup-overlay', function() {
    console.log('Modal close triggered');

    currentModalProductId = null;
    modal.fadeOut(300, function() {
        modalBody.removeClass('is-loading');
        modalBody.html(skeletonLoaderHTML); 
    });
});

let currentSearchActive = false;
let originalProducts = '';

$(document).on('click', '.load-more-tag', function() {
        if (currentSearchActive) {
        alert('Please clear search to load more products');
        return;
    }
    const button = $(this);
    const tagId = button.data('tag');
    const vendorId = button.data('vendor');
    const page = button.data('page');
    const container = $('#tag-' + tagId);

    button.prop('disabled', true).find('.loading-indicator').show();

    $.ajax({
        url: '<?php echo admin_url("admin-ajax.php"); ?>',
        type: 'POST',
        data: {
            action: 'load_more_products',
            security: '<?php echo wp_create_nonce("dokan-custom"); ?>',
            tag_id: tagId,
            page: page,
            vendor_id: vendorId
        },
        success: (response) => {
            if (response.success) {
                if (response.html) {
                    container.append(response.html);
                    button.data('page', response.new_page);
                    
                }
                if (!response.has_more) {
                    button.parent().remove();
                }
                originalProducts = $('#products-container').html();
            }
        },
        error: (xhr) => {
            console.error('AJAX Error:', xhr.responseText);
        },
        complete: () => {
            button.prop('disabled', false).find('.loading-indicator').hide();
        }
    });
}); 

const $container = $('#products-container');
let abortController = null;

let searchTimeout = null;


function initProductSearch() {

    const $searchInput = $('#live-search-input');
    const $container = $('#products-container');

    $(window).on('load', function() {
        originalProducts = $container.html();
    });

    $searchInput.on('input', function() {
        const searchQuery = $(this).val().trim();
        
        // Immediate clear handling
        if (searchQuery === '') {
            clearTimeout(searchTimeout);
            if (abortController) abortController.abort();
            $container.html(originalProducts);
            $('#products-container').html(originalProducts);
            currentSearchActive = false;
            return;
        }
        currentSearchActive = false;
        // Abort previous request
        if (abortController) abortController.abort();
        abortController = new AbortController();

        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            if (searchQuery.length < 2) return;

            $.ajax({
                url: '<?php echo admin_url("admin-ajax.php"); ?>',
                type: 'POST',
                data: {
                    action: 'live_search_products',
                    security: '<?php echo wp_create_nonce('live_search_nonce'); ?>',
                    search: searchQuery,
                    vendor_id: <?php echo get_the_author_meta('ID'); ?>
                },
                signal: abortController.signal,
                beforeSend: () => {
                    currentSearchActive = true;
                    $container.addClass('loading');
                },
                success: (response) => {
                    if (response.success) {
                        $container.html(response.data.html);
                    }
                },
                error: (xhr) => {
                    if (xhr.statusText !== 'abort') {
                        console.error('Search failed');
                    }
                },
                complete: () => {
                    $container.removeClass('loading');
                }
            });
        }, 500); // Wait 500ms after last keystroke
    });
}


        
    $('.hamburger-toggle').on('click', function() {
        $('.custom-category-menu').toggleClass('active');
    });
// Add click handler for menu items to close the menu
    $(document).on('click', '.custom-category-menu .category-tab', function() {
        $('.custom-category-menu').removeClass('active');
        $('.hamburger-toggle').removeClass('active');
    });

    // Close menu when clicking outside
    $(document).on('click', function(e) {
        if (!$(e.target).closest('.custom-category-menu, .hamburger-toggle').length) {
            $('.custom-category-menu').removeClass('active');
            $('.hamburger-toggle').removeClass('active');
        }
    });

 // Sticky class for category-tabs (add on any scroll, never remove)
  var $tabs = $('.category-tabs');
if ($tabs.length) {
    var tabsOffset = $tabs.offset().top;

    function handleStickyTabs() {
        // Only apply on mobile (e.g., width < 768px)
        if ($(window).width() < 768) {
            if ($(window).scrollTop() >= tabsOffset) {
                $tabs.addClass('is-sticky');
            } else {
                $tabs.removeClass('is-sticky');
            }
        } else {
            // Always remove sticky class on desktop
            $tabs.removeClass('is-sticky');
        }
    }

    $(window).on('scroll resize', handleStickyTabs);
    // Initial check
    handleStickyTabs();
}
	
   var scrollTimeout;
    var isScrollingProgrammatically = false;
    var tabsOffset = $tabs.length ? $tabs.offset().top : 0;
    // Scroll-based category activation
   function updateActiveTab() {
    if (isScrollingProgrammatically) return;
    
    var scrollPosition = $(window).scrollTop();
    var tabsHeight = $tabs.length ? $tabs.outerHeight() : 0;
    var offset = tabsHeight + 50; // Increased padding for better detection
    var activeTagId = null;
    var closestDistance = Infinity;
    
    // Find the section that's most prominently in view
    $('.category-section[data-tag]').each(function() {
        var $section = $(this);
        var $sectionTitle = $section.find('.section-title');
        
        if ($sectionTitle.length) {
            var sectionTop = $sectionTitle.offset().top;
            var sectionHeight = $section.outerHeight();
            var sectionCenter = sectionTop + (sectionHeight / 2);
            var viewportCenter = scrollPosition + ($(window).height() / 2);
            
            // Calculate distance from viewport center to section center
            var distance = Math.abs(viewportCenter - sectionCenter);
            
            // Also check if section is actually visible
            var isInView = (scrollPosition + offset >= sectionTop - 100) && 
                          (scrollPosition < sectionTop + sectionHeight);
            
            if (isInView && distance < closestDistance) {
                closestDistance = distance;
                activeTagId = $section.attr('data-tag');
            }
        }
    });
    
    // Only update if we found a valid active section
    if (activeTagId) {
        var $currentActive = $('.category-tab.active');
        var $newActive = $('.category-tab[data-tag="' + activeTagId + '"]');
        
        // Only update if it's actually different
        if (!$newActive.hasClass('active')) {
            $('.category-tab').removeClass('active');
            $newActive.addClass('active');
            
            // Scroll tab into view horizontally - Enhanced method
            scrollTabIntoView($newActive);
        }
    }
}

// Enhanced horizontal scrolling function
function scrollTabIntoView($tab) {
    if (!$tab.length) return;
    
    var $tabsContainer = $tab.closest('.category-tabs'); // Adjust selector as needed
    var $scrollContainer = $tabsContainer.find('.tabs-scroll-container'); // Adjust selector as needed
    
    // If no specific scroll container, use the tabs container itself
    if (!$scrollContainer.length) {
        $scrollContainer = $tabsContainer;
    }
    
    // Get positions and dimensions
    var containerScrollLeft = $scrollContainer.scrollLeft();
    var containerWidth = $scrollContainer.outerWidth();
    var tabLeft = $tab.position().left;
    var tabWidth = $tab.outerWidth();
    
    // Calculate if tab is outside visible area
    var tabRight = tabLeft + tabWidth;
    var scrollLeft = containerScrollLeft;
    
    // If tab is to the right of visible area
    if (tabRight > containerWidth) {
        scrollLeft = containerScrollLeft + (tabRight - containerWidth) + 20; // 20px padding
    }
    // If tab is to the left of visible area
    else if (tabLeft < 0) {
        scrollLeft = containerScrollLeft + tabLeft - 20; // 20px padding
    }
    
    // Smooth scroll horizontally
    if (scrollLeft !== containerScrollLeft) {
        $scrollContainer.animate({
            scrollLeft: scrollLeft
        }, 300);
    }
    
    // Fallback: try native scrollIntoView if above doesn't work
    setTimeout(function() {
        if ($tab[0] && $tab[0].scrollIntoView) {
            $tab[0].scrollIntoView({
                behavior: "smooth",
                inline: "center",
                block: "nearest"
            });
        }
    }, 50);
}

    // Handle tab clicks for smooth scrolling
function handleTabClick(e) {
    e.preventDefault();
    
    var tagId = $(this).data('tag');
    var $targetSection = $('.category-section[data-tag="' + tagId + '"]');
    var $targetTitle = $targetSection.find('.section-title');
    
    if ($targetTitle.length) {
        isScrollingProgrammatically = true;
        
        // Stop any ongoing scroll animations first
        $('html, body').stop(true, false);
        
        // Calculate offset: tabs height + 20px padding
        var tabsHeight = $tabs.length ? $tabs.outerHeight() : 0;
        var scrollOffset = tabsHeight + 20;
        var targetTop = $targetTitle.offset().top - scrollOffset;
        
        // Update active tab immediately
        $('.category-tab').removeClass('active');
        $(this).addClass('active');
        scrollTabIntoView($(this));
        
        // Smooth scroll to target with better easing
        $('html, body').animate({
            scrollTop: targetTop
        }, {
            duration: 600,
            easing: 'swing',
            complete: function() {
                // Reset flag after animation completes
                setTimeout(function() {
                    isScrollingProgrammatically = false;
                }, 100);
            }
        });
    }
}

    // Throttled scroll handler
    function handleScroll() {
        // Handle sticky tabs
        handleStickyTabs();
        
        // Throttle the active tab updates
        if (scrollTimeout) {
            clearTimeout(scrollTimeout);
        }
        
        scrollTimeout = setTimeout(updateActiveTab, 100);
    }

    // Event listeners
    $(window).on('scroll', handleScroll);
    $(window).on('resize', function() {
        // Recalculate tabs offset on resize
        tabsOffset = $tabs.length ? $tabs.offset().top : 0;
        handleStickyTabs();
        updateActiveTab();
    });
    
    // Handle tab clicks
    $(document).on('click', '.category-tab', handleTabClick);

    // Initial setup
    handleStickyTabs();
    updateActiveTab();
       
$(document).on('click', '.helpful-btn', function(e) {
    e.preventDefault();
    var $button = $(this);
    var commentId = $button.data('comment-id');
    var $icon = $button.find('i');
    var $count = $button.find('.helpful-count');

    $.ajax({
        url: myAjax.ajaxUrl,  // Use the localized variable (now inline)
        type: 'POST',
        data: {
            action: 'toggle_review_helpful',
            comment_id: commentId,
            security: myAjax.cartNonce  // Use the inline nonce
        },
        beforeSend: function() {
            $button.prop('disabled', true);
        },
        success: function(response) {
            if (response.success) {
                // Update icon
                $icon.toggleClass('far fas');
                
                // Update count
                $count.text('(' + response.data.count + ')');
                
                // Add animation class
                $button.addClass('helpful-animation');
                setTimeout(function() {
                    $button.removeClass('helpful-animation');
                }, 300);
            } else {
                alert(response.data.message || 'Error updating helpful status');
            }
        },
        error: function(xhr, status, error) {
            console.error('AJAX Error:', status, error);
            alert('Error connecting to server. Please try again.');
        },
        complete: function() {
            $button.prop('disabled', false);
        }
    });
});
 window.openPostalPopupManually = function(e) {
        e && e.preventDefault();
       console.log("here i come");
		if (window.PostalSelector && typeof window.PostalSelector.showPopupManually === 'function') {
			window.PostalSelector.showPopupManually();
		} else {
			console.error('PostalSelector not available');
		}
 }
});
    
</script>
<style>
.product-modal-skeleton {
    display: block;
    width: 100%;
    padding: 20px;
}

.skeleton-image {
    height: 300px;
    background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
    background-size: 200% 100%;
    animation: loading-skeleton 1.5s infinite;
    margin-bottom: 20px;
    border-radius: 4px;
}

.skeleton-details {
    width: 100%;
}

.skeleton-line {
    height: 20px;
    background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
    background-size: 200% 100%;
    animation: loading-skeleton 1.5s infinite;
    margin-bottom: 15px;
    border-radius: 4px;
}

.skeleton-title {
    width: 80%;
}

.skeleton-price {
    width: 40%;
}

.skeleton-block {
    height: 60px;
}

.skeleton-add-to-cart {
    display: flex;
    gap: 10px;
    margin-top: 20px;
}

.skeleton-qty {
    width: 80px;
    height: 40px;
    background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
    background-size: 200% 100%;
    animation: loading-skeleton 1.5s infinite;
    border-radius: 4px;
}

.skeleton-button {
    width: 150px;
    height: 40px;
    background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
    background-size: 200% 100%;
    animation: loading-skeleton 1.5s infinite;
    border-radius: 4px;
}

@keyframes loading-skeleton {
    0% {
        background-position: 200% 0;
    }
    100% {
        background-position: -200% 0;
    }
}

</style>
 

                </div>


                <div id="popup_cart" class="popup_cart">

                <aside class="custom-cart">
        <div class="cart-container">
            <div class="cart-header">
                <div class="delivery-pickup">
                    <button class="delivery active">Delivery <p>Standard (15-30 min)</p></button>
                    <button class="pickup">Pick-up</button>
                </div>
                <h2>Ihre Artikel</h2>
            </div>
            <div class="cart-items">
                <!-- atta codes -->
            <div class="cart-loading" style="display: none;">
                     <div class="loading-spinner"></div>
                           Artikel im Warenkorb werden geladen...
                      </div>
                      <!-- atta codes -->
 
                <ul id="cart-item-list">
                 <!-- dynamically load them -->
                </ul>
            </div>

            <!-- Popular with your order section -->
<!--             <div class="popular-with-order">
                <h3>Popular with your order</h3>
				<p class="popular-with-des">
					Based on what customers bought together
				</p>
                <div class="popular-items">
                    <?php
                    $upload_dir = wp_upload_dir();
                    $image_url = $upload_dir['baseurl'] . '/2025/03/Plus-circle.png';

                    $args = array(
                        'post_type' => 'product',
                        'posts_per_page' => -1,
                        'author' => $seller_id,
                        'tax_query' => array(
                            array(
                                'taxonomy' => 'product_tag',
                                'field'    => 'name',
                                'terms'    => 'Popular'
                            )
                        )
                    );
                    $popular_products = new WP_Query($args);



                    if ($popular_products->have_posts()) {
                        while ($popular_products->have_posts()) {
                            $popular_products->the_post();
                            $product = wc_get_product(get_the_ID());
                            
                            // Handle price display for variable products
                            if ($product->is_type('variable')) {
                                $min_price = $product->get_variation_regular_price('min');
                                $max_price = $product->get_variation_regular_price('max');
                                $min_sale_price = $product->get_variation_sale_price('min');
                                $max_sale_price = $product->get_variation_sale_price('max');
                                
                                if ($min_sale_price !== '' && $min_sale_price < $min_price) {
                                    $price_html = '<span class="popular-item-price">' . wc_price($min_sale_price) . ' <del>' . wc_price($min_price) . '</del></span>';
                                } else {
                                    $price_html = '<span class="popular-item-price">' . wc_price($min_price) . '</span>';
                                }
                            } else {
                                // Regular product price handling
                                $regular_price = $product->get_regular_price();
                                $sale_price = $product->get_sale_price();
                                
                                if ($sale_price !== '' && $sale_price < $regular_price) {
                                    $price_html = '<span class="popular-item-price">' . wc_price($sale_price) . ' <del>' . wc_price($regular_price) . '</del></span>';
                                } else {
                                    $price_html = '<span class="popular-item-price">' . wc_price($regular_price) . '</span>';
                                }
                            }

                            echo '<div class="popular-item">
                                <div class="popular-item-box">
                                    <img src="' . get_the_post_thumbnail_url(get_the_ID(), 'thumbnail') . '" alt="' . esc_attr(get_the_title()) . '">
                                    <button class="add-popular" data-product-id="' . get_the_ID() . '">
                                        <img src="' . esc_url($image_url) . '" alt="Add to cart" class="plus-icon"/>
                                    </button>
                                </div>
                                <div class="popular-item-details">
                                    <span class="popular-item-label">' . get_the_title() . '</span>
                                    ' . $price_html . '
                                </div>
                            </div>';
                        }
                    }
                    wp_reset_postdata();

                    // 1. WooCommerce Subtotal (cart items only)
                    // 1. WooCommerce Subtotal (cart items only)
                        $subtotal = WC()->cart ? WC()->cart->get_subtotal() : 0;
                        
                        // 2. Get postal code from session
                        $postal_code = WC()->session->get('postal_code');
                        
                        // 3. Get current vendor ID (Dokan store page)
                        $vendor_id = get_query_var('author'); // works on store/vendor page
                        
                        // 4. Get that vendor's saved postal prices
                        $is_pick_up = WC()->session->get('is_pickup_order');
                        $delivery_fee = 0;
                        $city_name = '';
                        $city_name = WC()->session->get('postal_label');
                        
                        if ($delivery_fee == 0 && !$is_pick_up) {

                            $vendor_id = get_query_var('author');
                           
                            if (!$vendor_id && function_exists('dokan_get_current_user_id')) {
                                $vendor_id = dokan_get_current_user_id();
                            }
                    
                            $postal_prices = get_user_meta($vendor_id, '_vendor_postal_prices', true);
                         
                            if (is_array($postal_prices)) {
									foreach ($postal_prices as $entry) {
          							  if (isset($entry['postal_code']) && $entry['postal_code'] === $postal_code) {
           								     $delivery_fee = floatval($entry['price']);
             								   break; // stop looping once found
          								  }
     								   }
  								  }      
                        }
                        
                        // 6. Manually calculate grand total
                        $grand_total = WC()->cart->get_total('edit');
                    ?>
                </div>
            </div>
 -->
            <div class="cart-summary">
    <div class="summary-item">
        <span>Zwischensumme</span>
        <span id="cart-subtotal"><?php wc_price($subtotal); ?></span>
    </div>
    <div>
   
                    </div>
    <div class="summary-item">
        <span>Liefergebühr</span>
        <span id="cart-standard-fee">
    <?php echo $delivery_fee > 0 ? wc_price($delivery_fee)  : 'Frei' ?>
</span>
    </div>
<?php if (!$is_pick_up): ?>
 <div class="summary-item">
        <span>Liefergebiet</span>
        <span id="cart-standard-fee">
    <?php echo $city_name  ?>
</span>
    </div>
<?php endif; ?>
<div id="#cart-standard-fee" class="summary-item" style="cursor: pointer; color:red; width:fit-content;" onclick="openPostalPopupManually()">
    <?php echo $is_pick_up ? "Auf Lieferung umstellen" : "Liefergebiet ändern"; ?>
</div>
    <hr>
 
    <div class="summary-item total">
    <span>Gesamt (inkl. MwSt.)</span>
    <span id="cart-total">
<?php
if (WC()->cart->is_empty()) {
    echo wc_price(0);
} else {
    echo wc_price($grand_total);
}
?>
</span>
</div>
 
    <button id="checkout-button">Überprüfen Sie Zahlung und Adresse</button>
</div>
        </div>
    </aside>

                </div>
                </div>
    <script type="text/javascript">



    jQuery(document).ready(function($) {
        var ajaxUrl = '<?php echo admin_url("admin-ajax.php"); ?>';
        var checkoutUrl = '<?php echo wc_get_checkout_url(); ?>';
        var myAjax = { // Define myAjax if not already defined globally
            ajaxUrl: ajaxUrl,
            cartNonce: '<?php echo wp_create_nonce("woocommerce-cart"); ?>'
        };

        var isStoreCurrentlyClosed = <?php
            // Pass store status to JS - ALWAYS CHECK
            $store_user_check = get_user_by('id', get_query_var('author'));
            echo ($store_user_check && function_exists('dokan_is_store_open') && !dokan_is_store_open($store_user_check->ID)) ? 'true' : 'false';
        ?>;
        var preorderDataForConfirmation = {}; // Store data for pre-order confirmation popup
        $(document.body).on('removed_from_cart', function() {
        console.log('Product removed from cart, updating custom cart...');
        updateCartContents();

    });
        // --- Pre-order Popup HTML (Always output, initially hidden) ---
         // Check if the popup isn't already added by another part of the theme/plugin
         if ($('#preorder-popup-container').length === 0) {
            $('body').append(`
                <div id="preorder-popup-container" style="display: none;">
                    <!-- Store Closed Notice Banner (Optional - can be added here or via PHP hook) -->
                     <?php
                     // Optionally, add the notice banner generation here if needed even when store open initially
                     // $store_user_for_notice = get_user_by('id', get_query_var('author'));
                     // if ($store_user_for_notice && function_exists('dokan_is_store_open') && !dokan_is_store_open($store_user_for_notice->ID)) {
                     ?>
                     <!--
                         <div class="store-closed-notice">
                             <div class="store-closed-content">
                                 <span class="store-closed-icon">⚠️</span>
                                 <div class="store-closed-text">
                                     <h3><?php _e('Dieses Geschäft ist derzeit geschlossen', 'dokan-lite'); ?></h3>
                                     <p><?php _e('Sie können weiterhin in den Produkten stöbern. Jetzt aufgegebene Bestellungen werden als Vorbestellungen behandelt und nach der Wiedereröffnung des Geschäfts bearbeitet.', 'dokan-lite'); ?></p>
                                 </div>
                                 <button id="understand-closed-btn"><?php _e('Ich verstehe', 'dokan-lite'); ?></button>
                             </div>
                         </div>
                      -->
                     <?php // } ?>

                    <!-- Pre-order Confirmation Popup -->
                    <div class="preorder-popup">
                        <div class="preorder-popup-content">
                            <span class="close-preorder-popup">&times;</span>
                            <h3><?php _e('Bestätigen Sie die Vorbestellung', 'dokan-lite'); ?></h3>
                            <p><?php _e('Der Laden ist geschlossen. Diese Bestellung als Vorbestellung aufgeben?', 'dokan-lite'); ?></p>
                            <div class="preorder-popup-actions">
                                <button class="preorder-yes-btn"><?php _e('Ja, Vorbestellung aufgeben', 'dokan-lite'); ?></button>
                                <button class="preorder-no-btn"><?php _e('Stornieren', 'dokan-lite'); ?></button>
                            </div>
                        </div>
                    </div>
                </div>
            `);
         }
         // --- End Pre-order Popup HTML ---

        // --- REMOVED Pre-order Popup CSS generation ---
        // The styles are now loaded from style.css

        // Dismiss the optional notice banner
         $(document).on('click', '#understand-closed-btn', function() {
             $('.store-closed-notice').slideUp();
         });

        // Popup open/close functionality (Quick View)
    $(document).on('click', '.quick-view-button', function(e) {
            e.preventDefault();
            var productId = $(this).data('product-id');
            var $popup = $('#popup-' + productId);
            $popup.fadeIn(300);
            
            // Find the default variation and trigger change
            setTimeout(function() {
                var $defaultVariation = $popup.find('.variation-radio[data-is-default="true"]');
                if ($defaultVariation.length) {
                    $defaultVariation.prop('checked', true).trigger('change');
                    $defaultVariation.closest('.variation-item').addClass('selected');
                } else {
                    // Only if no default is set, fall back to the lowest price variation
                    var $lowestPriceVariation = $popup.find('.variation-radio').first(); // Assuming first is lowest if no default
                    var minPrice = Infinity;
                    $popup.find('.variation-item').each(function(){
                        var priceText = $(this).find('.variation-price').text().match(/\d+(\.\d+)?/g);
                        var price = priceText ? parseFloat(priceText[priceText.length-1]) : Infinity;
                        var variationInput = $(this).find('.variation-radio');
                        if(price < minPrice && variationInput.length){
                            minPrice = price;
                            $lowestPriceVariation = variationInput;
                        }
                    });
                     if($lowestPriceVariation.length){
                        $lowestPriceVariation.prop('checked', true).trigger('change');
                        $lowestPriceVariation.closest('.variation-item').addClass('selected');
                    }
                }
            }, 100);
        });

        $(document).on('click', '.close-popup', function() {
            $(this).closest('.product-quick-view-popup').fadeOut(300);
        });

        $(window).on('click', function(e) {
            if($(e.target).hasClass('product-quick-view-popup')) {
                $('.product-quick-view-popup').fadeOut(300);
            }
        });

        // Update the variation selection handler (Quick View)
        $(document).on('change', '.variation-radio', function() {
            var $this = $(this);
            var $popup = $this.closest('.product-quick-view-popup');
            var $addToCartButton = $popup.find('.add-to-cart-popup');
            var variationId = $this.val();
            
            // Remove selected class from all items and add to the selected one
            $popup.find('.variation-item').removeClass('selected');
            $this.closest('.variation-item').addClass('selected');
            
            // Update add to cart button data
            $addToCartButton.attr('data-variation-id', variationId);
            
            // Update main product price display
            var selectedPrice = $this.closest('.variation-item').find('.variation-price').html();
            $popup.find('.popup-product-details .price').html(selectedPrice);
            
            // Enable the add to cart button
            $addToCartButton.prop('disabled', false).removeClass('disabled');
        });


        // atta code
/**
 * Parse a price string to extract the numerical value.
 * Handles various currency formats.
 */
function parsePrice(priceStr) {
    if (!priceStr) return 0;
   
    // Remove all non-numeric characters except decimal point/comma
    const numericStr = priceStr.replace(/[^0-9.,]/g, '');
    // Replace comma with dot for standard decimal parsing
    const normalizedStr = numericStr.replace(/,/g, '.');
    // Return parsed float or 0 if parsing fails
    return parseFloat(normalizedStr) || 0;
}
 
/**
 * Format a price number according to site's currency format
 */
function formatPrice(price) {
    // This function should use the site's currency format
    // For example: €40.00
    return '€' + price.toFixed(2);
}
 
/**
 * Update the add to cart button price in real-time based on selected options
 */
function updateCartButtonPrice() {
    const $popup = $('.product-quick-view-popup:visible');
    if ($popup.length === 0) {
        // If popup is not visible, exit the function
        return;
    }
 
    const $button = $popup.find('.add-to-cart-popup');
    let basePrice = 0;
    let extrasTotal = 0;
    const mainQuantity = parseInt($popup.find('.qty-input').val()) || 1;
    
    // 1. First check for selected variation price
    const $selectedVariation = $popup.find('.variation-radio:checked');
    if ($selectedVariation.length) {

        const $priceElement = $selectedVariation.closest('.variation-item').find('.variation-price');
        if ($priceElement.length) {
            // If there's a sale price (ins tag), use that, otherwise use the regular price
            if ($priceElement.find('ins').length) {
                basePrice = parsePrice($priceElement.find('ins').text());
                console.log("Base Variation SALE Price:", basePrice);
            } else if ($priceElement.find('del').length) {
                // If there's a <del>, get the text after it (the sale price)
                const priceText = $priceElement.clone().children('del').remove().end().text();
                basePrice = parsePrice(priceText);
            } else {
                basePrice = parsePrice($priceElement.text());
                console.log("Base Variation Regular Price:", basePrice);
            }
        }
    } else {
        // No variation selected, use main product price
        const $mainPriceElement = $popup.find('.price');
        if ($mainPriceElement.length) {
            // If there's a sale price (ins tag), use that
            if ($mainPriceElement.find('ins').length) {
                basePrice = parsePrice($mainPriceElement.find('ins').text());
                console.log("Base Product SALE Price:", basePrice);
            } else {
                // Otherwise use whatever price is displayed (might be regular)
                basePrice = parsePrice($mainPriceElement.text());
                console.log("Base Product Regular Price:", basePrice);
            }
        }
    }
 
    // 2. Add grouped products (extras) to total
    $popup.find('.grouped-product-item input.grouped-product-checkbox:checked').each(function() {
        const $item = $(this).closest('.grouped-product-item');
        const $priceElement = $item.find('.product-price');
        const $qtyInput = $item.find('.grouped-qty');
 
        if ($priceElement.length && $qtyInput.length) {
            let itemPrice = 0;
           
            // If there's a sale price (ins tag), use that
            if ($priceElement.find('ins').length) {
                itemPrice = parsePrice($priceElement.find('ins').text());
                console.log("Grouped Item SALE Price:", itemPrice);
            } else {
                // Otherwise use whatever price is displayed
                itemPrice = parsePrice($priceElement.text());
                console.log("Grouped Item Regular Price:", itemPrice);
            }
           
            const itemQuantity = parseInt($qtyInput.val()) || 1;
            extrasTotal += itemPrice * itemQuantity;
            console.log("Added Grouped Item:", itemPrice, "x", itemQuantity);
        }
    });
 
    // 3. Add product add-ons to total
   $popup.find('.product-addon-radio:checked, .product-addon-checkbox:checked').filter(function () {
    return $(this).data('price') !== undefined && $(this).data('price-type') !== undefined;
}).each(function () {
    const $checkbox = $(this);
    const price = parseFloat($checkbox.data('price')) || 0;
    const priceType = $checkbox.data('price-type');

    console.log("priceType", priceType, extrasTotal);

    if (priceType === 'percentage_based') {
        extrasTotal += basePrice * (price / 100);
    } else {
        extrasTotal += price;
    }
});

 
    // 4. Calculate final total and update button
    const finalTotal = (basePrice + extrasTotal) * mainQuantity;
    console.log("Final Calculation: (", basePrice, "+", extrasTotal, ") *", mainQuantity, "=", finalTotal);
 
    if ($button.length) {
        $button.html(formatPrice(finalTotal));
        console.log("Button updated to:", formatPrice(finalTotal));
    }
}

let updateTimeout;
$(document).on('change', '.variation-radio, .product-addon-radio, .product-addon-checkbox, .qty-input', () => {
  clearTimeout(updateTimeout);
  updateTimeout = setTimeout(updateCartButtonPrice, 100);
});
//   atta code end
function updatePercentageAddonPrice() {
    const $popup = $('.product-quick-view-popup:visible');
    if ($popup.length === 0) return;

    // Find selected variation
    const $selectedVariation = $popup.find('.variation-radio:checked');
    let basePrice = parseFloat($selectedVariation.data('sale-price'));
    if ($selectedVariation.length) {
    
    if (!basePrice || basePrice === 0) {
        basePrice = parseFloat($selectedVariation.data('regular-price')) || 0;
    }

    if (basePrice === 0) {
        console.warn("Base price could not be determined.");
        return;
    }
    }
    
    if (basePrice === 0) return;
  
    $popup.find('.product-addon-radio[data-price-type="percentage_based"], .product-addon-checkbox[data-price-type="percentage_based"]').each(function () {
        const $addonInput = $(this);
        const percent = parseFloat($addonInput.data('price')) || 0;
        const addonPrice = basePrice * (percent / 100);

        const $priceContainer = $addonInput.closest('.product-addon-item').find('.addon-price');
        if ($priceContainer.length) {
            $priceContainer.text('+' + formatPrice(addonPrice));
            $priceContainer.attr('data-calculated', addonPrice); // Optional: for debugging or later use
        }
    });
}


let updateTimeout2;
$(document).on('change', '.variation-radio', () => {
    clearTimeout(updateTimeout2);
    updateTimeout2 = setTimeout(updatePercentageAddonPrice, 100);
});;


        $(document).on('click', '.add-to-cart-popup', function(e) {
            var $button = $(this);
            if ($button.hasClass('loading') || $button.prop('disabled')) {
  				  console.warn('Button is already processing...');
   				 return;
						}
            var productId = $button.data('product-id');
            var isPopup = $button.hasClass('add-to-cart-popup');
            var $productPopup = isPopup ? $button.closest('.product-quick-view-popup') : $('#popup-' + productId);
            var quantity = 1;
            var variationId = null;
            // var groupedProducts = [];
            var customFieldValue = null;
            var originalContent = $button.html();

            $button.addClass('loading').prop('disabled', true);
            console.log("this is button clicked",$button);
            // --- Get Product Data (Same as before) ---
            // Get quantity
            if (isPopup && $productPopup.length) {
                quantity = parseInt($productPopup.find('.qty-input').val()) || 1;
            } else {
                if ($productPopup.length && $productPopup.is(':visible')) {
                     quantity = parseInt($productPopup.find('.qty-input').val()) || 1;
                } else {
                    // Attempt to get quantity from a potential quick view not yet open or default
                     quantity = parseInt($('#popup-' + productId).find('.qty-input').val()) || 1;
                }
            }
             if (isNaN(quantity) || quantity < 1) quantity = 1;

            // Get variation ID
             if ($productPopup.length && $productPopup.find('.variation-radio').length > 0) {
                  variationId = $productPopup.find('.variation-radio:checked').val();
                  if (!variationId) {
                     var defaultVariation = $productPopup.find('.variation-radio[data-is-default="true"]');
                     if (defaultVariation.length) variationId = defaultVariation.val();
                     else variationId = $productPopup.find('.variation-radio').first().val();
                  }
             }

       
        // atta code grouped product start
       let productAddons = {};

// Force DOM state refresh
$productPopup.find('.product-addons-container input[name^="addon-"]').each(function() {
    this.checked = this.checked;
});

$productPopup.find('.product-addons-container input[name^="addon-"]').each(function() {
         var $input = $(this);
         var input_name = $input.attr('name');
         var input_value = $input.val();
         var input_type = $input.attr('type');
         var base_key_name = input_name.replace('[]', '');
         if (input_type === 'checkbox') {
              // For checkboxes, only send if checked
              if ($input.is(':checked') && $input.prop('checked')) {
                  
                   if (productAddons[base_key_name] === undefined) {
                    productAddons[base_key_name] = []; // Initialize as array if first value for this name
                   }
                    productAddons[base_key_name].push(encodeURIComponent(input_value)); 
              }
         } else if (input_type === 'radio') {
              // For radio buttons, only send if checked
            if ($input.is(':checked') && $input.prop('checked')) {
                   // Radio names are like 'addon-XXX-Y' (no [])
                   // Only one radio with the same name can be checked.
                  productAddons[base_key_name] = encodeURIComponent(input_value); // Assign the single checked value
              }
         }
        
    });
     // ** --- End Manual Addon Data Collection --- **
let firstMissingAddonGroup = null;
let missingRequiredAddons = [];

$('.product-addons-group').each(function() {
    var $group = $(this);
    var $requiredInputs = $group.find('input[data-required="1"]');
    
    if ($requiredInputs.length > 0) {
        var groupName = $group.find('.addon-group-title').text().replace('(Required)', '').trim();
        var hasSelection = false;
        
        // Check if any input in this group is selected
        $requiredInputs.each(function() {
            var $input = $(this);
            if ($input.is(':checked')) {
                hasSelection = true;
                return false; // Break out of loop
            }
        });
        
        if (!hasSelection) {
            missingRequiredAddons.push(groupName);
            // Store the first missing addon group for scrolling
            if (firstMissingAddonGroup === null) {
                firstMissingAddonGroup = $group;
            }
        }
    }
});

// If there are missing required addons, show error and return
if (missingRequiredAddons.length > 0) {
    $button.html(originalContent).prop('disabled', false).removeClass('loading');
    let errorMessage = 'Bitte wählen Sie die erforderlichen Add-Ons aus.';
    handleAddToCartError($button, errorMessage, firstMissingAddonGroup);
    return false;
}

        // atta code custom field end
            // Get custom field value
            if (isPopup && $productPopup.length) {
                 var $customField = $productPopup.find('#popup_user_custom_input');
                 if ($customField.length) {
                     customFieldValue = $customField.val();
                 }
            }
            // --- End Get Product Data ---

            // --- Store Closed Check ---
            if (isStoreCurrentlyClosed) {
                console.log('[Store Closed] Add to cart intercepted.');
                e.preventDefault(); // Prevent default action
                e.stopImmediatePropagation(); // Prevent other handlers

                // Store data for the popup confirmation
                preorderDataForConfirmation = {
                    button: $button, // Reference to the clicked button
                    product_id: productId,
                    quantity: quantity,
                    variation_id: variationId,
                    // grouped_products: groupedProducts,
                    custom_field: customFieldValue,
                    
                };

                // Show the pre-order confirmation popup
                $('#preorder-popup-container').show(); // Show container first
                $('.preorder-popup').fadeIn(300); // Then fade in popup
                console.log('[Store Closed] Showing pre-order popup.');
                return false; // Stop further execution of this handler
            }
            // --- End Store Closed Check ---

            // --- Original Add to Cart Logic (Only runs if store is OPEN) ---
            console.log('[Store Open] Proceeding with normal add to cart.');
            e.preventDefault(); // Still prevent default button behavior
            $button.addClass('loading'); // Add loading state

            var data = {
                action: 'woocommerce_ajax_add_to_cart',
                product_id: productId,
                quantity: quantity,
                security: myAjax.cartNonce,
                addons_data: productAddons
            };

            if (variationId) {
                data.variation_id = variationId;
            }
            // if (groupedProducts.length > 0) {
            //     data.grouped_products = groupedProducts;
            // }
             if (customFieldValue) {
                 data.custom_field = customFieldValue;
             }
   console.log('[Original-order] Making AJAX call with data:', JSON.stringify(data));
            // Make the standard AJAX call
            $.ajax({
                url: myAjax.ajaxUrl,
                type: 'POST',
                data: data,
                success: function(response) {
            
                    if (response.error) {
                       $button.prop('disabled', false);
 
                        handleAddToCartError($button, response.message);
                        
                        return;
                    }
                  
 
                    handleAddToCartSuccess($button, $productPopup);

                    // Trigger standard WooCommerce cart update
                    $(document.body).trigger('added_to_cart', [response.fragments, response.cart_hash]);
                    console.log('[Store Open] Triggered added_to_cart');

                    // Update custom cart (Your theme's specific function)
                    if (typeof updateCartContents === 'function') {
                        console.log('[Store Open] Calling custom updateCartContents()...');
                        updateCartContents();
                      }
                  
                 },
                error: function(xhr, status, error) {
                    console.error("[Store Open] Cart error:", error);
                     $button.prop('disabled', false);

                    handleAddToCartError($button, 'Server error occurred');
                },
                complete: function() {
                    $button.prop('disabled', false);
                }
            });
            // --- End Original Add to Cart Logic ---
        });

        // --- Handle pre-order confirmation (Yes button) ---
        // This handler is now always present, but the popup only shows if store is closed
        $(document).on('click', '.preorder-yes-btn', function() {
            console.log('[Pre-order] Yes button clicked.');
            var $popupYesButton = $(this);
             // Hide the popup first
             $('.preorder-popup').fadeOut(300, function(){
                 $('#preorder-popup-container').hide(); // Hide container after fade out
             });


            if (!preorderDataForConfirmation || !preorderDataForConfirmation.product_id) {
                console.error('[Pre-order] No data found to place pre-order.');
                return;
            }

            $popupYesButton.prop('disabled', true).text('Wird verarbeitet...');
            // Also show loading on original button if possible
            var originalButton = preorderDataForConfirmation.button;
            if(originalButton && originalButton.length) {
                 originalButton.addClass('loading');
            }
              // atta code grouped product start
        let productAddons = {};
         $('.product-addons-container').find('input[name^="addon-"]').each(function() {
         var $input = $(this);
         var input_name = $input.attr('name');
         var input_value = $input.val();
         var input_type = $input.attr('type');
         var base_key_name = input_name.replace('[]', '');
         if (input_type === 'checkbox') {
              // For checkboxes, only send if checked
              if ($input.is(':checked')) {
                  
                   if (productAddons[base_key_name] === undefined) {
                    productAddons[base_key_name] = []; // Initialize as array if first value for this name
                   }
                   productAddons[base_key_name].push(input_value);
              }
         } else if (input_type === 'radio') {
              // For radio buttons, only send if checked
              if ($input.is(':checked')) {
                   // Radio names are like 'addon-XXX-Y' (no [])
                   // Only one radio with the same name can be checked.
                   productAddons[base_key_name] = input_value; // Assign the single checked value
              }
         }
        
    });
     // ** --- End Manual Addon Data Collection --- **
            // Prepare data for AJAX call, adding pre-order flag
            var data = {
                action: 'woocommerce_ajax_add_to_cart', // Use the standard action
                product_id: preorderDataForConfirmation.product_id,
                quantity: preorderDataForConfirmation.quantity,
                is_preorder: 1, // The important flag
                addons_data: productAddons,
                security: myAjax.cartNonce
            };

            if (preorderDataForConfirmation.variation_id) {
                data.variation_id = preorderDataForConfirmation.variation_id;
            }
            // if (preorderDataForConfirmation.grouped_products && preorderDataForConfirmation.grouped_products.length > 0) {
            //     data.grouped_products = preorderDataForConfirmation.grouped_products;
            // }
             if (preorderDataForConfirmation.custom_field) {
                 data.custom_field = preorderDataForConfirmation.custom_field;
             }

            console.log('[Pre-order] Making AJAX call with data:', JSON.stringify(data));
            // Make the AJAX call (same endpoint as normal add-to-cart)
            $.ajax({
                url: myAjax.ajaxUrl,
                type: 'POST',
                data: data,
                success: function(response) {
                    console.log('[Pre-order] AJAX Success Response:', response);
                    var $originalProductPopup = originalButton ? (originalButton.hasClass('add-to-cart-popup') ? originalButton.closest('.product-quick-view-popup') : $('#popup-' + preorderDataForConfirmation.product_id)) : null;

                    if (response.error) {
                         console.error('[Pre-order] AJAX returned error:', response.message);
                         // Use the original error handler
                          if (typeof handleAddToCartError === 'function' && originalButton && originalButton.length) {
                            handleAddToCartError(originalButton, response.message);
                          } else {
                             alert('Error placing pre-order: ' + response.message);
                             if(originalButton && originalButton.length) originalButton.removeClass('loading');
                          }
                    } else {
                        console.log('[Pre-order] AJAX success, using original success handler...');
                        // Use the original success handler for consistency
                         if (typeof handleAddToCartSuccess === 'function' && originalButton && originalButton.length) {
                            handleAddToCartSuccess(originalButton, $originalProductPopup);
                         } else if(originalButton && originalButton.length) {
                            // Basic success feedback if handler not found
                            originalButton.removeClass('loading').addClass('added');
                            setTimeout(function() { originalButton.removeClass('added'); }, 1000);
                         }

                         // Trigger standard WooCommerce cart update
                        console.log('[Pre-order] AJAX success, using original success handler..2.22222.');

                        $(document.body).trigger('added_to_cart', [response.fragments, response.cart_hash, originalButton]);   
                        console.log('[Pre-order] Triggered added_to_cart');

                         // Update custom cart (Your theme's specific function)
                         if (typeof updateCartContents === 'function') {
                             console.log('[Pre-order] Calling custom updateCartContents()...');
                             updateCartContents();
                         }
                         
                         // Show pre-order specific notification
                         $('<div class="cart-notification">Vorbestellung erfolgreich aufgegeben!</div>')
                             .appendTo('body')
                             .fadeIn(300)
                             .delay(2000)
                             .fadeOut(300, function() { $(this).remove(); });
                    }
                },
                error: function(xhr, status, error) {
                    console.error("[Pre-order] AJAX Error: Status:", status, "Error:", error, "Response:", xhr.responseText);
                    // Use original error handler
                     if (typeof handleAddToCartError === 'function' && originalButton && originalButton.length) {
                        handleAddToCartError(originalButton, 'AJAX error occurred');
                     } else {
                        alert('An error occurred while placing the pre-order. Please check console (F12).');
                         if(originalButton && originalButton.length) originalButton.removeClass('loading');
                     }
                },
                complete: function() {
                    console.log('[Pre-order] AJAX call complete.');
                    $popupYesButton.prop('disabled', false).text('Ja, Vorbestellung aufgeben');
                    // Clear stored data AFTER ensuring handlers used it
                     setTimeout(function() { preorderDataForConfirmation = {}; }, 100);
                }
            });
          });

        // --- End Handle pre-order confirmation ---

        // Handle pre-order cancellation and close
        $(document).on('click', '.preorder-no-btn, .close-preorder-popup', function() {
             $('.preorder-popup').fadeOut(300, function(){
                 $('#preorder-popup-container').hide(); // Hide container after fade out
             });
                $('.product-quick-view-popup').fadeOut(300, function(){
                     $('#product-quick-view-popup-content').hide(); // Hide container after fade out
                 });
            preorderDataForConfirmation = {}; // Clear stored data
        });

        // Close popup when clicking outside
        $(window).on('click', function(e) {
            if ($(e.target).hasClass('preorder-popup')) {
                 $('.preorder-popup').fadeOut(300, function(){
                     $('#preorder-popup-container').hide(); // Hide container after fade out
                 });
                $('.product-quick-view-popup').fadeOut(300, function(){
                     $('#product-quick-view-popup-content').hide(); // Hide container after fade out
                 });
                preorderDataForConfirmation = {}; // Clear stored data
            }
        });
		let isCartLoadingVisible = false;

		function showCartOverlay() {
			if (isCartLoadingVisible) return;
			$('.custom-cart').addClass('cart-disabled');
			isCartLoadingVisible = true;
		}
		function hideCartOverlay() {
			$('.custom-cart').removeClass('cart-disabled');
			isCartLoadingVisible = false;
		}

        // --- Existing Helper Functions (Keep These) ---
        function handleAddToCartSuccess($button, $popup) {
             if (!$button || !$button.length) return;

    // IMMEDIATELY close popup
			if (
				$popup && $popup.length &&
				$popup.is(':visible') &&
				$button.hasClass('add-to-cart-popup')
			) {
				$popup.fadeOut(200);
			}

			// Optional: reset button state after short delay
			setTimeout(function () {
				  $button.removeClass('loading').prop('disabled', false).addClass('added');
			}, 1000);

            // General notification (Pre-order shows its own specific one)
            // Check if the data storage is empty (meaning it wasn't a pre-order action that just completed)
            if($.isEmptyObject(preorderDataForConfirmation)) {
                $('<div class="cart-notification">Produkt wurde zum Warenkorb hinzugefügt.</div>')
                    .appendTo('body')
                    .fadeIn(300)
                    .delay(1500)
                    .fadeOut(300, function() {
                        $(this).remove();
                    });
            }
        }

function handleAddToCartError($button, message, $scrollToAddon = null) {
    if(!$button || !$button.length) return; // Safety check
    $button.removeClass('loading');
    
    // Remove any existing error messages
    $('.popup-product-details .addon-error-message').remove();
    $('.product-addons-group .addon-error-message').remove();
    
    var $popup = $('.product-quick-view-popup-content');
    
    // If we have a specific addon to scroll to (addon validation error)
    if ($scrollToAddon && $scrollToAddon.length) {
        // Add error message above the first missing addon group
        $scrollToAddon.prepend('<div class="addon-error-message" style="color: red; margin-bottom: 10px; font-size: 14px; font-weight: bold;">' + message + '</div>');
        
        // Scroll to the specific addon group
        if ($popup.length) {
            var addonOffset = $scrollToAddon.position().top;
            var popupScrollTop = $popup.scrollTop();
            var targetScroll = popupScrollTop + addonOffset - 50; // 50px padding from top
            
            $popup.animate({
                scrollTop: targetScroll
            }, 500);
        }
    } else {
        // For other errors (server errors, etc.) - show at bottom and scroll to bottom
        $('.popup-product-details').append('<div class="addon-error-message" style="color: red; margin-top: 10px; font-size: 14px; font-weight: bold;">' + message + '</div>');
        
        // Scroll to bottom of popup
        if ($popup.length) {
            $popup.animate({
                scrollTop: $popup[0].scrollHeight
            }, 500);
        }
    }
    
    // Animate the add-to-cart button
    $button.css('position', 'relative')
        .animate({ left: '-5px' }, 80)
        .animate({ left: '5px' }, 80)
        .animate({ left: '-5px' }, 80)
        .animate({ left: '5px' }, 80)
        .animate({ left: '0px' }, 80);
}

      $(document).on('click', '.quantity-btn', function(e) {
            e.preventDefault();
            showCartOverlay();
            var $button = $(this);
            var cartItemKey = $button.data('cart-item-key');
            var newQuantity = $button.data('quantity');
            
            // Disable button during request
            $button.prop('disabled', true);
            
            $.ajax({
                type: 'POST',
                 url: myAjax.ajaxUrl,
                data: {
                    action: 'update_cart_item_quantity',
                    cart_item_key: cartItemKey,
                    quantity: newQuantity,
                     security: '<?php echo wp_create_nonce("update_cart_item"); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        // Trigger cart update
                        $(document.body).trigger('wc_fragment_refresh');
                        updateCartContents(); 
                        // Update Elementor mini cart if it exists
                        
                    } else {
                        console.log('Error updating cart:', response.data);
                    }
                },
                error: function() {updateCartContents(); 
                    console.log('AJAX error occurred');
                },
                complete: function() {
                    $button.prop('disabled', false);
                }
            });
        });
  
        // Smooth scroll for tags
        $('.category-tab').on('click', function() {
            $('.category-tab').removeClass('active');
            $(this).addClass('active');
            const tagId = $(this).data('tag');
            const $target = $('#tag-' + tagId);
            if ($target.length) {
                const offset = $target.offset().top - 20; // Adjust offset as needed
                $('html, body').animate({scrollTop: offset}, 800);
            } else {
                 console.warn('Target section not found for tag ID:', tagId);
            }
        });

        // Review popup functionality
        

        // Function to update cart items, totals, etc. without page reload (Keep this)
function updateCartContents() {

 showCartOverlay();
            $.ajax({
                url: ajaxUrl, // Use variable defined at the top
                type: 'POST',
                data: {
                    action: 'update_cart_fragments' // Your custom AJAX action
                },
                success: function(response) {
                     console.log('updateCartContents AJAX success:', response);
                    // Update cart items list
                    // Use a more reliable check for emptiness based on cart_count if available
                    if (response.cart_items !== undefined) {
                         if (response.cart_count !== undefined && response.cart_count === 0) {
                              $('#cart-item-list').html('<li class="empty-cart-message">Ihr Warenkorb ist leer</li>');
                              console.log('Cart is empty, message shown.');
                         } else {
                             $('#cart-item-list').html(response.cart_items);
                                if ($('#cart-item-list').find('.clear-cart-link').length === 0) {
                    // Append the clear cart link only once and position it at the right end
                            $('#cart-item-list').append('<a href="#" class="clear-cart-link" style="text-decoration:underline; float:right; margin:10px;">Leeren Sie Ihren Warenkorb</a>');
                        }
                         }
                    } // else: maybe log an error if cart_items is missing?
                    
                    // Update totals
                    if (response.cart_subtotal) {
                        $('#cart-subtotal').html(response.cart_subtotal);
                    }
                    
                    if (response.cart_total) {
                        $('#cart-total').html(response.cart_total);
                    }

                    if (response.cart_standard_fee) {
                        $('#cart-standard-fee').html(response.cart_standard_fee);
                    }

                   

                    
                    
                    // Update WooCommerce mini-cart fragments if they exist and are part of the response
                    if (response.fragments) {
                         console.log('Updating WC fragments...');
                        $.each(response.fragments, function(key, value) {
                            $(key).replaceWith(value);
                        });
                    }
                   

                    // --- NEW LOGIC: Check if cart became empty ---                    
                    if (response.cart_count !== undefined && response.cart_count === 0) {
                        console.log('Cart is now empty via AJAX. Removing vendor restrictions.');
                        // Remove the specific vendor notice more reliably
                        $('.dokan-alert-warning').filter(function() {
                            return $(this).text().indexOf('Sie können nur von einem Geschäft gleichzeitig bestellen.') > -1;
                        }).remove();

                        // Re-enable buttons
                        $('.product-grid .product-quick-view-popup .add-to-cart-popup, .popular-items .add-popular, .product-grid .quick-view-button')
                            .css({ // Reset inline styles
                                'opacity': '', 
                                'cursor': '',
                                'pointer-events': ''
                            })
                            .removeAttr('title'); // Remove the tooltip
                    }
                    // --- END NEW LOGIC ---

                    hideCartOverlay();
                },
                 error: function(xhr, status, error) {
                     hideCartOverlay();
                     console.error('updateCartContents AJAX Error:', status, error);
                 }
            });
        }
$(document).on('click', '.clear-cart-link', function(e) {
                        e.preventDefault();
                        showCartOverlay();
                        // Show loading indicator
                        var $notice = $(this).closest('.dokan-alert');
                        $notice.append('<span class="clear-cart-loading"> <?php _e("Warenkorb wird geleert...", "dokan-custom"); ?></span>');
                        
                        // Make AJAX call to clear the cart
                        $.ajax({
                            url: '<?php echo admin_url('admin-ajax.php'); ?>',
                            type: 'POST',
                            data: {
                                action: 'dokan_custom_clear_cart',
                                security: '<?php echo wp_create_nonce('dokan-custom-clear-cart'); ?>'
                            },
                            success: function(response) {
                                if (response.success) {
                                    // Remove the notice
                                    $notice.slideUp(300, function() {
                                        $(this).remove();
                                    });
                                    
                                    // Re-enable all buttons
                                    $('.product-grid .product-quick-view-popup .add-to-cart-popup, .popular-items .add-popular, .product-grid .quick-view-button')
                                        .css({
                                            'opacity': '',
                                            'cursor': '',
                                            'pointer-events': ''
                                        })
                                        .removeAttr('title');
                                    
                                    // Update cart display - if updateCartContents function exists, use it
                                    if (typeof updateCartContents === 'function') {
                                        updateCartContents();
                                    } else {
                                        // Fallback - reload the page
                                        window.location.reload();
                                    }
                                    
                                    // Show success message
//                                     $('<div class="dokan-alert dokan-alert-success" style="margin-bottom: 20px;">' +
//                                         '<a class="dokan-close" data-dismiss="alert" href="#">&times;</a>' +
//                                         '<strong><?php _e("Success:", "dokan-custom"); ?></strong> ' +
//                                         '<?php _e("Ihr Warenkorb wurde geleert. Sie können nun Artikel aus diesem Geschäft hinzufügen.", "dokan-custom"); ?>' +
//                                         '</div>')
//                                         .insertBefore('.product-grid')
//                                         .delay(5000)
//                                         .fadeOut(500, function() {
//                                             $(this).remove();
//                                         });
                                    
                                    // Also clear mini-cart fragments
                                    $(document.body).trigger('wc_fragment_refresh');
                                } else {
                                    // Show error message
                                    alert('<?php _e("Fehler beim Leeren des Warenkorbs. Bitte versuchen Sie es erneut.", "dokan-custom"); ?>');
                                    $notice.find('.clear-cart-loading').remove();
                                }
                            },
                            error: function() {
                                // Show error message
                                alert('<?php _e("Fehler beim Leeren des Warenkorbs. Bitte versuchen Sie es erneut.", "dokan-custom"); ?>');
                                $notice.find('.clear-cart-loading').remove();
                            }
                        });
                    });
        // Handle incrementing item quantity
//         $(document).on('click', '.increment-item', function(e) {
//             e.preventDefault();
//             var $this = $(this);
//             var cartItemKey = $this.closest('.cart-item').data('cart-item-key');
//             var currentQty = parseInt($this.siblings('span').text());
            
//             $.ajax({
//                 url: myAjax.ajaxUrl,
//                 type: 'POST',
//                 data: {
//                     action: 'woocommerce_update_cart_item', // Standard WC action
//                     cart_item_key: cartItemKey,
//                     quantity: currentQty + 1,
//                      _wpnonce: $( '#woocommerce-cart-nonce' ).val() || myAjax.cartNonce // Get WC nonce
//                 },
//                 success: function(response) {
//                      console.log('Increment success, updating cart contents...');
//                     // Update quantity display immediately for responsive feel
//                     $this.siblings('span').text(currentQty + 1);
                    
//                     // If quantity is now 2, replace trash with decrement
//                     if (currentQty === 1) {
//                         var $removeBtn = $this.siblings('.remove-item');
//                         $removeBtn.removeClass('remove-item').addClass('decrement-item');
//                          // Use a simple minus sign, FontAwesome might not be loaded everywhere
//                          $removeBtn.html('-'); 
//                     }
                    
//                     // Update cart totals using our function
//                     updateCartContents(); 
//                      // Also trigger WC fragment refresh for compatibility
//                      $(document.body).trigger('wc_fragment_refresh');
//                 },
//                  error: function(xhr, status, error) {
//                      console.error('Increment AJAX Error:', status, error);
//                      alert('Fehler beim Aktualisieren der Warenkorbmenge.');
//                  }
//             });
//         });

//         // Handle decrementing item quantity
//         $(document).on('click', '.decrement-item', function(e) {
//             e.preventDefault();
//             var $this = $(this);
//             var cartItemKey = $this.closest('.cart-item').data('cart-item-key');
//             var currentQty = parseInt($this.siblings('span').text());
            
//             if (currentQty > 1) {
//                 $.ajax({
//                     url: myAjax.ajaxUrl,
//                     type: 'POST',
//                     data: {
//                         action: 'woocommerce_update_cart_item', // Standard WC action
//                         cart_item_key: cartItemKey,
//                         quantity: currentQty - 1,
//                          _wpnonce: $( '#woocommerce-cart-nonce' ).val() || myAjax.cartNonce // Get WC nonce
//                     },
//                     success: function(response) {
//                          console.log('Decrement success, updating cart contents...');
//                         // Update quantity display immediately
//                         var newQty = currentQty - 1;
//                         $this.siblings('span').text(newQty);
                        
//                         // If quantity is now 1, replace decrement with trash
//                         if (newQty === 1) {
//                             $this.removeClass('decrement-item').addClass('remove-item');
//                              // Use FontAwesome trash icon
//                              $this.html('<i class="fas fa-trash-alt"></i>'); 
//                         }
                        
//                         // Update cart totals using our function
//                         updateCartContents();
//                          // Also trigger WC fragment refresh for compatibility
//                          $(document.body).trigger('wc_fragment_refresh');
//                     },
//                      error: function(xhr, status, error) {
//                          console.error('Decrement AJAX Error:', status, error);
//                          alert('Error updating cart quantity.');
//                      }
//                 });
//             }
//         });

//         // Handle removing item from cart
//         $(document).on('click', '.remove-item', function(e) {
//             e.preventDefault();
//             var $this = $(this);
//             var cartItemKey = $this.closest('.cart-item').data('cart-item-key');
            
//             $.ajax({
//                 url: myAjax.ajaxUrl,
//                 type: 'POST',
//                 data: {
//                     action: 'woocommerce_remove_cart_item', // Standard WC action
//                     cart_item_key: cartItemKey,
//                      _wpnonce: $( '#woocommerce-cart-nonce' ).val() || myAjax.cartNonce // Get WC nonce
//                 },
//                 success: function(response) {
//                      console.log('Remove success, updating cart contents...');
//                     // Remove item from display with animation
//                     $this.closest('.cart-item').slideUp(300, function() {
//                         $(this).remove();
//                         // Check if cart is now empty and show message if needed
//                         if ($('#cart-item-list').children().length === 0) {
//                             $('#cart-item-list').html('<li class="empty-cart-message">Ihr Warenkorb ist leer.</li>');
//                         }
//                     });
                    
//                     // Update cart totals using our function
//                     updateCartContents();
//                      // Also trigger WC fragment refresh for compatibility
//                      $(document.body).trigger('wc_fragment_refresh');
//                 },
//                  error: function(xhr, status, error) {
//                      console.error('Remove AJAX Error:', status, error);
//                      alert('Error removing item from cart.');
//                  }
//             });
//         });

       

        // Initial cart update when page loads
        updateCartContents();

        // Quantity controls for main product (within popup)
        $(document).on("click", ".minus-qty", function() {
            var $input = $(this).siblings(".qty-input");
            var value = parseInt($input.val());
            if (value > 1) {
                $input.val(value - 1).trigger("change");
            }
        });

        $(document).on("click", ".plus-qty", function() {
            var $input = $(this).siblings(".qty-input");
            var value = parseInt($input.val());
            if (value < 99) { // Assuming max 99
                $input.val(value + 1).trigger("change");
            }
        });

        // Delivery/Pickup toggle functionality
        $('.delivery-pickup button').on('click', function() {
            $('.delivery-pickup button').removeClass('active');
            $(this).addClass('active');

          
            
            // Store the selected option (delivery or pickup)
            var selectedOption = $(this).hasClass('delivery') ? 'delivery' : 'pickup';
            localStorage.setItem('deliveryOption', selectedOption);
            // You might need additional logic here if delivery impacts cart totals
        });

        // Popular products add to cart functionality
        $(document).on('click', '.add-popular', function(e) {
            var $button = $(this);
            var productId = $button.data('product-id');

             // --- Store Closed Check for Popular items ---
             if (isStoreCurrentlyClosed) {
                 console.log('[Store Closed] Add popular item intercepted.');
                 e.preventDefault();
                 e.stopImmediatePropagation();
                 // Store data for the popup confirmation - simplified
                 preorderDataForConfirmation = {
                     button: $button,
                     product_id: productId,
                     quantity: 1, // Popular items are usually added with quantity 1
                     variation_id: null,
                    //  grouped_products: [],
                     custom_field: null
                 };
                 // Show the pre-order confirmation popup
                 $('#preorder-popup-container').show();
                 $('.preorder-popup').fadeIn(300);
                 console.log('[Store Closed] Showing pre-order popup for popular item.');
                 return false; // Stop further execution
             }
             // --- End Store Closed Check ---

             // --- Original Add Popular Logic (Only runs if store is OPEN) ---
             console.log('[Store Open] Proceeding with adding popular item.');
             e.preventDefault(); // Still prevent default
             $button.addClass('loading');

             $.ajax({
                 url: myAjax.ajaxUrl,
                 type: 'POST',
                 data: {
                     action: 'woocommerce_ajax_add_to_cart',
                     product_id: productId,
                     quantity: 1,
                     security: myAjax.cartNonce
                 },
                 success: function(response) {
                     if (response.error) {
                        // Use the error handler
                         handleAddToCartError($button, response.message);
                     } else {
                         // Use existing success handler
                         handleAddToCartSuccess($button, null); // No popup context for popular items

                         // Trigger standard WooCommerce cart update
                         $(document.body).trigger('added_to_cart', [response.fragments, response.cart_hash]);
                         console.log('[Store Open - Popular] Triggered added_to_cart');

                         // Update custom cart (Your theme's specific function)
                         if (typeof updateCartContents === 'function') {
                             console.log('[Store Open - Popular] Calling custom updateCartContents()...');
                             updateCartContents();
                         }
                     }
                 },
                 error: function() {
                     handleAddToCartError($button, 'Error adding product to cart');
                 }
                 // Complete removes loading via success/error handlers
             });
         });


        // --- Review tabs functionality ---
        // ... (Keep existing review sorting logic) ...
        // --- Reviewed product click ---
        // ... (Keep existing reviewed product click logic) ...
        // --- Slider Navigation ---
        // ... (Keep existing slider init logic) ...


    }); // End jQuery(document).ready

    </script>
    

    <script>
   jQuery(document).ready(function($) {

   $(document).on('click', '.elementor-menu-cart__toggle', function() {
        $('#popup_cart').hide();
        console.log('Custom cart hidden via toggle click');
    });
    
    $(document).on('click', '.elementor-menu-cart__close-button', function() {
        $('#popup_cart').show();
        console.log('Custom cart shown via close click');
    });
    $(document).on('click keyup', function() {
        $('#popup_cart').show();
        console.log('Custom cart shown via close click');
    });
    
    // Also hide when clicking on cart icon/button
    $(document).on('click', '[data-elementor-open-modal="popup_cart"], .elementor-menu-cart__toggle_button', function() {
        setTimeout(function() {
            $('#popup_cart').hide();
        }, 50);
    });
  

    // Main slider initialization function
    function initSliderNavigation() {
        // Initialize each slider separately by looping through all instances
        jQuery('.reviewed-products').each(function() {
            const $container = jQuery(this);
            const sliderId = $container.data('slider-id');
            const slider = $container.find('.reviewed-products-slider');
            const prevBtn = $container.find('.prev-btn');
            const nextBtn = $container.find('.next-btn');
            
            if (!slider.length || !prevBtn.length || !nextBtn.length) return;
            
            // Count the products in this slider
            const productCount = slider.find('.reviewed-product').length;
            
            // Simple rule: Show navigation if 2+ products, hide if only 1
            if (productCount <= 1) {
                // Hide navigation for 1 or 0 products
                $container.find('.slider-navigation').hide();
                return; // Skip further setup for this slider
            } else {
                // Show navigation for 2+ products
                $container.find('.slider-navigation').show();
            }
            
            // Force enable buttons initially
            prevBtn.prop('disabled', false);
            nextBtn.prop('disabled', false);
            
            // Modified update function to handle edge cases better
            function updateButtonStates() {
                const scrollLeft = slider.scrollLeft();
                const maxScroll = slider[0].scrollWidth - slider[0].clientWidth;
                
                // Only disable if truly at the edge and there are enough items to scroll
                if (slider[0].scrollWidth <= slider[0].clientWidth) {
                    // Not enough content to scroll
                    prevBtn.prop('disabled', true);
                    nextBtn.prop('disabled', true);
                } else {
                    // Check if at start or end
                    prevBtn.prop('disabled', scrollLeft <= 5); // Allow small margin for error
                    nextBtn.prop('disabled', scrollLeft >= maxScroll - 5); // Allow small margin for error
                }
            }
            
            // Initialize the interval to check button states periodically
            const intervalId = setInterval(updateButtonStates, 100); // Update every 100ms
            
            // Clear the interval after a certain time or when page is unloaded
            jQuery(window).on('beforeunload', function() {
                clearInterval(intervalId);
            });

            // Update on scroll - using namespaced event to avoid conflicts
            slider.off('scroll.slider-' + sliderId).on('scroll.slider-' + sliderId, updateButtonStates);
            
            // Improved click handlers
            prevBtn.off('click.slider-' + sliderId).on('click.slider-' + sliderId, function() {
                const itemWidth = slider.find('.reviewed-product').outerWidth(true);
                slider.animate({scrollLeft: '-=' + itemWidth}, 300, updateButtonStates);
            });
            
            nextBtn.off('click.slider-' + sliderId).on('click.slider-' + sliderId, function() {
                const itemWidth = slider.find('.reviewed-product').outerWidth(true);
                slider.animate({scrollLeft: '+=' + itemWidth}, 300, updateButtonStates);
            });
            
            // Update on window resize and content changes - using namespaced event
            jQuery(window).off('resize.slider-' + sliderId).on('resize.slider-' + sliderId, updateButtonStates);
        });
        $('.review-tab').on('click', function() {
            $('.review-tab').removeClass('active');
            $(this).addClass('active');
        });
    }
    
    // Run on DOM ready
    initSliderNavigation();
    
    // Also run after page is fully loaded with images
    $(window).on('load', function() {
        initSliderNavigation();
    });

    // Reinitialize when specific popups are shown
    $(document).on('click', '.user-reviews-link, .show-reviews-btn', function() {
        initSliderNavigation();
    });

    // Add event listener to reinitialize slider when a tab is clicked
    $('.review-tabs .review-tab').on('click', function() {
        // Sort reviews first (you can call your sort function here)
        var selectedTab = $(this).text().toLowerCase();
        sortReviews(selectedTab);

        // Reinitialize slider after sorting/review tab change
        initSliderNavigation();
    });

    $('.show-reviews-popup').on('click', function(e) {
            e.preventDefault();
            $('#reviews-popup').fadeIn(300);
            initSliderNavigation(); // Initialize sliders when popup opens
            sortReviews('top reviews'); // Sort reviews when popup opens
        });

        $('.close-popup-reviews').on('click', function() {
            $('#reviews-popup').fadeOut(300);
        });

        $(window).on('click', function(e) {
            if ($(e.target).hasClass('reviews-popup-overlay')) {
                $('#reviews-popup').fadeOut(300);
            }
        });
    // Sorting function (based on your existing sorting logic)
    function sortReviews(selectedTab) {
        var $reviewsList = $('.reviews-list');
        var $reviews = $('.review-item').get();
        
        $reviews.sort(function(a, b) {
            var $a = $(a);
            var $b = $(b);
            
            switch(selectedTab) {
                case 'newest':
                    return new Date($b.data('date')) - new Date($a.data('date'));
                case 'highest rating':
                    return $b.data('rating') - $a.data('rating');
                case 'lowest rating':
                    return $a.data('rating') - $b.data('rating');
                case 'top reviews':
                default:
                    // Sort by rating first, then by date for same ratings
                    var ratingDiff = $b.data('rating') - $a.data('rating');
                    if (ratingDiff === 0) {
                        return new Date($b.data('date')) - new Date($a.data('date'));
                    }
                    return ratingDiff;
            }
        });
        
        // Empty the existing reviews and append the sorted ones
        $reviewsList.empty();
        $.each($reviews, function(index, item) {
            $reviewsList.append(item);
        });
        
        // Add animation class to reviews
        $('.review-item').addClass('review-animation');
        setTimeout(function() {
            $('.review-item').removeClass('review-animation');
        }, 300);
    }
    
});
</script>

<?php
}

// AJAX handlers for cart functionality
add_action('wp_ajax_woocommerce_ajax_add_to_cart', 'custom_ajax_add_to_cart');
add_action('wp_ajax_nopriv_woocommerce_ajax_add_to_cart', 'custom_ajax_add_to_cart');

function custom_ajax_add_to_cart() {
    // Add nonce verification for security
    if (!isset($_POST['security']) || !wp_verify_nonce($_POST['security'], 'woocommerce-cart')) {
        wp_send_json(array(
            'error' => true,
            'message' => __('Invalid security token', 'woocommerce')
        ));
        wp_die();
    }

    $product_id = apply_filters('woocommerce_add_to_cart_product_id', absint($_POST['product_id']));
    $quantity = empty($_POST['quantity']) ? 1 : wc_stock_amount(wp_unslash($_POST['quantity']));
    $variation_id = isset($_POST['variation_id']) ? absint($_POST['variation_id']) : 0;
    
    // Get the product
    $product = wc_get_product($product_id);
    
    if (!$product) {
        wp_send_json(array(
            'error' => true,
            'message' => __('Produkt nicht gefunden.', 'woocommerce')
        ));
        wp_die();
    }
    
    $added_to_cart = false;
 
        if (isset($_POST['addons_data']) && is_array($_POST['addons_data'])) {
            foreach ($_POST['addons_data'] as $addon_name => $addon_value) {
                // Copy the addon data from the nested array to the top level of $_POST
                $_POST[$addon_name] = $addon_value;
            }
           
        }
        
    $cart_item_data = array();
    
    // Handle variable product
    if ($product->is_type('variable')) {
        if (!$variation_id) {
            // Get the default variation or lowest price variation
            $available_variations = $product->get_available_variations();
            if (!empty($available_variations)) {
                $lowest_price = PHP_FLOAT_MAX;
                foreach ($available_variations as $variation) {
                    $variation_price = floatval($variation['display_price']);
                    if ($variation_price < $lowest_price) {
                        $lowest_price = $variation_price;
                        $variation_id = $variation['variation_id'];
                        $variation_data = $variation['attributes'];
                    }
                }
            }
        }
        
        if ($variation_id) {
            // Get variation attributes
            $variation = array();
            $variation_data = wc_get_product_variation_attributes($variation_id);
            foreach ($variation_data as $attr_name => $attr_value) {
                $variation['attribute_' . $attr_name] = $attr_value;
            }
          

            $added_to_cart = WC()->cart->add_to_cart($product_id, $quantity, $variation_id, $variation, $cart_item_data);
        }
    } else {
    

        $added_to_cart = WC()->cart->add_to_cart($product_id, $quantity, 0 , array(), $cart_item_data);
    }
    
    
    if ($added_to_cart) {
        do_action('woocommerce_ajax_added_to_cart', $product_id);
        
        // Get updated cart fragments
        ob_start();
        woocommerce_mini_cart();
        $mini_cart = ob_get_clean();
        
        wp_send_json(array(
            'success' => true,
            'cart_hash' => WC()->cart->get_cart_hash(),
            'cart_quantity' => WC()->cart->get_cart_contents_count(),
            'fragments' => array(
                'div.widget_shopping_cart_content' => '<div class="widget_shopping_cart_content">' . $mini_cart . '</div>'
            )
        ));
    } else {
        wp_send_json(array(
            'error' => true,
            'message' => __('Fehler beim Hinzufügen des Produkts zum Warenkorb.', 'woocommerce')
        ));
    }
    
    wp_die();
}

add_action('wp_ajax_update_cart_fragments', 'get_cart_fragments');
add_action('wp_ajax_nopriv_update_cart_fragments', 'get_cart_fragments');
function get_cart_fragments() {
    $fragments = array();
    
    ob_start();
    woocommerce_mini_cart();
    $mini_cart = ob_get_clean();
    
    $fragments['div.widget_shopping_cart_content'] = '<div class="widget_shopping_cart_content">' . $mini_cart . '</div>';
    
    // Add cart count and totals
    $fragments['cart_count'] = WC()->cart->get_cart_contents_count();
    $fragments['cart_subtotal'] = WC()->cart->get_cart_subtotal();
    $fragments['cart_total'] = WC()->cart->get_total();
    $delivery_fee = 0;

ob_start(); 
if (!WC()->cart->is_empty()) { 
    foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) { 
        $_product = $cart_item['data']; 
        if ($_product) { 
            $quantity = $cart_item['quantity']; 
            echo '<li class="cart-item" data-cart-item-key="' . esc_attr($cart_item_key) . '">'; 
            echo '<div class="item-details">'; 
            echo '<div class="item-left">'; 
            echo '<div class="item-image">'; 
            echo $_product->get_image('thumbnail'); 
            echo '</div>'; 
            echo '<div class="item-info">'; 
            
            // Get the base product price for display
            $base_display_price = 0;
            if (isset($cart_item['variation_id']) && $cart_item['variation_id']) {
                // For variation products, get variation price
                $variation = wc_get_product($cart_item['variation_id']);
                if ($variation) {
                    $base_display_price = $variation->get_price();
                }
            } else {
                // For simple products
                $product = wc_get_product($cart_item['product_id']); 
                if ($product) { 
                    $base_display_price = $product->get_price(); 
                }
            }
            
            // Display product name with base price
            echo '<div class="item-name">' . $_product->get_name() . ' - ' . wc_price($base_display_price) . '</div>';
            
            if (!empty($cart_item['addons'])) { 
                // Get the correct base price for addon calculations
                $base_product_price = null;
                
                // Method 1: Check if base price is stored in cart item
                if (isset($cart_item['base_price'])) { 
                    $base_product_price = $cart_item['base_price']; 
                } 
                // Method 2: Get the original product/variation price (BEFORE addons)
                else { 
                    // For variations, get the variation price
                    if (isset($cart_item['variation_id']) && $cart_item['variation_id']) {
                        $variation = wc_get_product($cart_item['variation_id']);
                        if ($variation) {
                            $base_product_price = $variation->get_price();
                        }
                    } 
                    // For simple products
                    else {
                        $product = wc_get_product($cart_item['product_id']); 
                        if ($product) { 
                            $base_product_price = $product->get_price(); 
                        }
                    }
                }
                
                // Method 3: Fallback - calculate base price by subtracting fixed addons
                if (empty($base_product_price)) { 
                    $base_product_price = $cart_item['data']->get_price();
                    
                    // Subtract only fixed addon prices to get base price
                    foreach ($cart_item['addons'] as $temp_addon) { 
                        if (!empty($temp_addon['value']) && 
                            (!isset($temp_addon['price_type']) || $temp_addon['price_type'] !== 'percentage_based')) { 
                            $base_product_price -= $temp_addon['price']; 
                        } 
                    } 
                }
                
                // Display addons with correct pricing multiplied by quantity
                foreach ($cart_item['addons'] as $addon) { 
                    if (!empty($addon['value'])) { 
                        if (isset($addon['price_type']) && $addon['price_type'] == 'percentage_based') { 
                            // Calculate percentage based on the correct base price
                            $addon_price = ($base_product_price * $addon['price']) / 100; 
                        } else { 
                            // Direct price for fixed addons
                            $addon_price = $addon['price']; 
                        }
                        
                        // Multiply addon price by quantity for total addon cost
                        $total_addon_price = $addon_price * $quantity;
                        
                        echo '<div class="addon-name" style="font-size: 12px;"> ' . 
                             esc_html($addon['value']) . ' ' . wc_price($total_addon_price) . 
                             ($quantity > 1 ? ' (' . wc_price($addon_price) . ' x ' . $quantity . ')' : '') . 
                             '</div>'; 
                    } 
                } 
            } 
            
            echo '</div>'; 
            echo '</div>'; 
            echo '<div class="item-controls">'; 
            echo '<div class="price-quantity">'; 
            echo '<div class="item-price" style="display:flex">' . "Artikelsumme&nbsp" .  ' ' . wc_price($_product->get_price() * $quantity) . '</div>'; 
            echo '<div class="item-quantity">'; 
           	echo '<button class="quantity-btn decrement-btn ' . ($quantity == 1 ? 'remove-item' : 'decrement-item') . '" 
					data-product-id="' . esc_attr($_product->get_id()) . '" 
					data-cart-item-key="' . esc_attr($cart_item_key) . '" 
					data-quantity="' . esc_attr($quantity - 1) . '">';
			echo ($quantity == 1 ? '<i class="fas fa-trash-alt" style="font-size: 10px;"></i>' : '-');
			echo '</button>';

			echo '<span class="quantity-display" style="min-width: 20px; text-align: center; font-weight: bold;">' . $quantity . '</span>';

			echo '<button class="quantity-btn increment-btn increment-item" 
					data-product-id="' . esc_attr($_product->get_id()) . '" 
					data-cart-item-key="' . esc_attr($cart_item_key) . '" 
					data-quantity="' . esc_attr($quantity + 1) . '">+</button>';
            echo '</div>'; 
            echo '</div>'; 
            echo '</div>'; 
            echo '</div>'; 
            echo '</li>'; 
        } 
    } 
} else { 
    echo '<li class="empty-cart-message">Your cart is empty</li>'; 
} 

$fragments['cart_items'] = ob_get_clean();
wp_send_json($fragments); 
wp_die();  
}

add_action('wp_ajax_woocommerce_update_cart_item', 'update_cart_item_quantity');
add_action('wp_ajax_nopriv_woocommerce_update_cart_item', 'update_cart_item_quantity');
function update_cart_item_quantity() {
    $cart_item_key = sanitize_text_field($_POST['cart_item_key']);
    $quantity = intval($_POST['quantity']);
    
    if ($quantity > 0) {
        // WC()->cart->set_quantity($cart_item_key, $quantity);
        $result = WC()->cart->set_quantity( $cart_item_key, $quantity, true );
        if ( $result !== false ) { 
           
            do_action('woocommerce_ajax_cart_item_quantity_updated', $cart_item_key, $quantity);
       }
        wp_send_json(array(
            'success' => true,
            'cart_hash' => WC()->cart->get_cart_hash()
        ));
    } else {
        wp_send_json(array(
            'success' => false,
            'message' => 'Invalid quantity'
        ));
    }
    
    wp_die();
}

add_action('wp_ajax_woocommerce_remove_cart_item', 'remove_cart_item');
add_action('wp_ajax_nopriv_woocommerce_remove_cart_item', 'remove_cart_item');
function remove_cart_item() {
    $cart_item_key = sanitize_text_field($_POST['cart_item_key']);
    $removed = WC()->cart->remove_cart_item($cart_item_key);
    
    if ( $$removed !== false ) { 
        
        do_action('woocommerce_ajax_cart_item_quantity_updated', $cart_item_key, $quantity);
   }
    wp_send_json(array(
        'success' => $removed,
        'cart_hash' => WC()->cart->get_cart_hash()
    ));
    
    wp_die();
}


function custom_get_seller_rating_counts($seller_id) {
    // Get all products by this seller
    $args = array(
        'post_type' => 'product',
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'author' => $seller_id,
    );
    
    $products = get_posts($args);
    $rating_counts = array(5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0);
    $total_count = 0;
    
    foreach ($products as $product) {
        // Get product reviews/ratings
        $comments = get_comments(array(
            'post_id' => $product->ID,
            'status' => 'approve'
        ));
        
        foreach ($comments as $comment) {
            $rating = get_comment_meta($comment->comment_ID, 'rating', true);
            if ($rating) {
                $rating = intval($rating);
                if (isset($rating_counts[$rating])) {
                    $rating_counts[$rating]++;
                    $total_count++;
                }
            }
        }
    }
    
    return array(
        'counts' => $rating_counts,
        'total' => $total_count
    );
}





// Add a store closed banner and pre-order popup at the top of the store page




/**
 * Add a store closed notice banner at the top of the store page if closed.
 */
add_action('dokan_store_profile_frame_after', 'dokan_custom_store_closed_notice_banner', 5, 2);

function dokan_custom_store_closed_notice_banner($store_user, $store_info) {
    // Check if Dokan function exists and if store is closed
    if ($store_user && function_exists('dokan_is_store_open') && !dokan_is_store_open($store_user->ID)) {
        ?>
        <div class="store-closed-notice">
            <div class="store-closed-content">
                <span class="store-closed-icon">⚠️</span>
                <div class="store-closed-text">
                   <h3><?php esc_html_e('Dieser Shop ist derzeit geschlossen', 'dokan-lite'); ?></h3>
<p><?php esc_html_e('Sie können weiterhin Produkte durchsuchen. Bestellungen, die jetzt aufgegeben werden, gelten als Vorbestellungen und werden bearbeitet, sobald der Shop wieder geöffnet ist.', 'dokan-lite'); ?></p>

                </div>
                <button id="understand-closed-btn"><?php esc_html_e('Ich verstehe', 'dokan-lite'); ?></button>
            </div>
        </div>
        <?php
        // Note: The necessary CSS for .store-closed-notice is already added by the main JavaScript block.
        // The JS to hide this banner on #understand-closed-btn click is also in the main JS block.
    }
}

/**
 * Prevent adding products from multiple vendors to the cart.
 * Hook into the validation step before adding to cart.
 */
add_filter( 'woocommerce_add_to_cart_validation', 'dokan_custom_only_one_vendor_at_a_time', 10, 3 );

function dokan_custom_only_one_vendor_at_a_time( $passed, $product_id, $quantity ) {
    // If validation already failed, don't do anything
    if ( ! $passed ) {
        return false;
    }

    // Check if the cart is empty
    if ( WC()->cart->is_empty() ) {
        return $passed; // Cart is empty, allow adding
    }

    // Get the vendor ID of the product being added
    $new_product_vendor_id = get_post_field( 'post_author', $product_id );

    // Get the vendor ID from the first item currently in the cart
    $cart_items = WC()->cart->get_cart();
    $first_cart_item = reset( $cart_items ); // Get the first item
    $existing_vendor_id = get_post_field( 'post_author', $first_cart_item['product_id'] );

    // Compare vendor IDs
    if ( $new_product_vendor_id != $existing_vendor_id ) {
        // Vendor IDs don't match, prevent adding to cart and show error
        wc_add_notice( __( 'You can only order from one store at a time. Please complete your order from the current store or clear your cart before adding items from another store.', 'dokan-custom' ), 'error' );
        $passed = false; // Mark validation as failed
    }

    return $passed;
}

/**
 * Display a notice on the store page if the cart contains items from another vendor.
 */
add_action('dokan_store_profile_frame_after', 'dokan_custom_different_vendor_notice', 6, 2); // Priority 6 to show after closed notice

function dokan_custom_different_vendor_notice($store_user, $store_info) {
    // Check if cart is not empty and necessary functions exist
    if ( ! WC()->cart->is_empty() && function_exists('dokan_get_store_info') ) {
        // Get the current store's vendor ID
        $current_store_vendor_id = $store_user->ID;

        // Get the vendor ID from the first item currently in the cart
        $cart_items = WC()->cart->get_cart();
        $first_cart_item = reset( $cart_items );
        $existing_vendor_id = get_post_field( 'post_author', $first_cart_item['product_id'] );

        // Compare vendor IDs
        if ( $current_store_vendor_id != $existing_vendor_id ) {
            // Get the store name of the vendor whose items are in the cart
            $existing_vendor_store_info = dokan_get_store_info( $existing_vendor_id );
            $existing_vendor_store_name = isset( $existing_vendor_store_info['store_name'] ) ? $existing_vendor_store_info['store_name'] : __( 'another store', 'dokan-custom' );

            ?>
            <div class="dokan-alert dokan-alert-warning" style="margin-bottom: 20px;">
                 <a class="dokan-close" data-dismiss="alert" href="#">&times;</a>
                <strong><?php _e( 'Please Note:', 'dokan-custom' ); ?></strong>
                <?php
                printf(
                    /* translators: %s: Name of the store whose items are in the cart. */
                    __( ' Ihr Warenkorb enthält Artikel von "%s". Sie können nur von einem Geschäft gleichzeitig bestellen. Bitte schließen Sie Ihre vorherige Bestellung ab oder <a href="#" class="clear-cart-link" style="text-decoration:underline;">leeren Sie Ihren Warenkorb</a>, bevor Sie Artikel von diesem Geschäft hinzufügen.', 'dokan-custom' ),
                    esc_html( $existing_vendor_store_name )
                );

                
                ?>
            </div>
            <?php
           $session_postal_code = WC()->session->get('postal_code');

            // Check if a postal code is set in the session
            if ( ! empty( $session_postal_code ) ) {
                $cart_vendor_postal_prices = ( $existing_vendor_id && get_userdata($existing_vendor_id) ) ? get_user_meta( $existing_vendor_id, '_vendor_postal_prices', true ) ?: [] : [];
               if ( ! is_array($cart_vendor_postal_prices) || ! array_key_exists( $session_postal_code, $cart_vendor_postal_prices ) ) {
                    // Postal code is in session but not valid for the vendor in the cart. Display the secondary notice.
                    ?>
                    <div class="dokan-alert dokan-alert-info" style="margin-bottom: 20px;">
                        <a class="dokan-close" data-dismiss="alert" href="#">&times;</a>
                        <strong><?php _e( 'Lieferbereich Hinweis:', 'my-dokan-postal-fees' ); ?></strong>
                        <?php
                        printf(
                            /* translators: 1: Selected postal code, 2: Name of the store whose items are in the cart, 3: Formatted zero price. */
                            __( 'Der ausgewählte Lieferbereich (%1$s) ist derzeit nicht für Artikel von "%2$s" in Ihrem Warenkorb verfügbar. Die Liefergebühr für Ihren aktuellen Warenkorb beträgt %3$s.', 'my-dokan-postal-fees' ),
                            esc_html( $session_postal_code ),
                            esc_html( $existing_vendor_store_name ),
                            wc_price(0) // Display zero price formatted correctly
                        );
                        ?>
                    </div>
                    <?php
                }
            }
?>
            <script type="text/javascript">
                // Optional: Disable add to cart buttons visually as well
                jQuery(document).ready(function($) {
                    // Disable buttons
                    $('.product-grid .product-quick-view-popup .add-to-cart-popup, .popular-items .add-popular').css({
                        'opacity': '0.5',
                        'cursor': 'not-allowed',
                        'pointer-events': 'none' // Prevent clicks
                    }).attr('title', '<?php esc_attr_e( 'Leeren Sie zuerst den Warenkorb des anderen Geschäfts.', 'dokan-custom' ); ?>');
                    // Also disable the quick view button as adding from there is blocked
                    $('.product-grid .quick-view-button').css({
                        'opacity': '0.5',
                        'cursor': 'not-allowed',
                        'pointer-events': 'none'
                    }).attr('title', '<?php esc_attr_e( 'Leeren Sie zuerst den Warenkorb des anderen Geschäfts.', 'dokan-custom' ); ?>');
                    
                    // Add click handler for the "clear cart" link
                    
                });
            </script>
            <?php
        }
    }
}

// Add AJAX handler for clearing cart
add_action('wp_ajax_dokan_custom_clear_cart', 'dokan_custom_clear_cart_ajax');
add_action('wp_ajax_nopriv_dokan_custom_clear_cart', 'dokan_custom_clear_cart_ajax');

function dokan_custom_clear_cart_ajax() {
    // Verify nonce
    if (!isset($_POST['security']) || !wp_verify_nonce($_POST['security'], 'dokan-custom-clear-cart')) {
        wp_send_json_error(array('message' => __('Security check failed', 'dokan-custom')));
        wp_die();
    }
    
    // Check if WC is active and cart is available
    if (function_exists('WC') && isset(WC()->cart)) {
        // Empty the cart
        WC()->cart->empty_cart();
        
        // Return success response
        wp_send_json_success(array(
            'message' => __('Cart cleared successfully', 'dokan-custom'),
            'cart_count' => 0
        ));
    } else {
        wp_send_json_error(array('message' => __('WooCommerce-Warenkorb nicht verfügbar.', 'dokan-custom')));
    }
    
    wp_die();
}

// anas code




// attar code

/**
 * Enqueue scripts and localize data for the postal code selector.
 */
add_action('wp_enqueue_scripts', 'mydokanpf_enqueue_postal_scripts');
function mydokanpf_enqueue_postal_scripts() {
    // We need this script primarily on the vendor store page
    if ( ! function_exists('dokan_is_store_page') || ! dokan_is_store_page() ) {
        // You might want it on cart/checkout too if you add a "change location" button there
        // if( !is_cart() && !is_checkout() ) {
             return;
        // }
    }
    global $mydokanpf_postal_code;
    // 1. Get Vendor Data (Only if on Store Page)
    $vendor_postal_prices = [];
   
    $vendor_id = get_query_var('author');
    error_log('vendor id from url: ' . $vendor_id); // Debug
             if ( $vendor_id ) {
                
                 $vendor_postal_prices = get_user_meta( $vendor_id, '_vendor_postal_prices', true ) ?: [];
             }
           
 
       
 else {
        // If loading on cart/checkout, maybe get vendor from cart session?
        $vendor_id = WC()->session ? WC()->session->get('vendor_id') : null;
        if ($vendor_id > 0) {
           //  $vendor_id = absint($delivery_context['vendor_id']);
             $vendor_postal_prices = get_user_meta( $vendor_id, '_vendor_postal_prices', true ) ?: [];
        }
    }
 

    error_log('The postal code from dokan single vendor page ' . $mydokanpf_postal_code); // DEBUG

    // 3. Enqueue the script
    wp_enqueue_script(
        'mydokanpf-postal-selector',
        plugin_dir_url( __FILE__ )  . 'postal-selector.js', // Path within your plugin
        ['jquery', 'wp-util'],
        '1.0.1', // Increment version on changes
        true
    );
    $is_pickup = WC()->session->__isset('is_pickup_order') && WC()->session->get('is_pickup_order');
 
    // 4. Localize data for the script
    wp_localize_script('mydokanpf-postal-selector', 'postalCodeData', [       
        'ajaxUrl'           => admin_url('admin-ajax.php'),
        'postalNonce'       => wp_create_nonce('postal_code_set_nonce'),
        'pickupNonce'       => wp_create_nonce('set_pickup_nonce'),
		'isPickup'          => $is_pickup,
        'vendorId'          => $vendor_id, // Current vendor context
        'vendorPostalPrices'=> $vendor_postal_prices, // Current vendor's data
        'currentUserPostal' => $mydokanpf_postal_code,
        'promptTitle'       => __('Wählen Sie Ihr Liefergebiet aus', 'my-dokan-postal-fees'),
        'selectLabel'       => __('-- Postleitzahl auswählen --', 'my-dokan-postal-fees'),
        'feeLabel'          => __('Gebühr:', 'my-dokan-postal-fees'),
        'cartUpdatingText'  => __('Warenkorb wird aktualisiert …', 'my-dokan-postal-fees'),
        'textDomain'        => 'my-dokan-postal-fees', // Pass text domain for JS localization
        'debug'             => defined('WP_DEBUG') && WP_DEBUG
    ]);
 
    // Enqueue a simple CSS file for basic popup styling (optional)
    // wp_enqueue_style('mydokanpf-postal-style', MYDOKANPF_PLUGIN_URL . 'css/postal-popup.css');
}
 
/**
* AJAX handler to save the selected postal code and vendor context to the session.
*/
add_action('wp_ajax_set_user_postal_code', 'mydokanpf_ajax_set_user_postal_code');
add_action('wp_ajax_nopriv_set_user_postal_code', 'mydokanpf_ajax_set_user_postal_code'); // For non-logged-in users
 
function mydokanpf_ajax_set_user_postal_code() {
    // 1. Verify Nonce
    check_ajax_referer('postal_code_set_nonce', 'security');
 
    // 2. Check Session
    if ( ! function_exists('WC') || ! WC()->session ) {
        wp_send_json_error(['message' => __('Session not available.', 'my-dokan-postal-fees')], 400);
        return;
    }
try {
     if (WC()->session->get('is_pickup_order')) {
        WC()->session->__unset('is_pickup_order');
	 }
    // 3. Sanitize Input
    $code = isset($_POST['postal_code']) ? sanitize_text_field($_POST['postal_code']) : '';
    $code_label = isset($_POST['postal_label']) ? sanitize_text_field($_POST['postal_label']) : '';

    WC()->session->set('postal_code', $code);
    WC()->session->set('postal_label', $code_label);
    if (WC()->cart && !WC()->cart->is_empty()) {
        WC()->cart->calculate_totals();
    }
   wp_send_json_success([
        'message'   => __('Postal code updated.', 'my-dokan-postal-fees'),
        'new_code'  => $code,
        'postal_label' => $code_label,
    ]);
}
catch (Exception $e) {
        // Handle any unexpected errors
        wp_send_json_error([
            'message' => __('An error occurred while updating postal code.', 'my-dokan-postal-fees'),
            'debug' => 'Exception: ' . $e->getMessage()
        ], 500);
    }
 
    // Ensure we always exit after AJAX
    wp_die();
}
 
 
/**
 * Add delivery fee based on selected postal code & vendor context stored in session.
 * Accounts for the "one vendor per cart" rule.
 *
 * Hook: woocommerce_cart_calculate_fees
 * @param WC_Cart $cart The cart object.
 */
add_action('woocommerce_cart_calculate_fees', 'mydokanpf_add_postal_code_fee', 20, 1); // Priority 20
function mydokanpf_add_postal_code_fee($cart) {
    $pick_up_order = WC()->session->get('is_pickup_order');
    if ($pick_up_order) {
        return;
    }
  
    if (is_admin() && !defined('DOING_AJAX')) {
        return;
    }

    // Ensure WC and session are ready
    if (!function_exists('WC') || !WC()->session) {
        return;
    }
   $postal_code = WC()->session->get('postal_code');
    // Determine the vendor ID relevant for fee calculation (always based on cart contents)
    $vendor_id = null;
    foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
        $product_id = $cart_item['product_id'];
        $author_id = get_post_field('post_author', $product_id);
        // Assuming one vendor per cart, we just need the first one
        $vendor_id = $author_id;
        break; // Exit loop after finding the first item's vendor
    }
  if (empty($postal_code) || empty($vendor_id)) {
        error_log("[Postal Fee] Skipping fee calculation: No session postal code or no cart vendor.");
        return;
    }
   if ( ! get_userdata($vendor_id) ) {
         error_log("[Postal Fee] Skipping fee calculation: Cart Vendor ID $vendor_id not found.");
        return;
    }
    $vendor_postal_prices = get_user_meta($vendor_id, '_vendor_postal_prices', true) ?: [];

    error_log('[Postal Fee] cart_calculate_fees fired. Cart Vendor ID: ' . $vendor_id . ', Session Postal code: ' . $postal_code);

    // Check if the cart vendor supports the selected postal code
$matched_fee = null;

foreach ($vendor_postal_prices as $entry) {
    if (isset($entry['postal_code']) && $entry['postal_code'] === $postal_code) {
        $matched_fee = $entry;
        break;
    }
}

if ($matched_fee) {
    $fee_value = isset($matched_fee['price']) ? floatval($matched_fee['price']) : 0;
    $area_label = isset($matched_fee['area_name']) ? $matched_fee['area_name'] : '';

    $fee_label = sprintf(__('Lieferung an %s - %s', 'my-dokan-postal-fees'), $postal_code, $area_label);
    error_log("[Postal Fee] Applying fee $fee_value for postal code $postal_code (Cart Vendor $vendor_id)");

    $cart->add_fee($fee_label, $fee_value, false, '');
} else {
    error_log("[Postal Fee] Session postal code $postal_code is NOT valid for Cart Vendor $vendor_id. Fee not applied.");
    if ((is_cart() || is_checkout()) && !wc_has_notice('', 'error')) {
        wc_add_notice(
            sprintf(__('The selected delivery area (%s) is not available for the items currently in your cart. Please select a different area or clear your cart.', 'my-dokan-postal-fees'), $postal_code),
            'error'
        );
    }
}

}
add_action('wp_ajax_live_search_products', 'live_search_products');
add_action('wp_ajax_nopriv_live_search_products', 'live_search_products');

function live_search_products() {
    check_ajax_referer('live_search_nonce', 'security');

    $vendor_id = isset($_POST['vendor_id']) ? absint($_POST['vendor_id']) : 0;
    $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
    
    // Create dummy tag object for compatibility with display_products_chunk
    $dummy_tag = (object)[
        'term_id' => 0,
        'name' => 'search-results'
    ];
    // Get search results using your existing function
    $result = [
        'html' => '<div class="category-section" data-tag="search-results">
                     <h2 class="section-title">Suchergebnisse</h2>
                     <div class="product-grid">'
    ];

    // Get products using your existing function
     $products = display_products_chunk((object)['term_id' => 0], $vendor_id, 1, -1, $search);
    if (empty($products['html'])) {
        $result['html'] .= '<div class="no-products-found">Keine Produkte entsprechen Ihrer Suche.</div>';
    } else {
        $result['html'] .= $products['html'];
    }

    $result['html'] .= '</div></div>';
    
    wp_send_json_success($result);
}
add_action('wp_ajax_set_user_pickup', 'set_user_pickup');
add_action('wp_ajax_nopriv_set_user_pickup', 'set_user_pickup');

function set_user_pickup() {
    check_ajax_referer('set_pickup_nonce', 'security');
    
    WC()->session->set('is_pickup_order', true);
   if (WC()->session->get('postal_code')) {
        WC()->session->__unset('postal_code');
    }
    if (WC()->session->get('postal_label')) {
        WC()->session->__unset('postal_label');
    }
    if (WC()->cart && !WC()->cart->is_empty()) {
        WC()->cart->calculate_totals();
    }
    wp_send_json_success([
        'new_code' => 'PICKUP',
        'is_pickup' => true
    ]);
}

add_action('wp_ajax_clear_pickup_mode', 'clear_pickup_mode');
add_action('wp_ajax_nopriv_clear_pickup_mode', 'clear_pickup_mode');

function clear_pickup_mode() {
    check_ajax_referer('remove-pickup-order', 'security');

    WC()->session->set('is_pickup_order', false);

    wp_send_json_success(['message' => 'Pickup mode cleared']);
}
