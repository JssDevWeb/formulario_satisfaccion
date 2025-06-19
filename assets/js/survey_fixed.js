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
    allQuestions: [], // Stores all questions (course and professor)
    courseQuestions: [], // Derived from allQuestions
    professorQuestions: [], // Derived from allQuestions
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
const QuestionManager = {
    async loadAllQuestions() {
        if (AppState.allQuestions.length > 0) {
            debugLog('Todas las preguntas ya están cargadas.');
            return true;
        }
        try {
            // Attempt to fetch all questions. Assuming the API returns both course and professor questions
            // If seccion is not specified, or if there's an 'all' parameter.
            // This is an assumption about the backend API (get_preguntas.php)
            const response = await Utils.fetchAPI(`${CONFIG.ENDPOINTS.preguntas}`);
            debugLog('Respuesta completa de API para get_preguntas.php:', response);

            // The PHP API (get_preguntas.php) when seccion=todas (default) returns:
            // { success: true, data: { curso: [], profesor: [] }, ... }
            // When a specific seccion is requested, it returns:
            // { success: true, data: [ ...questions for that section... ], ... }

            if (response && response.success && response.data) {
                if (Array.isArray(response.data)) {
                    // This case handles if API returns a flat array of all questions (e.g. future API version or specific filter)
                    // Each question object in this array must have a 'seccion' property.
                    AppState.allQuestions = response.data;
                    debugLog('Todas las preguntas cargadas desde un array plano en response.data:', AppState.allQuestions);
                } else if (response.data.curso && response.data.profesor) {
                    // This handles the default case for seccion=todas from get_preguntas.php
                    AppState.courseQuestions = response.data.curso;
                    AppState.professorQuestions = response.data.profesor;
                    AppState.allQuestions = [...response.data.curso, ...response.data.profesor];
                    debugLog('Preguntas de curso cargadas desde response.data.curso:', AppState.courseQuestions);
                    debugLog('Preguntas de profesor cargadas desde response.data.profesor:', AppState.professorQuestions);
                    debugLog('Todas las preguntas combinadas en AppState.allQuestions:', AppState.allQuestions);
                } else {
                    Utils.showAlert('Formato de datos de preguntas no reconocido.', 'warning');
                    console.warn("Respuesta de API para preguntas (formato no reconocido):", response);
                    return false;
                }

                // If AppState.allQuestions was populated directly (e.g. flat array)
                // and course/professor questions weren't, derive them now.
                if (AppState.allQuestions.length > 0 && (AppState.courseQuestions.length === 0 && AppState.professorQuestions.length === 0)) {
                    AppState.courseQuestions = AppState.allQuestions.filter(q => q.seccion === 'curso');
                    AppState.professorQuestions = AppState.allQuestions.filter(q => q.seccion === 'profesor');
                }

                debugLog('AppState.allQuestions final:', AppState.allQuestions);
                debugLog('AppState.courseQuestions final derivado:', AppState.courseQuestions);
                debugLog('AppState.professorQuestions final derivado:', AppState.professorQuestions);
                return true; // Successfully processed questions
            } else {
                // This 'else' block is executed if the condition (response && response.success && response.data) is false.
                // This means either the response object itself is falsy, or success is false, or data is missing.
                Utils.showAlert('No se pudieron cargar las preguntas: respuesta inválida o no exitosa de la API.', 'warning');
                console.warn("Respuesta de API para preguntas (inválida o no exitosa):", response); // Corrected 'data' to 'response'
                return false; // Indicate failure
            }
        } catch (error) {
            console.error('Error cargando todas las preguntas:', error); // Catches errors from fetchAPI or other synchronous errors
            Utils.showAlert('Error crítico al cargar las preguntas base.', 'danger');
            return false;
        }
    },

    async loadCourseQuestions() {
        if (AppState.courseQuestions.length === 0 && AppState.allQuestions.length > 0) {
             // Already loaded and filtered by loadAllQuestions
            debugLog("Usando preguntas de curso desde allQuestions");
        } else if (AppState.allQuestions.length === 0) {
            // Fallback or initial load if loadAllQuestions hasn't run or failed for course specific part
            debugLog("loadAllQuestions no ha sido llamado o no retornó preguntas de curso, intentando carga específica.");
            // This path should ideally not be taken if loadAllQuestions is called first.
            // Kept for robustness or if direct call is ever needed.
            try {
                debugLog("Ejecutando fallback de API para preguntas de curso");
                const response = await Utils.fetchAPI(`${CONFIG.ENDPOINTS.preguntas}?seccion=curso`);
                debugLog("Respuesta de API en fallback (curso):", response);
                // When seccion=curso, PHP returns { success: true, data: [...] }
                if (response && response.success && response.data && Array.isArray(response.data) && response.data.length > 0) {
                    AppState.courseQuestions = response.data;
                    debugLog("Preguntas de curso cargadas desde fallback:", AppState.courseQuestions);
                    // If allQuestions is empty, populate it partially or fully
                    if (!AppState.allQuestions.some(q => q.seccion === 'curso')) {
                        AppState.allQuestions = AppState.allQuestions.concat(response.data);
                         debugLog("AppState.allQuestions actualizado desde fallback (curso):", AppState.allQuestions);
                    }
                } else {
                     Utils.showAlert('No hay preguntas disponibles para este curso (carga específica)', 'warning');
                     debugLog("No se encontraron preguntas de curso en fallback o respuesta inválida:", response);
                     return;
                }
            } catch (error) {
                console.error('Error cargando preguntas del curso (carga específica):', error);
                Utils.showAlert('Error al cargar las preguntas del curso', 'danger');
                return;
            }
        }

        if (AppState.courseQuestions.length > 0) {
            this.renderQuestions(AppState.courseQuestions, 'courseQuestions', 'course');
            document.getElementById('courseTitle').textContent = AppState.currentFormulario.curso_nombre;
        } else {
            Utils.showAlert('No hay preguntas de curso para mostrar.', 'warning');
        }
    },

    async loadProfessorQuestions() {
        if (AppState.professorQuestions.length === 0 && AppState.allQuestions.length > 0) {
            debugLog("Usando preguntas de profesor desde allQuestions");
        } else if (AppState.allQuestions.length === 0) {
            debugLog("loadAllQuestions no ha sido llamado o no retornó preguntas de profesor, intentando carga específica.");
            // This path should ideally not be taken.
            try {
                debugLog("Ejecutando fallback de API para preguntas de profesor");
                const response = await Utils.fetchAPI(`${CONFIG.ENDPOINTS.preguntas}?seccion=profesor`);
                debugLog("Respuesta de API en fallback (profesor):", response);
                // When seccion=profesor, PHP returns { success: true, data: [...] }
                if (response && response.success && response.data && Array.isArray(response.data) && response.data.length > 0) {
                    AppState.professorQuestions = response.data;
                    debugLog("Preguntas de profesor cargadas desde fallback:", AppState.professorQuestions);
                     if (!AppState.allQuestions.some(q => q.seccion === 'profesor')) {
                        AppState.allQuestions = AppState.allQuestions.concat(response.data);
                        debugLog("AppState.allQuestions actualizado desde fallback (profesor):", AppState.allQuestions);
                    }
                } else {
                    Utils.showAlert('No hay preguntas disponibles para evaluar profesores (carga específica)', 'warning');
                    debugLog("No se encontraron preguntas de profesor en fallback o respuesta inválida:", response);
                    return;
                }
            } catch (error) {
                console.error('Error cargando preguntas del profesor (carga específica):', error);
                Utils.showAlert('Error al cargar las preguntas del profesor', 'danger');
                return;
            }
        }

        if (AppState.professorQuestions.length > 0) {
            this.renderProfessorQuestions();
        } else {
            Utils.showAlert('No hay preguntas de profesor para mostrar.', 'warning');
        }
    },

    renderQuestions(questions, containerId, prefix) {
        debugLog(`Renderizando preguntas para ${containerId}`, { count: questions ? questions.length : 0, questions: questions });
        const container = document.getElementById(containerId);
        container.innerHTML = ''; // Clear existing questions

        if (!questions || questions.length === 0) {
            debugLog(`No questions to render for ${containerId}`);
            return;
        }

        const fragment = document.createDocumentFragment();
        questions.forEach((question, index) => {
            const questionDiv = this.createQuestionElement(question, prefix, index);
            fragment.appendChild(questionDiv);
        });

        debugLog(`Fragmento para ${containerId} tiene ${fragment.childNodes.length} nodos hijos.`, fragment);
        container.appendChild(fragment); // Append all questions at once
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
    async startSurvey() {
        debugLog('Iniciando encuesta');
        const questionsLoaded = await QuestionManager.loadAllQuestions();
        if (!questionsLoaded) {
            Utils.showAlert('No se pudieron cargar las preguntas necesarias para iniciar la encuesta.', 'danger');
            return;
        }
        Utils.showStep(2);
        // loadCourseQuestions will now use pre-loaded questions if available
        await QuestionManager.loadCourseQuestions();
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
        // loadProfessorQuestions will now use pre-loaded questions if available
        await QuestionManager.loadProfessorQuestions();
        // renderProfessorQuestions is called by loadProfessorQuestions if successful
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
        const validatedRadioGroups = {}; // Cache for radio group validation

        requiredInputs.forEach(input => {
            const questionGroupElement = input.closest('.mb-4, .form-group');

            if (input.type === 'radio') {
                const groupName = input.name;
                if (validatedRadioGroups[groupName] === undefined) { // Check if group already validated
                    const radioGroup = currentStepElement.querySelectorAll(`[name="${groupName}"]`);
                    const hasChecked = Array.from(radioGroup).some(radio => radio.checked);
                    validatedRadioGroups[groupName] = hasChecked; // Store validation result
                }

                if (!validatedRadioGroups[groupName]) {
                    isValid = false;
                    if (questionGroupElement) questionGroupElement.classList.add('border-danger');
                } else {
                    if (questionGroupElement) questionGroupElement.classList.remove('border-danger');
                }
            } else if (input.tagName === 'TEXTAREA' || input.type === 'text') {
                if (!input.value.trim()) {
                    isValid = false;
                    input.classList.add('is-invalid');
                    if (questionGroupElement) questionGroupElement.classList.add('border-danger');
                } else {
                    input.classList.remove('is-invalid');
                    if (questionGroupElement) questionGroupElement.classList.remove('border-danger');
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
            let displayErrorMessage = 'Error al enviar la encuesta. Por favor intente nuevamente.';
            if (error && error.message) {
                // Append server message if it's not a generic HTTP error status text
                if (!error.message.startsWith('HTTP Error:')) {
                    displayErrorMessage += ` Detalle: ${error.message}`;
                }
            }
            Utils.showAlert(displayErrorMessage, 'danger', 0); // 0 duration for persistent alert
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
document.addEventListener('DOMContentLoaded', async function() {
    debugLog('Inicializando sistema de encuestas secuencial');
    
    // Inicializar event listeners
    initializeEventListeners();
    
    // Cargar formularios disponibles
    await FormularioManager.loadFormularios();

    // Pre-cargar todas las preguntas al inicio de la aplicación
    // Esto sucede en segundo plano y no bloquea la selección del formulario
    QuestionManager.loadAllQuestions().then(loaded => {
        if (loaded) {
            debugLog("Preguntas base precargadas exitosamente.");
        } else {
            debugLog("Precarga de preguntas base falló o no retornó preguntas.");
        }
    }).catch(error => {
        console.error("Error en la precarga de preguntas:", error);
        Utils.showAlert("Hubo un problema al cargar datos iniciales de la encuesta.", "warning", 0); // Persistent alert
    });
    
    // Mostrar primer paso
    Utils.showStep(1);
    
    debugLog('Sistema inicializado correctamente');
});
