<?php
/**
 * Panel de Administración - Gestión de Formularios
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
    
    try {
        $db = Database::getInstance();
        $pdo = $db->getConnection();
        
        switch ($action) {
            case 'create':
                $stmt = $pdo->prepare("INSERT INTO formularios (nombre, curso_id, descripcion, fecha_inicio, fecha_fin, activo, creado_por) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $result = $stmt->execute([
                    $_POST['nombre'],
                    $_POST['curso_id'],
                    $_POST['descripcion'],
                    $_POST['fecha_inicio'] ?: null,
                    $_POST['fecha_fin'] ?: null,
                    isset($_POST['activo']) ? 1 : 0,
                    'admin'
                ]);
                
                if ($result) {
                    $message = "Formulario creado exitosamente";
                    $message_type = "success";
                }
                break;
                
            case 'update':
                $stmt = $pdo->prepare("UPDATE formularios SET nombre = ?, curso_id = ?, descripcion = ?, fecha_inicio = ?, fecha_fin = ?, activo = ? WHERE id = ?");
                $result = $stmt->execute([
                    $_POST['nombre'],
                    $_POST['curso_id'],
                    $_POST['descripcion'],
                    $_POST['fecha_inicio'] ?: null,
                    $_POST['fecha_fin'] ?: null,
                    isset($_POST['activo']) ? 1 : 0,
                    $_POST['id']
                ]);
                
                if ($result) {
                    $message = "Formulario actualizado exitosamente";
                    $message_type = "success";
                }
                break;
                
            case 'delete':
                $stmt = $pdo->prepare("DELETE FROM formularios WHERE id = ?");
                $result = $stmt->execute([$_POST['id']]);
                
                if ($result) {
                    $message = "Formulario eliminado exitosamente";
                    $message_type = "success";
                }
                break;
                
            case 'toggle_status':
                $stmt = $pdo->prepare("UPDATE formularios SET activo = NOT activo WHERE id = ?");
                $result = $stmt->execute([$_POST['id']]);
                
                if ($result) {
                    $message = "Estado del formulario actualizado";
                    $message_type = "success";
                }
                break;
        }
    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
        $message_type = "error";
    }
}

// Obtener lista de formularios
try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();
    
    $stmt = $pdo->query("SELECT f.*, c.nombre as curso_nombre,
                                COUNT(DISTINCT e.id) as total_encuestas,
                                COUNT(DISTINCT cp.profesor_id) as total_profesores
                         FROM formularios f
                         INNER JOIN cursos c ON f.curso_id = c.id
                         LEFT JOIN encuestas e ON f.id = e.formulario_id
                         LEFT JOIN curso_profesores cp ON f.id = cp.formulario_id
                         GROUP BY f.id
                         ORDER BY f.fecha_creacion DESC");
    $formularios = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Obtener cursos para el select
    $stmt = $pdo->query("SELECT id, nombre FROM cursos WHERE activo = 1 ORDER BY nombre");
    $cursos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $formularios = [];
    $cursos = [];
    $message = "Error al cargar datos: " . $e->getMessage();
    $message_type = "error";
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Formularios - Sistema de Encuestas</title>    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/admin.css" rel="stylesheet">
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
                            <a class="nav-link text-white active" href="formularios.php">
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
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 content-with-sidebar">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Gestión de Formularios</h1>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#formularioModal">
                        <i class="bi bi-plus-lg me-2"></i>Nuevo Formulario
                    </button>
                </div>

                <!-- Alertas -->
                <?php if ($message): ?>
                <div class="alert alert-<?php echo $message_type === 'error' ? 'danger' : $message_type; ?> alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <!-- Tabla de formularios -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Lista de Formularios</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($formularios)): ?>
                        <div class="text-center py-4">
                            <i class="bi bi-file-earmark-text display-1 text-muted"></i>
                            <p class="text-muted mt-3">No hay formularios registrados</p>
                        </div>
                        <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover admin-table formularios-table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Nombre</th>
                                        <th>Curso</th>
                                        <th>Período</th>
                                        <th>Profesores</th>
                                        <th>Encuestas</th>
                                        <th>Estado</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($formularios as $formulario): ?>
                                    <tr>
                                        <td><?php echo $formulario['id']; ?></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($formulario['nombre']); ?></strong>
                                            <?php if ($formulario['descripcion']): ?>
                                            <br><small class="text-muted"><?php echo htmlspecialchars(substr($formulario['descripcion'], 0, 50)) . (strlen($formulario['descripcion']) > 50 ? '...' : ''); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-info"><?php echo htmlspecialchars($formulario['curso_nombre']); ?></span>
                                        </td>
                                        <td>
                                            <?php if ($formulario['fecha_inicio'] && $formulario['fecha_fin']): ?>
                                            <small>
                                                <i class="bi bi-calendar-range"></i>
                                                <?php echo date('d/m/Y', strtotime($formulario['fecha_inicio'])); ?><br>
                                                <i class="bi bi-arrow-down"></i>
                                                <?php echo date('d/m/Y', strtotime($formulario['fecha_fin'])); ?>
                                            </small>
                                            <?php else: ?>
                                            <span class="text-muted">Sin límite</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-secondary"><?php echo $formulario['total_profesores']; ?></span>
                                        </td>
                                        <td>
                                            <span class="badge bg-success"><?php echo $formulario['total_encuestas']; ?></span>
                                        </td>
                                        <td>
                                            <?php 
                                            $vigente = true;
                                            if ($formulario['fecha_inicio'] && $formulario['fecha_fin']) {
                                                $vigente = (date('Y-m-d') >= $formulario['fecha_inicio'] && date('Y-m-d') <= $formulario['fecha_fin']);
                                            }
                                            ?>
                                            <span class="badge bg-<?php echo $formulario['activo'] && $vigente ? 'success' : 'secondary'; ?>">
                                                <?php 
                                                if (!$formulario['activo']) echo 'Inactivo';
                                                elseif (!$vigente) echo 'Vencido';
                                                else echo 'Activo';
                                                ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm" role="group">
                                                <button type="button" class="btn btn-outline-primary" onclick="editarFormulario(<?php echo htmlspecialchars(json_encode($formulario)); ?>)">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                <button type="button" class="btn btn-outline-info" onclick="gestionarProfesores(<?php echo $formulario['id']; ?>, '<?php echo htmlspecialchars($formulario['nombre']); ?>')">
                                                    <i class="bi bi-people"></i>
                                                </button>
                                                <form method="POST" class="form-inline" onsubmit="return confirm('¿Está seguro de cambiar el estado?')">
                                                    <input type="hidden" name="action" value="toggle_status">
                                                    <input type="hidden" name="id" value="<?php echo $formulario['id']; ?>">
                                                    <button type="submit" class="btn btn-outline-warning">
                                                        <i class="bi bi-<?php echo $formulario['activo'] ? 'pause' : 'play'; ?>"></i>
                                                    </button>
                                                </form>
                                                <form method="POST" class="form-inline" onsubmit="return confirm('¿Está seguro de eliminar este formulario?')">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="id" value="<?php echo $formulario['id']; ?>">
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
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Modal para crear/editar formulario -->
    <div class="modal fade" id="formularioModal" tabindex="-1" aria-labelledby="formularioModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST" id="formularioForm">
                    <div class="modal-header">
                        <h5 class="modal-title" id="formularioModalLabel">Nuevo Formulario</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" id="form_action" value="create">
                        <input type="hidden" name="id" id="formulario_id">
                        
                        <div class="row">
                            <div class="col-md-8">
                                <div class="mb-3">
                                    <label for="nombre" class="form-label">Nombre del Formulario *</label>
                                    <input type="text" class="form-control" id="nombre" name="nombre" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="curso_id" class="form-label">Curso *</label>
                                    <select class="form-select" id="curso_id" name="curso_id" required>
                                        <option value="">Seleccionar curso...</option>
                                        <?php foreach ($cursos as $curso): ?>
                                        <option value="<?php echo $curso['id']; ?>"><?php echo htmlspecialchars($curso['nombre']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="descripcion" class="form-label">Descripción</label>
                            <textarea class="form-control" id="descripcion" name="descripcion" rows="3"></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="fecha_inicio" class="form-label">Fecha de Inicio</label>
                                    <input type="date" class="form-control" id="fecha_inicio" name="fecha_inicio">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="fecha_fin" class="form-label">Fecha de Fin</label>
                                    <input type="date" class="form-control" id="fecha_fin" name="fecha_fin">
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="activo" name="activo" checked>
                            <label class="form-check-label" for="activo">
                                Formulario activo
                            </label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Guardar</button>
                    </div>
                </form>
            </div>
        </div>    </div>

    <!-- Modal para Gestionar Profesores -->
    <div class="modal fade" id="profesoresModal" tabindex="-1" aria-labelledby="profesoresModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="profesoresModalLabel">Gestionar Profesores</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="alertsContainer"></div>
                    <input type="hidden" id="formularioIdProfesores">
                    <div class="mb-3">
                        <h6>Asignar/Desasignar Profesores al Formulario</h6>
                        <p class="text-muted">Seleccione los profesores que desea asignar o desasignar de este formulario.</p>
                    </div>
                    <div id="profesoresContainer">
                        <!-- Contenido dinámico -->
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                    <button type="button" class="btn btn-primary" onclick="window.location.reload()">Actualizar Página</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
    function editarFormulario(formulario) {
        document.getElementById('form_action').value = 'update';
        document.getElementById('formulario_id').value = formulario.id;
        document.getElementById('nombre').value = formulario.nombre;
        document.getElementById('curso_id').value = formulario.curso_id;
        document.getElementById('descripcion').value = formulario.descripcion || '';
        document.getElementById('fecha_inicio').value = formulario.fecha_inicio || '';
        document.getElementById('fecha_fin').value = formulario.fecha_fin || '';
        document.getElementById('activo').checked = formulario.activo == 1;
        
        document.getElementById('formularioModalLabel').textContent = 'Editar Formulario';
        
        const modal = new bootstrap.Modal(document.getElementById('formularioModal'));
        modal.show();
    }
      function gestionarProfesores(formularioId, nombreFormulario) {
        // Actualizar título del modal
        document.getElementById('profesoresModalLabel').textContent = 'Gestionar Profesores - ' + nombreFormulario;
        document.getElementById('formularioIdProfesores').value = formularioId;
        
        // Limpiar contenido previo
        const container = document.getElementById('profesoresContainer');
        container.innerHTML = '<div class="text-center"><div class="spinner-border" role="status"><span class="visually-hidden">Cargando...</span></div></div>';
        
        // Cargar profesores
        cargarProfesoresDisponibles(formularioId);
        
        // Mostrar modal
        const modal = new bootstrap.Modal(document.getElementById('profesoresModal'));
        modal.show();
    }
    
    async function cargarProfesoresDisponibles(formularioId) {
        try {
            // Obtener todos los profesores
            const response = await fetch('../api/get_profesores_todos.php');
            const data = await response.json();
            
            if (!data.success) {
                throw new Error(data.message);
            }
            
            // Obtener profesores asignados al formulario
            const responseAsignados = await fetch(`../api/get_profesores.php?formulario_id=${formularioId}`);
            const dataAsignados = await responseAsignados.json();
            
            const profesoresAsignados = dataAsignados.success ? dataAsignados.data.map(p => p.id) : [];
            
            // Renderizar lista
            renderizarListaProfesores(data.data, profesoresAsignados, formularioId);
            
        } catch (error) {
            document.getElementById('profesoresContainer').innerHTML = 
                '<div class="alert alert-danger">Error al cargar profesores: ' + error.message + '</div>';
        }
    }
    
    function renderizarListaProfesores(profesores, asignados, formularioId) {
        const container = document.getElementById('profesoresContainer');
        
        if (profesores.length === 0) {
            container.innerHTML = '<div class="alert alert-info">No hay profesores disponibles.</div>';
            return;
        }
        
        let html = '<div class="list-group">';
        
        profesores.forEach(profesor => {
            const isAsignado = asignados.includes(profesor.id);
            const badgeClass = isAsignado ? 'bg-success' : 'bg-secondary';
            const buttonText = isAsignado ? 'Quitar' : 'Asignar';
            const buttonClass = isAsignado ? 'btn-outline-danger' : 'btn-outline-success';
            const iconClass = isAsignado ? 'bi-dash-circle' : 'bi-plus-circle';
            
            html += `
                <div class="list-group-item d-flex justify-content-between align-items-center">
                    <div>
                        <strong>${profesor.nombre}</strong>
                        <br>
                        <small class="text-muted">${profesor.email} | ${profesor.especialidad || 'Sin especialidad'}</small>
                    </div>
                    <div>
                        <span class="badge ${badgeClass} me-2">${isAsignado ? 'Asignado' : 'Disponible'}</span>
                        <button type="button" class="btn btn-sm ${buttonClass}" 
                                onclick="toggleProfesorAsignacion(${profesor.id}, ${formularioId}, ${isAsignado})">
                            <i class="bi ${iconClass}"></i> ${buttonText}
                        </button>
                    </div>
                </div>
            `;
        });
        
        html += '</div>';
        container.innerHTML = html;
    }
      async function toggleProfesorAsignacion(profesorId, formularioId, isAsignado) {
        try {
            const accion = isAsignado ? 'desasignar' : 'asignar';
            const response = await fetch('../api/gestionar_profesor_formulario.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    accion: accion,
                    profesor_id: profesorId,
                    formulario_id: formularioId
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                // Recargar la lista
                cargarProfesoresDisponibles(formularioId);
                
                // Mostrar mensaje de éxito
                mostrarMensaje(data.message, 'success');
            } else {
                throw new Error(data.message);
            }
            
        } catch (error) {
            mostrarMensaje('Error: ' + error.message, 'danger');
        }
    }
    
    function mostrarMensaje(mensaje, tipo) {
        const alertsContainer = document.getElementById('alertsContainer') || document.body;
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${tipo} alert-dismissible fade show`;
        alertDiv.innerHTML = `
            ${mensaje}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        alertsContainer.insertBefore(alertDiv, alertsContainer.firstChild);
        
        // Auto-remove after 5 seconds
        setTimeout(() => {
            if (alertDiv.parentNode) {
                alertDiv.remove();
            }
        }, 5000);
    }
    
    // Limpiar formulario al cerrar modal
    document.getElementById('formularioModal').addEventListener('hidden.bs.modal', function () {
        document.getElementById('form_action').value = 'create';
        document.getElementById('formulario_id').value = '';
        document.getElementById('formularioForm').reset();
        document.getElementById('activo').checked = true;
        document.getElementById('formularioModalLabel').textContent = 'Nuevo Formulario';
    });
    
    // Validar fechas
    document.getElementById('fecha_inicio').addEventListener('change', function() {
        const fechaInicio = this.value;
        const fechaFin = document.getElementById('fecha_fin');
        if (fechaInicio) {
            fechaFin.min = fechaInicio;
        }
    });
    
    document.getElementById('fecha_fin').addEventListener('change', function() {
        const fechaFin = this.value;
        const fechaInicio = document.getElementById('fecha_inicio');
        if (fechaFin && fechaInicio.value && fechaFin < fechaInicio.value) {
            alert('La fecha de fin no puede ser anterior a la fecha de inicio');
            this.value = '';
        }
    });
    </script>
</body>
</html>
