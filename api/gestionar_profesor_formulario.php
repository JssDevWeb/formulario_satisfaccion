<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Manejar preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Solo permitir POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Método no permitido. Solo se permite POST.'
    ]);
    exit();
}

// Incluir la configuración de la base de datos
require_once '../config/database.php';

try {
    // Obtener el cuerpo de la petición
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);
    
    if (!$data) {
        throw new Exception("Datos JSON no válidos");
    }
    
    // Validar parámetros requeridos
    if (!isset($data['formulario_id']) || !isset($data['profesor_id']) || !isset($data['accion'])) {
        throw new Exception("Parámetros requeridos: formulario_id, profesor_id, accion");
    }
    
    $formulario_id = (int)$data['formulario_id'];
    $profesor_id = (int)$data['profesor_id'];
    $accion = $data['accion']; // 'asignar' o 'desasignar'
    
    if (!in_array($accion, ['asignar', 'desasignar'])) {
        throw new Exception("Acción no válida. Use 'asignar' o 'desasignar'");
    }
      // Crear conexión a la base de datos
    $database = Database::getInstance();
    $db = $database->getConnection();
    
    if (!$db) {
        throw new Exception("Error de conexión a la base de datos");
    }
    
    // Verificar que el formulario existe
    $query = "SELECT id, nombre FROM formularios WHERE id = ? AND activo = 1";
    $stmt = $db->prepare($query);
    $stmt->execute([$formulario_id]);
    $formulario = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$formulario) {
        throw new Exception("Formulario no encontrado o inactivo");
    }
    
    // Verificar que el profesor existe
    $query = "SELECT id, nombre FROM profesores WHERE id = ? AND activo = 1";
    $stmt = $db->prepare($query);
    $stmt->execute([$profesor_id]);
    $profesor = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$profesor) {
        throw new Exception("Profesor no encontrado o inactivo");
    }
    
    $db->beginTransaction();
    
    if ($accion === 'asignar') {
        // Verificar si ya existe la asignación
        $query = "SELECT id FROM curso_profesores WHERE formulario_id = ? AND profesor_id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$formulario_id, $profesor_id]);
        
        if ($stmt->fetch()) {            // Ya existe, solo activarla
            $query = "UPDATE curso_profesores SET activo = 1 WHERE formulario_id = ? AND profesor_id = ?";
            $stmt = $db->prepare($query);
            $stmt->execute([$formulario_id, $profesor_id]);
        } else {            // Crear nueva asignación
            $query = "INSERT INTO curso_profesores (formulario_id, profesor_id, activo) VALUES (?, ?, 1)";
            $stmt = $db->prepare($query);
            $stmt->execute([$formulario_id, $profesor_id]);
        }
        
        $mensaje = "Profesor '{$profesor['nombre']}' asignado exitosamente al formulario '{$formulario['nombre']}'";
        
    } else { // desasignar        // Desactivar la asignación
        $query = "UPDATE curso_profesores SET activo = 0 WHERE formulario_id = ? AND profesor_id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$formulario_id, $profesor_id]);
        
        if ($stmt->rowCount() === 0) {
            throw new Exception("No se encontró la asignación para desactivar");
        }
        
        $mensaje = "Profesor '{$profesor['nombre']}' desasignado exitosamente del formulario '{$formulario['nombre']}'";
    }
    
    $db->commit();
    
    // Respuesta exitosa
    echo json_encode([
        'success' => true,
        'message' => $mensaje,
        'data' => [
            'formulario_id' => $formulario_id,
            'profesor_id' => $profesor_id,
            'accion' => $accion,
            'formulario_nombre' => $formulario['nombre'],
            'profesor_nombre' => $profesor['nombre']
        ]
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    // Rollback en caso de error
    if (isset($db) && $db->inTransaction()) {
        $db->rollback();
    }
    
    // Error
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error al gestionar asignación: ' . $e->getMessage(),
        'data' => null
    ], JSON_UNESCAPED_UNICODE);
}
?>
