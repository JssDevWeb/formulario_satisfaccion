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
        procesar: 'procesar_encuesta.php'
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

        debugLog(`Haciendo petición a: ${url}`, options);

        try {
            const response = await fetch(url, { ...defaultOptions, ...options });
            
            if (!response.ok) {
                throw new Error(`HTTP Error: ${response.status} ${response.statusText}`);
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
    },

    showStep(stepNumber) {
        // Ocultar todos los pasos
        document.querySelectorAll('.survey-step').forEach(step => {
            step.style.display = 'none';
        });

        // Mostrar el paso actual
        const stepElement = document.getElementById(`step${stepNumber}-${this.getStepName(stepNumber)}`);
        if (stepElement) {
            stepElement.style.display = 'block';
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
                    option.textContent = `${formulario.nombre} - ${formulario.curso_nombre}`;
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
    },

    showFormularioInfo(formulario) {
        const infoDiv = document.getElementById('formularioInfo');
        infoDiv.innerHTML = `
            <small class="text-muted">
                <strong>Curso:</strong> ${formulario.curso_nombre}<br>
                <strong>Período:</strong> ${formulario.fecha_inicio || 'No especificado'} - ${formulario.fecha_fin || 'No especificado'}
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
            
            if (data.curso && data.curso.length > 0) {
                AppState.courseQuestions = data.curso;
                this.renderQuestions(data.curso, 'courseQuestions', 'course');
                
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
            
            if (data.profesor && data.profesor.length > 0) {
                AppState.professorQuestions = data.profesor;
                this.renderProfessorQuestions();
            } else {
                Utils.showAlert('No hay preguntas disponibles para evaluar profesores', 'warning');
            }
        } catch (error) {
            console.error('Error cargando preguntas del profesor:', error);
            Utils.showAlert('Error al cargar las preguntas del profesor', 'danger');
        }
    },

    renderQuestions(questions, containerId, prefix) {
        const container = document.getElementById(containerId);
        container.innerHTML = '';

        questions.forEach((question, index) => {
            const questionDiv = this.createQuestionElement(question, prefix, index);
            container.appendChild(questionDiv);
        });
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

        let questionHTML = `
            <h6 class="mb-3">${question.texto}</h6>
        `;

        const inputName = `${prefix}_${question.id}`;

        switch (question.tipo) {
            case 'opcion_multiple':
                questionHTML += this.createMultipleChoiceHTML(question, inputName);
                break;
            case 'escala':
                questionHTML += this.createScaleHTML(question, inputName);
                break;
            case 'texto':
                questionHTML += this.createOpenHTML(question, inputName);
                break;
            default:
                questionHTML += `<p class="text-danger">Tipo de pregunta no soportado: ${question.tipo}</p>`;
        }

        questionDiv.innerHTML = questionHTML;
        return questionDiv;
    },    createMultipleChoiceHTML(question, inputName) {
        let html = '<div class="form-group">';
        
        if (question.opciones_array && question.opciones_array.length > 0) {
            question.opciones_array.forEach((opcion, index) => {
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
        }
        
        html += '</div>';
        return html;
    },    createScaleHTML(question, inputName) {
        // Para preguntas de escala, los rangos están en respuesta_info
        const maxValue = question.respuesta_info?.rango_max || 5;
        const minValue = question.respuesta_info?.rango_min || 1;
        
        let html = `
            <div class="form-group">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <span class="badge bg-danger">${minValue}</span>
                    <span class="text-muted">Calificación</span>
                    <span class="badge bg-success">${maxValue}</span>
                </div>
                <div class="btn-group-toggle rating-buttons" data-toggle="buttons">
        `;
        
        for (let i = minValue; i <= maxValue; i++) {
            html += `
                <input type="radio" class="btn-check" name="${inputName}" 
                       value="${i}" id="${inputName}_${i}" ${question.es_obligatoria ? 'required' : ''}>
                <label class="btn btn-outline-primary rating-btn" for="${inputName}_${i}">
                    ${i}
                </label>
            `;
        }
        
        html += `
                </div>
                <div class="selected-rating mt-2" style="display: none;">
                    <small class="text-muted">Calificación seleccionada: <span class="rating-value"></span></small>
                </div>
            </div>
        `;
        
        return html;
    },

    createOpenHTML(question, inputName) {        const maxLength = question.respuesta_info?.longitud_max || 1000;
        return `
            <div class="form-group">
                <textarea class="form-control" name="${inputName}" 
                          rows="4" maxlength="${maxLength}" 
                          placeholder="Escriba su respuesta aquí..."
                          ${question.es_obligatoria ? 'required' : ''}></textarea>
                <div class="form-text">
                    <span class="char-counter">0/${maxLength} caracteres</span>
                </div>
            </div>
        `;
    }
};

// Manejo de navegación
const NavigationManager = {
    startSurvey() {
        debugLog('Iniciando encuesta');
        Utils.showStep(2);
        QuestionManager.loadCourseQuestions();
    },

    async nextToProfessors() {
        // Validar respuestas del curso
        if (!this.validateCurrentStep()) {
            Utils.showAlert('Por favor complete todas las preguntas requeridas', 'warning');
            return;
        }

        // Guardar respuestas del curso
        this.saveCourseAnswers();
        
        // Pasar a evaluación de profesores
        AppState.currentProfessorIndex = 0;
        Utils.showStep(3);
        await QuestionManager.loadProfessorQuestions();
        QuestionManager.renderProfessorQuestions();
    },

    nextProfessor() {
        // Validar respuestas del profesor actual
        if (!this.validateCurrentStep()) {
            Utils.showAlert('Por favor complete todas las preguntas requeridas', 'warning');
            return;
        }

        // Guardar respuestas del profesor actual
        this.saveProfessorAnswers();

        // Avanzar al siguiente profesor
        AppState.currentProfessorIndex++;
        QuestionManager.renderProfessorQuestions();
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
        let isValid = true;

        requiredInputs.forEach(input => {
            if (input.type === 'radio') {
                const radioGroup = currentStepElement.querySelectorAll(`[name="${input.name}"]`);
                const hasChecked = Array.from(radioGroup).some(radio => radio.checked);
                if (!hasChecked) {
                    isValid = false;
                    input.closest('.mb-4, .form-group').classList.add('border-danger');
                } else {
                    input.closest('.mb-4, .form-group').classList.remove('border-danger');
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
    },

    resetSurvey() {
        if (confirm('¿Está seguro de que desea reiniciar la encuesta? Se perderán todas las respuestas.')) {
            // Limpiar estado
            AppState.currentStep = 1;
            AppState.currentFormulario = null;
            AppState.profesores = [];
            AppState.currentProfessorIndex = 0;
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
    },

    prepareSubmissionData() {
        const data = {
            formulario_id: AppState.responses.formulario_id,
            respuestas: []
        };

        // Agregar respuestas del curso
        Object.entries(AppState.responses.course_answers).forEach(([questionId, answer]) => {
            data.respuestas.push({
                pregunta_id: parseInt(questionId),
                profesor_id: null, // null para preguntas del curso
                respuesta: answer
            });
        });

        // Agregar respuestas de profesores
        Object.entries(AppState.responses.professor_answers).forEach(([profesorId, answers]) => {
            Object.entries(answers).forEach(([questionId, answer]) => {
                data.respuestas.push({
                    pregunta_id: parseInt(questionId),
                    profesor_id: parseInt(profesorId),
                    respuesta: answer
                });
            });
        });

        return data;
    },

    handleSuccessfulSubmission(response) {
        // Redirigir a página de agradecimiento
        window.location.href = 'gracias.html';
    }
};

// Event Listeners
function initializeEventListeners() {
    // Botón principal de bienvenida
    const startFormBtn = document.getElementById('start-form-btn');
    if (startFormBtn) {
        startFormBtn.addEventListener('click', function() {
            // Ocultar pantalla de bienvenida y mostrar formulario
            document.getElementById('intro-text').style.display = 'none';
            document.getElementById('form-container').style.display = 'block';
            Utils.showStep(1);
        });
    }

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
        NavigationManager.resetSurvey.bind(NavigationManager));

    // Manejo de inputs de rating
    document.addEventListener('change', function(e) {
        if (e.target.type === 'radio' && e.target.closest('.rating-buttons')) {
            const ratingContainer = e.target.closest('.form-group');
            const selectedRating = ratingContainer.querySelector('.selected-rating');
            const ratingValue = ratingContainer.querySelector('.rating-value');
            
            if (selectedRating && ratingValue) {
                ratingValue.textContent = e.target.value;
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
                const maxLength = e.target.getAttribute('maxlength') || 1000;
                counter.textContent = `${length}/${maxLength} caracteres`;
            }
        }
    });
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
