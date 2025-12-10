(function() {
    'use strict';
    
    // Usar delegación de eventos desde el principio
    document.addEventListener('click', function(e) {
        // Verificar si el click fue en nuestro botón
        if (e.target.id !== 'id_generate_ai_feedback') {
            return; // Ignorar clicks en otros elementos
        }
        
        e.preventDefault();
        
        // Ahora e.target ES el botón clickeado
        var button = e.target;
        var assignid = button.getAttribute('data-assignid');
        var userid = button.getAttribute('data-userid');
        
        // Desactivar botón
        button.disabled = true;
        button.style.opacity = '0.6';
        button.style.cursor = 'not-allowed';
        
        var status = document.getElementById('ai_feedback_status');
        var textarea = document.getElementById('id_assignfeedbackaiprompt');
        
        status.innerHTML = '<span style="color: blue;"><i class="fa fa-spinner fa-spin"></i> Generando feedback con IA, por favor espere...</span>';
        textarea.value = 'Procesando...';
        
        // Construir URL para la petición AJAX
        var ajaxUrl = M.cfg.wwwroot + '/mod/assign/feedback/aiprompt/ajax_generate_feedback.php';
        
        // Hacer petición AJAX
        var xhr = new XMLHttpRequest();
        xhr.open('POST', ajaxUrl, true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        
        xhr.onload = function() {
            // Reactivar botón
            button.disabled = false;
            button.style.opacity = '1';
            button.style.cursor = 'pointer';
            
            if (xhr.status === 200) {
                try {
                    var response = JSON.parse(xhr.responseText);
                    
                    if (response.success) {
                        status.innerHTML = '<span style="color: green;"><i class="fa fa-check"></i> ¡Feedback generado exitosamente!</span>';
                        textarea.value = response.feedback;
                        
                    } else {
                        status.innerHTML = '<span style="color: red;"><i class="fa fa-times"></i> Error: ' + response.error + '</span>';
                        textarea.value = '';
                        console.error('❌ Error:', response.error);
                    }
                } catch (e) {
                    status.innerHTML = '<span style="color: red;"><i class="fa fa-times"></i> Error al procesar la respuesta</span>';
                    textarea.value = '';
                    console.error('❌ Error al parsear JSON:', e);
                }
            } else {
                status.innerHTML = '<span style="color: red;"><i class="fa fa-times"></i> Error de conexión (HTTP ' + xhr.status + ')</span>';
                textarea.value = '';
                console.error('❌ Error HTTP:', xhr.status);
            }
        };
        
        xhr.onerror = function() {
            // Reactivar botón
            button.disabled = false;
            button.style.opacity = '1';
            button.style.cursor = 'pointer';
            
            status.innerHTML = '<span style="color: red;"><i class="fa fa-times"></i> Error de red. Verifique su conexión.</span>';
            textarea.value = '';
            console.error('❌ Error de red');
        };
        
        xhr.ontimeout = function() {
            // Reactivar botón
            button.disabled = false;
            button.style.opacity = '1';
            button.style.cursor = 'pointer';
            
            status.innerHTML = '<span style="color: red;"><i class="fa fa-times"></i> Timeout. La IA tardó demasiado en responder.</span>';
            textarea.value = '';
            console.error('❌ Timeout');
        };
        
        // Timeout de 150 segundos (150000 ms)
        xhr.timeout = 150000;
        
        // Enviar petición
        var params = 'assignid=' + encodeURIComponent(assignid) + 
                    '&userid=' + encodeURIComponent(userid) + 
                    '&sesskey=' + encodeURIComponent(M.cfg.sesskey);
        
        xhr.send(params);
    });
})();