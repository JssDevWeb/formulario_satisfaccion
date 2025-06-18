<?php
/**
 * API: Obtener Preguntas por Sección
 * 
 * @file get_preguntas.php
 * @description Endpoint para obtener preguntas activas organizadas por sección (curso/profesor)
 * @method GET
 * @version 1.0
 * @author Sistema de Encuestas Académicas
 * @date 11 de junio de 2025
 * 
 * Parámetros opcionales:
 * - seccion: string - 'curso', 'profesor', o 'todas' (default: 'todas')
 * - tipo: string - 'escala', 'texto', 'opcion_multiple', o 'todos' (default: 'todos')
 * - solo_obligatorias: boolean - Solo preguntas obligatorias (default: false)
 * - incluir_opciones: boolean - Incluir opciones para preguntas de opción múltiple (default: true)
 */

// Headers de seguridad y CORS
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

// Manejar preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Solo permitir método GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error' => 'Método no permitido. Use GET.',
        'code' => 'METHOD_NOT_ALLOWED'
    ]);
    exit();
}

// Incluir configuración de base de datos
require_once '../config/database.php';

/**
 * Función para registrar logs de API
 */
function logApiRequest($endpoint, $params, $ip, $userAgent) {
    $logData = [
        'timestamp' => date('Y-m-d H:i:s'),
        'endpoint' => $endpoint,
        'method' => $_SERVER['REQUEST_METHOD'],
        'ip' => $ip,
        'user_agent' => substr($userAgent, 0, 255),
        'params' => $params
    ];
    
    if (defined('MODO_DESARROLLO') && MODO_DESARROLLO) {
        error_log('API Request: ' . json_encode($logData));
    }
}

/**
 * Función para validar y sanitizar parámetros
 */
function sanitizeParams() {
    $params = [];
    $errors = [];
    
    // Parámetro seccion
    $secciones_validas = ['curso', 'profesor', 'todas'];
    $seccion = $_GET['seccion'] ?? 'todas';
    $seccion = strtolower(trim($seccion));
    
    if (!in_array($seccion, $secciones_validas)) {
        $errors[] = 'seccion debe ser: ' . implode(', ', $secciones_validas);
    }
    $params['seccion'] = $seccion;
    
    // Parámetro tipo
    $tipos_validos = ['escala', 'texto', 'opcion_multiple', 'todos'];
    $tipo = $_GET['tipo'] ?? 'todos';
    $tipo = strtolower(trim($tipo));
    
    if (!in_array($tipo, $tipos_validos)) {
        $errors[] = 'tipo debe ser: ' . implode(', ', $tipos_validos);
    }
    $params['tipo'] = $tipo;
    
    // Parámetro solo_obligatorias (boolean)
    $params['solo_obligatorias'] = false;
    if (isset($_GET['solo_obligatorias'])) {
        $solo_obligatorias = filter_var($_GET['solo_obligatorias'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($solo_obligatorias !== null) {
            $params['solo_obligatorias'] = $solo_obligatorias;
        }
    }
    
    // Parámetro incluir_opciones (boolean)
    $params['incluir_opciones'] = true;
    if (isset($_GET['incluir_opciones'])) {
        $incluir_opciones = filter_var($_GET['incluir_opciones'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($incluir_opciones !== null) {
            $params['incluir_opciones'] = $incluir_opciones;
        }
    }
    
    if (!empty($errors)) {
        throw new InvalidArgumentException(implode(', ', $errors));
    }
    
    return $params;
}

/**
 * Función para obtener preguntas según los filtros
 */
function getPreguntasFiltradas($pdo, $params) {
    try {
        $sql = "
            SELECT 
                id,
                texto,
                seccion,
                tipo,
                opciones,
                orden,
                es_obligatoria,
                activa,
                fecha_creacion,
                fecha_modificacion
            FROM preguntas 
            WHERE activa = TRUE
        ";
        
        $sqlParams = [];
        
        // Filtro por sección
        if ($params['seccion'] !== 'todas') {
            $sql .= " AND seccion = :seccion";
            $sqlParams[':seccion'] = $params['seccion'];
        }
        
        // Filtro por tipo
        if ($params['tipo'] !== 'todos') {
            $sql .= " AND tipo = :tipo";
            $sqlParams[':tipo'] = $params['tipo'];
        }
        
        // Filtro solo obligatorias
        if ($params['solo_obligatorias']) {
            $sql .= " AND es_obligatoria = TRUE";
        }
        
        $sql .= " ORDER BY seccion ASC, orden ASC, id ASC";
        
        $stmt = $pdo->prepare($sql);
        
        // Bind parameters
        foreach ($sqlParams as $param => $value) {
            $stmt->bindValue($param, $value, PDO::PARAM_STR);
        }
        
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        error_log('Error en getPreguntasFiltradas: ' . $e->getMessage());
        throw new Exception('Error al consultar preguntas');
    }
}

/**
 * Función para procesar y organizar las preguntas
 */
function procesarPreguntas($preguntas, $incluirOpciones) {
    $resultado = [
        'curso' => [],
        'profesor' => []
    ];
    
    foreach ($preguntas as $pregunta) {
        // Convertir campos numéricos y booleanos
        $pregunta['id'] = (int)$pregunta['id'];
        $pregunta['orden'] = (int)$pregunta['orden'];
        $pregunta['es_obligatoria'] = (bool)$pregunta['es_obligatoria'];
        $pregunta['activa'] = (bool)$pregunta['activa'];
          // Procesar opciones para preguntas de opción múltiple
        if ($pregunta['tipo'] === 'opcion_multiple' && $incluirOpciones && !empty($pregunta['opciones'])) {
            $opciones = json_decode($pregunta['opciones'], true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($opciones)) {
                $pregunta['opciones_array'] = $opciones;
            } else {
                $pregunta['opciones_array'] = [];
            }
        }
        // Procesar opciones para preguntas de escala
        elseif ($pregunta['tipo'] === 'escala' && $incluirOpciones && !empty($pregunta['opciones'])) {
            $opciones = json_decode($pregunta['opciones'], true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($opciones)) {
                $pregunta['opciones_array'] = $opciones;
            } else {
                $pregunta['opciones_array'] = [];
            }
        } else {
            $pregunta['opciones_array'] = null;
        }
        
        // Remover el campo opciones JSON original si no se necesita
        if (!$incluirOpciones || ($pregunta['tipo'] !== 'opcion_multiple' && $pregunta['tipo'] !== 'escala')) {
            unset($pregunta['opciones']);
        }
        
        // Agregar información sobre el tipo de respuesta esperada
        $pregunta['respuesta_info'] = getRespuestaInfo($pregunta['tipo']);
        
        // Organizar por sección
        $seccion = $pregunta['seccion'];
        $resultado[$seccion][] = $pregunta;
    }
    
    return $resultado;
}

/**
 * Función para obtener información sobre el tipo de respuesta
 */
function getRespuestaInfo($tipo) {
    $info = [
        'escala' => [
            'tipo_valor' => 'entero',
            'rango_min' => 1,
            'rango_max' => 5,
            'descripcion' => 'Escala del 1 al 5 donde 1 es muy malo y 5 es excelente',
            'campo_bd' => 'valor_int'
        ],
        'texto' => [
            'tipo_valor' => 'texto',
            'longitud_max' => 1000,
            'descripcion' => 'Respuesta en texto libre',
            'campo_bd' => 'valor_text'
        ],
        'opcion_multiple' => [
            'tipo_valor' => 'seleccion',
            'descripcion' => 'Selección entre opciones predefinidas',
            'campo_bd' => 'valor_text'
        ]
    ];
    
    return $info[$tipo] ?? null;
}

/**
 * Función para generar estadísticas de las preguntas
 */
function generarEstadisticas($preguntas) {
    $stats = [
        'total_preguntas' => 0,
        'por_seccion' => [
            'curso' => 0,
            'profesor' => 0
        ],
        'por_tipo' => [
            'escala' => 0,
            'texto' => 0,
            'opcion_multiple' => 0
        ],
        'obligatorias' => 0,
        'opcionales' => 0
    ];
    
    foreach ($preguntas as $seccion => $lista_preguntas) {
        $stats['por_seccion'][$seccion] = count($lista_preguntas);
        $stats['total_preguntas'] += count($lista_preguntas);
        
        foreach ($lista_preguntas as $pregunta) {
            $stats['por_tipo'][$pregunta['tipo']]++;
            
            if ($pregunta['es_obligatoria']) {
                $stats['obligatorias']++;
            } else {
                $stats['opcionales']++;
            }
        }
    }
    
    return $stats;
}

/**
 * Función principal de procesamiento
 */
function procesarSolicitud() {
    try {
        // Obtener información de la solicitud
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        
        // Validar y sanitizar parámetros
        $params = sanitizeParams();
        
        // Registrar la solicitud
        logApiRequest('get_preguntas', $params, $ip, $userAgent);
        
        // Conectar a la base de datos
        $pdo = getConnection();
        
        // Obtener preguntas
        $preguntas_raw = getPreguntasFiltradas($pdo, $params);
        
        // Procesar y organizar preguntas
        $preguntas = procesarPreguntas($preguntas_raw, $params['incluir_opciones']);
        
        // Generar estadísticas
        $estadisticas = generarEstadisticas($preguntas);
          // Preparar datos de respuesta
        $responseData = $preguntas;
        
        // Si se solicita una sección específica, devolver solo esa sección como array plano
        if ($params['seccion'] !== 'todas') {
            $responseData = $preguntas[$params['seccion']] ?? [];
        }
        
        // Preparar respuesta exitosa
        $response = [
            'success' => true,
            'data' => $responseData,
            'estadisticas' => $estadisticas,
            'filtros_aplicados' => [
                'seccion' => $params['seccion'],
                'tipo' => $params['tipo'],
                'solo_obligatorias' => $params['solo_obligatorias'],
                'incluir_opciones' => $params['incluir_opciones']
            ],
            'metadata' => [
                'timestamp' => date('Y-m-d H:i:s'),
                'total_resultados' => count($preguntas_raw)
            ]
        ];
        
        // Enviar respuesta
        http_response_code(200);
        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        
    } catch (InvalidArgumentException $e) {
        // Error de validación de parámetros
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage(),
            'code' => 'INVALID_PARAMETERS',
            'timestamp' => date('Y-m-d H:i:s')
        ], JSON_UNESCAPED_UNICODE);
        
    } catch (Exception $e) {
        // Error en el procesamiento
        $error_id = uniqid('err_');
        error_log("Error en get_preguntas.php [{$error_id}]: " . $e->getMessage());
        
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Error interno del servidor',
            'code' => 'INTERNAL_ERROR',
            'error_id' => $error_id,
            'timestamp' => date('Y-m-d H:i:s')
        ], JSON_UNESCAPED_UNICODE);
    }
}

/**
 * Rate limiting básico (por IP)
 */
function checkRateLimit($ip) {
    $maxRequests = 150; // máximo 150 requests por hora por IP
    $timeWindow = 3600; // 1 hora en segundos
    
    // En desarrollo, no aplicar rate limiting
    if (defined('MODO_DESARROLLO') && MODO_DESARROLLO) {
        return true;
    }
    
    // Implementación simple con archivos (en producción usar Redis/Memcached)
    $rateLimitFile = sys_get_temp_dir() . '/rate_limit_preguntas_' . md5($ip) . '.txt';
    
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
            'error' => 'Límite de solicitudes excedido. Intente más tarde.',
            'code' => 'RATE_LIMIT_EXCEEDED',
            'retry_after' => 3600
        ], JSON_UNESCAPED_UNICODE);
        exit();
    }
    
    // Procesar solicitud
    procesarSolicitud();
    
} catch (Throwable $e) {
    // Error crítico no manejado
    error_log('Error crítico en get_preguntas.php: ' . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error crítico del sistema',
        'code' => 'CRITICAL_ERROR'
    ], JSON_UNESCAPED_UNICODE);
}
?>
