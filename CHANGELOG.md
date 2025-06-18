# Changelog

Todos los cambios notables en este proyecto serán documentados en este archivo.

El formato está basado en [Keep a Changelog](https://keepachangelog.com/es-ES/1.0.0/),
y este proyecto adhiere a [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Sin Release] - 2025-06-17

### Agregado
- Sistema completo de encuestas académicas
- Panel administrativo con dashboard
- Gestión de cursos, profesores y formularios
- Sistema de reportes con gráficos interactivos
- API REST para frontend
- Sistema de cache y rate limiting
- Diseño responsive con modo claro/oscuro
- Documentación completa

### Funcionalidades Principales

#### Frontend (Encuestas)
- Formulario dinámico de encuestas
- Selección de curso y profesor
- Preguntas de escala (1-10) y texto libre
- Validación en tiempo real
- Página de confirmación
- Rate limiting anti-spam

#### Panel Administrativo
- **Dashboard**: Resumen general del sistema
- **Cursos**: CRUD completo con estados activo/inactivo
- **Profesores**: Gestión completa de profesores
- **Formularios**: Creación y gestión de formularios
- **Preguntas**: Sistema avanzado de gestión por secciones
- **Reportes**: Análisis estadístico completo

#### Sistema de Reportes
- Gráficos de torta por curso y profesor
- Estadísticas descriptivas (promedio, mediana, desviación)
- Análisis de preguntas más críticas
- Comentarios cualitativos categorizados
- Resumen ejecutivo con KPIs
- Filtros avanzados por curso, fecha y profesor
- Exportación de datos

#### Características Técnicas
- **Arquitectura**: MVC con separación clara de responsabilidades
- **Base de Datos**: MySQL con diseño normalizado
- **Seguridad**: 
  - Prepared statements para prevenir SQL injection
  - Sanitización de datos para prevenir XSS
  - Rate limiting por IP
  - Sistema de sesiones seguras
- **Performance**:
  - Sistema de cache para consultas frecuentes
  - Optimización de consultas SQL
  - Compresión de assets
- **UI/UX**:
  - Bootstrap 5 para diseño responsive
  - Modo claro/oscuro
  - Animaciones y transiciones suaves
  - Iconografía consistente con Bootstrap Icons

### Estructura de Base de Datos

#### Tablas Principales
- `cursos`: Gestión de cursos académicos
- `profesores`: Información de profesores
- `formularios`: Definición de formularios
- `preguntas`: Banco de preguntas por sección
- `encuestas`: Registros de encuestas enviadas
- `respuestas`: Respuestas individuales de encuestas

#### Características de BD
- Charset UTF8MB4 para soporte completo de unicode
- Collation español para ordenamiento correcto
- Índices optimizados para consultas frecuentes
- Constraints de integridad referencial
- Campos de auditoría (created_at, updated_at)

### APIs Implementadas

#### Endpoints Públicos
- `GET /api/get_cursos.php` - Obtener cursos activos
- `GET /api/get_profesores.php` - Obtener profesores por curso
- `GET /api/get_preguntas.php` - Obtener preguntas por sección
- `POST /api/procesar_encuesta.php` - Procesar encuesta completada

#### Características de API
- Respuestas en formato JSON
- Validación de parámetros
- Manejo de errores estandarizado
- Rate limiting por endpoint
- Logging de requests

### Mejoras de Experiencia de Usuario

#### Formulario de Encuesta
- Carga dinámica de profesores según curso seleccionado
- Validación en tiempo real
- Indicadores de progreso
- Mensajes de confirmación
- Diseño intuitivo y accesible

#### Panel Administrativo
- Navegación consistente con sidebar
- Tablas interactivas con ordenamiento
- Modales para formularios
- Indicadores visuales de estado
- Búsqueda y filtrado avanzado

#### Sistema de Reportes
- Gráficos interactivos con Chart.js
- Filtros dinámicos
- Métricas en tiempo real
- Visualizaciones responsivas
- Exportación de reportes

### Optimizaciones de Performance

#### Backend
- Conexiones de BD optimizadas con pool de conexiones
- Cache de consultas frecuentes
- Compresión de respuestas
- Optimización de consultas SQL

#### Frontend
- Carga asíncrona de recursos
- Minimización de requests HTTP
- Cache de browser optimizado
- Lazy loading de imágenes

### Seguridad Implementada

#### Protección de Datos
- Encriptación de datos sensibles
- Validación y sanitización de inputs
- Protección CSRF
- Headers de seguridad

#### Control de Acceso
- Sistema de autenticación robusto
- Validación de sesiones
- Rate limiting por IP y endpoint
- Logging de accesos

### Documentación

#### Técnica
- Comentarios en código
- Documentación de API
- Diagrama de base de datos
- Guías de instalación

#### Usuario
- Manual de usuario para administradores
- Guía de uso del sistema
- FAQ y troubleshooting
- Videos tutoriales (pendiente)

---

## Convenciones de Versionado

- **Major (X.0.0)**: Cambios incompatibles en API, reestructuración mayor
- **Minor (x.Y.0)**: Nueva funcionalidad compatible, mejoras significativas  
- **Patch (x.y.Z)**: Bug fixes, mejoras menores, actualizaciones de seguridad

## Tipos de Cambios

- **Agregado**: Para nuevas funcionalidades
- **Cambiado**: Para cambios en funcionalidad existente
- **Deprecado**: Para funcionalidad que será removida
- **Removido**: Para funcionalidad removida
- **Corregido**: Para bug fixes
- **Seguridad**: Para mejoras de seguridad
