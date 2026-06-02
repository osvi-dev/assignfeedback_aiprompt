(function() {
    'use strict';
    
    // Delegación de eventos para el botón del estudiante
    document.addEventListener('click', function(e) {
        // Verificar si el click fue en el botón del estudiante
        if (e.target.id !== 'id_generate_student_ai_feedback') {
            return;
        }
        
        e.preventDefault();
        
        var button = e.target;
        var assignid = button.getAttribute('data-assignid');
        var userid = button.getAttribute('data-userid');
        
        // Desactivar botón
        button.disabled = true;
        button.style.opacity = '0.6';
        button.style.cursor = 'not-allowed';
        
        var status = document.getElementById('student_ai_feedback_status');
        var feedbackContainer = document.getElementById('student_ai_feedback_result');
        
        status.innerHTML = '<span style="color: blue;"><i class="fa fa-spinner fa-spin"></i> Generando retroalimentación con IA, por favor espere...</span>';
        feedbackContainer.innerHTML = '<p style="color: #888; font-style: italic;">Procesando...</p>';
        
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
                        status.innerHTML = '<span style="color: green;"><i class="fa fa-check"></i> ¡Retroalimentación generada exitosamente!</span>';
                        feedbackContainer.innerHTML = '<div style="background-color: #f8f9fa; padding: 15px; border-radius: 5px; border-left: 4px solid #28a745; white-space: pre-wrap;">' + response.feedback + '</div>';
                    } else {
                        status.innerHTML = '<span style="color: red;"><i class="fa fa-times"></i> ' + response.error + '</span>';
                        feedbackContainer.innerHTML = '';
                        console.error('Error:', response.error);
                    }
                } catch (e) {
                    status.innerHTML = '<span style="color: red;"><i class="fa fa-times"></i> Error al procesar la respuesta</span>';
                    feedbackContainer.innerHTML = '';
                    console.error('Error al parsear JSON:', e);
                }
            } else {
                status.innerHTML = '<span style="color: red;"><i class="fa fa-times"></i> Error de conexión (HTTP ' + xhr.status + ')</span>';
                feedbackContainer.innerHTML = '';
                console.error('Error HTTP:', xhr.status);
            }
        };
        
        xhr.onerror = function() {
            button.disabled = false;
            button.style.opacity = '1';
            button.style.cursor = 'pointer';
            
            status.innerHTML = '<span style="color: red;"><i class="fa fa-times"></i> Error de red. Verifique su conexión.</span>';
            feedbackContainer.innerHTML = '';
            console.error('Error de red');
        };
        
        xhr.ontimeout = function() {
            button.disabled = false;
            button.style.opacity = '1';
            button.style.cursor = 'pointer';
            
            status.innerHTML = '<span style="color: red;"><i class="fa fa-times"></i> Timeout. La IA tardó demasiado en responder.</span>';
            feedbackContainer.innerHTML = '';
            console.error('Timeout');
        };
        
        // Timeout de 150 segundos
        xhr.timeout = 150000;
        
        // Enviar petición con role=student
        var params = 'assignid=' + encodeURIComponent(assignid) + 
                    '&userid=' + encodeURIComponent(userid) + 
                    '&role=student' +
                    '&sesskey=' + encodeURIComponent(M.cfg.sesskey);
        
        xhr.send(params);
    });
})();
