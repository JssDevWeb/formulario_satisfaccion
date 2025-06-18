<?php
/**
 * Panel de Administración - Gestión de Profesores
 * Sistema de Encuestas Académicas
 */

session_start();

// Verificar autenticación
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
                // Validar que el email no esté duplicado
                $stmt = $pdo->prepare("SELECT id FROM profesores WHERE email = ?");
                $stmt->execute([$_POST['email']]);
                if ($stmt->fetch()) {
                    throw new Exception("Ya existe un profesor con este email");
                }
                
                $stmt = $pdo->prepare("INSERT INTO profesores (nombre, email, telefono, especialidad, grado_academico, departamento, activo) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $result = $stmt->execute([
                    $_POST['nombre'],
                    $_POST['email'],
                    $_POST['telefono'],
                    $_POST['especialidad'],
                    $_POST['grado_academico'],
                    $_POST['departamento'],
                    isset($_POST['activo']) ? 1 : 0
                ]);
                
                if ($result) {
                    $message = "Profesor creado exitosamente";
                    $message_type = "success";
                }
                break;
                
            case 'update':
                // Validar que el email no esté duplicado por otro profesor
                $stmt = $pdo->prepare("SELECT id FROM profesores WHERE email = ? AND id != ?");
                $stmt->execute([$_POST['email'], $_POST['id']]);
                if ($stmt->fetch()) {
                    throw new Exception("Ya existe otro profesor con este email");
                }
                
                $stmt = $pdo->prepare("UPDATE profesores SET nombre = ?, email = ?, telefono = ?, especialidad = ?, grado_academico = ?, departamento = ?, activo = ? WHERE id = ?");
                $result = $stmt->execute([
                    $_POST['nombre'],
                    $_POST['email'],
                    $_POST['telefono'],
                    $_POST['especialidad'],
                    $_POST['grado_academico'],
                    $_POST['departamento'],
                    isset($_POST['activo']) ? 1 : 0,
                    $_POST['id']
                ]);
                
                if ($result) {
                    $message = "Profesor actualizado exitosamente";
                    $message_type = "success";
                }
                break;
                
            case 'delete':
                // Verificar si el profesor tiene cursos asignados
                $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM curso_profesores WHERE profesor_id = ?");
                $stmt->execute([$_POST['id']]);
                $result = $stmt->fetch();
                
                if ($result['total'] > 0) {
                    throw new Exception("No se puede eliminar el profesor porque tiene cursos asignados");
                }
                
                $stmt = $pdo->prepare("DELETE FROM profesores WHERE id = ?");
                $result = $stmt->execute([$_POST['id']]);
                
                if ($result) {
                    $message = "Profesor eliminado exitosamente";
                    $message_type = "success";
                }
                break;
                
            case 'toggle_status':
                $stmt = $pdo->prepare("UPDATE profesores SET activo = NOT activo WHERE id = ?");
                $result = $stmt->execute([$_POST['id']]);
                
                if ($result) {
                    $message = "Estado del profesor actualizado";
                    $message_type = "success";
                }
                break;
        }
    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
        $message_type = "error";
    }
}

// Obtener lista de profesores con estadísticas
try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();
    
    $stmt = $pdo->query("SELECT p.*, 
                                COUNT(DISTINCT cp.formulario_id) as total_cursos,
                                COUNT(DISTINCT e.id) as total_encuestas
                         FROM profesores p
                         LEFT JOIN curso_profesores cp ON p.id = cp.profesor_id
                         LEFT JOIN formularios f ON cp.formulario_id = f.id
                         LEFT JOIN encuestas e ON f.id = e.formulario_id
                         GROUP BY p.id
                         ORDER BY p.nombre");
    $profesores = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $profesores = [];
    $message = "Error al cargar profesores: " . $e->getMessage();
    $message_type = "error";
}

// Obtener profesor para editar si se especifica
$profesor_edit = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM profesores WHERE id = ?");
        $stmt->execute([$_GET['edit']]);
        $profesor_edit = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $message = "Error al cargar profesor: " . $e->getMessage();
        $message_type = "error";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">    <title>Gestión de Profesores - Sistema de Encuestas Académicas</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/admin.css" rel="stylesheet">
</head>
<body>
    <div class="container-fluid">
        <div class="row row-sidebar">
            <!-- Sidebar -->
            <nav class="col-md-3 col-lg-2 d-md-block sidebar col-sidebar">
                <div class="sidebar-sticky">                    <div class="text-center mb-4">
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
                            <a class="nav-link text-white active" href="profesores.php">
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
                        </li>                        <li class="nav-item">
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
                    <h1 class="h2">Gestión de Profesores</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#profesorModal" onclick="clearForm()">
                            <i class="bi bi-plus me-2"></i>Nuevo Profesor
                        </button>
                    </div>
                </div>

                <!-- Mensajes -->
                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $message_type === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Estadísticas -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card bg-primary text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <div class="h5 mb-0"><?php echo count($profesores); ?></div>
                                        <div class="small">Total Profesores</div>
                                    </div>                                    <div class="align-self-center">
                                        <i class="bi bi-people fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-success text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <div class="h5 mb-0"><?php echo count(array_filter($profesores, fn($p) => $p['activo'])); ?></div>
                                        <div class="small">Profesores Activos</div>
                                    </div>                                    <div class="align-self-center">
                                        <i class="bi bi-person-check fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-warning text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <div class="h5 mb-0"><?php echo array_sum(array_column($profesores, 'total_cursos')); ?></div>
                                        <div class="small">Asignaciones de Cursos</div>
                                    </div>                                    <div class="align-self-center">
                                        <i class="bi bi-book-half fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-info text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <div class="h5 mb-0"><?php echo array_sum(array_column($profesores, 'total_encuestas')); ?></div>
                                        <div class="small">Encuestas Recibidas</div>
                                    </div>                                    <div class="align-self-center">
                                        <i class="bi bi-bar-chart fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tabla de Profesores -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Lista de Profesores</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($profesores)): ?>                            <div class="text-center py-4">
                                <i class="bi bi-people fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted">No hay profesores registrados</h5>
                                <p class="text-muted">Comienza agregando un nuevo profesor.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover admin-table profesores-table">
                                    <thead>
                                        <tr>
                                            <th>Nombre</th>
                                            <th>Email</th>
                                            <th>Teléfono</th>
                                            <th>Especialidad</th>
                                            <th>Grado Académico</th>
                                            <th>Departamento</th>
                                            <th>Cursos</th>
                                            <th>Estado</th>
                                            <th>Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($profesores as $profesor): ?>
                                            <tr>
                                                <td>
                                                    <div class="fw-bold"><?php echo htmlspecialchars($profesor['nombre']); ?></div>
                                                </td>
                                                <td>
                                                    <?php if ($profesor['email']): ?>
                                                        <a href="mailto:<?php echo htmlspecialchars($profesor['email']); ?>" class="text-decoration-none">
                                                            <?php echo htmlspecialchars($profesor['email']); ?>
                                                        </a>
                                                    <?php else: ?>
                                                        <span class="text-muted">No registrado</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($profesor['telefono']): ?>
                                                        <?php echo htmlspecialchars($profesor['telefono']); ?>
                                                    <?php else: ?>
                                                        <span class="text-muted">No registrado</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($profesor['especialidad']): ?>
                                                        <span class="badge bg-secondary"><?php echo htmlspecialchars($profesor['especialidad']); ?></span>
                                                    <?php else: ?>
                                                        <span class="text-muted">No especificada</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($profesor['grado_academico']): ?>
                                                        <span class="badge bg-info"><?php echo htmlspecialchars($profesor['grado_academico']); ?></span>
                                                    <?php else: ?>
                                                        <span class="text-muted">No especificado</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($profesor['departamento']): ?>
                                                        <?php echo htmlspecialchars($profesor['departamento']); ?>
                                                    <?php else: ?>
                                                        <span class="text-muted">No asignado</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="badge bg-primary"><?php echo $profesor['total_cursos']; ?></span>
                                                </td>
                                                <td>
                                                    <?php if ($profesor['activo']): ?>
                                                        <span class="badge bg-success">Activo</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-danger">Inactivo</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="btn-group btn-group-sm" role="group">
                                                        <button type="button" class="btn btn-outline-primary" 
                                                                onclick="editProfesor(<?php echo htmlspecialchars(json_encode($profesor)); ?>)"
                                                                data-bs-toggle="modal" data-bs-target="#profesorModal"
                                                                title="Editar">                                                            <i class="bi bi-pencil"></i>
                                                        </button>
                                                        
                                                        <form method="POST" class="d-inline">
                                                            <input type="hidden" name="action" value="toggle_status">
                                                            <input type="hidden" name="id" value="<?php echo $profesor['id']; ?>">
                                                            <button type="submit" class="btn btn-outline-warning" 
                                                                    title="<?php echo $profesor['activo'] ? 'Desactivar' : 'Activar'; ?>"
                                                                    onclick="return confirm('¿Está seguro de cambiar el estado de este profesor?')">
                                                                <i class="bi bi-<?php echo $profesor['activo'] ? 'eye-slash' : 'eye'; ?>"></i>
                                                            </button>
                                                        </form>
                                                        
                                                        <form method="POST" class="d-inline">
                                                            <input type="hidden" name="action" value="delete">
                                                            <input type="hidden" name="id" value="<?php echo $profesor['id']; ?>">                                                            <button type="submit" class="btn btn-outline-danger" 
                                                                    title="Eliminar"
                                                                    onclick="return confirm('¿Está seguro de eliminar este profesor? Esta acción no se puede deshacer.')">
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

    <!-- Modal para Crear/Editar Profesor -->
    <div class="modal fade" id="profesorModal" tabindex="-1" aria-labelledby="profesorModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="profesorModalLabel">Nuevo Profesor</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" id="profesorForm">
                    <div class="modal-body">
                        <input type="hidden" name="action" id="formAction" value="create">
                        <input type="hidden" name="id" id="profesorId">
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="nombre" class="form-label">Nombre Completo *</label>
                                    <input type="text" class="form-control" id="nombre" name="nombre" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="email" class="form-label">Email *</label>
                                    <input type="email" class="form-control" id="email" name="email" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="telefono" class="form-label">Teléfono</label>
                                    <input type="text" class="form-control" id="telefono" name="telefono">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="especialidad" class="form-label">Especialidad</label>
                                    <input type="text" class="form-control" id="especialidad" name="especialidad">
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="grado_academico" class="form-label">Grado Académico</label>
                                    <select class="form-select" id="grado_academico" name="grado_academico">
                                        <option value="">Seleccionar...</option>
                                        <option value="Licenciatura">Licenciatura</option>
                                        <option value="Maestría">Maestría</option>
                                        <option value="Doctorado">Doctorado</option>
                                        <option value="Postdoctorado">Postdoctorado</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="departamento" class="form-label">Departamento</label>
                                    <input type="text" class="form-control" id="departamento" name="departamento">
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="activo" name="activo" checked>
                                <label class="form-check-label" for="activo">
                                    Profesor Activo
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary" id="submitButton">Crear Profesor</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function clearForm() {
            document.getElementById('profesorForm').reset();
            document.getElementById('formAction').value = 'create';
            document.getElementById('profesorId').value = '';
            document.getElementById('profesorModalLabel').textContent = 'Nuevo Profesor';
            document.getElementById('submitButton').textContent = 'Crear Profesor';
            document.getElementById('activo').checked = true;
        }

        function editProfesor(profesor) {
            document.getElementById('formAction').value = 'update';
            document.getElementById('profesorId').value = profesor.id;
            document.getElementById('nombre').value = profesor.nombre || '';
            document.getElementById('email').value = profesor.email || '';
            document.getElementById('telefono').value = profesor.telefono || '';
            document.getElementById('especialidad').value = profesor.especialidad || '';
            document.getElementById('grado_academico').value = profesor.grado_academico || '';
            document.getElementById('departamento').value = profesor.departamento || '';
            document.getElementById('activo').checked = profesor.activo == 1;
            
            document.getElementById('profesorModalLabel').textContent = 'Editar Profesor';
            document.getElementById('submitButton').textContent = 'Actualizar Profesor';
        }
        
        // Auto-cerrar alertas después de 5 segundos
        setTimeout(function() {
            var alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                var bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
    </script>
</body>
</html>
