<?php
/**
 * Plugin Name:       Buytiti - Render - Products
 * Plugin URI:        https://buytiti.com
 * Description:       Plugin para mostrar productos de un e-commerce
 * Requires at least: 6.1
 * Requires PHP:      7.0
 * Version:           0.2.1
 * Author:            Fernando Isaac González Medina
 * License:           GPL-2.0
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       buytitipluginproductos
 *
 * @package Buytiti
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
    // Salir si se accede directamente.
}

// Asegúrate de que WooCommerce está activo
if ( ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
    return;
}

if (!function_exists('products_enqueue_scripts_styles_v2')) {
    function products_enqueue_scripts_styles_v2() {
        // Asegurarse de que jQuery está encolado
        wp_enqueue_script('jquery');
        
        // Encolar el script de renderizado de productos
        wp_enqueue_script('buytiti-render-script', plugin_dir_url(__FILE__) . 'buytiti-render-products-v2.js', array('jquery'), '1.0', true);

        // Localizar el script con parámetros para AJAX
        wp_localize_script('buytiti-render-script', 'buytiti_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
        ));
        
        // Encolar el estilo de renderizado de productos
        wp_enqueue_style('buytiti-products-render-style', plugin_dir_url(__FILE__) . 'buytiti-render-products-v2.css');

        // Verificar si estamos en la página de prueba
        if (is_page()) {
            // Encolar Slick Carousel y asegurarse de que jQuery es una dependencia
            wp_enqueue_script('slick-js', plugin_dir_url(__FILE__) . 'assets/slick/slick.min.js', array('jquery'), '1.8.1', true);
            wp_enqueue_style('slick-css', plugin_dir_url(__FILE__) . 'assets/slick/slick.css');
            wp_enqueue_style('slick-theme-css', plugin_dir_url(__FILE__) . 'assets/slick/slick-theme.css');
        }
    }
}

add_action('wp_enqueue_scripts', 'products_enqueue_scripts_styles_v2');

function buytiti_add_to_cart_ajax_v2() {
    $product_id = intval($_POST['product_id']);
    $quantity = intval($_POST['quantity']);

    $added = WC()->cart->add_to_cart($product_id, $quantity);

    if ($added) {
        wp_send_json_success();
    } else {
        wp_send_json_error();
    }
}
add_action('wp_ajax_buytiti_add_to_cart', 'buytiti_add_to_cart_ajax_v2');
add_action('wp_ajax_nopriv_buytiti_add_to_cart', 'buytiti_add_to_cart_ajax_v2');
function buytiti_get_cart_content() {
    // Obtener los fragmentos del carrito de WooCommerce
    $fragments = WC_AJAX::get_refreshed_fragments();
    wp_send_json_success(array('fragments' => $fragments));
}
add_action('wp_ajax_buytiti_get_cart_content', 'buytiti_get_cart_content');
add_action('wp_ajax_nopriv_buytiti_get_cart_content', 'buytiti_get_cart_content');

function buytiti_get_bestsellers_last_30_days($cantidad = 20) {
    $date_30_days_ago = date('Y-m-d H:i:s', strtotime('-30 days'));

    $args = array(
        'post_type' => 'product',
        'post_status' => 'publish',
        'posts_per_page' => $cantidad,
        'meta_query' => array(
            array(
                'key' => '_stock_status',
                'value' => 'instock',
                'compare' => '='
            ),
            array(
                'key' => 'total_sales',
                'value' => 20,
                'compare' => '>',
                'type' => 'NUMERIC'
            )
        ),
        'date_query' => array(
            array(
                'column' => 'post_date_gmt',
                'after' => $date_30_days_ago
            )
        ),
        'orderby' => 'meta_value_num',
        'order' => 'DESC'
    );

    $query = new WP_Query($args);

    return $query->posts;
}

function mi_woo_products_shortcode($atts) {
    // Establecer atributos por defecto
    $atts = shortcode_atts(
        array(
            'cantidad' => 6, // Número de productos por defecto
            'categoria' => '', // Categoría del producto (puede ser vacío)
            'slider' => 'no', // Por defecto, no mostrar como slider
            'bestsellers' => 'no' // Mostrar los productos más vendidos de los últimos 30 días
        ),
        $atts,
        'mi_woo_products' // Nombre del shortcode
    );

    $cantidad = intval($atts['cantidad']);
    $mostrar_como_slider = $atts['slider'] === 'yes';
    $mostrar_bestsellers = $atts['bestsellers'] === 'yes';

    // Argumentos para la consulta de productos
    $args = array(
        'post_type' => 'product',
        'posts_per_page' => $cantidad,
        'orderby' => 'date',
        'order' => 'DESC',
        'post_status' => 'publish',
        'meta_query' => array(
            array(
                'key' => '_stock_status',
                'value' => 'instock',
                'compare' => '=',
            ),
        ),
    );

    // Consultar los productos más vendidos si se selecciona la opción
    if ($mostrar_bestsellers) {
        $productos = buytiti_get_bestsellers_last_30_days($cantidad);
    } else {
        if (!empty($atts['categoria'])) {
            $args['tax_query'] = array(
                array(
                    'taxonomy' => 'product_cat',
                    'field' => 'slug',
                    'terms' => $atts['categoria']
                ),
            );
        }
        $query = new WP_Query($args);
        $productos = $query->posts;
    }

    if (empty($productos)) {
        return 'No hay productos disponibles.';
    }

    // Iniciar contenedor con estilo de cuadrícula
    $output = $mostrar_como_slider ? '<div class="buytiti-product-slider-v2 slick-hidden">' : '<div class="woo-products-grid-v2">';

    foreach ($productos as $producto_id) {
        $product = wc_get_product($producto_id);

        if (!$product) {
            continue;
        }

        $output .= '<div class="woo-product-item-v2" id="product-' . $product->get_id() . '">';
        $product_link = get_permalink($product->get_id());

        $image_url = '';
        $image_id = $product->get_image_id();
        if ($image_id) {
            $image_url = wp_get_attachment_url($image_id);
        }

        $secondary_image_url = '';
        $image_ids = $product->get_gallery_image_ids();
        if (!empty($image_ids)) {
            $secondary_image_url = wp_get_attachment_url($image_ids[0]);
        }

        $output .= '<a href="' . esc_url($product_link) . '" class="product-image-link-v2">';
        if ($image_url) {
            $output .= '<img src="' . esc_url($image_url) . '" alt="' . esc_attr($product->get_name()) . '" class="primary-image-v2">';
        } else {
            $output .= '<span>No hay imagen disponible</span>';
        }
        if ($secondary_image_url) {
            $output .= '<img src="' . esc_url($secondary_image_url) . '" alt="' . esc_attr($product->get_name()) . '" class="secondary-image-v2" style="display:none;">';
        }
        $output .= '</a><br>';

        $output .= do_shortcode('[ti_wishlists_addtowishlist]');

        $published_date = get_the_date('U');
        $current_date = current_time('U');
        $days_since_published = ($current_date - $published_date) / DAY_IN_SECONDS;

        if ($days_since_published < 7) {
            $output .= '<span class="new-product-v2">Nuevo</span>';
        }

        $sale_price = $product->get_sale_price();
        $regular_price = $product->get_regular_price();

        $output .= get_product_labels_v2($product, $sale_price);

        if ($sale_price && $regular_price > 0) {
            $descuento = (($regular_price - $sale_price) / $regular_price) * 100;
            $output .= '<span class="product-discount-buytiti-v2">-' . round($descuento) . '%</span>';
        }

        if ($mostrar_bestsellers) {
            $total_sales = get_post_meta($product->get_id(), 'total_sales', true);
            $output .= '<span class="bestseller-sales-v2">Vendidos: ' . $total_sales . '</span>';
        }

        $sku = $product->get_sku();
        if ($sku) {
            $output .= '<span class="' . esc_attr('sku-class-buytiti-v2') . '">SKU: ' . esc_html($sku) . '</span><br>';
        }

        $marca = $product->get_attribute('Marca');
        if (!$marca) {
            $categorias = get_the_terms($product->get_id(), 'product_cat');
            $categoria = $categorias ? $categorias[0]->name : '';
            $marca = 'BUYTITI - ' . $categoria;
        }
        $output .= '<span class="' . esc_attr('product-brand-buytiti-v2') . '">' . esc_html($marca) . '</span><br>';

        $output .= '<a href="' . esc_url($product_link) . '" class="' . esc_attr('product-link-buytiti-v2') . '">';
        $output .= '<strong class="' . esc_attr('product-title-buytiti-v2') . '">' . esc_html($product->get_name()) . '</strong>';
        $output .= '</a><br>';

        if ($sale_price) {
            $output .= '<span class="precio"><del class="precio-regular-tachado-v2">' . wc_price($regular_price) . '</del> <ins class="sale-price-v2">' . wc_price($sale_price) . '</ins></span><br>';
        } else {
            $output .= '<span class="precio precio-regular-v2">' . wc_price($regular_price) . '</span><br>';
        }

        $output .= '<form class="add-to-cart-form-v2" method="post">';
        $output .= '<input type="hidden" name="add-to-cart" value="' . esc_attr($product->get_id()) . '">';
        $output .= '<input type="number" name="quantity" value="1" min="1" class="' . esc_attr('input-quantity-buytiti-v2') . '" style="margin-right:10px;">';
        $output .= '<input type="submit" value="Añadir al carrito" class="add-to-cart-button-v2">';
        $output .= '</form>';

        $output .= '</div>';
    }

    wp_reset_postdata();
    $output .= '</div>';

    return $output;
}

function get_product_labels_v2($product, $sale_price) {
    $output = '';
    $categorias = get_the_terms(get_the_ID(), 'product_cat');
    $esOfertaEnVivo = false;
    $esLiquidacion = false;

    if ($sale_price && $categorias) {
        foreach ($categorias as $categoria) {
            if ($categoria->name === 'Ofertas en Vivo') {
                $output .= '<span class="etiqueta-ofertas-en-vivo-v2">Ofertas en Vivo</span>';
                $esOfertaEnVivo = true;
                break;
            }
        }
    }

    if ($sale_price && $categorias) {
        foreach ($categorias as $categoria) {
            if ($categoria->name === 'LIQUIDACIONES') {
                $output .= '<span class="etiqueta-liquidacionSinApi-v2">LIQUIDACIÓN</span>';
                $esLiquidacion = true;
                break;
            }
        }
    }

    if ($sale_price && !$esOfertaEnVivo && !$esLiquidacion) {
        $output .= '<span class="etiqueta-oferta-v2">Remate</span>';
    }

    return $output;
}

// Registrar el shortcode
add_shortcode('mi_woo_products', 'mi_woo_products_shortcode');
