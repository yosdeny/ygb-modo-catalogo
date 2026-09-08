<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="robots" content="noindex, nofollow">
    <title><?php echo esc_html($titulo); ?> - <?php echo esc_html(get_bloginfo('name')); ?></title>
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: linear-gradient(135deg, <?php echo esc_attr($gradiente_1); ?> 0%, <?php echo esc_attr($gradiente_2); ?> 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .ygb-bloqueo-container {
            max-width: 600px;
            width:100%;
            background: rgba(0,0,0,0.75);
            backdrop-filter: blur(10px);
            border-radius: 24px;
            padding: 40px 30px;
            text-align: center;
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5);
            color: white;
            animation: fadeIn 0.8s ease-out;
        }
        @keyframes fadeIn {
            from { opacity:0; transform:translateY(20px); }
            to { opacity:1; transform:translateY(0); }
        }
        .ygb-bloqueo-logo {
            max-width:200px;
            max-height:100px;
            margin-bottom:30px;
            display:inline-block;
        }
        .ygb-bloqueo-titulo {
            font-size: clamp(1.8rem,5vw,2.5rem);
            font-weight:700;
            margin-bottom:20px;
            letter-spacing:-0.5px;
        }
        .ygb-bloqueo-mensaje {
            font-size: clamp(1rem,3vw,1.2rem);
            line-height:1.5;
            margin-bottom:30px;
            opacity:0.95;
        }
        .ygb-admin-button {
            display: inline-block;
            background: <?php echo esc_attr($color_primario); ?>;
            color: white !important;
            text-decoration: none;
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 500;
            margin: 15px 0 0;
            transition: background 0.3s ease;
            border: none;
            cursor: pointer;
        }
        .ygb-admin-button:hover {
            background: <?php echo esc_attr($color_secundario); ?>;
        }

        /* ===== COUNTDOWN ===== */
        .ygb-countdown {
            border-radius: 15px;
            padding: clamp(20px, 4vw, 30px);
            margin: clamp(20px, 4vw, 30px) 0;
            width: 100%;
            box-sizing: border-box;
            background: linear-gradient(135deg, <?php echo esc_attr($gradiente_1); ?> 0%, <?php echo esc_attr($gradiente_2); ?> 100%);
        }
        .ygb-countdown-titulo {
            font-size: clamp(0.9em, 2.5vw, 1.1em);
            text-transform: uppercase;
            letter-spacing: 2px;
            margin-bottom: 15px;
            opacity: 0.9;
            white-space: normal;
            color: white;
        }
        .ygb-countdown-timer {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            width: 100%;
            box-sizing: border-box;
        }
        .ygb-countdown-item {
            padding: clamp(8px, 2vw, 15px);
            border-radius: 12px;
            min-width: 70px;
            flex: 0 1 auto;
            backdrop-filter: blur(5px);
            box-sizing: border-box;
            text-align: center;
            background: rgba(255, 255, 255, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }
        .ygb-countdown-numero {
            font-size: clamp(1.5em, 6vw, 2.8em);
            font-weight: bold;
            line-height: 1.2;
            white-space: nowrap;
            color: white;
        }
        .ygb-countdown-etiqueta {
            font-size: clamp(0.7em, 2.5vw, 0.9em);
            opacity: 0.9;
            margin-top: 5px;
            text-transform: uppercase;
            white-space: nowrap;
            color: white;
        }
        @media (max-width: 480px) {
            .ygb-countdown-item { min-width: 60px; padding: 8px 3px; }
            .ygb-countdown-numero { font-size: 1.3em; }
        }
        @media (max-width: 360px) {
            .ygb-countdown-item { min-width: 55px; padding: 6px 2px; }
            .ygb-countdown-numero { font-size: 1.2em; }
        }

        .ygb-bloqueo-footer {
            margin-top:40px;
            font-size:0.8rem;
            opacity:0.7;
            border-top:1px solid rgba(255,255,255,0.2);
            padding-top:20px;
        }
        @media (max-width:640px) {
            .ygb-bloqueo-container { padding:30px 20px; }
            .ygb-bloqueo-titulo { font-size:1.5rem; }
            .ygb-admin-button { padding:10px 20px; font-size:0.9rem; }
        }
    </style>
</head>
<body>
    <div class="ygb-bloqueo-container">
        <?php if (!empty($logo)): ?>
            <img src="<?php echo esc_url($logo); ?>" alt="<?php echo esc_attr(get_bloginfo('name')); ?>" class="ygb-bloqueo-logo">
        <?php endif; ?>
        <h1 class="ygb-bloqueo-titulo"><?php echo esc_html($titulo); ?></h1>
        <div class="ygb-bloqueo-mensaje"><?php echo wp_kses_post($mensaje); ?></div>
        
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
            // Usar la función global YGB_Countdown del archivo externo
            var fechaObjetivo = <?php echo intval($fecha_timestamp); ?>;
            var mensajeFinal = '<?php echo esc_js(__('¡Ya estamos disponibles!', 'ygb-modo-catalogo')); ?>';
            
            if (typeof window.YGB_Countdown !== 'undefined') {
                window.YGB_Countdown.init('countdown', fechaObjetivo, mensajeFinal);
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
        
        <?php if (!empty($admin_button)): ?>
            <a href="<?php echo esc_url(wp_login_url()); ?>" class="ygb-admin-button" rel="nofollow">
                <?php _e('Acceso administradores', 'ygb-modo-catalogo'); ?>
            </a>
        <?php endif; ?>
        
        <div class="ygb-bloqueo-footer">
            &copy; <?php echo esc_html(date_i18n('Y')); ?> <?php echo esc_html(get_bloginfo('name')); ?>
        </div>
    </div>
</body>
</html>