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
    startTime: null, // Hora de inicio de la encuesta
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
    // Retain the advanced loadAllQuestions from the workspace
    async loadAllQuestions() {
        if (AppState.allQuestions.length > 0) {
            debugLog('Todas las preguntas ya están cargadas.');
            return true;
        }
        try {
            const response = await Utils.fetchAPI(`${CONFIG.ENDPOINTS.preguntas}`);
            debugLog('Respuesta completa de API para get_preguntas.php:', response);

            if (response && response.success && response.data) {
                if (Array.isArray(response.data)) {
                    AppState.allQuestions = response.data;
                    debugLog('Todas las preguntas cargadas desde un array plano en response.data:', AppState.allQuestions);
                } else if (response.data.curso && response.data.profesor) {
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

                if (AppState.allQuestions.length > 0 && (AppState.courseQuestions.length === 0 && AppState.professorQuestions.length === 0)) {
                    AppState.courseQuestions = AppState.allQuestions.filter(q => q.seccion === 'curso');
                    AppState.professorQuestions = AppState.allQuestions.filter(q => q.seccion === 'profesor');
                }

                debugLog('AppState.allQuestions final:', AppState.allQuestions);
                debugLog('AppState.courseQuestions final derivado:', AppState.courseQuestions);
                debugLog('AppState.professorQuestions final derivado:', AppState.professorQuestions);
                return true;
            } else {
                Utils.showAlert('No se pudieron cargar las preguntas: respuesta inválida o no exitosa de la API.', 'warning');
                console.warn("Respuesta de API para preguntas (inválida o no exitosa):", response);
                return false;
            }
        } catch (error) {
            console.error('Error cargando todas las preguntas:', error);
            Utils.showAlert('Error crítico al cargar las preguntas base.', 'danger');
            return false;
        }
    },

    // Modified loadCourseQuestions to use new renderQuestions
    async loadCourseQuestions() {
        debugLog("Solicitando carga de preguntas de curso.");
        if (AppState.courseQuestions.length === 0 && AppState.allQuestions.length > 0) {
            debugLog("Usando preguntas de curso desde AppState.allQuestions pre-cargadas.");
            // AppState.courseQuestions should already be filtered by loadAllQuestions
        } else if (AppState.allQuestions.length === 0) {
            debugLog("AppState.allQuestions vacío. Ejecutando fallback de API para preguntas de curso.");
            try {
                const response = await Utils.fetchAPI(`${CONFIG.ENDPOINTS.preguntas}?seccion=curso`);
                debugLog("Respuesta de API en fallback (curso):", response);
                if (response && response.success && response.data && Array.isArray(response.data)) {
                    AppState.courseQuestions = response.data;
                    if (!AppState.allQuestions.some(q => q.seccion === 'curso')) {
                        AppState.allQuestions = AppState.allQuestions.concat(response.data);
                    }
                    debugLog("Preguntas de curso cargadas desde fallback y AppState actualizado.", { course: AppState.courseQuestions, all: AppState.allQuestions});
                } else {
                     Utils.showAlert('No hay preguntas disponibles para este curso (carga específica).', 'warning');
                     debugLog("No se encontraron preguntas de curso en fallback o respuesta inválida:", response);
                     return; // Exit if no questions loaded
                }
            } catch (error) {
                console.error('Error cargando preguntas del curso (carga específica):', error);
                Utils.showAlert('Error al cargar las preguntas del curso.', 'danger');
                return; // Exit on error
            }
        }

        if (AppState.courseQuestions.length > 0) {
            this.renderQuestions(AppState.courseQuestions, 'courseQuestions', 'course');
            if(AppState.currentFormulario) {
                document.getElementById('courseTitle').textContent = AppState.currentFormulario.curso_nombre;
            }
        } else {
            Utils.showAlert('No hay preguntas de curso para mostrar.', 'warning');
        }
    },

    // Modified loadProfessorQuestions to use new renderQuestions
    async loadProfessorQuestions() {
        debugLog("Solicitando carga de preguntas de profesor.");
        if (AppState.professorQuestions.length === 0 && AppState.allQuestions.length > 0) {
            debugLog("Usando preguntas de profesor desde AppState.allQuestions pre-cargadas.");
            // AppState.professorQuestions should already be filtered by loadAllQuestions
        } else if (AppState.allQuestions.length === 0) {
            debugLog("AppState.allQuestions vacío. Ejecutando fallback de API para preguntas de profesor.");
            try {
                const response = await Utils.fetchAPI(`${CONFIG.ENDPOINTS.preguntas}?seccion=profesor`);
                debugLog("Respuesta de API en fallback (profesor):", response);
                if (response && response.success && response.data && Array.isArray(response.data)) {
                    AppState.professorQuestions = response.data;
                     if (!AppState.allQuestions.some(q => q.seccion === 'profesor')) {
                        AppState.allQuestions = AppState.allQuestions.concat(response.data);
                    }
                    debugLog("Preguntas de profesor cargadas desde fallback y AppState actualizado.", { professor: AppState.professorQuestions, all: AppState.allQuestions});
                } else {
                    Utils.showAlert('No hay preguntas disponibles para evaluar profesores (carga específica).', 'warning');
                    debugLog("No se encontraron preguntas de profesor en fallback o respuesta inválida:", response);
                    return; // Exit if no questions loaded
                }
            } catch (error) {
                console.error('Error cargando preguntas del profesor (carga específica):', error);
                Utils.showAlert('Error al cargar las preguntas del profesor.', 'danger');
                return; // Exit on error
            }
        }

        if (AppState.professorQuestions.length > 0) {
            this.renderProfessorQuestions(); // This will call the new renderQuestions
        } else {
            Utils.showAlert('No hay preguntas de profesor para mostrar.', 'warning');
        }
    },

    // User's renderQuestions (table-based)
    renderQuestions(questions, containerId, prefix) {
        debugLog(`Renderizando ${questions.length} preguntas en ${containerId} con prefijo ${prefix}`);
        const container = document.getElementById(containerId);
        if (!container) {
            console.error(`Contenedor ${containerId} no encontrado.`);
            return;
        }
        container.innerHTML = ''; // Limpiar contenedor

        let html = `
            <div class="table-responsive">
                <table class="table evaluation-table table-hover">
                    <thead class="sticky-top">
                        <tr>
                            <th class="question-text-col">Pregunta</th>
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
            const inputName = `${prefix}_${question.id}`;
            if (question.tipo === 'escala') {
                html += this.createScaleHTML(question, inputName, index + 1);
            } else {
                html += this.createNonScaleQuestionHTML(question, inputName, index + 1);
            }
        });

        html += `
                    </tbody>
                </table>
            </div>
        `;
        container.innerHTML = html;
        this.addEmojiCellListeners(containerId);
        debugLog("Tabla de preguntas renderizada y listeners agregados.");
    },

    // User's renderProfessorQuestions (adapted to call new renderQuestions)
    renderProfessorQuestions() {
        const profesor = AppState.profesores[AppState.currentProfessorIndex];
        if (!profesor) {
            debugLog("renderProfessorQuestions: No hay profesor actual, saliendo.");
            return;
        }
        debugLog("Renderizando preguntas para profesor:", profesor);

        document.getElementById('professorTitle').textContent = profesor.nombre;
        document.getElementById('professorCounter').textContent = 
            `Profesor ${AppState.currentProfessorIndex + 1} de ${AppState.profesores.length}`;

        this.renderQuestions(
            AppState.professorQuestions, 
            'professorQuestions', 
            `professor_${profesor.id}` // Usar profesor.id para unicidad del input name
        );
        this.updateProfessorNavigation();
    },

    // User's createScaleHTML
    createScaleHTML(question, inputName, questionNumber) {
        const isRequired = question.es_obligatoria;
        const options = [
            { label: 'Excelente', value: 10, emoji: '😃' },
            { label: 'Bueno', value: 8, emoji: '🙂' },
            { label: 'Correcto', value: 6, emoji: '😐' },
            { label: 'Regular', value: 4, emoji: '😕' },
            { label: 'Deficiente', value: 2, emoji: '😞' }
        ];

        let cellsHTML = options.map(opt => `
            <td class="emoji-cell text-center" data-label="${opt.label} (${opt.value})">
                <input type="radio"
                       name="${inputName}"
                       id="${inputName}_${opt.value}"
                       value="${opt.value}"
                       ${isRequired ? 'required' : ''}
                       class="form-check-input visually-hidden">
                <label for="${inputName}_${opt.value}" class="emoji-only">${opt.emoji}</label>
            </td>
        `).join('');

        return `
            <tr data-question-id="${question.id}" data-question-type="${question.tipo}" ${isRequired ? 'data-required="true"' : ''}>
                <td class="question-text-col">
                    <span class="question-number">${questionNumber}.</span> ${question.texto}
                    ${isRequired ? '<span class="text-danger">*</span>' : ''}
                </td>
                ${cellsHTML}
            </tr>
        `;
    },

    // User's createMultipleChoiceHTML (for embedding in createNonScaleQuestionHTML)
    createMultipleChoiceHTML(question, inputName) {
        let optionsHTML = '';
        if (question.opciones_array && question.opciones_array.length > 0) {
            optionsHTML = question.opciones_array.map((opcion, index) => `
                <div class="form-check">
                    <input class="form-check-input" type="radio"
                           name="${inputName}" value="${opcion}"
                           id="${inputName}_${index}" ${question.es_obligatoria ? 'required' : ''}>
                    <label class="form-check-label" for="${inputName}_${index}">
                        ${opcion}
                    </label>
                </div>
            `).join('');
        }
        return `<div class="form-group question-content">${optionsHTML}</div>`;
    },

    // User's createOpenHTML (for embedding in createNonScaleQuestionHTML)
    createOpenHTML(question, inputName) {
        const maxLength = question.respuesta_info?.longitud_max || 2000; // User had 2000
        return `
            <div class="form-group question-content">
                <textarea class="form-control" name="${inputName}"
                          rows="4" maxlength="${maxLength}"
                          placeholder="Escriba sus comentarios aquí..."
                          ${question.es_obligatoria ? 'required' : ''}></textarea>
                <div class="form-text text-end char-counter-wrapper">
                    <span class="char-counter">0/${maxLength}</span> caracteres
                </div>
            </div>
        `;
    },

    // User's createNonScaleQuestionHTML
    createNonScaleQuestionHTML(question, inputName, questionNumber) {
        const isRequired = question.es_obligatoria;
        let contentHTML = '';

        switch (question.tipo) {
            case 'opcion_multiple':
                contentHTML = this.createMultipleChoiceHTML(question, inputName);
                break;
            case 'texto':
                contentHTML = this.createOpenHTML(question, inputName);
                break;
            default:
                contentHTML = `<p class="text-danger">Tipo de pregunta no soportado: ${question.tipo}</p>`;
        }

        return `
            <tr data-question-id="${question.id}" data-question-type="${question.tipo}" ${isRequired ? 'data-required="true"' : ''}>
                <td class="question-text-col">
                    <span class="question-number">${questionNumber}.</span> ${question.texto}
                    ${isRequired ? '<span class="text-danger">*</span>' : ''}
                </td>
                <td colspan="5" class="non-scale-input-cell">
                    ${contentHTML}
                </td>
            </tr>
        `;
    },

    // User's addEmojiCellListeners
    addEmojiCellListeners(containerId) {
        const container = document.getElementById(containerId);
        if (!container) return;

        container.querySelectorAll('.emoji-cell').forEach(cell => {
            cell.addEventListener('click', function() {
                // Desmarcar otras celdas en la misma fila (pregunta)
                this.closest('tr').querySelectorAll('.emoji-cell').forEach(otherCell => {
                    otherCell.classList.remove('selected');
                    otherCell.querySelector('.emoji-only').style.display = 'none';
                });
                // Marcar celda actual
                this.classList.add('selected');
                this.querySelector('.emoji-only').style.display = 'block';
                // Seleccionar el radio button correspondiente
                const radio = this.querySelector('input[type="radio"]');
                if (radio) radio.checked = true;

                // Quitar clase de error si estaba
                this.closest('tr').classList.remove('missing-answer', 'border-danger');
            });
        });
        debugLog(`Listeners de emoji agregados para ${containerId}`);
    },

    // Keep this utility from existing workspace
    updateProfessorNavigation() {
        const prevBtn = document.getElementById('prevProfessorBtn');
        const nextBtn = document.getElementById('nextProfessorBtn');
        const submitBtn = document.getElementById('submitBtn');

        const isFirst = AppState.currentProfessorIndex === 0;
        const isLast = AppState.currentProfessorIndex === AppState.profesores.length - 1;

        prevBtn.style.display = isFirst ? 'none' : 'inline-block';
        nextBtn.style.display = isLast ? 'none' : 'inline-block';
        submitBtn.style.display = isLast ? 'inline-block' : 'none';
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
        debugLog("Validando paso actual...");
        const currentStepElement = document.querySelector('.survey-step[style*="block"]');
        if (!currentStepElement) {
            debugLog("Elemento del paso actual no encontrado.");
            return true; // No hay nada que validar si no se encuentra el paso
        }

        let isValid = true;
        // Buscar todas las filas de preguntas que son requeridas
        const requiredQuestions = currentStepElement.querySelectorAll('tr[data-required="true"]');

        requiredQuestions.forEach(tr => {
            tr.classList.remove('missing-answer', 'border-danger'); // Limpiar validación previa
            let answered = false;
            const questionType = tr.dataset.questionType;

            if (questionType === 'escala') {
                // Para preguntas de escala, verificar si algún radio button está seleccionado
                const radios = tr.querySelectorAll('input[type="radio"]');
                if (Array.from(radios).some(radio => radio.checked)) {
                    answered = true;
                }
            } else if (questionType === 'texto') {
                // Para preguntas de texto, verificar si el textarea tiene contenido
                const textarea = tr.querySelector('textarea');
                if (textarea && textarea.value.trim() !== '') {
                    answered = true;
                }
            } else if (questionType === 'opcion_multiple') {
                // Para opción múltiple, verificar si algún radio button está seleccionado
                const radios = tr.querySelectorAll('input[type="radio"]');
                if (Array.from(radios).some(radio => radio.checked)) {
                    answered = true;
                }
            }
            // Añadir más tipos si es necesario

            if (!answered) {
                isValid = false;
                tr.classList.add('missing-answer', 'border-danger'); // Marcar la fila como no respondida
                debugLog(`Pregunta no respondida (ID: ${tr.dataset.questionId}):`, tr.querySelector('.question-text-col').textContent.trim());
            }
        });

        if (!isValid) {
            Utils.showAlert('Por favor, complete todas las preguntas marcadas con (*) antes de continuar.', 'warning');
            const firstMissing = currentStepElement.querySelector('.missing-answer');
            if (firstMissing) {
                firstMissing.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        }
        debugLog(`Validación completada. Es válido: ${isValid}`);
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

    calculateCompletionTime() {
        if (!AppState.startTime) return null;
        const endTime = new Date();
        const diffSeconds = Math.round((endTime - AppState.startTime) / 1000);
        debugLog(`Tiempo de completado calculado: ${diffSeconds} segundos.`);
        return diffSeconds;
    },

    prepareSubmissionData() {
        const tiempoCompletado = this.calculateCompletionTime();
        const data = {
            formulario_id: AppState.responses.formulario_id,
            respuestas: [],
            tiempo_completado: tiempoCompletado
        };
        debugLog("Preparando datos de envío. Tiempo completado:", tiempoCompletado);

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

    // Contador de caracteres para textarea
    // El listener de rating-buttons anterior ha sido eliminado porque
    // la nueva lógica de tabla usa addEmojiCellListeners.
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
    debugLog('Inicializando sistema de encuestas secuencial - DOMContentLoaded');
    
    // Inicializar event listeners
    initializeEventListeners(); // Ensure this is called to set up table interactions too
    
    // Cargar formularios disponibles
    await FormularioManager.loadFormularios();

    // Pre-cargar todas las preguntas al inicio de la aplicación
    QuestionManager.loadAllQuestions().then(loaded => {
        if (loaded) {
            debugLog("Preguntas base precargadas exitosamente post DOMContentLoaded.");
        } else {
            debugLog("Precarga de preguntas base falló o no retornó preguntas post DOMContentLoaded.");
        }
    }).catch(error => {
        console.error("Error en la precarga de preguntas post DOMContentLoaded:", error);
        Utils.showAlert("Hubo un problema al cargar datos iniciales de la encuesta (preguntas).", "warning", 0);
    });
    
    // Mostrar primer paso (selección de curso)
    Utils.showStep(1);
    // AppState.startTime será establecido cuando el usuario comience la encuesta (ej. al hacer clic en "startSurveyBtn")
    
    debugLog('Sistema de encuestas secuencial inicializado y listo.');
});

// Event Listeners - specifically for table interactions if not covered by existing ones.
// The user's addEmojiCellListeners is now part of QuestionManager.
// Textarea char counter is already document-delegated.
// Radio button changes inside tables for non-scale questions will work by default.
// The main point is that renderQuestions now calls addEmojiCellListeners.
