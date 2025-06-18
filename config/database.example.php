<?php
/**
 * EJEMPLO - Configuración de Base de Datos para Producción
 * 
 * ⚠️  IMPORTANTE: Este es un archivo de ejemplo
 * 
 * Para usar en producción:
 * 1. Copia este archivo como config/database.php
 * 2. Cambia todos los valores por los de tu servidor
 * 3. Usa contraseñas seguras
 * 4. Considera usar variables de entorno
 * 
 * @author Sistema de Encuestas Académicas
 * @version 1.0
 * @date 17 de junio de 2025
 */

// Configurar zona horaria del sistema
date_default_timezone_set('Europe/Madrid'); // Cambiar según tu ubicación

// ================================
// CONFIGURACIÓN DE PRODUCCIÓN
// ================================

// Base de datos - CAMBIAR ESTOS VALORES
define('DB_HOST', 'tu-servidor-mysql.com');           // Host del servidor MySQL
define('DB_NAME', 'nombre_base_datos_produccion');    // Nombre de la base de datos
define('DB_USER', 'usuario_mysql_seguro');            // Usuario MySQL (NO usar root)
define('DB_PASS', 'contraseña_muy_segura_123!@#');    // Contraseña fuerte
define('DB_CHARSET', 'utf8mb4');
define('DB_COLLATE', 'utf8mb4_spanish2_ci');

// Configuración de seguridad adicional
define('DB_SSL_ENABLED', false);      // Cambiar a true si usas SSL
define('DB_SSL_CA', '');               // Ruta al certificado CA (si usas SSL)
define('DB_PORT', 3306);               // Puerto MySQL (cambiar si es diferente)

// ================================
// CONFIGURACIÓN ALTERNATIVA CON VARIABLES DE ENTORNO
// ================================

/*
// Si prefieres usar variables de entorno (recomendado para producción):

define('DB_HOST', $_ENV['DB_HOST'] ?? 'localhost');
define('DB_NAME', $_ENV['DB_NAME'] ?? 'academia_encuestas');
define('DB_USER', $_ENV['DB_USER'] ?? 'root');
define('DB_PASS', $_ENV['DB_PASS'] ?? '');

// Crear archivo .env en la raíz del proyecto:
// DB_HOST=tu-servidor-mysql.com
// DB_NAME=academia_encuestas_prod
// DB_USER=usuario_seguro
// DB_PASS=contraseña_muy_segura
*/

/**
 * Clase Database - Patrón Singleton para gestión de conexiones
 * 
 * Esta clase maneja la conexión a la base de datos de forma segura
 * y eficiente, implementando el patrón Singleton para evitar
 * múltiples conexiones innecesarias.
 */
class Database {
    private static $instance = null;
    private $connection = null;
    private $dsn;
    private $options;

    /**
     * Constructor privado para implementar Singleton
     */
    private function __construct() {
        $this->dsn = "mysql:host=" . DB_HOST . 
                     ";dbname=" . DB_NAME . 
                     ";charset=" . DB_CHARSET .
                     ";port=" . DB_PORT;
        
        $this->options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET . " COLLATE " . DB_COLLATE,
            PDO::ATTR_TIMEOUT            => 30,
            PDO::ATTR_PERSISTENT         => false, // Cambiar a true si necesitas conexiones persistentes
        ];

        // Configuración SSL si está habilitada
        if (defined('DB_SSL_ENABLED') && DB_SSL_ENABLED) {
            $this->options[PDO::MYSQL_ATTR_SSL_CA] = DB_SSL_CA;
            $this->options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = true;
        }
    }

    /**
     * Obtener instancia única de la clase
     * 
     * @return Database Instancia única de la clase
     */
    public static function getInstance(): Database {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Obtener la conexión PDO a la base de datos
     * 
     * @return PDO Conexión a la base de datos
     * @throws PDOException Si no se puede conectar
     */
    public function getConnection(): PDO {
        if ($this->connection === null) {
            try {
                $this->connection = new PDO($this->dsn, DB_USER, DB_PASS, $this->options);
                
                // Log de conexión exitosa (solo en desarrollo)
                if (defined('DEBUG_MODE') && DEBUG_MODE) {
                    error_log("Database: Conexión establecida exitosamente");
                }
                
            } catch (PDOException $e) {
                // Log del error (sin mostrar credenciales)
                error_log("Database Error: No se pudo conectar a la base de datos - " . $e->getMessage());
                
                // En producción, mostrar un error genérico
                throw new PDOException("Error de conexión a la base de datos. Por favor, contacte al administrador.");
            }
        }
        
        return $this->connection;
    }

    /**
     * Cerrar la conexión a la base de datos
     */
    public function closeConnection(): void {
        $this->connection = null;
    }

    /**
     * Verificar si la conexión está activa
     * 
     * @return bool True si la conexión está activa
     */
    public function isConnected(): bool {
        try {
            if ($this->connection === null) {
                return false;
            }
            
            $this->connection->query('SELECT 1');
            return true;
            
        } catch (PDOException $e) {
            return false;
        }
    }

    /**
     * Obtener información sobre la conexión
     * 
     * @return array Información de la conexión
     */
    public function getConnectionInfo(): array {
        if ($this->connection === null) {
            return ['status' => 'disconnected'];
        }

        try {
            $serverInfo = $this->connection->getAttribute(PDO::ATTR_SERVER_INFO);
            $serverVersion = $this->connection->getAttribute(PDO::ATTR_SERVER_VERSION);
            
            return [
                'status' => 'connected',
                'server_info' => $serverInfo,
                'server_version' => $serverVersion,
                'host' => DB_HOST,
                'database' => DB_NAME,
                'charset' => DB_CHARSET
            ];
            
        } catch (PDOException $e) {
            return [
                'status' => 'error',
                'error' => $e->getMessage()
            ];
        }
    }

    // Prevenir clonación y deserialización
    private function __clone() {}
    public function __wakeup() {
        throw new Exception("No se puede deserializar una instancia de " . __CLASS__);
    }
}

// ================================
// FUNCIONES DE UTILIDAD
// ================================

/**
 * Obtener conexión rápida a la base de datos
 * 
 * @return PDO Conexión PDO
 */
function getDB(): PDO {
    return Database::getInstance()->getConnection();
}

/**
 * Ejecutar una consulta preparada de forma segura
 * 
 * @param string $sql Consulta SQL con placeholders
 * @param array $params Parámetros para la consulta
 * @return PDOStatement Resultado de la consulta
 */
function executeQuery(string $sql, array $params = []): PDOStatement {
    $db = getDB();
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

/**
 * Verificar conexión a la base de datos
 * 
 * @return bool True si la conexión es exitosa
 */
function testDatabaseConnection(): bool {
    try {
        $db = Database::getInstance();
        return $db->isConnected() || $db->getConnection() !== null;
    } catch (Exception $e) {
        error_log("Test de conexión falló: " . $e->getMessage());
        return false;
    }
}

// ================================
// CONFIGURACIONES ADICIONALES PARA PRODUCCIÓN
// ================================

// Configurar manejo de errores para producción
if (!defined('DEBUG_MODE')) {
    define('DEBUG_MODE', false); // Cambiar a false en producción
}

// Configurar reportes de errores
if (DEBUG_MODE) {
    ini_set('display_errors', 1);
    ini_set('log_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    error_reporting(E_ERROR | E_WARNING | E_PARSE);
}

// Configurar logging
ini_set('log_errors_max_len', 1024);
ini_set('error_log', __DIR__ . '/../logs/database_errors.log');

?>
