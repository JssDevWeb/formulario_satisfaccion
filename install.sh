#!/bin/bash

# ============================================
# INSTALADOR AUTOMÁTICO - SISTEMA DE ENCUESTAS ACADÉMICAS
# ============================================
# Script de instalación para sistemas Linux/Unix
# Automatiza la configuración del sistema en servidores
# ============================================

set -e  # Salir si cualquier comando falla

# Colores para output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Función para imprimir mensajes
print_status() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

print_success() {
    echo -e "${GREEN}[ÉXITO]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[ADVERTENCIA]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

print_header() {
    echo -e "${BLUE}============================================${NC}"
    echo -e "${BLUE} INSTALADOR - SISTEMA DE ENCUESTAS ACADÉMICAS${NC}"
    echo -e "${BLUE}============================================${NC}"
    echo ""
}

# Función para verificar si un comando existe
command_exists() {
    command -v "$1" >/dev/null 2>&1
}

# Función para verificar requisitos del sistema
check_requirements() {
    print_status "Verificando requisitos del sistema..."
    
    local php_version=""
    local mysql_version=""
    local missing_deps=()
    
    # Verificar PHP
    if command_exists php; then
        php_version=$(php -v | head -n 1 | cut -d " " -f 2 | cut -d "." -f 1,2)
        if [[ $(echo "$php_version >= 7.4" | bc -l) -eq 1 ]]; then
            print_success "PHP $php_version encontrado"
        else
            print_error "PHP $php_version encontrado, pero se requiere 7.4 o superior"
            missing_deps+=("php>=7.4")
        fi
    else
        print_error "PHP no encontrado"
        missing_deps+=("php")
    fi
    
    # Verificar MySQL/MariaDB
    if command_exists mysql; then
        mysql_version=$(mysql --version | cut -d " " -f 6 | cut -d "." -f 1,2)
        print_success "MySQL $mysql_version encontrado"
    elif command_exists mariadb; then
        mysql_version=$(mariadb --version | cut -d " " -f 6 | cut -d "." -f 1,2)
        print_success "MariaDB $mysql_version encontrado"
    else
        print_error "MySQL/MariaDB no encontrado"
        missing_deps+=("mysql-server")
    fi
    
    # Verificar Apache/Nginx
    if command_exists apache2 || command_exists httpd; then
        print_success "Apache encontrado"
    elif command_exists nginx; then
        print_success "Nginx encontrado"
    else
        print_warning "No se encontró servidor web (Apache/Nginx)"
    fi
    
    # Verificar extensiones PHP
    print_status "Verificando extensiones PHP..."
    local php_extensions=("pdo" "pdo_mysql" "json" "mbstring" "session")
    
    for ext in "${php_extensions[@]}"; do
        if php -m | grep -q "^$ext$"; then
            print_success "Extensión PHP $ext encontrada"
        else
            print_error "Extensión PHP $ext no encontrada"
            missing_deps+=("php-$ext")
        fi
    done
    
    # Verificar Git
    if command_exists git; then
        print_success "Git encontrado"
    else
        print_warning "Git no encontrado (opcional)"
    fi
    
    if [ ${#missing_deps[@]} -gt 0 ]; then
        print_error "Faltan las siguientes dependencias:"
        for dep in "${missing_deps[@]}"; do
            echo "  - $dep"
        done
        echo ""
        print_error "Por favor, instala las dependencias faltantes antes de continuar."
        exit 1
    fi
    
    print_success "Todos los requisitos están satisfechos"
}

# Función para solicitar información de configuración
get_config() {
    print_status "Configuración de la base de datos..."
    
    # Solicitar información de la base de datos
    read -p "Host de la base de datos [localhost]: " DB_HOST
    DB_HOST=${DB_HOST:-localhost}
    
    read -p "Nombre de la base de datos [academia_encuestas]: " DB_NAME
    DB_NAME=${DB_NAME:-academia_encuestas}
    
    read -p "Usuario de la base de datos [root]: " DB_USER
    DB_USER=${DB_USER:-root}
    
    read -sp "Contraseña de la base de datos: " DB_PASS
    echo ""
    
    read -p "Puerto de MySQL [3306]: " DB_PORT
    DB_PORT=${DB_PORT:-3306}
    
    # Solicitar información del administrador
    print_status "Configuración del administrador..."
    
    read -p "Usuario administrador [admin]: " ADMIN_USER
    ADMIN_USER=${ADMIN_USER:-admin}
    
    read -sp "Contraseña del administrador: " ADMIN_PASS
    echo ""
    
    read -p "Email del administrador: " ADMIN_EMAIL
}

# Función para crear la base de datos
create_database() {
    print_status "Creando base de datos..."
    
    # Crear base de datos si no existe
    mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" -e "CREATE DATABASE IF NOT EXISTS $DB_NAME CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci;" 2>/dev/null
    
    if [ $? -eq 0 ]; then
        print_success "Base de datos '$DB_NAME' creada/verificada"
    else
        print_error "Error al crear la base de datos"
        exit 1
    fi
    
    # Importar esquema si existe
    if [ -f "admin/academia_encuestas.sql" ]; then
        print_status "Importando esquema de base de datos..."
        mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" < admin/academia_encuestas.sql
        
        if [ $? -eq 0 ]; then
            print_success "Esquema importado correctamente"
        else
            print_error "Error al importar el esquema"
            exit 1
        fi
    else
        print_warning "Archivo de esquema no encontrado (admin/academia_encuestas.sql)"
    fi
}

# Función para configurar archivos
setup_config() {
    print_status "Configurando archivos de configuración..."
    
    # Crear archivo de configuración de base de datos
    if [ -f "config/database.example.php" ]; then
        cp config/database.example.php config/database.php
        
        # Reemplazar valores en el archivo de configuración
        sed -i "s/tu-servidor-mysql.com/$DB_HOST/g" config/database.php
        sed -i "s/nombre_base_datos_produccion/$DB_NAME/g" config/database.php
        sed -i "s/usuario_mysql_seguro/$DB_USER/g" config/database.php
        sed -i "s/contraseña_muy_segura_123!@#/$DB_PASS/g" config/database.php
        sed -i "s/3306/$DB_PORT/g" config/database.php
        
        print_success "Archivo de configuración creado"
    else
        print_error "Archivo de ejemplo de configuración no encontrado"
        exit 1
    fi
    
    # Configurar .htaccess si existe el ejemplo
    if [ -f ".htaccess.example" ]; then
        cp .htaccess.example .htaccess
        print_success "Archivo .htaccess configurado"
    fi
}

# Función para configurar permisos
setup_permissions() {
    print_status "Configurando permisos de archivos..."
    
    # Crear directorios necesarios
    mkdir -p logs cache uploads/temp
    
    # Configurar permisos
    chmod 755 -R ./
    chmod 777 logs/ cache/ uploads/temp/
    chmod 600 config/database.php
    
    # Configurar propietario (si se ejecuta como root)
    if [ "$EUID" -eq 0 ]; then
        local web_user="www-data"
        if id "$web_user" &>/dev/null; then
            chown -R "$web_user:$web_user" ./
            print_success "Propietario configurado para $web_user"
        else
            print_warning "Usuario web no encontrado, omitiendo configuración de propietario"
        fi
    fi
    
    print_success "Permisos configurados"
}

# Función para crear usuario administrador
create_admin_user() {
    print_status "Creando usuario administrador..."
    
    # Crear hash de contraseña
    local password_hash=$(php -r "echo password_hash('$ADMIN_PASS', PASSWORD_DEFAULT);")
    
    # Insertar usuario en la base de datos
    mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" -e "
        INSERT INTO administradores (usuario, password, email, nombre, activo, fecha_creacion) 
        VALUES ('$ADMIN_USER', '$password_hash', '$ADMIN_EMAIL', 'Administrador', 1, NOW())
        ON DUPLICATE KEY UPDATE 
        password='$password_hash', email='$ADMIN_EMAIL', activo=1;
    " 2>/dev/null
    
    if [ $? -eq 0 ]; then
        print_success "Usuario administrador creado/actualizado"
    else
        print_warning "No se pudo crear el usuario administrador (puede que la tabla no exista)"
    fi
}

# Función para verificar la instalación
verify_installation() {
    print_status "Verificando instalación..."
    
    # Verificar conexión a la base de datos
    php -r "
        require_once 'config/database.php';
        try {
            \$db = Database::getInstance()->getConnection();
            echo 'Conexión a base de datos: OK\n';
        } catch (Exception \$e) {
            echo 'Error de conexión: ' . \$e->getMessage() . '\n';
            exit(1);
        }
    "
    
    if [ $? -eq 0 ]; then
        print_success "Verificación de base de datos exitosa"
    else
        print_error "Error en la verificación de base de datos"
        exit 1
    fi
    
    # Verificar permisos de escritura
    if [ -w "logs/" ] && [ -w "cache/" ]; then
        print_success "Permisos de escritura verificados"
    else
        print_error "Error en permisos de escritura"
        exit 1
    fi
}

# Función para mostrar información final
show_final_info() {
    print_success "¡Instalación completada exitosamente!"
    echo ""
    echo -e "${GREEN}Información de acceso:${NC}"
    echo "  URL del sistema: http://tu-dominio.com/"
    echo "  Panel administrativo: http://tu-dominio.com/admin/"
    echo "  Usuario administrador: $ADMIN_USER"
    echo "  Contraseña: [la que ingresaste]"
    echo ""
    echo -e "${YELLOW}Pasos siguientes:${NC}"
    echo "  1. Configurar tu servidor web para servir el directorio actual"
    echo "  2. Acceder al panel administrativo y configurar cursos/profesores"
    echo "  3. Crear formularios de encuesta"
    echo "  4. Configurar SSL/HTTPS para producción"
    echo "  5. Configurar backups automáticos"
    echo ""
    echo -e "${BLUE}Archivos importantes:${NC}"
    echo "  - config/database.php: Configuración de base de datos"
    echo "  - .htaccess: Configuración de Apache"
    echo "  - logs/: Archivos de log"
    echo "  - cache/: Archivos de cache"
    echo ""
    echo -e "${GREEN}¡Disfruta tu Sistema de Encuestas Académicas!${NC}"
}

# Función principal
main() {
    print_header
    
    # Verificar si se ejecuta como root (recomendado para permisos)
    if [ "$EUID" -ne 0 ]; then
        print_warning "No se está ejecutando como root. Algunos pasos pueden requerir sudo."
    fi
    
    # Verificar si estamos en el directorio correcto
    if [ ! -f "index.html" ] || [ ! -d "admin" ]; then
        print_error "Este script debe ejecutarse desde el directorio raíz del proyecto"
        exit 1
    fi
    
    # Ejecutar pasos de instalación
    check_requirements
    get_config
    create_database
    setup_config
    setup_permissions
    create_admin_user
    verify_installation
    show_final_info
}

# Manejar interrupciones
trap 'print_error "Instalación interrumpida"; exit 1' INT TERM

# Ejecutar función principal
main "$@"
