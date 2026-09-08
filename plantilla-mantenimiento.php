<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="robots" content="noindex, nofollow">
    <title><?php echo esc_html($titulo); ?> - <?php echo esc_html(get_bloginfo('name')); ?></title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: linear-gradient(135deg, <?php echo esc_attr($gradiente_1); ?> 0%, <?php echo esc_attr($gradiente_2); ?> 100%);
            min-height: 100vh;
            margin: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 10px;
            box-sizing: border-box;
        }
    </style>
    <?php wp_head(); ?>
</head>
<body>
    <div class="ygb-container">
        <?php if (!empty($logo)): ?>
            <img src="<?php echo esc_url($logo); ?>" alt="<?php echo esc_attr(get_bloginfo('name')); ?>" class="ygb-logo">
        <?php endif; ?>
        
        <h1><?php echo esc_html($titulo); ?></h1>
        
        <div class="ygb-mensaje">
            <?php echo wp_kses_post($mensaje); ?>
        </div>
        
        <?php if (!empty($countdown_activo) && !empty($countdown_fecha)): 
            $timezone = wp_timezone();
            $fecha_obj = DateTime::createFromFormat('Y-m-d\TH:i', $countdown_fecha, $timezone);
            if ($fecha_obj !== false):
                $ahora = new DateTime('now', $timezone);
                if ($fecha_obj > $ahora):
                    $fecha_timestamp = $fecha_obj->getTimestamp() * 1000;
                    $mensaje_final = __('¡Ya estamos disponibles!', 'ygb-modo-catalogo');
        ?>
        <div class="ygb-countdown" id="countdown">
            <div class="ygb-countdown-titulo"><?php _e('Tiempo restante', 'ygb-modo-catalogo'); ?></div>
            <div class="ygb-countdown-timer">
                <div class="ygb-countdown-item">
                    <div class="ygb-countdown-numero" id="dias">00</div>
                    <div class="ygb-countdown-etiqueta"><?php _e('Días', 'ygb-modo-catalogo'); ?></div>
                </div>
                <div class="ygb-countdown-item">
                    <div class="ygb-countdown-numero" id="horas">00</div>
                    <div class="ygb-countdown-etiqueta"><?php _e('Horas', 'ygb-modo-catalogo'); ?></div>
                </div>
                <div class="ygb-countdown-item">
                    <div class="ygb-countdown-numero" id="minutos">00</div>
                    <div class="ygb-countdown-etiqueta"><?php _e('Minutos', 'ygb-modo-catalogo'); ?></div>
                </div>
                <div class="ygb-countdown-item">
                    <div class="ygb-countdown-numero" id="segundos">00</div>
                    <div class="ygb-countdown-etiqueta"><?php _e('Segundos', 'ygb-modo-catalogo'); ?></div>
                </div>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            if (typeof window.YGB_Countdown !== 'undefined') {
                window.YGB_Countdown.init('countdown', <?php echo intval($fecha_timestamp); ?>, '<?php echo esc_js($mensaje_final); ?>');
            }
        });
        </script>
        <?php 
                else:
                    echo '<div class="ygb-countdown" style="padding:30px; text-align:center;">' . __('El sitio estará disponible próximamente.', 'ygb-modo-catalogo') . '</div>';
                endif;
            endif;
        endif; 
        ?>
        
        <div class="ygb-botones">
            <a href="<?php echo esc_url(wp_login_url()); ?>" class="ygb-boton" rel="nofollow"><?php _e('Acceso administradores', 'ygb-modo-catalogo'); ?></a>
            <a href="<?php echo esc_url($clientes_url); ?>" class="ygb-boton ygb-boton-outline" rel="nofollow"><?php _e('Acceso clientes', 'ygb-modo-catalogo'); ?></a>
        </div>
        
        <div class="ygb-footer">
            &copy; <?php echo esc_html(date_i18n('Y')); ?> <?php echo esc_html(get_bloginfo('name')); ?>
        </div>
    </div>
    <?php wp_footer(); ?>
</body>
</html>