<?php
/**
 * Panel de Administración - Gestión de Cursos
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
                $stmt = $pdo->prepare("INSERT INTO cursos (nombre, descripcion, codigo, creditos, activo) VALUES (?, ?, ?, ?, ?)");
                $result = $stmt->execute([
                    $_POST['nombre'],
                    $_POST['descripcion'],
                    $_POST['codigo'],
                    $_POST['creditos'],
                    isset($_POST['activo']) ? 1 : 0
                ]);
                
                if ($result) {
                    $message = "Curso creado exitosamente";
                    $message_type = "success";
                }
                break;
                
            case 'update':
                $stmt = $pdo->prepare("UPDATE cursos SET nombre = ?, descripcion = ?, codigo = ?, creditos = ?, activo = ? WHERE id = ?");
                $result = $stmt->execute([
                    $_POST['nombre'],
                    $_POST['descripcion'],
                    $_POST['codigo'],
                    $_POST['creditos'],
                    isset($_POST['activo']) ? 1 : 0,
                    $_POST['id']
                ]);
                
                if ($result) {
                    $message = "Curso actualizado exitosamente";
                    $message_type = "success";
                }
                break;
                
            case 'delete':
                $stmt = $pdo->prepare("DELETE FROM cursos WHERE id = ?");
                $result = $stmt->execute([$_POST['id']]);
                
                if ($result) {
                    $message = "Curso eliminado exitosamente";
                    $message_type = "success";
                }
                break;
                
            case 'toggle_status':
                $stmt = $pdo->prepare("UPDATE cursos SET activo = NOT activo WHERE id = ?");
                $result = $stmt->execute([$_POST['id']]);
                
                if ($result) {
                    $message = "Estado del curso actualizado";
                    $message_type = "success";
                }
                break;
        }
    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
        $message_type = "error";
    }
}

// Obtener lista de cursos
try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();
    
    $stmt = $pdo->query("SELECT c.*, 
                                COUNT(DISTINCT f.id) as total_formularios,
                                COUNT(DISTINCT e.id) as total_encuestas
                         FROM cursos c
                         LEFT JOIN formularios f ON c.id = f.curso_id
                         LEFT JOIN encuestas e ON c.id = e.curso_id
                         GROUP BY c.id
                         ORDER BY c.nombre");
    $cursos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $cursos = [];
    $message = "Error al cargar cursos: " . $e->getMessage();
    $message_type = "error";
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Cursos - Sistema de Encuestas</title>    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/admin.css" rel="stylesheet">
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
                            <a class="nav-link text-white" href="index.php">
                                <i class="bi bi-house-door me-2"></i>Dashboard
                            </a>
                        </li>                        <li class="nav-item">
                            <a class="nav-link text-white active" href="cursos.php">
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
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 content-with-sidebar">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Gestión de Cursos</h1>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#cursoModal">
                        <i class="bi bi-plus-lg me-2"></i>Nuevo Curso
                    </button>
                </div>

                <!-- Alertas -->
                <?php if ($message): ?>
                <div class="alert alert-<?php echo $message_type === 'error' ? 'danger' : $message_type; ?> alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <!-- Tabla de cursos -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Lista de Cursos</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($cursos)): ?>
                        <div class="text-center py-4">
                            <i class="bi bi-inbox display-1 text-muted"></i>
                            <p class="text-muted mt-3">No hay cursos registrados</p>
                        </div>
                        <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover admin-table cursos-table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Nombre</th>
                                        <th>Código</th>
                                        <th>Créditos</th>
                                        <th>Formularios</th>
                                        <th>Encuestas</th>
                                        <th>Estado</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($cursos as $curso): ?>
                                    <tr>
                                        <td><?php echo $curso['id']; ?></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($curso['nombre']); ?></strong>
                                            <?php if ($curso['descripcion']): ?>
                                            <br><small class="text-muted"><?php echo htmlspecialchars(substr($curso['descripcion'], 0, 50)) . (strlen($curso['descripcion']) > 50 ? '...' : ''); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($curso['codigo']): ?>
                                            <span class="badge bg-secondary"><?php echo htmlspecialchars($curso['codigo']); ?></span>
                                            <?php else: ?>
                                            <span class="text-muted">Sin código</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo $curso['creditos'] ?: '-'; ?></td>
                                        <td>
                                            <span class="badge bg-info"><?php echo $curso['total_formularios']; ?></span>
                                        </td>
                                        <td>
                                            <span class="badge bg-success"><?php echo $curso['total_encuestas']; ?></span>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo $curso['activo'] ? 'success' : 'secondary'; ?>">
                                                <?php echo $curso['activo'] ? 'Activo' : 'Inactivo'; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm" role="group">
                                                <button type="button" class="btn btn-outline-primary" onclick="editarCurso(<?php echo htmlspecialchars(json_encode($curso)); ?>)">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                <form method="POST" class="form-inline" onsubmit="return confirm('¿Está seguro de cambiar el estado?')">
                                                    <input type="hidden" name="action" value="toggle_status">
                                                    <input type="hidden" name="id" value="<?php echo $curso['id']; ?>">
                                                    <button type="submit" class="btn btn-outline-warning">
                                                        <i class="bi bi-<?php echo $curso['activo'] ? 'pause' : 'play'; ?>"></i>
                                                    </button>
                                                </form>
                                                <form method="POST" class="form-inline" onsubmit="return confirm('¿Está seguro de eliminar este curso?')">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="id" value="<?php echo $curso['id']; ?>">
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

    <!-- Modal para crear/editar curso -->
    <div class="modal fade" id="cursoModal" tabindex="-1" aria-labelledby="cursoModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" id="cursoForm">
                    <div class="modal-header">
                        <h5 class="modal-title" id="cursoModalLabel">Nuevo Curso</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" id="form_action" value="create">
                        <input type="hidden" name="id" id="curso_id">
                        
                        <div class="mb-3">
                            <label for="nombre" class="form-label">Nombre del Curso *</label>
                            <input type="text" class="form-control" id="nombre" name="nombre" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="codigo" class="form-label">Código</label>
                            <input type="text" class="form-control" id="codigo" name="codigo" placeholder="Ej: MAT101">
                        </div>
                        
                        <div class="mb-3">
                            <label for="creditos" class="form-label">Créditos</label>
                            <input type="number" class="form-control" id="creditos" name="creditos" min="0" max="10">
                        </div>
                        
                        <div class="mb-3">
                            <label for="descripcion" class="form-label">Descripción</label>
                            <textarea class="form-control" id="descripcion" name="descripcion" rows="3"></textarea>
                        </div>
                        
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="activo" name="activo" checked>
                            <label class="form-check-label" for="activo">
                                Curso activo
                            </label>
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
    function editarCurso(curso) {
        document.getElementById('form_action').value = 'update';
        document.getElementById('curso_id').value = curso.id;
        document.getElementById('nombre').value = curso.nombre;
        document.getElementById('codigo').value = curso.codigo || '';
        document.getElementById('creditos').value = curso.creditos || '';
        document.getElementById('descripcion').value = curso.descripcion || '';
        document.getElementById('activo').checked = curso.activo == 1;
        
        document.getElementById('cursoModalLabel').textContent = 'Editar Curso';
        
        const modal = new bootstrap.Modal(document.getElementById('cursoModal'));
        modal.show();
    }
    
    // Limpiar formulario al cerrar modal
    document.getElementById('cursoModal').addEventListener('hidden.bs.modal', function () {
        document.getElementById('form_action').value = 'create';
        document.getElementById('curso_id').value = '';
        document.getElementById('cursoForm').reset();
        document.getElementById('activo').checked = true;
        document.getElementById('cursoModalLabel').textContent = 'Nuevo Curso';
    });
    </script>
</body>
</html>
