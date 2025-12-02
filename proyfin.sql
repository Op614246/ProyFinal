-- phpMyAdmin SQL Dump
-- version 4.9.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3307
-- Generation Time: Dec 02, 2025 at 11:53 PM
-- Server version: 10.4.8-MariaDB
-- PHP Version: 7.1.33

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `proyfin`
--

-- --------------------------------------------------------

--
-- Table structure for table `categorias`
--

CREATE TABLE `categorias` (
  `id` int(11) NOT NULL,
  `nombre` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `color` varchar(7) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `activo` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `categorias`
--

INSERT INTO `categorias` (`id`, `nombre`, `descripcion`, `color`, `activo`, `created_at`) VALUES
(1, 'Operaciones', 'Tareas operativas', '#FF6B6B', 1, '2025-11-29 17:47:41'),
(2, 'Inventario', 'Gestión de inventario', '#4ECDC4', 1, '2025-11-29 17:47:41'),
(3, 'Reportes', 'Generación de reportes', '#95E1D3', 1, '2025-11-29 17:47:41'),
(4, 'Limpieza', 'Tareas de limpieza', '#FFE66D', 1, '2025-11-29 17:47:41'),
(5, 'Almacenes', 'Gestión de almacenes', '#A8E6CF', 1, '2025-11-29 17:47:41'),
(6, 'Capacitación', 'Entrenamiento de personal', '#FFD3B6', 1, '2025-11-29 17:47:41'),
(7, 'Informes', 'Elaboración de informes', '#FFAAA5', 1, '2025-11-29 17:47:41'),
(8, 'Personal', 'Tareas personales', '#FF8B94', 1, '2025-11-29 17:47:41');

-- --------------------------------------------------------

--
-- Table structure for table `evidencia_imagenes`
--

CREATE TABLE `evidencia_imagenes` (
  `id` int(11) NOT NULL,
  `evidencia_id` int(11) NOT NULL,
  `file_path` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Ruta del archivo',
  `file_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Nombre original del archivo',
  `file_size_kb` decimal(10,2) DEFAULT NULL COMMENT 'Tamaño en KB',
  `mime_type` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `uploaded_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `subtareas`
--

CREATE TABLE `subtareas` (
  `id` int(11) NOT NULL,
  `task_id` int(11) DEFAULT NULL,
  `titulo` varchar(250) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `estado` enum('Pendiente','En progreso','Completada','Cerrada','Activo','Inactiva') COLLATE utf8mb4_unicode_ci DEFAULT 'Pendiente',
  `prioridad` enum('Baja','Media','Alta') COLLATE utf8mb4_unicode_ci DEFAULT 'Media',
  `completada` tinyint(1) DEFAULT 0,
  `progreso` int(11) DEFAULT 0,
  `fechaAsignacion` date DEFAULT NULL,
  `fechaVencimiento` date DEFAULT NULL,
  `categoria_id` int(11) DEFAULT NULL,
  `horainicio` time DEFAULT NULL,
  `horafin` time DEFAULT NULL,
  `usuarioasignado_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `subtareas`
--

INSERT INTO `subtareas` (`id`, `task_id`, `titulo`, `descripcion`, `estado`, `prioridad`, `completada`, `progreso`, `fechaAsignacion`, `fechaVencimiento`, `categoria_id`, `horainicio`, `horafin`, `usuarioasignado_id`, `created_at`, `updated_at`) VALUES
(1, 1, 'Recopilar datos de ventas', 'Recopilar y compilar todos los datos de ventas del día anterior', 'Completada', 'Alta', 1, 100, '2025-11-28', '2025-11-28', 3, '08:00:00', '08:30:00', 3, '2025-11-29 17:47:41', '2025-11-30 05:25:38'),
(2, 1, 'Generar gráficos de análisis', 'Crear gráficos visuales para presentar los datos de ventas', 'Completada', 'Media', 1, 100, '2025-11-28', '2025-11-28', 3, '08:30:00', '09:00:00', 4, '2025-11-29 17:47:41', '2025-11-30 05:25:38'),
(3, 2, 'Contar ingredientes principales', 'Contar todos los ingredientes principales de la cocina', 'En progreso', 'Alta', 0, 100, '2025-11-28', '2025-11-28', 2, '10:30:00', '11:00:00', 5, '2025-11-29 17:47:41', '2025-11-30 05:25:38'),
(4, 2, 'Verificar fechas de vencimiento', 'Revisar las fechas de vencimiento de todos los productos', 'En progreso', 'Alta', 0, 0, '2025-11-28', '2025-11-28', 2, '11:00:00', '11:30:00', NULL, '2025-11-29 17:47:41', '2025-11-30 05:25:47'),
(5, 2, 'Actualizar sistema de inventario', 'Actualizar el sistema con los datos del inventario realizado', 'Pendiente', 'Media', 0, 0, '2025-11-28', '2025-11-28', 2, '11:30:00', '12:30:00', NULL, '2025-11-29 17:47:41', '2025-11-30 05:25:47'),
(6, 3, 'Contar ingredientes principales', 'Contar todos los ingredientes principales del almacén', 'Pendiente', 'Alta', 0, 0, '2025-11-28', '2025-11-28', 2, '14:00:00', '14:30:00', 5, '2025-11-29 17:47:41', '2025-11-30 05:25:38'),
(7, 3, 'Verificar fechas de vencimiento', 'Revisar las fechas de vencimiento de todos los productos', 'Pendiente', 'Alta', 0, 0, '2025-11-28', '2025-11-28', 2, '14:30:00', '15:00:00', NULL, '2025-11-29 17:47:41', '2025-11-30 05:25:47'),
(11, 9, 'pruebita', 'asdasjkd', 'Pendiente', 'Media', 0, 0, '2025-11-30', NULL, NULL, NULL, NULL, NULL, '2025-11-30 18:45:38', '2025-11-30 18:45:38'),
(12, 7, 'Prueba 2', 'akmsdas', 'Pendiente', 'Baja', 0, 0, '2025-11-30', NULL, NULL, NULL, NULL, NULL, '2025-11-30 20:18:05', '2025-11-30 20:18:05'),
(13, 10, 'Realizar pruebas', '', 'Pendiente', 'Baja', 0, 0, '2025-12-01', NULL, NULL, NULL, NULL, NULL, '2025-12-01 14:50:47', '2025-12-01 14:50:47');

-- --------------------------------------------------------

--
-- Table structure for table `sucursales`
--

CREATE TABLE `sucursales` (
  `id` int(11) NOT NULL,
  `nombre` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `direccion` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `activo` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `sucursales`
--

INSERT INTO `sucursales` (`id`, `nombre`, `direccion`, `activo`, `created_at`) VALUES
(1, 'Central', 'Av. Principal 123', 1, '2025-11-30 04:00:56'),
(2, 'Norte', 'Calle Norte 456', 1, '2025-11-30 04:00:56'),
(3, 'Sur', 'Av. Sur 789', 1, '2025-11-30 04:00:56'),
(4, 'Este', 'Jr. Este 321', 1, '2025-11-30 04:00:56');

-- --------------------------------------------------------

--
-- Table structure for table `tasks`
--

CREATE TABLE `tasks` (
  `id` int(11) NOT NULL,
  `title` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `categoria_id` int(11) DEFAULT NULL,
  `status` enum('pending','in_process','completed','incomplete','inactive','closed') NOT NULL,
  `priority` enum('high','medium','low') NOT NULL,
  `assigned_user_id` int(11) DEFAULT NULL,
  `created_by_user_id` int(11) NOT NULL,
  `sucursal_id` int(11) DEFAULT NULL,
  `deadline` date NOT NULL,
  `fecha_asignacion` date DEFAULT NULL,
  `horainicio` time DEFAULT NULL,
  `horafin` time DEFAULT NULL,
  `progreso` int(11) DEFAULT 0,
  `completed_at` datetime DEFAULT NULL,
  `reopened_at` datetime DEFAULT NULL,
  `completion_notes` text DEFAULT NULL,
  `reabierta_por` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `tasks`
--

INSERT INTO `tasks` (`id`, `title`, `description`, `categoria_id`, `status`, `priority`, `assigned_user_id`, `created_by_user_id`, `sucursal_id`, `deadline`, `fecha_asignacion`, `horainicio`, `horafin`, `progreso`, `completed_at`, `reopened_at`, `completion_notes`, `reabierta_por`, `created_at`, `updated_at`) VALUES
(1, 'Revisar inventario almacén', 'Hacer conteo fÝsico del almacÚn principal', 2, 'inactive', 'high', 4, 3, 1, '2025-12-01', '2025-11-29', '08:00:00', '12:00:00', 0, NULL, NULL, NULL, NULL, '2025-11-29 23:01:43', '2025-12-01 09:36:17'),
(2, 'Limpieza área de ventas', 'Limpieza general del piso de ventas', 4, 'inactive', 'medium', 4, 3, 1, '2025-11-30', '2025-11-29', '14:00:00', '16:00:00', 50, NULL, NULL, NULL, NULL, '2025-11-29 23:01:43', '2025-12-01 09:36:17'),
(3, 'Generar reporte mensual', 'Elaborar reporte de ventas noviembre', 3, 'completed', 'high', 5, 3, 2, '2025-12-02', '2025-11-29', '09:00:00', '11:00:00', 100, '2025-11-30 02:10:36', NULL, '', NULL, '2025-11-29 23:01:43', '2025-11-30 02:10:36'),
(4, 'Capacitación nuevo sistema', 'Entrenar al personal en nuevo software', 6, 'inactive', 'medium', NULL, 3, 1, '2025-12-05', '2025-11-29', '10:00:00', '12:00:00', 0, NULL, NULL, NULL, NULL, '2025-11-29 23:01:43', '2025-12-01 09:36:17'),
(5, 'Actualizar precios productos', 'Cambiar etiquetas de precios promocionales', 1, 'inactive', 'low', 4, 3, 3, '2025-12-01', '2025-11-30', '08:00:00', '10:00:00', 100, NULL, NULL, NULL, NULL, '2025-11-29 23:01:43', '2025-12-02 11:51:31'),
(6, 'Verificar stock mínimo', 'Revisar productos con stock bajo', 2, 'inactive', 'high', 5, 3, 2, '2025-12-01', '2025-11-29', '15:00:00', '17:00:00', 0, NULL, NULL, NULL, NULL, '2025-11-29 23:01:43', '2025-12-01 09:36:17'),
(7, 'nueva', 'prueba', 4, 'completed', 'medium', 4, 3, 2, '2025-12-01', '2025-11-30', NULL, NULL, 100, '2025-11-30 15:39:53', NULL, 'asdasdad', NULL, '2025-11-30 02:01:10', '2025-11-30 16:46:29'),
(8, '2da', 'pueba', 6, 'inactive', 'medium', 5, 3, 2, '2025-11-30', '2025-11-30', NULL, NULL, 100, '2025-11-30 15:20:20', NULL, 'asdads', NULL, '2025-11-30 02:07:10', '2025-12-02 11:51:31'),
(9, 'N-2', 'decrip', 1, 'inactive', 'low', 4, 3, 4, '2025-12-01', '2025-11-30', '18:00:00', '17:00:00', 0, NULL, NULL, NULL, NULL, '2025-11-30 13:02:45', '2025-12-02 11:51:31'),
(10, 'N-2', 'Hola', 2, 'completed', 'high', 4, 3, 4, '2025-12-05', '2025-12-03', '10:00:00', '10:00:00', 100, '2025-12-01 09:52:36', NULL, 'Hay alguno errores', NULL, '2025-12-01 09:50:12', '2025-12-01 09:52:36'),
(11, 'N-3', 'Prueba sin asignar', 2, 'pending', 'low', NULL, 3, 2, '2025-12-01', '2025-12-01', '10:00:00', '12:00:00', 0, NULL, NULL, NULL, NULL, '2025-12-01 09:54:28', '2025-12-01 09:54:28');

-- --------------------------------------------------------

--
-- Table structure for table `task_evidencias`
--

CREATE TABLE `task_evidencias` (
  `id` int(11) NOT NULL,
  `task_id` int(11) NOT NULL,
  `archivo` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Ruta del archivo de evidencia',
  `tipo` enum('imagen','documento','otro') COLLATE utf8mb4_unicode_ci DEFAULT 'imagen',
  `nombre_original` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Nombre original del archivo subido',
  `tamanio` int(11) DEFAULT NULL COMMENT 'Tamanio en bytes',
  `uploaded_by` int(11) DEFAULT NULL COMMENT 'Usuario que subio la evidencia',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `task_evidencias`
--

INSERT INTO `task_evidencias` (`id`, `task_id`, `archivo`, `tipo`, `nombre_original`, `tamanio`, `uploaded_by`, `created_at`) VALUES
(1, 7, 'uploads/evidencias/tarea_7_1764535193.png', 'imagen', NULL, NULL, 4, '2025-11-30 20:39:53'),
(2, 8, 'uploads/evidencias/tarea_8_1764534020.png', 'imagen', NULL, NULL, 5, '2025-11-30 20:20:20'),
(3, 10, 'uploads/evidencias/tarea_10_1764600756.png', 'imagen', NULL, NULL, 4, '2025-12-01 14:52:36');

-- --------------------------------------------------------

--
-- Table structure for table `task_reaperturas`
--

CREATE TABLE `task_reaperturas` (
  `id` int(11) NOT NULL,
  `task_id` int(11) NOT NULL,
  `reopened_by` int(11) DEFAULT NULL COMMENT 'Usuario que reabrio la tarea',
  `reopened_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `motivo` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Motivo de reapertura',
  `observaciones` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `previous_status` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `previous_assigned_user_id` int(11) DEFAULT NULL,
  `previous_deadline` date DEFAULT NULL,
  `previous_priority` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `previous_completed_at` timestamp NULL DEFAULT NULL,
  `new_assigned_user_id` int(11) DEFAULT NULL,
  `new_deadline` date DEFAULT NULL,
  `new_priority` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `task_reaperturas`
--

INSERT INTO `task_reaperturas` (`id`, `task_id`, `reopened_by`, `reopened_at`, `motivo`, `observaciones`, `previous_status`, `previous_assigned_user_id`, `previous_deadline`, `previous_priority`, `previous_completed_at`, `new_assigned_user_id`, `new_deadline`, `new_priority`) VALUES
(1, 5, NULL, '2025-12-02 17:40:36', 'Revisión requerida', '', 'completed', NULL, NULL, NULL, NULL, 4, '2025-12-01', 'low'),
(2, 7, NULL, '2025-12-02 17:40:36', 'Revisión requerida', 'asdasd', 'completed', NULL, NULL, NULL, '2025-11-30 20:39:53', 4, '2025-12-01', 'medium'),
(3, 8, NULL, '2025-12-02 17:40:36', 'Corrección de alcance', 'asdasdasd', 'completed', NULL, NULL, NULL, '2025-11-30 20:20:20', 5, '2025-11-30', 'medium');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('admin','user') NOT NULL,
  `nombre` varchar(100) DEFAULT NULL,
  `apellido` varchar(100) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `departamento` varchar(100) DEFAULT NULL,
  `estado` enum('Disponible','Ocupado','No disponible') DEFAULT 'Disponible',
  `tareas_activas` int(11) DEFAULT 0,
  `turno` varchar(50) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `failed_attempts` int(11) DEFAULT 0,
  `last_attempt_time` datetime DEFAULT NULL,
  `lockout_until` datetime DEFAULT NULL,
  `is_permanently_locked` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password_hash`, `role`, `nombre`, `apellido`, `email`, `departamento`, `estado`, `tareas_activas`, `turno`, `is_active`, `failed_attempts`, `last_attempt_time`, `lockout_until`, `is_permanently_locked`) VALUES
(3, 'cfJABVgP2iq6bOpsDtsA9g==', '$2y$10$ONcmMcyaa6cwyl5qxdRJh.nT2aUkwa6a1qVTjpba9a2/6mBmwpWqK', 'admin', 'Carlos', 'Mendoza', 'carlos@empresa.com', 'TI', 'Disponible', 0, NULL, 1, 0, NULL, NULL, 0),
(4, 'sZ03k3V1ANQ6fSqiFbFpKQ==', '$2y$10$TgrI5/atOp6rOsDTIOvGv.KdLykEs5Ldf4ySGql7a5PbUZP3.4FWS', 'user', 'Maria', 'Garcia', 'maria@empresa.com', 'Operaciones', 'Disponible', 0, NULL, 1, 0, NULL, NULL, 0),
(5, 'oi4J9eag1SQ4cQ0WHSDCKg==', '$2y$10$KX/1QCvQiUMAGUnMiTmtAO0xb/Ks3Pa1HgEiuFk.lfYZBNswwZ34e', 'user', 'Juan', 'Lopez', 'juan@empresa.com', 'Ventas', 'Disponible', 0, NULL, 1, 0, NULL, NULL, 0);

-- --------------------------------------------------------

--
-- Table structure for table `user_sessions`
--

CREATE TABLE `user_sessions` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `token_hash` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `expires_at` datetime NOT NULL,
  `logged_out_at` datetime DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `user_sessions`
--

INSERT INTO `user_sessions` (`id`, `user_id`, `token_hash`, `ip_address`, `user_agent`, `created_at`, `expires_at`, `logged_out_at`, `is_active`) VALUES
(1, 3, '9659d65e816009880f8f70ecf61ff028db890656f9bd97dec2cfad67b4d046d6', '::1', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Mobile Safari/537.36', '2025-11-27 14:30:23', '2025-11-27 21:30:23', '2025-11-27 14:30:48', 0),
(2, 3, 'db30ef178cd87560ea75f17a482dc72614da9139201909ef3e83f9cf39ae8b8b', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-27 14:40:51', '2025-11-27 21:40:51', '2025-11-27 14:43:48', 0),
(3, 4, 'c3e972c23c4024fb1df3718480e2fb694d510b32ee3f0989087db41512842f73', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-27 15:02:19', '2025-11-27 22:02:19', '2025-11-27 15:03:23', 0),
(4, 3, 'c2b4200e9fab1c3c720eeffbb178a72169b99f564580f01ecc91a12e39aef889', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-27 15:06:31', '2025-11-27 22:06:31', '2025-11-27 15:27:31', 0),
(5, 3, 'ad9d7fcca9250f45ba30fab5ba8f90f1f0f64b62d68abe66f6b2cd4ca9b32911', '::1', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Mobile Safari/537.36', '2025-11-27 15:27:37', '2025-11-27 22:27:37', '2025-11-27 15:33:07', 0),
(6, 3, '73b43e26044fc841baf80c96255632897f07b44549a83110b8f5043ca83a4d7b', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-27 15:33:14', '2025-11-27 22:33:14', '2025-11-27 15:34:05', 0),
(7, 5, 'fec3ac5921410c59df37c7a5a0121b2df2440f2913d5ea03433e22e18ea1211a', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-27 15:34:30', '2025-11-27 22:34:30', '2025-11-27 15:34:35', 0),
(8, 3, '5e02410f5110e2b7cbdf0a5032cd7361672be298333a7948fa774cecdf9c42c1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-27 15:43:12', '2025-11-27 22:43:12', '2025-11-27 16:05:29', 0),
(9, 3, 'e672c2e775c6a839a2d0af68ce7415b14475c92e7c455444c0652393f4ec143e', '::1', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Mobile Safari/537.36', '2025-11-27 16:26:49', '2025-11-27 23:26:49', '2025-11-27 16:27:41', 0),
(10, 3, '5b7c4024f1e252bbf3e4cb874509fe136dc01cf2514e1d3eaa1e55ce145b347b', '::1', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Mobile Safari/537.36', '2025-11-27 16:30:17', '2025-11-27 23:30:17', '2025-11-27 16:30:54', 0),
(11, 4, '38496bab24a301061169a3695b699f9c8de79ea69e9b0a1f3cad28973911b215', '::1', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Mobile Safari/537.36', '2025-11-27 16:31:06', '2025-11-27 23:31:06', '2025-11-27 16:31:26', 0),
(12, 3, 'c464841fd3e0a9c0e640b54261d8166845248375be739483c5497337f177d925', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-27 17:51:01', '2025-11-28 00:51:01', '2025-11-27 17:53:03', 0),
(13, 3, '57ff7579fa3ba4743a3f59e149c2dff0c3dd3e23cbb55e36049196056418b0de', '::1', 'PostmanRuntime/7.49.1', '2025-11-28 10:38:37', '2025-11-28 17:38:37', NULL, 1),
(14, 3, 'f01bbc94440b80f429ad11893a273a1c639a3a892014b7292ee760a4a1fe843a', '::1', 'PostmanRuntime/7.49.1', '2025-11-28 10:42:26', '2025-11-28 17:42:26', NULL, 1),
(15, 3, 'eb5b2b6b6c741d54fbc3fe1cbe87cda1ab09cd161dcd9a87ef2ac878c21b888b', '::1', 'PostmanRuntime/7.49.1', '2025-11-28 10:42:35', '2025-11-28 17:42:35', NULL, 1),
(16, 3, 'ddfc4c33aead18a9cc418385e70df0534d0b93307bff411b123193e4728cdafe', '::1', 'PostmanRuntime/7.49.1', '2025-11-28 10:43:54', '2025-11-28 17:43:54', NULL, 1),
(17, 3, '9dd4c347209f45698c2cb8d322238b0b433f9cc3aa5b2493ceaa94a4ecd6e8a4', '::1', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Mobile Safari/537.36', '2025-11-28 21:59:21', '2025-11-29 04:59:21', NULL, 1),
(18, 3, 'b0afb56b1b5c41ae01405b0f0f838d2396310e1d34d1c13489041e4551d3ee36', '::1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Mobile/15E148 Safari/604.1', '2025-11-28 22:52:51', '2025-11-29 05:52:51', NULL, 1),
(19, 3, '972bc70abc69228fdd02b44dc60732250a97257e265d34917e7a8f6dc836cff0', '::1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Mobile/15E148 Safari/604.1', '2025-11-28 23:28:13', '2025-11-29 06:28:13', NULL, 1),
(20, 3, '41642aad39f8439885c10f1c2f1bba7037db663947be89f9e680666accca0729', '::1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Mobile/15E148 Safari/604.1', '2025-11-28 23:28:43', '2025-11-29 06:28:43', NULL, 1),
(21, 3, '3888acb703be8824ebac9ef01933efde3d3033561a801330e09da2920395263a', '::1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Mobile/15E148 Safari/604.1', '2025-11-28 23:43:19', '2025-11-29 06:43:19', NULL, 1),
(22, 3, '95401f07f966a10c29606ef0c2ec15e85a42761be9e0a568149fac78eb2f3703', '::1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Mobile/15E148 Safari/604.1', '2025-11-28 23:58:26', '2025-11-29 06:58:26', NULL, 1),
(23, 4, '711aac1bd09848c8a9ace018ddbca20c88ff868254d23b32ded320e71d7ca689', '::1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Mobile/15E148 Safari/604.1', '2025-11-29 00:02:15', '2025-11-29 07:02:15', NULL, 1),
(24, 3, 'eee44c18aa1dc50ff6cd0d1638c1f819f1824f4f69181a4e488f90db24b1404d', '::1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Mobile/15E148 Safari/604.1', '2025-11-29 09:42:03', '2025-11-29 16:42:03', NULL, 1),
(25, 4, '70f79bf2e8232c5c4e72b79ee7898b3598da4236b054d22305fa0533e4a018b7', '::1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Mobile/15E148 Safari/604.1', '2025-11-29 10:04:15', '2025-11-29 17:04:15', NULL, 1),
(26, 3, 'ca44d217b43c292de4b7652e412d5469f4709f4be238aa6fe11d31f2d0e9326d', '::1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Mobile/15E148 Safari/604.1', '2025-11-29 11:31:04', '2025-11-29 18:31:04', NULL, 1),
(27, 3, 'f3c9e03f1656ea4a74f5febe639771a9a9031b16cb9558cf58437a227e7c9e62', '::1', 'PostmanRuntime/7.49.1', '2025-11-29 11:43:41', '2025-11-29 18:43:41', NULL, 1),
(28, 3, '1ef188065d5640ee2dc52940f2a0917aa6b57336f557aadd71b517c488b6350b', '::1', 'PostmanRuntime/7.49.1', '2025-11-29 11:45:39', '2025-11-29 18:45:39', NULL, 1),
(29, 3, '46999233fc96721654263ca81c9e59326a67fb7048f1900e795405da5f415693', '::1', 'PostmanRuntime/7.49.1', '2025-11-29 11:48:10', '2025-11-29 18:48:10', NULL, 1),
(30, 3, '7440496abe6421414fe2a7519a95370cad6e33935259840d0f635fdc642e2cbb', '::1', 'PostmanRuntime/7.49.1', '2025-11-29 11:53:06', '2025-11-29 18:53:06', NULL, 1),
(31, 3, '66f9df62906153b6b7098c119b7e451d2e09e23ef065c96970ede558eaf69ffd', '::1', 'PostmanRuntime/7.49.1', '2025-11-29 12:13:19', '2025-11-29 19:13:19', NULL, 1),
(32, 3, 'a2e7dcbc875748f72bc19c51e96affe3df31ce36701b165c31bc8ac8db54de7e', '::1', 'PostmanRuntime/7.49.1', '2025-11-29 12:15:14', '2025-11-29 19:15:14', NULL, 1),
(33, 3, 'be645ec0a65f3c1f9ac52fe88f480e10b43d75cb322fe05369d2f50e0f758530', '::1', 'PostmanRuntime/7.49.1', '2025-11-29 12:19:52', '2025-11-29 19:19:52', NULL, 1),
(34, 3, '0aff4af86966ba07d5cdfab97088552b8546251ff9cf8d56b0b3b831185f63df', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-29 15:31:47', '2025-11-29 22:31:47', NULL, 1),
(35, 3, 'c4c2081d882dbc7f90570caf30d409d8b5b92e747cacba78776546d0c208f4ce', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-29 15:46:09', '2025-11-29 22:46:09', '2025-11-29 15:46:18', 0),
(36, 3, '1e34eed9d1cc5b271c355b10d0c6262bcb440d404827add4cc10bd97ecf6f9b1', '::1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Mobile/15E148 Safari/604.1', '2025-11-29 15:46:28', '2025-11-29 22:46:28', '2025-11-29 15:57:31', 0),
(37, 3, '64eb43e47af02f550d5def6a3bcce2ec826b555d49d3488c112613602a50a41f', '::1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Mobile/15E148 Safari/604.1', '2025-11-29 15:57:49', '2025-11-29 22:57:49', NULL, 1),
(38, 4, 'e689f0e5dea03a6c5afd31e91521406a54cc6c48cd37eb23261aa6ffcc7b4815', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-29 22:20:53', '2025-11-30 05:20:53', NULL, 1),
(39, 4, 'a90af4d3a93298d0bd8fc2bc3c7facf048890f3a3c8cf7d4dee1f36bb67d2844', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-29 22:21:28', '2025-11-30 05:21:28', NULL, 1),
(40, 4, 'c61b7caca43044cbb2a9c483e08aa0ba2c2816ce6262cd7d11ac076cfd3b939a', '::1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Mobile/15E148 Safari/604.1', '2025-11-29 22:21:40', '2025-11-30 05:21:40', NULL, 1),
(41, 3, 'fc0d064b32d0a3a23d26ea36a8fdce9ed54f7614b6b56a16b68ac572ac92423b', '::1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Mobile/15E148 Safari/604.1', '2025-11-29 22:52:17', '2025-11-30 05:52:17', '2025-11-29 22:52:40', 0),
(42, 3, 'eab6734b064d4d2a1ed6cb5e4cc4a092c6d8323f96fe68c47cfe6c73ef339e8c', '::1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Mobile/15E148 Safari/604.1', '2025-11-29 22:53:16', '2025-11-30 05:53:16', '2025-11-29 23:23:07', 0),
(43, 4, 'fd57ecffd8956502de9d392f0dda776520bb652e819e8be8377265a495a578d8', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-29 23:23:21', '2025-11-30 06:23:21', '2025-11-29 23:25:28', 0),
(44, 5, 'aa5c4f9690b3d9dcf0ee1f9c96c0e730918f5dc442c8d69103e8d977c5832dde', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-29 23:25:38', '2025-11-30 06:25:38', '2025-11-29 23:31:57', 0),
(45, 3, '491307711cb5dbe33eee62c94cb74f946d796966ca0b6012e3443f6304c8c593', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-29 23:32:02', '2025-11-30 06:32:02', '2025-11-29 23:32:40', 0),
(46, 3, '4a85a31436ca86cba41fc0bfa350b38a62b9652221233c059232cb3e3f2a211b', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-29 23:46:31', '2025-11-30 06:46:31', '2025-11-29 23:51:15', 0),
(47, 3, 'b2bb213b073b87a0d71bd7f718e05e406cf866993b57262423d3336a05c529ea', '::1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Mobile/15E148 Safari/604.1', '2025-11-29 23:52:59', '2025-11-30 06:52:59', '2025-11-29 23:58:33', 0),
(48, 4, 'cecfcbba12ec09760475b8502b02ed4db77255d7a618fe94ac638ffe67a1d3d0', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-29 23:58:45', '2025-11-30 06:58:45', '2025-11-29 23:58:58', 0),
(49, 5, '82f522aa29e63fb418f5720857a1dc04650ac389b200bfe566f3fe93b373ebae', '::1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Mobile/15E148 Safari/604.1', '2025-11-29 23:59:10', '2025-11-30 06:59:10', '2025-11-29 23:59:42', 0),
(50, 3, '09044b4cb418e86b67707e56f182775e94d54027a2e9afa49ab8849cdf336147', '::1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Mobile/15E148 Safari/604.1', '2025-11-29 23:59:50', '2025-11-30 06:59:50', '2025-11-30 00:01:06', 0),
(51, 5, 'b5723029d58eb8766a0e8bb2d113fbed826bf9364eda68c8d101cf1f137da297', '::1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Mobile/15E148 Safari/604.1', '2025-11-30 00:01:23', '2025-11-30 07:01:23', '2025-11-30 00:13:24', 0),
(52, 3, 'a2d2ac3a6f437989cee8e28c9ef04785fcb0f25985b9f1937f7ca9a376923eef', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-30 00:13:31', '2025-11-30 07:13:31', NULL, 1),
(53, 5, '051dc80bde88287001fc6436364008a749961b168ca6551960f4140c2fe102ac', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-30 00:30:48', '2025-11-30 07:30:48', NULL, 1),
(54, 3, 'dd84ee8234eec9637cabeb52dcc2c343d5d537478198d3427b8b4d5952a8abc4', '::1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Mobile/15E148 Safari/604.1', '2025-11-30 00:35:41', '2025-11-30 07:35:41', NULL, 1),
(55, 3, '3f5cf48c2d0ac1faddb7831720e846cc1ab98abd609878b8caeccaf8511a67b3', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-30 00:51:48', '2025-11-30 07:51:48', NULL, 1),
(56, 3, '0c8c300e227d3324dd82b668b4135f995fab0bb575ff843dcbfe4333b7b19b94', '::1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Mobile/15E148 Safari/604.1', '2025-11-30 01:03:32', '2025-11-30 08:03:32', NULL, 1),
(57, 3, '94f1f5512a8f5312872ba200efcc98c3a2b6098a9a4f0865d14172cf8034be14', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-30 01:05:32', '2025-11-30 08:05:32', NULL, 1),
(58, 3, '6029820059de52e222b6ba751ecb348b07a1152f2f0ac36b25b27c4b229bc808', '::1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Mobile/15E148 Safari/604.1', '2025-11-30 01:10:27', '2025-11-30 08:10:27', NULL, 1),
(59, 3, '71fbe923e59bc3177168f3eafd23002f34edb78e33cb29695a90a02279803849', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-30 01:18:56', '2025-11-30 08:18:56', NULL, 1),
(60, 3, 'f816b0c10c31ed87a6f43653cc009e8c712ad934eb36f1ca6ac226563c0f3976', '::1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Mobile/15E148 Safari/604.1', '2025-11-30 01:19:01', '2025-11-30 08:19:01', NULL, 1),
(61, 3, 'b5c519090bcd99ce30a58e18aa9948979e3bcbee724c48ed9c1a3535f9d02bbb', '::1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Mobile/15E148 Safari/604.1', '2025-11-30 01:24:32', '2025-11-30 08:24:32', NULL, 1),
(62, 3, '5fe6d71deec6987fbc2e4ca1ff9921908a8f4e5facb4e1b079631dd6345cb415', '::1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Mobile/15E148 Safari/604.1', '2025-11-30 01:43:57', '2025-11-30 08:43:57', NULL, 1),
(63, 3, '57a9b55201b7865fb811d7850a3c64771d05f33a6b5a11798ee41daff2b1e4c9', '::1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Mobile/15E148 Safari/604.1', '2025-11-30 01:45:00', '2025-11-30 08:45:00', NULL, 1),
(64, 3, '0e24391712f478fc03bcc04cfda65e55af33b42126c628e1cbb58d100514d552', '::1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Mobile/15E148 Safari/604.1', '2025-11-30 01:45:34', '2025-11-30 08:45:34', NULL, 1),
(65, 3, '0fdacf8dd61f626741ad3d67950fc0c5c1ea405223207487facb8da7236e363b', '::1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Mobile/15E148 Safari/604.1', '2025-11-30 01:47:30', '2025-11-30 08:47:30', NULL, 1),
(66, 4, '43453cb45eb1a00afeb2564d4a638ed6f9cb923703f1fe31b972d87ccc5c5e0f', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-30 02:07:51', '2025-11-30 09:07:51', NULL, 1),
(67, 3, '860c4e5be6d269bef3a4f795e8bf37a959910d2dc4011fd4c9ca9e1f2ac2872b', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-30 02:08:25', '2025-11-30 09:08:25', NULL, 1),
(68, 4, '7129b0fae17082e47f7d546e49a457144fe7638ea84c71520aa4e79684498159', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-30 02:09:06', '2025-11-30 09:09:06', NULL, 1),
(69, 5, 'fd4f15293edbc199ab058c4404222cc6d869a82f1047fbc1484ba2e539256f6f', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-30 02:09:32', '2025-11-30 09:09:32', NULL, 1),
(70, 3, '001288c5d994f20ace1942ae972d58e26f91ec48ab8425239527feaee0bd4bf9', '::1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Mobile/15E148 Safari/604.1', '2025-11-30 11:24:03', '2025-11-30 18:24:03', NULL, 1),
(71, 4, '69188b6d79034699e3d1cb29795cead0841db1ba81cd4c7e42ba7f9965968324', '::1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Mobile/15E148 Safari/604.1', '2025-11-30 11:25:10', '2025-11-30 18:25:10', NULL, 1),
(72, 3, '5ffea937befb8f8887ce1150928a722cbda52d22aaae02e409a7ec5cd5d7a035', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-30 11:25:30', '2025-11-30 18:25:30', NULL, 1),
(73, 3, '5a5224e27aa6749fe771ceb15daca357731e3d7e697fda595150965db1fc12de', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-30 11:45:32', '2025-11-30 18:45:32', NULL, 1),
(74, 4, '8ab30e77f206f5dcf246b04fd4633b7557d9052133fc4c34230ca3e7cbd6748a', '::1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Mobile/15E148 Safari/604.1', '2025-11-30 11:50:19', '2025-11-30 18:50:19', NULL, 1),
(75, 4, 'e42b0774eb325bdb795f7b24094f5c63ff01589393448c7367b59928ba62ecc3', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-30 12:01:22', '2025-11-30 19:01:22', NULL, 1),
(76, 3, '2fb8199c1a397aaf69c725879e812f722bb1b555b28a1e542ae4db0129a3a840', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-30 12:01:49', '2025-11-30 19:01:49', NULL, 1),
(77, 5, '3b5418835efc2a796b0482cbfd165c85b3d77839aa4fc77417407b6af0ccd55e', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-30 12:02:46', '2025-11-30 19:02:46', NULL, 1),
(78, 3, '35c50c28b6e95c9bae73d2336a2fe4e50c302dcfa4d40325258e6ee79be25ed1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-30 12:03:10', '2025-11-30 19:03:10', NULL, 1),
(79, 4, '15eedd0818cd846b7562f623007fb15c96c30f66b846a939a0d7200d1f8d1f69', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-30 12:03:32', '2025-11-30 19:03:32', NULL, 1),
(80, 4, 'f6216baa39c40c29fb1d5bd058d58582101fb48d956679f6844228665117a870', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-30 12:06:26', '2025-11-30 19:06:26', NULL, 1),
(81, 3, '8a538a2621a305851754a6f3510f305ff35dd11f12b33a62a630b38ddef42cd8', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-30 12:08:14', '2025-11-30 19:08:14', NULL, 1),
(82, 3, '8e223e999b12eb97f8a32abbe4ec747e7bd88dd0bccac8cad533b0fffcefba4d', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-30 12:23:01', '2025-11-30 19:23:01', NULL, 1),
(83, 4, 'ce18816b8d609f6847ea24701a227fae0c074407f2692d07601e710ca9605cee', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-30 12:24:40', '2025-11-30 19:24:40', NULL, 1),
(84, 3, 'c7a2a9ba29f499cf97028b7d87e15a3c96af31df8368eb4249be74927f0db7eb', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-30 13:01:21', '2025-11-30 20:01:21', NULL, 1),
(85, 3, 'cbd997cb4ea31a315f7e1c1affadca85928549b4f70c37320b778df7a055e4bc', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-30 13:13:17', '2025-11-30 20:13:17', NULL, 1),
(86, 3, '642c65438f79bfa15a2fa73a4c6f009e123ad6b428f0767285199add214bf0ad', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-30 13:16:07', '2025-11-30 20:16:07', NULL, 1),
(87, 3, '3e5d6de3a4d00b70c1e18d54c94c34ce2c0240a7d71a349de5b48783335063b2', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-30 13:18:14', '2025-11-30 20:18:14', NULL, 1),
(88, 3, '68c3346b9cabf00e9c526ec959494e04c2453d30de104af0dad1ea737d607718', '::1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Mobile/15E148 Safari/604.1', '2025-11-30 13:45:19', '2025-11-30 20:45:19', NULL, 1),
(89, 4, '84a1615d92872b09059f4186240a1920b63a2e9c0740f79e3ed615909dad0d41', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-30 13:49:15', '2025-11-30 20:49:14', NULL, 1),
(90, 3, '24c2f70be2bc7190b42ebf39e77d30a6037c85964ab5fc1bd720e397ffc8cdbe', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-30 13:49:38', '2025-11-30 20:49:38', NULL, 1),
(91, 3, '1aad018545a19ce84f19b7503f8632cb7693e0dd982d58d6916f301ae092709a', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-30 14:42:21', '2025-11-30 21:42:21', NULL, 1),
(92, 4, 'c01dda7279f1ccca7e694524b3436f0a9316b6791f3a47657d13f5323ec303ad', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-30 14:42:50', '2025-11-30 21:42:50', NULL, 1),
(93, 4, 'dfbc13d3c2dce05654edbecd145d9434453162fe09e53af5f9ee162cbdc10ae1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-30 14:43:02', '2025-11-30 21:43:02', NULL, 1),
(94, 4, '2100b46637183bba443c19da8ab4065b16136da329ca84c14f79de9756ca2bcd', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-30 14:45:11', '2025-11-30 21:45:11', NULL, 1),
(95, 3, '909e855a37cd0a4a60cb9bde9cfb4a06d35a0100091d9d0b1f51a8e7121d7e7b', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-30 14:45:43', '2025-11-30 21:45:43', NULL, 1),
(96, 3, '79103a5a92ff280c31e12b3aaf459249773f0d50486de33499803ecae91a887d', '::1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Mobile/15E148 Safari/604.1', '2025-11-30 14:54:46', '2025-11-30 21:54:46', NULL, 1),
(97, 3, 'cd557492170360358c761dfe4a4157899f3d28a04cc22b3ae5f6079483a57d57', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-30 15:00:14', '2025-11-30 22:00:14', NULL, 1),
(98, 3, '8c1eba2a9e5f01f4d013c136c858405f5a21ba1bba53fa8b7485ed1ad98186ce', '::1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Mobile/15E148 Safari/604.1', '2025-11-30 15:07:10', '2025-11-30 22:07:10', NULL, 1),
(99, 3, 'd284e6c9c510bf3d9555219475c778fda74acdd077b6823b133b8dc9d524a49d', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-30 15:10:57', '2025-11-30 22:10:57', NULL, 1),
(100, 5, 'b9b3943eb2677697bb8d87cfe59edba41bf301892655b07895750532acf29950', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-30 15:14:03', '2025-11-30 22:14:03', NULL, 1),
(101, 3, '09f9d666ce17a23be1039a8a7dfc1da95cd64c82c0ae28645cb139ce6da43c9b', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-30 15:17:26', '2025-11-30 22:17:26', NULL, 1),
(102, 4, 'ebbd4ddee17536b200bc858636fe7f8c62df2f42a35daead5dbdea034d9a2092', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-30 15:18:25', '2025-11-30 22:18:25', NULL, 1),
(103, 4, '4ce6014cc5b6ec3ade04b70410c208a02fd53d36b33fa3d5b04e6b4382877a40', '::1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Mobile/15E148 Safari/604.1', '2025-11-30 15:26:55', '2025-11-30 22:26:55', NULL, 1),
(104, 4, '5cac2151deb9ea53e56b9e9c0b79ba654be9c2e4f6aa7e961861bfcb2ce02f07', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-30 15:33:55', '2025-11-30 22:33:55', NULL, 1),
(105, 3, '2f52f3880b310d1384e8aad5d75ced1cfe9febbe2a98234210ffd0cb6b219479', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-30 15:41:04', '2025-11-30 22:41:04', NULL, 1),
(106, 5, '11667550286baf208c77a8a401128b6c3c02d801549b1bea2a669c6c0c66a5db', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-30 15:57:54', '2025-11-30 22:57:54', NULL, 1),
(107, 4, 'c8352cb44e306620e421d93cf9e84005c948b2a2c9754a6799f8fb34ccf3bf9d', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-30 15:58:30', '2025-11-30 22:58:30', NULL, 1),
(108, 3, 'f7dd496b23e302910aa83f9347793216d1c5284ce1e59306a14390d449575431', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-30 15:58:55', '2025-11-30 22:58:55', NULL, 1),
(109, 4, 'a601920e595ee5e7edec76c9779363413ede31c2a65b24074ef5ff141fab1e6d', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-30 15:59:17', '2025-11-30 22:59:17', NULL, 1),
(110, 3, 'c6c27ae6f5546620ced1706b5ec011950d162d24b461882b4b67aa48e20aee09', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-30 15:59:58', '2025-11-30 22:59:58', NULL, 1),
(111, 4, '72d17ed7bca9b1bed2f04324c2bf3047e3ef70e691332475b4f53f5ccfd8252a', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-30 16:00:51', '2025-11-30 23:00:51', NULL, 1),
(112, 3, '51893ccb4ea9df52a7de4d034695fc5a8c53ec114ce82a63073188acb4753ffb', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-30 16:02:54', '2025-11-30 23:02:54', NULL, 1),
(113, 5, '9637812ac3265f9bb57241fba47ab066b483abf3a757af79c9b95b2a3634fb29', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-30 16:05:13', '2025-11-30 23:05:13', NULL, 1),
(114, 3, 'a9be1da6e76dbc2b9bd0b5aa7db7fb4faf20aac4bda5e922f701a857b472ff39', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-30 16:28:26', '2025-11-30 23:28:26', NULL, 1),
(115, 3, '600969e38b254bd11ff225055cfe05e8dceb6e835e448ce73b8709fe931ee7d0', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-30 16:28:37', '2025-11-30 23:28:37', NULL, 1),
(116, 3, 'a402e724bd241819bc9c50ee08cb3a598f326cd0de0be127dc7932c9ca98760f', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-30 16:36:52', '2025-11-30 23:36:52', NULL, 1),
(117, 3, '9f3fd4b155b72ff4596378b7b86cfe1ea208940e73532ee580eb47dc0d246532', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-30 22:22:38', '2025-12-01 05:22:38', NULL, 1),
(118, 3, 'e0cc5ce9d8b1f7a3bda834f3f8e063f1a51bab0242562f2d2e93583837179360', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-30 22:22:55', '2025-12-01 05:22:55', NULL, 1),
(119, 3, 'd1a9d8efc72d101e0870a96e3bc5728f9076e050a719feaa76d2e469a48ab062', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-30 22:42:23', '2025-11-30 23:42:23', NULL, 1),
(120, 3, '22de133ac9e5ab8ec91fa7ca325472a04e2ace784a2587cc8402cd4a988652a6', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-01 09:36:16', '2025-12-01 10:36:16', NULL, 1),
(121, 4, 'd0fc14e00fb0f0cb88cb65048773863dca102f6c099269aa9573cc922cafdbc1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-01 09:36:59', '2025-12-01 10:36:59', NULL, 1),
(122, 3, '528edb11a3881126fc4620177e8d34af15a1b1c0e55e6310a06f7163b05be0f7', '::1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Mobile/15E148 Safari/604.1', '2025-12-01 09:45:51', '2025-12-01 10:45:51', NULL, 1),
(123, 3, '68709a8a0966451265e9e8b7a6ffab92b80fb857d37e797d5701958ed86c6544', '::1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Mobile/15E148 Safari/604.1', '2025-12-01 09:46:08', '2025-12-01 10:46:08', NULL, 1),
(124, 3, '615afc04a44dfca7b47808b54776769587ef2587a333d3f84583d6c35db9f2e7', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-01 09:48:04', '2025-12-01 10:48:04', NULL, 1),
(125, 4, '6cc928d8f9356b7245041a7919fc78a943a116929cb4341b4b967c1e4c77e97e', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-01 09:51:22', '2025-12-01 10:51:22', NULL, 1),
(126, 3, '28ce8f2a375df49214f2d756b0df4fa012cd930a024d872b6cd8d3b20aecaef7', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-01 09:53:11', '2025-12-01 10:53:11', NULL, 1),
(127, 5, '6b56737b044f6692a55d2e05d7a12775451c352566522f2f6d7ad905df99df49', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-01 09:55:15', '2025-12-01 10:55:15', NULL, 1),
(128, 3, '8928c62f4ecb048e742a4f085070d952b51150cda07efa7bdb470a0814e9cdbe', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-02 11:51:29', '2025-12-02 12:51:29', NULL, 1),
(129, 4, 'd083249eb09e872596995cbca73fb3540dc8bde94be0649188710a673eb28b33', '::1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Mobile/15E148 Safari/604.1', '2025-12-02 11:53:45', '2025-12-02 12:53:45', NULL, 1),
(130, 3, 'bad3fea5bd095a5b73cbd2c263455cedd715c0c1d6634a849cf6666f61745dbf', '::1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Mobile/15E148 Safari/604.1', '2025-12-02 11:57:59', '2025-12-02 12:57:59', NULL, 1),
(131, 3, '4c1c9872c710e062e101ba9bb48bc35c0913f64e45305565c5c517dfe83c37fa', '::1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Mobile/15E148 Safari/604.1', '2025-12-02 12:20:46', '2025-12-02 13:20:46', NULL, 1);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `categorias`
--
ALTER TABLE `categorias`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nombre` (`nombre`);

--
-- Indexes for table `evidencia_imagenes`
--
ALTER TABLE `evidencia_imagenes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_imagenes_evidencia` (`evidencia_id`);

--
-- Indexes for table `subtareas`
--
ALTER TABLE `subtareas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `estado` (`estado`),
  ADD KEY `prioridad` (`prioridad`),
  ADD KEY `usuarioasignado_id` (`usuarioasignado_id`),
  ADD KEY `idx_subtarea_task` (`task_id`),
  ADD KEY `fk_subtarea_categoria` (`categoria_id`);

--
-- Indexes for table `sucursales`
--
ALTER TABLE `sucursales`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `tasks`
--
ALTER TABLE `tasks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_task_creator` (`created_by_user_id`),
  ADD KEY `idx_task_status_priority` (`status`,`priority`),
  ADD KEY `idx_task_deadline` (`deadline`),
  ADD KEY `idx_task_assigned` (`assigned_user_id`),
  ADD KEY `idx_task_categoria` (`categoria_id`),
  ADD KEY `idx_task_sucursal` (`sucursal_id`);

--
-- Indexes for table `task_evidencias`
--
ALTER TABLE `task_evidencias`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_task_evidencias_task` (`task_id`),
  ADD KEY `idx_task_evidencias_fecha` (`created_at`);

--
-- Indexes for table `task_reaperturas`
--
ALTER TABLE `task_reaperturas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_task_reaperturas_task` (`task_id`),
  ADD KEY `idx_task_reaperturas_fecha` (`reopened_at`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `user_sessions`
--
ALTER TABLE `user_sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_sessions_user` (`user_id`),
  ADD KEY `idx_user_sessions_token` (`token_hash`),
  ADD KEY `idx_user_sessions_active` (`is_active`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `categorias`
--
ALTER TABLE `categorias`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `evidencia_imagenes`
--
ALTER TABLE `evidencia_imagenes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `subtareas`
--
ALTER TABLE `subtareas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `sucursales`
--
ALTER TABLE `sucursales`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `tasks`
--
ALTER TABLE `tasks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `task_evidencias`
--
ALTER TABLE `task_evidencias`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `task_reaperturas`
--
ALTER TABLE `task_reaperturas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `user_sessions`
--
ALTER TABLE `user_sessions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=132;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `evidencia_imagenes`
--
ALTER TABLE `evidencia_imagenes`
  ADD CONSTRAINT `fk_imagenes_evidencia` FOREIGN KEY (`evidencia_id`) REFERENCES `task_evidencias` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `subtareas`
--
ALTER TABLE `subtareas`
  ADD CONSTRAINT `fk_subtarea_categoria` FOREIGN KEY (`categoria_id`) REFERENCES `categorias` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_subtarea_task` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_subtarea_usuario` FOREIGN KEY (`usuarioasignado_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `subtareas_ibfk_3` FOREIGN KEY (`categoria_id`) REFERENCES `categorias` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `tasks`
--
ALTER TABLE `tasks`
  ADD CONSTRAINT `fk_task_assigned_user` FOREIGN KEY (`assigned_user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_task_categoria` FOREIGN KEY (`categoria_id`) REFERENCES `categorias` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_task_creator` FOREIGN KEY (`created_by_user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_task_sucursal` FOREIGN KEY (`sucursal_id`) REFERENCES `sucursales` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `task_evidencias`
--
ALTER TABLE `task_evidencias`
  ADD CONSTRAINT `fk_task_evidencias_task` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `task_reaperturas`
--
ALTER TABLE `task_reaperturas`
  ADD CONSTRAINT `fk_task_reaperturas_task` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_sessions`
--
ALTER TABLE `user_sessions`
  ADD CONSTRAINT `user_sessions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
