=== YGB Modo Catálogo / Bloqueo Total ===
Contributors: yosdeny
Tags: catalogo, modo catalogo, mantenimiento, bloqueo, coming soon
Requires at least: 7.0
Tested up to: 7.1
Stable tag: 2.7.2
Requires PHP: 8.0
Tested PHP: 8.2
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

== Installation ==

1. Sube los archivos del plugin a la carpeta `/wp-content/plugins/`
2. Activa el plugin desde el menú 'Plugins' en WordPress
3. Configura las opciones desde el menú del plugin

== Frequently Asked Questions ==

= ¿Puedo usar ambos modos al mismo tiempo? =
No, el plugin está diseñado para que solo un modo esté activo a la vez. Si activas el modo Catálogo mientras el Bloqueo Total está activo, el Bloqueo Total se desactivará automáticamente (y viceversa). Esta exclusión mutua asegura una experiencia coherente para tus visitantes. Sin embargo, técnicamente el código permite que ambas opciones estén marcadas simultáneamente en la base de datos, pero el plugin siempre priorizará el Modo Bloqueo Total sobre el Modo Catálogo al mostrar las páginas.

= ¿El plugin afecta al SEO? =
El modo bloqueo total puede afectar el SEO si se mantiene por mucho tiempo.

== Changelog ==

= 2.7.2 =
* Versión actual del plugin

== Upgrade Notice ==

= 2.7.2 =
Actualización recomendada para todos los usuarios.
