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
        (function() {
            var fechaObjetivo = <?php echo intval($fecha_timestamp); ?>;
            var countdownElement = document.getElementById('countdown');
            
            if (!countdownElement) return;
            
            function actualizarCountdown() {
                var ahora = new Date().getTime();
                var diff = fechaObjetivo - ahora;
                
                // Protección contra fechas pasadas o negativas
                if (diff <= 0) {
                    if (countdownElement) {
                        countdownElement.innerHTML = '<div class="ygb-countdown" style="padding:30px;">' + 
                            '<div class="ygb-countdown-titulo">✨ <?php echo esc_js(__('¡Ya estamos disponibles!', 'ygb-modo-catalogo')); ?></div>' + 
                            '</div>';
                    }
                    return;
                }
                
                // Asegurar que diff no sea negativo (por si acaso)
                diff = Math.max(0, diff);
                
                var dias = Math.floor(diff / (1000 * 60 * 60 * 24));
                var horas = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                var minutos = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
                var segundos = Math.floor((diff % (1000 * 60)) / 1000);
                
                var diasElem = document.getElementById('dias');
                var horasElem = document.getElementById('horas');
                var minutosElem = document.getElementById('minutos');
                var segundosElem = document.getElementById('segundos');
                
                if (diasElem) diasElem.innerText = dias.toString().padStart(2, '0');
                if (horasElem) horasElem.innerText = horas.toString().padStart(2, '0');
                if (minutosElem) minutosElem.innerText = minutos.toString().padStart(2, '0');
                if (segundosElem) segundosElem.innerText = segundos.toString().padStart(2, '0');
            }
            
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', function() {
                    actualizarCountdown();
                    setInterval(actualizarCountdown, 1000);
                });
            } else {
                actualizarCountdown();
                setInterval(actualizarCountdown, 1000);
            }
        })();
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