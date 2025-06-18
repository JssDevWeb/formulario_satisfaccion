<?php
/**
 * API: Obtener Profesores por Curso
 * 
 * @file get_profesores_por_curso.php
 * @description Endpoint para obtener profesores que enseñan un curso específico
 * @method GET
 * @version 1.0
 * @param curso_id - ID del curso
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

// Incluir configuración
require_once '../config/database.php';

try {
    // Obtener parámetro curso_id
    $curso_id = isset($_GET['curso_id']) ? (int)$_GET['curso_id'] : null;
    
    if (!$curso_id) {
        echo json_encode([
            'success' => false,
            'message' => 'Parámetro curso_id es requerido',
            'data' => []
        ]);
        exit();
    }
    
    // Conectar a la base de datos
    $db = Database::getInstance();
    $pdo = $db->getConnection();
    
    // Obtener profesores que enseñan el curso especificado
    $sql = "
        SELECT DISTINCT 
            p.id,
            p.nombre,
            p.especialidad,
            p.email
        FROM profesores p
        INNER JOIN curso_profesores cp ON p.id = cp.profesor_id
        INNER JOIN formularios f ON cp.formulario_id = f.id
        INNER JOIN cursos c ON f.curso_id = c.id
        WHERE c.id = :curso_id 
        AND p.activo = 1 
        AND f.activo = 1
        AND cp.activo = 1
        ORDER BY p.nombre ASC
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':curso_id' => $curso_id]);
    $profesores = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Respuesta exitosa
    echo json_encode([
        'success' => true,
        'message' => 'Profesores obtenidos exitosamente',
        'data' => $profesores,
        'total' => count($profesores)
    ]);
    
} catch (Exception $e) {
    // Log del error
    error_log("Error en get_profesores_por_curso.php: " . $e->getMessage());
    
    // Respuesta de error
    echo json_encode([
        'success' => false,
        'message' => 'Error interno del servidor',
        'data' => []
    ]);
}
?>
