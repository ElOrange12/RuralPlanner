-- 1. Creamos la base de datos y la seleccionamos
CREATE DATABASE IF NOT EXISTS rural_planner;
USE rural_planner;

-- ==========================================
-- TABLA DE USUARIOS (Login y Roles)
-- ==========================================
CREATE TABLE usuarios (
    id_usuario INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL, -- Aquí deberás guardar la contraseña hasheada (ej. con password_hash de PHP)
    rol ENUM('admin', 'user') DEFAULT 'user',
    fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ==========================================
-- SECCIÓN CASAS
-- ==========================================
CREATE TABLE casas (
    id_casa INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    precio DECIMAL(10, 2) NOT NULL,
    url_web VARCHAR(255),
    url_imagen TEXT, -- TEXT por si la URL en base64 o de Unsplash es muy larga
    id_creador INT, -- Para saber quién propuso la casa
    FOREIGN KEY (id_creador) REFERENCES usuarios(id_usuario) ON DELETE SET NULL
);

-- Tabla intermedia para los Likes (Relación N:M entre Usuarios y Casas)
CREATE TABLE votos_casas (
    id_usuario INT,
    id_casa INT,
    PRIMARY KEY (id_usuario, id_casa), -- Evita que un usuario vote dos veces la misma casa
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario) ON DELETE CASCADE,
    FOREIGN KEY (id_casa) REFERENCES casas(id_casa) ON DELETE CASCADE
);

-- ==========================================
-- SECCIÓN ACTIVIDADES
-- ==========================================
CREATE TABLE actividades (
    id_actividad INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    categoria ENUM('aventura', 'agua', 'juegos', 'comida', 'fiesta') NOT NULL,
    precio DECIMAL(10, 2) DEFAULT 0.00,
    descripcion TEXT,
    url_web VARCHAR(255),
    url_imagen TEXT,
    id_creador INT,
    FOREIGN KEY (id_creador) REFERENCES usuarios(id_usuario) ON DELETE SET NULL
);

-- Tabla intermedia para apuntarse a los planes
CREATE TABLE votos_actividades (
    id_usuario INT,
    id_actividad INT,
    PRIMARY KEY (id_usuario, id_actividad),
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario) ON DELETE CASCADE,
    FOREIGN KEY (id_actividad) REFERENCES actividades(id_actividad) ON DELETE CASCADE
);

-- ==========================================
-- SECCIÓN LISTA DE LA COMPRA
-- ==========================================
CREATE TABLE lista_compra (
    id_item INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    precio_estimado DECIMAL(10, 2) NOT NULL,
    comprado BOOLEAN DEFAULT FALSE,
    url_imagen TEXT,
    id_pagador INT, -- Si es NULL, entendemos que es "Fondo Común"
    FOREIGN KEY (id_pagador) REFERENCES usuarios(id_usuario) ON DELETE SET NULL
);

-- ==========================================
-- SECCIÓN TRANSPORTE (Configuración general)
-- ==========================================
CREATE TABLE transporte (
    id_config INT PRIMARY KEY DEFAULT 1, -- Solo habrá una fila con la configuración final
    tipo ENUM('coche', 'tren', 'avion') NOT NULL,
    coste_total DECIMAL(10, 2) NOT NULL,
    url_imagen TEXT
);

-- ===================
-- CREACIÓN DE USUARIO
-- ===================

-- 1. Creamos el usuario con su contraseña
CREATE USER 'AdminPlanner'@'localhost' IDENTIFIED BY 'PlannerRural2026$';

-- 2. Le damos permiso para conectarse
GRANT USAGE ON *.* TO 'AdminPlanner'@'localhost';

-- 3. Le quitamos los límites de consultas (opcional pero recomendado para desarrollo)
ALTER USER 'AdminPlanner'@'localhost' 
REQUIRE NONE 
WITH MAX_QUERIES_PER_HOUR 0 
MAX_CONNECTIONS_PER_HOUR 0 
MAX_UPDATES_PER_HOUR 0 
MAX_USER_CONNECTIONS 0;

-- 4. Le damos TODO el poder, pero SOLO sobre la base de datos 'rural_planner'
GRANT ALL PRIVILEGES ON rural_planner.* TO 'AdminPlanner'@'localhost';

-- 5. Recargamos los privilegios para que los cambios surtan efecto inmediato
FLUSH PRIVILEGES;

-- Cambios funcionales a la base de datos

ALTER TABLE casas MODIFY url_web TEXT;

ALTER TABLE casas MODIFY url_imagen LONGTEXT;

ALTER TABLE actividades MODIFY url_imagen LONGTEXT;

ALTER TABLE actividades 
MODIFY url_web TEXT,
MODIFY url_imagen LONGTEXT;

ALTER TABLE actividades ADD COLUMN duracion DECIMAL(4,2) DEFAULT 0;

ALTER TABLE actividades DROP COLUMN duracion;
ALTER TABLE actividades ADD COLUMN hora_inicio TIME, ADD COLUMN hora_fin TIME;

ALTER TABLE actividades DROP COLUMN duracion;
ALTER TABLE actividades 
ADD COLUMN hora_inicio TIME, 
ADD COLUMN hora_finalizacion TIME;

DROP TABLE IF EXISTS transporte;
CREATE TABLE transporte (
    id_trayecto INT AUTO_INCREMENT PRIMARY KEY,
    tipo ENUM('coche', 'tren', 'avion') NOT NULL,
    ruta VARCHAR(255) NOT NULL,
    coste_total DECIMAL(10, 2) NOT NULL
);
