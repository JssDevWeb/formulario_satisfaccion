<?php
/**
 * API: Procesar Envío de Encuesta
 * 
 * @file procesar_encuesta.php
 * @description Endpoint para procesar y guardar respuestas de encuestas con validaciones completas
 * @method POST
 * @version 1.0
 * @author Sistema de Encuestas Académicas
 * @date 11 de junio de 2025
 * 
 * Estructura JSON esperada:
 * {
 *   "formulario_id": 1,
 *   "tiempo_completado": 300,
 *   "respuestas_curso": {
 *     "1": {"valor": 5},
 *     "2": {"valor": "Excelente curso"}
 *   },
 *   "respuestas_profesores": {
 *     "1": {
 *       "6": {"valor": 4},
 *       "7": {"valor": "Buen profesor"}
 *     }
 *   }
 * }
 */

// Headers de seguridad y CORS
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

// Manejar preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Solo permitir método POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error' => 'Método no permitido. Use POST.',
        'code' => 'METHOD_NOT_ALLOWED'
    ]);
    exit();
}

// Incluir configuración de base de datos
require_once '../config/database.php';

// Configuraciones específicas para procesar encuestas
define('HORAS_LIMITE_SPAM', 24); // Horas entre envíos desde la misma IP
define('MAX_TIEMPO_COMPLETADO', 3600); // Máximo 1 hora para completar encuesta
define('MIN_TIEMPO_COMPLETADO', 30); // Mínimo 30 segundos para completar encuesta
define('MAX_LONGITUD_TEXTO', 500); // Máximo caracteres para respuestas de texto

/**
 * Función para registrar logs de API
 */
function logApiRequest($endpoint, $data, $ip, $userAgent) {
    $logData = [
        'timestamp' => date('Y-m-d H:i:s'),
        'endpoint' => $endpoint,
        'method' => $_SERVER['REQUEST_METHOD'],
        'ip' => $ip,
        'user_agent' => substr($userAgent, 0, 255),
        'data_size' => strlen(json_encode($data))
    ];
    
    if (defined('MODO_DESARROLLO') && MODO_DESARROLLO) {
        error_log('API Request: ' . json_encode($logData));
    }
}

/**
 * Función para validar datos de entrada
 */
function validarDatosEntrada() {
    // Obtener datos JSON
    $input = file_get_contents('php://input');
    if (empty($input)) {
        throw new InvalidArgumentException('No se recibieron datos');
    }
    
    $data = json_decode($input, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new InvalidArgumentException('JSON inválido: ' . json_last_error_msg());
    }
    
    // Validar campos obligatorios
    if (!isset($data['formulario_id']) || !is_numeric($data['formulario_id'])) {
        throw new InvalidArgumentException('formulario_id es obligatorio y debe ser numérico');
    }
    
    $formulario_id = (int)$data['formulario_id'];
    if ($formulario_id <= 0) {
        throw new InvalidArgumentException('formulario_id debe ser positivo');
    }
    
    // Validar tiempo completado
    $tiempo_completado = null;
    if (isset($data['tiempo_completado'])) {
        if (!is_numeric($data['tiempo_completado'])) {
            throw new InvalidArgumentException('tiempo_completado debe ser numérico');
        }
        $tiempo_completado = (int)$data['tiempo_completado'];
        if ($tiempo_completado < MIN_TIEMPO_COMPLETADO || $tiempo_completado > MAX_TIEMPO_COMPLETADO) {
            throw new InvalidArgumentException(
                'tiempo_completado debe estar entre ' . MIN_TIEMPO_COMPLETADO . ' y ' . MAX_TIEMPO_COMPLETADO . ' segundos'
            );
        }
    }
    
    // Validar que se incluyan respuestas
    if (!isset($data['respuestas_curso']) && !isset($data['respuestas_profesores'])) {
        throw new InvalidArgumentException('Debe incluir al menos respuestas_curso o respuestas_profesores');
    }
    
    return [
        'formulario_id' => $formulario_id,
        'tiempo_completado' => $tiempo_completado,
        'respuestas_curso' => $data['respuestas_curso'] ?? [],
        'respuestas_profesores' => $data['respuestas_profesores'] ?? []
    ];
}

/**
 * Función para verificar control anti-spam
 */
function verificarAntiSpam($pdo, $ip) {
    // En modo desarrollo, saltar verificación
    if (defined('MODO_DESARROLLO') && MODO_DESARROLLO) {
        return true;
    }
    
    try {
        $sql = "
            SELECT COUNT(*) as total 
            FROM encuestas 
            WHERE ip_cliente = :ip 
                AND fecha_envio >= DATE_SUB(NOW(), INTERVAL :horas HOUR)
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':ip', $ip, PDO::PARAM_STR);
        $stmt->bindParam(':horas', HORAS_LIMITE_SPAM, PDO::PARAM_INT);
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['total'] == 0;
        
    } catch (PDOException $e) {
        error_log('Error en verificarAntiSpam: ' . $e->getMessage());
        return false;
    }
}

/**
 * Función para obtener información del formulario
 */
function obtenerInfoFormulario($pdo, $formularioId) {
    try {
        $sql = "
            SELECT 
                f.id,
                f.nombre,
                f.curso_id,
                f.activo,
                f.fecha_inicio,
                f.fecha_fin,
                f.permite_respuestas_anonimas,
                c.nombre as curso_nombre,
                c.activo as curso_activo
            FROM formularios f
            INNER JOIN cursos c ON f.curso_id = c.id
            WHERE f.id = :formulario_id
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':formulario_id', $formularioId, PDO::PARAM_INT);
        $stmt->execute();
        
        $formulario = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$formulario) {
            throw new Exception('Formulario no encontrado', 404);
        }
        
        if (!$formulario['activo'] || !$formulario['curso_activo']) {
            throw new Exception('Formulario o curso inactivo', 403);
        }
        
        // Verificar vigencia por fechas
        $hoy = date('Y-m-d');
        if ($formulario['fecha_inicio'] && $hoy < $formulario['fecha_inicio']) {
            throw new Exception('Formulario aún no está disponible', 403);
        }
        if ($formulario['fecha_fin'] && $hoy > $formulario['fecha_fin']) {
            throw new Exception('Formulario ya no está disponible', 403);
        }
        
        return $formulario;
        
    } catch (PDOException $e) {
        error_log('Error en obtenerInfoFormulario: ' . $e->getMessage());
        throw new Exception('Error al verificar formulario');
    }
}

/**
 * Función para obtener preguntas del formulario
 */
function obtenerPreguntasFormulario($pdo) {
    try {
        $sql = "
            SELECT 
                id,
                texto,
                seccion,
                tipo,
                es_obligatoria,
                opciones
            FROM preguntas 
            WHERE activa = TRUE
            ORDER BY seccion, orden
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        
        $preguntas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Organizar por ID para acceso rápido
        $preguntasById = [];
        foreach ($preguntas as $pregunta) {
            $preguntasById[$pregunta['id']] = $pregunta;
        }
        
        return $preguntasById;
        
    } catch (PDOException $e) {
        error_log('Error en obtenerPreguntasFormulario: ' . $e->getMessage());
        throw new Exception('Error al obtener preguntas');
    }
}

/**
 * Función para obtener profesores del formulario
 */
function obtenerProfesoresFormulario($pdo, $formularioId) {
    try {
        $sql = "
            SELECT DISTINCT p.id
            FROM profesores p
            INNER JOIN curso_profesores cp ON p.id = cp.profesor_id
            WHERE cp.formulario_id = :formulario_id 
                AND cp.activo = TRUE 
                AND p.activo = TRUE
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':formulario_id', $formularioId, PDO::PARAM_INT);
        $stmt->execute();
        
        $profesores = $stmt->fetchAll(PDO::FETCH_COLUMN);
        return array_map('intval', $profesores);
        
    } catch (PDOException $e) {
        error_log('Error en obtenerProfesoresFormulario: ' . $e->getMessage());
        throw new Exception('Error al obtener profesores');
    }
}

/**
 * Función para validar una respuesta individual
 */
function validarRespuesta($pregunta, $valor) {
    $errors = [];
    
    // Verificar si es obligatoria
    if ($pregunta['es_obligatoria'] && (is_null($valor) || $valor === '')) {
        $errors[] = "Pregunta obligatoria: {$pregunta['texto']}";
        return $errors;
    }
    
    // Si no hay valor y no es obligatoria, está bien
    if (is_null($valor) || $valor === '') {
        return $errors;
    }
    
    // Validaciones por tipo de pregunta
    switch ($pregunta['tipo']) {        case 'escala':
            if (!is_numeric($valor)) {
                $errors[] = "Pregunta de escala debe ser numérica: {$pregunta['texto']}";
            } else {
                $valorInt = (int)$valor;
                
                // Verificar si la pregunta tiene opciones específicas definidas
                $valoresValidos = [];
                if (!empty($pregunta['opciones'])) {
                    $opciones = json_decode($pregunta['opciones'], true);
                    if (is_array($opciones)) {
                        // Extraer valores válidos de las opciones
                        foreach ($opciones as $opcion) {
                            if (isset($opcion['valor'])) {
                                $valoresValidos[] = (int)$opcion['valor'];
                            }
                        }
                    }
                }
                
                // Si no hay opciones específicas, usar validación tradicional 1-10
                if (empty($valoresValidos)) {
                    $escalaMin = isset($pregunta['escala_min']) ? (int)$pregunta['escala_min'] : 1;
                    $escalaMax = isset($pregunta['escala_max']) ? (int)$pregunta['escala_max'] : 10;
                    
                    if ($valorInt < $escalaMin || $valorInt > $escalaMax) {
                        $errors[] = "Pregunta de escala debe estar entre {$escalaMin} y {$escalaMax}: {$pregunta['texto']}";
                    }
                } else {
                    // Validar contra opciones específicas
                    if (!in_array($valorInt, $valoresValidos)) {
                        $errors[] = "Valor de escala no válido para: {$pregunta['texto']}. Valores permitidos: " . implode(', ', $valoresValidos);
                    }
                }
            }
            break;
            
        case 'texto':
            if (!is_string($valor)) {
                $errors[] = "Pregunta de texto debe ser string: {$pregunta['texto']}";
            } else {
                if (strlen($valor) > MAX_LONGITUD_TEXTO) {
                    $errors[] = "Respuesta de texto muy larga (máximo " . MAX_LONGITUD_TEXTO . " caracteres): {$pregunta['texto']}";
                }
            }
            break;
            
        case 'opcion_multiple':
            if (!is_string($valor)) {
                $errors[] = "Pregunta de opción múltiple debe ser string: {$pregunta['texto']}";
            } else {
                // Validar que la opción seleccionada esté en las opciones válidas
                if (!empty($pregunta['opciones'])) {
                    $opciones = json_decode($pregunta['opciones'], true);
                    if (is_array($opciones) && !in_array($valor, $opciones)) {
                        $errors[] = "Opción no válida para: {$pregunta['texto']}";
                    }
                }
            }
            break;
    }
    
    return $errors;
}

/**
 * Función para validar todas las respuestas
 */
function validarRespuestas($data, $preguntas, $profesoresValidos) {
    $errors = [];
    
    // Validar respuestas de curso
    foreach ($data['respuestas_curso'] as $preguntaId => $respuesta) {
        $preguntaId = (int)$preguntaId;
        
        if (!isset($preguntas[$preguntaId])) {
            $errors[] = "Pregunta de curso no existe: ID $preguntaId";
            continue;
        }
        
        $pregunta = $preguntas[$preguntaId];
        if ($pregunta['seccion'] !== 'curso') {
            $errors[] = "Pregunta ID $preguntaId no es de sección curso";
            continue;
        }
        
        $valor = $respuesta['valor'] ?? null;
        $errors = array_merge($errors, validarRespuesta($pregunta, $valor));
    }
    
    // Validar respuestas de profesores
    foreach ($data['respuestas_profesores'] as $profesorId => $respuestasPorProfesor) {
        $profesorId = (int)$profesorId;
        
        if (!in_array($profesorId, $profesoresValidos)) {
            $errors[] = "Profesor no válido para este formulario: ID $profesorId";
            continue;
        }
        
        foreach ($respuestasPorProfesor as $preguntaId => $respuesta) {
            $preguntaId = (int)$preguntaId;
            
            if (!isset($preguntas[$preguntaId])) {
                $errors[] = "Pregunta de profesor no existe: ID $preguntaId";
                continue;
            }
            
            $pregunta = $preguntas[$preguntaId];
            if ($pregunta['seccion'] !== 'profesor') {
                $errors[] = "Pregunta ID $preguntaId no es de sección profesor";
                continue;
            }
            
            $valor = $respuesta['valor'] ?? null;
            $errors = array_merge($errors, validarRespuesta($pregunta, $valor));
        }
    }
    
    return $errors;
}

/**
 * Función para generar hash de sesión anti-spam
 */
function generarHashSession($ip, $userAgent, $formularioId) {
    return hash('sha256', $ip . '|' . $userAgent . '|' . $formularioId . '|' . date('Y-m-d H'));
}

/**
 * Función para insertar encuesta y respuestas
 */
function insertarEncuesta($pdo, $data, $formulario, $ip, $userAgent) {
    try {
        // Iniciar transacción
        $pdo->beginTransaction();
        
        // Generar hash de sesión
        $hashSession = generarHashSession($ip, $userAgent, $data['formulario_id']);
        
        // Insertar encuesta principal
        $sqlEncuesta = "
            INSERT INTO encuestas (
                curso_id, 
                formulario_id, 
                fecha_envio, 
                ip_cliente, 
                user_agent, 
                tiempo_completado, 
                es_anonima, 
                hash_session
            ) VALUES (
                :curso_id, 
                :formulario_id, 
                NOW(), 
                :ip_cliente, 
                :user_agent, 
                :tiempo_completado, 
                :es_anonima, 
                :hash_session
            )
        ";
        
        $stmtEncuesta = $pdo->prepare($sqlEncuesta);
        $stmtEncuesta->bindParam(':curso_id', $formulario['curso_id'], PDO::PARAM_INT);
        $stmtEncuesta->bindParam(':formulario_id', $data['formulario_id'], PDO::PARAM_INT);
        $stmtEncuesta->bindParam(':ip_cliente', $ip, PDO::PARAM_STR);
        $stmtEncuesta->bindParam(':user_agent', $userAgent, PDO::PARAM_STR);
        $stmtEncuesta->bindParam(':tiempo_completado', $data['tiempo_completado'], PDO::PARAM_INT);
        $stmtEncuesta->bindParam(':es_anonima', $formulario['permite_respuestas_anonimas'], PDO::PARAM_BOOL);
        $stmtEncuesta->bindParam(':hash_session', $hashSession, PDO::PARAM_STR);
        
        $stmtEncuesta->execute();
        $encuestaId = $pdo->lastInsertId();
        
        // Preparar statement para respuestas
        $sqlRespuesta = "
            INSERT INTO respuestas (
                encuesta_id, 
                pregunta_id, 
                profesor_id, 
                valor_int, 
                valor_text, 
                fecha_respuesta
            ) VALUES (
                :encuesta_id, 
                :pregunta_id, 
                :profesor_id, 
                :valor_int, 
                :valor_text, 
                NOW()
            )
        ";
        $stmtRespuesta = $pdo->prepare($sqlRespuesta);
        
        $respuestasInsertadas = 0;
        
        // Insertar respuestas de curso
        foreach ($data['respuestas_curso'] as $preguntaId => $respuesta) {
            $valor = $respuesta['valor'] ?? null;
            if ($valor === null || $valor === '') continue;
            
            $valorInt = null;
            $valorText = null;
            
            if (is_numeric($valor)) {
                $valorInt = (int)$valor;
            } else {
                $valorText = substr($valor, 0, MAX_LONGITUD_TEXTO);
            }
            
            $stmtRespuesta->bindParam(':encuesta_id', $encuestaId, PDO::PARAM_INT);
            $stmtRespuesta->bindParam(':pregunta_id', $preguntaId, PDO::PARAM_INT);
            $stmtRespuesta->bindValue(':profesor_id', null, PDO::PARAM_NULL);
            $stmtRespuesta->bindParam(':valor_int', $valorInt, PDO::PARAM_INT);
            $stmtRespuesta->bindParam(':valor_text', $valorText, PDO::PARAM_STR);
            
            $stmtRespuesta->execute();
            $respuestasInsertadas++;
        }
        
        // Insertar respuestas de profesores
        foreach ($data['respuestas_profesores'] as $profesorId => $respuestasPorProfesor) {
            foreach ($respuestasPorProfesor as $preguntaId => $respuesta) {
                $valor = $respuesta['valor'] ?? null;
                if ($valor === null || $valor === '') continue;
                
                $valorInt = null;
                $valorText = null;
                
                if (is_numeric($valor)) {
                    $valorInt = (int)$valor;
                } else {
                    $valorText = substr($valor, 0, MAX_LONGITUD_TEXTO);
                }
                
                $stmtRespuesta->bindParam(':encuesta_id', $encuestaId, PDO::PARAM_INT);
                $stmtRespuesta->bindParam(':pregunta_id', $preguntaId, PDO::PARAM_INT);
                $stmtRespuesta->bindParam(':profesor_id', $profesorId, PDO::PARAM_INT);
                $stmtRespuesta->bindParam(':valor_int', $valorInt, PDO::PARAM_INT);
                $stmtRespuesta->bindParam(':valor_text', $valorText, PDO::PARAM_STR);
                
                $stmtRespuesta->execute();
                $respuestasInsertadas++;
            }
        }
        
        // Confirmar transacción
        $pdo->commit();
        
        return [
            'encuesta_id' => $encuestaId,
            'respuestas_insertadas' => $respuestasInsertadas,
            'hash_session' => $hashSession
        ];
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log('Error en insertarEncuesta: ' . $e->getMessage());
        throw new Exception('Error al guardar encuesta');
    }
}

/**
 * Función principal de procesamiento
 */
function procesarSolicitud() {
    try {
        // Obtener información de la solicitud
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        
        // Validar datos de entrada
        $data = validarDatosEntrada();
        
        // Registrar la solicitud
        logApiRequest('procesar_encuesta', $data, $ip, $userAgent);
        
        // Conectar a la base de datos
        $pdo = getConnection();
        
        // Verificar anti-spam
        if (!verificarAntiSpam($pdo, $ip)) {
            throw new Exception('Debe esperar ' . HORAS_LIMITE_SPAM . ' horas entre envíos', 429);
        }
        
        // Obtener información del formulario
        $formulario = obtenerInfoFormulario($pdo, $data['formulario_id']);
        
        // Obtener preguntas y profesores válidos
        $preguntas = obtenerPreguntasFormulario($pdo);
        $profesoresValidos = obtenerProfesoresFormulario($pdo, $data['formulario_id']);
        
        // Validar respuestas
        $errorsValidacion = validarRespuestas($data, $preguntas, $profesoresValidos);
        if (!empty($errorsValidacion)) {
            throw new InvalidArgumentException('Errores de validación: ' . implode(', ', $errorsValidacion));
        }
        
        // Insertar encuesta y respuestas
        $resultado = insertarEncuesta($pdo, $data, $formulario, $ip, $userAgent);
        
        // Preparar respuesta exitosa
        $response = [
            'success' => true,
            'message' => 'Encuesta procesada exitosamente',
            'data' => [
                'encuesta_id' => $resultado['encuesta_id'],
                'respuestas_guardadas' => $resultado['respuestas_insertadas'],
                'formulario' => $formulario['nombre'],
                'curso' => $formulario['curso_nombre']
            ],
            'metadata' => [
                'timestamp' => date('Y-m-d H:i:s'),
                'tiempo_completado' => $data['tiempo_completado'],
                'hash_session' => substr($resultado['hash_session'], 0, 16) . '...' // Solo mostrar parte del hash
            ]
        ];
        
        // Enviar respuesta
        http_response_code(201);
        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        
    } catch (InvalidArgumentException $e) {
        // Error de validación
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage(),
            'code' => 'VALIDATION_ERROR',
            'timestamp' => date('Y-m-d H:i:s')
        ], JSON_UNESCAPED_UNICODE);
        
    } catch (Exception $e) {
        // Otros errores
        $httpCode = 500;
        $errorCode = 'INTERNAL_ERROR';
        
        if ($e->getCode() === 404) {
            $httpCode = 404;
            $errorCode = 'NOT_FOUND';
        } elseif ($e->getCode() === 403) {
            $httpCode = 403;
            $errorCode = 'FORBIDDEN';
        } elseif ($e->getCode() === 429) {
            $httpCode = 429;
            $errorCode = 'TOO_MANY_REQUESTS';
        }
        
        if ($httpCode === 500) {
            $error_id = uniqid('err_');
            error_log("Error en procesar_encuesta.php [{$error_id}]: " . $e->getMessage());
            $response_error = 'Error interno del servidor';
            $response_data = ['error_id' => $error_id];
        } else {
            $response_error = $e->getMessage();
            $response_data = [];
        }
        
        http_response_code($httpCode);
        echo json_encode(array_merge([
            'success' => false,
            'error' => $response_error,
            'code' => $errorCode,
            'timestamp' => date('Y-m-d H:i:s')
        ], $response_data), JSON_UNESCAPED_UNICODE);
    }
}

/**
 * Rate limiting avanzado para envíos de encuesta
 */
function checkRateLimit($ip) {
    $maxRequests = 10; // máximo 10 envíos por hora por IP
    $timeWindow = 3600; // 1 hora en segundos
    
    // En desarrollo, permitir más requests
    if (defined('MODO_DESARROLLO') && MODO_DESARROLLO) {
        $maxRequests = 50;
    }
    
    // Implementación simple con archivos (en producción usar Redis/Memcached)
    $rateLimitFile = sys_get_temp_dir() . '/rate_limit_encuestas_' . md5($ip) . '.txt';
    
    $currentTime = time();
    $requests = [];
    
    // Leer requests previos
    if (file_exists($rateLimitFile)) {
        $data = file_get_contents($rateLimitFile);
        $requests = $data ? explode(',', $data) : [];
    }
    
    // Filtrar requests dentro de la ventana de tiempo
    $requests = array_filter($requests, function($timestamp) use ($currentTime, $timeWindow) {
        return ($currentTime - (int)$timestamp) < $timeWindow;
    });
    
    // Verificar límite
    if (count($requests) >= $maxRequests) {
        return false;
    }
    
    // Agregar request actual
    $requests[] = $currentTime;
    
    // Guardar requests actualizados
    file_put_contents($rateLimitFile, implode(',', $requests));
    
    return true;
}

// ===================================
// EJECUCIÓN PRINCIPAL
// ===================================

try {
    // Verificar rate limiting
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    if (!checkRateLimit($ip)) {
        http_response_code(429);
        echo json_encode([
            'success' => false,
            'error' => 'Límite de envíos excedido. Intente más tarde.',
            'code' => 'RATE_LIMIT_EXCEEDED',
            'retry_after' => 3600
        ], JSON_UNESCAPED_UNICODE);
        exit();
    }
    
    // Procesar solicitud
    procesarSolicitud();
    
} catch (InvalidArgumentException $e) {
    // Error de validación de datos
    error_log('Error de validación en procesar_encuesta.php: ' . $e->getMessage());
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'code' => 'VALIDATION_ERROR'
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    // Error general
    error_log('Error en procesar_encuesta.php: ' . $e->getMessage());
    
    $httpCode = $e->getCode() ?: 500;
    http_response_code($httpCode);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'code' => 'GENERAL_ERROR'
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    // Error crítico no manejado
    error_log('Error crítico en procesar_encuesta.php: ' . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error crítico del sistema',
        'code' => 'CRITICAL_ERROR',
        'debug_message' => $e->getMessage(),
        'debug_file' => $e->getFile(),
        'debug_line' => $e->getLine()
    ], JSON_UNESCAPED_UNICODE);
}
?>
