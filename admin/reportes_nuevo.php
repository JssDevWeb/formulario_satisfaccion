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
    ");
    $cursos_disponibles = $stmt->fetchAll();
    
    // ============================================
    // PREGUNTAS MÁS CRÍTICAS
    // ============================================
    $stmt = $db->prepare("
        SELECT 
            pr.texto as texto_pregunta,
            pr.seccion,
            COUNT(*) as total_respuestas,
            ROUND(AVG(r.valor_int), 2) as promedio,
            ROUND(STDDEV(r.valor_int), 2) as desviacion_estandar,
            COUNT(CASE WHEN r.valor_int <= 5 THEN 1 END) as respuestas_bajas
        FROM encuestas e
        JOIN respuestas r ON e.id = r.encuesta_id
        JOIN preguntas pr ON r.pregunta_id = pr.id
        WHERE pr.tipo = 'escala'
        GROUP BY pr.id, pr.texto, pr.seccion
        HAVING COUNT(*) >= 1
        ORDER BY promedio ASC, respuestas_bajas DESC
        LIMIT 10
    ");
    $stmt->execute();
    $preguntas_criticas = $stmt->fetchAll();
    
    // ============================================
    // COMENTARIOS CUALITATIVOS MÁS RECIENTES
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
            AND r.valor_text IS NOT NULL 
            AND r.valor_text != '' 
            AND CHAR_LENGTH(TRIM(r.valor_text)) > 5
        ORDER BY e.fecha_envio DESC
        LIMIT 10
    ");
    $stmt->execute();
    $comentarios_recientes = $stmt->fetchAll();
    
    // ============================================
    // GRÁFICOS DE TORTA DINÁMICOS
    // ============================================
    $graficos_torta = [];
    if ($generar_graficos) {
        $graficos_torta = generarGraficosCursoYProfesores($db, $curso_grafico_id, $fecha_grafico);
    }
    
} catch (Exception $e) {
    error_log("Error en reportes: " . $e->getMessage());
    $preguntas_criticas = [];
    $comentarios_recientes = [];
    $graficos_torta = [];
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
        $stmt = $db->prepare("
            SELECT c.id, c.nombre, c.codigo
            FROM cursos c
            WHERE c.id = :curso_id
        ");
        $stmt->execute([':curso_id' => $curso_id]);
        $curso = $stmt->fetch();
        
        if (!$curso) return null;
        
        // Contar total de encuestas
        $stmt = $db->prepare("
            SELECT COUNT(DISTINCT e.id) as total_encuestas
            FROM encuestas e
            JOIN respuestas r ON e.id = r.encuesta_id
            JOIN preguntas pr ON r.pregunta_id = pr.id
            WHERE e.curso_id = :curso_id
            AND DATE(e.fecha_envio) = :fecha
            AND pr.seccion = 'curso' AND pr.tipo = 'escala'
        ");
        $stmt->execute([':curso_id' => $curso_id, ':fecha' => $fecha]);
        $total_encuestas = $stmt->fetch()['total_encuestas'];
        
        if ($total_encuestas == 0) return null;
        
        // Obtener respuestas por valor
        $stmt = $db->prepare("
            SELECT 
                r.valor_int,
                COUNT(*) as cantidad_respuestas
            FROM encuestas e
            JOIN respuestas r ON e.id = r.encuesta_id
            JOIN preguntas pr ON r.pregunta_id = pr.id
            WHERE e.curso_id = :curso_id
            AND DATE(e.fecha_envio) = :fecha
            AND pr.seccion = 'curso' AND pr.tipo = 'escala'
            GROUP BY r.valor_int
            ORDER BY r.valor_int DESC
        ");
        $stmt->execute([':curso_id' => $curso_id, ':fecha' => $fecha]);
        $resultados = $stmt->fetchAll();
        
        // Calcular distribución porcentual
        $total_respuestas = array_sum(array_column($resultados, 'cantidad_respuestas'));
        $distribucion = ['Excelente' => 0, 'Bueno' => 0, 'Correcto' => 0, 'Regular' => 0, 'Deficiente' => 0];
        
        foreach ($resultados as $res) {
            $porcentaje = round(($res['cantidad_respuestas'] / $total_respuestas) * 100, 1);
            
            if ($res['valor_int'] >= 9) {
                $distribucion['Excelente'] += $porcentaje;
            } elseif ($res['valor_int'] >= 7) {
                $distribucion['Bueno'] += $porcentaje;
            } elseif ($res['valor_int'] >= 5) {
                $distribucion['Correcto'] += $porcentaje;
            } elseif ($res['valor_int'] >= 3) {
                $distribucion['Regular'] += $porcentaje;
            } else {
                $distribucion['Deficiente'] += $porcentaje;
            }
        }
        
        return [
            'id' => 'chart_curso_' . $curso_id,
            'titulo' => 'Curso: ' . $curso['nombre'],
            'tipo' => 'curso',
            'labels' => array_keys($distribucion),
            'data' => array_values($distribucion)
        ];
        
    } catch (Exception $e) {
        error_log("Error generando gráfico de curso: " . $e->getMessage());
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
            $grafico = generarGraficoProfesorEspecifico($db, $profesor['id'], $curso_id, $fecha);
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
function generarGraficoProfesorEspecifico($db, $profesor_id, $curso_id, $fecha) {
    try {
        // Obtener información del profesor
        $stmt = $db->prepare("SELECT nombre FROM profesores WHERE id = :profesor_id");
        $stmt->execute([':profesor_id' => $profesor_id]);
        $profesor = $stmt->fetch();
        
        if (!$profesor) return null;
        
        // Contar total de encuestas
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
        
        // Obtener respuestas por valor
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
        ");
        $stmt->execute([':curso_id' => $curso_id, ':profesor_id' => $profesor_id, ':fecha' => $fecha]);
        $resultados = $stmt->fetchAll();
        
        // Calcular distribución porcentual
        $total_respuestas = array_sum(array_column($resultados, 'cantidad_respuestas'));
        $distribucion = ['Excelente' => 0, 'Bueno' => 0, 'Correcto' => 0, 'Regular' => 0, 'Deficiente' => 0];
        
        foreach ($resultados as $res) {
            $porcentaje = round(($res['cantidad_respuestas'] / $total_respuestas) * 100, 1);
            
            if ($res['valor_int'] >= 9) {
                $distribucion['Excelente'] += $porcentaje;
            } elseif ($res['valor_int'] >= 7) {
                $distribucion['Bueno'] += $porcentaje;
            } elseif ($res['valor_int'] >= 5) {
                $distribucion['Correcto'] += $porcentaje;
            } elseif ($res['valor_int'] >= 3) {
                $distribucion['Regular'] += $porcentaje;
            } else {
                $distribucion['Deficiente'] += $porcentaje;
            }
        }
        
        return [
            'id' => 'chart_profesor_' . $profesor_id,
            'titulo' => 'Prof: ' . $profesor['nombre'],
            'tipo' => 'profesor',
            'labels' => array_keys($distribucion),
            'data' => array_values($distribucion)
        ];
        
    } catch (Exception $e) {
        error_log("Error generando gráfico de profesor: " . $e->getMessage());
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
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-6">
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
                            <div class="col-md-4">
                                <label for="fecha_grafico" class="form-label">Fecha de Encuesta</label>
                                <input type="date" class="form-control" id="fecha_grafico" name="fecha_grafico" 
                                       value="<?php echo htmlspecialchars($fecha_grafico); ?>" required>
                            </div>
                            <div class="col-md-2">
                                <div class="d-grid h-100 align-items-end">
                                    <button type="submit" name="generar_graficos" value="1" class="btn btn-primary">
                                        <i class="bi bi-pie-chart"></i> Generar
                                    </button>
                                </div>
                            </div>
                            <div class="col-12 mt-2">
                                <small class="text-muted">
                                    <i class="bi bi-info-circle"></i> 
                                    Se generarán gráficos para el curso seleccionado y todos sus profesores evaluados en la fecha especificada.
                                </small>
                            </div>
                        </form>
                    </div>
                </div>

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
                    </div>
                </div>
                <?php endif; ?>

                <!-- Preguntas Más Críticas -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h6 class="m-0 font-weight-bold">
                            <i class="bi bi-exclamation-triangle"></i> Preguntas Más Críticas
                        </h6>
                    </div>                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>Pregunta</th>
                                        <th>Sección</th>
                                        <th>Respuestas</th>
                                        <th>Promedio</th>
                                        <th>Desv. Est.</th>
                                        <th>Resp. Bajas</th>
                                        <th>% Crítico</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($preguntas_criticas as $pregunta): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($pregunta['texto_pregunta']); ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo $pregunta['seccion'] == 'curso' ? 'info' : 'success'; ?>">
                                                <?php echo ucfirst(htmlspecialchars($pregunta['seccion'])); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($pregunta['total_respuestas']); ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo $pregunta['promedio'] >= 7 ? 'success' : ($pregunta['promedio'] >= 5 ? 'warning' : 'danger'); ?>">
                                                <?php echo htmlspecialchars($pregunta['promedio']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($pregunta['desviacion_estandar'] ?? '0.00'); ?></td>
                                        <td><?php echo htmlspecialchars($pregunta['respuestas_bajas']); ?></td>
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
                    </div>
                </div>

                <!-- Comentarios Cualitativos Más Recientes -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h6 class="m-0 font-weight-bold">
                            <i class="bi bi-chat-quote"></i> Comentarios Cualitativos Más Recientes
                        </h6>
                    </div>
                    <div class="card-body">
                        <?php if (empty($comentarios_recientes)): ?>
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle"></i> No se encontraron comentarios cualitativos recientes.
                            </div>
                        <?php else: ?>                            <div class="table-responsive">
                                <table class="table table-bordered">
                                    <thead>
                                        <tr>
                                            <th>Fecha</th>
                                            <th>Pregunta</th>
                                            <th>Curso</th>
                                            <th>Profesor</th>
                                            <th>Comentario</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($comentarios_recientes as $comentario): ?>
                                        <tr>
                                            <td><?php echo date('d/m/Y', strtotime($comentario['fecha_envio'])); ?></td>
                                            <td><?php echo htmlspecialchars($comentario['pregunta_texto']); ?></td>
                                            <td><?php echo htmlspecialchars($comentario['curso_nombre']); ?></td>
                                            <td>
                                                <?php if ($comentario['profesor_nombre']): ?>
                                                    <?php echo htmlspecialchars($comentario['profesor_nombre']); ?>
                                                <?php else: ?>
                                                    <span class="text-muted">General</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($comentario['comentario']); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

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
                                backgroundColor: [
                                    '#28a745', // Excelente - Verde
                                    '#17a2b8', // Bueno - Azul claro
                                    '#ffc107', // Correcto - Amarillo
                                    '#fd7e14', // Regular - Naranja
                                    '#dc3545'  // Deficiente - Rojo
                                ],
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
                                            return label + ': ' + value + '%';
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
    </script>

</body>
</html>
