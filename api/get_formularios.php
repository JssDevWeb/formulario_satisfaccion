<?php
/**
 * API: Obtener Formularios Disponibles
 * Versión simplificada
 */

// Headers de respuesta
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Manejar preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Solo permitir GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Método no permitido. Solo se permite GET.',
        'code' => 'METHOD_NOT_ALLOWED'
    ]);
    exit();
}

// Incluir configuración y funciones comunes
require_once '../config/database.php';
require_once 'common.php';

try {
    // Obtener IP del cliente
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    
    // Verificar rate limiting
    if (!checkRateLimit($ip, 100)) {
        sendJsonResponse(false, null, 'Límite de peticiones excedido. Intente más tarde.', 429);
    }
    
    // Obtener parámetros
    $curso_id = isset($_GET['curso_id']) ? sanitizeInput($_GET['curso_id'], 'int') : null;
    
    // Conectar a la base de datos
    $db = Database::getInstance();
    $pdo = $db->getConnection();
    
    // Construir consulta SQL
    $sql = "
        SELECT 
            f.id,
            f.descripcion,
            f.fecha_inicio,
            f.fecha_fin,
            f.activo,
            c.id as curso_id,
            c.nombre as curso_nombre,
            c.codigo as curso_codigo,
            CASE 
                WHEN NOW() BETWEEN f.fecha_inicio AND f.fecha_fin AND f.activo = 1 
                THEN 1 
                ELSE 0 
            END as esta_vigente,
            CASE 
                WHEN f.fecha_inicio > NOW() THEN 'pendiente'
                WHEN f.fecha_fin < NOW() THEN 'finalizado'
                WHEN f.activo = 0 THEN 'inactivo'
                ELSE 'activo'
            END as estado
        FROM formularios f
        INNER JOIN cursos c ON f.curso_id = c.id
        WHERE c.activo = 1
    ";
    
    $params = [];
    
    // Filtrar por curso si se especifica
    if ($curso_id) {
        $sql .= " AND c.id = :curso_id";
        $params[':curso_id'] = $curso_id;
    }
    
    // Solo mostrar formularios vigentes por defecto
    $sql .= " AND NOW() BETWEEN f.fecha_inicio AND f.fecha_fin AND f.activo = 1";
    
    $sql .= " ORDER BY c.nombre, f.descripcion";
    
    // Ejecutar consulta
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $formularios = $stmt->fetchAll(PDO::FETCH_ASSOC);
      // Formatear fechas
    foreach ($formularios as &$formulario) {
        $formulario['fecha_inicio'] = date('Y-m-d', strtotime($formulario['fecha_inicio']));
        $formulario['fecha_fin'] = date('Y-m-d', strtotime($formulario['fecha_fin']));
        $formulario['esta_vigente'] = (bool)$formulario['esta_vigente'];
        $formulario['activo'] = (bool)$formulario['activo'];
        
        // Agregar campos compatibles con el frontend
        $formulario['valido_desde'] = $formulario['fecha_inicio'];
        $formulario['valido_hasta'] = $formulario['fecha_fin'];
    }
    
    // Log de la petición
    logApiRequest('get_formularios', 'GET', $_GET, 200, $ip);
    
    // Enviar respuesta
    sendJsonResponse(true, $formularios, 'Formularios obtenidos exitosamente', 200, [
        'total' => count($formularios),
        'filtered_by_curso' => $curso_id ? true : false
    ]);
    
} catch (Exception $e) {
    // Log del error
    error_log("Error en get_formularios.php: " . $e->getMessage());
    
    // Respuesta de error
    sendJsonResponse(false, null, 'Error interno del servidor', 500);
}
?>
