<?php
/**
 * API: Obtener Cursos Activos con Formularios Disponibles
 * 
 * @file get_cursos.php
 * @description Endpoint para obtener la lista de cursos activos que tienen formularios disponibles
 * @method GET
 * @version 1.0
 * @author Sistema de Encuestas Académicas
 * @date 11 de junio de 2025
 * 
 * Parámetros opcionales:
 * - include_stats: boolean - Incluir estadísticas básicas del curso
 * - fecha_referencia: date - Fecha para validar formularios vigentes (formato Y-m-d)
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
require_once __DIR__ . '/../config/database.php';

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
    
    // Parámetro include_stats (boolean)
    $params['include_stats'] = false;
    if (isset($_GET['include_stats'])) {
        $include_stats = filter_var($_GET['include_stats'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($include_stats !== null) {
            $params['include_stats'] = $include_stats;
        }
    }
    
    // Parámetro fecha_referencia (date)
    $params['fecha_referencia'] = date('Y-m-d');
    if (isset($_GET['fecha_referencia']) && !empty($_GET['fecha_referencia'])) {
        $fecha = DateTime::createFromFormat('Y-m-d', $_GET['fecha_referencia']);
        if ($fecha && $fecha->format('Y-m-d') === $_GET['fecha_referencia']) {
            $params['fecha_referencia'] = $_GET['fecha_referencia'];
        }
    }
    
    return $params;
}

/**
 * Función para obtener cursos con formularios activos
 */
function getCursosConFormularios($pdo, $params) {
    try {
        // Query base para cursos con formularios activos
        $sql = "
            SELECT DISTINCT
                c.id,
                c.nombre,
                c.descripcion,
                c.codigo,
                c.creditos,
                c.activo,
                c.fecha_creacion,
                COUNT(DISTINCT f.id) as total_formularios,
                COUNT(DISTINCT cp.profesor_id) as total_profesores
        ";
        
        // Si se solicitan estadísticas, agregar campos adicionales
        if ($params['include_stats']) {
            $sql .= ",
                COUNT(DISTINCT e.id) as total_encuestas_completadas,
                ROUND(AVG(CASE WHEN p.seccion = 'curso' AND r.valor_int IS NOT NULL THEN r.valor_int END), 2) as promedio_evaluacion,
                MAX(e.fecha_envio) as ultima_evaluacion
            ";
        }
        
        $sql .= "
            FROM cursos c
            INNER JOIN formularios f ON c.id = f.curso_id 
                AND f.activo = TRUE
                AND (f.fecha_inicio IS NULL OR f.fecha_inicio <= :fecha_referencia)
                AND (f.fecha_fin IS NULL OR f.fecha_fin >= :fecha_referencia)
            LEFT JOIN curso_profesores cp ON f.id = cp.formulario_id AND cp.activo = TRUE
        ";
        
        // Joins adicionales para estadísticas
        if ($params['include_stats']) {
            $sql .= "
                LEFT JOIN encuestas e ON f.id = e.formulario_id
                LEFT JOIN respuestas r ON e.id = r.encuesta_id
                LEFT JOIN preguntas p ON r.pregunta_id = p.id AND p.activa = TRUE
            ";
        }
        
        $sql .= "
            WHERE c.activo = TRUE
            GROUP BY c.id, c.nombre, c.descripcion, c.codigo, c.creditos, c.activo, c.fecha_creacion
            ORDER BY c.nombre ASC
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':fecha_referencia', $params['fecha_referencia'], PDO::PARAM_STR);
        $stmt->execute();
        
        $cursos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Procesar resultados
        foreach ($cursos as &$curso) {
            // Convertir campos numéricos
            $curso['id'] = (int)$curso['id'];
            $curso['creditos'] = (int)$curso['creditos'];
            $curso['activo'] = (bool)$curso['activo'];
            $curso['total_formularios'] = (int)$curso['total_formularios'];
            $curso['total_profesores'] = (int)$curso['total_profesores'];
            
            if ($params['include_stats']) {
                $curso['total_encuestas_completadas'] = (int)$curso['total_encuestas_completadas'];
                $curso['promedio_evaluacion'] = $curso['promedio_evaluacion'] ? (float)$curso['promedio_evaluacion'] : null;
            }
            
            // Obtener formularios disponibles para este curso
            $curso['formularios_disponibles'] = getFormulariosDisponibles($pdo, $curso['id'], $params['fecha_referencia']);
        }
        
        return $cursos;
        
    } catch (PDOException $e) {
        error_log('Error en getCursosConFormularios: ' . $e->getMessage());
        throw new Exception('Error al consultar cursos');
    }
}

/**
 * Función para obtener formularios disponibles de un curso
 */
function getFormulariosDisponibles($pdo, $cursoId, $fechaReferencia) {
    try {
        $sql = "
            SELECT 
                f.id,
                f.nombre,
                f.descripcion,
                f.fecha_inicio,
                f.fecha_fin,
                f.permite_respuestas_anonimas,
                COUNT(DISTINCT cp.profesor_id) as total_profesores
            FROM formularios f
            LEFT JOIN curso_profesores cp ON f.id = cp.formulario_id AND cp.activo = TRUE
            WHERE f.curso_id = :curso_id 
                AND f.activo = TRUE
                AND (f.fecha_inicio IS NULL OR f.fecha_inicio <= :fecha_referencia)
                AND (f.fecha_fin IS NULL OR f.fecha_fin >= :fecha_referencia)
            GROUP BY f.id, f.nombre, f.descripcion, f.fecha_inicio, f.fecha_fin, f.permite_respuestas_anonimas
            ORDER BY f.nombre ASC
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':curso_id', $cursoId, PDO::PARAM_INT);
        $stmt->bindParam(':fecha_referencia', $fechaReferencia, PDO::PARAM_STR);
        $stmt->execute();
        
        $formularios = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Procesar resultados
        foreach ($formularios as &$formulario) {
            $formulario['id'] = (int)$formulario['id'];
            $formulario['permite_respuestas_anonimas'] = (bool)$formulario['permite_respuestas_anonimas'];
            $formulario['total_profesores'] = (int)$formulario['total_profesores'];
        }
        
        return $formularios;
        
    } catch (PDOException $e) {
        error_log('Error en getFormulariosDisponibles: ' . $e->getMessage());
        return [];
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
        
        // Validar y sanitizar parámetros
        $params = sanitizeParams();
        
        // Registrar la solicitud
        logApiRequest('get_cursos', $params, $ip, $userAgent);
        
        // Conectar a la base de datos
        $pdo = getConnection();
        
        // Obtener cursos
        $cursos = getCursosConFormularios($pdo, $params);
        
        // Preparar respuesta exitosa
        $response = [
            'success' => true,
            'data' => $cursos,
            'metadata' => [
                'total_cursos' => count($cursos),
                'fecha_referencia' => $params['fecha_referencia'],
                'include_stats' => $params['include_stats'],
                'timestamp' => date('Y-m-d H:i:s')
            ]
        ];
        
        // Enviar respuesta
        http_response_code(200);
        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        
    } catch (Exception $e) {
        // Error en el procesamiento
        $error_id = uniqid('err_');
        error_log("Error en get_cursos.php [{$error_id}]: " . $e->getMessage());
        
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
    $maxRequests = 100; // máximo 100 requests por hora por IP
    $timeWindow = 3600; // 1 hora en segundos
    
    // En desarrollo, no aplicar rate limiting
    if (defined('MODO_DESARROLLO') && MODO_DESARROLLO) {
        return true;
    }
    
    // Implementación simple con archivos (en producción usar Redis/Memcached)
    $rateLimitFile = sys_get_temp_dir() . '/rate_limit_' . md5($ip) . '.txt';
    
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
    error_log('Error crítico en get_cursos.php: ' . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error crítico del sistema',
        'code' => 'CRITICAL_ERROR'
    ], JSON_UNESCAPED_UNICODE);
}
?>
