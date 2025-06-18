<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceso Administrativo - Sistema de Encuestas Académicas</title>
      <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <!-- CSS personalizado -->
    <link href="../assets/css/admin.css" rel="stylesheet">
</head>
<body class="login-page">
    <!-- Floating Background Shapes -->
    <div class="floating-shapes">
        <div class="shape"></div>
        <div class="shape"></div>
        <div class="shape"></div>
    </div>

    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-5 col-xl-4">
                <div class="login-card">
                    <!-- Header -->
                    <div class="login-header">
                        <div class="mb-3">
                            <i class="bi bi-shield-lock login-icon"></i>
                        </div>
                        <h3 class="mb-1">Panel Administrativo</h3>
                        <p class="mb-0 opacity-75">Sistema de Encuestas Académicas</p>
                    </div>
                    
                    <!-- Body -->
                    <div class="login-body">
                        <?php if (isset($login_error)): ?>
                            <div class="alert alert-danger d-flex align-items-center" role="alert">
                                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                                <div><?php echo htmlspecialchars($login_error); ?></div>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST" action="index.php" class="needs-validation" novalidate>
                            <input type="hidden" name="login" value="1">
                            
                            <!-- Username Field -->
                            <div class="mb-3">
                                <label for="username" class="form-label">
                                    <i class="bi bi-person"></i> Usuario
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="bi bi-person-fill"></i>
                                    </span>
                                    <input type="text" 
                                           class="form-control" 
                                           id="username" 
                                           name="username" 
                                           placeholder="Ingrese su usuario"
                                           required
                                           autocomplete="username"
                                           value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
                                    <div class="invalid-feedback">
                                        Por favor ingrese su nombre de usuario.
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Password Field -->
                            <div class="mb-4">
                                <label for="password" class="form-label">
                                    <i class="bi bi-lock"></i> Contraseña
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="bi bi-lock-fill"></i>
                                    </span>
                                    <input type="password" 
                                           class="form-control" 
                                           id="password" 
                                           name="password" 
                                           placeholder="Ingrese su contraseña"
                                           required
                                           autocomplete="current-password">                                    <button class="btn btn-outline-secondary toggle-password-btn" 
                                            type="button" 
                                            id="togglePassword">
                                        <i class="bi bi-eye" id="toggleIcon"></i>
                                    </button>
                                    <div class="invalid-feedback">
                                        Por favor ingrese su contraseña.
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Submit Button -->
                            <div class="d-grid">
                                <button type="submit" class="btn btn-login">
                                    <i class="bi bi-box-arrow-in-right me-2"></i>
                                    Iniciar Sesión
                                </button>
                            </div>
                        </form>
                        
                        <!-- Additional Info -->
                        <div class="text-center mt-4">
                            <small class="text-muted">
                                <i class="bi bi-shield-check"></i>
                                Acceso seguro y encriptado
                            </small>
                        </div>
                        
                        <!-- Demo Credentials (Remove in production) -->
                        <div class="alert alert-info mt-3" role="alert">
                            <div class="d-flex align-items-center">
                                <i class="bi bi-info-circle-fill me-2"></i>
                                <div>
                                    <strong>Credenciales de demostración:</strong><br>
                                    <small>Usuario: <code>admin</code> | Contraseña: <code>admin2025</code></small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Footer -->
                <div class="text-center mt-4">
                    <small class="text-white-50">
                        <i class="bi bi-arrow-left"></i>
                        <a href="../index.html" class="text-white-50 text-decoration-none">
                            Volver al sitio principal
                        </a>
                    </small>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Toggle password visibility
        document.getElementById('togglePassword').addEventListener('click', function() {
            const password = document.getElementById('password');
            const toggleIcon = document.getElementById('toggleIcon');
            
            if (password.type === 'password') {
                password.type = 'text';
                toggleIcon.classList.remove('bi-eye');
                toggleIcon.classList.add('bi-eye-slash');
            } else {
                password.type = 'password';
                toggleIcon.classList.remove('bi-eye-slash');
                toggleIcon.classList.add('bi-eye');
            }
        });
        
        // Form validation
        (function() {
            'use strict';
            window.addEventListener('load', function() {
                const forms = document.getElementsByClassName('needs-validation');
                Array.prototype.filter.call(forms, function(form) {
                    form.addEventListener('submit', function(event) {
                        if (form.checkValidity() === false) {
                            event.preventDefault();
                            event.stopPropagation();
                        }
                        form.classList.add('was-validated');
                    }, false);
                });
            }, false);
        })();
        
        // Auto-focus on username field
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('username').focus();
        });
        
        // Prevent form resubmission
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.href);
        }
    </script>
</body>
</html>
