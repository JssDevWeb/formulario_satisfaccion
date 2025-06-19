/**
 * ============================================
 * SISTEMA DE ENCUESTAS ACADÉMICAS - JAVASCRIPT SECUENCIAL
 * ============================================
 * Archivo: survey_new.js
 * Descripción: Maneja el flujo secuencial de evaluación de curso y profesores
 * Flujo: Selección Curso → Evaluación Curso → Profesor 1 → Profesor 2 → ... → Envío
 * ============================================
 */

// Debug mode - cambiar a false en producción
const DEBUG_MODE = true;

// Función de debug
function debugLog(message, data = null) {
    if (DEBUG_MODE) {
        console.log(`[SURVEY SEQUENTIAL] ${message}`, data || '');
    }
}

// Configuración global
const CONFIG = {
    API_BASE_URL: './api/',
    ENDPOINTS: {
        formularios: 'get_formularios.php',
        profesores: 'get_profesores.php',
        preguntas: 'get_preguntas.php',
        procesar: 'procesar_encuesta.php' // Volvemos al original para ver el error específico
    }
};

// Estado global de la aplicación
const AppState = {
    currentStep: 1, // 1: selección, 2: curso, 3: profesores
    currentFormulario: null,
    profesores: [],
    currentProfessorIndex: 0,
    courseQuestions: [],
    professorQuestions: [],
    startTime: null, // Timestamp de cuando se inicia la encuesta
    responses: {
        formulario_id: null,
        course_answers: {},
        professor_answers: {} // {profesorId: {preguntaId: respuesta}}
    }
};

// Utilidades
const Utils = {
    async fetchAPI(endpoint, options = {}) {
        const url = CONFIG.API_BASE_URL + endpoint;
        const defaultOptions = {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            }
        };

        debugLog(`Haciendo petición a: ${url}`, options);        try {
            const response = await fetch(url, { ...defaultOptions, ...options });
            
            if (!response.ok) {
                // Intentar obtener el mensaje de error del servidor
                let errorMessage = `HTTP Error: ${response.status} ${response.statusText}`;
                try {
                    const errorData = await response.json();
                    if (errorData.error) {
                        errorMessage += ` - ${errorData.error}`;
                    }
                    if (errorData.debug_message) {
                        errorMessage += ` (Debug: ${errorData.debug_message})`;
                    }
                } catch (e) {
                    // Si no se puede leer como JSON, usar el error HTTP básico
                }
                throw new Error(errorMessage);
            }

            const data = await response.json();
            debugLog('Datos recibidos:', data);
            
            if (!data.success) {
                throw new Error(data.message || 'Error en la respuesta del servidor');
            }

            return data;
        } catch (error) {
            debugLog('Error en petición:', error);
            throw error;
        }
    },

    showAlert(message, type = 'info', duration = 5000) {
        const alertContainer = document.getElementById('alertContainer');
        const alertId = 'alert_' + Date.now();
        
        const alertHTML = `
            <div class="alert alert-${type} alert-dismissible fade show" role="alert" id="${alertId}">
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        `;
        
        alertContainer.insertAdjacentHTML('beforeend', alertHTML);
        
        if (duration > 0) {
            setTimeout(() => {
                const alertElement = document.getElementById(alertId);
                if (alertElement) {
                    const alert = new bootstrap.Alert(alertElement);
                    alert.close();
                }
            }, duration);
        }
    },    showStep(stepNumber) {
        // Obtener el paso actual visible antes de ocultar
        const currentVisibleStep = document.querySelector('.survey-step[style*="block"]');
        
        // Función para mostrar el nuevo paso con animación
        const showNewStep = () => {
            const stepElement = document.getElementById(`step${stepNumber}-${this.getStepName(stepNumber)}`);
            if (stepElement) {
                stepElement.style.display = 'block';
                stepElement.style.opacity = '0';
                stepElement.style.transform = 'translateY(20px)';
                
                // Forzar reflow para asegurar que se apliquen los estilos
                stepElement.offsetHeight;
                
                // Aplicar transición suave
                stepElement.style.transition = 'opacity 0.4s ease-out, transform 0.4s ease-out';
                stepElement.style.opacity = '1';
                stepElement.style.transform = 'translateY(0)';
                
                // Limpiar estilos de transición después de la animación
                setTimeout(() => {
                    stepElement.style.transition = '';
                    stepElement.style.transform = '';
                }, 400);
            }
        };

        if (currentVisibleStep) {
            // Animar salida del paso actual
            currentVisibleStep.style.transition = 'opacity 0.3s ease-in, transform 0.3s ease-in';
            currentVisibleStep.style.opacity = '0';
            currentVisibleStep.style.transform = 'translateY(-20px)';
            
            // Después de la animación de salida, ocultar y mostrar el nuevo
            setTimeout(() => {
                // Ocultar todos los pasos
                document.querySelectorAll('.survey-step').forEach(step => {
                    step.style.display = 'none';
                    step.style.transition = '';
                    step.style.opacity = '';
                    step.style.transform = '';
                });
                
                // Mostrar el nuevo paso con animación
                showNewStep();
            }, 300);
        } else {
            // Si no hay paso visible, mostrar directamente
            document.querySelectorAll('.survey-step').forEach(step => {
                step.style.display = 'none';
            });
            showNewStep();
        }

        // Mostrar/ocultar elementos según el paso
        const progressBar = document.getElementById('progressBar');
        const resetSection = document.getElementById('resetSection');
        
        if (stepNumber === 1) {
            progressBar.style.display = 'none';
            resetSection.style.display = 'none';
        } else {
            progressBar.style.display = 'block';
            resetSection.style.display = 'block';
        }

        AppState.currentStep = stepNumber;
        this.updateProgress();
    },

    getStepName(stepNumber) {
        const stepNames = {
            1: 'course-selection',
            2: 'course-evaluation', 
            3: 'professor-evaluation'
        };
        return stepNames[stepNumber] || '';
    },

    updateProgress() {
        const { currentStep, profesores, currentProfessorIndex } = AppState;
        
        let totalSteps = 2; // Selección + Curso
        if (profesores.length > 0) {
            totalSteps += profesores.length;
        }

        let currentStepNumber = currentStep;
        if (currentStep === 3) {
            currentStepNumber = 2 + currentProfessorIndex + 1;
        }

        const progressPercent = (currentStepNumber / totalSteps) * 100;
        
        // Actualizar elementos del progreso
        document.getElementById('progressText').textContent = `Paso ${currentStepNumber} de ${totalSteps}`;
        document.getElementById('progressBarFill').style.width = `${progressPercent}%`;
        
        // Actualizar texto del paso actual
        let stepText = '';
        switch(currentStep) {
            case 1:
                stepText = 'Seleccionando curso';
                break;
            case 2:
                stepText = 'Evaluando el curso';
                break;
            case 3:
                const profesor = profesores[currentProfessorIndex];
                stepText = `Evaluando profesor: ${profesor ? profesor.nombre : ''}`;
                break;
        }
        document.getElementById('currentStepText').textContent = stepText;
    }
};

// Manejo de formularios
const FormularioManager = {
    async loadFormularios() {
        try {
            const data = await Utils.fetchAPI(CONFIG.ENDPOINTS.formularios);
            const select = document.getElementById('formulario_id');
            
            // Limpiar opciones existentes (excepto la primera)
            select.innerHTML = '<option value="">Seleccione un curso...</option>';
              if (data.data && data.data.length > 0) {
                data.data.forEach(formulario => {
                    const option = document.createElement('option');
                    option.value = formulario.id;
                    // Usar curso_codigo si existe, si no usar descripcion del formulario
                    const codigoCurso = formulario.curso_codigo || 'SIN_CODIGO';
                    option.textContent = `${codigoCurso} - ${formulario.curso_nombre}`;
                    option.dataset.formulario = JSON.stringify(formulario);
                    select.appendChild(option);
                });
                debugLog('Formularios cargados:', data.data.length);
            } else {
                Utils.showAlert('No hay formularios disponibles', 'warning');
            }
        } catch (error) {
            console.error('Error cargando formularios:', error);
            Utils.showAlert('Error al cargar los cursos disponibles', 'danger');
        }
    },

    async onFormularioChange() {
        const select = document.getElementById('formulario_id');
        const selectedOption = select.selectedOptions[0];
        
        if (!selectedOption || !selectedOption.value) {
            this.clearFormularioInfo();
            return;
        }

        try {
            const formulario = JSON.parse(selectedOption.dataset.formulario);
            AppState.currentFormulario = formulario;
            AppState.responses.formulario_id = formulario.id;
            
            // Mostrar información del formulario
            this.showFormularioInfo(formulario);
            
            // Cargar profesores del formulario
            await this.loadProfesores(formulario.id);
            
        } catch (error) {
            console.error('Error procesando selección de formulario:', error);
            Utils.showAlert('Error al procesar la selección del curso', 'danger');
        }
    },    showFormularioInfo(formulario) {
        const infoDiv = document.getElementById('formularioInfo');
        
        // Formatear fechas a formato español
        const formatearFecha = (fecha) => {
            if (!fecha) return 'No especificado';
            
            try {
                const fechaObj = new Date(fecha + 'T00:00:00');
                if (isNaN(fechaObj.getTime())) return 'Fecha inválida';
                
                return fechaObj.toLocaleDateString('es-ES', {
                    year: 'numeric',
                    month: 'long',
                    day: 'numeric'
                });
            } catch (error) {
                return 'Fecha inválida';
            }
        };
        
        const fechaInicio = formatearFecha(formulario.fecha_inicio);
        const fechaFin = formatearFecha(formulario.fecha_fin);
        
        infoDiv.innerHTML = `
            <small class="text-muted">
                <strong>Curso:</strong> ${formulario.curso_nombre}<br>
                <strong>Período:</strong> ${fechaInicio} - ${fechaFin}
            </small>
        `;
    },

    clearFormularioInfo() {
        document.getElementById('formularioInfo').innerHTML = '';
        document.getElementById('profesoresInfo').style.display = 'none';
        document.getElementById('startSurveyBtn').disabled = true;
        AppState.currentFormulario = null;
        AppState.profesores = [];
    },

    async loadProfesores(formularioId) {
        try {
            const data = await Utils.fetchAPI(`${CONFIG.ENDPOINTS.profesores}?formulario_id=${formularioId}`);
            
            if (data.data && data.data.length > 0) {
                AppState.profesores = data.data;
                this.showProfesoresInfo(data.data);
                document.getElementById('startSurveyBtn').disabled = false;
            } else {
                Utils.showAlert('Este curso no tiene profesores asignados', 'warning');
                AppState.profesores = [];
                document.getElementById('startSurveyBtn').disabled = true;
            }
        } catch (error) {
            console.error('Error cargando profesores:', error);
            Utils.showAlert('Error al cargar los profesores del curso', 'danger');
        }
    },

    showProfesoresInfo(profesores) {
        const infoDiv = document.getElementById('profesoresInfo');
        const listDiv = document.getElementById('profesoresList');
        
        let listHTML = '<ul class="mb-0">';
        profesores.forEach((profesor, index) => {
            listHTML += `<li><strong>${profesor.nombre}</strong> - ${profesor.especialidad || 'Sin especialidad'}</li>`;
        });
        listHTML += '</ul>';
        
        listDiv.innerHTML = listHTML;
        infoDiv.style.display = 'block';
    }
};

// Manejo de preguntas
const QuestionManager = {    async loadCourseQuestions() {
        try {
            const data = await Utils.fetchAPI(`${CONFIG.ENDPOINTS.preguntas}?seccion=curso`);
            
            if (data.data && data.data.length > 0) {
                AppState.courseQuestions = data.data;
                this.renderQuestions(data.data, 'courseQuestions', 'course');
                
                // Actualizar título del curso
                document.getElementById('courseTitle').textContent = AppState.currentFormulario.curso_nombre;
            } else {
                Utils.showAlert('No hay preguntas disponibles para este curso', 'warning');
            }
        } catch (error) {
            console.error('Error cargando preguntas del curso:', error);
            Utils.showAlert('Error al cargar las preguntas del curso', 'danger');
        }
    },    async loadProfessorQuestions() {
        try {
            const data = await Utils.fetchAPI(`${CONFIG.ENDPOINTS.preguntas}?seccion=profesor`);
            
            if (data.data && data.data.length > 0) {
                AppState.professorQuestions = data.data;
                this.renderProfessorQuestions();
            } else {
                Utils.showAlert('No hay preguntas disponibles para evaluar profesores', 'warning');
            }
        } catch (error) {
            console.error('Error cargando preguntas del profesor:', error);
            Utils.showAlert('Error al cargar las preguntas del profesor', 'danger');
        }
    },    renderQuestions(questions, containerId, prefix) {
        const container = document.getElementById(containerId);
        
        // Crear la tabla de evaluación
        let tableHTML = `                <table class="evaluation-table">
                <thead>
                    <tr>
                        <th>CONCEPTO A VALORAR</th>
                        <th class="emoji-header">Excelente</th>
                        <th class="emoji-header">Bueno</th>
                        <th class="emoji-header">Correcto</th>
                        <th class="emoji-header">Regular</th>
                        <th class="emoji-header">Deficiente</th>
                    </tr>
                </thead>
                <tbody>
        `;
        
        questions.forEach((question, index) => {
            const questionType = question.tipo || question.tipo_pregunta || 'text';
            
            if (questionType === 'escala' || questionType === 'scale') {
                // Para preguntas de escala, usar el nuevo diseño de tabla
                const inputName = `${prefix}_${question.id}`;
                tableHTML += this.createScaleHTML(question, inputName);
            } else {
                // Para otros tipos de pregunta, usar el diseño tradicional
                tableHTML += `
                    <tr>
                        <td colspan="6" class="other-question-type">
                            ${this.createNonScaleQuestionHTML(question, prefix, index)}
                        </td>
                    </tr>
                `;
            }
        });
        
        tableHTML += `
                </tbody>
            </table>
        `;
        
        container.innerHTML = tableHTML;
        
        // Agregar event listeners para las celdas de emoji
        this.addEmojiCellListeners(container);
    },

    renderProfessorQuestions() {
        const profesor = AppState.profesores[AppState.currentProfessorIndex];
        if (!profesor) return;

        // Actualizar título del profesor
        document.getElementById('professorTitle').textContent = profesor.nombre;
        document.getElementById('professorCounter').textContent = 
            `Profesor ${AppState.currentProfessorIndex + 1} de ${AppState.profesores.length}`;

        // Renderizar preguntas
        this.renderQuestions(
            AppState.professorQuestions, 
            'professorQuestions', 
            `professor_${profesor.id}`
        );

        // Actualizar botones de navegación
        this.updateProfessorNavigation();
    },

    updateProfessorNavigation() {
        const prevBtn = document.getElementById('prevProfessorBtn');
        const nextBtn = document.getElementById('nextProfessorBtn');
        const submitBtn = document.getElementById('submitBtn');

        const isFirst = AppState.currentProfessorIndex === 0;
        const isLast = AppState.currentProfessorIndex === AppState.profesores.length - 1;

        prevBtn.style.display = isFirst ? 'none' : 'inline-block';
        nextBtn.style.display = isLast ? 'none' : 'inline-block';
        submitBtn.style.display = isLast ? 'inline-block' : 'none';
    },    createQuestionElement(question, prefix, index) {
        const questionDiv = document.createElement('div');
        questionDiv.className = 'mb-4 p-3 border rounded';
        questionDiv.dataset.questionId = question.id;

        // Usar el campo correcto para el texto de la pregunta
        const questionText = question.texto || question.pregunta || question.texto_pregunta || 'Pregunta sin texto';
        const questionType = question.tipo || question.tipo_pregunta || 'text';

        let questionHTML = `
            <h6 class="mb-3">${questionText}</h6>
        `;

        const inputName = `${prefix}_${question.id}`;

        switch (questionType) {
            case 'opcion_multiple':
            case 'multiple_choice':
                questionHTML += this.createMultipleChoiceHTML(question, inputName);
                break;
            case 'escala':
            case 'scale':
                questionHTML += this.createScaleHTML(question, inputName);
                break;
            case 'texto':
            case 'open':
            case 'abierta':
                questionHTML += this.createOpenHTML(question, inputName);
                break;
            default:
                questionHTML += `<p class="text-danger">Tipo de pregunta no soportado: ${questionType}</p>`;
        }

        questionDiv.innerHTML = questionHTML;
        return questionDiv;
    },    createMultipleChoiceHTML(question, inputName) {
        let html = '<div class="form-group">';
        
        // Buscar opciones en diferentes campos posibles
        let opciones = question.opciones_array || question.opciones || [];
        
        // Si las opciones están en formato JSON string, parsearlas
        if (typeof opciones === 'string') {
            try {
                opciones = JSON.parse(opciones);
            } catch (e) {
                opciones = [];
            }
        }
        
        if (opciones && opciones.length > 0) {
            opciones.forEach((opcion, index) => {
                html += `
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="radio" 
                               name="${inputName}" value="${opcion}" 
                               id="${inputName}_${index}" ${question.es_obligatoria ? 'required' : ''}>
                        <label class="form-check-label" for="${inputName}_${index}">
                            ${opcion}
                        </label>
                    </div>
                `;
            });
        } else {
            html += '<p class="text-muted">Sin opciones disponibles</p>';
        }
        
        html += '</div>';
        return html;
    },    createScaleHTML(question, inputName) {
        console.log('🔍 createScaleHTML llamado para:', question);
        
        // Definir la escala de evaluación con emojis (siempre la misma)
        const scaleOptions = [
            { value: 10, text: 'Excelente', emoji: '😃' },
            { value: 7, text: 'Bueno', emoji: '🙂' },
            { value: 5, text: 'Correcto', emoji: '😐' },
            { value: 3, text: 'Regular', emoji: '😕' },
            { value: 1, text: 'Deficiente', emoji: '😞' }
        ];
        
        // Crear la fila de la tabla para esta pregunta
        const questionText = question.texto || question.pregunta || question.texto_pregunta || 'Pregunta sin texto';
        
        let html = `
            <tr data-question-id="${question.id}">
                <td class="question-text">${questionText}</td>
        `;
        
        // Crear celdas con emojis para cada opción
        scaleOptions.forEach(option => {
            html += `
                <td class="emoji-cell" data-label="${option.text}">
                    <input type="radio" name="${inputName}" value="${option.value}" 
                           id="${inputName}_${option.value}" ${question.es_obligatoria ? 'required' : ''}>
                </td>
            `;
        });
        
        html += '</tr>';
          console.log('✅ HTML generado para escala:', html);
        return html;
    },
    
    createOpenHTML(question, inputName) {
        return `
            <div class="form-group">
                <textarea class="form-control" name="${inputName}" 
                          rows="4" maxlength="500" 
                          placeholder="Escriba su respuesta aquí..."
                          ${question.es_obligatoria ? 'required' : ''}></textarea>                <div class="form-text">
                    <span class="char-counter">0/500 caracteres</span>
                </div>
            </div>
        `;
    },

    // Función para crear HTML de preguntas que no son de escala
    createNonScaleQuestionHTML(question, prefix, index) {
        const questionText = question.texto || question.pregunta || question.texto_pregunta || 'Pregunta sin texto';
        const questionType = question.tipo || question.tipo_pregunta || 'text';
        const inputName = `${prefix}_${question.id}`;
        
        let html = `<div class="non-scale-question"><h6>${questionText}</h6>`;
        
        switch (questionType) {
            case 'opcion_multiple':
            case 'multiple_choice':
                html += this.createMultipleChoiceHTML(question, inputName);
                break;
            case 'texto':
            case 'open':
            case 'abierta':
                html += this.createOpenHTML(question, inputName);
                break;
            default:
                html += `<p class="text-danger">Tipo de pregunta no soportado: ${questionType}</p>`;
        }
        
        html += '</div>';
        return html;
    },
      // Función para agregar event listeners a las celdas de emoji
    addEmojiCellListeners(container) {
        const emojiCells = container.querySelectorAll('.emoji-cell');
        
        // Mapa de emojis basado en valores
        const emojiMap = { 
            '10': '😃', '7': '🙂', '5': '😐', '3': '😕', '1': '😞' 
        };
        
        emojiCells.forEach(cell => {
            const radioInput = cell.querySelector('input[type="radio"]');
            
            if (radioInput) {
                // Limpiar emojis existentes para evitar duplicados
                const existingEmoji = cell.querySelector('.emoji-only');
                if (existingEmoji) {
                    existingEmoji.remove();
                }
                
                // Añadir emoji dinámico basado en el valor del radio button
                const emojiValue = radioInput.value;
                if (emojiMap[emojiValue]) {
                    const emojiSpan = document.createElement('span');
                    emojiSpan.className = 'emoji-only';
                    emojiSpan.textContent = emojiMap[emojiValue];
                    cell.appendChild(emojiSpan);
                }
                
                // Listener para la celda completa
                cell.addEventListener('click', () => {
                    // Limpiar selección previa en la misma fila
                    const row = cell.closest('tr');
                    row.querySelectorAll('.emoji-cell').forEach(c => c.classList.remove('selected'));
                    
                    // Marcar la celda actual como seleccionada
                    cell.classList.add('selected');
                    radioInput.checked = true;
                    
                    // Disparar evento de cambio
                    radioInput.dispatchEvent(new Event('change'));
                });
                
                // Listener para cambios en el radio button
                radioInput.addEventListener('change', () => {
                    if (radioInput.checked) {
                        const row = cell.closest('tr');
                        row.querySelectorAll('.emoji-cell').forEach(c => c.classList.remove('selected'));
                        cell.classList.add('selected');
                        
                        // Marcar la fila como respondida
                        row.classList.add('answered');
                    }
                });
                
                // Si ya está seleccionado, marcar como selected
                if (radioInput.checked) {
                    cell.classList.add('selected');
                }
            }
        });
    }
};

// Manejo de navegación
const NavigationManager = {    startSurvey() {
        debugLog('Iniciando encuesta');
        
        // Registrar tiempo de inicio
        AppState.startTime = Date.now();
        
        // Deshabilitar scroll suave temporalmente
        document.documentElement.style.scrollBehavior = 'auto';
        
        // Guardar posición actual del scroll
        const currentScrollY = window.scrollY;
        
        // Ocultar elementos de introducción
        const introText = document.getElementById('intro-text');
        const formContainer = document.getElementById('form-container');
        const startFormBtn = document.getElementById('start-form-btn');
          if (introText) {
            introText.classList.add('fadeOut');
            setTimeout(() => {
                introText.style.display = 'none';
            }, 800); // Tiempo más largo para transición suave
        }
        
        if (startFormBtn) {
            startFormBtn.style.transition = 'opacity 0.6s ease-in-out';
            startFormBtn.style.opacity = '0';
            setTimeout(() => {
                startFormBtn.style.display = 'none';
            }, 600); // Tiempo más largo
        }
        
        if (formContainer) {
            // Mostrar inmediatamente pero transparente
            formContainer.style.display = 'block';
            formContainer.style.opacity = '0';
            
            setTimeout(() => {
                formContainer.classList.add('visible');
                
                // Mantener la posición del scroll
                window.scrollTo(0, currentScrollY);
                
                // Restaurar scroll suave después de la transición
                setTimeout(() => {
                    document.documentElement.style.scrollBehavior = 'smooth';
                }, 800); // Tiempo más largo para completar transición
            }, 600); // Tiempo más largo para iniciar transición
        }
        
        Utils.showStep(2);
        QuestionManager.loadCourseQuestions();
    },    async nextToProfessors() {
        // Validar respuestas del curso
        if (!this.validateCurrentStep()) {
            Utils.showAlert('Por favor complete todas las preguntas requeridas', 'warning');
            return;
        }

        // Guardar respuestas del curso
        this.saveCourseAnswers();
        
        // Ir directamente a evaluación de profesores
        this.proceedToProfessors();
    },
      proceedToProfessors() {
        // Mostrar mensaje de carga temporal
        Utils.showAlert('Cargando evaluación de profesores...', 'info', 1500);
        
        // Pequeño retraso para mejor experiencia de usuario
        setTimeout(() => {
            // Pasar a evaluación de profesores
            AppState.currentProfessorIndex = 0;
            Utils.showStep(3);
            this.loadAndShowFirstProfessor();
        }, 100); // Pequeño retraso para suavizar la transición
    },
    
    async loadAndShowFirstProfessor() {
        await QuestionManager.loadProfessorQuestions();
        QuestionManager.renderProfessorQuestions();
    },    nextProfessor() {
        // Validar respuestas del profesor actual
        if (!this.validateCurrentStep()) {
            Utils.showAlert('Por favor complete todas las preguntas requeridas', 'warning');
            return;
        }

        // Guardar respuestas del profesor actual
        this.saveProfessorAnswers();

        // Ir directamente al siguiente profesor
        this.proceedToNextProfessor();
    },
      proceedToNextProfessor() {
        // Mostrar mensaje informativo
        const nextProfessor = AppState.profesores[AppState.currentProfessorIndex + 1];
        if (nextProfessor) {
            Utils.showAlert(`Cargando evaluación de: ${nextProfessor.nombre}`, 'info', 1500);
        }
        
        // Pequeño retraso para suavizar la transición
        setTimeout(() => {
            // Avanzar al siguiente profesor
            AppState.currentProfessorIndex++;
            QuestionManager.renderProfessorQuestions();
        }, 100);
    },

    prevProfessor() {
        if (AppState.currentProfessorIndex > 0) {
            // Guardar respuestas del profesor actual (opcional)
            this.saveProfessorAnswers();
            
            AppState.currentProfessorIndex--;
            QuestionManager.renderProfessorQuestions();
        }
    },

    validateCurrentStep() {
        const currentStepElement = document.querySelector('.survey-step[style*="block"]');
        if (!currentStepElement) return true;

        const requiredInputs = currentStepElement.querySelectorAll('[required]');
        let isValid = true;        requiredInputs.forEach(input => {
            if (input.type === 'radio') {
                const radioGroup = currentStepElement.querySelectorAll(`[name="${input.name}"]`);
                const hasChecked = Array.from(radioGroup).some(radio => radio.checked);
                
                // Buscar el contenedor padre apropiado (nuevo: tr, legacy: .mb-4/.form-group)
                let container = input.closest('tr'); // Estructura de tabla nueva
                if (!container) {
                    container = input.closest('.mb-4, .form-group, .form-section'); // Estructura legacy
                }
                
                if (!hasChecked) {
                    isValid = false;
                    if (container) {
                        // Usar clase apropiada según el tipo de contenedor
                        if (container.tagName === 'TR') {
                            container.classList.add('missing-answer');
                        } else {
                            container.classList.add('border-danger');
                        }
                    }
                } else {
                    if (container) {
                        // Remover clase de error apropiada
                        if (container.tagName === 'TR') {
                            container.classList.remove('missing-answer');
                        } else {
                            container.classList.remove('border-danger');
                        }
                    }
                }
            } else if (input.tagName === 'TEXTAREA' || input.type === 'text') {
                if (!input.value.trim()) {
                    isValid = false;
                    input.classList.add('is-invalid');
                } else {
                    input.classList.remove('is-invalid');
                }
            }
        });

        return isValid;
    },

    saveCourseAnswers() {
        const courseContainer = document.getElementById('courseQuestions');
        const inputs = courseContainer.querySelectorAll('input, textarea, select');
        
        inputs.forEach(input => {
            if ((input.type === 'radio' || input.type === 'checkbox') && !input.checked) return;
            if (input.value) {
                const questionId = this.extractQuestionId(input.name);
                AppState.responses.course_answers[questionId] = input.value;
            }
        });

        debugLog('Respuestas del curso guardadas:', AppState.responses.course_answers);
    },

    saveProfessorAnswers() {
        const profesor = AppState.profesores[AppState.currentProfessorIndex];
        if (!profesor) return;

        const professorContainer = document.getElementById('professorQuestions');
        const inputs = professorContainer.querySelectorAll('input, textarea, select');
        
        if (!AppState.responses.professor_answers[profesor.id]) {
            AppState.responses.professor_answers[profesor.id] = {};
        }
        
        inputs.forEach(input => {
            if ((input.type === 'radio' || input.type === 'checkbox') && !input.checked) return;
            if (input.value) {
                const questionId = this.extractQuestionId(input.name);
                AppState.responses.professor_answers[profesor.id][questionId] = input.value;
            }
        });

        debugLog(`Respuestas del profesor ${profesor.nombre} guardadas:`, 
                 AppState.responses.professor_answers[profesor.id]);
    },

    extractQuestionId(inputName) {
        // Extraer ID de pregunta del nombre del input (ej: "course_123" -> "123")
        const parts = inputName.split('_');
        return parts[parts.length - 1];
    },    resetSurvey() {
        if (confirm('¿Está seguro de que desea reiniciar la encuesta? Se perderán todas las respuestas.')) {
            // Limpiar estado
            AppState.currentStep = 1;
            AppState.currentFormulario = null;
            AppState.profesores = [];
            AppState.currentProfessorIndex = 0;
            AppState.startTime = null; // Resetear tiempo de inicio
            AppState.responses = {
                formulario_id: null,
                course_answers: {},
                professor_answers: {}
            };

            // Limpiar formulario
            document.getElementById('surveyForm').reset();
            document.getElementById('formulario_id').value = '';
            FormularioManager.clearFormularioInfo();

            // Volver al paso 1
            Utils.showStep(1);
            
            Utils.showAlert('Encuesta reiniciada', 'info');
        }
    }
};

// Manejo del envío
const SubmissionManager = {
    async submitSurvey() {
        try {
            // Validar último profesor
            if (!NavigationManager.validateCurrentStep()) {
                Utils.showAlert('Por favor complete todas las preguntas requeridas', 'warning');
                return;
            }

            // Guardar respuestas del último profesor
            NavigationManager.saveProfessorAnswers();

            // Mostrar overlay de carga
            document.getElementById('loadingOverlay').style.display = 'flex';

            // Preparar datos para envío
            const submissionData = this.prepareSubmissionData();
            
            debugLog('Datos a enviar:', submissionData);

            // Enviar encuesta
            const response = await Utils.fetchAPI(CONFIG.ENDPOINTS.procesar, {
                method: 'POST',
                body: JSON.stringify(submissionData)
            });

            // Procesar respuesta exitosa
            this.handleSuccessfulSubmission(response);

        } catch (error) {
            console.error('Error enviando encuesta:', error);
            Utils.showAlert('Error al enviar la encuesta. Por favor intente nuevamente.', 'danger');
        } finally {
            document.getElementById('loadingOverlay').style.display = 'none';
        }
    },    prepareSubmissionData() {
        const data = {
            formulario_id: AppState.responses.formulario_id,
            tiempo_completado: this.calculateCompletionTime(),
            respuestas_curso: {},
            respuestas_profesores: {}
        };

        // Agregar respuestas del curso
        Object.entries(AppState.responses.course_answers).forEach(([questionId, answer]) => {
            data.respuestas_curso[questionId] = {
                valor: answer
            };
        });

        // Agregar respuestas de profesores
        Object.entries(AppState.responses.professor_answers).forEach(([profesorId, answers]) => {
            data.respuestas_profesores[profesorId] = {};
            Object.entries(answers).forEach(([questionId, answer]) => {
                data.respuestas_profesores[profesorId][questionId] = {
                    valor: answer
                };
            });
        });

        return data;
    },

    calculateCompletionTime() {
        // Si no tenemos tiempo de inicio, usar un valor por defecto
        if (!AppState.startTime) {
            return 120; // 2 minutos por defecto
        }
        
        const completionTime = Math.floor((Date.now() - AppState.startTime) / 1000);
        return Math.max(30, Math.min(3600, completionTime)); // Entre 30 segundos y 1 hora
    },

    handleSuccessfulSubmission(response) {
        // Redirigir a página de agradecimiento
        window.location.href = 'gracias.html';
    }
};

// Event Listeners
function initializeEventListeners() {
    // Selección de formulario
    document.getElementById('formulario_id').addEventListener('change', 
        FormularioManager.onFormularioChange.bind(FormularioManager));

    // Botón iniciar encuesta
    document.getElementById('startSurveyBtn').addEventListener('click', 
        NavigationManager.startSurvey.bind(NavigationManager));

    // Botón continuar con profesores
    document.getElementById('nextToProfessorsBtn').addEventListener('click', 
        NavigationManager.nextToProfessors.bind(NavigationManager));

    // Navegación entre profesores
    document.getElementById('nextProfessorBtn').addEventListener('click', 
        NavigationManager.nextProfessor.bind(NavigationManager));
    
    document.getElementById('prevProfessorBtn').addEventListener('click', 
        NavigationManager.prevProfessor.bind(NavigationManager));

    // Envío de encuesta
    document.getElementById('submitBtn').addEventListener('click', (e) => {
        e.preventDefault();
        SubmissionManager.submitSurvey();
    });

    // Resetear encuesta
    document.getElementById('resetForm').addEventListener('click', 
        NavigationManager.resetSurvey.bind(NavigationManager));    // Manejo de inputs de rating (escalas tradicionales y mejoradas)
    document.addEventListener('change', function(e) {
        if (e.target.type === 'radio') {
            const ratingContainer = e.target.closest('.form-group');
            const selectedRating = ratingContainer?.querySelector('.selected-rating');
            
            if (selectedRating) {
                const ratingValue = selectedRating.querySelector('.rating-value');
                const ratingLabel = selectedRating.querySelector('.rating-label');
                
                if (ratingValue) {
                    ratingValue.textContent = e.target.value;
                }
                
                // Para escalas con etiquetas, obtener la etiqueta del label
                if (ratingLabel) {
                    const label = e.target.closest('.form-check')?.querySelector('label');
                    if (label) {
                        const labelText = label.textContent.trim();
                        // Extraer solo la etiqueta (sin el número)
                        const etiqueta = labelText.replace(/\s*\d+\s*$/, '').trim();
                        ratingLabel.textContent = etiqueta;
                    }
                }
                
                selectedRating.style.display = 'block';
            }
        }
    });

    // Contador de caracteres para textarea
    document.addEventListener('input', function(e) {
        if (e.target.tagName === 'TEXTAREA') {
            const counter = e.target.parentNode.querySelector('.char-counter');
            if (counter) {
                const length = e.target.value.length;
                const maxLength = e.target.getAttribute('maxlength') || 500;
                counter.textContent = `${length}/${maxLength} caracteres`;
            }
        }
    });    // Botón de inicio prominente (nuevo)
    const startFormBtn = document.getElementById('start-form-btn');
    if (startFormBtn) {
        startFormBtn.addEventListener('click', function(e) {
            // Prevenir comportamiento por defecto
            e.preventDefault();
            
            // Deshabilitar scroll suave temporalmente
            document.documentElement.style.scrollBehavior = 'auto';
            
            // Guardar posición actual del scroll
            const currentScrollY = window.scrollY;
            
            const introText = document.getElementById('intro-text');
            const formContainer = document.getElementById('form-container');
              // Ocultar intro y mostrar formulario con animación suave
            if (introText) {
                introText.classList.add('fadeOut');
                setTimeout(() => {
                    introText.style.display = 'none';
                }, 800); // Tiempo más largo para transición suave
            }
            
            if (formContainer) {
                // Mostrar inmediatamente pero transparente
                formContainer.style.display = 'block';
                formContainer.style.opacity = '0';
                
                setTimeout(() => {
                    formContainer.classList.add('visible');
                    
                    // Mantener la posición del scroll
                    window.scrollTo(0, currentScrollY);
                    
                    // Focus en el select de curso después de la animación
                    setTimeout(() => {
                        const cursoSelect = document.getElementById('formulario_id');
                        if (cursoSelect) {
                            cursoSelect.focus();
                        }
                        
                        // Restaurar scroll suave después de un momento
                        setTimeout(() => {
                            document.documentElement.style.scrollBehavior = 'smooth';
                        }, 200);
                    }, 200);
                }, 600); // Tiempo más largo para iniciar transición
            }
            
            // Ocultar el botón con animación
            startFormBtn.style.transition = 'opacity 0.6s ease-in-out';
            startFormBtn.style.opacity = '0';
            setTimeout(() => {
                startFormBtn.style.display = 'none';
            }, 600); // Tiempo más largo
        });
    }
}

// Inicialización
document.addEventListener('DOMContentLoaded', function() {
    debugLog('Inicializando sistema de encuestas secuencial');
    
    // Inicializar event listeners
    initializeEventListeners();
    
    // Cargar formularios disponibles
    FormularioManager.loadFormularios();
    
    // Mostrar primer paso
    Utils.showStep(1);
    
    debugLog('Sistema inicializado correctamente');
});
