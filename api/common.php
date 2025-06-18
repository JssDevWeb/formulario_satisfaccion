<?php
/**
 * Funciones comunes para APIs
 * Archivo: api/common.php
 */

// Evitar ejecución directa
if (basename($_SERVER['PHP_SELF']) == basename(__FILE__)) {
    http_response_code(403);
    die('Acceso directo no permitido');
}

/**
 * Función para registrar logs de API
 */
if (!function_exists('logApiRequest')) {
    function logApiRequest($endpoint, $method, $params = [], $response_code = 200, $ip = null) {
        $ip = $ip ?: ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        $timestamp = date('Y-m-d H:i:s');
        
        $log_entry = sprintf(
            "[%s] %s %s from %s - Code: %d - Params: %s - UA: %s\n",
            $timestamp,
            $method,
            $endpoint,
            $ip,
            $response_code,
            json_encode($params),
            $user_agent
        );
        
        $log_file = __DIR__ . '/../logs/api.log';
        $log_dir = dirname($log_file);
        
        if (!is_dir($log_dir)) {
            mkdir($log_dir, 0755, true);
        }
        
        error_log($log_entry, 3, $log_file);
    }
}

/**
 * Función para validar y limpiar entrada
 */
if (!function_exists('sanitizeInput')) {
    function sanitizeInput($input, $type = 'string') {
        if ($input === null || $input === '') {
            return null;
        }
        
        switch ($type) {
            case 'int':
                return filter_var($input, FILTER_VALIDATE_INT);
            case 'float':
                return filter_var($input, FILTER_VALIDATE_FLOAT);
            case 'email':
                return filter_var($input, FILTER_VALIDATE_EMAIL);
            case 'url':
                return filter_var($input, FILTER_VALIDATE_URL);
            case 'string':
            default:
                return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
        }
    }
}

/**
 * Función para enviar respuesta JSON
 */
if (!function_exists('sendJsonResponse')) {
    function sendJsonResponse($success, $data = null, $message = '', $code = 200, $meta = []) {
        http_response_code($code);
        
        $response = [
            'success' => $success,
            'timestamp' => date('c'),
            'code' => $code
        ];
        
        if ($message) {
            $response['message'] = $message;
        }
        
        if ($data !== null) {
            $response['data'] = $data;
        }
        
        if (!empty($meta)) {
            $response['meta'] = $meta;
        }
        
        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit();
    }
}

/**
 * Función para verificar rate limiting
 */
if (!function_exists('checkRateLimit')) {
    function checkRateLimit($ip, $limit_per_hour = 200) {
        $cache_file = __DIR__ . '/../cache/rate_limit_' . md5($ip) . '.json';
        $cache_dir = dirname($cache_file);
        
        if (!is_dir($cache_dir)) {
            mkdir($cache_dir, 0755, true);
        }
        
        $current_time = time();
        $rate_data = [];
        
        if (file_exists($cache_file)) {
            $rate_data = json_decode(file_get_contents($cache_file), true) ?: [];
        }
        
        // Limpiar requests antiguos (más de 1 hora)
        $rate_data = array_filter($rate_data, function($timestamp) use ($current_time) {
            return ($current_time - $timestamp) < 3600;
        });
        
        // Verificar límite
        if (count($rate_data) >= $limit_per_hour) {
            return false;
        }
        
        // Registrar request actual
        $rate_data[] = $current_time;
        file_put_contents($cache_file, json_encode($rate_data));
        
        return true;
    }
}

/**
 * Función para verificar anti-spam
 */
if (!function_exists('checkAntiSpam')) {
    function checkAntiSpam($ip, $cooldown_hours = 24) {
        $cache_file = __DIR__ . '/../cache/spam_' . md5($ip) . '.json';
        $cache_dir = dirname($cache_file);
        
        if (!is_dir($cache_dir)) {
            mkdir($cache_dir, 0755, true);
        }
        
        if (file_exists($cache_file)) {
            $last_submission = json_decode(file_get_contents($cache_file), true);
            $time_diff = time() - $last_submission['timestamp'];
            
            if ($time_diff < ($cooldown_hours * 3600)) {
                $remaining_hours = ceil(($cooldown_hours * 3600 - $time_diff) / 3600);
                return [
                    'allowed' => false,
                    'remaining_hours' => $remaining_hours
                ];
            }
        }
        
        return ['allowed' => true];
    }
}

/**
 * Función para registrar envío de encuesta
 */
if (!function_exists('registerSpamCheck')) {
    function registerSpamCheck($ip) {
        $cache_file = __DIR__ . '/../cache/spam_' . md5($ip) . '.json';
        $cache_dir = dirname($cache_file);
        
        if (!is_dir($cache_dir)) {
            mkdir($cache_dir, 0755, true);
        }
        
        $data = [
            'timestamp' => time(),
            'ip' => $ip
        ];
        
        file_put_contents($cache_file, json_encode($data));
    }
}
?>
