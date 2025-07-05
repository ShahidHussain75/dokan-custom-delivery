# Dokan Custom Plugin

A comprehensive WordPress plugin that extends Dokan marketplace functionality with custom features for vendor stores, product management, cart handling, and delivery systems.

## Plugin Information
- **Plugin Name**: Dokan Custom
- **Version**: 1.0.3
- **Author**: Shahid
- **Description**: Custom functionality for Dokan including grouped products support, postal code delivery system, and enhanced vendor store features

## Table of Contents
1. [Core Setup and Initialization](#core-setup-and-initialization)
2. [Product Display and Lazy Loading](#product-display-and-lazy-loading)
3. [AJAX Handlers](#ajax-handlers)
4. [Product Modal and Quick View](#product-modal-and-quick-view)
5. [Price Handling Functions](#price-handling-functions)
6. [Product Variations and Addons](#product-variations-and-addons)
7. [Vendor Profile Enhancement](#vendor-profile-enhancement)
8. [Store Timing and Information](#store-timing-and-information)
9. [Category Tabs and Product Search](#category-tabs-and-product-search)
10. [Reviews and Ratings System](#reviews-and-ratings-system)
11. [Cart Management](#cart-management)
12. [Postal Code and Delivery System](#postal-code-and-delivery-system)
13. [Store Status and Pre-order System](#store-status-and-pre-order-system)
14. [Vendor Restrictions](#vendor-restrictions)

---

## Core Setup and Initialization

### Plugin Header and Security (Lines 1-20)
```php
// Plugin header information and basic security checks
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}
```

### Style and Script Enqueuing (Lines 22-35)
```php
function themeslug_enqueue_style() {
    // Enqueues custom CSS and JavaScript files
    // Adds cart nonce for AJAX security
    // Enqueues stickersidebar.js
}
```

---

## Product Display and Lazy Loading

### Display Products Chunk Function (Lines 37-85)
**Lines 37-85**: `display_products_chunk()`
- Displays products in chunks for lazy loading
- Handles product search functionality
- Generates product cards with images, prices, and descriptions
- Supports variable products and price formatting

### AJAX Load More Products (Lines 87-115)
**Lines 87-115**: `load_more_products()`
- AJAX handler for lazy loading additional products
- Handles pagination and security verification
- Returns HTML and pagination status

---

## AJAX Handlers

### Product Modal Loading (Lines 117-200)
**Lines 117-200**: `load_product_modal()`
- AJAX handler for loading product quick view modal
- Generates complete product modal HTML
- Includes variations, addons, and pricing information

---

## Product Modal and Quick View

### Modal HTML Generation (Lines 202-250)
**Lines 202-250**: Product modal content generation
- Product image with loading states
- Product details (name, price, description)
- Variable product support
- Product addons integration
- Quantity controls and add to cart functionality

---

## Price Handling Functions

### Product Price HTML (Lines 252-290)
**Lines 252-290**: `get_product_price_html()`
- Handles variable product pricing
- Supports sale price display
- Formats price ranges for variable products
- Handles regular and sale price formatting

---

## Product Variations and Addons

### Variations HTML Generation (Lines 292-350)
**Lines 292-350**: `get_variations_html()`
- Generates variation selection interface
- Automatically selects lowest price variation
- Displays variation prices with sale support
- Radio button selection for variations

### Product Addons HTML (Lines 352-450)
**Lines 352-450**: `get_product_addons_html()`
- Generates product addon interface
- Supports checkbox and radio button addons
- Handles percentage-based and fixed pricing
- Required field validation
- Multiple addon groups support

---

## Vendor Profile Enhancement

### Custom Content After Seller Profile (Lines 452-500)
**Lines 452-500**: `custom_content_after_seller_profile()`
- Enhanced vendor profile display
- Store information and ratings
- Profile image and vendor details
- Store timing information

### Store Information Display (Lines 500-600)
**Lines 500-600**: Store information section
- Vendor name and rating display
- Delivery information and minimum order amounts
- Store status indicators
- About us modal functionality

---

## Store Timing and Information

### Store Timing Logic (Lines 600-700)
**Lines 600-700**: Store timing functionality
- Opening and closing time display
- Day-based timing groups
- German day abbreviations
- Store status indicators

### About Us Modal (Lines 700-800)
**Lines 700-800**: About us modal content
- Store address and map integration
- Business details and contact information
- Store description and legal information
- Interactive map with Leaflet.js

---

## Category Tabs and Product Search

### Category Tabs Generation (Lines 800-900)
**Lines 800-900**: Category tabs functionality
- Dynamic category tab generation
- Product tag-based categorization
- Mobile-responsive hamburger menu
- Active tab highlighting

### Live Search Implementation (Lines 900-1000)
**Lines 900-1000**: Live search functionality
- Real-time product search
- AJAX-based search results
- Search input with debouncing
- Product filtering by vendor

---

## Reviews and Ratings System

### Reviews Popup Generation (Lines 1000-1200)
**Lines 1000-1200**: Reviews system
- Comprehensive reviews popup
- Rating summary with star display
- Rating distribution bars
- Review sorting functionality

### Review Display and Interaction (Lines 1200-1400)
**Lines 1200-1400**: Review interaction features
- Individual review display
- Helpful review voting system
- Reviewed products slider
- Review date and author information

---

## Cart Management

### Cart AJAX Handlers (Lines 1400-1600)
**Lines 1400-1600**: Cart management functions
- Add to cart functionality
- Cart item quantity updates
- Cart item removal
- Cart fragment updates

### Cart Display and Updates (Lines 1600-1800)
**Lines 1600-1800**: Cart display functionality
- Custom cart popup
- Cart item listing
- Cart totals calculation
- Delivery fee integration

---

## Postal Code and Delivery System
## This code adds functionality of postal code and local pick up ( the modal that shows on load of singel vendor page )

### Postal Code Script Enqueuing (Lines 1800-1900)
**Lines 1800-1900**: `mydokanpf_enqueue_postal_scripts()`
- Enqueues postal selector JavaScript
- Localizes postal code data
- Vendor-specific postal pricing
- Session management

### Postal Code AJAX Handlers (Lines 1900-2000)
**Lines 1900-2000**: Postal code functionality
- Set user postal code
- Pickup mode handling
- Delivery fee calculation
- Session management

### Delivery Fee Calculation (Lines 2000-2100)
**Lines 2000-2100**: `mydokanpf_add_postal_code_fee()`
- Cart fee calculation based on postal code
- Vendor-specific delivery pricing
- Pickup order handling
- Fee validation and display

---

## Store Status and Pre-order System

### Store Closed Notice (Lines 2100-2200)
**Lines 2100-2200**: Store status functionality
- Store closed banner display
- Pre-order confirmation popup
- Store status checking
- User notification system

### Pre-order AJAX Handling (Lines 2200-2300)
**Lines 2200-2300**: Pre-order system
- Pre-order confirmation
- Store closed state handling
- Pre-order data storage
- Success/error handling

---

## Vendor Restrictions

### Single Vendor Cart Rule (Lines 2300-2400)
**Lines 2300-2400**: `dokan_custom_only_one_vendor_at_a_time()`
- Prevents multiple vendors in cart
- Cart validation
- Error messaging
- Vendor ID comparison

### Different Vendor Notice (Lines 2400-2500)
**Lines 2400-2500**: `dokan_custom_different_vendor_notice()`
- Displays vendor conflict notices
- Cart clearing functionality
- Button disabling
- User guidance

### Cart Clearing AJAX (Lines 2500-2600)
**Lines 2500-2600**: `dokan_custom_clear_cart_ajax()`
- AJAX cart clearing
- Security verification
- Success/error handling
- Cart state management

---

## JavaScript Integration

### Main JavaScript Functions (Lines 2600-3000)
**Lines 2600-3000**: Core JavaScript functionality
- Product modal handling
- Cart updates and management
- Search functionality
- Tab navigation
- Review system interaction

### Cart JavaScript (Lines 3000-3500)
**Lines 3000-3500**: Cart-specific JavaScript
- Add to cart functionality
- Quantity controls
- Cart display updates
- Delivery/pickup toggle
- Popular products handling

### Review System JavaScript (Lines 3500-4000)
**Lines 3500-4000**: Review system JavaScript
- Review popup functionality
- Review sorting
- Slider navigation
- Helpful voting system
- Review display management

---

## CSS and Styling

### Modal Skeleton Loading (Lines 4000-4100)
**Lines 4000-4100**: Loading state styles
- Skeleton loading animations
- Modal loading states
- CSS animations
- Responsive design elements

---

## Helper Functions

### Rating System Helpers (Lines 4100-4200)
**Lines 4100-4200**: Rating helper functions
- Seller rating calculations
- Rating count aggregation
- Rating distribution
- Review data processing

### Utility Functions (Lines 4200-4326)
**Lines 4200-4326**: Various utility functions
- Data sanitization
- Error handling
- Session management
- Security functions

---

## Key Features Summary

1. **Product Management**: Lazy loading, search, variations, addons
2. **Vendor Profiles**: Enhanced store pages, ratings, reviews
3. **Cart System**: Custom cart, single vendor restriction, pre-orders
4. **Delivery System**: Postal code selection, delivery fees, pickup options
5. **Store Status**: Open/closed states, pre-order handling
6. **User Interface**: Responsive design, modals, tabs, search
7. **Reviews**: Comprehensive rating system, helpful voting
8. **Security**: Nonce verification, input sanitization, AJAX security

## Dependencies

- WordPress 5.8+
- WooCommerce
- Dokan Lite/Pro
- PHP 7.2+
- jQuery
- Leaflet.js (for maps)
- FontAwesome (for icons)

## Installation

1. Upload the plugin files to `/wp-content/plugins/dokan-custom/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Configure vendor postal codes and delivery areas
4. Set up store timing and information

## Configuration

- Configure vendor postal codes in vendor dashboard
- Set up store timing and delivery areas
- Customize CSS for styling
- Configure minimum order amounts per vendor

## Support

For support and questions, please refer to the plugin documentation or contact the developer. 
