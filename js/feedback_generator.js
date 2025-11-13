(function() {
    'use strict';
    
    console.log('🎉 Plugin cargado');
    
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
    
    function init() {
        var button = document.getElementById('id_generate_ai_feedback');
        
        if (!button) {
            console.error('❌ Botón NO encontrado');
            return;
        }
        
        console.log('✅ Botón encontrado');
        
        button.addEventListener('click', function(e) {
            e.preventDefault();
            
            console.log('👋 ¡BOTÓN PRESIONADO!');
            console.log('AssignID:', this.getAttribute('data-assignid'));
            console.log('UserID:', this.getAttribute('data-userid'));
            
            var status = document.getElementById('ai_feedback_status');
            status.innerHTML = '<span style="color: green;">✅ Botón funciona!</span>';
            
            var textarea = document.getElementById('id_assignfeedbackaiprompt');
            textarea.value = '¡Hola! El botón está funcionando correctamente.';
        });
    }
})();