<?php
/**
 * Plugin Name: YGB Modo Catálogo / Bloqueo Total
 * Plugin URI: https://github.com/yosdeny
 * Description: Activa/desactiva el modo catálogo (con countdown) o el modo bloqueo total (sin enlaces). Configuración independiente por modo.
 * Version: 2.7.3
 * Author: YGB
 * Author URI: https://github.com/yosdeny
 * Requires at least: 7.0
 * Tested up to: 7.1
 * Requires PHP: 8.0
 * Tested PHP: 8.2
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: ygb-modo-catalogo
 */

if (!defined('ABSPATH')) exit;

class YGB_ModoCatalogo {
    
    private static $instance = null;
    private $catalogo_activado = false;
    private $total_activado = false;
    private $timezone = null;

    // Valores por defecto - Modo Catálogo
    const DEFAULT_PRIMARIO = '#667eea';
    const DEFAULT_SECUNDARIO = '#764ba2';
    const DEFAULT_GRADIENTE_1 = '#667eea';
    const DEFAULT_GRADIENTE_2 = '#764ba2';
    const DEFAULT_TITULO = 'Sitio en Mantenimiento';
    const DEFAULT_MENSAJE = 'Estamos trabajando para ofrecerte una mejor experiencia.';
    
    // Valores por defecto - Bloqueo Total
    const DEFAULT_TOTAL_TITULO = 'Acceso Restringido';
    const DEFAULT_TOTAL_MENSAJE = 'El sitio no está disponible en estos momentos.';
    const DEFAULT_TOTAL_PRIMARIO = '#dc3545';
    const DEFAULT_TOTAL_SECUNDARIO = '#a71d2a';
    const DEFAULT_TOTAL_GRADIENTE_1 = '#2c3e50';
    const DEFAULT_TOTAL_GRADIENTE_2 = '#1a1a2e';
    const DEFAULT_TOTAL_ADMIN_BUTTON = true;
    const DEFAULT_TOTAL_COUNTDOWN_ACTIVO = false;
    const DEFAULT_TOTAL_COUNTDOWN_FECHA = '';

    private function __construct() {
        $this->catalogo_activado = get_option('ygb_mc_activado', false);
        $this->total_activado = get_option('ygb_mc_total_activado', false);
        $this->timezone = wp_timezone();
        
        add_action('template_redirect', array($this, 'interceptar_acceso'), 0);
        add_action('admin_menu', array($this, 'agregar_menu'));
        add_action('admin_init', array($this, 'registrar_opciones'));
        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_assets'));
        add_action('wp_ajax_ygb_mc_toggle_catalogo', array($this, 'ajax_toggle_catalogo'));
        add_action('wp_ajax_ygb_mc_toggle_total', array($this, 'ajax_toggle_total'));
        add_action('wp_ajax_nopriv_ygb_mc_check_auth', array($this, 'ajax_check_auth'));
        add_action('admin_bar_menu', array($this, 'barra_estado'), 100);
        add_action('wp_enqueue_scripts', array($this, 'cargar_estilos_personalizados'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_countdown_script'));
        add_action('init', array($this, 'init_woo_exclusions'));
        add_action('init', array($this, 'add_woo_filters'));
        add_action('send_headers', array($this, 'enviar_headers_cache'));
        add_action('admin_init', array($this, 'schedule_transient_cleanup'));
        add_action('ygb_mc_cleanup_transients', array($this, 'cleanup_transients'));
        
        register_uninstall_hook(__FILE__, array('YGB_ModoCatalogo', 'desinstalar'));
    }

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function admin_enqueue_assets($hook) {
        if ('toplevel_page_ygb-catalogo' !== $hook) return;
        wp_enqueue_media();
    }

    /**
     * Encola el script de countdown con jQuery como dependencia
     * Solo se carga cuando un modo está activo y para usuarios no autenticados
     */
    public function enqueue_countdown_script() {
        if (!$this->catalogo_activado && !$this->total_activado) {
            return;
        }
        
        if ($this->usuario_autenticado() || $this->es_peticion_auth()) {
            return;
        }

        wp_enqueue_script(
            'ygb-mc-countdown',
            plugin_dir_url(__FILE__) . 'assets/js/countdown.js',
            array('jquery'),
            '2.7.3',
            true
        );

        // Localizar script con textos traducibles
        wp_localize_script('ygb-mc-countdown', 'ygbCountdownData', array(
            'mensajeFinal' => __('¡Ya estamos disponibles!', 'ygb-modo-catalogo'),
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ygb_mc_countdown_nonce')
        ));
    }

    /**
     * Programa la limpieza diaria de transients
     * Se ejecuta en admin_init para verificar si el cron job está programado
     */
    public function schedule_transient_cleanup() {
        if (!wp_next_scheduled('ygb_mc_cleanup_transients')) {
            wp_schedule_event(time(), 'daily', 'ygb_mc_cleanup_transients');
        }
    }

    /**
     * Limpieza de transients expirados o huérfanos
     * Se ejecuta vía WP Cron diariamente
     */
    public function cleanup_transients() {
        global $wpdb;
        
        // Limpiar transients de rate limiting (expiran a 1 minuto, pero por seguridad)
        $transient_patterns = array(
            '_transient_ygb_mc_toggle_limit_%',
            '_transient_timeout_ygb_mc_toggle_limit_%',
            '_transient_ygb_mc_toggle_total_limit_%',
            '_transient_timeout_ygb_mc_toggle_total_limit_%'
        );

        if (is_multisite()) {
            foreach ($transient_patterns as $pattern) {
                $wpdb->query($wpdb->prepare(
                    "DELETE FROM {$wpdb->sitemeta} WHERE meta_key LIKE %s",
                    $pattern
                ));
            }
        } else {
            foreach ($transient_patterns as $pattern) {
                $wpdb->query($wpdb->prepare(
                    "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                    $pattern
                ));
            }
        }

        // Limpiar caché de objetos
        wp_cache_flush();
        
        error_log('[YGB] Limpieza de transients completada');
    }

    public function enviar_headers_cache() {
        if (($this->catalogo_activado || $this->total_activado) && !$this->usuario_autenticado()) {
            header('Cache-Control: no-cache, no-store, must-revalidate, max-age=0');
            header('Pragma: no-cache');
            header('Expires: Wed, 11 Jan 1984 05:00:00 GMT');
            if ($this->total_activado) {
                header('Retry-After: 86400');
            }
        }
    }

    public static function desinstalar() {
        if (!defined('WP_UNINSTALL_PLUGIN')) return;
        
        $opciones = array(
            'ygb_mc_activado','ygb_mc_titulo','ygb_mc_mensaje','ygb_mc_logo',
            'ygb_mc_clientes_url','ygb_mc_countdown_activo','ygb_mc_countdown_fecha',
            'ygb_mc_color_primario','ygb_mc_color_secundario','ygb_mc_gradiente_1','ygb_mc_gradiente_2',
            'ygb_mc_total_activado','ygb_mc_total_titulo','ygb_mc_total_mensaje','ygb_mc_total_logo',
            'ygb_mc_total_color_primario','ygb_mc_total_color_secundario',
            'ygb_mc_total_gradiente_1','ygb_mc_total_gradiente_2',
            'ygb_mc_total_admin_button','ygb_mc_total_countdown_activo','ygb_mc_total_countdown_fecha'
        );
        foreach ($opciones as $opcion) delete_option($opcion);
        
        global $wpdb;
        if (is_multisite()) {
            $wpdb->query($wpdb->prepare(
                "DELETE FROM {$wpdb->sitemeta} WHERE meta_key LIKE %s OR meta_key LIKE %s",
                '_transient_ygb_mc_toggle_limit_%','_transient_ygb_mc_toggle_total_limit_%'
            ));
        } else {
            $wpdb->query($wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
                '_transient_ygb_mc_toggle_limit_%','_transient_ygb_mc_toggle_total_limit_%'
            ));
        }
        wp_cache_flush();
    }

    public function init_woo_exclusions() {
        if (class_exists('WooCommerce')) {
            add_filter('ygb_mc_excluir_acceso', array($this, 'excluir_woo_endpoints'), 10, 2);
        }
        if (defined('YITH_WCWL')) {
            add_filter('ygb_mc_excluir_acceso', array($this, 'excluir_yith_wishlist'), 10, 2);
            add_filter('ygb_mc_login_urls', array($this, 'agregar_yith_login_urls'));
        }
    }

    /**
     * Filtros de WooCommerce con evaluación dinámica del estado.
     * Siempre están añadidos, pero consultan las opciones en tiempo real.
     */
    public function add_woo_filters() {
        if (class_exists('WooCommerce')) {
            add_filter('woocommerce_is_purchasable', array($this, 'woo_is_purchasable'), 10, 2);
            add_filter('woocommerce_add_to_cart_validation', array($this, 'woo_add_to_cart_validation'), 10, 3);
        }
    }

    public function woo_is_purchasable($purchasable, $product) {
        $catalogo = get_option('ygb_mc_activado', false);
        $total = get_option('ygb_mc_total_activado', false);
        if ($catalogo || $total) {
            return false;
        }
        return $purchasable;
    }

    public function woo_add_to_cart_validation($passed, $product_id, $quantity) {
        $catalogo = get_option('ygb_mc_activado', false);
        $total = get_option('ygb_mc_total_activado', false);
        if ($catalogo || $total) {
            return false;
        }
        return $passed;
    }

    public function excluir_woo_endpoints($excluir, $request_uri) {
        if (class_exists('WooCommerce')) {
            $path = wp_parse_url($request_uri, PHP_URL_PATH);
            if (!$path) return $excluir;
            $path = trim($path, '/');
            $woo_endpoints = array('my-account','cart','checkout','add-to-cart','lost-password','logout');
            foreach ($woo_endpoints as $endpoint) {
                if ($path === $endpoint || strpos($path, $endpoint . '/') === 0) return true;
            }
            if (isset($_GET['wc-ajax']) || strpos($request_uri, 'wc-ajax=') !== false) return true;
        }
        return $excluir;
    }

    public function excluir_yith_wishlist($excluir, $request_uri) {
        if (defined('YITH_WCWL')) {
            $path = wp_parse_url($request_uri, PHP_URL_PATH);
            if (!$path) return $excluir;
            $path = trim($path, '/');
            $yith_endpoints = array('wishlist','wishlist-view');
            foreach ($yith_endpoints as $endpoint) {
                if ($path === $endpoint || strpos($path, $endpoint . '/') === 0) return true;
            }
            if (isset($_REQUEST['yith_wcwl_ajax']) || isset($_REQUEST['wishlist-action'])) return true;
        }
        return $excluir;
    }

    public function agregar_yith_login_urls($urls) {
        return array_merge($urls, array('yith-wcwl-ajax','wc-ajax=','yith_wcwl_ajax'));
    }

    private function es_peticion_auth() {
        $auth_actions = array('yith_wcwl_ajax','wc-ajax','login','logout','auth','heartbeat');
        if (isset($_POST['action'])) {
            $action = sanitize_key($_POST['action']);
            foreach ($auth_actions as $auth) {
                if (strpos($action, $auth) !== false) return true;
            }
        }
        if (isset($_GET['action'])) {
            $action = sanitize_key($_GET['action']);
            foreach ($auth_actions as $auth) {
                if (strpos($action, $auth) !== false) return true;
            }
        }
        $auth_params = array('wc-ajax','yith_wcwl_ajax','wishlist-action');
        foreach ($auth_params as $param) {
            if (isset($_REQUEST[$param])) return true;
        }
        return false;
    }

    private function usuario_autenticado() {
        if (is_user_logged_in()) return true;
        if (!defined('LOGGED_IN_COOKIE')) return false;
        if (isset($_COOKIE[LOGGED_IN_COOKIE])) {
            $user_id = wp_validate_auth_cookie($_COOKIE[LOGGED_IN_COOKIE], 'logged_in');
            if ($user_id) {
                $user = get_userdata($user_id);
                if ($user && $user->ID > 0) return true;
            }
        }
        if (class_exists('WooCommerce') && function_exists('WC') && WC() && isset(WC()->session)) {
            $customer_id = WC()->session->get('customer_id');
            if (!empty($customer_id) && is_numeric($customer_id)) {
                $user = get_userdata($customer_id);
                if ($user && $user->ID > 0) return true;
            }
        }
        return false;
    }

    public function interceptar_acceso() {
        if (is_user_logged_in() && current_user_can('manage_options')) return;
        if (!$this->catalogo_activado && !$this->total_activado) return;
        if (wp_doing_ajax() || defined('DOING_CRON') || defined('REST_REQUEST') || is_feed()) return;
        if ($this->es_peticion_auth()) return;
        
        $request_uri = $_SERVER['REQUEST_URI'] ?? '';
        $login_path = wp_parse_url(wp_login_url(), PHP_URL_PATH);
        $admin_path = wp_parse_url(admin_url(), PHP_URL_PATH);
        if (strpos($request_uri, $login_path) !== false ||
            strpos($request_uri, $admin_path) !== false ||
            strpos($request_uri, 'wp-login.php') !== false ||
            strpos($request_uri, '/wp-admin/') !== false) {
            return;
        }
        if ($this->usuario_autenticado()) return;
        if (apply_filters('ygb_mc_excluir_acceso', false, $request_uri)) return;
        
        if ($this->total_activado) {
            $this->mostrar_bloqueo_total();
        } elseif ($this->catalogo_activado) {
            $this->mostrar_mantenimiento();
        }
        wp_die();
    }

    public function ajax_check_auth() {
        if ($this->usuario_autenticado()) {
            wp_send_json_success(array('authenticated' => true));
        } else {
            wp_send_json_error(array('authenticated' => false));
        }
    }

    private function validar_url_clientes($url) {
        if (empty($url)) return '';
        $url = esc_url_raw($url);
        if (!preg_match('/^https?:\/\//', $url)) return '';
        $site_host = wp_parse_url(home_url(), PHP_URL_HOST);
        $url_host = wp_parse_url($url, PHP_URL_HOST);
        if ($url_host !== $site_host) return '';
        $query = wp_parse_url($url, PHP_URL_QUERY);
        if ($query) {
            parse_str($query, $params);
            $dangerous = array('redirect_to','return','redirect','goto');
            foreach ($dangerous as $d) {
                if (isset($params[$d]) && !empty($params[$d])) {
                    $url = strtok($url, '?');
                    break;
                }
            }
        }
        return $url;
    }

    private function validar_fecha_countdown($fecha) {
        if (empty($fecha)) return '';
        try {
            $fecha_obj = DateTime::createFromFormat('Y-m-d\TH:i', $fecha, $this->timezone);
            if (!$fecha_obj || $fecha_obj->format('Y-m-d\TH:i') !== $fecha) {
                throw new Exception('Formato inválido');
            }
            $ahora = new DateTime('now', $this->timezone);
            $minima = clone $ahora;
            $minima->modify('+5 minutes');
            if ($fecha_obj <= $minima) {
                update_option('ygb_mc_countdown_activo', false);
                return '';
            }
            return $fecha;
        } catch (Exception $e) {
            // Solo agregar error si estamos en contexto de admin
            if (is_admin() && function_exists('add_settings_error')) {
                add_settings_error('ygb_mc_opciones','invalid_countdown_date',
                    __('La fecha del countdown no es válida. Usa el formato YYYY-MM-DD HH:MM', 'ygb-modo-catalogo')
                );
            }
            delete_option('ygb_mc_countdown_fecha');
            return '';
        }
    }

    private function validar_fecha_countdown_total($fecha) {
        if (empty($fecha)) return '';
        try {
            $fecha_obj = DateTime::createFromFormat('Y-m-d\TH:i', $fecha, $this->timezone);
            if (!$fecha_obj || $fecha_obj->format('Y-m-d\TH:i') !== $fecha) {
                throw new Exception('Formato inválido');
            }
            $ahora = new DateTime('now', $this->timezone);
            $minima = clone $ahora;
            $minima->modify('+5 minutes');
            if ($fecha_obj <= $minima) {
                update_option('ygb_mc_total_countdown_activo', false);
                return '';
            }
            return $fecha;
        } catch (Exception $e) {
            // Solo agregar error si estamos en contexto de admin
            if (is_admin() && function_exists('add_settings_error')) {
                add_settings_error('ygb_mc_opciones','invalid_countdown_date_total',
                    __('La fecha del countdown no es válida. Usa el formato YYYY-MM-DD HH:MM', 'ygb-modo-catalogo')
                );
            }
            delete_option('ygb_mc_total_countdown_fecha');
            return '';
        }
    }

    private function validar_logo($url) {
        $url = esc_url_raw($url);
        if (empty($url)) return '';
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            add_settings_error('ygb_mc_opciones','invalid_logo','La URL del logo no es válida.');
            return get_option('ygb_mc_logo', '');
        }
        $exts = array('jpg','jpeg','png','gif','svg','webp');
        $ext = strtolower(pathinfo($url, PATHINFO_EXTENSION));
        if (!empty($ext) && !in_array($ext, $exts)) {
            if (strpos($url, 'wp-content/uploads') !== false) return $url;
            add_settings_error('ygb_mc_opciones','invalid_logo_type','El logo debe ser una imagen (JPG, PNG, GIF, SVG, WEBP).');
            return get_option('ygb_mc_logo', '');
        }
        return $url;
    }

    private function validar_color_css($color) {
        $color = sanitize_text_field($color);
        $color = trim($color);
        if (empty($color)) return self::DEFAULT_PRIMARIO;
        $validated = sanitize_hex_color($color);
        if (!empty($validated)) return $validated;
        $safe = array('white','black','red','green','blue','yellow','cyan','magenta',
            'gray','grey','lightgray','darkgray','orange','purple','pink','brown','transparent');
        if (in_array(strtolower($color), $safe)) return strtolower($color);
        return self::DEFAULT_PRIMARIO;
    }

    private function get_clientes_url() {
        $url = get_option('ygb_mc_clientes_url', '');
        if (!empty($url)) {
            $url = $this->validar_url_clientes($url);
            if (!empty($url)) return $url;
        }
        if (class_exists('WooCommerce') && function_exists('wc_get_page_permalink')) {
            return wc_get_page_permalink('myaccount');
        }
        return wp_login_url();
    }

    private function mostrar_mantenimiento() {
        status_header(503);
        header('Cache-Control: no-cache, no-store, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: Wed, 11 Jan 1984 05:00:00 GMT');
        header('Retry-After: 3600');
        
        $titulo = esc_html(get_option('ygb_mc_titulo', self::DEFAULT_TITULO));
        $mensaje = wp_kses_post(get_option('ygb_mc_mensaje', self::DEFAULT_MENSAJE));
        $logo = esc_url(get_option('ygb_mc_logo', ''));
        $clientes_url = esc_url($this->get_clientes_url());
        $countdown_activo = get_option('ygb_mc_countdown_activo', false);
        $countdown_fecha = get_option('ygb_mc_countdown_fecha', '');
        $gradiente_1 = esc_attr(get_option('ygb_mc_gradiente_1', self::DEFAULT_GRADIENTE_1));
        $gradiente_2 = esc_attr(get_option('ygb_mc_gradiente_2', self::DEFAULT_GRADIENTE_2));
        
        include plugin_dir_path(__FILE__) . 'plantilla-mantenimiento.php';
        wp_die();
    }

    private function mostrar_bloqueo_total() {
        status_header(503);
        header('Cache-Control: no-cache, no-store, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: Wed, 11 Jan 1984 05:00:00 GMT');
        header('Retry-After: 86400');
        
        $titulo = esc_html(get_option('ygb_mc_total_titulo', self::DEFAULT_TOTAL_TITULO));
        $mensaje = wp_kses_post(get_option('ygb_mc_total_mensaje', self::DEFAULT_TOTAL_MENSAJE));
        $logo = esc_url(get_option('ygb_mc_total_logo', ''));
        $gradiente_1 = esc_attr(get_option('ygb_mc_total_gradiente_1', self::DEFAULT_TOTAL_GRADIENTE_1));
        $gradiente_2 = esc_attr(get_option('ygb_mc_total_gradiente_2', self::DEFAULT_TOTAL_GRADIENTE_2));
        $admin_button = (bool) get_option('ygb_mc_total_admin_button', self::DEFAULT_TOTAL_ADMIN_BUTTON);
        $countdown_activo = get_option('ygb_mc_total_countdown_activo', false);
        $countdown_fecha = get_option('ygb_mc_total_countdown_fecha', '');
        $color_primario = esc_attr(get_option('ygb_mc_total_color_primario', self::DEFAULT_TOTAL_PRIMARIO));
        $color_secundario = esc_attr(get_option('ygb_mc_total_color_secundario', self::DEFAULT_TOTAL_SECUNDARIO));
        
        include plugin_dir_path(__FILE__) . 'plantilla-bloqueo-total.php';
        wp_die();
    }

    public function cargar_estilos_personalizados() {
        $modo_activo = false;
        if ($this->total_activado) {
            $modo_activo = true;
            $primario = get_option('ygb_mc_total_color_primario', self::DEFAULT_TOTAL_PRIMARIO);
            $secundario = get_option('ygb_mc_total_color_secundario', self::DEFAULT_TOTAL_SECUNDARIO);
            $gradiente_1 = get_option('ygb_mc_total_gradiente_1', self::DEFAULT_TOTAL_GRADIENTE_1);
            $gradiente_2 = get_option('ygb_mc_total_gradiente_2', self::DEFAULT_TOTAL_GRADIENTE_2);
        } elseif ($this->catalogo_activado) {
            $modo_activo = true;
            $primario = get_option('ygb_mc_color_primario', self::DEFAULT_PRIMARIO);
            $secundario = get_option('ygb_mc_color_secundario', self::DEFAULT_SECUNDARIO);
            $gradiente_1 = get_option('ygb_mc_gradiente_1', self::DEFAULT_GRADIENTE_1);
            $gradiente_2 = get_option('ygb_mc_gradiente_2', self::DEFAULT_GRADIENTE_2);
        }
        if (!$modo_activo || $this->usuario_autenticado() || $this->es_peticion_auth()) return;
        
        wp_enqueue_style('ygb-mc-estilos', plugin_dir_url(__FILE__) . 'assets/css/estilos.css', array(), '2.7.2');
        
        $primario = $this->validar_color_css($primario);
        $secundario = $this->validar_color_css($secundario);
        $gradiente_1 = $this->validar_color_css($gradiente_1);
        $gradiente_2 = $this->validar_color_css($gradiente_2);
        
        $custom_css = "
            .ygb-boton {
                background: {$primario} !important;
                color: white !important;
                border: 1px solid {$primario} !important;
            }
            .ygb-boton:hover {
                background: {$secundario} !important;
                color: white !important;
                border: 1px solid {$secundario} !important;
            }
            .ygb-boton-outline {
                background: transparent !important;
                border: 1px solid {$primario} !important;
                color: {$primario} !important;
            }
            .ygb-boton-outline:hover {
                background: transparent !important;
                border: 2px solid {$primario} !important;
                color: {$primario} !important;
            }
            .ygb-countdown {
                background: linear-gradient(135deg, {$gradiente_1} 0%, {$gradiente_2} 100%) !important;
            }
            .ygb-countdown-item {
                background: rgba(255, 255, 255, 0.2) !important;
                backdrop-filter: blur(5px) !important;
                border: 1px solid rgba(255, 255, 255, 0.3) !important;
            }
            .ygb-countdown-numero,
            .ygb-countdown-etiqueta,
            .ygb-countdown-titulo {
                color: white !important;
            }
        ";
        wp_add_inline_style('ygb-mc-estilos', $custom_css);
    }

    public function agregar_menu() {
        add_menu_page(
            __('YGB Control', 'ygb-modo-catalogo'),
            __('YGB Control', 'ygb-modo-catalogo'),
            'manage_options',
            'ygb-catalogo',
            array($this, 'pagina_ajustes'),
            'dashicons-visibility',
            30
        );
    }

    public function barra_estado($wp_admin_bar) {
        if (!current_user_can('manage_options')) return;
        $catalogo_color = $this->catalogo_activado ? '#f00' : '#0a0';
        $catalogo_texto = $this->catalogo_activado ? '🔴 CATÁLOGO ON' : '🟢 CATÁLOGO OFF';
        $total_color = $this->total_activado ? '#f00' : '#0a0';
        $total_texto = $this->total_activado ? '🔴 BLOQUEO TOTAL ON' : '🟢 BLOQUEO TOTAL OFF';
        
        $wp_admin_bar->add_node(array(
            'id' => 'ygb-mc-estado',
            'title' => '<span style="background:' . esc_attr($catalogo_color) . ';color:white;padding:2px 8px;border-radius:20px;margin-right:5px;">' . esc_html($catalogo_texto) . '</span>' .
                       '<span style="background:' . esc_attr($total_color) . ';color:white;padding:2px 8px;border-radius:20px;">' . esc_html($total_texto) . '</span>',
            'href' => admin_url('admin.php?page=ygb-catalogo')
        ));
        $wp_admin_bar->add_node(array(
            'id' => 'ygb-mc-toggle-catalogo',
            'parent' => 'ygb-mc-estado',
            'title' => '⚡ ' . ($this->catalogo_activado ? __('Desactivar Catálogo', 'ygb-modo-catalogo') : __('Activar Catálogo', 'ygb-modo-catalogo')),
            'href' => '#',
            'meta' => array('onclick' => 'ygbToggleCatalogo();return false;')
        ));
        $wp_admin_bar->add_node(array(
            'id' => 'ygb-mc-toggle-total',
            'parent' => 'ygb-mc-estado',
            'title' => '🔒 ' . ($this->total_activado ? __('Desactivar Bloqueo Total', 'ygb-modo-catalogo') : __('Activar Bloqueo Total', 'ygb-modo-catalogo')),
            'href' => '#',
            'meta' => array('onclick' => 'ygbToggleTotal();return false;')
        ));
        
        add_action('admin_footer', function() {
            if (!is_admin_bar_showing()) return;
            
            // Generar nonces frescos para evitar expiración
            $nonce_catalogo = wp_create_nonce('ygb_mc_nonce_catalogo');
            $nonce_total = wp_create_nonce('ygb_mc_nonce_total');
            ?>
            <script>
            // Definir ajaxurl correctamente
            var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
            
            function ygbToggleCatalogo() {
                if(confirm('<?php echo esc_js(__('¿Cambiar estado del MODO CATÁLOGO?', 'ygb-modo-catalogo')); ?>')) {
                    jQuery.post(ajaxurl, {
                        action: 'ygb_mc_toggle_catalogo',
                        nonce: '<?php echo esc_js($nonce_catalogo); ?>'
                    }).done(function(response) {
                        if (response.success) location.reload();
                        else alert(response.data || 'Error');
                    }).fail(function() { alert('Error de conexión'); });
                }
                return false;
            }
            function ygbToggleTotal() {
                if(confirm('<?php echo esc_js(__('¿Cambiar estado del MODO BLOQUEO TOTAL?', 'ygb-modo-catalogo')); ?>')) {
                    jQuery.post(ajaxurl, {
                        action: 'ygb_mc_toggle_total',
                        nonce: '<?php echo esc_js($nonce_total); ?>'
                    }).done(function(response) {
                        if (response.success) location.reload();
                        else alert(response.data || 'Error');
                    }).fail(function() { alert('Error de conexión'); });
                }
                return false;
            }
            </script>
            <?php
        });
    }

    public function pagina_ajustes() {
        if (isset($_GET['settings-updated']) && $_GET['settings-updated'] == 'true') {
            echo '<div class="notice notice-success is-dismissible"><p>' . __('Cambios guardados correctamente.', 'ygb-modo-catalogo') . '</p></div>';
        }
        ?>
        <div class="wrap">
            <h1><?php _e('YGB Control - Modos de Acceso', 'ygb-modo-catalogo'); ?></h1>
            
            <div style="background:#fff;padding:20px;margin:20px 0;border:1px solid #ccc;border-radius:5px;">
                <h2 style="margin-top:0;"><?php _e('Control Rápido de Modos', 'ygb-modo-catalogo'); ?></h2>
                <div style="display:flex; gap:20px; flex-wrap:wrap;">
                    <div>
                        <h3>📦 Modo Catálogo</h3>
                        <button type="button" id="ygb-toggle-catalogo" class="button button-primary" style="background:<?php echo $this->catalogo_activado?'#0a0':'#f00'; ?>;border:none;padding:8px 20px;height:auto;">
                            <?php echo $this->catalogo_activado ? '🔴 ' . __('DESACTIVAR CATÁLOGO', 'ygb-modo-catalogo') : '🟢 ' . __('ACTIVAR CATÁLOGO', 'ygb-modo-catalogo'); ?>
                        </button>
                        <p class="description"><?php _e('Bloquea visitantes, muestra countdown y botón clientes', 'ygb-modo-catalogo'); ?></p>
                    </div>
                    <div>
                        <h3>🔒 Modo Bloqueo Total</h3>
                        <button type="button" id="ygb-toggle-total" class="button button-primary" style="background:<?php echo $this->total_activado?'#0a0':'#f00'; ?>;border:none;padding:8px 20px;height:auto;">
                            <?php echo $this->total_activado ? '🔓 ' . __('DESACTIVAR BLOQUEO TOTAL', 'ygb-modo-catalogo') : '🔒 ' . __('ACTIVAR BLOQUEO TOTAL', 'ygb-modo-catalogo'); ?>
                        </button>
                        <p class="description"><?php _e('Bloquea TODO (sin enlaces, sin countdown)', 'ygb-modo-catalogo'); ?></p>
                    </div>
                </div>
                <p class="description">⚠️ <?php _e('Si ambos modos están activos, prevalece el BLOQUEO TOTAL.', 'ygb-modo-catalogo'); ?></p>
            </div>

            <h2 class="nav-tab-wrapper">
                <a href="#catalogo" class="nav-tab nav-tab-active" id="tab-catalogo-link">📦 <?php _e('Modo Catálogo', 'ygb-modo-catalogo'); ?></a>
                <a href="#total" class="nav-tab" id="tab-total-link">🔒 <?php _e('Bloqueo Total', 'ygb-modo-catalogo'); ?></a>
            </h2>

            <div id="catalogo-tab" class="tab-content" style="display:block;">
                <form method="post" action="options.php" style="background:#fff;padding:20px;border:1px solid #ccc;border-radius:5px;margin-top:20px;">
                    <?php settings_fields('ygb_mc_opciones_catalogo'); ?>
                    <input type="hidden" name="ygb_mc_activado" value="<?php echo $this->catalogo_activado?'1':'0'; ?>">
                    
                    <h3>⚙️ <?php _e('Configuración del Modo Catálogo', 'ygb-modo-catalogo'); ?></h3>
                    <table class="form-table">
                        <tr>
                            <th scope="row"><label for="ygb_mc_titulo"><?php _e('Título', 'ygb-modo-catalogo'); ?></label></th>
                            <td><input type="text" name="ygb_mc_titulo" id="ygb_mc_titulo" value="<?php echo esc_attr(get_option('ygb_mc_titulo', self::DEFAULT_TITULO)); ?>" class="regular-text"></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="ygb_mc_mensaje"><?php _e('Mensaje', 'ygb-modo-catalogo'); ?></label></th>
                            <td><textarea name="ygb_mc_mensaje" id="ygb_mc_mensaje" rows="4" class="large-text"><?php echo esc_textarea(get_option('ygb_mc_mensaje', self::DEFAULT_MENSAJE)); ?></textarea></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="ygb_mc_logo"><?php _e('Logo', 'ygb-modo-catalogo'); ?></label></th>
                            <td>
                                <input type="text" name="ygb_mc_logo" id="ygb_mc_logo" value="<?php echo esc_attr(get_option('ygb_mc_logo', '')); ?>" class="regular-text">
                                <button type="button" class="button subir_logo"><?php _e('Seleccionar logo', 'ygb-modo-catalogo'); ?></button>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="ygb_mc_clientes_url"><?php _e('URL para Clientes', 'ygb-modo-catalogo'); ?></label></th>
                            <td><input type="url" name="ygb_mc_clientes_url" id="ygb_mc_clientes_url" value="<?php echo esc_attr(get_option('ygb_mc_clientes_url', '')); ?>" class="regular-text"></td>
                        </tr>
                    </table>

                    <h3>⏱️ <?php _e('Countdown', 'ygb-modo-catalogo'); ?></h3>
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php _e('Activar Countdown', 'ygb-modo-catalogo'); ?></th>
                            <td><label><input type="checkbox" name="ygb_mc_countdown_activo" value="1" <?php checked(get_option('ygb_mc_countdown_activo', false)); ?>> <?php _e('Mostrar cuenta regresiva', 'ygb-modo-catalogo'); ?></label></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="ygb_mc_countdown_fecha"><?php _e('Fecha de finalización', 'ygb-modo-catalogo'); ?></label></th>
                            <td><input type="datetime-local" name="ygb_mc_countdown_fecha" id="ygb_mc_countdown_fecha" value="<?php echo esc_attr(get_option('ygb_mc_countdown_fecha', '')); ?>" class="regular-text"></td>
                        </tr>
                    </table>

                    <h3>🎨 <?php _e('Colores', 'ygb-modo-catalogo'); ?></h3>
                    <table class="form-table">
                        <tr><th scope="row"><?php _e('Color Primario Botones', 'ygb-modo-catalogo'); ?></th><td><input type="color" name="ygb_mc_color_primario" value="<?php echo esc_attr(get_option('ygb_mc_color_primario', self::DEFAULT_PRIMARIO)); ?>"></td></tr>
                        <tr><th scope="row"><?php _e('Color Secundario Botones', 'ygb-modo-catalogo'); ?></th><td><input type="color" name="ygb_mc_color_secundario" value="<?php echo esc_attr(get_option('ygb_mc_color_secundario', self::DEFAULT_SECUNDARIO)); ?>"></td></tr>
                        <tr><th scope="row"><?php _e('Gradiente Countdown 1', 'ygb-modo-catalogo'); ?></th><td><input type="color" name="ygb_mc_gradiente_1" value="<?php echo esc_attr(get_option('ygb_mc_gradiente_1', self::DEFAULT_GRADIENTE_1)); ?>"></td></tr>
                        <tr><th scope="row"><?php _e('Gradiente Countdown 2', 'ygb-modo-catalogo'); ?></th><td><input type="color" name="ygb_mc_gradiente_2" value="<?php echo esc_attr(get_option('ygb_mc_gradiente_2', self::DEFAULT_GRADIENTE_2)); ?>"></td></tr>
                    </table>
                    
                    <?php submit_button(__('Guardar configuración de Catálogo', 'ygb-modo-catalogo')); ?>
                </form>
            </div>

            <div id="total-tab" class="tab-content" style="display:none;">
                <form method="post" action="options.php" style="background:#fff;padding:20px;border:1px solid #ccc;border-radius:5px;margin-top:20px;">
                    <?php settings_fields('ygb_mc_opciones_total'); ?>
                    <input type="hidden" name="ygb_mc_total_activado" value="<?php echo $this->total_activado?'1':'0'; ?>">
                    
                    <h3>🔒 <?php _e('Configuración del Bloqueo Total', 'ygb-modo-catalogo'); ?></h3>
                    <table class="form-table">
                        <tr>
                            <th scope="row"><label for="ygb_mc_total_titulo"><?php _e('Título', 'ygb-modo-catalogo'); ?></label></th>
                            <td><input type="text" name="ygb_mc_total_titulo" id="ygb_mc_total_titulo" value="<?php echo esc_attr(get_option('ygb_mc_total_titulo', self::DEFAULT_TOTAL_TITULO)); ?>" class="regular-text"></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="ygb_mc_total_mensaje"><?php _e('Mensaje', 'ygb-modo-catalogo'); ?></label></th>
                            <td><textarea name="ygb_mc_total_mensaje" id="ygb_mc_total_mensaje" rows="4" class="large-text"><?php echo esc_textarea(get_option('ygb_mc_total_mensaje', self::DEFAULT_TOTAL_MENSAJE)); ?></textarea></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="ygb_mc_total_logo"><?php _e('Logo', 'ygb-modo-catalogo'); ?></label></th>
                            <td>
                                <input type="text" name="ygb_mc_total_logo" id="ygb_mc_total_logo" value="<?php echo esc_attr(get_option('ygb_mc_total_logo', '')); ?>" class="regular-text">
                                <button type="button" class="button subir_logo_total"><?php _e('Seleccionar logo', 'ygb-modo-catalogo'); ?></button>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('Botón de acceso para administradores', 'ygb-modo-catalogo'); ?></th>
                            <td>
                                <input type="hidden" name="ygb_mc_total_admin_button" value="0">
                                <label>
                                    <input type="checkbox" name="ygb_mc_total_admin_button" value="1" <?php checked(get_option('ygb_mc_total_admin_button', self::DEFAULT_TOTAL_ADMIN_BUTTON)); ?>>
                                    <?php _e('Mostrar enlace a la página de login (solo para administradores)', 'ygb-modo-catalogo'); ?>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('Countdown en Bloqueo Total', 'ygb-modo-catalogo'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="ygb_mc_total_countdown_activo" value="1" <?php checked(get_option('ygb_mc_total_countdown_activo', false)); ?>>
                                    <?php _e('Mostrar cuenta regresiva', 'ygb-modo-catalogo'); ?>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="ygb_mc_total_countdown_fecha"><?php _e('Fecha de finalización (Bloqueo Total)', 'ygb-modo-catalogo'); ?></label></th>
                            <td>
                                <input type="datetime-local" name="ygb_mc_total_countdown_fecha" id="ygb_mc_total_countdown_fecha" value="<?php echo esc_attr(get_option('ygb_mc_total_countdown_fecha', '')); ?>" class="regular-text">
                            </td>
                        </tr>
                    </table>

                    <h3>🎨 <?php _e('Colores (fondo y botones)', 'ygb-modo-catalogo'); ?></h3>
                    <table class="form-table">
                        <tr><th scope="row"><?php _e('Color Primario (botones)', 'ygb-modo-catalogo'); ?></th><td><input type="color" name="ygb_mc_total_color_primario" value="<?php echo esc_attr(get_option('ygb_mc_total_color_primario', self::DEFAULT_TOTAL_PRIMARIO)); ?>"></td></tr>
                        <tr><th scope="row"><?php _e('Color Secundario (hover)', 'ygb-modo-catalogo'); ?></th><td><input type="color" name="ygb_mc_total_color_secundario" value="<?php echo esc_attr(get_option('ygb_mc_total_color_secundario', self::DEFAULT_TOTAL_SECUNDARIO)); ?>"></td></tr>
                        <tr><th scope="row"><?php _e('Gradiente Fondo 1', 'ygb-modo-catalogo'); ?></th><td><input type="color" name="ygb_mc_total_gradiente_1" value="<?php echo esc_attr(get_option('ygb_mc_total_gradiente_1', self::DEFAULT_TOTAL_GRADIENTE_1)); ?>"></td></tr>
                        <tr><th scope="row"><?php _e('Gradiente Fondo 2', 'ygb-modo-catalogo'); ?></th><td><input type="color" name="ygb_mc_total_gradiente_2" value="<?php echo esc_attr(get_option('ygb_mc_total_gradiente_2', self::DEFAULT_TOTAL_GRADIENTE_2)); ?>"></td></tr>
                    </table>
                    
                    <?php submit_button(__('Guardar configuración de Bloqueo Total', 'ygb-modo-catalogo')); ?>
                </form>
            </div>
            
            <div style="margin-top: 20px; padding: 15px; background: #f0f8ff; border-left: 4px solid #0073aa; border-radius: 5px;">
                <h3>🔧 <?php _e('Compatibilidad', 'ygb-modo-catalogo'); ?></h3>
                <p><?php _e('Compatible con WooCommerce, YITH Wishlist, login AJAX. El modo Bloqueo Total NO muestra ningún enlace de acceso.', 'ygb-modo-catalogo'); ?></p>
            </div>
        </div>

        <script>
        jQuery(document).ready(function($) {
            // Definir ajaxurl correctamente
            var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
            
            // Generar nonces frescos (se regeneran en cada carga de página)
            var nonceCatalogo = '<?php echo esc_js(wp_create_nonce('ygb_mc_nonce_catalogo')); ?>';
            var nonceTotal = '<?php echo esc_js(wp_create_nonce('ygb_mc_nonce_total')); ?>';
            
            // Tabs
            $('#tab-catalogo-link').click(function(e) {
                e.preventDefault();
                $('.nav-tab').removeClass('nav-tab-active');
                $(this).addClass('nav-tab-active');
                $('.tab-content').hide();
                $('#catalogo-tab').show();
            });
            $('#tab-total-link').click(function(e) {
                e.preventDefault();
                $('.nav-tab').removeClass('nav-tab-active');
                $(this).addClass('nav-tab-active');
                $('.tab-content').hide();
                $('#total-tab').show();
            });

            // Toggle Catálogo
            $('#ygb-toggle-catalogo').click(function() {
                var $btn = $(this);
                $btn.prop('disabled', true).text('<?php echo esc_js(__('Procesando...', 'ygb-modo-catalogo')); ?>');
                $.post(ajaxurl, { action: 'ygb_mc_toggle_catalogo', nonce: nonceCatalogo })
                .done(function(response) {
                    if(response.success) location.reload();
                    else alert(response.data);
                }).fail(function() { alert('Error'); })
                .always(function() { $btn.prop('disabled', false); });
            });

            // Toggle Total
            $('#ygb-toggle-total').click(function() {
                var $btn = $(this);
                $btn.prop('disabled', true).text('<?php echo esc_js(__('Procesando...', 'ygb-modo-catalogo')); ?>');
                $.post(ajaxurl, { action: 'ygb_mc_toggle_total', nonce: nonceTotal })
                .done(function(response) {
                    if(response.success) location.reload();
                    else alert(response.data);
                }).fail(function() { alert('Error'); })
                .always(function() { $btn.prop('disabled', false); });
            });

            // Media uploader para logos
            $('.subir_logo').click(function(e) {
                e.preventDefault();
                if (typeof wp !== 'undefined' && wp.media) {
                    var frame = wp.media({ title: '<?php echo esc_js(__('Seleccionar logo', 'ygb-modo-catalogo')); ?>', multiple: false, library: { type: 'image' } });
                    frame.on('select', function() { var url = frame.state().get('selection').first().toJSON().url; $('#ygb_mc_logo').val(url); });
                    frame.open();
                } else { alert('<?php echo esc_js(__('Librería de medios no disponible', 'ygb-modo-catalogo')); ?>'); }
            });
            $('.subir_logo_total').click(function(e) {
                e.preventDefault();
                if (typeof wp !== 'undefined' && wp.media) {
                    var frame = wp.media({ title: '<?php echo esc_js(__('Seleccionar logo', 'ygb-modo-catalogo')); ?>', multiple: false, library: { type: 'image' } });
                    frame.on('select', function() { var url = frame.state().get('selection').first().toJSON().url; $('#ygb_mc_total_logo').val(url); });
                    frame.open();
                } else { alert('<?php echo esc_js(__('Librería de medios no disponible', 'ygb-modo-catalogo')); ?>'); }
            });
        });
        </script>
        <?php
    }

    public function registrar_opciones() {
        // Catálogo
        register_setting('ygb_mc_opciones_catalogo', 'ygb_mc_activado');
        register_setting('ygb_mc_opciones_catalogo', 'ygb_mc_titulo', 'sanitize_text_field');
        register_setting('ygb_mc_opciones_catalogo', 'ygb_mc_mensaje', 'wp_kses_post');
        register_setting('ygb_mc_opciones_catalogo', 'ygb_mc_logo', array($this, 'validar_logo'));
        register_setting('ygb_mc_opciones_catalogo', 'ygb_mc_clientes_url', array($this, 'validar_url_clientes'));
        register_setting('ygb_mc_opciones_catalogo', 'ygb_mc_countdown_activo');
        register_setting('ygb_mc_opciones_catalogo', 'ygb_mc_countdown_fecha', array($this, 'validar_fecha_countdown'));
        register_setting('ygb_mc_opciones_catalogo', 'ygb_mc_color_primario', array($this, 'validar_color_css'));
        register_setting('ygb_mc_opciones_catalogo', 'ygb_mc_color_secundario', array($this, 'validar_color_css'));
        register_setting('ygb_mc_opciones_catalogo', 'ygb_mc_gradiente_1', array($this, 'validar_color_css'));
        register_setting('ygb_mc_opciones_catalogo', 'ygb_mc_gradiente_2', array($this, 'validar_color_css'));

        // Bloqueo Total
        register_setting('ygb_mc_opciones_total', 'ygb_mc_total_activado');
        register_setting('ygb_mc_opciones_total', 'ygb_mc_total_titulo', 'sanitize_text_field');
        register_setting('ygb_mc_opciones_total', 'ygb_mc_total_mensaje', 'wp_kses_post');
        register_setting('ygb_mc_opciones_total', 'ygb_mc_total_logo', array($this, 'validar_logo'));
        register_setting('ygb_mc_opciones_total', 'ygb_mc_total_admin_button', 'rest_sanitize_boolean');
        register_setting('ygb_mc_opciones_total', 'ygb_mc_total_countdown_activo');
        register_setting('ygb_mc_opciones_total', 'ygb_mc_total_countdown_fecha', array($this, 'validar_fecha_countdown_total'));
        register_setting('ygb_mc_opciones_total', 'ygb_mc_total_color_primario', array($this, 'validar_color_css'));
        register_setting('ygb_mc_opciones_total', 'ygb_mc_total_color_secundario', array($this, 'validar_color_css'));
        register_setting('ygb_mc_opciones_total', 'ygb_mc_total_gradiente_1', array($this, 'validar_color_css'));
        register_setting('ygb_mc_opciones_total', 'ygb_mc_total_gradiente_2', array($this, 'validar_color_css'));
    }

    public function ajax_toggle_catalogo() {
        check_ajax_referer('ygb_mc_nonce_catalogo', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('No tienes permisos.', 'ygb-modo-catalogo'));
        }
        
        $user_id = get_current_user_id();
        $blog_id = get_current_blog_id();
        $transient_key = 'ygb_mc_toggle_limit_catalogo_' . $blog_id . '_' . $user_id;
        $attempts = get_transient($transient_key);
        if ($attempts !== false && $attempts >= 3) {
            wp_send_json_error(__('Demasiados intentos. Espera 1 minuto.', 'ygb-modo-catalogo'));
        }
        $this->log_actividad($user_id, 'catalogo', !$this->catalogo_activado ? 'activado' : 'desactivado');
        $nuevo_estado = !$this->catalogo_activado;
        if ($nuevo_estado && $this->total_activado) {
            update_option('ygb_mc_total_activado', false);
            $this->total_activado = false;
            $this->log_actividad($user_id, 'total', 'desactivado por exclusión desde catálogo');
        }
        update_option('ygb_mc_activado', $nuevo_estado);
        $this->catalogo_activado = $nuevo_estado;
        
        // Limpiar cachés para que los cambios se reflejen inmediatamente
        if (function_exists('wc_clear_notices')) {
            wc_clear_notices();
        }
        if (function_exists('WC') && isset(WC()->cache)) {
            WC()->cache->flush();
        }
        wp_cache_flush();
        
        if ($attempts === false) set_transient($transient_key, 1, MINUTE_IN_SECONDS);
        else set_transient($transient_key, $attempts + 1, MINUTE_IN_SECONDS);
        wp_send_json_success(array('message' => __('Catálogo cambiado', 'ygb-modo-catalogo'), 'estado' => $nuevo_estado));
    }
    
    public function ajax_toggle_total() {
        check_ajax_referer('ygb_mc_nonce_total', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('No tienes permisos.', 'ygb-modo-catalogo'));
        }
        
        $user_id = get_current_user_id();
        $blog_id = get_current_blog_id();
        $transient_key = 'ygb_mc_toggle_limit_total_' . $blog_id . '_' . $user_id;
        $attempts = get_transient($transient_key);
        if ($attempts !== false && $attempts >= 3) {
            wp_send_json_error(__('Demasiados intentos. Espera 1 minuto.', 'ygb-modo-catalogo'));
        }
        $this->log_actividad($user_id, 'total', !$this->total_activado ? 'activado' : 'desactivado');
        $nuevo_estado = !$this->total_activado;
        if ($nuevo_estado && $this->catalogo_activado) {
            update_option('ygb_mc_activado', false);
            $this->catalogo_activado = false;
            $this->log_actividad($user_id, 'catalogo', 'desactivado por exclusión desde total');
        }
        update_option('ygb_mc_total_activado', $nuevo_estado);
        $this->total_activado = $nuevo_estado;
        
        // Limpiar cachés para que los cambios se reflejen inmediatamente
        if (function_exists('wc_clear_notices')) {
            wc_clear_notices();
        }
        if (function_exists('WC') && isset(WC()->cache)) {
            WC()->cache->flush();
        }
        wp_cache_flush();
        
        if ($attempts === false) set_transient($transient_key, 1, MINUTE_IN_SECONDS);
        else set_transient($transient_key, $attempts + 1, MINUTE_IN_SECONDS);
        wp_send_json_success(array('message' => __('Bloqueo Total cambiado', 'ygb-modo-catalogo'), 'estado' => $nuevo_estado));
    }
    
    private function log_actividad($user_id, $modo, $accion) {
        if (!defined('WP_DEBUG_LOG') || !WP_DEBUG_LOG) return;
        $user = get_userdata($user_id);
        $username = $user ? $user->user_login : __('desconocido', 'ygb-modo-catalogo');
        error_log(sprintf('[YGB] %s - Usuario %s (ID:%d) %s modo %s', current_time('mysql'), $username, $user_id, $accion, $modo));
    }
}

// Inicializar
YGB_ModoCatalogo::get_instance();