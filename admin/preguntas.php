<?php
/**
 * Panel de Administración - Gestión de Preguntas
 * Sistema de Encuestas Académicas
 */

session_start();

// // Verificar autenticación
// if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
//     header('Location: login.php');
//     exit();
// }

require_once '../config/database.php';

// Procesar acciones
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $seccion_filtro = $_POST['seccion_filtro'] ?? ''; // Mantener filtro activo
    
    try {
        $db = Database::getInstance();
        $pdo = $db->getConnection();
        
        switch ($action) {
            case 'create':
                $stmt = $pdo->prepare("INSERT INTO preguntas (texto, seccion, tipo, orden, es_obligatoria, activa) VALUES (?, ?, ?, ?, ?, ?)");
                $result = $stmt->execute([
                    $_POST['texto'],
                    $_POST['seccion'],
                    $_POST['tipo'],
                    $_POST['orden'],
                    isset($_POST['es_obligatoria']) ? 1 : 0,
                    isset($_POST['activa']) ? 1 : 0
                ]);
                
                if ($result) {
                    $message = "Pregunta creada exitosamente";
                    $message_type = "success";
                }
                break;
                
            case 'update':
                $stmt = $pdo->prepare("UPDATE preguntas SET texto = ?, seccion = ?, tipo = ?, orden = ?, es_obligatoria = ?, activa = ? WHERE id = ?");
                $result = $stmt->execute([
                    $_POST['texto'],
                    $_POST['seccion'],
                    $_POST['tipo'],
                    $_POST['orden'],
                    isset($_POST['es_obligatoria']) ? 1 : 0,
                    isset($_POST['activa']) ? 1 : 0,
                    $_POST['id']
                ]);
                
                if ($result) {
                    $message = "Pregunta actualizada exitosamente";
                    $message_type = "success";
                }
                break;
                
            case 'delete':
                $stmt = $pdo->prepare("DELETE FROM preguntas WHERE id = ?");
                $result = $stmt->execute([$_POST['id']]);
                
                if ($result) {
                    $message = "Pregunta eliminada exitosamente";
                    $message_type = "success";
                }
                break;
                
            case 'toggle_status':
                $stmt = $pdo->prepare("UPDATE preguntas SET activa = NOT activa WHERE id = ?");
                $result = $stmt->execute([$_POST['id']]);
                
                if ($result) {
                    $message = "Estado de la pregunta actualizado";
                    $message_type = "success";
                }
                break;
                
            case 'reorder':
                // Actualizar orden de múltiples preguntas
                foreach ($_POST['orden_preguntas'] as $id => $orden) {
                    $stmt = $pdo->prepare("UPDATE preguntas SET orden = ? WHERE id = ?");
                    $stmt->execute([$orden, $id]);
                }
                $message = "Orden de preguntas actualizado";
                $message_type = "success";
                break;
        }        // REDIRIGIR MANTENIENDO EL FILTRO ACTIVO Y LA PESTAÑA
        if ($message && $message_type === 'success') {
            $redirect_url = 'preguntas.php';
            $params = [];
            
            // Determinar la pestaña activa basándose en múltiples fuentes
            $tab_para_redirigir = '';
            
            // Prioridad 1: Tab explícito desde el formulario
            if (!empty($_POST['tab_actual'])) {
                $tab_para_redirigir = $_POST['tab_actual'];
            }
            // Prioridad 2: Sección de la pregunta procesada (para create/update)
            elseif (!empty($_POST['seccion'])) {
                $tab_para_redirigir = $_POST['seccion'];
            }
            // Prioridad 3: Filtro de sección activo
            elseif (!empty($seccion_filtro)) {
                $tab_para_redirigir = $seccion_filtro;
            }
            // Prioridad 4: Tab desde URL
            elseif (!empty($_GET['tab'])) {
                $tab_para_redirigir = $_GET['tab'];
            }
            // Fallback: curso por defecto
            else {
                $tab_para_redirigir = 'curso';
            }
            
            // Agregar parámetros de redirección
            if (!empty($seccion_filtro)) {
                $params['seccion'] = $seccion_filtro;
            }
            
            if (!empty($tab_para_redirigir)) {
                $params['tab'] = $tab_para_redirigir;
            }
            
            $params['msg'] = $message;
            $params['type'] = $message_type;
            
            if (!empty($params)) {
                $redirect_url .= '?' . http_build_query($params);
            }
            
            header('Location: ' . $redirect_url);
            exit();
        }
        
    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
        $message_type = "error";        // También redirigir en caso de error manteniendo filtro y pestaña
        if (!empty($seccion_filtro) || !empty($_POST['tab_actual']) || !empty($_GET['tab'])) {
            $params = [];
            
            if (!empty($seccion_filtro)) {
                $params['seccion'] = $seccion_filtro;
            }
            
            // Determinar tab para error con la misma lógica
            $tab_para_error = $_POST['tab_actual'] ?? $_POST['seccion'] ?? $seccion_filtro ?? $_GET['tab'] ?? 'curso';
            $params['tab'] = $tab_para_error;
            
            $params['msg'] = $message;
            $params['type'] = $message_type;
            
            $redirect_url = 'preguntas.php?' . http_build_query($params);
            header('Location: ' . $redirect_url);
            exit();
        }
    }
}

// Obtener lista de preguntas
try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();
    
// Obtener filtro de sección y mensajes de la URL
$seccion_filtro = $_GET['seccion'] ?? '';
$message = $_GET['msg'] ?? $message;
$message_type = $_GET['type'] ?? $message_type;

// Determinar pestaña activa - si no hay filtro específico, detectar por contexto
$tab_activa = $seccion_filtro;
if (!$tab_activa) {
    // Si no hay filtro, intentar detectar por el contexto de la operación
    if (isset($_GET['tab'])) {
        $tab_activa = $_GET['tab'];
    } else {
        $tab_activa = 'curso'; // Por defecto
    }
}
    $where_clause = $seccion_filtro ? "WHERE seccion = :seccion" : "";
    
    $stmt = $pdo->prepare("SELECT p.*, 
                                  COUNT(DISTINCT r.id) as total_respuestas
                           FROM preguntas p
                           LEFT JOIN respuestas r ON p.id = r.pregunta_id
                           $where_clause
                           GROUP BY p.id
                           ORDER BY p.seccion, p.orden, p.id");
    
    if ($seccion_filtro) {
        $stmt->execute([':seccion' => $seccion_filtro]);
    } else {
        $stmt->execute();
    }
    
    $preguntas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $preguntas = [];
    $message = "Error al cargar preguntas: " . $e->getMessage();
    $message_type = "error";
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Preguntas - Sistema de Encuestas</title>    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/admin.css" rel="stylesheet">
    <style>
        /* Estilos para el modo de reordenamiento */
        .sortable-active tr {
            transition: all 0.2s ease;
        }
        
        .sortable-active tr:hover {
            background-color: rgba(0, 123, 255, 0.1) !important;
            transform: scale(1.01);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        
        .sortable-active tr[draggable="true"] {
            cursor: grab;
        }
        
        .sortable-active tr[draggable="true"]:active {
            cursor: grabbing;
        }
        
        .drag-handle {
            cursor: grab;
            transition: all 0.2s ease;
        }
        
        .drag-handle:hover {
            color: #0d6efd !important;
            transform: scale(1.2);
        }
        
        /* Animación para el badge de orden */
        .badge {
            transition: all 0.3s ease;
        }
        
        .sortable-active .badge {
            background-color: #ffc107 !important;
            color: #000 !important;
            animation: pulse 1.5s infinite;
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }
        
        /* Feedback visual durante drag */
        tr[draggable="true"]:hover {
            background-color: rgba(0, 123, 255, 0.05);
        }
        
        /* Mejorar visibilidad del handle */
        .drag-handle.d-none {
            display: none !important;
        }
        
        .drag-handle:not(.d-none) {
            display: inline-block !important;
            color: #6c757d;
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
                        </li>                        <li class="nav-item">
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
                            <a class="nav-link text-white active" href="preguntas.php">
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
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 content-with-sidebar">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Gestión de Preguntas</h1>
                    <div>
                        <button type="button" class="btn btn-outline-primary me-2" onclick="toggleReorderMode(event)">
                            <i class="bi bi-arrow-up-down me-2"></i>Reordenar
                        </button>
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#preguntaModal">
                            <i class="bi bi-plus-lg me-2"></i>Nueva Pregunta
                        </button>
                    </div>
                </div>                <!-- Filtros -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <label for="filtro_seccion" class="form-label">Filtrar por sección:</label>
                        <div class="input-group">
                            <select class="form-select" id="filtro_seccion" onchange="filtrarPorSeccion()">
                                <option value="">Todas las secciones</option>
                                <option value="curso" <?php echo $seccion_filtro === 'curso' ? 'selected' : ''; ?>>Curso</option>
                                <option value="profesor" <?php echo $seccion_filtro === 'profesor' ? 'selected' : ''; ?>>Profesor</option>
                            </select>
                            <?php if ($seccion_filtro): ?>
                            <button class="btn btn-outline-secondary" type="button" onclick="limpiarFiltro()" title="Limpiar filtro">
                                <i class="bi bi-x-lg"></i>
                            </button>
                            <?php endif; ?>
                        </div>
                        <?php if ($seccion_filtro): ?>
                        <small class="text-primary">
                            <i class="bi bi-filter"></i>
                            Mostrando solo preguntas de: <strong><?php echo ucfirst($seccion_filtro); ?></strong>
                        </small>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Alertas -->
                <?php if ($message): ?>
                <div class="alert alert-<?php echo $message_type === 'error' ? 'danger' : $message_type; ?> alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>                <!-- Tabs por sección -->
                <ul class="nav nav-tabs" id="seccionTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link <?php echo $tab_activa === 'curso' ? 'active' : ''; ?>" 
                                id="curso-tab" data-bs-toggle="tab" data-bs-target="#curso" type="button" role="tab"
                                onclick="cambiarTab('curso')">
                            <i class="bi bi-book me-2"></i>Preguntas de Curso
                            <?php 
                            $count_curso = count(array_filter($preguntas, function($p) { return $p['seccion'] === 'curso'; }));
                            echo " <span class='badge bg-primary'>$count_curso</span>";
                            ?>
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link <?php echo $tab_activa === 'profesor' ? 'active' : ''; ?>" 
                                id="profesor-tab" data-bs-toggle="tab" data-bs-target="#profesor" type="button" role="tab"
                                onclick="cambiarTab('profesor')">
                            <i class="bi bi-person me-2"></i>Preguntas de Profesor
                            <?php 
                            $count_profesor = count(array_filter($preguntas, function($p) { return $p['seccion'] === 'profesor'; }));
                            echo " <span class='badge bg-primary'>$count_profesor</span>";
                            ?>
                        </button>
                    </li>
                </ul>                <div class="tab-content" id="seccionTabsContent">
                    <!-- Preguntas de Curso -->
                    <div class="tab-pane fade <?php echo $tab_activa === 'curso' ? 'show active' : ''; ?>" 
                         id="curso" role="tabpanel">                        <?php
                        $preguntas_curso = array_filter($preguntas, function($p) { return $p['seccion'] === 'curso'; });
                        renderPreguntasSeccion($preguntas_curso, 'curso', $seccion_filtro);
                        ?>
                    </div>

                    <!-- Preguntas de Profesor -->
                    <div class="tab-pane fade <?php echo $tab_activa === 'profesor' ? 'show active' : ''; ?>" 
                         id="profesor" role="tabpanel">
                        <?php
                        $preguntas_profesor = array_filter($preguntas, function($p) { return $p['seccion'] === 'profesor'; });
                        renderPreguntasSeccion($preguntas_profesor, 'profesor', $seccion_filtro);
                        ?>
                    </div>
                </div>
            </main>
        </div>
    </div>    <?php
    function renderPreguntasSeccion($preguntas_seccion, $seccion, $seccion_filtro = '') {
        if (empty($preguntas_seccion)):
    ?>
    <div class="card mt-3">
        <div class="card-body text-center py-5">
            <i class="bi bi-question-circle display-1 text-muted"></i>
            <p class="text-muted mt-3">No hay preguntas de <?php echo $seccion; ?> registradas</p>
        </div>
    </div>
    <?php else: ?>
    <div class="card mt-3">
        <div class="card-header">
            <h6 class="card-title mb-0">Preguntas de <?php echo ucfirst($seccion); ?> (<?php echo count($preguntas_seccion); ?>)</h6>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0 admin-table preguntas-table">
                    <thead class="table-light">
                        <tr>
                            <th width="50">Orden</th>
                            <th>Pregunta</th>
                            <th>Tipo</th>
                            <th>Obligatoria</th>
                            <th>Respuestas</th>
                            <th>Estado</th>
                            <th width="150">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($preguntas_seccion as $pregunta): ?>
                        <tr data-pregunta-id="<?php echo $pregunta['id']; ?>">
                            <td>
                                <span class="badge bg-secondary"><?php echo $pregunta['orden']; ?></span>
                                <i class="bi bi-grip-vertical drag-handle text-muted ms-2 d-none"></i>
                            </td>
                            <td>
                                <div class="fw-bold"><?php echo htmlspecialchars($pregunta['texto']); ?></div>
                            </td>
                            <td>
                                <span class="badge bg-<?php echo $pregunta['tipo'] === 'escala' ? 'primary' : 'info'; ?>">
                                    <?php echo ucfirst($pregunta['tipo']); ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge bg-<?php echo $pregunta['es_obligatoria'] ? 'warning' : 'secondary'; ?>">
                                    <?php echo $pregunta['es_obligatoria'] ? 'Sí' : 'No'; ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge bg-success"><?php echo $pregunta['total_respuestas']; ?></span>
                            </td>
                            <td>
                                <span class="badge bg-<?php echo $pregunta['activa'] ? 'success' : 'secondary'; ?>">
                                    <?php echo $pregunta['activa'] ? 'Activa' : 'Inactiva'; ?>
                                </span>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm" role="group">
                                    <button type="button" class="btn btn-outline-primary" onclick="editarPregunta(<?php echo htmlspecialchars(json_encode($pregunta)); ?>)">
                                        <i class="bi bi-pencil"></i>
                                    </button>                                    <form method="POST" class="form-inline" onsubmit="return confirmarCambioEstado('<?php echo $seccion; ?>')">
                                        <input type="hidden" name="action" value="toggle_status">
                                        <input type="hidden" name="id" value="<?php echo $pregunta['id']; ?>">
                                        <input type="hidden" name="seccion_filtro" value="<?php echo htmlspecialchars($seccion_filtro ?? ''); ?>">
                                        <input type="hidden" name="tab_actual" value="<?php echo htmlspecialchars($tab_activa ?? 'curso'); ?>">
                                        <button type="submit" class="btn btn-outline-warning">
                                            <i class="bi bi-<?php echo $pregunta['activa'] ? 'pause' : 'play'; ?>"></i>
                                        </button>
                                    </form>
                                    <form method="POST" class="form-inline" onsubmit="return confirmarEliminacion('<?php echo addslashes($pregunta['texto']); ?>', '<?php echo $seccion; ?>')">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo $pregunta['id']; ?>">
                                        <input type="hidden" name="seccion_filtro" value="<?php echo htmlspecialchars($seccion_filtro ?? ''); ?>">
                                        <input type="hidden" name="tab_actual" value="<?php echo htmlspecialchars($tab_activa ?? 'curso'); ?>">
                                        <button type="submit" class="btn btn-outline-danger">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php 
        endif;
    }
    ?>

    <!-- Modal para crear/editar pregunta -->
    <div class="modal fade" id="preguntaModal" tabindex="-1" aria-labelledby="preguntaModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST" id="preguntaForm">
                    <div class="modal-header">
                        <h5 class="modal-title" id="preguntaModalLabel">Nueva Pregunta</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>                    <div class="modal-body">                        <input type="hidden" name="action" id="form_action" value="create">
                        <input type="hidden" name="id" id="pregunta_id">
                        <input type="hidden" name="seccion_filtro" value="<?php echo htmlspecialchars($seccion_filtro ?? ''); ?>">
                        <input type="hidden" name="tab_actual" id="tab_actual" value="<?php echo htmlspecialchars($tab_activa ?? 'curso'); ?>">
                        
                        <div class="row">
                            <div class="col-md-8">
                                <div class="mb-3">
                                    <label for="seccion" class="form-label">Sección *</label>
                                    <select class="form-select" id="seccion" name="seccion" required>
                                        <option value="">Seleccionar sección...</option>
                                        <option value="curso">Curso</option>
                                        <option value="profesor">Profesor</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="tipo" class="form-label">Tipo *</label>
                                    <select class="form-select" id="tipo" name="tipo" required>
                                        <option value="">Seleccionar tipo...</option>
                                        <option value="escala">Escala (1-10)</option>
                                        <option value="texto">Texto libre</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="texto" class="form-label">Texto de la Pregunta *</label>
                            <textarea class="form-control" id="texto" name="texto" rows="3" required placeholder="Escriba aquí el texto de la pregunta..."></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="orden" class="form-label">Orden</label>
                                    <input type="number" class="form-control" id="orden" name="orden" min="1" value="1">
                                    <div class="form-text">Orden de aparición en el formulario</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <div class="form-check">
                                        <input type="checkbox" class="form-check-input" id="es_obligatoria" name="es_obligatoria" checked>
                                        <label class="form-check-label" for="es_obligatoria">
                                            Pregunta obligatoria
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input type="checkbox" class="form-check-input" id="activa" name="activa" checked>
                                        <label class="form-check-label" for="activa">
                                            Pregunta activa
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Guardar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
      <script>
    // Variables globales
    let reorderMode = false;
    let sortable = null;    function editarPregunta(pregunta) {
        document.getElementById('form_action').value = 'update';
        document.getElementById('pregunta_id').value = pregunta.id;
        document.getElementById('seccion').value = pregunta.seccion;
        document.getElementById('tipo').value = pregunta.tipo;
        document.getElementById('texto').value = pregunta.texto;
        document.getElementById('orden').value = pregunta.orden;
        document.getElementById('es_obligatoria').checked = pregunta.es_obligatoria == 1;
        document.getElementById('activa').checked = pregunta.activa == 1;
        
        // Actualizar el tab actual basándose en la pestaña activa o la sección de la pregunta
        const urlParams = new URLSearchParams(window.location.search);
        const tabActual = urlParams.get('tab') || pregunta.seccion || 'curso';
        document.getElementById('tab_actual').value = tabActual;
        
        const contexto = pregunta.seccion === 'curso' ? 'CURSO' : 'PROFESOR';
        document.getElementById('preguntaModalLabel').textContent = `Editar Pregunta de ${contexto}`;
        
        // Agregar badge de contexto en el modal
        const modalHeader = document.querySelector('#preguntaModal .modal-header');
        const existingBadge = modalHeader.querySelector('.context-badge');
        if (existingBadge) existingBadge.remove();
        
        const contextBadge = document.createElement('span');
        contextBadge.className = `badge bg-${pregunta.seccion === 'curso' ? 'primary' : 'info'} context-badge ms-2`;
        contextBadge.textContent = contexto;
        modalHeader.querySelector('h5').appendChild(contextBadge);
        
        const modal = new bootstrap.Modal(document.getElementById('preguntaModal'));
        modal.show();
        
        console.log(`🔧 Editando pregunta de ${contexto}, tab actual: ${tabActual}`);
    }

    function filtrarPorSeccion() {
        const seccion = document.getElementById('filtro_seccion').value;
        window.location.href = `preguntas.php${seccion ? '?seccion=' + seccion : ''}`;
    }

    function limpiarFiltro() {
        window.location.href = 'preguntas.php';
    }// Mejorar cambio de tabs manteniendo filtro
    function cambiarTab(seccion) {
        const urlParams = new URLSearchParams(window.location.search);
        urlParams.set('tab', seccion);
        
        // Si hay filtro de sección, mantenerlo
        const filtroActual = urlParams.get('seccion');
        if (filtroActual) {
            urlParams.set('seccion', filtroActual);
        }
        
        // Actualizar URL sin recargar la página
        window.history.replaceState({}, '', `${window.location.pathname}?${urlParams.toString()}`);
        
        // Actualizar el filtro select si es necesario
        const filtroSelect = document.getElementById('filtro_seccion');
        if (filtroSelect && !filtroActual) {
            filtroSelect.value = seccion;
        }
        
        // Mostrar feedback visual del filtro activo
        mostrarFiltroActivo(seccion);
        
        console.log(`🏷️ Cambiado a pestaña: ${seccion.toUpperCase()}`);
    }    function mostrarFiltroActivo(seccion) {
        // Remover indicadores anteriores
        const indicadores = document.querySelectorAll('.filtro-activo-indicator');
        indicadores.forEach(ind => ind.remove());
        
        // Verificar si hay filtro real o solo pestaña activa
        const urlParams = new URLSearchParams(window.location.search);
        const tieneFiltroPorSeccion = urlParams.get('seccion');
        
        if (tieneFiltroPorSeccion) {
            // Agregar indicador visual para filtro real
            const indicator = document.createElement('div');
            indicator.className = 'alert alert-info filtro-activo-indicator mt-2 py-2';
            indicator.innerHTML = `
                <i class="bi bi-filter me-2"></i>
                <strong>Filtro activo:</strong> Mostrando solo preguntas de ${seccion === 'curso' ? 'Curso' : 'Profesor'}
                <button type="button" class="btn btn-sm btn-outline-primary ms-2" onclick="limpiarFiltro()">
                    <i class="bi bi-x"></i> Limpiar
                </button>
            `;
            
            const tabContent = document.getElementById('seccionTabsContent');
            tabContent.parentNode.insertBefore(indicator, tabContent);
        } else if (seccion) {
            // Mostrar indicador más sutil para pestaña activa sin filtro
            const indicator = document.createElement('div');
            indicator.className = 'alert alert-light filtro-activo-indicator mt-2 py-2 border-start border-primary border-3';
            indicator.innerHTML = `
                <i class="bi bi-info-circle me-2 text-primary"></i>
                <strong>Pestaña activa:</strong> ${seccion === 'curso' ? 'Curso' : 'Profesor'}
                <small class="text-muted ms-2">Las operaciones se realizarán en esta sección</small>
            `;
            
            const tabContent = document.getElementById('seccionTabsContent');
            tabContent.parentNode.insertBefore(indicator, tabContent);
        }
    }

    // Funciones mejoradas de confirmación
    function confirmarEliminacion(textoPregunta, seccion) {
        const contexto = seccion === 'curso' ? 'CURSO' : 'PROFESOR';
        const mensaje = `⚠️ CONFIRMAR ELIMINACIÓN\n\n` +
                       `Estás a punto de eliminar una pregunta de ${contexto}:\n\n` +
                       `"${textoPregunta.substring(0, 100)}${textoPregunta.length > 100 ? '...' : ''}"\n\n` +
                       `Esta acción no se puede deshacer.\n\n` +
                       `¿Estás seguro de eliminar esta pregunta de ${contexto}?`;
        
        return confirm(mensaje);
    }

    function confirmarCambioEstado(seccion) {
        const contexto = seccion === 'curso' ? 'CURSO' : 'PROFESOR';
        return confirm(`¿Cambiar estado de esta pregunta de ${contexto}?`);
    }
      function toggleReorderMode(event) {
        reorderMode = !reorderMode;
        const dragHandles = document.querySelectorAll('.drag-handle');
        const button = event.target.closest('button');
        const tbody = document.querySelector('table tbody');
        
        if (reorderMode) {
            // Activar modo de reordenamiento
            dragHandles.forEach(handle => handle.classList.remove('d-none'));
            button.innerHTML = '<i class="bi bi-check-lg me-2"></i>Guardar Orden';
            button.classList.remove('btn-outline-primary');
            button.classList.add('btn-success');
            
            // Hacer las filas arrastrables
            tbody.classList.add('sortable-active');
            initSortable(tbody);
            
            console.log('✅ Modo reordenamiento activado');
        } else {
            // Desactivar modo de reordenamiento y guardar
            dragHandles.forEach(handle => handle.classList.add('d-none'));
            button.innerHTML = '<i class="bi bi-arrow-up-down me-2"></i>Reordenar';
            button.classList.remove('btn-success');
            button.classList.add('btn-outline-primary');
            
            tbody.classList.remove('sortable-active');
            if (sortable) {
                sortable.destroy();
                sortable = null;
            }
            
            // Guardar el nuevo orden
            saveNewOrder();
            
            console.log('💾 Guardando nuevo orden...');
        }
    }
    
    function initSortable(tbody) {
        // Implementación simple de drag & drop
        let draggedElement = null;
        
        tbody.addEventListener('dragstart', function(e) {
            if (e.target.closest('tr')) {
                draggedElement = e.target.closest('tr');
                draggedElement.style.opacity = '0.5';
                console.log('🖱️ Iniciando arrastre:', draggedElement.dataset.preguntaId);
            }
        });
        
        tbody.addEventListener('dragend', function(e) {
            if (draggedElement) {
                draggedElement.style.opacity = '';
                draggedElement = null;
            }
        });
        
        tbody.addEventListener('dragover', function(e) {
            e.preventDefault();
        });
        
        tbody.addEventListener('drop', function(e) {
            e.preventDefault();
            const targetRow = e.target.closest('tr');
            
            if (draggedElement && targetRow && draggedElement !== targetRow) {
                const tbody = targetRow.parentNode;
                const rows = Array.from(tbody.children);
                const draggedIndex = rows.indexOf(draggedElement);
                const targetIndex = rows.indexOf(targetRow);
                
                if (draggedIndex < targetIndex) {
                    tbody.insertBefore(draggedElement, targetRow.nextSibling);
                } else {
                    tbody.insertBefore(draggedElement, targetRow);
                }
                
                updateOrderBadges();
                console.log('📋 Pregunta reordenada');
            }
        });
        
        // Hacer las filas arrastrables
        const rows = tbody.querySelectorAll('tr');
        rows.forEach(row => {
            row.draggable = true;
            row.style.cursor = 'grab';
        });
    }
    
    function updateOrderBadges() {
        const rows = document.querySelectorAll('table tbody tr');
        rows.forEach((row, index) => {
            const badge = row.querySelector('.badge');
            if (badge) {
                badge.textContent = index + 1;
            }
        });
    }
      function saveNewOrder() {
        const rows = document.querySelectorAll('table tbody tr');
        const ordenPreguntas = {};
        
        rows.forEach((row, index) => {
            const preguntaId = row.dataset.preguntaId;
            if (preguntaId) {
                ordenPreguntas[preguntaId] = index + 1;
            }
        });
        
        // Enviar al servidor incluyendo filtro actual
        const formData = new FormData();
        formData.append('action', 'reorder');
        
        // Obtener filtro actual de la URL
        const urlParams = new URLSearchParams(window.location.search);
        const seccionFiltro = urlParams.get('seccion') || '';
        if (seccionFiltro) {
            formData.append('seccion_filtro', seccionFiltro);
        }
        
        Object.entries(ordenPreguntas).forEach(([id, orden]) => {
            formData.append(`orden_preguntas[${id}]`, orden);
        });
        
        fetch('preguntas.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            // La respuesta será una redirección, recargar la página manteniendo filtro
            window.location.reload();
        })
        .catch(error => {
            console.error('❌ Error al guardar orden:', error);
            showMessage('Error al guardar el orden', 'error');
        });
    }
    
    function showMessage(message, type) {
        const alertClass = type === 'success' ? 'alert-success' : 'alert-danger';
        const alertHtml = `
            <div class="alert ${alertClass} alert-dismissible fade show mt-3" role="alert">
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        `;
        
        const container = document.querySelector('.container-fluid');
        const firstChild = container.firstElementChild;
        firstChild.insertAdjacentHTML('afterend', alertHtml);
        
        // Auto-remover después de 3 segundos
        setTimeout(() => {
            const alert = document.querySelector('.alert');
            if (alert) {
                alert.remove();
            }        }, 3000);
    }    // Limpiar formulario al cerrar modal
    document.addEventListener('DOMContentLoaded', function() {
        // Variables compartidas para toda la función
        const urlParams = new URLSearchParams(window.location.search);
        const seccionActual = urlParams.get('seccion');
        const tabActual = urlParams.get('tab');
        const contextoActivo = seccionActual || tabActual;
        
        // Inicializar indicador de filtro/pestaña si existe
        if (contextoActivo) {
            mostrarFiltroActivo(contextoActivo);
        }

        // Configurar modal
        document.getElementById('preguntaModal').addEventListener('hidden.bs.modal', function () {
            document.getElementById('form_action').value = 'create';
            document.getElementById('pregunta_id').value = '';
            document.getElementById('preguntaForm').reset();
            document.getElementById('es_obligatoria').checked = true;
            document.getElementById('activa').checked = true;
            
            // Limpiar contexto del modal
            const modalTitle = document.getElementById('preguntaModalLabel');
            const contextBadge = modalTitle.querySelector('.context-badge');
            if (contextBadge) contextBadge.remove();
            
            // Usar las variables ya declaradas arriba
            const contextoModal = seccionActual || tabActual;
            
            // Actualizar tab_actual para nueva pregunta
            document.getElementById('tab_actual').value = contextoModal || 'curso';
            
            if (contextoModal) {
                const contexto = contextoModal === 'curso' ? 'CURSO' : 'PROFESOR';
                modalTitle.textContent = `Nueva Pregunta de ${contexto}`;
                
                // Pre-seleccionar la sección del contexto activo
                document.getElementById('seccion').value = contextoModal;
                
                // Agregar badge de contexto
                const contextBadge = document.createElement('span');
                contextBadge.className = `badge bg-${contextoModal === 'curso' ? 'primary' : 'info'} context-badge ms-2`;
                contextBadge.textContent = contexto;
                modalTitle.appendChild(contextBadge);
                
                console.log(`➕ Preparando nueva pregunta para contexto: ${contexto}`);
            } else {
                modalTitle.textContent = 'Nueva Pregunta';
            }
        });

        // Mejorar feedback al usuario sobre el filtro
        const filtroSelect = document.getElementById('filtro_seccion');
        if (filtroSelect && filtroSelect.value) {
            // Resaltar visualmente que hay un filtro activo
            filtroSelect.style.borderColor = '#0d6efd';
            filtroSelect.style.boxShadow = '0 0 0 0.2rem rgba(13, 110, 253, 0.25)';
        }

        // Advertencia si hay filtro activo
        if (seccionActual) {
            console.log(`🔍 Filtro activo: ${seccionActual.toUpperCase()}`);
        } else if (tabActual) {
            console.log(`🏷️ Pestaña activa: ${tabActual.toUpperCase()}`);
        }
        
        // Agregar tooltip a botones de acción para clarificar contexto
        if (contextoActivo) {
            const botonesAccion = document.querySelectorAll('[data-bs-toggle="modal"], button[onclick*="confirm"]');
            botonesAccion.forEach(btn => {
                if (!btn.title) {
                    btn.title = `Acción para preguntas de ${contextoActivo.toUpperCase()}`;
                }
            });
        }
    });
    </script>
</body>
</html>
