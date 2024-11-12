<?php
/**
 * Plugin Name: Custom Checkout Autofiller for WooCommerce
 * Plugin URI: https://axumcode.com
 * Description: Fills out WooCommerce checkout fields with data from the map.
 * Version: 1.0
 * Author: Mikiyas Shiferaw
 * Author URI: https://t.me/mikiyas_sh
 * License: GPL2
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Enqueue LeafletJS assets from CDN
function enqueue_leafletjs_assets() {
    if ( is_page() && has_shortcode( get_post()->post_content, 'leaflet_map' ) ) {
        wp_enqueue_style( 'leaflet', 'https://unpkg.com/leaflet@1.7.1/dist/leaflet.css' );
        wp_enqueue_script( 'leaflet', 'https://unpkg.com/leaflet@1.7.1/dist/leaflet.js', array(), '1.7.1', true );
    }
}
add_action( 'wp_enqueue_scripts', 'enqueue_leafletjs_assets' );

// Shortcode to display LeafletJS map with draggable marker and label
function leaflet_map_shortcode( $atts ) {
    $atts = shortcode_atts( array(
        'lat'  => '9.03',     // Latitude of Addis Ababa
        'lon'  => '38.74',    // Longitude of Addis Ababa
        'zoom' => '13',       // Default zoom level
    ), $atts, 'leaflet_map' );

    ob_start(); ?>
    <div id="map" style="height: 400px;"></div>
    <script>
        document.addEventListener( 'DOMContentLoaded', function() {
            if (typeof L !== 'undefined') {
                var map = L.map('map').setView([<?php echo esc_js( $atts['lat'] ); ?>, <?php echo esc_js( $atts['lon'] ); ?>], <?php echo esc_js( $atts['zoom'] ); ?>);

                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
                }).addTo(map);

                // Create a single draggable marker at the initial location
                var marker = L.marker([<?php echo esc_js( $atts['lat'] ); ?>, <?php echo esc_js( $atts['lon'] ); ?>], {
                    draggable: true 
                }).addTo(map);

                // Add a tooltip (label) to the marker
                marker.bindTooltip('Drag me', {
                    permanent: true,
                    direction: 'top'
                }).openTooltip();

                // Update marker position on drag end to fetch address
                marker.on('dragend', function(e) {
                    var latLng = e.target.getLatLng();
                    var url = 'https://nominatim.openstreetmap.org/reverse?lat=' + latLng.lat + '&lon=' + latLng.lng + '&format=json&addressdetails=1';

                    fetch(url)
                        .then(response => response.json())
                        .then(data => {
                            if (data && data.address) {
                                populateCheckoutFields(data.address, latLng);
                            }
                        })
                        .catch(error => {
                            console.error('Error fetching location name: ', error);
                        });
                });

                // Move marker to double-click location
                map.on('dblclick', function(e) {
                    var latLng = e.latlng;
                    marker.setLatLng(latLng); // Move the marker to the double-clicked location
                });
            } else {
                console.error("LeafletJS library failed to load.");
            }
        });

        // Function to populate WooCommerce checkout fields
        function populateCheckoutFields(address, latLng) {
            if (document.body.classList.contains('woocommerce-checkout')) {
                document.getElementById('billing_address_1').value = address.suburb || '';
                document.getElementById('billing_address_2').value = address.county || '';
                document.getElementById('billing_city').value = address.city || 'Addis Ababa';
                document.getElementById('billing_postcode').value = address.postcode || '';
                document.getElementById('billing_country').value = address.country_code || '';

                document.getElementById('shipping_address_1').value = address.road || '';
                document.getElementById('shipping_address_2').value = address.suburb || '';
                document.getElementById('shipping_city').value = address.city || '';
                document.getElementById('shipping_postcode').value = address.postcode || '';
                document.getElementById('shipping_country').value = address.country_code || '';

                var orderComments = document.getElementById('order_comments');
                if (orderComments) {
                    document.querySelector('form.checkout').addEventListener('submit', function() {
                        orderComments.value += `\nLatitude: ${latLng.lat}, Longitude: ${latLng.lng}`;
                    });
                }
            }
        }
    </script>
    <?php
    return ob_get_clean();
}
add_shortcode( 'leaflet_map', 'leaflet_map_shortcode' );
