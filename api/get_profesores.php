<?php
/**
 * API: Obtener Profesores por Formulario
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
    
    // Obtener parámetros requeridos
    $formulario_id = isset($_GET['formulario_id']) ? sanitizeInput($_GET['formulario_id'], 'int') : null;
    
    if (!$formulario_id) {
        sendJsonResponse(false, null, 'Parámetro formulario_id es requerido', 400);
    }
    
    // Conectar a la base de datos
    $db = Database::getInstance();
    $pdo = $db->getConnection();
    
    // Verificar que el formulario existe y está activo
    $sql_formulario = "
        SELECT f.id, f.descripcion, f.activo 
        FROM formularios f 
        WHERE f.id = :formulario_id AND f.activo = 1
    ";
    
    $stmt = $pdo->prepare($sql_formulario);
    $stmt->execute([':formulario_id' => $formulario_id]);
    $formulario = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$formulario) {
        sendJsonResponse(false, null, 'Formulario no encontrado o no está activo', 404);
    }
    
    // Obtener profesores asignados al formulario
    $sql = "
        SELECT 
            p.id,
            p.nombre,
            p.especialidad,
            p.email,
            cp.orden,
            cp.activo as asignacion_activa
        FROM profesores p
        INNER JOIN curso_profesores cp ON p.id = cp.profesor_id
        WHERE cp.formulario_id = :formulario_id 
        AND p.activo = 1 
        AND cp.activo = 1
        ORDER BY cp.orden ASC, p.nombre ASC
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':formulario_id' => $formulario_id]);
    $profesores = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Formatear datos
    foreach ($profesores as &$profesor) {
        $profesor['asignacion_activa'] = (bool)$profesor['asignacion_activa'];
        $profesor['orden'] = (int)$profesor['orden'];
    }
    
    // Log de la petición
    logApiRequest('get_profesores', 'GET', $_GET, 200, $ip);
    
    // Enviar respuesta
    sendJsonResponse(true, $profesores, 'Profesores obtenidos exitosamente', 200, [
        'total' => count($profesores),
        'formulario_id' => $formulario_id,
        'formulario_descripcion' => $formulario['descripcion']
    ]);
    
} catch (Exception $e) {
    // Log del error
    error_log("Error en get_profesores.php: " . $e->getMessage());
    
    // Respuesta de error
    sendJsonResponse(false, null, 'Error interno del servidor', 500);
}
?>
