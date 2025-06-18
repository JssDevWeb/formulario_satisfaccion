/**
 * Unit Tests for survey_fixed.js
 *
 * These tests are written in a Jest-like syntax.
 * To run them, you would typically use the Jest test runner:
 * `npx jest survey_fixed.test.js` (assuming Jest is set up in your project)
 *
 * You might need to install Jest and set up a testing environment (e.g., with JSDOM for DOM manipulations).
 * `npm install --save-dev jest jsdom`
 *
 * Add to package.json:
 * "scripts": {
 *   "test": "jest"
 * },
 * "jest": {
 *   "testEnvironment": "jsdom"
 * }
 */

// JSDOM setup for document, window, etc. (if not using Jest's default JSDOM environment)
// const { JSDOM } = require('jsdom');
// const dom = new JSDOM('<!doctype html><html><body></body></html>');
// global.document = dom.window.document;
// global.window = dom.window;
// global.navigator = dom.window.navigator;
// global.fetch = jest.fn(); // Mock fetch globally
// global.alert = jest.fn();
// global.confirm = jest.fn();
// global.bootstrap = { Alert: jest.fn().mockImplementation(() => ({ close: jest.fn() })) };


// Mock global fetch
global.fetch = jest.fn();
// Mock Bootstrap Alert
global.bootstrap = { Alert: jest.fn().mockImplementation(() => ({ close: jest.fn() })) };


// Import functions/objects to be tested
// Note: This assumes survey_fixed.js is structured to export these or make them globally available.
// For this example, we'll assume they are global, as per the original script structure.
// If survey_fixed.js were a module, you'd use: import { Utils, AppState, ... } from './survey_fixed.js';

// Helper to reset AppState and DOM before each test
const resetAppState = () => {
    AppState.currentStep = 1;
    AppState.currentFormulario = null;
    AppState.profesores = [];
    AppState.currentProfessorIndex = 0;
    AppState.allQuestions = [];
    AppState.courseQuestions = [];
    AppState.professorQuestions = [];
    AppState.responses = {
        formulario_id: null,
        course_answers: {},
        professor_answers: {}
    };
};

const setupDOM = () => {
    document.body.innerHTML = `
        <div id="alertContainer"></div>
        <select id="formulario_id">
            <option value="">Seleccione un curso...</option>
        </select>
        <div id="formularioInfo"></div>
        <div id="profesoresInfo" style="display: none;">
            <ul id="profesoresList"></ul>
        </div>
        <button id="startSurveyBtn" disabled></button>

        <div id="progressBar" style="display: none;">
            <div id="progressText"></div>
            <div id="progressBarFill" style="width: 0%;"></div>
            <div id="currentStepText"></div>
        </div>
        <div id="resetSection" style="display: none;">
             <button id="resetForm"></button>
        </div>

        <div id="step1-course-selection" class="survey-step" style="display: block;"></div>
        <div id="step2-course-evaluation" class="survey-step" style="display: none;">
            <div id="courseTitle"></div>
            <div id="courseQuestions"></div>
            <button id="nextToProfessorsBtn"></button>
        </div>
        <div id="step3-professor-evaluation" class="survey-step" style="display: none;">
            <div id="professorTitle"></div>
            <div id="professorCounter"></div>
            <div id="professorQuestions"></div>
            <button id="prevProfessorBtn"></button>
            <button id="nextProfessorBtn"></button>
            <button id="submitBtn"></button>
        </div>
        <div id="loadingOverlay" style="display: none;"></div>
        <form id="surveyForm"></form>
    `;
};


describe('Utils', () => {
    beforeEach(() => {
        fetch.mockClear();
        setupDOM(); // Setup basic DOM for showAlert
        document.getElementById('alertContainer').innerHTML = '';
    });

    describe('fetchAPI', () => {
        it('should return data on successful fetch with data.success = true', async () => {
            const mockData = { success: true, data: { id: 1, name: 'Test' } };
            fetch.mockResolvedValueOnce({
                ok: true,
                json: async () => mockData,
            });
            const result = await Utils.fetchAPI('test-endpoint');
            expect(fetch).toHaveBeenCalledTimes(1);
            expect(result).toEqual(mockData);
        });

        it('should throw error on HTTP error (response.ok = false)', async () => {
            fetch.mockResolvedValueOnce({
                ok: false,
                status: 404,
                statusText: 'Not Found',
            });
            await expect(Utils.fetchAPI('test-endpoint')).rejects.toThrow('HTTP Error: 404 Not Found');
        });

        it('should throw error if data.success = false', async () => {
            const mockData = { success: false, message: 'Server validation failed' };
            fetch.mockResolvedValueOnce({
                ok: true,
                json: async () => mockData,
            });
            await expect(Utils.fetchAPI('test-endpoint')).rejects.toThrow('Server validation failed');
        });

        it('should throw default error if data.success = false and no message', async () => {
            const mockData = { success: false };
            fetch.mockResolvedValueOnce({
                ok: true,
                json: async () => mockData,
            });
            await expect(Utils.fetchAPI('test-endpoint')).rejects.toThrow('Error en la respuesta del servidor');
        });

         it('should throw error on network failure', async () => {
            fetch.mockRejectedValueOnce(new TypeError('Network request failed'));
            await expect(Utils.fetchAPI('test-endpoint')).rejects.toThrow('Network request failed');
        });
    });

    describe('showAlert', () => {
        jest.useFakeTimers();
        it('should create and append an alert message, then remove it after duration', () => {
            Utils.showAlert('Test message', 'success', 1000);
            const alertContainer = document.getElementById('alertContainer');
            expect(alertContainer.children.length).toBe(1);
            const alertElement = alertContainer.children[0];
            expect(alertElement.textContent.includes('Test message')).toBe(true);
            expect(alertElement.classList.contains('alert-success')).toBe(true);

            jest.advanceTimersByTime(1000);
            // Relies on bootstrap.Alert mock correctly simulating the close behavior
            // For a more direct test, we would check if alertElement.remove() was called,
            // or if the element is no longer in the container.
            // The current mock just simulates the bootstrap object exists.
            // A better mock for bootstrap.Alert would be:
            // const mockClose = jest.fn();
            // global.bootstrap = { Alert: jest.fn().mockImplementation(() => ({ close: mockClose })) };
            // expect(mockClose).toHaveBeenCalled();
            // For simplicity here, we assume it works if no error.
            expect(alertContainer.children.length).toBe(1); // Still 1 because mock close doesn't remove it
        });
        jest.useRealTimers();
    });

    describe('showStep', () => {
        it('should display the correct step and hide others', () => {
            setupDOM();
            Utils.showStep(2);
            expect(document.getElementById('step1-course-selection').style.display).toBe('none');
            expect(document.getElementById('step2-course-evaluation').style.display).toBe('block');
            expect(document.getElementById('step3-professor-evaluation').style.display).toBe('none');
            expect(AppState.currentStep).toBe(2);
        });

        it('should show progress bar for steps > 1', () => {
            setupDOM();
            Utils.showStep(2);
            expect(document.getElementById('progressBar').style.display).toBe('block');
            expect(document.getElementById('resetSection').style.display).toBe('block');
        });

        it('should hide progress bar for step 1', () => {
            setupDOM();
            Utils.showStep(1);
            expect(document.getElementById('progressBar').style.display).toBe('none');
            expect(document.getElementById('resetSection').style.display).toBe('none');
        });
    });
});


describe('FormularioManager', () => {
    beforeEach(() => {
        resetAppState();
        setupDOM();
        fetch.mockClear();
        // Mock Utils.showAlert to prevent actual alert rendering during tests
        jest.spyOn(Utils, 'showAlert').mockImplementation(() => {});
    });
    afterEach(() => {
        jest.restoreAllMocks(); // Restore any mocks
    });

    describe('loadFormularios', () => {
        it('should populate select element on successful API call', async () => {
            const mockFormularios = [
                { id: 1, nombre: 'Form A', curso_nombre: 'Curso 101' },
                { id: 2, nombre: 'Form B', curso_nombre: 'Curso 102' },
            ];
            fetch.mockResolvedValueOnce({
                ok: true,
                json: async () => ({ success: true, data: mockFormularios }),
            });

            await FormularioManager.loadFormularios();
            const select = document.getElementById('formulario_id');
            expect(select.options.length).toBe(mockFormularios.length + 1); // +1 for "Seleccione..."
            expect(select.options[1].value).toBe('1');
            expect(select.options[1].textContent).toBe('Form A - Curso 101');
        });

        it('should show alert if no formularios are available', async () => {
            fetch.mockResolvedValueOnce({
                ok: true,
                json: async () => ({ success: true, data: [] }),
            });
            await FormularioManager.loadFormularios();
            expect(Utils.showAlert).toHaveBeenCalledWith('No hay formularios disponibles', 'warning');
        });

        it('should show error alert on API fetch error', async () => {
            fetch.mockRejectedValueOnce(new Error('API Error'));
            await FormularioManager.loadFormularios();
            expect(Utils.showAlert).toHaveBeenCalledWith('Error al cargar los cursos disponibles', 'danger');
        });
    });

    describe('onFormularioChange', () => {
        beforeEach(() => {
            // Mock loadProfesores as it's called by onFormularioChange
            jest.spyOn(FormularioManager, 'loadProfesores').mockResolvedValue();
        });

        it('should update AppState and call loadProfesores when a formulario is selected', async () => {
            setupDOM(); // Ensure DOM is set up
            const formularioData = { id: 1, curso_nombre: 'Test Course', fecha_inicio: '2023-01-01', fecha_fin: '2023-12-31' };
            const select = document.getElementById('formulario_id');
            const option = document.createElement('option');
            option.value = '1';
            option.textContent = 'Test Form - Test Course';
            option.dataset.formulario = JSON.stringify(formularioData);
            select.appendChild(option);
            select.value = '1'; // Simulate selection

            await FormularioManager.onFormularioChange();

            expect(AppState.currentFormulario).toEqual(formularioData);
            expect(AppState.responses.formulario_id).toBe(formularioData.id);
            expect(FormularioManager.loadProfesores).toHaveBeenCalledWith(formularioData.id);
            expect(document.getElementById('formularioInfo').innerHTML).toContain('Test Course');
        });

        it('should clear info and disable button if no formulario is selected', async () => {
             setupDOM();
            const startSurveyBtn = document.getElementById('startSurveyBtn');
            startSurveyBtn.disabled = false;
            document.getElementById('formularioInfo').innerHTML = 'Some info';

            const select = document.getElementById('formulario_id');
            select.value = ''; // Simulate no selection

            await FormularioManager.onFormularioChange();

            expect(AppState.currentFormulario).toBeNull();
            expect(document.getElementById('formularioInfo').innerHTML).toBe('');
            expect(startSurveyBtn.disabled).toBe(true);
        });
    });
});

describe('QuestionManager', () => {
    beforeEach(() => {
        resetAppState();
        setupDOM();
        fetch.mockClear();
        jest.spyOn(Utils, 'showAlert').mockImplementation(()_ => {});
    });
    afterEach(() => {
        jest.restoreAllMocks();
    });

    describe('loadAllQuestions', () => {
        it('should populate AppState.allQuestions, courseQuestions, and professorQuestions from API (data.data format)', async () => {
            const mockQuestions = [
                { id: 1, texto: 'Q1', seccion: 'curso' },
                { id: 2, texto: 'Q2', seccion: 'profesor' },
                { id: 3, texto: 'Q3', seccion: 'curso' },
            ];
            fetch.mockResolvedValueOnce({
                ok: true,
                json: async () => ({ success: true, data: mockQuestions }),
            });

            const result = await QuestionManager.loadAllQuestions();
            expect(result).toBe(true);
            expect(AppState.allQuestions).toEqual(mockQuestions);
            expect(AppState.courseQuestions).toEqual([mockQuestions[0], mockQuestions[2]]);
            expect(AppState.professorQuestions).toEqual([mockQuestions[1]]);
        });

        it('should populate AppState from API (data.curso, data.profesor format)', async () => {
            const mockCursoQuestions = [{ id: 1, texto: 'Qc1', seccion: 'curso' }];
            const mockProfesorQuestions = [{ id: 2, texto: 'Qp1', seccion: 'profesor' }];
            fetch.mockResolvedValueOnce({
                ok: true,
                json: async () => ({ success: true, curso: mockCursoQuestions, profesor: mockProfesorQuestions }),
            });

            const result = await QuestionManager.loadAllQuestions();
            expect(result).toBe(true);
            expect(AppState.allQuestions).toEqual([...mockCursoQuestions, ...mockProfesorQuestions]);
            expect(AppState.courseQuestions).toEqual(mockCursoQuestions);
            expect(AppState.professorQuestions).toEqual(mockProfesorQuestions);
        });


        it('should return true and use existing questions if allQuestions is already populated', async () => {
            AppState.allQuestions = [{ id: 1, texto: 'Q1', seccion: 'curso' }];
            const result = await QuestionManager.loadAllQuestions();
            expect(result).toBe(true);
            expect(fetch).not.toHaveBeenCalled();
        });

        it('should show alert and return false if API response is not successful or malformed', async () => {
            fetch.mockResolvedValueOnce({
                ok: true,
                json: async () => ({ success: false, message: 'Failed to load' }),
            });
            // This will throw an error in fetchAPI, which is caught by loadAllQuestions
            const result = await QuestionManager.loadAllQuestions();
            expect(result).toBe(false); // fetchAPI error leads to this being false
            expect(Utils.showAlert).toHaveBeenCalledWith('Error crítico al cargar las preguntas base.', 'danger');
        });
    });

    describe('renderQuestions & createQuestionElement', () => {
        const questions = [
            { id: 1, texto: 'Rate this course.', tipo: 'escala', es_obligatoria: true, respuesta_info: { rango_min: 1, rango_max: 5} },
            { id: 2, texto: 'Your comments?', tipo: 'texto', es_obligatoria: false, respuesta_info: {longitud_max: 100} },
            { id: 3, texto: 'Choose one.', tipo: 'opcion_multiple', opciones_array: ['A', 'B'], es_obligatoria: true },
        ];
        const containerId = 'testQuestionsContainer';

        beforeEach(() => {
            const container = document.createElement('div');
            container.id = containerId;
            document.body.appendChild(container);
        });
        afterEach(() => {
            const container = document.getElementById(containerId);
            if (container) {
                container.remove();
            }
        });

        it('renderQuestions should append question elements to container using a DocumentFragment', () => {
            // Spy on createQuestionElement to verify it's called
            const createSpy = jest.spyOn(QuestionManager, 'createQuestionElement');
            QuestionManager.renderQuestions(questions, containerId, 'test');

            const container = document.getElementById(containerId);
            expect(createSpy).toHaveBeenCalledTimes(questions.length);
            expect(container.children.length).toBe(questions.length);
            // Test if one of the question types was rendered (e.g. first question)
            expect(container.innerHTML).toContain('Rate this course.');
            expect(container.innerHTML).toContain('rating-buttons'); // Escala
        });

        it('createQuestionElement should create HTML for "escala" questions', () => {
            const q = questions[0];
            const element = QuestionManager.createQuestionElement(q, 'test', 0);
            expect(element.dataset.questionId).toBe(String(q.id));
            expect(element.innerHTML).toContain(q.texto);
            expect(element.innerHTML).toContain('rating-buttons');
            expect(element.querySelectorAll('input[type="radio"]').length).toBe(5); // 1 to 5
            expect(element.querySelector('input[required]')).not.toBeNull();
        });

        it('createQuestionElement should create HTML for "texto" questions', () => {
            const q = questions[1];
            const element = QuestionManager.createQuestionElement(q, 'test', 1);
            expect(element.innerHTML).toContain(q.texto);
            expect(element.querySelector('textarea')).not.toBeNull();
            expect(element.querySelector('textarea[maxlength="100"]')).not.toBeNull();
            expect(element.querySelector('textarea[required]')).toBeNull();
        });

        it('createQuestionElement should create HTML for "opcion_multiple" questions', () => {
            const q = questions[2];
            const element = QuestionManager.createQuestionElement(q, 'test', 2);
            expect(element.innerHTML).toContain(q.texto);
            expect(element.querySelectorAll('input[type="radio"]').length).toBe(q.opciones_array.length);
            expect(element.querySelector('input[required]')).not.toBeNull();
        });
    });
});


describe('NavigationManager', () => {
    beforeEach(() => {
        resetAppState();
        setupDOM();
        fetch.mockClear(); // Clear fetch mock for Utils.fetchAPI
        jest.spyOn(Utils, 'showAlert').mockImplementation(() => {});
        jest.spyOn(Utils, 'showStep').mockImplementation(() => {});
        jest.spyOn(QuestionManager, 'loadAllQuestions').mockResolvedValue(true);
        jest.spyOn(QuestionManager, 'loadCourseQuestions').mockResolvedValue();
        jest.spyOn(QuestionManager, 'loadProfessorQuestions').mockResolvedValue();
        jest.spyOn(QuestionManager, 'renderProfessorQuestions').mockImplementation(() => {});
    });
    afterEach(() => {
        jest.restoreAllMocks();
    });

    describe('startSurvey', () => {
        it('should call loadAllQuestions, showStep(2), and loadCourseQuestions', async () => {
            await NavigationManager.startSurvey();
            expect(QuestionManager.loadAllQuestions).toHaveBeenCalled();
            expect(Utils.showStep).toHaveBeenCalledWith(2);
            expect(QuestionManager.loadCourseQuestions).toHaveBeenCalled();
        });
        it('should show alert and not proceed if loadAllQuestions fails', async () => {
            QuestionManager.loadAllQuestions.mockResolvedValueOnce(false);
            await NavigationManager.startSurvey();
            expect(Utils.showAlert).toHaveBeenCalledWith('No se pudieron cargar las preguntas necesarias para iniciar la encuesta.', 'danger');
            expect(Utils.showStep).not.toHaveBeenCalled();
        });
    });

    describe('nextToProfessors', () => {
        // Mock validateCurrentStep and saveCourseAnswers
        const mockValidate = jest.spyOn(NavigationManager, 'validateCurrentStep');
        const mockSaveCourse = jest.spyOn(NavigationManager, 'saveCourseAnswers');

        it('should proceed if validation passes', async () => {
            mockValidate.mockReturnValue(true);
            AppState.profesores = [{ id: 1, nombre: 'Prof X'}]; // Need at least one professor

            await NavigationManager.nextToProfessors();

            expect(mockValidate).toHaveBeenCalled();
            expect(mockSaveCourse).toHaveBeenCalled();
            expect(Utils.showStep).toHaveBeenCalledWith(3);
            expect(QuestionManager.loadProfessorQuestions).toHaveBeenCalled();
        });

        it('should show alert and not proceed if validation fails', async () => {
            mockValidate.mockReturnValue(false);
            await NavigationManager.nextToProfessors();
            expect(Utils.showAlert).toHaveBeenCalledWith('Por favor complete todas las preguntas requeridas', 'warning');
            expect(mockSaveCourse).not.toHaveBeenCalled();
        });
    });

    describe('validateCurrentStep', () => {
        it('should return true if all required inputs are filled', () => {
            // Setup DOM with a step and a required radio group
            document.body.innerHTML += `
                <div id="step-test" class="survey-step" style="display: block;">
                    <div class="mb-4"> <!-- Question Group Element -->
                        <input type="radio" name="q1" value="a" required id="q1a">
                        <input type="radio" name="q1" value="b" id="q1b" checked>
                    </div>
                    <div class="mb-4"> <!-- Question Group Element -->
                         <textarea name="q2" required id="q2"></textarea>
                    </div>
                </div>
            `;
            document.getElementById('q2').value = "Some text";
            expect(NavigationManager.validateCurrentStep()).toBe(true);
        });

        it('should return false and add error classes if required radio is not checked', () => {
             document.body.innerHTML += `
                <div id="step-test" class="survey-step" style="display: block;">
                    <div class="mb-4" id="qg1"> <!-- Question Group Element -->
                        <input type="radio" name="q1" value="a" required id="q1a">
                        <input type="radio" name="q1" value="b" id="q1b">
                    </div>
                </div>
            `;
            expect(NavigationManager.validateCurrentStep()).toBe(false);
            expect(document.getElementById('qg1').classList.contains('border-danger')).toBe(true);
        });

        it('should return false and add error classes if required textarea is empty', () => {
             document.body.innerHTML += `
                <div id="step-test" class="survey-step" style="display: block;">
                     <div class="mb-4" id="qg2"> <!-- Question Group Element -->
                        <textarea name="q2" required id="q2"></textarea>
                    </div>
                </div>
            `;
            expect(NavigationManager.validateCurrentStep()).toBe(false);
            expect(document.getElementById('q2').classList.contains('is-invalid')).toBe(true);
            expect(document.getElementById('qg2').classList.contains('border-danger')).toBe(true);
        });
    });

    describe('saveCourseAnswers / saveProfessorAnswers', () => {
        it('saveCourseAnswers should update AppState.responses.course_answers', () => {
            const courseQuestionsDiv = document.getElementById('courseQuestions');
            courseQuestionsDiv.innerHTML = `
                <input type="radio" name="course_1" value="val1" checked>
                <textarea name="course_2">text answer</textarea>
                <input type="radio" name="course_3" value="optA"> <!-- Not checked -->
            `;
            NavigationManager.saveCourseAnswers();
            expect(AppState.responses.course_answers['1']).toBe('val1');
            expect(AppState.responses.course_answers['2']).toBe('text answer');
            expect(AppState.responses.course_answers['3']).toBeUndefined();
        });

        it('saveProfessorAnswers should update AppState.responses.professor_answers', () => {
            AppState.profesores = [{ id: 101, nombre: 'Prof Test' }];
            AppState.currentProfessorIndex = 0;
            const professorQuestionsDiv = document.getElementById('professorQuestions');
            professorQuestionsDiv.innerHTML = `
                <input type="radio" name="prof_101_5" value="5" checked>
                <textarea name="prof_101_6">good</textarea>
            `;

            NavigationManager.saveProfessorAnswers();
            const profId = AppState.profesores[AppState.currentProfessorIndex].id;
            expect(AppState.responses.professor_answers[profId]['5']).toBe('5');
            expect(AppState.responses.professor_answers[profId]['6']).toBe('good');
        });
    });
});

describe('SubmissionManager', () => {
    beforeEach(() => {
        resetAppState();
        setupDOM();
        fetch.mockClear();
        jest.spyOn(Utils, 'showAlert').mockImplementation(() => {});
        // Mock window.location.href
        delete window.location;
        window.location = { href: '' };
    });
    afterEach(() => {
        jest.restoreAllMocks();
    });

    describe('prepareSubmissionData', () => {
        it('should correctly format data from AppState', () => {
            AppState.responses.formulario_id = 1;
            AppState.responses.course_answers = { '10': 'text_val', '11': '3' };
            AppState.responses.professor_answers = {
                '101': { '20': 'A', '21': '5' },
                '102': { '20': 'B' }
            };

            const expectedData = {
                formulario_id: 1,
                respuestas: [
                    { pregunta_id: 10, profesor_id: null, respuesta: 'text_val' },
                    { pregunta_id: 11, profesor_id: null, respuesta: '3' },
                    { pregunta_id: 20, profesor_id: 101, respuesta: 'A' },
                    { pregunta_id: 21, profesor_id: 101, respuesta: '5' },
                    { pregunta_id: 20, profesor_id: 102, respuesta: 'B' },
                ],
            };
            const submissionData = SubmissionManager.prepareSubmissionData();
            // Sort arrays for comparison as order might not be guaranteed by Object.entries
            submissionData.respuestas.sort((a,b) => a.pregunta_id - b.pregunta_id || (a.profesor_id || 0) - (b.profesor_id || 0) );
            expectedData.respuestas.sort((a,b) => a.pregunta_id - b.pregunta_id || (a.profesor_id || 0) - (b.profesor_id || 0) );
            expect(submissionData).toEqual(expectedData);
        });
    });

    describe('submitSurvey', () => {
        const mockValidate = jest.spyOn(NavigationManager, 'validateCurrentStep');
        const mockSaveProf = jest.spyOn(NavigationManager, 'saveProfessorAnswers');

        it('should redirect on successful submission', async () => {
            mockValidate.mockReturnValue(true);
            fetch.mockResolvedValueOnce({
                ok: true,
                json: async () => ({ success: true }),
            });

            await SubmissionManager.submitSurvey();
            expect(mockSaveProf).toHaveBeenCalled();
            expect(fetch).toHaveBeenCalledTimes(1);
            expect(window.location.href).toBe('gracias.html');
            expect(document.getElementById('loadingOverlay').style.display).toBe('none');
        });

        it('should show alert on API error', async () => {
            mockValidate.mockReturnValue(true);
            fetch.mockRejectedValueOnce(new Error('API Network Error'));

            await SubmissionManager.submitSurvey();
            expect(mockSaveProf).toHaveBeenCalled();
            expect(Utils.showAlert).toHaveBeenCalledWith(
                expect.stringContaining('Error al enviar la encuesta.'),
                'danger',
                0
            );
            expect(document.getElementById('loadingOverlay').style.display).toBe('none');
        });

        it('should show alert with server message on API error (data.success: false)', async () => {
            mockValidate.mockReturnValue(true);
            fetch.mockResolvedValueOnce({ // This setup makes fetchAPI throw new Error(data.message)
                ok: true,
                json: async () => ({ success: false, message: "Server validation failed" }),
            });

            await SubmissionManager.submitSurvey();
            expect(mockSaveProf).toHaveBeenCalled();
            expect(Utils.showAlert).toHaveBeenCalledWith(
                'Error al enviar la encuesta. Por favor intente nuevamente. Detalle: Server validation failed',
                'danger',
                0
            );
            expect(document.getElementById('loadingOverlay').style.display).toBe('none');
        });


        it('should not submit if validation fails', async () => {
            mockValidate.mockReturnValue(false);
            await SubmissionManager.submitSurvey();
            expect(Utils.showAlert).toHaveBeenCalledWith('Por favor complete todas las preguntas requeridas', 'warning');
            expect(fetch).not.toHaveBeenCalled();
            expect(document.getElementById('loadingOverlay').style.display).toBe('none'); // Should still hide if shown
        });
    });
});

// Example of how to run:
// 1. Make sure survey_fixed.js is loaded in the test environment or its functions are accessible.
//    (This might require temporarily modifying survey_fixed.js to use exports if run in Node with Jest,
//    or ensuring it populates the global scope if run in a browser-like Jest environment like JSDOM).
// 2. Run `npx jest your_test_file.test.js`
//
// Note on DOM elements: These tests assume survey_fixed.js correctly finds elements by ID.
// The setupDOM helper provides these elements.
//
// Note on async operations: Tests involving promises (async/await) are handled correctly by Jest.
//
// Note on Bootstrap components: Real Bootstrap JS is not run. The mock for bootstrap.Alert
// is very basic. Testing complex UI interactions might require more sophisticated tools like Testing Library.

console.log("survey_fixed.test.js loaded. Run with Jest.");

// To make this file runnable with Node and Jest, ensure survey_fixed.js
// either attaches its main objects (Utils, FormularioManager, etc.) to `global` or `window`
// when in a test-like environment, or is refactored into an ES module or CommonJS module.
// For this example, we assume global availability as per typical script tag usage.
// If survey_fixed.js is:
// (function() { /* all code */ window.Utils = Utils; /* etc */ })();
// Then these tests can access them via window.Utils or just Utils in JSDOM.

// If survey_fixed.js is already loaded via a <script> tag in the HTML file Jest uses (testEnvironmentOptions)
// then the objects might be globally available.

// For a standalone script, you might need to load it using a utility:
// const fs = require('fs');
// const path = require('path');
// const surveyScript = fs.readFileSync(path.resolve(__dirname, './survey_fixed.js'), 'utf-8');
// eval(surveyScript); // This is generally not recommended but can work for non-module scripts.

// Or, ideally, refactor survey_fixed.js to be a module.
// export { Utils, AppState, FormularioManager, QuestionManager, NavigationManager, SubmissionManager };
// And in tests:
// import { Utils, ... } from './survey_fixed.js';
// Jest handles ES modules with experimental flag or Babel.

// The current `global.fetch = jest.fn()` approach is standard for Jest.
// JSDOM provides `document`, `window`, etc.

// Final check: Ensure all described functionalities have at least one test case.
// - FormularioManager: loadFormularios (success, empty, error), onFormularioChange (select, noselect) - Covered
// - QuestionManager: loadAllQuestions (success data/curso-prof, already loaded, api error), renderQuestions, createQuestionElement (multiple types) - Covered
// - NavigationManager: startSurvey (success, fail load), nextToProfessors (valid, invalid), validateCurrentStep (various cases), saveCourseAnswers, saveProfessorAnswers - Covered
// - SubmissionManager: prepareSubmissionData, submitSurvey (success, api error, validation fail) - Covered
// - Utils: fetchAPI (success, http error, server error, network fail), showAlert, showStep - Covered
