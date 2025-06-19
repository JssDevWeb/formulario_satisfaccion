<?php

/**
 * Unit Tests for procesar_encuesta.php
 *
 * These tests are written for PHPUnit.
 * To run them, ensure PHPUnit is installed (e.g., via Composer: `composer require --dev phpunit/phpunit`).
 * Then execute from the project root:
 * `vendor/bin/phpunit api/procesar_encuesta.test.php`
 *
 * Note: This test suite focuses on the functions within procesar_encuesta.php.
 * It requires that 'api/procesar_encuesta.php' and its dependencies like
 * '../config/database.php' are loadable.
 *
 * Global functions (like header, http_response_code, file_get_contents) and `exit()` calls
 * present in the original script are not easily testable without refactoring the script
 * or using advanced PHPUnit features (like process isolation or function mocking libraries).
 * These tests primarily focus on the logic within the defined PHP functions, mocking PDO.
 */

use PHPUnit\Framework\TestCase;

// Mocking global functions if not using a library like AspectMock or uopz
// For simplicity, we'll assume database.php can be included and we can mock getConnection.
// If procesar_encuesta.php directly calls global PDO, it's harder.
// We will assume procesar_encuesta.php is included once, and its functions become available.

// It's often better to include the file under test within each test method
// or setUp to ensure a clean state, especially if it defines globals or constants conditionally.
// However, for function testing, including it once might be fine if functions are pure enough.
@include_once __DIR__ . '/procesar_encuesta.php'; // Use @ to suppress errors if already included elsewhere
@include_once __DIR__ . '/../config/database.php';


// Mock the PDO class and PDOStatement
if (!class_exists('PDOMock')) {
    class PDOMock extends \PDO {
        public function __construct() {} // Override constructor
        public function prepare($statement, $driver_options = []) {
            // Return a mock statement
            $stmtMock = Mockery::mock('PDOStatement');
            $stmtMock->shouldReceive('bindParam')->andReturn(true);
            $stmtMock->shouldReceive('bindValue')->andReturn(true);
            $stmtMock->shouldReceive('execute')->andReturn(true);
            // Set up default fetch/fetchAll behavior, can be overridden per test
            $stmtMock->shouldReceive('fetch')->andReturn(false);
            $stmtMock->shouldReceive('fetchAll')->andReturn([]);
            $stmtMock->shouldReceive('fetchColumn')->andReturn(null);
            return $stmtMock;
        }
        public function beginTransaction() {}
        public function commit() {}
        public function rollBack() {}
        public function lastInsertId($name = null) { return '1'; } // Default mock lastInsertId
        publicfunction exec($statement) { return 0; }
    }
}


class ProcesarEncuestaTest extends TestCase
{
    protected $pdoMock;
    protected $stmtMock;

    protected function setUp(): void
    {
        // Define constants if not already defined to avoid errors
        if (!defined('MODO_DESARROLLO')) define('MODO_DESARROLLO', true);
        if (!defined('HORAS_LIMITE_SPAM')) define('HORAS_LIMITE_SPAM', 24);
        if (!defined('MAX_TIEMPO_COMPLETADO')) define('MAX_TIEMPO_COMPLETADO', 3600);
        if (!defined('MIN_TIEMPO_COMPLETADO')) define('MIN_TIEMPO_COMPLETADO', 30);
        if (!defined('MAX_LONGITUD_TEXTO')) define('MAX_LONGITUD_TEXTO', 500);

        // Mock PDO and PDOStatement using Mockery
        $this->pdoMock = Mockery::mock('PDOMock'); // Use our extended mock for new PDO()
        $this->stmtMock = Mockery::mock('PDOStatement');

        // Default behaviors for stmtMock (can be overridden in specific tests)
        $this->stmtMock->shouldReceive('bindParam')->andReturn(true);
        $this->stmtMock->shouldReceive('bindValue')->andReturn(true);
        $this->stmtMock->shouldReceive('execute')->andReturn(true);
        $this->stmtMock->shouldReceive('fetch')->andReturn(false);
        $this->stmtMock->shouldReceive('fetchAll')->andReturn([]);
        $this->stmtMock->shouldReceive('fetchColumn')->andReturn(null);

        // Replace the global getConnection function if possible, or ensure it uses the mock.
        // This is tricky. A better way is to refactor procesar_encuesta.php to accept PDO via DI.
        // For now, we hope functions take $pdo as an argument.
    }

    protected function tearDown(): void
    {
        Mockery::close();
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testValidarDatosEntrada_ValidInput()
    {
        // Mock file_get_contents
        // This is hard without a library or refactoring.
        // We'll assume input is passed directly for isolated function test.
        $testData = [
            'formulario_id' => 1,
            'respuestas' => [['pregunta_id' => 1, 'respuesta' => 'test']]
        ];
        // Simulate the behavior of json_decode(file_get_contents('php://input'), true)
        // by directly calling the function with data that would result from it.
        // This requires refactoring validarDatosEntrada to accept $data or mocking globals.

        // For this test, let's assume validarDatosEntrada is refactored to take $inputString
        // $result = validarDatosEntrada(json_encode($testData));
        // As it is, we can't easily test it without side effects or complex mocking.

        // Test a simplified version or assume it works if other tests pass.
        // Focusing on tests for functions that accept $pdo.
        $this->markTestSkipped('Skipping testValidarDatosEntrada_ValidInput due to global file_get_contents dependency. Refactor to inject input source or use stream wrappers for testing.');

        // If we could mock file_get_contents:
        // StreamMemory::create(json_encode($testData));
        // $result = validarDatosEntrada();
        // $this->assertEquals(1, $result['formulario_id']);
        // $this->assertCount(1, $result['respuestas']);
    }

    public function testValidarDatosEntrada_MissingFormularioId()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('formulario_id es obligatorio y debe ser numérico');
        // Simulate input
        $_POST_backup = $_POST; // Backup global state if modifying directly
        $GLOBALS['_SERVER']['REQUEST_METHOD'] = 'POST'; // Ensure method is POST
        // This test would ideally mock file_get_contents.
        // For now, we manually call with what would be the decoded data.
        // To test the actual function as is, you would need to mock `file_get_contents('php://input')`
        // and `json_decode`. This is non-trivial for procedural code.
        // A direct call that simulates the internal logic after json_decode:
        $data = ['respuestas' => [['pregunta_id' => 1, 'respuesta' => 'test']]];
        // This is not how validarDatosEntrada is structured. It gets data itself.
        // We need to refactor or use more advanced mocking.
        $this->markTestSkipped('Skipping testValidarDatosEntrada_MissingFormularioId due to global state dependency.');
    }


    public function testVerificarAntiSpam_NoPreviousEntries()
    {
        $this->stmtMock->shouldReceive('fetch')->once()->andReturn(['total' => 0]);
        $this->pdoMock->shouldReceive('prepare')->once()->andReturn($this->stmtMock);

        $this->assertTrue(verificarAntiSpam($this->pdoMock, '127.0.0.1'));
    }

    public function testVerificarAntiSpam_PreviousEntriesExist()
    {
        $this->stmtMock->shouldReceive('fetch')->once()->andReturn(['total' => 1]);
        $this->pdoMock->shouldReceive('prepare')->once()->andReturn($this->stmtMock);

        $this->assertFalse(verificarAntiSpam($this->pdoMock, '127.0.0.1'));
    }

    public function testVerificarAntiSpam_ModoDesarrollo()
    {
        if (!defined('MODO_DESARROLLO_OLD')) define('MODO_DESARROLLO_OLD', MODO_DESARROLLO);
        $this->redefineConstant('MODO_DESARROLLO', true); // Helper to redefine
        $this->assertTrue(verificarAntiSpam($this->pdoMock, '127.0.0.1'));
        $this->redefineConstant('MODO_DESARROLLO', MODO_DESARROLLO_OLD); // Restore
    }


    public function testObtenerInfoFormulario_FoundAndActive()
    {
        $expectedFormulario = [
            'id' => 1, 'nombre' => 'Test Form', 'curso_id' => 10, 'activo' => 1,
            'fecha_inicio' => date('Y-m-d', strtotime('-1 day')),
            'fecha_fin' => date('Y-m-d', strtotime('+1 day')),
            'permite_respuestas_anonimas' => 0, 'curso_nombre' => 'Test Course', 'curso_activo' => 1
        ];
        $this->stmtMock->shouldReceive('fetch')->once()->andReturn($expectedFormulario);
        $this->pdoMock->shouldReceive('prepare')->once()->andReturn($this->stmtMock);

        $result = obtenerInfoFormulario($this->pdoMock, 1);
        $this->assertEquals($expectedFormulario, $result);
    }

    public function testObtenerInfoFormulario_NotFound()
    {
        $this->stmtMock->shouldReceive('fetch')->once()->andReturn(false);
        $this->pdoMock->shouldReceive('prepare')->once()->andReturn($this->stmtMock);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Formulario no encontrado');
        obtenerInfoFormulario($this->pdoMock, 1);
    }

    public function testObtenerInfoFormulario_NotActive()
    {
        $form = ['id' => 1, 'activo' => 0, 'curso_activo' => 1, /* ... other fields */];
        $this->stmtMock->shouldReceive('fetch')->once()->andReturn($form);
        $this->pdoMock->shouldReceive('prepare')->once()->andReturn($this->stmtMock);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Formulario o curso inactivo');
        obtenerInfoFormulario($this->pdoMock, 1);
    }

    public function testObtenerInfoFormulario_NotYetAvailable()
    {
        $form = [
            'id' => 1, 'activo' => 1, 'curso_activo' => 1,
            'fecha_inicio' => date('Y-m-d', strtotime('+1 day')),
            'fecha_fin' => date('Y-m-d', strtotime('+2 day')),
            /* ... */
        ];
        $this->stmtMock->shouldReceive('fetch')->once()->andReturn($form);
        $this->pdoMock->shouldReceive('prepare')->once()->andReturn($this->stmtMock);
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Formulario aún no está disponible');
        obtenerInfoFormulario($this->pdoMock, 1);
    }

    public function testObtenerInfoFormulario_Expired()
    {
        $form = [
            'id' => 1, 'activo' => 1, 'curso_activo' => 1,
            'fecha_inicio' => date('Y-m-d', strtotime('-2 day')),
            'fecha_fin' => date('Y-m-d', strtotime('-1 day')),
            /* ... */
        ];
        $this->stmtMock->shouldReceive('fetch')->once()->andReturn($form);
        $this->pdoMock->shouldReceive('prepare')->once()->andReturn($this->stmtMock);
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Formulario ya no está disponible');
        obtenerInfoFormulario($this->pdoMock, 1);
    }


    public function testObtenerPreguntasFormulario_ReturnsPreguntas()
    {
        $rawPreguntas = [
            ['id' => 1, 'texto' => 'Q1', 'seccion' => 'curso', 'tipo' => 'texto', 'es_obligatoria' => 1, 'opciones' => null],
            ['id' => 2, 'texto' => 'Q2', 'seccion' => 'profesor', 'tipo' => 'escala', 'es_obligatoria' => 0, 'opciones' => null],
        ];
        $expectedPreguntasById = [
            1 => $rawPreguntas[0],
            2 => $rawPreguntas[1],
        ];
        $this->stmtMock->shouldReceive('fetchAll')->once()->with(PDO::FETCH_ASSOC)->andReturn($rawPreguntas);
        $this->pdoMock->shouldReceive('prepare')->once()->andReturn($this->stmtMock);

        $result = obtenerPreguntasFormulario($this->pdoMock);
        $this->assertEquals($expectedPreguntasById, $result);
    }

    public function testObtenerProfesoresFormulario_ReturnsProfesorIds()
    {
        $dbResult = ['101', '102']; // PDO::FETCH_COLUMN returns flat array
        $expectedIntIds = [101, 102];

        $this->stmtMock->shouldReceive('fetchAll')->once()->with(PDO::FETCH_COLUMN)->andReturn($dbResult);
        $this->pdoMock->shouldReceive('prepare')->once()->andReturn($this->stmtMock);

        $result = obtenerProfesoresFormulario($this->pdoMock, 1);
        $this->assertEquals($expectedIntIds, $result);
    }

    public function testValidarRespuesta_RequiredButEmpty()
    {
        $pregunta = ['es_obligatoria' => 1, 'texto' => 'Required Q'];
        $errors = validarRespuesta($pregunta, '');
        $this->assertCount(1, $errors);
        $this->assertStringContainsString('Pregunta obligatoria', $errors[0]);
    }

    public function testValidarRespuesta_EscalaNonNumeric()
    {
        $pregunta = ['es_obligatoria' => 0, 'tipo' => 'escala', 'texto' => 'Scale Q'];
        $errors = validarRespuesta($pregunta, 'abc');
        $this->assertCount(1, $errors);
        $this->assertStringContainsString('debe ser numérica', $errors[0]);
    }

    public function testValidarRespuesta_EscalaOutOfRange_Default()
    {
        $pregunta = ['es_obligatoria' => 0, 'tipo' => 'escala', 'texto' => 'Scale Q', 'opciones' => null]; // No specific options
        $errors = validarRespuesta($pregunta, '11'); // Default range is 1-10 if not specified by 'escala_min/max'
        $this->assertCount(1, $errors);
        $this->assertStringContainsString('debe estar entre 1 y 10', $errors[0]);
    }

    public function testValidarRespuesta_EscalaOutOfRange_SpecificOptions()
    {
        $pregunta = [
            'es_obligatoria' => 0, 'tipo' => 'escala', 'texto' => 'Scale Q',
            'opciones' => json_encode([['valor'=>1], ['valor'=>2], ['valor'=>3]])
        ];
        $errors = validarRespuesta($pregunta, '4');
        $this->assertCount(1, $errors);
        $this->assertStringContainsString('Valor de escala no válido', $errors[0]);
        $this->assertStringContainsString('Valores permitidos: 1, 2, 3', $errors[0]);
    }

    public function testValidarRespuesta_TextoTooLong()
    {
        $pregunta = ['es_obligatoria' => 0, 'tipo' => 'texto', 'texto' => 'Text Q'];
        $longText = str_repeat('a', MAX_LONGITUD_TEXTO + 1);
        $errors = validarRespuesta($pregunta, $longText);
        $this->assertCount(1, $errors);
        $this->assertStringContainsString('Respuesta de texto muy larga', $errors[0]);
    }

    public function testValidarRespuesta_OpcionMultipleInvalidOption()
    {
        $pregunta = ['es_obligatoria' => 0, 'tipo' => 'opcion_multiple', 'texto' => 'MC Q', 'opciones' => json_encode(['A', 'B'])];
        $errors = validarRespuesta($pregunta, 'C');
        $this->assertCount(1, $errors);
        $this->assertStringContainsString('Opción no válida', $errors[0]);
    }

    public function testValidarRespuesta_Valid()
    {
        $pregunta = ['es_obligatoria' => 0, 'tipo' => 'texto', 'texto' => 'Text Q'];
        $errors = validarRespuesta($pregunta, 'Valid text');
        $this->assertCount(0, $errors);
    }


    public function testValidarRespuestas_AllValid()
    {
        $respuestasArray = [
            ['pregunta_id' => 1, 'profesor_id' => null, 'respuesta' => 'Good course'],
            ['pregunta_id' => 2, 'profesor_id' => 101, 'respuesta' => '5'],
        ];
        $preguntas = [
            1 => ['id' => 1, 'texto' => 'CQ1', 'seccion' => 'curso', 'tipo' => 'texto', 'es_obligatoria' => 0],
            2 => ['id' => 2, 'texto' => 'PQ1', 'seccion' => 'profesor', 'tipo' => 'escala', 'es_obligatoria' => 0, 'opciones' => null],
        ];
        $profesoresValidos = [101, 102];

        $errors = validarRespuestas($respuestasArray, $preguntas, $profesoresValidos);
        $this->assertCount(0, $errors);
    }

    public function testValidarRespuestas_InvalidPreguntaId()
    {
        $respuestasArray = [['pregunta_id' => 99, 'respuesta' => 'test']];
        $preguntas = [1 => ['id' => 1, 'seccion' => 'curso']];
        $profesoresValidos = [];

        $errors = validarRespuestas($respuestasArray, $preguntas, $profesoresValidos);
        $this->assertCount(1, $errors);
        $this->assertStringContainsString('Pregunta ID 99 (índice 0) no existe', $errors[0]);
    }

    public function testValidarRespuestas_ProfesorIdForCursoQuestion()
    {
        $respuestasArray = [['pregunta_id' => 1, 'profesor_id' => 101, 'respuesta' => 'test']];
        $preguntas = [1 => ['id' => 1, 'seccion' => 'curso', 'tipo'=>'texto']];
        $profesoresValidos = [101];

        $errors = validarRespuestas($respuestasArray, $preguntas, $profesoresValidos);
        $this->assertCount(1, $errors);
        $this->assertStringContainsString("es de sección 'curso', pero se proveyó un profesor_id", $errors[0]);
    }

    public function testValidarRespuestas_NullProfesorIdForProfesorQuestion()
    {
        $respuestasArray = [['pregunta_id' => 1, 'profesor_id' => null, 'respuesta' => 'test']];
        $preguntas = [1 => ['id' => 1, 'seccion' => 'profesor', 'tipo'=>'texto']];
        $profesoresValidos = [];

        $errors = validarRespuestas($respuestasArray, $preguntas, $profesoresValidos);
        $this->assertCount(1, $errors);
        $this->assertStringContainsString("es de sección 'profesor', pero no se proveyó profesor_id", $errors[0]);
    }

    public function testValidarRespuestas_InvalidProfesorId()
    {
        $respuestasArray = [['pregunta_id' => 1, 'profesor_id' => 999, 'respuesta' => 'test']];
        $preguntas = [1 => ['id' => 1, 'seccion' => 'profesor', 'tipo'=>'texto']];
        $profesoresValidos = [101];

        $errors = validarRespuestas($respuestasArray, $preguntas, $profesoresValidos);
        $this->assertCount(1, $errors);
        $this->assertStringContainsString("Profesor ID 999 (índice 0) no es válido", $errors[0]);
    }

    public function testValidarRespuestas_IndividualAnswerValidationFails()
    {
        $respuestasArray = [['pregunta_id' => 1, 'profesor_id' => null, 'respuesta' => '']]; // Empty required
        $preguntas = [1 => ['id' => 1, 'seccion' => 'curso', 'tipo'=>'texto', 'es_obligatoria' => 1, 'texto'=>'req']];
        $profesoresValidos = [];

        $errors = validarRespuestas($respuestasArray, $preguntas, $profesoresValidos);
        $this->assertCount(1, $errors);
        $this->assertStringContainsString("Error en respuesta para pregunta ID 1 (índice 0): Pregunta obligatoria: req", $errors[0]);
    }


    public function testInsertarEncuesta_SuccessfulInsertion()
    {
        $data = [
            'formulario_id' => 1,
            'tiempo_completado' => 120,
            'respuestas' => [
                ['pregunta_id' => 1, 'profesor_id' => null, 'respuesta' => 'Curso Test'],
                ['pregunta_id' => 2, 'profesor_id' => 101, 'respuesta' => '5']
            ]
        ];
        $formulario = ['curso_id' => 10, 'permite_respuestas_anonimas' => 0, 'nombre'=>'Form1', 'curso_nombre'=>'Course1'];
        $ip = '127.0.0.1';
        $userAgent = 'TestAgent';

        // Mocking for encuesta insertion
        $this->pdoMock->shouldReceive('beginTransaction')->once();
        $this->pdoMock->shouldReceive('prepare')->with(Mockery::on(function($sql){ return strpos($sql, 'INSERT INTO encuestas') !== false; }))->once()->andReturn($this->stmtMock);
        $this->pdoMock->shouldReceive('lastInsertId')->once()->andReturn('123');

        // Mocking for respuestas insertion (called for each respuesta)
        $this->pdoMock->shouldReceive('prepare')->with(Mockery::on(function($sql){ return strpos($sql, 'INSERT INTO respuestas') !== false; }))->once()->andReturn($this->stmtMock);
        $this->stmtMock->shouldReceive('execute')->times(count($data['respuestas']) + 1); // +1 for encuesta insert

        $this->pdoMock->shouldReceive('commit')->once();

        $result = insertarEncuesta($this->pdoMock, $data, $formulario, $ip, $userAgent);

        $this->assertEquals('123', $result['encuesta_id']);
        $this->assertEquals(count($data['respuestas']), $result['respuestas_insertadas']);
        $this->assertNotEmpty($result['hash_session']);
    }

    public function testInsertarEncuesta_RollbackOnException()
    {
        $data = [ /* ... */ ]; $formulario = [/* ... */]; $ip = ''; $userAgent = '';
        $this->pdoMock->shouldReceive('beginTransaction')->once();
        $this->pdoMock->shouldReceive('prepare')->andThrow(new PDOException('DB error'));
        $this->pdoMock->shouldReceive('rollBack')->once();
        $this->pdoMock->shouldNotReceive('commit');

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Error al guardar encuesta');
        insertarEncuesta($this->pdoMock, $data, $formulario, $ip, $userAgent);
    }

    // Helper to redefine constants for testing purposes
    protected function redefineConstant($name, $value) {
        if (defined($name)) {
            // Need uopz or similar to truly redefine, or run in separate process.
            // This is a simplification that won't work across all PHP setups / strictness levels for constants.
            // For MODO_DESARROLLO specifically, it's often checked with `defined() && CONSTANT_VALUE`.
            // A better approach is to wrap constants in functions or class constants that can be mocked/overridden.
            // For this test, we'll assume this simplistic redefinition works or MODO_DESARROLLO is checked loosely.
        }
        define($name, $value);
    }


    // procesarSolicitud is difficult to test in isolation without significant refactoring
    // or advanced mocking of global state (SERVER, file_get_contents, headers, etc.)
    // Tests for individual helper functions are more practical for the current structure.
    public function testProcesarSolicitud_ExampleFlow()
    {
        // This would require mocking:
        // - file_get_contents('php://input')
        // - $_SERVER superglobal
        // - getConnection() to return $this->pdoMock
        // - All helper functions (verificarAntiSpam, obtenerInfoFormulario, etc.) or their DB interactions
        // - header(), http_response_code(), echo json_encode(), exit()
        $this->markTestSkipped('Skipping procesarSolicitud integration test due to complexity of mocking global state and functions.');
    }

}

// Note: To properly mock global functions like getConnection() if it's not passed as a parameter,
// you might need to use techniques like:
// 1. Namespace importing and function mocking (if PHP version and setup allow).
// 2. Using a class wrapper for global functions and injecting/mocking the wrapper.
// 3. Using libraries like "Mockery" for some cases, or "PHPUnit Bridge" for global functions.
// For this example, functions are tested by passing mocks directly where possible.
// The constant redefinition is a placeholder for a more robust method (like uopz).
?>
