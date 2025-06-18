-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1:3306
-- Tiempo de generación: 16-06-2025 a las 10:13:16
-- Versión del servidor: 9.1.0
-- Versión de PHP: 8.3.14

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `academia_encuestas`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cursos`
--

DROP TABLE IF EXISTS `cursos`;
CREATE TABLE IF NOT EXISTS `cursos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nombre` varchar(255) COLLATE utf8mb4_spanish2_ci NOT NULL COMMENT 'Nombre del curso',
  `descripcion` varchar(1000) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL COMMENT 'Descripción detallada del curso (máximo 1000 caracteres)',
  `codigo` varchar(50) COLLATE utf8mb4_spanish2_ci DEFAULT NULL COMMENT 'Código único del curso (ej: MAT101)',
  `creditos` tinyint DEFAULT '0' COMMENT 'Número de créditos del curso',
  `activo` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'Estado del curso (activo/inactivo)',
  `fecha_creacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha de creación del registro',
  `fecha_modificacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Fecha de última modificación',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_cursos_codigo` (`codigo`),
  KEY `idx_cursos_activo` (`activo`),
  KEY `idx_cursos_nombre` (`nombre`),
  KEY `idx_cursos_descripcion` (`descripcion`(100))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish2_ci COMMENT='Tabla de cursos académicos';

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `curso_profesores`
--

DROP TABLE IF EXISTS `curso_profesores`;
CREATE TABLE IF NOT EXISTS `curso_profesores` (
  `id` int NOT NULL AUTO_INCREMENT,
  `formulario_id` int NOT NULL COMMENT 'ID del formulario',
  `profesor_id` int NOT NULL COMMENT 'ID del profesor',
  `orden` int NOT NULL DEFAULT '0' COMMENT 'Orden de aparición del profesor en el formulario',
  `activo` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'Estado de la relación (activo/inactivo)',
  `fecha_asignacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha de asignación del profesor',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_formulario_profesor` (`formulario_id`,`profesor_id`),
  KEY `idx_curso_profesores_formulario` (`formulario_id`),
  KEY `idx_curso_profesores_profesor` (`profesor_id`),
  KEY `idx_curso_profesores_orden` (`orden`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish2_ci COMMENT='Relación entre formularios y profesores';

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `encuestas`
--

DROP TABLE IF EXISTS `encuestas`;
CREATE TABLE IF NOT EXISTS `encuestas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `curso_id` int NOT NULL COMMENT 'ID del curso evaluado',
  `formulario_id` int NOT NULL COMMENT 'ID del formulario utilizado',
  `fecha_envio` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha y hora de envío de la encuesta',
  `ip_cliente` varchar(45) COLLATE utf8mb4_spanish2_ci DEFAULT NULL COMMENT 'Dirección IP del cliente (IPv4 o IPv6)',
  `user_agent` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL COMMENT 'User agent del navegador (máximo 500 caracteres)',
  `tiempo_completado` int DEFAULT NULL COMMENT 'Tiempo en segundos para completar la encuesta',
  `es_anonima` tinyint(1) DEFAULT '1' COMMENT 'Indica si la encuesta es anónima',
  `hash_session` varchar(255) COLLATE utf8mb4_spanish2_ci DEFAULT NULL COMMENT 'Hash de sesión para control anti-spam',
  PRIMARY KEY (`id`),
  KEY `idx_encuestas_curso` (`curso_id`),
  KEY `idx_encuestas_formulario` (`formulario_id`),
  KEY `idx_encuestas_fecha` (`fecha_envio`),
  KEY `idx_encuestas_ip` (`ip_cliente`),
  KEY `idx_encuestas_hash` (`hash_session`),
  KEY `idx_encuestas_fecha_curso` (`fecha_envio`,`curso_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish2_ci COMMENT='Registro de encuestas completadas';

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `formularios`
--

DROP TABLE IF EXISTS `formularios`;
CREATE TABLE IF NOT EXISTS `formularios` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nombre` varchar(255) COLLATE utf8mb4_spanish2_ci NOT NULL COMMENT 'Nombre descriptivo del formulario',
  `curso_id` int NOT NULL COMMENT 'ID del curso asociado',
  `descripcion` varchar(1000) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL COMMENT 'Descripción del formulario (máximo 1000 caracteres)',
  `fecha_inicio` date DEFAULT NULL COMMENT 'Fecha de inicio de disponibilidad',
  `fecha_fin` date DEFAULT NULL COMMENT 'Fecha de fin de disponibilidad',
  `activo` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'Estado del formulario (activo/inactivo)',
  `permite_respuestas_anonimas` tinyint(1) DEFAULT '1' COMMENT 'Permite respuestas sin identificación',
  `fecha_creacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha de creación del registro',
  `fecha_modificacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Fecha de última modificación',
  `creado_por` varchar(255) COLLATE utf8mb4_spanish2_ci NOT NULL DEFAULT 'admin' COMMENT 'Usuario que creó el formulario',
  PRIMARY KEY (`id`),
  KEY `idx_formularios_curso` (`curso_id`),
  KEY `idx_formularios_activo` (`activo`),
  KEY `idx_formularios_fechas` (`fecha_inicio`,`fecha_fin`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish2_ci COMMENT='Formularios de encuesta por curso';

--
-- Disparadores `formularios`
--
DROP TRIGGER IF EXISTS `tr_validar_fechas_formulario`;
DELIMITER $$
CREATE TRIGGER `tr_validar_fechas_formulario` BEFORE INSERT ON `formularios` FOR EACH ROW BEGIN
    IF NEW.fecha_fin IS NOT NULL AND NEW.fecha_inicio IS NOT NULL THEN
        IF NEW.fecha_fin < NEW.fecha_inicio THEN
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La fecha de fin no puede ser anterior a la fecha de inicio';
        END IF;
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `preguntas`
--

DROP TABLE IF EXISTS `preguntas`;
CREATE TABLE IF NOT EXISTS `preguntas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `texto` varchar(1000) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci NOT NULL COMMENT 'Texto de la pregunta (máximo 1000 caracteres)',
  `seccion` enum('curso','profesor') COLLATE utf8mb4_spanish2_ci NOT NULL COMMENT 'Sección a la que pertenece la pregunta',
  `tipo` enum('escala','texto','opcion_multiple') COLLATE utf8mb4_spanish2_ci NOT NULL DEFAULT 'escala' COMMENT 'Tipo de pregunta',
  `opciones` json DEFAULT NULL COMMENT 'Opciones para preguntas de opción múltiple (formato JSON)',
  `orden` int NOT NULL DEFAULT '0' COMMENT 'Orden de aparición de la pregunta',
  `es_obligatoria` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'Indica si la pregunta es obligatoria',
  `activa` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'Estado de la pregunta (activa/inactiva)',
  `fecha_creacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha de creación del registro',
  `fecha_modificacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Fecha de última modificación',
  PRIMARY KEY (`id`),
  KEY `idx_preguntas_seccion` (`seccion`),
  KEY `idx_preguntas_activa` (`activa`),
  KEY `idx_preguntas_orden` (`orden`),
  KEY `idx_preguntas_tipo` (`tipo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish2_ci COMMENT='Catálogo de preguntas para encuestas';

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `profesores`
--

DROP TABLE IF EXISTS `profesores`;
CREATE TABLE IF NOT EXISTS `profesores` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nombre` varchar(255) COLLATE utf8mb4_spanish2_ci NOT NULL COMMENT 'Nombre completo del profesor',
  `email` varchar(255) COLLATE utf8mb4_spanish2_ci DEFAULT NULL COMMENT 'Correo electrónico institucional',
  `telefono` varchar(20) COLLATE utf8mb4_spanish2_ci DEFAULT NULL COMMENT 'Número de teléfono de contacto',
  `especialidad` varchar(255) COLLATE utf8mb4_spanish2_ci DEFAULT NULL COMMENT 'Área de especialización del profesor',
  `grado_academico` enum('Licenciatura','Maestría','Doctorado','Postdoctorado') COLLATE utf8mb4_spanish2_ci DEFAULT NULL COMMENT 'Máximo grado académico',
  `departamento` varchar(255) COLLATE utf8mb4_spanish2_ci DEFAULT NULL COMMENT 'Departamento al que pertenece',
  `activo` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'Estado del profesor (activo/inactivo)',
  `fecha_creacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha de creación del registro',
  `fecha_modificacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Fecha de última modificación',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_profesores_email` (`email`),
  KEY `idx_profesores_activo` (`activo`),
  KEY `idx_profesores_especialidad` (`especialidad`),
  KEY `idx_profesores_nombre` (`nombre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish2_ci COMMENT='Tabla de profesores';

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `respuestas`
--

DROP TABLE IF EXISTS `respuestas`;
CREATE TABLE IF NOT EXISTS `respuestas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `encuesta_id` int NOT NULL COMMENT 'ID de la encuesta',
  `pregunta_id` int NOT NULL COMMENT 'ID de la pregunta respondida',
  `profesor_id` int DEFAULT NULL COMMENT 'ID del profesor evaluado (NULL para preguntas de curso)',
  `valor_int` tinyint DEFAULT NULL COMMENT 'Valor numérico para preguntas de escala (1-5)',
  `valor_text` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL COMMENT 'Valor de texto para preguntas abiertas (máximo 500 caracteres)',
  `valor_json` json DEFAULT NULL COMMENT 'Valor en formato JSON para respuestas complejas',
  `fecha_respuesta` timestamp NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha de la respuesta',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_respuesta_unica` (`encuesta_id`,`pregunta_id`,`profesor_id`),
  KEY `idx_respuestas_encuesta` (`encuesta_id`),
  KEY `idx_respuestas_pregunta` (`pregunta_id`),
  KEY `idx_respuestas_profesor` (`profesor_id`),
  KEY `idx_respuestas_valor_int` (`valor_int`),
  KEY `idx_respuestas_encuesta_pregunta` (`encuesta_id`,`pregunta_id`),
  KEY `idx_respuestas_profesor_valor` (`profesor_id`,`valor_int`),
  KEY `idx_respuestas_valor_text` (`valor_text`(100))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish2_ci COMMENT='Respuestas individuales de las encuestas';

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `v_estadisticas_curso`
-- (Véase abajo para la vista actual)
--
DROP VIEW IF EXISTS `v_estadisticas_curso`;
CREATE TABLE IF NOT EXISTS `v_estadisticas_curso` (
`curso_id` int
,`curso_nombre` varchar(255)
,`curso_codigo` varchar(50)
,`total_encuestas` bigint
,`total_profesores` bigint
,`promedio_curso` decimal(6,2)
,`ultima_evaluacion` datetime
);

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `v_estadisticas_profesor`
-- (Véase abajo para la vista actual)
--
DROP VIEW IF EXISTS `v_estadisticas_profesor`;
CREATE TABLE IF NOT EXISTS `v_estadisticas_profesor` (
`profesor_id` int
,`profesor_nombre` varchar(255)
,`especialidad` varchar(255)
,`total_evaluaciones` bigint
,`promedio_general` decimal(6,2)
,`cursos_asignados` bigint
);

-- --------------------------------------------------------

--
-- Estructura para la vista `v_estadisticas_curso`
--
DROP TABLE IF EXISTS `v_estadisticas_curso`;

DROP VIEW IF EXISTS `v_estadisticas_curso`;
CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_estadisticas_curso`  AS SELECT `c`.`id` AS `curso_id`, `c`.`nombre` AS `curso_nombre`, `c`.`codigo` AS `curso_codigo`, count(distinct `e`.`id`) AS `total_encuestas`, count(distinct `cp`.`profesor_id`) AS `total_profesores`, round(avg((case when ((`p`.`seccion` = 'curso') and (`r`.`valor_int` is not null)) then `r`.`valor_int` end)),2) AS `promedio_curso`, max(`e`.`fecha_envio`) AS `ultima_evaluacion` FROM (((((`cursos` `c` left join `formularios` `f` on((`c`.`id` = `f`.`curso_id`))) left join `encuestas` `e` on((`f`.`id` = `e`.`formulario_id`))) left join `curso_profesores` `cp` on((`f`.`id` = `cp`.`formulario_id`))) left join `respuestas` `r` on((`e`.`id` = `r`.`encuesta_id`))) left join `preguntas` `p` on((`r`.`pregunta_id` = `p`.`id`))) WHERE ((`c`.`activo` = true) AND (`f`.`activo` = true)) GROUP BY `c`.`id`, `c`.`nombre`, `c`.`codigo` ;

-- --------------------------------------------------------

--
-- Estructura para la vista `v_estadisticas_profesor`
--
DROP TABLE IF EXISTS `v_estadisticas_profesor`;

DROP VIEW IF EXISTS `v_estadisticas_profesor`;
CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_estadisticas_profesor`  AS SELECT `pr`.`id` AS `profesor_id`, `pr`.`nombre` AS `profesor_nombre`, `pr`.`especialidad` AS `especialidad`, count(distinct `e`.`id`) AS `total_evaluaciones`, round(avg((case when ((`p`.`seccion` = 'profesor') and (`r`.`valor_int` is not null)) then `r`.`valor_int` end)),2) AS `promedio_general`, count(distinct `cp`.`formulario_id`) AS `cursos_asignados` FROM ((((`profesores` `pr` left join `curso_profesores` `cp` on((`pr`.`id` = `cp`.`profesor_id`))) left join `encuestas` `e` on((`cp`.`formulario_id` = `e`.`formulario_id`))) left join `respuestas` `r` on(((`e`.`id` = `r`.`encuesta_id`) and (`r`.`profesor_id` = `pr`.`id`)))) left join `preguntas` `p` on((`r`.`pregunta_id` = `p`.`id`))) WHERE (`pr`.`activo` = true) GROUP BY `pr`.`id`, `pr`.`nombre`, `pr`.`especialidad` ;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `curso_profesores`
--
ALTER TABLE `curso_profesores`
  ADD CONSTRAINT `curso_profesores_ibfk_1` FOREIGN KEY (`formulario_id`) REFERENCES `formularios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `curso_profesores_ibfk_2` FOREIGN KEY (`profesor_id`) REFERENCES `profesores` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `encuestas`
--
ALTER TABLE `encuestas`
  ADD CONSTRAINT `encuestas_ibfk_1` FOREIGN KEY (`curso_id`) REFERENCES `cursos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `encuestas_ibfk_2` FOREIGN KEY (`formulario_id`) REFERENCES `formularios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `formularios`
--
ALTER TABLE `formularios`
  ADD CONSTRAINT `formularios_ibfk_1` FOREIGN KEY (`curso_id`) REFERENCES `cursos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `respuestas`
--
ALTER TABLE `respuestas`
  ADD CONSTRAINT `respuestas_ibfk_1` FOREIGN KEY (`encuesta_id`) REFERENCES `encuestas` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `respuestas_ibfk_2` FOREIGN KEY (`pregunta_id`) REFERENCES `preguntas` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `respuestas_ibfk_3` FOREIGN KEY (`profesor_id`) REFERENCES `profesores` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
