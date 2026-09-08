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
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_ygb_mc_toggle_total_limit_%'");
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_ygb_mc_toggle_total_limit_%'");

// Limpiar WP Cron jobs programados por el plugin
$cron_jobs = array('ygb_mc_cleanup_transients');
foreach ($cron_jobs as $job) {
    $timestamp = wp_next_scheduled($job);
    if ($timestamp) {
        wp_unschedule_event($timestamp, $job);
    }
}

// Soporte Multisite: limpiar opciones de todos los blogs
if (is_multisite()) {
    $blog_ids = get_sites(array('fields' => 'ids'));
    foreach ($blog_ids as $blog_id) {
        switch_to_blog($blog_id);
        foreach ($opciones as $opcion) {
            delete_option($opcion);
        }
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_ygb_mc_toggle_limit_%'");
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_ygb_mc_toggle_limit_%'");
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_ygb_mc_toggle_total_limit_%'");
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_ygb_mc_toggle_total_limit_%'");
        
        // Limpiar cron jobs en cada blog
        foreach ($cron_jobs as $job) {
            $timestamp = wp_next_scheduled($job);
            if ($timestamp) {
                wp_unschedule_event($timestamp, $job);
            }
        }
        restore_current_blog();
    }
    
    // Limpiar sitemeta también
    $wpdb->query("DELETE FROM {$wpdb->sitemeta} WHERE meta_key LIKE '_transient_ygb_mc_toggle_limit_%'");
    $wpdb->query("DELETE FROM {$wpdb->sitemeta} WHERE meta_key LIKE '_transient_timeout_ygb_mc_toggle_limit_%'");
    $wpdb->query("DELETE FROM {$wpdb->sitemeta} WHERE meta_key LIKE '_transient_ygb_mc_toggle_total_limit_%'");
    $wpdb->query("DELETE FROM {$wpdb->sitemeta} WHERE meta_key LIKE '_transient_timeout_ygb_mc_toggle_total_limit_%'");
}

// Limpiar caché de objetos
wp_cache_flush();