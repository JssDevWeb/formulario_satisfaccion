<?php
// Debug logging
error_log("get_profesores_todos.php - Inicio de ejecución");

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Accept');

// Manejar preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    error_log("get_profesores_todos.php - OPTIONS request");
    http_response_code(200);
    exit();
}

error_log("get_profesores_todos.php - Request method: " . $_SERVER['REQUEST_METHOD']);

// Incluir la configuración de la base de datos
require_once '../config/database.php';

try {    error_log("get_profesores_todos.php - Conectando a base de datos");
    
    // Crear conexión a la base de datos
    $database = Database::getInstance();
    $db = $database->getConnection();
    
    if (!$db) {
        throw new Exception("Error de conexión a la base de datos");
    }
    
    error_log("get_profesores_todos.php - Conexión exitosa, ejecutando consulta");
      // Consulta para obtener todos los profesores activos
    $query = "SELECT 
                id, 
                nombre, 
                email, 
                telefono, 
                activo
              FROM profesores 
              WHERE activo = 1 
              ORDER BY nombre ASC";
    
    $stmt = $db->prepare($query);
    $stmt->execute();
    
    $profesores = [];
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {        $profesores[] = [
            'id' => (int)$row['id'],
            'nombre' => $row['nombre'],
            'email' => $row['email'],
            'telefono' => $row['telefono'],
            'activo' => (bool)$row['activo']
        ];    }
    
    error_log("get_profesores_todos.php - Consulta ejecutada, profesores encontrados: " . count($profesores));
    
    // Respuesta exitosa
    $response = [
        'success' => true,
        'message' => 'Profesores cargados exitosamente',
        'data' => $profesores,
        'total' => count($profesores)
    ];
    
    error_log("get_profesores_todos.php - Enviando respuesta JSON");
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    error_log("get_profesores_todos.php - Error: " . $e->getMessage());
    
    // Error de base de datos o conexión
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error al cargar profesores: ' . $e->getMessage(),
        'data' => [],
        'total' => 0
    ], JSON_UNESCAPED_UNICODE);
}

error_log("get_profesores_todos.php - Fin de ejecución");
?>
