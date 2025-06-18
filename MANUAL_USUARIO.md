# 📚 Manual de Usuario - Sistema de Encuestas Académicas

<div align="center">

![Manual de Usuario](https://img.shields.io/badge/Manual-Usuario-blue?style=for-the-badge)
![Versión](https://img.shields.io/badge/Versión-1.0-green?style=for-the-badge)
![Fecha](https://img.shields.io/badge/Fecha-Junio_2025-orange?style=for-the-badge)

*Guía completa para el uso del Sistema de Encuestas Académicas*

</div>

---

## 📋 Tabla de Contenidos

1. [Introducción](#-introducción)
2. [Acceso al Sistema](#-acceso-al-sistema)
3. [Interfaz para Estudiantes](#-interfaz-para-estudiantes)
4. [Panel Administrativo](#-panel-administrativo)
5. [Gestión de Cursos](#-gestión-de-cursos)
6. [Gestión de Profesores](#-gestión-de-profesores)
7. [Gestión de Preguntas](#-gestión-de-preguntas)
8. [Gestión de Formularios](#-gestión-de-formularios)
9. [Reportes y Estadísticas](#-reportes-y-estadísticas)
10. [Solución de Problemas](#-solución-de-problemas)
11. [Preguntas Frecuentes](#-preguntas-frecuentes)

---

## 🎯 Introducción

### ¿Qué es el Sistema de Encuestas Académicas?

El Sistema de Encuestas Académicas es una plataforma web diseñada para recopilar y analizar la opinión de los estudiantes sobre cursos y profesores. Permite:

- **Evaluación anónima** de cursos y profesores
- **Gestión administrativa** completa
- **Generación de reportes** estadísticos
- **Análisis de tendencias** educativas

### Beneficios del Sistema

✅ **Para Estudiantes:**
- Proceso de evaluación rápido y sencillo
- Anonimato garantizado
- Interfaz intuitiva y responsiva

✅ **Para Administradores:**
- Control total sobre el proceso de evaluación
- Reportes detallados y visualizaciones
- Gestión centralizada de todos los componentes

✅ **Para la Institución:**
- Mejora continua de la calidad educativa
- Datos objetivos para toma de decisiones
- Transparencia en el proceso de evaluación

---

## 🚪 Acceso al Sistema

### URLs de Acceso

- **Interfaz de Estudiantes**: `http://tu-dominio.com/`
- **Panel Administrativo**: `http://tu-dominio.com/admin/`

### Requisitos del Navegador

- **Navegadores Soportados**: Chrome, Firefox, Safari, Edge
- **JavaScript**: Debe estar habilitado
- **Cookies**: Deben estar habilitadas
- **Resolución mínima**: 320px (móvil)

---

## 👨‍🎓 Interfaz para Estudiantes

### Proceso de Evaluación

#### Paso 1: Acceder a la Encuesta

1. **Navegar** a la página principal del sistema
2. **Leer** las instrucciones de evaluación
3. **Hacer clic** en "Comenzar Encuesta Académica"

![Pantalla de Inicio](docs/images/inicio-encuesta.png)

#### Paso 2: Seleccionar Curso

1. **Seleccionar** el curso a evaluar del menú desplegable
2. **Hacer clic** en "Continuar"

> **💡 Tip**: Solo aparecerán los cursos con formularios activos

#### Paso 3: Seleccionar Profesores

1. **Seleccionar** los profesores que desea evaluar
2. **Hacer clic** en "Continuar"

> **⚠️ Importante**: Debe seleccionar al menos un profesor

#### Paso 4: Evaluación del Curso

1. **Responder** las preguntas sobre el curso
2. **Usar la escala** de evaluación:
   - 😃 **Excelente** (5 puntos)
   - 🙂 **Bueno** (4 puntos)
   - 😐 **Correcto** (3 puntos)
   - 😕 **Regular** (2 puntos)
   - 😞 **Deficiente** (1 punto)

3. **Completar** campos de texto libre (opcional)

#### Paso 5: Evaluación de Profesores

1. **Responder** las preguntas para cada profesor seleccionado
2. **Usar la misma escala** de evaluación
3. **Añadir comentarios** si lo desea

#### Paso 6: Envío de la Encuesta

1. **Revisar** todas las respuestas
2. **Hacer clic** en "Enviar Encuesta"
3. **Confirmar** el envío

> **🔒 Privacidad**: Todas las respuestas son anónimas

### Escala de Evaluación

| Emoji | Descripción | Valor | Cuándo Usar |
|-------|-------------|-------|-------------|
| 😃 | Excelente | 5 | Cuando el aspecto evaluado supera las expectativas |
| 🙂 | Bueno | 4 | Cuando el aspecto evaluado es satisfactorio |
| 😐 | Correcto | 3 | Cuando el aspecto evaluado es aceptable |
| 😕 | Regular | 2 | Cuando el aspecto evaluado tiene deficiencias |
| 😞 | Deficiente | 1 | Cuando el aspecto evaluado es insatisfactorio |

### Consejos para una Evaluación Efectiva

✅ **Haga:**
- Sea honesto y constructivo en sus respuestas
- Complete toda la encuesta para obtener datos más precisos
- Use los campos de texto para proporcionar contexto
- Evalúe basándose en su experiencia durante todo el curso

❌ **Evite:**
- Respuestas basadas en emociones momentáneas
- Comentarios ofensivos o inapropiados
- Evaluaciones basadas en rumores o información de terceros
- Dejar preguntas importantes sin responder

---

## 🔧 Panel Administrativo

### Acceso al Panel

1. **Navegar** a `/admin/`
2. **Introducir** credenciales de administrador
3. **Hacer clic** en "Iniciar Sesión"

### Dashboard Principal

El dashboard muestra un resumen completo del sistema:

#### Métricas Principales

- **📊 Total de Encuestas**: Número total de encuestas recibidas
- **📅 Encuestas Hoy**: Encuestas recibidas en el día actual
- **📆 Encuestas Semana**: Encuestas recibidas en la semana actual
- **🗓️ Encuestas Mes**: Encuestas recibidas en el mes actual

#### Estado del Sistema

- **✅ Formularios Activos**: Número de formularios disponibles
- **⚠️ Próximos a Expirar**: Formularios que expiran en 7 días
- **🎓 Cursos Activos**: Cursos con formularios disponibles
- **👨‍🏫 Profesores Evaluados**: Profesores con al menos una evaluación

#### Gráficos y Visualizaciones

- **Top 5 Cursos**: Cursos con más encuestas recibidas
- **Top 5 Profesores**: Profesores mejor evaluados
- **Tendencias**: Evolución de encuestas en el tiempo

### Navegación del Panel

El panel administrativo incluye las siguientes secciones:

| Sección | Función |
|---------|---------|
| 🏠 **Dashboard** | Vista general del sistema |
| 📚 **Cursos** | Gestión de cursos |
| 👨‍🏫 **Profesores** | Gestión de profesores |
| ❓ **Preguntas** | Gestión de preguntas |
| 📋 **Formularios** | Gestión de formularios |
| 📊 **Reportes** | Análisis y estadísticas |

---

## 📚 Gestión de Cursos

### Acceder a la Gestión de Cursos

1. **Hacer clic** en "Cursos" en el menú lateral
2. **Ver** la lista de cursos existentes

### Crear un Nuevo Curso

#### Información Requerida:

1. **Nombre del Curso** (obligatorio)
   - Ejemplo: "Cálculo I", "Historia del Arte"
   
2. **Descripción** (opcional)
   - Breve descripción del contenido del curso
   
3. **Código** (obligatorio)
   - Código único del curso
   - Ejemplo: "MAT101", "ART201"
   
4. **Créditos** (obligatorio)
   - Número de créditos académicos
   - Ejemplo: 3, 4, 6
   
5. **Estado** (Activo/Inactivo)
   - Determina si el curso aparece en las encuestas

#### Proceso de Creación:

1. **Hacer clic** en "Nuevo Curso"
2. **Completar** el formulario
3. **Hacer clic** en "Guardar"

### Editar un Curso Existente

1. **Localizar** el curso en la tabla
2. **Hacer clic** en el icono de edición (✏️)
3. **Modificar** los campos necesarios
4. **Hacer clic** en "Actualizar"

### Eliminar un Curso

1. **Localizar** el curso en la tabla
2. **Hacer clic** en el icono de eliminación (🗑️)
3. **Confirmar** la eliminación

> **⚠️ Advertencia**: Eliminar un curso puede afectar formularios y encuestas existentes

### Gestión de Estado

- **Curso Activo**: Aparece en formularios y puede recibir evaluaciones
- **Curso Inactivo**: No aparece en nuevos formularios, pero mantiene datos históricos

---

## 👨‍🏫 Gestión de Profesores

### Acceder a la Gestión de Profesores

1. **Hacer clic** en "Profesores" en el menú lateral
2. **Ver** la lista de profesores registrados

### Crear un Nuevo Profesor

#### Información Requerida:

1. **Nombre Completo** (obligatorio)
   - Nombre y apellidos del profesor
   
2. **Email** (obligatorio)
   - Correo electrónico institucional
   
3. **Departamento** (opcional)
   - Departamento académico al que pertenece
   
4. **Título Académico** (opcional)
   - Ejemplo: "Dr.", "Mg.", "Lic."
   
5. **Estado** (Activo/Inactivo)
   - Determina si el profesor aparece en las encuestas

#### Proceso de Creación:

1. **Hacer clic** en "Nuevo Profesor"
2. **Completar** el formulario
3. **Hacer clic** en "Guardar"

### Asignar Profesores a Cursos

1. **Seleccionar** el profesor
2. **Hacer clic** en "Asignar Cursos"
3. **Seleccionar** los cursos correspondientes
4. **Confirmar** la asignación

### Editar Información del Profesor

1. **Localizar** el profesor en la tabla
2. **Hacer clic** en el icono de edición (✏️)
3. **Modificar** los campos necesarios
4. **Hacer clic** en "Actualizar"

---

## ❓ Gestión de Preguntas

### Acceder a la Gestión de Preguntas

1. **Hacer clic** en "Preguntas" en el menú lateral
2. **Seleccionar** la pestaña correspondiente:
   - **Preguntas de Curso**: Para evaluar aspectos del curso
   - **Preguntas de Profesor**: Para evaluar aspectos del profesor

### Tipos de Preguntas

#### 1. Preguntas de Escala (1-5)
- **Uso**: Evaluaciones cuantitativas
- **Ejemplo**: "¿Cómo califica la claridad del contenido?"
- **Respuesta**: Escala de 1 (Deficiente) a 5 (Excelente)

#### 2. Preguntas de Texto Libre
- **Uso**: Comentarios cualitativos
- **Ejemplo**: "¿Qué aspectos del curso mejoraría?"
- **Respuesta**: Texto libre (hasta 500 caracteres)

### Crear una Nueva Pregunta

#### Para Preguntas de Curso:

1. **Seleccionar** pestaña "Preguntas de Curso"
2. **Hacer clic** en "Nueva Pregunta"
3. **Completar** la información:
   - **Texto de la Pregunta**
   - **Tipo** (Escala o Texto)
   - **Orden** (posición en el formulario)
   - **Estado** (Activa/Inactiva)
4. **Hacer clic** en "Guardar"

#### Para Preguntas de Profesor:

1. **Seleccionar** pestaña "Preguntas de Profesor"
2. **Seguir** el mismo proceso que para preguntas de curso

### Reordenar Preguntas

1. **Usar** los controles de orden (↑↓)
2. **Cambiar** el número de orden
3. **Confirmar** los cambios

> **💡 Tip**: El orden determina cómo aparecen las preguntas en la encuesta

### Preguntas Recomendadas

#### Para Cursos:
- "¿Cómo califica la organización del curso?"
- "¿El contenido del curso cumplió sus expectativas?"
- "¿Recomendaría este curso a otros estudiantes?"
- "¿Qué aspectos del curso considera más valiosos?"

#### Para Profesores:
- "¿Cómo califica la claridad de las explicaciones del profesor?"
- "¿El profesor demostró dominio de la materia?"
- "¿El profesor fue accesible para consultas?"
- "¿Qué aspectos de la enseñanza del profesor destacaría?"

---

## 📋 Gestión de Formularios

### Acceder a la Gestión de Formularios

1. **Hacer clic** en "Formularios" en el menú lateral
2. **Ver** la lista de formularios existentes

### Crear un Nuevo Formulario

#### Información Requerida:

1. **Curso** (obligatorio)
   - Seleccionar el curso a evaluar
   
2. **Nombre del Formulario** (obligatorio)
   - Ejemplo: "Evaluación Final - Cálculo I"
   
3. **Descripción** (opcional)
   - Propósito del formulario
   
4. **Fechas**:
   - **Fecha de Inicio**: Cuándo estará disponible
   - **Fecha de Fin**: Cuándo dejará de estar disponible
   
5. **Estado** (Activo/Inactivo)

#### Proceso de Creación:

1. **Hacer clic** en "Nuevo Formulario"
2. **Seleccionar** el curso
3. **Completar** la información
4. **Configurar** las fechas
5. **Hacer clic** en "Crear Formulario"

### Configurar Preguntas del Formulario

1. **Seleccionar** el formulario creado
2. **Hacer clic** en "Configurar Preguntas"
3. **Seleccionar** las preguntas de curso que incluir
4. **Seleccionar** las preguntas de profesor que incluir
5. **Confirmar** la configuración

### Gestión de Profesores en Formularios

1. **Acceder** al formulario
2. **Hacer clic** en "Gestionar Profesores"
3. **Seleccionar** los profesores que serán evaluados
4. **Confirmar** la selección

### Estados de Formularios

- **🟢 Activo**: Disponible para responder
- **🟡 Programado**: Activación futura programada
- **🔴 Expirado**: Ya no acepta respuestas
- **⚫ Inactivo**: Deshabilitado manualmente

---

## 📊 Reportes y Estadísticas

### Acceder a los Reportes

1. **Hacer clic** en "Reportes" en el menú lateral
2. **Seleccionar** los filtros de análisis

### Filtros Disponibles

#### Filtro por Curso
1. **Seleccionar** "Todos los cursos" o un curso específico
2. **Aplicar** filtro

#### Filtro por Fecha
1. **Seleccionar** "Todas las fechas" o una fecha específica
2. **Ver** solo encuestas de esa fecha

#### Filtros Combinados
- **Curso + Fecha**: Análisis específico de un curso en una fecha
- **Todos los datos**: Vista general del sistema

### Tipos de Reportes

#### 1. Resumen General
- **Total de respuestas** recibidas
- **Promedio general** de satisfacción
- **Distribución de respuestas** por escala

#### 2. Análisis por Curso
- **Promedio de evaluación** del curso
- **Número de encuestas** recibidas
- **Comentarios** de texto libre
- **Tendencias** temporales

#### 3. Análisis por Profesor
- **Promedio de evaluación** por profesor
- **Comparación** entre profesores
- **Preguntas críticas** (evaluaciones bajas)
- **Comentarios** específicos

#### 4. Gráficos Estadísticos

##### Gráfico de Torta - Distribución de Respuestas
- **Excelente** (5): Verde
- **Bueno** (4): Azul
- **Correcto** (3): Amarillo
- **Regular** (2): Naranja
- **Deficiente** (1): Rojo

##### Gráfico de Barras - Comparación entre Cursos/Profesores
- **Eje X**: Cursos o Profesores
- **Eje Y**: Promedio de evaluación

### Interpretar los Resultados

#### Rangos de Evaluación:
- **4.5 - 5.0**: 😃 Excelente
- **3.5 - 4.4**: 🙂 Bueno
- **2.5 - 3.4**: 😐 Correcto
- **1.5 - 2.4**: 😕 Regular
- **1.0 - 1.4**: 😞 Deficiente

#### Preguntas Críticas:
- **Promedio < 2.5**: Requiere atención inmediata
- **Promedio 2.5-3.0**: Área de mejora
- **Promedio > 4.0**: Fortaleza a mantener

### Exportar Reportes

1. **Generar** el reporte deseado
2. **Hacer clic** en "Exportar"
3. **Seleccionar** formato (PDF, Excel)
4. **Descargar** el archivo

---

## 🔧 Solución de Problemas

### Problemas Comunes de Estudiantes

#### "No puedo enviar la encuesta"

**Posibles causas:**
- Formulario expirado
- Campos obligatorios sin completar
- Problemas de conexión

**Soluciones:**
1. **Verificar** que todos los campos estén completos
2. **Revisar** la fecha de expiración del formulario
3. **Refrescar** la página e intentar nuevamente
4. **Contactar** al administrador si persiste

#### "No aparecen cursos para evaluar"

**Posibles causas:**
- No hay formularios activos
- Formularios fuera de fecha

**Soluciones:**
1. **Contactar** al administrador
2. **Esperar** a que se activen nuevos formularios

#### "El sistema va lento"

**Soluciones:**
1. **Verificar** la conexión a internet
2. **Cerrar** otras pestañas del navegador
3. **Limpiar** caché del navegador
4. **Intentar** en horarios de menor demanda

### Problemas Comunes de Administradores

#### "No puedo acceder al panel administrativo"

**Soluciones:**
1. **Verificar** credenciales de acceso
2. **Comprobar** que la URL sea correcta (`/admin/`)
3. **Limpiar** cookies del navegador
4. **Contactar** al administrador del sistema

#### "Los reportes no muestran datos"

**Posibles causas:**
- Filtros muy restrictivos
- No hay encuestas en el período seleccionado

**Soluciones:**
1. **Ampliar** el rango de fechas
2. **Seleccionar** "Todos los cursos"
3. **Verificar** que haya encuestas enviadas

#### "Error al crear formularios"

**Soluciones:**
1. **Verificar** que el curso tenga profesores asignados
2. **Comprobar** que haya preguntas activas
3. **Revisar** las fechas de inicio y fin
4. **Verificar** conexión a la base de datos

### Códigos de Error Comunes

| Código | Descripción | Solución |
|--------|-------------|----------|
| 404 | Página no encontrada | Verificar URL |
| 403 | Acceso prohibido | Verificar permisos |
| 500 | Error del servidor | Contactar administrador |
| 408 | Tiempo de espera agotado | Refrescar página |

---

## ❓ Preguntas Frecuentes

### Para Estudiantes

**P: ¿Mis respuestas son realmente anónimas?**
R: Sí, el sistema no registra información personal que permita identificar quién respondió cada encuesta.

**P: ¿Puedo modificar mi respuesta después de enviarla?**
R: No, una vez enviada la encuesta no se puede modificar. Asegúrese de revisar todas sus respuestas antes de enviar.

**P: ¿Qué pasa si no completo toda la encuesta?**
R: Debe completar al menos las preguntas obligatorias para poder enviar la encuesta.

**P: ¿Cuánto tiempo tengo para completar la encuesta?**
R: Cada formulario tiene fechas específicas de inicio y fin. Debe completar la encuesta dentro de ese período.

**P: ¿Puedo evaluar el mismo curso varias veces?**
R: Generalmente no, cada estudiante puede evaluar cada formulario una sola vez.

### Para Administradores

**P: ¿Cómo puedo cambiar la escala de evaluación?**
R: La escala está integrada en el sistema. Para cambiarla, debe modificar el código fuente o contactar al desarrollador.

**P: ¿Puedo exportar todos los datos a la vez?**
R: Sí, puede generar reportes generales y exportarlos en diferentes formatos.

**P: ¿Cómo puedo respaldar la información?**
R: Debe realizar respaldos regulares de la base de datos MySQL. Consulte con su administrador de sistemas.

**P: ¿Puedo personalizar las preguntas por facultad?**
R: Puede crear diferentes conjuntos de preguntas y asignarlas a diferentes formularios según sus necesidades.

**P: ¿Hay límite en el número de encuestas?**
R: No hay límite técnico, pero el rendimiento puede verse afectado con volúmenes muy altos de datos.

### Técnicas

**P: ¿Qué navegadores son compatibles?**
R: Chrome, Firefox, Safari, Edge (versiones recientes).

**P: ¿Funciona en dispositivos móviles?**
R: Sí, el sistema tiene diseño responsivo y funciona en móviles y tablets.

**P: ¿Necesito instalar algo adicional?**
R: No, solo necesita un navegador web moderno con JavaScript habilitado.

**P: ¿Qué hago si encuentro un error?**
R: Anote el mensaje de error, los pasos que realizó, y contacte al administrador del sistema.

---

## 📞 Soporte y Contacto

### Soporte Técnico

**Para problemas técnicos:**
- 📧 **Email**: soporte@encuestas-academicas.com
- 📞 **Teléfono**: +XX XXX XXX XXXX
- 🕒 **Horario**: Lunes a Viernes, 8:00 AM - 6:00 PM

### Soporte Académico

**Para consultas sobre uso del sistema:**
- 📧 **Email**: academico@encuestas-academicas.com
- 🏢 **Oficina**: Edificio Administrativo, Piso 3
- 🕒 **Horario**: Lunes a Viernes, 9:00 AM - 5:00 PM

### Recursos Adicionales

- 📖 **Documentación**: [docs.encuestas-academicas.com](http://docs.encuestas-academicas.com)
- 🎥 **Videos tutoriales**: [youtube.com/encuestas-academicas](http://youtube.com/encuestas-academicas)
- 💬 **Foro de usuarios**: [forum.encuestas-academicas.com](http://forum.encuestas-academicas.com)

---

## 📝 Notas de Versión

### Versión 1.0 (Junio 2025)

**Funcionalidades incluidas:**
- ✅ Sistema completo de encuestas
- ✅ Panel administrativo
- ✅ Reportes y estadísticas
- ✅ Diseño responsivo
- ✅ Seguridad implementada

**Próximas funcionalidades:**
- 🔄 Notificaciones por email
- 📊 Dashboard en tiempo real
- 📱 Aplicación móvil
- 🔍 Búsqueda avanzada

---

## 📄 Apéndices

### Apéndice A: Atajos de Teclado

| Combinación | Función |
|-------------|---------|
| `Ctrl + S` | Guardar formulario |
| `Ctrl + Enter` | Enviar encuesta |
| `Esc` | Cancelar acción |
| `Tab` | Navegar entre campos |

### Apéndice B: Códigos de Curso Estándar

| Prefijo | Área |
|---------|------|
| MAT | Matemáticas |
| FIS | Física |
| QUI | Química |
| BIO | Biología |
| HIS | Historia |
| LIT | Literatura |
| ING | Inglés |
| INF | Informática |

### Apéndice C: Mejores Prácticas

#### Para Administradores:
1. **Revisar** formularios antes de activarlos
2. **Programar** evaluaciones con tiempo suficiente
3. **Comunicar** fechas importantes a estudiantes
4. **Realizar** respaldos regulares
5. **Monitorear** el uso del sistema

#### Para Estudiantes:
1. **Leer** todas las instrucciones
2. **Tomarse tiempo** para reflexionar
3. **Ser constructivo** en comentarios
4. **Completar** toda la encuesta
5. **Enviar** antes de la fecha límite

---

<div align="center">

**Manual de Usuario - Sistema de Encuestas Académicas**

*Versión 1.0 - Junio 2025*

*Para soporte técnico, contacte: soporte@encuestas-academicas.com*

</div>
