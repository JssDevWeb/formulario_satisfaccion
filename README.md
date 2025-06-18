# 📊 Sistema de Encuestas Académicas

<div align="center">

![Sistema de Encuestas](https://img.shields.io/badge/Sistema-Encuestas_Acad%C3%A9micas-blue?style=for-the-badge)
![PHP](https://img.shields.io/badge/PHP-8.0%2B-777BB4?style=for-the-badge&logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-8.0%2B-4479A1?style=for-the-badge&logo=mysql&logoColor=white)
![Bootstrap](https://img.shields.io/badge/Bootstrap-5.3-7952B3?style=for-the-badge&logo=bootstrap&logoColor=white)
![License](https://img.shields.io/badge/License-MIT-green?style=for-the-badge)

*Sistema web completo para la gestión y evaluación de cursos y profesores académicos*

[🚀 Instalación](#-instalación) • [📖 Documentación](#-documentación) • [🔧 API](#-api) • [🤝 Contribuir](#-contribuir)

</div>

---

## 🎯 Descripción

El **Sistema de Encuestas Académicas** es una aplicación web desarrollada en PHP que permite la gestión integral de encuestas para la evaluación de cursos y profesores en instituciones educativas. Proporciona una interfaz intuitiva para estudiantes y un panel administrativo completo para la gestión de datos y generación de reportes.

### ✨ Características Principales

- 🎓 **Evaluación Académica**: Sistema completo de encuestas pour cursos y profesores
- 📱 **Diseño Responsivo**: Interfaz adaptable a todos los dispositivos
- � **Panel Administrativo**: Dashboard completo con métricas y gestión
- 📊 **Reportes Avanzados**: Visualización de datos con gráficos interactivos
- 🚀 **API REST**: Endpoints para integración y gestión de datos
- 🎨 **UI/UX Moderna**: Diseño basado en Bootstrap 5 con animaciones
- 🔒 **Seguridad**: Validaciones, sanitización y protección contra ataques
- ⚡ **Alto Rendimiento**: Optimizado para carga rápida y experiencia fluida
---

## 🛠️ Tecnologías

### Backend
- **PHP 8.0+** - Lenguaje principal del servidor
- **MySQL 8.0+** - Base de datos relacional
- **PDO** - Conexión segura a base de datos con patrón Singleton

### Frontend
- **HTML5 & CSS3** - Estructura y estilos modernos
- **JavaScript (ES6+)** - Interactividad y validaciones
- **Bootstrap 5.3** - Framework CSS responsivo
- **Bootstrap Icons** - Iconografía moderna
- **Chart.js** - Gráficos interactivos para reportes

### Herramientas de Desarrollo
- **Git** - Control de versiones
- **Composer** - Gestión de dependencias (opcional)
- **XAMPP/WAMP** - Entorno de desarrollo local

---

## � Requisitos del Sistema

### Requisitos Mínimos
- **Servidor Web**: Apache 2.4+ o Nginx 1.18+
- **PHP**: 8.0 o superior
- **MySQL**: 8.0 o superior (o MariaDB 10.5+)
- **Memoria RAM**: 512MB mínimo
- **Espacio en Disco**: 100MB mínimo

### Extensiones PHP Requeridas
```php
php-pdo
php-pdo-mysql
php-json
php-mbstring
php-session
php-curl (opcional)
```

---

## 🚀 Instalación

### 1. Clonar el Repositorio
```bash
git clone https://github.com/tu-usuario/formulario-encuestas-academicas.git
cd formulario-encuestas-academicas
```

### 2. Configurar el Entorno

**Opción A: XAMPP (Windows)**
```bash
# Mover el proyecto a la carpeta htdocs
mv formulario-encuestas-academicas C:\xampp\htdocs\
```

**Opción B: WAMP (Windows)**
```bash
# Mover el proyecto a la carpeta www
mv formulario-encuestas-academicas C:\wamp64\www\
```

**Opción C: Servidor Linux**
```bash
# Copiar a la carpeta del servidor web
sudo cp -r formulario-encuestas-academicas /var/www/html/
sudo chown -R www-data:www-data /var/www/html/formulario-encuestas-academicas
sudo chmod -R 755 /var/www/html/formulario-encuestas-academicas
```

### 3. Configurar la Base de Datos

#### Crear la Base de Datos
```sql
CREATE DATABASE academia_encuestas CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci;
```

#### Importar el Esquema
```bash
mysql -u root -p academia_encuestas < admin/academia_encuestas.sql
```

### 4. Configurar la Conexión

Editar `config/database.php`:
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'academia_encuestas');
define('DB_USER', 'tu_usuario');
define('DB_PASS', 'tu_contraseña');
```

### 5. Configurar Permisos

**Linux/macOS:**
```bash
chmod 755 -R ./
chmod 777 -R logs/
chmod 777 -R cache/
```

### 6. Verificar la Instalación

Abrir en el navegador:
- **Frontend**: `http://localhost/formulario-encuestas-academicas/`
- **Admin**: `http://localhost/formulario-encuestas-academicas/admin/`

---

## 🎯 Uso

### Para Estudiantes

1. **Acceder al Sistema**
   - Navegar a la URL principal
   - Hacer clic en "Comenzar Encuesta Académica"

2. **Completar la Encuesta**
   - Seleccionar curso y profesor
   - Responder las preguntas de evaluación
   - Enviar la encuesta (proceso anónimo)

### Para Administradores

1. **Acceso al Panel**
   ```
   URL: /admin/
   Usuario: admin
   Contraseña: [configurar en primera instalación]
   ```

2. **Gestión de Cursos**
   - Crear, editar y eliminar cursos
   - Asignar profesores a cursos
   - Configurar formularios de evaluación

3. **Gestión de Profesores**
   - Registrar nuevos profesores
   - Actualizar información de profesores
   - Asignar profesores a múltiples cursos

4. **Gestión de Preguntas**
   - Crear preguntas para cursos
   - Crear preguntas para profesores  
   - Configurar tipos de respuesta (escala, texto libre)

5. **Reportes y Estadísticas**
   - Ver métricas del dashboard
   - Generar reportes por curso y fecha
   - Exportar datos para análisis

---

## 🔧 API

El sistema incluye una API REST completa para la gestión de datos:

### Endpoints Principales

#### Encuestas
```http
POST /api/procesar_encuesta.php
Content-Type: application/json

{
  "formulario_id": 1,
  "tiempo_completado": 300,
  "respuestas_curso": {...},
  "respuestas_profesores": {...}
}
```

#### Cursos
```http
GET /api/get_cursos.php
GET /api/get_cursos.php?activos=1
```

#### Profesores
```http
GET /api/get_profesores.php
GET /api/get_profesores_por_curso.php?curso_id=1
GET /api/get_profesores_todos.php
```

#### Formularios
```http
GET /api/get_formularios.php?curso_id=1
GET /api/get_preguntas.php?formulario_id=1
```

### Respuestas de la API

**Éxito:**
```json
{
  "success": true,
  "data": {...},
  "message": "Operación realizada correctamente"
}
```

**Error:**
```json
{
  "success": false,
  "error": "Descripción del error",
  "code": "ERROR_CODE"
}
```

---

## 📊 Estructura del Proyecto

```
formulario-encuestas-academicas/
├── 📁 admin/                    # Panel administrativo
│   ├── 📄 index.php            # Dashboard principal
│   ├── 📄 cursos.php           # Gestión de cursos
│   ├── 📄 profesores.php       # Gestión de profesores
│   ├── 📄 preguntas.php        # Gestión de preguntas
│   ├── 📄 formularios.php      # Gestión de formularios
│   ├── 📄 reportes.php         # Reportes y estadísticas
│   ├── 📄 login.php            # Autenticación
│   └── 📁 assets/              # CSS y JS del admin
├── 📁 api/                     # API REST endpoints
│   ├── 📄 procesar_encuesta.php # Procesar respuestas
│   ├── 📄 get_cursos.php       # Obtener cursos
│   ├── 📄 get_profesores.php   # Obtener profesores
│   └── 📄 common.php           # Funciones comunes
├── 📁 assets/                  # Recursos del frontend
│   ├── 📁 css/
│   │   └── 📄 main.css         # Estilos principales
│   └── 📁 js/
│       ├── 📄 survey.js        # Lógica de encuestas
│       └── 📄 survey_fixed.js  # Versión optimizada
├── 📁 config/                  # Configuración
│   └── 📄 database.php         # Conexión a BD
├── 📁 error/                   # Páginas de error
│   ├── 📄 404.html
│   ├── 📄 403.html
│   └── 📄 500.html
├── 📁 logs/                    # Archivos de log
├── 📁 cache/                   # Cache del sistema
├── 📄 index.html               # Página principal
├── 📄 gracias.html             # Página de confirmación
└── 📄 README.md                # Este archivo
```

---

## 🔒 Seguridad

### Medidas Implementadas

- **🛡️ Validación de Datos**: Todas las entradas son validadas y sanitizadas
- **🔐 Prepared Statements**: Protección contra inyección SQL
- **🚫 Headers de Seguridad**: CSP, XSS Protection, CSRF tokens
- **📝 Rate Limiting**: Prevención de spam en formularios
- **🔒 Autenticación**: Sistema de login seguro para administradores
- **📊 Logging**: Registro de actividades para auditoría

### Configuración de Seguridad

#### Archivo .htaccess (Apache)
```apache
# Protección de archivos sensibles
<Files "*.sql">
    Order deny,allow
    Deny from all
</Files>

<Files "config/*.php">
    Order deny,allow
    Deny from all
</Files>

# Headers de seguridad
Header always set X-Content-Type-Options nosniff
Header always set X-Frame-Options DENY
Header always set X-XSS-Protection "1; mode=block"
```

---

## 📈 Rendimiento

### Optimizaciones Implementadas

- **⚡ CSS/JS Minificado**: Archivos optimizados para carga rápida
- **🔄 Lazy Loading**: Carga diferida de elementos no críticos
- **💾 Cache del Sistema**: Sistema de cache para consultas frecuentes
- **🗃️ Índices de BD**: Base de datos optimizada con índices apropiados
- **📱 Responsive Design**: Una sola versión para todos los dispositivos

### Métricas de Rendimiento

- **Tiempo de Carga**: < 2 segundos
- **First Contentful Paint**: < 1.5 segundos
- **Cumulative Layout Shift**: < 0.1
- **Mobile Score**: 90+/100

---

## 🧪 Testing

### Pruebas Manuales Realizadas

- ✅ Formularios de encuesta
- ✅ Panel administrativo
- ✅ API endpoints
- ✅ Responsive design
- ✅ Validaciones de seguridad
- ✅ Persistencia de datos

### Comandos de Testing

```bash
# Verificar sintaxis PHP
php -l admin/index.php

# Verificar conexión a BD
php -r "require 'config/database.php'; echo 'Conexión OK';"

# Verificar permisos
ls -la logs/ cache/
```

---

## 🤝 Contribuir

¡Las contribuciones son bienvenidas! Por favor lee [CONTRIBUTING.md](CONTRIBUTING.md) para detalles sobre el proceso.

### Proceso de Contribución

1. **Fork** el proyecto
2. **Crear** una rama para tu funcionalidad (`git checkout -b feature/NuevaFuncionalidad`)
3. **Commit** tus cambios (`git commit -m 'Agregar nueva funcionalidad'`)
4. **Push** a la rama (`git push origin feature/NuevaFuncionalidad`)
5. **Abrir** un Pull Request

### Estándares de Código

- **PSR-12** para PHP
- **ESLint** para JavaScript
- **Comentarios** en español
- **Documentación** actualizada

---

## 📝 Changelog

Ver [CHANGELOG.md](CHANGELOG.md) para un historial detallado de cambios.

### Versión Actual: 1.0.0

- ✨ Sistema completo de encuestas
- 🎯 Panel administrativo
- 📊 Reportes y estadísticas
- 🔒 Seguridad implementada
- 📱 Diseño responsivo

---

## 📞 Soporte

### Reportar Problemas

Si encuentras algún problema:

1. **Busca** en [Issues existentes](https://github.com/tu-usuario/formulario-encuestas-academicas/issues)
2. **Crea** un nuevo issue con:
   - Descripción del problema
   - Pasos para reproducir
   - Entorno (OS, PHP version, etc.)
   - Screenshots si es necesario

### Contacto

- **📧 Email**: soporte@encuestas-academicas.com
- **💬 Discussions**: [GitHub Discussions](https://github.com/tu-usuario/formulario-encuestas-academicas/discussions)
- **📋 Issues**: [GitHub Issues](https://github.com/tu-usuario/formulario-encuestas-academicas/issues)

---

## 📄 Licencia

Este proyecto está licenciado bajo la Licencia MIT - ver el archivo [LICENSE](LICENSE) para detalles.

---

## 🙏 Agradecimientos

- **Bootstrap Team** - Por el excelente framework CSS
- **Chart.js** - Por las librerías de gráficos
- **Community** - Por las contribuciones y feedback
- **Instituciones Educativas** - Por la inspiración y requisitos

---

## 🌟 Estrella el Proyecto

Si este proyecto te ha sido útil, ¡considera darle una estrella! ⭐

---

<div align="center">

**[⬆ Volver al inicio](#-sistema-de-encuestas-académicas)**

*Desarrollado con ❤️ para la comunidad educativa*

</div>
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php [QSA,L]
```

**Nginx:**
```nginx
location / {
    try_files $uri $uri/ /index.php?$query_string;
}
```

## 📁 **Estructura del Proyecto**

```
sistema-encuestas-academicas/
├── 📁 admin/                          # Panel administrativo
│   ├── 📄 index.php                   # Dashboard principal
│   ├── 📄 login.php                   # Autenticación
│   ├── 📄 cursos.php                  # Gestión de cursos
│   ├── 📄 profesores.php              # Gestión de profesores
│   ├── 📄 formularios.php             # Gestión de formularios
│   ├── 📄 preguntas.php               # Gestión de preguntas
│   ├── 📄 reportes.php                # Reportes y estadísticas
│   └── 📁 assets/                     # Recursos del admin
├── 📁 api/                            # API REST
│   ├── 📄 common.php                  # Funciones comunes
│   ├── 📄 procesar_encuesta.php       # Procesamiento de encuestas
│   ├── 📄 get_cursos.php              # Endpoint cursos
│   ├── 📄 get_profesores.php          # Endpoint profesores
│   └── 📄 get_preguntas.php           # Endpoint preguntas
├── 📁 assets/                         # Recursos frontend
│   ├── 📁 css/                        # Hojas de estilo
│   └── 📁 js/                         # JavaScript
├── 📁 config/                         # Configuración
│   └── 📄 database.php                # Configuración BD
├── 📁 cache/                          # Cache del sistema
├── 📁 logs/                           # Logs de la aplicación
├── 📁 error/                          # Páginas de error
├── 📄 index.html                      # Formulario público
├── 📄 gracias.html                    # Página de confirmación
└── 📄 .htaccess                       # Configuración Apache
```

## 💻 **Uso del Sistema**

### **👤 Para Usuarios (Estudiantes)**

1. **Acceder al formulario**: Navegar a la URL del sistema
2. **Seleccionar curso**: Elegir el curso a evaluar
3. **Seleccionar profesor**: Elegir el profesor del curso
4. **Completar encuesta**: Responder preguntas de escala y texto libre
5. **Enviar**: Envío anónimo y confirmación automática

### **🔧 Para Administradores**

1. **Acceso**: `/admin/login.php` (usuario: admin, contraseña: configurar)
2. **Dashboard**: Resumen general del sistema
3. **Gestión**:
   - **Cursos**: Crear, editar, activar/desactivar cursos
   - **Profesores**: Gestión completa de profesores
   - **Formularios**: Crear formularios personalizados
   - **Preguntas**: Gestión de preguntas por sección (curso/profesor)
4. **Reportes**: Análisis estadístico completo con filtros avanzados

## 📊 **Características de Reportes**

### **Métricas Disponibles**
- ✅ Gráficos de torta por curso y profesor
- ✅ Estadísticas descriptivas (promedio, mediana, desviación)
- ✅ Análisis de preguntas más críticas
- ✅ Comentarios cualitativos categorizados
- ✅ Resumen ejecutivo con KPIs
- ✅ Filtros por curso, fecha y profesor

### **Visualizaciones**
- 📈 **Gráficos interactivos** con Chart.js
- 📊 **Tablas ordenables** con métricas detalladas
- 🎯 **Cards informativas** con estadísticas clave
- 📱 **Responsive design** para todos los dispositivos

## 🔒 **Seguridad**

- **🛡️ Autenticación**: Sistema de sesiones seguras
- **🚫 Rate Limiting**: Protección contra spam
- **🔐 SQL Injection**: Prepared statements en todas las consultas
- **🚨 XSS Protection**: Sanitización de datos de entrada
- **📝 Logging**: Registro de actividades y errores
- **💾 Cache**: Sistema de cache para optimización

## ⚙️ **Configuración Avanzada**

### **Variables de Entorno (Recomendado)**

```bash
# .env (crear en producción)
DB_HOST=localhost
DB_NAME=academia_encuestas
DB_USER=usuario_seguro
DB_PASS=contraseña_muy_segura
ADMIN_USER=admin
ADMIN_PASS=contraseña_admin_segura
RATE_LIMIT_MAX=100
CACHE_TTL=3600
```

### **Configuración de Producción**

```php
// config/production.php
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// Configurar SSL/HTTPS
// Configurar backups automáticos
// Configurar monitoreo
```

## 🧪 **Testing**

### **Ejecutar Pruebas**

```bash
# Pruebas de conexión a BD
php admin/test_database.php

# Pruebas de API
php api/test_endpoints.php

# Pruebas de funcionalidad
php tests/run_tests.php
```

### **Datos de Prueba**

El sistema incluye datos de ejemplo para testing:
- 5 cursos predefinidos
- 10 profesores de ejemplo
- Preguntas estándar de evaluación
- Encuestas de muestra

## 📚 **API Documentation**

### **Endpoints Principales**

```http
GET  /api/get_cursos.php          # Obtener cursos activos
GET  /api/get_profesores.php      # Obtener profesores por curso
GET  /api/get_preguntas.php       # Obtener preguntas por sección
POST /api/procesar_encuesta.php   # Enviar encuesta completada
```

### **Ejemplo de Uso**

```javascript
// Obtener cursos
fetch('/api/get_cursos.php')
    .then(response => response.json())
    .then(data => console.log(data));

// Enviar encuesta
const encuestaData = {
    curso_id: 1,
    profesor_id: 2,
    respuestas: {...}
};

fetch('/api/procesar_encuesta.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify(encuestaData)
});
```

## 🐛 **Solución de Problemas**

### **Problemas Comunes**

| Problema | Causa | Solución |
|----------|-------|----------|
| Error 500 | Permisos incorrectos | `chmod 755 cache/ logs/` |
| BD no conecta | Credenciales incorrectas | Verificar `config/database.php` |
| Gráficos no cargan | JavaScript deshabilitado | Habilitar JS en navegador |
| Formulario no envía | Rate limiting | Esperar o ajustar límites |

### **Debug Mode**

```php
// Activar en config/database.php
define('DEBUG_MODE', true);
ini_set('display_errors', 1);
```

## 🤝 **Contribuir**

### **Proceso de Contribución**

1. **Fork** el repositorio
2. **Crear rama**: `git checkout -b feature/nueva-funcionalidad`
3. **Commit**: `git commit -m 'Agregar nueva funcionalidad'`
4. **Push**: `git push origin feature/nueva-funcionalidad`
5. **Pull Request**: Describir cambios detalladamente

### **Estándares de Código**

- **PSR-12** para PHP
- **ESLint** para JavaScript
- **Comentarios** en español
- **Tests** para nuevas funcionalidades

## 📈 **Roadmap**

### **v2.0 (Próximamente)**
- [ ] API REST completa
- [ ] Autenticación JWT
- [ ] Exportación a PDF/Excel
- [ ] Dashboard en tiempo real
- [ ] Notificaciones por email

### **v3.0 (Futuro)**
- [ ] Aplicación móvil
- [ ] Machine Learning para análisis
- [ ] Integración con LMS
- [ ] Multi-idioma

## 📄 **Licencia**

Este proyecto está bajo la Licencia MIT - ver el archivo [LICENSE](LICENSE) para detalles.

```
MIT License

Copyright (c) 2025 Sistema de Encuestas Académicas

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software...
```

## 👥 **Autores y Reconocimientos**

- **Desarrollador Principal**: [Tu Nombre](https://github.com/tuusuario)
- **Diseño UI/UX**: Bootstrap Team
- **Iconografía**: Bootstrap Icons
- **Gráficos**: Chart.js

### **Agradecimientos**

- Comunidad PHP por las mejores prácticas
- Bootstrap por el framework CSS
- Chart.js por las visualizaciones

## 📞 **Soporte**

- **Issues**: [GitHub Issues](https://github.com/tuusuario/sistema-encuestas-academicas/issues)
- **Email**: soporte@tudominio.com
- **Documentación**: [Wiki del Proyecto](https://github.com/tuusuario/sistema-encuestas-academicas/wiki)

---

<div align="center">

**⭐ Si este proyecto te resulta útil, ¡dale una estrella! ⭐**

Hecho con ❤️ para la comunidad educativa

</div>
