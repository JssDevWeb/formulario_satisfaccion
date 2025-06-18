# Contribuir al Sistema de Encuestas Académicas

¡Gracias por tu interés en contribuir! Este documento proporciona pautas para contribuir al proyecto.

## 🤝 Código de Conducta

### Nuestro Compromiso

En el interés de fomentar un ambiente abierto y acogedor, nosotros como contribuyentes y mantenedores nos comprometemos a hacer de la participación en nuestro proyecto y nuestra comunidad una experiencia libre de acoso para todos.

### Estándares

Ejemplos de comportamiento que contribuyen a crear un ambiente positivo:

- Usar lenguaje acogedor e inclusivo
- Ser respetuoso de diferentes puntos de vista y experiencias
- Aceptar críticas constructivas con gracia
- Enfocarse en lo que es mejor para la comunidad

## 🚀 Cómo Contribuir

### Reportar Bugs

Antes de crear un issue, por favor:

1. **Verifica** que el bug no haya sido reportado anteriormente
2. **Incluye** detalles específicos sobre tu entorno:
   - Versión de PHP
   - Versión de MySQL
   - Navegador y versión
   - Sistema operativo

**Template para Bug Reports:**

```markdown
**Descripción del Bug**
Una descripción clara y concisa del problema.

**Pasos para Reproducir**
1. Ve a '...'
2. Haz clic en '....'
3. Desplázate hacia abajo hasta '....'
4. Ve el error

**Comportamiento Esperado**
Una descripción clara de lo que esperabas que pasara.

**Capturas de Pantalla**
Si aplica, agrega capturas de pantalla para ayudar a explicar el problema.

**Información del Entorno:**
- OS: [ej. Windows 10, Ubuntu 20.04]
- Navegador: [ej. Chrome 91, Firefox 89]
- Versión PHP: [ej. 8.0]
- Versión MySQL: [ej. 8.0.25]
```

### Sugerir Mejoras

Para sugerir una mejora:

1. **Abre un issue** describiendo la mejora
2. **Explica** por qué sería útil para los usuarios
3. **Proporciona** ejemplos de uso si es posible

### Pull Requests

1. **Fork** el repositorio
2. **Crea** una rama para tu feature (`git checkout -b feature/AmazingFeature`)
3. **Haz** tus cambios
4. **Asegúrate** de seguir los estándares de código
5. **Agrega** tests si es aplicable
6. **Commit** tus cambios (`git commit -m 'Add some AmazingFeature'`)
7. **Push** a la rama (`git push origin feature/AmazingFeature`)
8. **Abre** un Pull Request

## 📝 Estándares de Código

### PHP

- **PSR-12** para estilo de código
- **PSR-4** para autoloading
- **Comentarios** en español
- **Variables** en snake_case
- **Funciones** descriptivas

```php
<?php
/**
 * Procesa una encuesta académica
 * 
 * @param array $datos_encuesta Datos de la encuesta
 * @return bool|array Resultado del procesamiento
 */
function procesar_encuesta_academica($datos_encuesta) {
    // Validar datos de entrada
    if (empty($datos_encuesta['curso_id'])) {
        return false;
    }
    
    // Procesar...
    return $resultado;
}
```

### JavaScript

- **ES6+** cuando sea posible
- **camelCase** para variables y funciones
- **Comentarios** descriptivos
- **Semicolons** obligatorios

```javascript
/**
 * Carga dinámicamente los profesores de un curso
 * @param {number} cursoId ID del curso
 * @returns {Promise<Array>} Lista de profesores
 */
async function cargarProfesoresPorCurso(cursoId) {
    try {
        const response = await fetch(`/api/get_profesores.php?curso_id=${cursoId}`);
        return await response.json();
    } catch (error) {
        console.error('Error cargando profesores:', error);
        return [];
    }
}
```

### CSS

- **BEM methodology** para nombres de clases
- **Mobile-first** approach
- **Comentarios** para secciones complejas

```css
/* Componente: Formulario de encuesta */
.survey-form {
    max-width: 800px;
    margin: 0 auto;
}

.survey-form__question {
    margin-bottom: 1.5rem;
}

.survey-form__question--required .survey-form__label::after {
    content: " *";
    color: #dc3545;
}
```

## 🧪 Testing

### Ejecutar Tests

```bash
# Tests de PHP
php tests/run_php_tests.php

# Tests de JavaScript (si existen)
npm test

# Tests de integración
php tests/integration_tests.php
```

### Escribir Tests

Para nuevas funcionalidades, incluye tests:

```php
<?php
/**
 * Test para validación de encuestas
 */
class EncuestaValidationTest extends PHPUnit\Framework\TestCase {
    
    public function testValidarDatosEncuestaValida() {
        $datos = [
            'curso_id' => 1,
            'profesor_id' => 1,
            'respuestas' => [...]
        ];
        
        $resultado = validar_datos_encuesta($datos);
        $this->assertTrue($resultado);
    }
    
    public function testValidarDatosEncuestaInvalida() {
        $datos = []; // Datos vacíos
        
        $resultado = validar_datos_encuesta($datos);
        $this->assertFalse($resultado);
    }
}
```

## 📚 Documentación

### Documentar Código

- **Funciones públicas** deben tener docblocks completos
- **APIs** deben estar documentadas
- **Cambios importantes** deben actualizarse en README.md

### Actualizar Documentación

Si tu cambio afecta la funcionalidad:

1. **Actualiza** README.md si es necesario
2. **Agrega** ejemplos de uso
3. **Actualiza** comentarios de código

## 🏷️ Versionado

Seguimos [Semantic Versioning](https://semver.org/):

- **MAJOR**: Cambios incompatibles en API
- **MINOR**: Nueva funcionalidad compatible
- **PATCH**: Bug fixes compatibles

Ejemplo: `v1.2.3`

## 📋 Release Process

Para maintainers:

1. **Actualizar** CHANGELOG.md
2. **Bump** versión en archivos relevantes
3. **Crear** tag de git
4. **Crear** release en GitHub
5. **Actualizar** documentación

## 🎯 Áreas Donde Necesitamos Ayuda

- **Frontend**: Mejoras en UI/UX
- **Backend**: Optimización de consultas
- **Testing**: Más cobertura de tests
- **Documentación**: Tutoriales y guías
- **Traducciones**: Soporte multi-idioma
- **Seguridad**: Auditorías de seguridad

## 💬 Comunicación

- **Issues**: Para bugs y feature requests
- **Discussions**: Para preguntas generales
- **Email**: Para asuntos sensibles

## 🙏 Reconocimientos

Todos los contribuyentes serán añadidos al README.md y tendrán crédito por su trabajo.

---

¡Gracias por ayudar a hacer este proyecto mejor! 🚀
