jQuery(document).ready(function($) {
    // Hover de imagen
    $(".product-image-link-v2").on("mouseover", function() {
        var primaryImage = $(this).find(".primary-image-v2");
        var secondaryImage = $(this).find(".secondary-image-v2");

        if (secondaryImage.length) {
            var temp = primaryImage.attr("src");
            primaryImage.attr("src", secondaryImage.attr("src"));
            secondaryImage.attr("src", temp);
        }
    });

    $(".product-image-link-v2").on("mouseleave", function() {
        var primaryImage = $(this).find(".primary-image-v2");
        var secondaryImage = $(this).find(".secondary-image-v2");

        if (secondaryImage.length) {
            var temp = primaryImage.attr("src");
            primaryImage.attr("src", secondaryImage.attr("src"));
            secondaryImage.attr("src", temp);
        }
    });

    // Inicializar Slick Slider si el contenedor existe
    if ($(".buytiti-product-slider-v2").length) {
        $(".buytiti-product-slider-v2").slick({
            infinite: true,
            autoplay: true,
            autoplaySpeed: 3000,
            speed: 300,
            slidesToShow: 6,
            slidesToScroll: 1,
            responsive: [
                {
                    breakpoint: 1590,
                    settings: {
                        slidesToShow: 5,
                        slidesToScroll: 1,
                        infinite: true,
                        autoplay: true,
                    }
                },
                {
                    breakpoint: 1366,
                    settings: {
                        slidesToShow: 3,
                        slidesToScroll: 1,
                        infinite: true,
                        autoplay: true,
                    }
                },
                {
                    breakpoint: 1024,
                    settings: {
                        slidesToShow: 3,
                        slidesToScroll: 1,
                        infinite: true,
                        autoplay: true,
                    }
                },
                {
                    breakpoint: 600,
                    settings: {
                        slidesToShow: 2,
                        slidesToScroll: 1,
                        infinite: true,
                        autoplay: true,
                    }
                },
                {
                    breakpoint: 480,
                    settings: {
                        slidesToShow: 2,
                        slidesToScroll: 1,
                        infinite: true,
                        autoplay: true,
                    }
                }
            ]
        });
    }

    // Añadir producto al carrito con AJAX y evitar la recarga de página
    $(".add-to-cart-form-v2").on("click", function(e) {
        e.preventDefault(); // Evitar el comportamiento predeterminado del formulario
        var form = $(this);
        var product_id = form.find('input[name=\"add-to-cart\"]').val();
        var quantity = form.find('input[name=\"quantity\"]').val();

        // Lógica AJAX para añadir al carrito
        $.ajax({
            url: buytiti_ajax.ajax_url, // URL AJAX de WooCommerce
            method: "POST",
            data: {
                action: "buytiti_add_to_cart", // Acción para añadir al carrito
                product_id: product_id,
                quantity: quantity, // Añadir la cantidad al data
                
            },
            success: function(response) {
                if (response.success) {
                    alert("Producto añadido al carrito: ");
                    var cart_count = $('.cart-count');
                    if (cart_count.length) {
                        var current_count = parseInt(cart_count.text());
                        cart_count.text(current_count + parseInt(quantity));
                    }
                    $.ajax({
                        type: 'POST',
                        url: buytiti_ajax.ajax_url,
                        data: {
                            action: 'buytiti_get_cart_content'
                        },
                        success: function(response) {
                            if (response.fragments) {
                                $.each(response.fragments, function(key, value) {
                                    $(key).replaceWith(value);
                                });
                            }
                        }
                    });
                } else {
                    alert('Error al añadir el producto al carrito');
                }
            },
            error: function() {
                alert("Error en solicitud AJAX");
            }
        });
    });
});
