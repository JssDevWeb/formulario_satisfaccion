<?php
/**
 * ============================================
 * SISTEMA DE ENCUESTAS ACADÉMICAS - REPORTES LIMPIO
 * ============================================
 * Archivo: admin/reportes_nuevo.php
 * Descripción: Página de reportes optimizada con solo las funcionalidades necesarias
 * Funcionalidades:
 * - Gráficos de Evaluación por Curso y Fecha
 * - Preguntas Más Críticas
 * - Comentarios Cualitativos Más Recientes
 * ============================================
 */

// Configuración de seguridad
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// Iniciar sesión
session_start();

// Verificar autenticación
// if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
//     header('Location: index.php');
//     exit;
// }

// Incluir configuración de base de datos
require_once '../config/database.php';

// Parámetros para gráficos de torta específicos
$curso_grafico_id = $_GET['curso_grafico_id'] ?? '';
$fecha_grafico = $_GET['fecha_grafico'] ?? '';
$generar_graficos = isset($_GET['generar_graficos']) && !empty($curso_grafico_id) && !empty($fecha_grafico);

try {
    $db = Database::getInstance()->getConnection();
    
    // ============================================
    // CURSOS DISPONIBLES (para selector de gráficos)
    // ============================================
    $stmt = $db->query("
        SELECT DISTINCT e.curso_id as id, c.nombre, f.descripcion
        FROM encuestas e 
        JOIN formularios f ON e.formulario_id = f.id
        JOIN cursos c ON e.curso_id = c.id 
        ORDER BY c.nombre
    ");    $cursos_disponibles = $stmt->fetchAll();
    
    // ============================================
    // GRÁFICOS DE TORTA DINÁMICOS
    // ============================================    // ============================================
    // INICIALIZACIÓN DE VARIABLES
    // ============================================
    $graficos_torta = [];
    $preguntas_criticas = [];
    $comentarios_curso = [];
    $comentarios_profesor = [];
    $resumen_ejecutivo = [];
    
    // ============================================
    // GENERACIÓN DE REPORTES (SOLO SI SE SOLICITA)
    // ============================================
    if ($generar_graficos) {
        // Generar gráficos
        try {
            $graficos_torta = generarGraficosCursoYProfesores($db, $curso_grafico_id, $fecha_grafico);
        } catch (Exception $e) {
            error_log("Error generando gráficos: " . $e->getMessage());
        }
        
        // Generar resumen ejecutivo
        try {
            // Estadísticas generales del curso/fecha
            $stmt = $db->prepare("
                SELECT 
                    COUNT(DISTINCT e.id) as total_encuestas_global,
                    COUNT(DISTINCT r.profesor_id) as total_profesores_evaluados,
                    COUNT(*) as total_respuestas_escala,
                AVG(r.valor_int) as promedio_general,
                MIN(r.valor_int) as valor_minimo,
                MAX(r.valor_int) as valor_maximo,
                STDDEV(r.valor_int) as desviacion_general
            FROM encuestas e
            JOIN respuestas r ON e.id = r.encuesta_id
            JOIN preguntas pr ON r.pregunta_id = pr.id
            WHERE e.curso_id = :curso_id
            AND DATE(e.fecha_envio) = :fecha
            AND pr.tipo = 'escala'
        ");
        $stmt->execute([':curso_id' => $curso_grafico_id, ':fecha' => $fecha_grafico]);
        $stats_generales = $stmt->fetch();
        
        // Mediana y percentiles
        $stmt = $db->prepare("
            SELECT r.valor_int
            FROM encuestas e
            JOIN respuestas r ON e.id = r.encuesta_id
            JOIN preguntas pr ON r.pregunta_id = pr.id
            WHERE e.curso_id = :curso_id
            AND DATE(e.fecha_envio) = :fecha
            AND pr.tipo = 'escala'
            ORDER BY r.valor_int
        ");
        $stmt->execute([':curso_id' => $curso_grafico_id, ':fecha' => $fecha_grafico]);
        $valores_ordenados = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Calcular percentiles
        $total_valores = count($valores_ordenados);
        $percentil_25 = $total_valores > 0 ? $valores_ordenados[floor($total_valores * 0.25)] : 0;
        $mediana = $total_valores > 0 ? $valores_ordenados[floor($total_valores * 0.5)] : 0;
        $percentil_75 = $total_valores > 0 ? $valores_ordenados[floor($total_valores * 0.75)] : 0;
        
        // Distribución de respuestas
        $stmt = $db->prepare("
            SELECT 
                r.valor_int,
                COUNT(*) as frecuencia,
                ROUND((COUNT(*) * 100.0 / (SELECT COUNT(*) FROM encuestas e2 
                    JOIN respuestas r2 ON e2.id = r2.encuesta_id 
                    JOIN preguntas pr2 ON r2.pregunta_id = pr2.id 
                    WHERE e2.curso_id = :curso_id AND DATE(e2.fecha_envio) = :fecha AND pr2.tipo = 'escala')), 1) as porcentaje
            FROM encuestas e
            JOIN respuestas r ON e.id = r.encuesta_id
            JOIN preguntas pr ON r.pregunta_id = pr.id
            WHERE e.curso_id = :curso_id
            AND DATE(e.fecha_envio) = :fecha
            AND pr.tipo = 'escala'
            GROUP BY r.valor_int
            ORDER BY r.valor_int DESC
        ");
        $stmt->execute([':curso_id' => $curso_grafico_id, ':fecha' => $fecha_grafico]);
        $distribucion = $stmt->fetchAll();
        
        // Detectar alertas automáticas
        $alertas = [];
        if ($stats_generales['promedio_general'] < 5) {
            $alertas[] = ['tipo' => 'danger', 'mensaje' => 'Promedio general muy bajo (' . round($stats_generales['promedio_general'], 1) . ')'];
        }
        if ($stats_generales['desviacion_general'] > 3) {
            $alertas[] = ['tipo' => 'warning', 'mensaje' => 'Alta variabilidad en las respuestas (σ = ' . round($stats_generales['desviacion_general'], 1) . ')'];
        }
        if ($stats_generales['total_encuestas_global'] < 5) {
            $alertas[] = ['tipo' => 'info', 'mensaje' => 'Muestra pequeña: Solo ' . $stats_generales['total_encuestas_global'] . ' encuestas'];
        }
          $resumen_ejecutivo = [
            'stats' => $stats_generales,
            'percentiles' => ['p25' => $percentil_25, 'mediana' => $mediana, 'p75' => $percentil_75],
            'distribucion' => $distribucion,
            'alertas' => $alertas
        ];
        } catch (Exception $e) {
            error_log("Error generando resumen ejecutivo: " . $e->getMessage());
        }
        
        // Generar preguntas críticas
        try {
            // ============================================
            // PREGUNTAS MÁS CRÍTICAS DEL CURSO Y FECHA ESPECÍFICA
            // ============================================
        $stmt = $db->prepare("
            SELECT 
                pr.texto as texto_pregunta,
                pr.seccion,
                COUNT(*) as total_respuestas,
                ROUND(AVG(r.valor_int), 2) as promedio,
                ROUND(STDDEV(r.valor_int), 2) as desviacion_estandar,
                MIN(r.valor_int) as valor_minimo,
                MAX(r.valor_int) as valor_maximo,
                COUNT(CASE WHEN r.valor_int <= 5 THEN 1 END) as respuestas_bajas,
                COUNT(CASE WHEN r.valor_int >= 8 THEN 1 END) as respuestas_altas,
                ROUND((COUNT(CASE WHEN r.valor_int <= 5 THEN 1 END) * 100.0 / COUNT(*)), 1) as porcentaje_critico,
                ROUND((COUNT(CASE WHEN r.valor_int >= 8 THEN 1 END) * 100.0 / COUNT(*)), 1) as porcentaje_excelente
            FROM encuestas e
            JOIN respuestas r ON e.id = r.encuesta_id
            JOIN preguntas pr ON r.pregunta_id = pr.id
            WHERE pr.tipo = 'escala'
                AND e.curso_id = :curso_id
                AND DATE(e.fecha_envio) = :fecha
            GROUP BY pr.id, pr.texto, pr.seccion
            HAVING COUNT(*) >= 1
            ORDER BY promedio ASC, respuestas_bajas DESC
            LIMIT 15
        ");
        $stmt->execute([':curso_id' => $curso_grafico_id, ':fecha' => $fecha_grafico]);
        $preguntas_criticas = $stmt->fetchAll();
        
        // ============================================
        // COMENTARIOS CUALITATIVOS DEL CURSO (SECCIÓN CURSO)
        // ============================================
        $stmt = $db->prepare("
            SELECT 
                r.valor_text as comentario,
                pr.texto as pregunta_texto,
                c.nombre as curso_nombre,
                e.fecha_envio
            FROM encuestas e
            JOIN respuestas r ON e.id = r.encuesta_id
            JOIN preguntas pr ON r.pregunta_id = pr.id
            JOIN cursos c ON e.curso_id = c.id
            WHERE pr.tipo = 'texto' 
                AND pr.seccion = 'curso'
                AND e.curso_id = :curso_id
                AND DATE(e.fecha_envio) = :fecha
                AND r.valor_text IS NOT NULL 
                AND r.valor_text != '' 
                AND CHAR_LENGTH(TRIM(r.valor_text)) > 5
            ORDER BY e.fecha_envio DESC
            LIMIT 10
        ");
        $stmt->execute([':curso_id' => $curso_grafico_id, ':fecha' => $fecha_grafico]);
        $comentarios_curso = $stmt->fetchAll();
        
        // ============================================
        // COMENTARIOS CUALITATIVOS DE PROFESORES
        // ============================================
        $stmt = $db->prepare("
            SELECT 
                r.valor_text as comentario,
                pr.texto as pregunta_texto,
                c.nombre as curso_nombre,
                p.nombre as profesor_nombre,
                e.fecha_envio
            FROM encuestas e
            JOIN respuestas r ON e.id = r.encuesta_id
            JOIN preguntas pr ON r.pregunta_id = pr.id
            JOIN cursos c ON e.curso_id = c.id
            LEFT JOIN profesores p ON r.profesor_id = p.id
            WHERE pr.tipo = 'texto' 
                AND pr.seccion = 'profesor'
                AND e.curso_id = :curso_id
                AND DATE(e.fecha_envio) = :fecha
                AND r.valor_text IS NOT NULL 
                AND r.valor_text != '' 
                AND CHAR_LENGTH(TRIM(r.valor_text)) > 5
            ORDER BY e.fecha_envio DESC
            LIMIT 10
        ");        $stmt->execute([':curso_id' => $curso_grafico_id, ':fecha' => $fecha_grafico]);
        $comentarios_profesor = $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Error generando comentarios: " . $e->getMessage());
        }
    }

} catch (Exception $e) {
    error_log("Error general en reportes: " . $e->getMessage());
    // No reinicializar las variables aquí para mantener los datos ya generados
}

/**
 * Genera gráficos para un curso específico y todos sus profesores en una fecha determinada
 */
function generarGraficosCursoYProfesores($db, $curso_id, $fecha) {
    $graficos = [];
    
    try {
        // Generar gráfico del curso
        $grafico_curso = generarGraficoCursoEspecifico($db, $curso_id, $fecha);
        if ($grafico_curso) {
            $graficos[] = $grafico_curso;
        }
        
        // Generar gráficos de profesores
        $graficos_profesores = generarGraficosProfesoresCurso($db, $curso_id, $fecha);
        $graficos = array_merge($graficos, $graficos_profesores);
        
    } catch (Exception $e) {
        error_log("Error generando gráficos: " . $e->getMessage());
    }
    
    return $graficos;
}

/**
 * Genera gráfico específico para un curso en una fecha determinada
 */
function generarGraficoCursoEspecifico($db, $curso_id, $fecha) {
    try {
        // Obtener información del curso
        $stmt = $db->prepare("SELECT c.id, c.nombre, c.codigo FROM cursos c WHERE c.id = :curso_id");
        $stmt->execute([':curso_id' => $curso_id]);
        $curso = $stmt->fetch();
        
        if (!$curso) {
            return null;
        }
        
        // Contar total de encuestas para este curso en esta fecha
        $stmt = $db->prepare("
            SELECT COUNT(DISTINCT e.id) as total_encuestas
            FROM encuestas e
            JOIN respuestas r ON e.id = r.encuesta_id
            JOIN preguntas pr ON r.pregunta_id = pr.id
            WHERE e.curso_id = :curso_id
            AND DATE(e.fecha_envio) = :fecha
            AND pr.seccion = 'curso' AND pr.tipo = 'escala'
        ");        $stmt->execute([':curso_id' => $curso_id, ':fecha' => $fecha]);
        $total_encuestas = $stmt->fetch()['total_encuestas'];
        
        if ($total_encuestas == 0) {
            return null;
        }
        
        // Obtener distribución de respuestas simplificada
        $stmt = $db->prepare("
            SELECT 
                r.valor_int,
                COUNT(*) as cantidad
            FROM encuestas e
            JOIN respuestas r ON e.id = r.encuesta_id
            JOIN preguntas pr ON r.pregunta_id = pr.id
            WHERE e.curso_id = :curso_id
            AND DATE(e.fecha_envio) = :fecha
            AND pr.seccion = 'curso' AND pr.tipo = 'escala'
            GROUP BY r.valor_int
            ORDER BY r.valor_int DESC
        ");        $stmt->execute([':curso_id' => $curso_id, ':fecha' => $fecha]);
        $distribucion_raw = $stmt->fetchAll();
        
        // Crear distribución estándar (valores 1-10)
        $distribucion = [];
        $total_respuestas = 0;
        
        // Inicializar todos los valores
        for ($i = 1; $i <= 10; $i++) {
            $distribucion[$i] = 0;
        }
        
        // Llenar con datos reales
        foreach ($distribucion_raw as $item) {
            $distribucion[$item['valor_int']] = (int)$item['cantidad'];
            $total_respuestas += (int)$item['cantidad'];
        }
        
        // Convertir a porcentajes
        $labels = [];
        $data = [];
        $escala_nombres = [
            10 => 'Excelente (10)',
            9 => 'Excelente (9)', 
            8 => 'Bueno (8)',
            7 => 'Bueno (7)',
            6 => 'Correcto (6)',
            5 => 'Correcto (5)',
            4 => 'Regular (4)',
            3 => 'Regular (3)',
            2 => 'Deficiente (2)',
            1 => 'Deficiente (1)'
        ];
          // Agrupar valores en 5 categorías estándares para consistencia visual
        $categorias_estandar = [
            'Excelente' => ['valores' => [9, 10], 'color' => '#28a745', 'cantidad' => 0],
            'Bueno' => ['valores' => [7, 8], 'color' => '#17a2b8', 'cantidad' => 0],
            'Correcto' => ['valores' => [5, 6], 'color' => '#ffc107', 'cantidad' => 0],
            'Regular' => ['valores' => [3, 4], 'color' => '#fd7e14', 'cantidad' => 0],
            'Deficiente' => ['valores' => [1, 2], 'color' => '#dc3545', 'cantidad' => 0]
        ];
        
        // Contar respuestas por categoría
        foreach ($distribucion as $valor => $cantidad) {
            foreach ($categorias_estandar as $categoria => &$info) {
                if (in_array($valor, $info['valores'])) {
                    $info['cantidad'] += $cantidad;
                    break;
                }
            }
        }
        unset($info); // Romper referencia
        
        // Crear arrays para Chart.js con todas las categorías (incluso las que tienen 0)
        $labels = [];
        $data = [];
        $colors = [];
        
        foreach ($categorias_estandar as $categoria => $info) {
            $porcentaje = $total_respuestas > 0 ? round(($info['cantidad'] / $total_respuestas) * 100, 1) : 0;
            $labels[] = $categoria . ' (' . $info['cantidad'] . ')';
            $data[] = $porcentaje;
            $colors[] = $info['color'];
        }
        
        // Calcular estadísticas
        $suma_total = 0;
        foreach ($distribucion_raw as $item) {
            $suma_total += $item['valor_int'] * $item['cantidad'];        }
        $promedio = $suma_total / $total_respuestas;
        
        // Contar número de preguntas de curso
        $stmt = $db->prepare("SELECT COUNT(*) as num_preguntas FROM preguntas WHERE seccion = 'curso' AND tipo = 'escala' AND activa = 1");
        $stmt->execute();
        $num_preguntas = $stmt->fetch()['num_preguntas'];
        
        $grafico = [
            'id' => 'curso_' . $curso_id,
            'titulo' => 'Evaluación del Curso: ' . $curso['nombre'],
            'subtitulo' => sprintf(
                '%d encuestas • %d respuestas • Promedio: %.1f/10',
                $total_encuestas,
                $total_respuestas,
                $promedio
            ),            'labels' => $labels,
            'data' => $data,
            'colors' => $colors,
            'tipo' => 'curso',
            'nombre' => $curso['nombre'],            'total_encuestas' => $total_encuestas,
            'total_respuestas' => $total_respuestas,
            'num_preguntas' => $num_preguntas,
            'promedio' => $promedio,
            'puntuacion_real' => $suma_total,
            'max_puntuacion' => $num_preguntas * $total_encuestas * 10
        ];
        
        return $grafico;
        
    } catch (Exception $e) {
        return null;
    }
}

/**
 * Genera gráficos para todos los profesores de un curso en una fecha específica
 */
function generarGraficosProfesoresCurso($db, $curso_id, $fecha) {
    $graficos = [];
    
    try {
        // Obtener profesores que tienen evaluaciones en este curso y fecha
        $stmt = $db->prepare("
            SELECT DISTINCT p.id, p.nombre
            FROM profesores p
            JOIN respuestas r ON p.id = r.profesor_id
            JOIN encuestas e ON r.encuesta_id = e.id
            JOIN preguntas pr ON r.pregunta_id = pr.id
            WHERE e.curso_id = :curso_id
            AND DATE(e.fecha_envio) = :fecha
            AND pr.seccion = 'profesor' AND pr.tipo = 'escala'
            ORDER BY p.nombre
        ");
        $stmt->execute([':curso_id' => $curso_id, ':fecha' => $fecha]);
        $profesores = $stmt->fetchAll();
          foreach ($profesores as $profesor) {
            $grafico = generarGraficoProfesorEspecifico($db, $profesor['id'], $profesor['nombre'], $curso_id, $fecha);
            if ($grafico) {
                $graficos[] = $grafico;
            }
        }
        
    } catch (Exception $e) {
        error_log("Error generando gráficos de profesores: " . $e->getMessage());
    }
    
    return $graficos;
}

/**
 * Genera gráfico específico para un profesor en un curso y fecha determinada
 */
function generarGraficoProfesorEspecifico($db, $profesor_id, $profesor_nombre, $curso_id, $fecha) {
    try {
        // Contar total de encuestas para este profesor en este curso y fecha
        $stmt = $db->prepare("
            SELECT COUNT(DISTINCT e.id) as total_encuestas
            FROM encuestas e
            JOIN respuestas r ON e.id = r.encuesta_id
            JOIN preguntas pr ON r.pregunta_id = pr.id
            WHERE e.curso_id = :curso_id
            AND r.profesor_id = :profesor_id
            AND DATE(e.fecha_envio) = :fecha
            AND pr.seccion = 'profesor' AND pr.tipo = 'escala'
        ");
        $stmt->execute([':curso_id' => $curso_id, ':profesor_id' => $profesor_id, ':fecha' => $fecha]);
        $total_encuestas = $stmt->fetch()['total_encuestas'];
        
        if ($total_encuestas == 0) return null;
        
        // Contar respuestas por valor con cantidad absoluta
        $stmt = $db->prepare("
            SELECT 
                r.valor_int,
                COUNT(*) as cantidad_respuestas
            FROM encuestas e
            JOIN respuestas r ON e.id = r.encuesta_id
            JOIN preguntas pr ON r.pregunta_id = pr.id
            WHERE e.curso_id = :curso_id
            AND r.profesor_id = :profesor_id
            AND DATE(e.fecha_envio) = :fecha
            AND pr.seccion = 'profesor' AND pr.tipo = 'escala'
            GROUP BY r.valor_int
            ORDER BY r.valor_int DESC
        ");        $stmt->execute([':curso_id' => $curso_id, ':profesor_id' => $profesor_id, ':fecha' => $fecha]);
        $resultados = $stmt->fetchAll();
        
        // Calcular puntuación real total (suma de todos los valor_int)
        $stmt = $db->prepare("
            SELECT SUM(r.valor_int) as puntuacion_real
            FROM encuestas e
            JOIN respuestas r ON e.id = r.encuesta_id
            JOIN preguntas pr ON r.pregunta_id = pr.id
            WHERE e.curso_id = :curso_id
            AND r.profesor_id = :profesor_id
            AND DATE(e.fecha_envio) = :fecha
            AND pr.seccion = 'profesor' AND pr.tipo = 'escala'
        ");
        $stmt->execute([':curso_id' => $curso_id, ':profesor_id' => $profesor_id, ':fecha' => $fecha]);
        $puntuacion_real = $stmt->fetch()['puntuacion_real'] ?? 0;
        
        // Contar número de preguntas de profesor tipo escala
        $stmt = $db->prepare("
            SELECT COUNT(*) as num_preguntas
            FROM preguntas
            WHERE seccion = 'profesor' AND tipo = 'escala' AND activa = 1
        ");
        $stmt->execute();
        $num_preguntas = $stmt->fetch()['num_preguntas'];
        
        // Calcular máximo total posible
        $max_total = 10 * $num_preguntas * $total_encuestas;
          // Usar el mismo sistema de categorías estándares que el curso
        $categorias_estandar = [
            'Excelente' => ['valores' => [9, 10], 'color' => '#28a745', 'cantidad' => 0],
            'Bueno' => ['valores' => [7, 8], 'color' => '#17a2b8', 'cantidad' => 0],
            'Correcto' => ['valores' => [5, 6], 'color' => '#ffc107', 'cantidad' => 0],
            'Regular' => ['valores' => [3, 4], 'color' => '#fd7e14', 'cantidad' => 0],
            'Deficiente' => ['valores' => [1, 2], 'color' => '#dc3545', 'cantidad' => 0]
        ];
        
        // Contar respuestas por categoría
        $total_respuestas_profesor = 0;
        foreach ($resultados as $res) {
            $valor = (int)$res['valor_int'];
            $cantidad = (int)$res['cantidad_respuestas'];
            $total_respuestas_profesor += $cantidad;
            
            foreach ($categorias_estandar as $categoria => &$info) {
                if (in_array($valor, $info['valores'])) {
                    $info['cantidad'] += $cantidad;
                    break;
                }
            }
        }
        unset($info); // Romper referencia
        
        // Crear arrays para Chart.js
        $labels = [];
        $data = [];
        $colors = [];
        
        foreach ($categorias_estandar as $categoria => $info) {
            $porcentaje = $total_respuestas_profesor > 0 ? round(($info['cantidad'] / $total_respuestas_profesor) * 100, 1) : 0;
            $labels[] = $categoria . ' (' . $info['cantidad'] . ')';
            $data[] = $porcentaje;
            $colors[] = $info['color'];
        }        return [
            'id' => "chart-profesor-{$profesor_id}",
            'titulo' => "Profesor: {$profesor_nombre} - {$fecha}",
            'subtitulo' => "{$total_encuestas} encuestas completadas - Puntuación: {$puntuacion_real}/" . ($total_encuestas * 70),
            'nombre' => $profesor_nombre,
            'tipo' => 'profesor',
            'data' => $data,
            'labels' => $labels,
            'colors' => $colors,
            'total_encuestas' => $total_encuestas,
            'num_preguntas' => $num_preguntas,
            'puntuacion_real' => $puntuacion_real,
            'max_puntuacion' => $total_encuestas * 70,
            'max_total' => $max_total
        ];
        
    } catch (Exception $e) {
        error_log("Error generando gráfico profesor específico: " . $e->getMessage());
        return null;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reportes - Sistema de Encuestas Académicas</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Admin CSS unificado -->
    <link href="assets/css/admin.css" rel="stylesheet">
    <!-- Chart.js - Versión simplificada sin date-fns para evitar error de exports -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.js"></script>
    
    <!-- Custom CSS for table usability -->
    <style>
        .table-container {
            border: 1px solid #dee2e6;
            border-radius: 0.375rem;
            background-color: #fff;
        }
        
        .table-container.scrollable {
            overflow-y: auto;
            max-height: 320px;
        }
        
        .sticky-top {
            background-color: #f8f9fa;
            z-index: 10;
        }
        
        .expandable-row {
            transition: all 0.3s ease;
        }
        
        .table-container::-webkit-scrollbar {
            width: 6px;
        }
        
        .table-container::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 3px;
        }
        
        .table-container::-webkit-scrollbar-thumb {
            background: #c1c1c1;
            border-radius: 3px;
        }
        
        .table-container::-webkit-scrollbar-thumb:hover {
            background: #a8a8a8;
        }
        
        .btn-outline-secondary:hover {
            background-color: #6c757d;
            border-color: #6c757d;
            color: #fff;
        }
        
        .table-info-bar {
            padding: 8px 12px;
            background-color: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
            font-size: 0.875rem;
        }
        
        /* Mejoras para formulario de filtros */
        .form-select {
            border: 1px solid #d1d3e2;
            border-radius: 0.35rem;
            transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
        }
        
        .form-select:focus {
            border-color: #5a6c7d;
            box-shadow: 0 0 0 0.2rem rgba(90, 108, 125, 0.25);
        }
        
        .form-control {
            border: 1px solid #d1d3e2;
            border-radius: 0.35rem;
            transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
        }
        
        .form-control:focus {
            border-color: #5a6c7d;
            box-shadow: 0 0 0 0.2rem rgba(90, 108, 125, 0.25);
        }
        
        .form-label {
            font-weight: 600;
            color: #5a5c69;
            margin-bottom: 0.5rem;
        }
        
        .form-text {
            font-size: 0.875rem;
            color: #6c757d;
        }
        
        /* Mejoras para placeholders */
        .form-control::placeholder {
            color: #6c757d;
            opacity: 0.8;
        }
        
        .form-control:focus::placeholder {
            color: #adb5bd;
            opacity: 0.6;
        }
        
        .btn-group .btn {
            border-radius: 0;
        }
        
        .btn-group .btn:first-child {
            border-top-left-radius: 0.35rem;
            border-bottom-left-radius: 0.35rem;
        }
        
        .btn-group .btn:last-child {
            border-top-right-radius: 0.35rem;
            border-bottom-right-radius: 0.35rem;
        }
        
        /* Mejoras para cards de filtros */
        .card {
            border: 1px solid #e3e6f0;
            border-radius: 0.35rem;
        }
        
        .card-header {
            background-color: #f8f9fc;
            border-bottom: 1px solid #e3e6f0;
        }
        
        /* Chart Container */
        .chart-container {
            position: relative;
            height: 300px;
            margin: 20px 0;
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .btn-group {
                flex-direction: column;
            }
            
            .btn-group .btn {
                border-radius: 0.35rem !important;
                margin-bottom: 0.5rem;
            }
            
            .btn-group .btn:last-child {
                margin-bottom: 0;
            }
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row row-sidebar">
            <!-- Sidebar -->
            <nav class="col-md-3 col-lg-2 d-md-block sidebar col-sidebar">
                <div class="sidebar-sticky">
                    <div class="text-center mb-4">
                        <h5 class="text-white">Panel Admin</h5>
                        <small class="text-muted">Sistema de Encuestas</small>
                    </div>
                    
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link text-white" href="index.php">
                                <i class="bi bi-house-door me-2"></i>Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white" href="cursos.php">
                                <i class="bi bi-book me-2"></i>Cursos
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white" href="profesores.php">
                                <i class="bi bi-person-badge me-2"></i>Profesores
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white" href="formularios.php">
                                <i class="bi bi-file-earmark-text me-2"></i>Formularios
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white" href="preguntas.php">
                                <i class="bi bi-question-circle me-2"></i>Preguntas
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white active" href="reportes.php">
                                <i class="bi bi-graph-up me-2"></i>Reportes
                            </a>
                        </li>
                        <li class="nav-item mt-4">
                            <a class="nav-link text-danger" href="login.php?logout=1">
                                <i class="bi bi-box-arrow-right me-2"></i>Cerrar Sesión
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <!-- Main content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 content-with-sidebar">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Reportes y Estadísticas</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="window.print()">
                                <i class="bi bi-printer"></i> Imprimir
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Main Content -->
                <div class="container-fluid py-4">
                    <!-- Page Header -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <h1 class="h3 mb-3">Reportes y Análisis</h1>
                            <p class="text-muted">Análisis detallado de las encuestas académicas</p>
                        </div>
                    </div>

                <!-- Gráficos de Torta Dinámicos -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h6 class="m-0 font-weight-bold">
                            <i class="bi bi-pie-chart"></i> Gráficos de Evaluación por Curso y Fecha
                        </h6>
                    </div>
                    <div class="card-body">                        <form method="GET" class="row g-3">
                            <div class="col-md-4">
                                <label for="curso_grafico_id" class="form-label">Curso</label>
                                <select class="form-select" id="curso_grafico_id" name="curso_grafico_id" required>
                                    <option value="">Seleccione un curso...</option>
                                    <?php foreach ($cursos_disponibles as $curso): ?>
                                        <option value="<?php echo $curso['id']; ?>" 
                                                <?php echo $curso_grafico_id == $curso['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($curso['nombre'] . ' - ' . $curso['descripcion']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="fecha_grafico" class="form-label">Fecha de Encuesta</label>
                                <input type="date" class="form-control" id="fecha_grafico" name="fecha_grafico" 
                                       value="<?php echo htmlspecialchars($fecha_grafico); ?>" required>
                            </div>
                            <div class="col-md-3">
                                <div class="d-grid h-100 align-items-end">
                                    <button type="submit" name="generar_graficos" value="1" class="btn btn-primary">
                                        <i class="bi bi-pie-chart"></i> Generar Reportes Específicos
                                    </button>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="d-grid h-100 align-items-end">
                                    <a href="reportes.php" class="btn btn-outline-secondary" title="Limpiar filtros y resetear formulario">
                                        <i class="bi bi-arrow-clockwise"></i>
                                        <span class="d-none d-lg-inline ms-1">Limpiar</span>
                                    </a>
                                </div>
                            </div>
                            <div class="col-12 mt-2">
                                <small class="text-muted">
                                    <i class="bi bi-info-circle"></i> 
                                    Se generarán gráficos para el curso seleccionado y todos sus profesores evaluados en la fecha especificada.
                                </small>
                            </div>                        </form>
                    </div>
                </div>

                <!-- Mensaje cuando no hay datos disponibles -->
                <?php if ($generar_graficos && empty($graficos_torta)): ?>
                <div class="card mb-4 border-warning">
                    <div class="card-header bg-warning text-dark">
                        <h6 class="m-0 font-weight-bold">
                            <i class="bi bi-exclamation-circle"></i> Sin Datos Disponibles
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="text-center py-4">
                            <div class="mb-3">
                                <i class="bi bi-inbox" style="font-size: 3rem; color: #ffc107;"></i>
                            </div>                            <h5 class="text-muted mb-3">No se encontraron encuestas</h5>
                            <p class="text-muted mb-4">
                                <?php
                                // Obtener información del curso seleccionado
                                try {
                                    $stmt = $db->prepare("SELECT nombre, codigo FROM cursos WHERE id = :curso_id");
                                    $stmt->execute([':curso_id' => $curso_grafico_id]);
                                    $curso_info = $stmt->fetch();
                                    $nombre_curso = $curso_info ? $curso_info['nombre'] : 'Curso ID: ' . $curso_grafico_id;
                                } catch (Exception $e) {
                                    $nombre_curso = 'Curso ID: ' . $curso_grafico_id;
                                }
                                ?>
                                No hay encuestas disponibles para el curso <strong><?php echo htmlspecialchars($nombre_curso); ?></strong> 
                                en la fecha <strong><?php echo htmlspecialchars($fecha_grafico); ?></strong>.
                            </p>
                              <div class="row justify-content-center">
                                <div class="col-md-8">
                                    <div class="alert alert-info">
                                        <i class="bi bi-lightbulb"></i>
                                        <strong>Sugerencias:</strong>
                                        <ul class="list-unstyled mt-2 mb-0">
                                            <li>• Verifica que la fecha sea correcta</li>
                                            <li>• Prueba con otra fecha en la que sepas que hubo encuestas</li>
                                            <li>• Selecciona un curso diferente</li>
                                            <li>• Contacta al administrador si crees que debería haber datos</li>
                                        </ul>
                                    </div>
                                    
                                    <!-- Mostrar fechas disponibles para este curso -->
                                    <?php
                                    try {                                        $stmt = $db->prepare("
                                            SELECT DISTINCT DATE(e.fecha_envio) as fecha_disponible, COUNT(*) as total_encuestas
                                            FROM encuestas e 
                                            WHERE e.curso_id = :curso_id 
                                            GROUP BY DATE(e.fecha_envio)
                                            ORDER BY DATE(e.fecha_envio) DESC 
                                            LIMIT 5
                                        ");
                                        $stmt->execute([':curso_id' => $curso_grafico_id]);
                                        $fechas_disponibles = $stmt->fetchAll();
                                        
                                        if (!empty($fechas_disponibles)): ?>                                    <div class="alert alert-light">
                                        <i class="bi bi-calendar3"></i>
                                        <strong>Fechas disponibles para este curso:</strong>
                                        <small class="text-muted d-block mb-2">Haz clic en cualquier fecha para ver los reportes:</small>
                                        <div class="mt-2">
                                            <?php foreach ($fechas_disponibles as $fecha_disp): ?>
                                            <a href="?curso_grafico_id=<?php echo $curso_grafico_id; ?>&fecha_grafico=<?php echo $fecha_disp['fecha_disponible']; ?>&generar_graficos=1" 
                                               class="btn btn-sm btn-outline-success me-2 mb-1" 
                                               title="Ver reportes del <?php echo $fecha_disp['fecha_disponible']; ?> (<?php echo $fecha_disp['total_encuestas']; ?> encuestas)">
                                                <i class="bi bi-calendar-check"></i>
                                                <?php echo $fecha_disp['fecha_disponible']; ?>
                                                <span class="badge bg-success ms-1"><?php echo $fecha_disp['total_encuestas']; ?></span>
                                            </a>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                    <?php else: ?>
                                    <div class="alert alert-secondary">
                                        <i class="bi bi-calendar-x"></i>
                                        <strong>No hay fechas disponibles</strong>
                                        <p class="mb-0 mt-1 small">Este curso no tiene encuestas registradas en ninguna fecha. 
                                        Verifica con el administrador o selecciona otro curso.</p>
                                    </div>
                                    <?php endif;
                                    } catch (Exception $e) {
                                        // Silenciar errores de consulta de fechas disponibles
                                    }
                                    ?>
                                </div>
                            </div>
                            
                            <div class="mt-3">
                                <a href="reportes.php" class="btn btn-outline-primary me-2">
                                    <i class="bi bi-arrow-left"></i> Volver a intentar
                                </a>
                                <a href="index.php" class="btn btn-outline-secondary">
                                    <i class="bi bi-house"></i> Ir al Dashboard
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Resumen Ejecutivo -->
                <?php if (!empty($resumen_ejecutivo) && !empty($graficos_torta)): ?>
                <div class="card mb-4 border-primary">
                    <div class="card-header bg-primary text-white">
                        <h6 class="m-0 font-weight-bold">
                            <i class="bi bi-speedometer2"></i> Resumen Ejecutivo - <?php echo htmlspecialchars($fecha_grafico); ?>
                        </h6>
                    </div>
                    <div class="card-body">
                        <!-- Alertas automáticas -->
                        <?php if (!empty($resumen_ejecutivo['alertas'])): ?>
                        <div class="row mb-3">
                            <div class="col-12">
                                <?php foreach ($resumen_ejecutivo['alertas'] as $alerta): ?>
                                <div class="alert alert-<?php echo $alerta['tipo']; ?> alert-dismissible fade show" role="alert">
                                    <i class="bi bi-exclamation-triangle"></i>
                                    <strong>Alerta:</strong> <?php echo htmlspecialchars($alerta['mensaje']); ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- KPIs principales -->
                        <div class="row mb-4">
                            <div class="col-md-2">
                                <div class="card bg-light border-info h-100">
                                    <div class="card-body text-center">
                                        <h4 class="text-info"><?php echo $resumen_ejecutivo['stats']['total_encuestas_global']; ?></h4>
                                        <small class="text-muted">Encuestas</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="card bg-light border-success h-100">
                                    <div class="card-body text-center">
                                        <h4 class="text-success"><?php echo $resumen_ejecutivo['stats']['total_profesores_evaluados']; ?></h4>
                                        <small class="text-muted">Profesores</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="card bg-light border-primary h-100">
                                    <div class="card-body text-center">
                                        <h4 class="text-primary"><?php echo round($resumen_ejecutivo['stats']['promedio_general'], 1); ?></h4>
                                        <small class="text-muted">Promedio</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="card bg-light border-warning h-100">
                                    <div class="card-body text-center">
                                        <h4 class="text-warning"><?php echo $resumen_ejecutivo['percentiles']['mediana']; ?></h4>
                                        <small class="text-muted">Mediana</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="card bg-light border-secondary h-100">
                                    <div class="card-body text-center">
                                        <h4 class="text-secondary"><?php echo round($resumen_ejecutivo['stats']['desviacion_general'], 1); ?></h4>
                                        <small class="text-muted">Desv. Est.</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="card bg-light border-dark h-100">
                                    <div class="card-body text-center">
                                        <h4 class="text-dark"><?php echo $resumen_ejecutivo['stats']['total_respuestas_escala']; ?></h4>
                                        <small class="text-muted">Respuestas</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Estadísticas detalladas -->
                        <div class="row">
                            <div class="col-md-6">
                                <h6><i class="bi bi-bar-chart"></i> Estadísticas Descriptivas</h6>
                                <table class="table table-sm admin-table">
                                    <tbody>
                                        <tr>
                                            <td><strong>Rango:</strong></td>
                                            <td>
                                                <span class="badge bg-danger"><?php echo $resumen_ejecutivo['stats']['valor_minimo']; ?></span>
                                                -
                                                <span class="badge bg-success"><?php echo $resumen_ejecutivo['stats']['valor_maximo']; ?></span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td><strong>Percentil 25:</strong></td>
                                            <td><span class="badge bg-info"><?php echo $resumen_ejecutivo['percentiles']['p25']; ?></span></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Percentil 75:</strong></td>
                                            <td><span class="badge bg-info"><?php echo $resumen_ejecutivo['percentiles']['p75']; ?></span></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Coef. Variación:</strong></td>
                                            <td>
                                                <?php 
                                                $cv = $resumen_ejecutivo['stats']['promedio_general'] > 0 ? 
                                                     round(($resumen_ejecutivo['stats']['desviacion_general'] / $resumen_ejecutivo['stats']['promedio_general']) * 100, 1) : 0;
                                                $cv_color = $cv < 20 ? 'success' : ($cv < 30 ? 'warning' : 'danger');
                                                ?>
                                                <span class="badge bg-<?php echo $cv_color; ?>"><?php echo $cv; ?>%</span>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <h6><i class="bi bi-pie-chart"></i> Distribución de Respuestas</h6>
                                <table class="table table-sm admin-table">
                                    <thead>
                                        <tr>
                                            <th>Valor</th>
                                            <th>Frecuencia</th>
                                            <th>%</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($resumen_ejecutivo['distribucion'] as $dist): ?>
                                        <tr>
                                            <td>
                                                <span class="badge bg-<?php 
                                                echo $dist['valor_int'] >= 8 ? 'success' : 
                                                    ($dist['valor_int'] >= 6 ? 'warning' : 'danger'); 
                                                ?>"><?php echo $dist['valor_int']; ?></span>
                                            </td>
                                            <td><?php echo $dist['frecuencia']; ?></td>
                                            <td>
                                                <div class="progress" style="height: 15px;">
                                                    <div class="progress-bar bg-<?php 
                                                    echo $dist['valor_int'] >= 8 ? 'success' : 
                                                        ($dist['valor_int'] >= 6 ? 'warning' : 'danger'); 
                                                    ?>" style="width: <?php echo $dist['porcentaje']; ?>%">
                                                        <?php echo $dist['porcentaje']; ?>%
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Mostrar Gráficos de Torta -->
                <?php if (!empty($graficos_torta)): ?>
                <div class="card mb-4">
                    <div class="card-header">
                        <h6 class="m-0 font-weight-bold">
                            <i class="bi bi-pie-chart-fill"></i> Gráficos de Evaluación - Fecha: <?php echo htmlspecialchars($fecha_grafico); ?>
                            (<?php echo count($graficos_torta); ?> gráfico<?php echo count($graficos_torta) > 1 ? 's' : ''; ?>)
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <?php foreach ($graficos_torta as $grafico): ?>
                            <div class="col-md-6 col-lg-4 mb-4">
                                <div class="card h-100 border-<?php echo $grafico['tipo'] == 'curso' ? 'primary' : 'success'; ?>">
                                    <div class="card-header bg-<?php echo $grafico['tipo'] == 'curso' ? 'primary' : 'success'; ?> text-white text-center">
                                        <h6 class="card-title m-0">
                                            <?php echo htmlspecialchars($grafico['titulo']); ?>
                                        </h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="chart-container">
                                            <canvas id="<?php echo $grafico['id']; ?>"></canvas>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>                </div>
                <?php endif; ?>

                <!-- Estadísticas Detalladas -->
                <?php if (!empty($graficos_torta)): ?>
                <div class="card mb-4">
                    <div class="card-header">
                        <h6 class="m-0 font-weight-bold">
                            <i class="bi bi-bar-chart-line"></i> Estadísticas Detalladas
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped admin-table">
                                <thead>
                                    <tr>
                                        <th><i class="bi bi-diagram-3"></i> Tipo</th>
                                        <th><i class="bi bi-person-workspace"></i> Curso/Profesor</th>
                                        <th><i class="bi bi-file-earmark-check"></i> Encuestas</th>
                                        <th><i class="bi bi-question-circle"></i> Preguntas</th>
                                        <th><i class="bi bi-trophy"></i> Puntuación</th>
                                        <th><i class="bi bi-percent"></i> Aprovechamiento</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($graficos_torta as $grafico): ?>
                                    <tr>
                                        <td>
                                            <span class="badge bg-<?php echo $grafico['tipo'] == 'curso' ? 'primary' : 'success'; ?>">
                                                <i class="bi bi-<?php echo $grafico['tipo'] == 'curso' ? 'book' : 'person'; ?>"></i>
                                                <?php echo ucfirst($grafico['tipo']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($grafico['nombre']); ?></strong>
                                        </td>
                                        <td>
                                            <span class="badge bg-info">
                                                <?php echo $grafico['total_encuestas']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-secondary">
                                                <?php echo $grafico['num_preguntas']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <strong class="text-primary">
                                                <?php echo $grafico['puntuacion_real']; ?>
                                            </strong>
                                            <span class="text-muted">/ <?php echo $grafico['max_puntuacion']; ?></span>
                                        </td>
                                        <td>
                                            <?php 
                                            $aprovechamiento = $grafico['max_puntuacion'] > 0 ? 
                                                round(($grafico['puntuacion_real'] / $grafico['max_puntuacion']) * 100, 1) : 0;
                                            $clase_color = $aprovechamiento >= 80 ? 'success' : 
                                                          ($aprovechamiento >= 60 ? 'warning' : 'danger');
                                            ?>
                                            <div class="d-flex align-items-center">
                                                <div class="progress flex-grow-1 me-2" style="height: 20px;">
                                                    <div class="progress-bar bg-<?php echo $clase_color; ?>" 
                                                         role="progressbar" 
                                                         style="width: <?php echo $aprovechamiento; ?>%"
                                                         aria-valuenow="<?php echo $aprovechamiento; ?>" 
                                                         aria-valuemin="0" 
                                                         aria-valuemax="100">
                                                    </div>
                                                </div>
                                                <span class="badge bg-<?php echo $clase_color; ?>">
                                                    <?php echo $aprovechamiento; ?>%
                                                </span>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Resumen General -->
                        <div class="row mt-3">
                            <div class="col-md-3">
                                <div class="card bg-light border-primary">
                                    <div class="card-body text-center">
                                        <h5 class="text-primary"><?php echo count($graficos_torta); ?></h5>
                                        <small class="text-muted">Total Evaluaciones</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card bg-light border-info">
                                    <div class="card-body text-center">
                                        <h5 class="text-info">
                                            <?php echo array_sum(array_column($graficos_torta, 'total_encuestas')); ?>
                                        </h5>
                                        <small class="text-muted">Total Encuestas</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card bg-light border-success">
                                    <div class="card-body text-center">
                                        <h5 class="text-success">
                                            <?php echo array_sum(array_column($graficos_torta, 'puntuacion_real')); ?>
                                        </h5>
                                        <small class="text-muted">Puntuación Total</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card bg-light border-warning">
                                    <div class="card-body text-center">
                                        <?php 
                                        $total_real = array_sum(array_column($graficos_torta, 'puntuacion_real'));
                                        $total_max = array_sum(array_column($graficos_torta, 'max_puntuacion'));
                                        $promedio_general = $total_max > 0 ? round(($total_real / $total_max) * 100, 1) : 0;
                                        ?>
                                        <h5 class="text-warning"><?php echo $promedio_general; ?>%</h5>
                                        <small class="text-muted">Promedio General</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>                <!-- Preguntas Más Críticas del Curso/Fecha Específica -->
                

                <!-- Comentarios Cualitativos del Curso -->
                <?php if (!empty($graficos_torta) && !empty($comentarios_curso)): ?>
                <div class="card mb-4">
                    <div class="card-header">
                        <h6 class="m-0 font-weight-bold">
                            <i class="bi bi-chat-quote"></i> Comentarios del Curso - 
                            <?php echo htmlspecialchars($fecha_grafico); ?>
                            <span class="badge bg-primary ms-2"><?php echo count($comentarios_curso); ?> comentarios</span>
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered admin-table">
                                <thead>
                                    <tr>
                                        <th><i class="bi bi-calendar"></i> Fecha</th>
                                        <th><i class="bi bi-question-circle"></i> Pregunta</th>
                                        <th><i class="bi bi-chat-text"></i> Comentario</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($comentarios_curso as $comentario): ?>
                                    <tr>
                                        <td>
                                            <span class="badge bg-info">
                                                <?php echo date('d/m/Y', strtotime($comentario['fecha_envio'])); ?>
                                            </span>
                                        </td>
                                        <td class="text-wrap" style="max-width: 250px;">
                                            <small class="text-muted"><?php echo htmlspecialchars($comentario['pregunta_texto']); ?></small>
                                        </td>
                                        <td class="text-wrap" style="max-width: 400px;">
                                            <em>"<?php echo htmlspecialchars($comentario['comentario']); ?>"</em>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Comentarios Cualitativos de Profesores -->
                <?php if (!empty($graficos_torta) && !empty($comentarios_profesor)): ?>
                <div class="card mb-4">
                    <div class="card-header">
                        <h6 class="m-0 font-weight-bold">
                            <i class="bi bi-chat-quote"></i> Comentarios de Profesores - 
                            <?php echo htmlspecialchars($fecha_grafico); ?>
                            <span class="badge bg-success ms-2"><?php echo count($comentarios_profesor); ?> comentarios</span>
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered admin-table">
                                <thead>
                                    <tr>
                                        <th><i class="bi bi-calendar"></i> Fecha</th>
                                        <th><i class="bi bi-question-circle"></i> Pregunta</th>
                                        <th><i class="bi bi-person"></i> Profesor</th>
                                        <th><i class="bi bi-chat-text"></i> Comentario</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($comentarios_profesor as $comentario): ?>
                                    <tr>
                                        <td>
                                            <span class="badge bg-info">
                                                <?php echo date('d/m/Y', strtotime($comentario['fecha_envio'])); ?>
                                            </span>
                                        </td>
                                        <td class="text-wrap" style="max-width: 200px;">
                                            <small class="text-muted"><?php echo htmlspecialchars($comentario['pregunta_texto']); ?></small>
                                        </td>
                                        <td>
                                            <span class="badge bg-success">
                                                <?php echo htmlspecialchars($comentario['profesor_nombre'] ?? 'No especificado'); ?>
                                            </span>
                                        </td>
                                        <td class="text-wrap" style="max-width: 350px;">
                                            <em>"<?php echo htmlspecialchars($comentario['comentario']); ?>"</em>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                <?php if (!empty($graficos_torta) && !empty($preguntas_criticas)): ?>
                <div class="card mb-4">
                    <div class="card-header">
                        <h6 class="m-0 font-weight-bold">
                            <i class="bi bi-exclamation-triangle"></i> Preguntas Más Críticas - 
                            <?php echo htmlspecialchars($fecha_grafico); ?>
                            <span class="badge bg-primary ms-2"><?php echo count($preguntas_criticas); ?> preguntas</span>
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered admin-table">
                                <thead>
                                    <tr>
                                        <th><i class="bi bi-diagram-3"></i> Sección</th>
                                        <th><i class="bi bi-question-circle"></i> Pregunta</th>
                                        <th><i class="bi bi-file-earmark-check"></i> Respuestas</th>
                                        <th><i class="bi bi-bar-chart"></i> Promedio</th>
                                        <th><i class="bi bi-graph-up"></i> Desv. Est.</th>
                                        <th><i class="bi bi-arrow-down"></i> Resp. Bajas</th>
                                        <th><i class="bi bi-percent"></i> % Crítico</th>
                                    </tr>
                                </thead>                                <tbody>
                                    <?php foreach ($preguntas_criticas as $pregunta): ?>
                                    <tr>
                                        <td>
                                            <span class="badge bg-<?php echo $pregunta['seccion'] == 'curso' ? 'primary' : 'success'; ?>">
                                                <i class="bi bi-<?php echo $pregunta['seccion'] == 'curso' ? 'book' : 'person'; ?>"></i>
                                                <?php echo ucfirst(htmlspecialchars($pregunta['seccion'])); ?>
                                            </span>
                                        </td>
                                        <td class="text-wrap" style="max-width: 300px;">
                                            <?php echo htmlspecialchars($pregunta['texto_pregunta']); ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-info">
                                                <?php echo htmlspecialchars($pregunta['total_respuestas']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo $pregunta['promedio'] >= 7 ? 'success' : ($pregunta['promedio'] >= 5 ? 'warning' : 'danger'); ?>">
                                                <?php echo htmlspecialchars($pregunta['promedio']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-secondary">
                                                <?php echo htmlspecialchars($pregunta['desviacion_estandar'] ?? '0.00'); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-danger">
                                                <?php echo htmlspecialchars($pregunta['respuestas_bajas']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php 
                                            $porcentaje_critico = round(($pregunta['respuestas_bajas'] / $pregunta['total_respuestas']) * 100, 1);
                                            ?>
                                            <span class="badge bg-<?php echo $porcentaje_critico >= 30 ? 'danger' : ($porcentaje_critico >= 15 ? 'warning' : 'success'); ?>">
                                                <?php echo $porcentaje_critico; ?>%
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>                </div>
                <?php endif; ?>                       
            </main>
        </div>
    </div>    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Charts Scripts -->
    <script>
        // Initialize DOM when ready
        document.addEventListener('DOMContentLoaded', function() {
            initializePieCharts();
        });
        
        /**
         * Inicializa los gráficos de torta dinámicos
         */
        function initializePieCharts() {
            <?php if (!empty($graficos_torta)): ?>
            const graficos = <?php echo json_encode($graficos_torta); ?>;
            
            graficos.forEach(function(grafico) {
                try {
                    const ctx = document.getElementById(grafico.id);
                    if (!ctx) {
                        console.warn('Canvas no encontrado:', grafico.id);
                        return;
                    }
                      new Chart(ctx, {
                        type: 'doughnut',
                        data: {
                            labels: grafico.labels,
                            datasets: [{
                                data: grafico.data,
                                backgroundColor: grafico.colors, // Usar colores del array PHP
                                borderWidth: 2,
                                borderColor: '#fff'
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    position: 'bottom',
                                    labels: {
                                        padding: 20,
                                        usePointStyle: true,
                                        font: {
                                            size: 12
                                        }
                                    }
                                },
                                tooltip: {
                                    callbacks: {
                                        label: function(context) {
                                            const label = context.label || '';
                                            const value = context.parsed;
                                            // Evitar información duplicada: solo mostrar porcentaje
                                            return label + ': ' + value + '%';
                                        },
                                        title: function(context) {
                                            // Personalizar el título del tooltip
                                            return grafico.titulo.split(':')[0] || 'Evaluación';
                                        }
                                    }
                                }
                            },
                            layout: {
                                padding: 10
                            }
                        }
                    });
                    
                    console.log('✅ Gráfico creado:', grafico.id);
                    
                } catch (error) {
                    console.error('❌ Error creando gráfico:', grafico.id, error);
                }
            });
              console.log('✅ Gráficos de torta inicializados:', graficos.length);
            <?php else: ?>
            console.log('ℹ️ No hay gráficos de torta para renderizar');
            <?php endif; ?>
        }

        // Función para mejorar la experiencia al hacer clic en fechas disponibles
        function mejorarExperienciaFechas() {
            // Obtener parámetros de la URL
            const urlParams = new URLSearchParams(window.location.search);
            const cursoId = urlParams.get('curso_grafico_id');
            const fecha = urlParams.get('fecha_grafico');
            const generar = urlParams.get('generar_graficos');
            
            // Si hay parámetros en la URL, actualizar el formulario
            if (cursoId && fecha) {
                const cursoSelect = document.getElementById('curso_grafico_id');
                const fechaInput = document.getElementById('fecha_grafico');
                
                if (cursoSelect && fechaInput) {
                    cursoSelect.value = cursoId;
                    fechaInput.value = fecha;
                    
                    // Si se debe generar automáticamente y no se han generado gráficos aún
                    if (generar === '1' && !<?php echo !empty($graficos_torta) ? 'true' : 'false'; ?>) {
                        console.log('🔄 Parámetros detectados en URL, actualizando formulario...');
                    }
                }
            }
            
            // Agregar loading states a los botones de fecha
            document.querySelectorAll('a[href*="generar_graficos=1"]').forEach(link => {
                link.addEventListener('click', function(e) {
                    // Agregar indicador de carga
                    const originalText = this.innerHTML;
                    this.innerHTML = '<i class="bi bi-hourglass-split"></i> Cargando...';
                    this.classList.add('disabled');
                    
                    // Restaurar después de un tiempo (fallback)
                    setTimeout(() => {
                        this.innerHTML = originalText;
                        this.classList.remove('disabled');
                    }, 5000);
                });
            });
        }

        // Ejecutar cuando el DOM esté listo
        document.addEventListener('DOMContentLoaded', mejorarExperienciaFechas);
    </script>

</body>
</html>
