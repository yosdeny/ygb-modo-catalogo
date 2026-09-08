=== YGB Modo Catálogo / Bloqueo Total ===
Contributors: yosdeny
Tags: catalogo, modo catalogo, mantenimiento, bloqueo, coming soon
Requires at least: 7.0
Tested up to: 6.7
Stable tag: 2.7.3
Requires PHP: 8.0
Tested PHP: 8.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Activa/desactiva el modo catálogo (con countdown) o el modo bloqueo total (sin enlaces). Configuración independiente por modo.

== Description ==

Este plugin permite activar dos modos de funcionamiento para tu sitio WordPress:

= Modo Catálogo =
* Muestra productos sin funcionalidad de compra
* Incluye contador regresivo (countdown)
* Personalización de colores y mensajes

= Modo Bloqueo Total =
* Bloquea el acceso al sitio con página de mantenimiento
* Sin enlaces navegables
* Personalización completa de la página de bloqueo

Mejoras en la versión 2.7.3:
* Documentación corregida sobre el uso simultáneo de modos
* JavaScript extraído a archivo externo para mejor mantenibilidad
* jQuery encolado explícitamente para mayor compatibilidad
* Limpieza automática de transients mediante WP Cron
* Mejor soporte para instalaciones multisite
* Manejo consistente de excepciones en todo el plugin

== Installation ==

1. Sube los archivos del plugin a la carpeta `/wp-content/plugins/`
2. Activa el plugin desde el menú 'Plugins' en WordPress
3. Configura las opciones desde el menú del plugin

== Frequently Asked Questions ==

= ¿Puedo usar ambos modos al mismo tiempo? =
Sí, técnicamente el código permite que ambas opciones estén marcadas simultáneamente en la base de datos. Sin embargo, el plugin siempre priorizará el Modo Bloqueo Total sobre el Modo Catálogo al mostrar las páginas. Si activas el modo Catálogo mientras el Bloqueo Total está activo, ambos permanecerán activos en la configuración, pero los visitantes verán la página de bloqueo total. Esta exclusión visual asegura una experiencia coherente para tus visitantes.

= ¿El plugin afecta al SEO? =
El modo bloqueo total puede afectar el SEO si se mantiene por mucho tiempo. Se recomienda usarlo solo durante períodos cortos de mantenimiento.

= ¿Cómo se limpian los datos temporales? =
El plugin incluye un proceso automático que limpia los transients acumulados cada hora. No es necesaria ninguna configuración adicional.

== Changelog ==

= 2.7.3 - 2026-09-08 =
* CORRECCIÓN: Documentación actualizada sobre el uso simultáneo de modos (readme.txt)
* MEJORA: JavaScript extraído a archivo externo (assets/js/ygb-countdown.js)
* MEJORA: jQuery encolado explícitamente con wp_enqueue_script()
* MEJORA: Implementación de WP Cron para limpieza automática de transients
* MEJORA: Soporte mejorado para instalaciones multisite
* MEJORA: Manejo consistente de excepciones en todo el plugin
* MEJORA: Variables CSS modernas para personalización de estilos
* FIX: Nonces AJAX con refresh automático para evitar expiración
* FIX: Strings internacionalizables reemplazan emojis hardcoded

= 2.7.2 =
* Versión anterior del plugin

== Upgrade Notice ==

= 2.7.3 =
Actualización recomendada para todos los usuarios. Incluye mejoras críticas de rendimiento, compatibilidad y documentación corregida.

= 2.7.2 =
Actualización recomendada para todos los usuarios.
