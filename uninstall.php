<?php
/**
 * Uninstall script for YGB Modo Catálogo
 * Limpia todas las opciones del plugin al desinstalarlo
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit; // Previene ejecución directa
}

// Lista de opciones a eliminar
$opciones = array(
    'ygb_mc_activado',
    'ygb_mc_titulo',
    'ygb_mc_mensaje',
    'ygb_mc_logo',
    'ygb_mc_clientes_url',
    'ygb_mc_countdown_activo',
    'ygb_mc_countdown_fecha',
    'ygb_mc_color_primario',
    'ygb_mc_color_secundario',
    'ygb_mc_gradiente_1',
    'ygb_mc_gradiente_2'
);

// Eliminar opciones
foreach ($opciones as $opcion) {
    delete_option($opcion);
}

// Eliminar transients relacionados con rate limiting
global $wpdb;
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_ygb_mc_toggle_limit_%'");
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_ygb_mc_toggle_limit_%'");