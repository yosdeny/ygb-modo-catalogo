/**
 * YGB Modo Catálogo / Bloqueo Total - Countdown Script
 * 
 * @package YGB_Modo_Catalogo
 * @version 2.7.3
 */

(function($) {
    'use strict';

    /**
     * Inicializa el countdown para una plantilla específica
     * 
     * @param {string} elementId - ID del elemento contenedor del countdown
     * @param {number} fechaTimestamp - Timestamp de la fecha objetivo en milisegundos
     * @param {string} mensajeFinal - Mensaje a mostrar cuando el countdown termine
     */
    function initCountdown(elementId, fechaTimestamp, mensajeFinal) {
        var countdownElement = document.getElementById(elementId);
        
        if (!countdownElement) {
            return;
        }

        /**
         * Actualiza los valores del countdown
         */
        function actualizarCountdown() {
            var ahora = new Date().getTime();
            var diff = fechaTimestamp - ahora;

            // Protección contra fechas pasadas o negativas
            if (diff <= 0) {
                if (countdownElement) {
                    countdownElement.innerHTML = '<div class="ygb-countdown" style="padding:30px;">' +
                        '<div class="ygb-countdown-titulo">' + mensajeFinal + '</div>' +
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

        // Iniciar countdown
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', function() {
                actualizarCountdown();
                setInterval(actualizarCountdown, 1000);
            });
        } else {
            actualizarCountdown();
            setInterval(actualizarCountdown, 1000);
        }
    }

    // Exponer función globalmente para uso desde las plantillas
    window.YGB_Countdown = {
        init: initCountdown
    };

})(jQuery);
