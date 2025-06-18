<?php
/**
 * Configuración de Base de Datos - Sistema de Encuestas Académicas
 * 
 * @author Sistema de Encuestas Académicas
 * @version 1.0
 * @date 11 de junio de 2025
 * 
 * IMPORTANTE: En producción, cambiar las credenciales por valores seguros
 * y considerar usar variables de entorno para mayor seguridad.
 */

// Configurar zona horaria del sistema
date_default_timezone_set('Europe/Madrid');

// Configuración para entorno de desarrollo
define('DB_HOST', 'localhost');
define('DB_NAME', 'academia_encuestas');
define('DB_USER', 'root');
define('DB_PASS', '');  // Cambiar en producción por una contraseña fuerte
define('DB_CHARSET', 'utf8mb4');
define('DB_COLLATE', 'utf8mb4_spanish2_ci');

/**
 * Clase Database - Patrón Singleton para gestión de conexiones
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
        $this->dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        
        $this->options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET . " COLLATE " . DB_COLLATE,
            PDO::ATTR_TIMEOUT            => 30,
            PDO::ATTR_PERSISTENT         => false,
        ];
    }

    /**
     * Obtener instancia única de la clase
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Obtener conexión a la base de datos
     */    public function getConnection() {
        if ($this->connection === null) {
            try {
                $this->connection = new PDO($this->dsn, DB_USER, DB_PASS, $this->options);
                // Configurar zona horaria para España (UTC+2 en horario de verano)
                $this->connection->exec("SET time_zone = '+02:00'");
                
                // También configurar PHP para usar la misma zona horaria
                date_default_timezone_set('Europe/Madrid');
            } catch (PDOException $e) {
                if (defined('MODO_DESARROLLO') && MODO_DESARROLLO === true) {
                    die("Error de conexión a la base de datos: " . $e->getMessage());
                } else {
                    error_log("Error de conexión BD: " . $e->getMessage());
                    die("Error interno del servidor. Por favor, contacte al administrador.");
                }
            }
        }
        return $this->connection;
    }

    /**
     * Prevenir clonación
     */
    private function __clone() {}

    /**
     * Prevenir deserialización
     */
    public function __wakeup() {}
}

/**
 * Función para obtener conexión a la base de datos (retrocompatibilidad)
 * 
 * @return PDO Instancia de conexión PDO
 * @throws PDOException Si no se puede conectar
 */
function getConnection() {
    return Database::getInstance()->getConnection();
}

/**
 * Función para verificar si la conexión está activa
 * 
 * @return bool True si la conexión es exitosa
 */
function testConnection() {
    try {
        $pdo = getConnection();
        $stmt = $pdo->query("SELECT 1");
        return $stmt !== false;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Función para ejecutar consultas preparadas de forma segura
 * 
 * @param string $query Consulta SQL
 * @param array $params Parámetros para la consulta
 * @return PDOStatement|false Resultado de la consulta
 */
function executeQuery($query, $params = []) {
    try {
        $pdo = getConnection();
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        return $stmt;
    } catch (PDOException $e) {
        if (defined('MODO_DESARROLLO') && MODO_DESARROLLO === true) {
            error_log("Error en consulta SQL: " . $e->getMessage() . " | Query: " . $query);
        } else {
            error_log("Error en consulta SQL: " . $e->getMessage());
        }
        return false;
    }
}

// Definir modo de desarrollo (cambiar a false en producción)
if (!defined('MODO_DESARROLLO')) {
    define('MODO_DESARROLLO', true);
}

// Configurar reportes de errores según el entorno
if (MODO_DESARROLLO) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', __DIR__ . '/../logs/php-errors.log');
}

/**
 * Configuración adicional de seguridad
 */
// Evitar que se acceda directamente a este archivo
if (basename($_SERVER['PHP_SELF']) == basename(__FILE__)) {
    http_response_code(403);
    die('Acceso directo no permitido');
}
?>
