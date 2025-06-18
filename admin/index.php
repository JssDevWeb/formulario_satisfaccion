<?php
/**
 * ============================================
 * SISTEMA DE ENCUESTAS ACADÉMICAS - DASHBOARD MEJORADO
 * ============================================
 * Archivo: admin/index_nuevo.php
 * Descripción: Dashboard administrativo completo con métricas avanzadas
 * ============================================
 */

// Configuración de seguridad
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// // Iniciar sesión
// session_start();

// // Verificar autenticación
// if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
//     header('Location: login.php');
//     exit;
// }

// Incluir configuración de base de datos
require_once '../config/database.php';

try {
    $db = Database::getInstance()->getConnection();
    
    // ============================================
    // MÉTRICAS PRINCIPALES - CARDS SUPERIORES
    // ============================================
    
    // Total encuestas
    $stmt = $db->query("SELECT COUNT(*) as total FROM encuestas");
    $total_encuestas = $stmt->fetch()['total'];
    
    // Encuestas hoy
    $stmt = $db->query("SELECT COUNT(*) as hoy FROM encuestas WHERE DATE(fecha_envio) = CURDATE()");
    $encuestas_hoy = $stmt->fetch()['hoy'];
    
    // Encuestas esta semana
    $stmt = $db->query("SELECT COUNT(*) as semana FROM encuestas WHERE YEARWEEK(fecha_envio) = YEARWEEK(NOW())");
    $encuestas_semana = $stmt->fetch()['semana'];
    
    // Encuestas este mes
    $stmt = $db->query("SELECT COUNT(*) as mes FROM encuestas WHERE YEAR(fecha_envio) = YEAR(NOW()) AND MONTH(fecha_envio) = MONTH(NOW())");
    $encuestas_mes = $stmt->fetch()['mes'];
    
    // Formularios activos
    $stmt = $db->query("SELECT COUNT(*) as activos FROM formularios WHERE activo = 1 AND (fecha_fin IS NULL OR fecha_fin >= CURDATE())");
    $formularios_activos = $stmt->fetch()['activos'];
    
    // Formularios próximos a expirar (7 días)
    $stmt = $db->query("SELECT COUNT(*) as expiran FROM formularios WHERE activo = 1 AND fecha_fin BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)");
    $formularios_expiran = $stmt->fetch()['expiran'];
    
    // Cursos con formularios activos
    $stmt = $db->query("SELECT COUNT(DISTINCT curso_id) as activos FROM formularios WHERE activo = 1");
    $cursos_activos = $stmt->fetch()['activos'];
    
    // Profesores evaluados (con respuestas)
    $stmt = $db->query("SELECT COUNT(DISTINCT profesor_id) as evaluados FROM respuestas");
    $profesores_evaluados = $stmt->fetch()['evaluados'];
    
    // Tiempo medio de respuesta
    $stmt = $db->query("SELECT AVG(tiempo_completado) as promedio FROM encuestas WHERE tiempo_completado IS NOT NULL");
    $tiempo_medio = $stmt->fetch()['promedio'] ?? 0;
    
    // ============================================
    // DATOS PARA GRÁFICOS
    // ============================================
    
    // Top 5 cursos por número de encuestas
    $stmt = $db->query("
        SELECT c.nombre, COUNT(e.id) as total_encuestas
        FROM cursos c
        JOIN formularios f ON c.id = f.curso_id
        JOIN encuestas e ON f.id = e.formulario_id
        GROUP BY c.id, c.nombre
        ORDER BY total_encuestas DESC
        LIMIT 5
    ");
    $top_cursos_cantidad = $stmt->fetchAll();
    
    // Top 5 profesores por promedio de valoraciones
    $stmt = $db->query("
        SELECT p.nombre, ROUND(AVG(r.valor_int), 2) as promedio
        FROM profesores p
        JOIN respuestas r ON p.id = r.profesor_id
        JOIN preguntas pr ON r.pregunta_id = pr.id
        WHERE pr.tipo = 'escala'
        GROUP BY p.id, p.nombre
        HAVING COUNT(r.id) >= 3
        ORDER BY promedio DESC
        LIMIT 5
    ");
    $top_profesores_promedio = $stmt->fetchAll();
    
    // Distribución anonimato vs identificados
    $stmt = $db->query("
        SELECT 
            SUM(CASE WHEN es_anonima = 1 THEN 1 ELSE 0 END) as anonimas,
            SUM(CASE WHEN es_anonima = 0 THEN 1 ELSE 0 END) as identificadas,
            COUNT(*) as total
        FROM encuestas
    ");
    $distribucion_anonimato = $stmt->fetch();
    
    // ============================================
    // ÚLTIMAS 10 ENCUESTAS
    // ============================================
    
    $stmt = $db->query("
        SELECT 
            e.id,
            DATE_FORMAT(e.fecha_envio, '%d/%m/%Y %H:%i') as fecha,
            c.nombre as curso,
            f.descripcion as formulario,
            COUNT(r.id) as num_respuestas,
            CASE 
                WHEN e.tiempo_completado IS NOT NULL THEN CONCAT(FLOOR(e.tiempo_completado/60), ':', LPAD(e.tiempo_completado%60, 2, '0'))
                ELSE 'N/A'
            END as tiempo,
            CASE WHEN e.es_anonima = 1 THEN 'Anónima' ELSE 'Identificada' END as tipo
        FROM encuestas e
        JOIN formularios f ON e.formulario_id = f.id
        JOIN cursos c ON f.curso_id = c.id
        LEFT JOIN respuestas r ON e.id = r.encuesta_id
        GROUP BY e.id, e.fecha_envio, c.nombre, f.descripcion, e.tiempo_completado, e.es_anonima
        ORDER BY e.fecha_envio DESC
        LIMIT 10
    ");
    $ultimas_encuestas = $stmt->fetchAll();
    
    // ============================================
    // ALERTAS DE FORMULARIOS
    // ============================================
    
    // Formularios expirados
    $stmt = $db->query("
        SELECT f.id, f.descripcion, c.nombre as curso, f.fecha_fin
        FROM formularios f
        JOIN cursos c ON f.curso_id = c.id
        WHERE f.activo = 1 AND f.fecha_fin < CURDATE()
        ORDER BY f.fecha_fin DESC
        LIMIT 5
    ");
    $formularios_expirados = $stmt->fetchAll();
    
    // Formularios próximos a vencer
    $stmt = $db->query("
        SELECT f.id, f.descripcion, c.nombre as curso, f.fecha_fin, DATEDIFF(f.fecha_fin, CURDATE()) as dias_restantes
        FROM formularios f
        JOIN cursos c ON f.curso_id = c.id
        WHERE f.activo = 1 AND f.fecha_fin BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
        ORDER BY f.fecha_fin ASC
        LIMIT 5
    ");
    $formularios_proximos_vencer = $stmt->fetchAll();
    
} catch (Exception $e) {
    error_log("Error en dashboard: " . $e->getMessage());
    // Valores por defecto en caso de error
    $total_encuestas = $encuestas_hoy = $encuestas_semana = $encuestas_mes = 0;
    $formularios_activos = $formularios_expiran = $cursos_activos = $profesores_evaluados = 0;
    $tiempo_medio = 0;
    $top_cursos_cantidad = $top_profesores_promedio = [];
    $distribucion_anonimato = ['anonimas' => 0, 'identificadas' => 0, 'total' => 0];
    $ultimas_encuestas = $formularios_expirados = $formularios_proximos_vencer = [];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Administrativo - Sistema de Encuestas Académicas</title>
      <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Admin CSS unificado -->
    <link href="assets/css/admin.css" rel="stylesheet">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>    <div class="container-fluid">
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
                            <a class="nav-link text-white active" href="index.php">
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
                            <a class="nav-link text-white" href="reportes.php">
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
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 content-with-sidebar">                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Dashboard Administrativo</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="location.reload()">
                                <i class="bi bi-arrow-clockwise"></i> Actualizar
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Cards superiores - Métricas principales -->
                <div class="row mb-4">
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-primary shadow metric-card h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                            Total Encuestas
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php echo number_format($total_encuestas); ?>
                                        </div>
                                        <small class="text-muted">
                                            Hoy: <?php echo $encuestas_hoy; ?> | 
                                            Semana: <?php echo $encuestas_semana; ?> | 
                                            Mes: <?php echo $encuestas_mes; ?>
                                        </small>
                                    </div>
                                    <div class="col-auto">
                                        <i class="bi bi-clipboard-data fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-success shadow metric-card h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                            Formularios Activos
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php echo number_format($formularios_activos); ?>
                                        </div>
                                        <small class="text-<?php echo $formularios_expiran > 0 ? 'warning' : 'muted'; ?>">
                                            <?php echo $formularios_expiran; ?> próximos a expirar
                                        </small>
                                    </div>
                                    <div class="col-auto">
                                        <i class="bi bi-file-earmark-check fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-info shadow metric-card h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                            Cursos y Profesores
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php echo $cursos_activos; ?> / <?php echo $profesores_evaluados; ?>
                                        </div>
                                        <small class="text-muted">Cursos activos / Profesores evaluados</small>
                                    </div>
                                    <div class="col-auto">
                                        <i class="bi bi-people fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-warning shadow metric-card h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                            Tiempo Promedio
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php echo gmdate("i:s", (int)$tiempo_medio); ?>
                                        </div>
                                        <small class="text-muted">Tiempo medio de respuesta</small>
                                    </div>
                                    <div class="col-auto">
                                        <i class="bi bi-clock fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Enlaces rápidos -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card shadow">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary">Acciones Rápidas</h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-3">
                                        <a href="cursos.php" class="btn btn-primary btn-sm quick-action-btn w-100">
                                            <i class="bi bi-plus-circle me-2"></i>Crear Nuevo Curso
                                        </a>
                                    </div>
                                    <div class="col-md-3">
                                        <a href="preguntas.php" class="btn btn-success btn-sm quick-action-btn w-100">
                                            <i class="bi bi-question-circle me-2"></i>Añadir Pregunta
                                        </a>
                                    </div>
                                    <div class="col-md-3">
                                        <a href="formularios.php" class="btn btn-info btn-sm quick-action-btn w-100">
                                            <i class="bi bi-file-plus me-2"></i>Nuevo Formulario
                                        </a>
                                    </div>
                                    <div class="col-md-3">
                                        <a href="reportes.php" class="btn btn-warning btn-sm quick-action-btn w-100">
                                            <i class="bi bi-graph-up me-2"></i>Generar Reporte
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Gráficos -->
                <div class="row mb-4">
                    <!-- Top cursos por cantidad -->
                    <div class="col-xl-4 col-lg-6">
                        <div class="card shadow mb-4">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary">Top 5 Cursos (Encuestas)</h6>
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="topCursosChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Top profesores por promedio -->
                    <div class="col-xl-4 col-lg-6">
                        <div class="card shadow mb-4">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary">Top 5 Profesores (Promedio)</h6>
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="topProfesoresChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Distribución anonimato -->
                    <div class="col-xl-4 col-lg-6">
                        <div class="card shadow mb-4">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary">Distribución Anonimato</h6>
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="anonimatoChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>                <!-- Alertas -->
                <?php if (!empty($formularios_expirados) || !empty($formularios_proximos_vencer)): ?>
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card shadow alert-card">
                            <div class="card-header py-3 bg-danger text-white">
                                <h6 class="m-0 font-weight-bold">
                                    <i class="bi bi-exclamation-triangle me-2"></i>Alertas de Formularios
                                </h6>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($formularios_expirados)): ?>
                                <div class="alert alert-danger">
                                    <strong><i class="bi bi-x-circle-fill me-2"></i>Formularios Expirados:</strong>
                                    <ul class="mb-0 mt-2">
                                        <?php foreach ($formularios_expirados as $form): ?>
                                        <li class="d-flex justify-content-between align-items-center py-1">
                                            <span>
                                                <strong><?php echo htmlspecialchars($form['descripcion']); ?></strong> 
                                                - <?php echo htmlspecialchars($form['curso']); ?>
                                            </span>
                                            <span class="badge bg-dark text-white ms-2">
                                                <i class="bi bi-calendar-x me-1"></i>
                                                Expiró: <?php echo date('d/m/Y', strtotime($form['fecha_fin'])); ?>
                                            </span>
                                        </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($formularios_proximos_vencer)): ?>
                                <div class="alert alert-warning">
                                    <strong><i class="bi bi-exclamation-triangle-fill me-2"></i>Próximos a Vencer:</strong>
                                    <ul class="mb-0 mt-2">
                                        <?php foreach ($formularios_proximos_vencer as $form): ?>
                                        <?php 
                                            $dias = $form['dias_restantes'];
                                            // Determinar el color según la urgencia
                                            if ($dias <= 0) {
                                                $badge_class = 'bg-danger text-white fw-bold';
                                                $urgencia = 'VENCIDO';
                                            } elseif ($dias <= 1) {
                                                $badge_class = 'bg-danger text-white fw-bold';
                                                $urgencia = 'CRÍTICO';
                                            } elseif ($dias <= 2) {
                                                $badge_class = 'bg-warning text-dark fw-bold';
                                                $urgencia = 'URGENTE';
                                            } elseif ($dias <= 4) {
                                                $badge_class = 'bg-info text-white';
                                                $urgencia = 'PRÓXIMO';
                                            } else {
                                                $badge_class = 'bg-secondary text-white';
                                                $urgencia = 'PROGRAMADO';
                                            }
                                        ?>
                                        <li class="d-flex justify-content-between align-items-center py-1">
                                            <span>
                                                <strong><?php echo htmlspecialchars($form['descripcion']); ?></strong> 
                                                - <?php echo htmlspecialchars($form['curso']); ?>
                                            </span>
                                            <span class="badge <?php echo $badge_class; ?> ms-2">
                                                <?php echo $urgencia; ?> 
                                                (<?php echo $dias <= 0 ? 'VENCIDO' : $dias . ' día' . ($dias != 1 ? 's' : '') . ' restante' . ($dias != 1 ? 's' : ''); ?>)
                                            </span>
                                        </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Tabla de últimas encuestas -->
                <div class="row">
                    <div class="col-12">
                        <div class="card shadow mb-4">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary">Últimas 10 Encuestas</h6>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-bordered table-hover admin-table">
                                        <thead class="table-dark">
                                            <tr>
                                                <th>ID</th>
                                                <th>Fecha</th>
                                                <th>Curso</th>
                                                <th>Formulario</th>
                                                <th>Respuestas</th>
                                                <th>Tiempo</th>
                                                <th>Tipo</th>
                                                <th>Acción</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($ultimas_encuestas as $encuesta): ?>
                                            <tr>
                                                <td><?php echo $encuesta['id']; ?></td>
                                                <td><?php echo $encuesta['fecha']; ?></td>
                                                <td><?php echo htmlspecialchars($encuesta['curso']); ?></td>
                                                <td><?php echo htmlspecialchars($encuesta['formulario']); ?></td>
                                                <td>
                                                    <span class="badge bg-<?php echo $encuesta['num_respuestas'] > 0 ? 'success' : 'warning'; ?>">
                                                        <?php echo $encuesta['num_respuestas']; ?>
                                                    </span>
                                                </td>
                                                <td><?php echo $encuesta['tiempo']; ?></td>
                                                <td>
                                                    <span class="badge bg-<?php echo $encuesta['tipo'] == 'Anónima' ? 'secondary' : 'primary'; ?>">
                                                        <?php echo $encuesta['tipo']; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <a href="reportes.php?encuesta_id=<?php echo $encuesta['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                        <i class="bi bi-eye"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </main>
        </div>
    </div>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Charts Scripts -->
    <script>
        // Top Cursos Chart
        const topCursosCtx = document.getElementById('topCursosChart').getContext('2d');
        new Chart(topCursosCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode(array_column($top_cursos_cantidad, 'nombre')); ?>,
                datasets: [{
                    label: 'Encuestas',
                    data: <?php echo json_encode(array_column($top_cursos_cantidad, 'total_encuestas')); ?>,
                    backgroundColor: 'rgba(54, 162, 235, 0.8)',
                    borderColor: 'rgba(54, 162, 235, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });        // Top Profesores Chart
        const topProfesoresCtx = document.getElementById('topProfesoresChart').getContext('2d');
        new Chart(topProfesoresCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode(array_column($top_profesores_promedio, 'nombre')); ?>,
                datasets: [{
                    label: 'Promedio',
                    data: <?php echo json_encode(array_column($top_profesores_promedio, 'promedio')); ?>,
                    backgroundColor: 'rgba(75, 192, 192, 0.8)',
                    borderColor: 'rgba(75, 192, 192, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: {
                        beginAtZero: true,
                        max: 10
                    }
                }
            }
        });

        // Anonimato Chart
        const anonimatoCtx = document.getElementById('anonimatoChart').getContext('2d');
        new Chart(anonimatoCtx, {
            type: 'doughnut',
            data: {
                labels: ['Anónimas', 'Identificadas'],
                datasets: [{
                    data: [<?php echo $distribucion_anonimato['anonimas']; ?>, <?php echo $distribucion_anonimato['identificadas']; ?>],
                    backgroundColor: [
                        'rgba(255, 206, 86, 0.8)',
                        'rgba(153, 102, 255, 0.8)'
                    ],
                    borderColor: [
                        'rgba(255, 206, 86, 1)',
                        'rgba(153, 102, 255, 1)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }        });
    </script>
</body>
</html>
