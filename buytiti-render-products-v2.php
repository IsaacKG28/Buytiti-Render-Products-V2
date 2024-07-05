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
        wp_enqueue_script('jquery');
        wp_enqueue_script('buytiti-render-script', plugin_dir_url(__FILE__) . 'buytiti-render-products-v2.js', array('jquery'), '1.0', true);

        // Localizar el script con parámetros para AJAX
        wp_localize_script('buytiti-render-script', 'buytiti_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
        ));
        
        wp_enqueue_style('buytiti-products-render-style', plugin_dir_url(__FILE__) . 'buytiti-render-products-v2.css');
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

function mi_woo_products_shortcode($atts) {
    // Establecer atributos por defecto
    $atts = shortcode_atts(
        array(
            'cantidad' => 6, // Número de productos por defecto
            'categoria' => '', // Categoría del producto (puede ser vacío)
            'slider' => 'no' // Por defecto, no mostrar como slider
        ),
        $atts,
        'mi_woo_products' // Nombre del shortcode
    );

    // Convertir el valor del atributo 'cantidad' a un entero
    $cantidad = intval($atts['cantidad']);
    $mostrar_como_slider = $atts['slider'] === 'yes';

    // Argumentos para la consulta de productos
    $args = array(
        'post_type'      => 'product',
        'posts_per_page' => $cantidad, // Usar la cantidad especificada
        'orderby'        => 'date',   // Ordenar por fecha
        'order'          => 'DESC',   // En orden descendente para los más recientes
        'post_status'    => 'publish', // Solo productos publicados
        'meta_query'     => array(
            array(
                'key'     => '_stock_status', // Clave de metadatos para el estado del stock
                'value'   => 'instock',       // Solo productos en stock
                'compare' => '=',            // Comparación de igualdad
            ),
        ),
    );

    // Agregar filtro de categoría si está definido
    if ( ! empty( $atts[ 'categoria' ] ) ) {
        $args[ 'tax_query' ] = array(
            array(
                'taxonomy' => 'product_cat',
                'field'    => 'slug',
                'terms'    => $atts[ 'categoria' ],
            ),
        );
    }

    $query = new WP_Query( $args );

    if ( ! $query->have_posts() ) {
        return 'No hay productos disponibles.';
        // Manejar caso sin productos
    }

    // Iniciar contenedor con estilo de cuadrícula
    $output = $mostrar_como_slider ? '<div class="buytiti-product-slider-v2">' : '<div class="woo-products-grid-v2">';


    while ($query->have_posts()) {
        $product_id = $product->ID;
        $query->the_post();
        $product = wc_get_product(get_the_ID());

        if (!$product) {
            continue;
        }

        $output .= '<div class="woo-product-item-v2" id="product-' . get_the_ID() . '">';
        $product_link = get_permalink(get_the_ID());

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
            $output .= '<img src="' . esc_url($image_url) . '" alt="' . esc_attr(get_the_title()) . '" class="primary-image-v2">';
        } else {
            $output .= '<span>No hay imagen disponible</span>';
        }
        if ($secondary_image_url) {
            $output .= '<img src="' . esc_url($secondary_image_url) . '" alt="' . esc_attr(get_the_title()) . '" class="secondary-image-v2" style="display:none;">';
        }
        $output .= '</a><br>';

        $output .= do_shortcode('[ti_wishlists_addtowishlist]');

        $published_date = get_the_date('U');
        $current_date = current_time('U');
        $days_since_published = ($current_date - $published_date) / DAY_IN_SECONDS;

        if ($days_since_published < 7) {
            $output .= '<span class="new-product-v2">Nuevo</span>';
        }

        // $stock = $product->get_stock_quantity();
        // $output .= '<span class="' . esc_attr('stock-quantity-buytitisinapi-v2') . '">Disponible: ' . esc_html($stock) . '</span><br>';

        $sale_price = $product->get_sale_price();
        $regular_price = $product->get_regular_price();

        $output .= get_product_labels_v2($product, $sale_price);

        if ($sale_price && $regular_price > 0) {
            $descuento = (($regular_price - $sale_price) / $regular_price) * 100;
            $output .= '<span class="product-discount-buytiti-v2">-' . round($descuento) . '%</span>';
        }

        $sku = $product->get_sku();
        if ($sku) {
            $output .= '<span class="' . esc_attr('sku-class-buytiti-v2') . '">SKU: ' . esc_html($sku) . '</span><br>';
        }

        $marca = $product->get_attribute('Marca');
        if (!$marca) {
            $categorias = get_the_terms(get_the_ID(), 'product_cat');
            $categoria = $categorias ? $categorias[0]->name : '';
            $marca = 'BUYTITI - ' . $categoria;
        }
        $output .= '<span class="' . esc_attr('product-brand-buytiti-v2') . '">' . esc_html($marca) . '</span><br>';

        $output .= '<a href="' . esc_url($product_link) . '" class="' . esc_attr('product-link-buytiti-v2') . '">';
        $output .= '<strong class="' . esc_attr('product-title-buytiti-v2') . '">' . esc_html(get_the_title()) . '</strong>';
        $output .= '</a><br>';

        if ($sale_price) {
            $output .= '<span class="precio"><del class="precio-regular-tachado-v2">' . wc_price($regular_price) . '</del> <ins class="sale-price-v2">' . wc_price($sale_price) . '</ins></span><br>';
        } else {
            $output .= '<span class="precio precio-regular-v2">' . wc_price($regular_price) . '</span><br>';
        }

        $output .= '<form class="add-to-cart-form-v2" method="post">';
            $output .= '<input type="hidden" name="add-to-cart" value="' . esc_attr(get_the_ID()) . '">';
            // $output .= '<input type="hidden" name="product-anchor" value="product-' . esc_attr(get_the_ID()) . '">';
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
