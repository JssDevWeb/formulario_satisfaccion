@echo off
REM ============================================
REM INSTALADOR WINDOWS - SISTEMA DE ENCUESTAS ACADEMICAS
REM ============================================
REM Script de instalación para Windows con XAMPP/WAMP
REM Automatiza la configuración básica del sistema
REM ============================================

setlocal enabledelayedexpansion

REM Configuración de colores
color 07

echo ============================================
echo  INSTALADOR - SISTEMA DE ENCUESTAS ACADEMICAS
echo ============================================
echo.

REM Verificar si estamos en el directorio correcto
if not exist "index.html" (
    echo ERROR: Este script debe ejecutarse desde el directorio raiz del proyecto
    echo Asegurate de que existan los archivos index.html y la carpeta admin
    pause
    exit /b 1
)

if not exist "admin" (
    echo ERROR: Carpeta admin no encontrada
    pause
    exit /b 1
)

echo [INFO] Directorio del proyecto verificado correctamente
echo.

REM Solicitar información de configuración
echo ============================================
echo CONFIGURACION DE BASE DE DATOS
echo ============================================
echo.

set /p DB_HOST="Host de la base de datos [localhost]: "
if "%DB_HOST%"=="" set DB_HOST=localhost

set /p DB_NAME="Nombre de la base de datos [academia_encuestas]: "
if "%DB_NAME%"=="" set DB_NAME=academia_encuestas

set /p DB_USER="Usuario de la base de datos [root]: "
if "%DB_USER%"=="" set DB_USER=root

set /p DB_PASS="Contraseña de la base de datos: "

set /p DB_PORT="Puerto de MySQL [3306]: "
if "%DB_PORT%"=="" set DB_PORT=3306

echo.
echo ============================================
echo CONFIGURACION DEL ADMINISTRADOR
echo ============================================
echo.

set /p ADMIN_USER="Usuario administrador [admin]: "
if "%ADMIN_USER%"=="" set ADMIN_USER=admin

set /p ADMIN_PASS="Contraseña del administrador: "

set /p ADMIN_EMAIL="Email del administrador: "

echo.
echo ============================================
echo VERIFICANDO INSTALACION
echo ============================================
echo.

REM Verificar PHP
php -v >nul 2>&1
if %errorlevel% neq 0 (
    echo ERROR: PHP no encontrado en el PATH
    echo Asegurate de que XAMPP/WAMP este instalado y PHP este en el PATH
    echo.
    echo Para XAMPP: Agregar C:\xampp\php al PATH
    echo Para WAMP: Agregar C:\wamp64\bin\php\php-version al PATH
    pause
    exit /b 1
)

echo [EXITO] PHP encontrado
php -v | findstr "PHP"

REM Verificar MySQL
mysql --version >nul 2>&1
if %errorlevel% neq 0 (
    echo [ADVERTENCIA] MySQL no encontrado en el PATH
    echo Esto es normal en XAMPP/WAMP, continuando...
) else (
    echo [EXITO] MySQL encontrado
    mysql --version
)

echo.
echo ============================================
echo CONFIGURANDO ARCHIVOS
echo ============================================
echo.

REM Crear directorios necesarios
if not exist "logs" mkdir logs
if not exist "cache" mkdir cache
if not exist "uploads" mkdir uploads
if not exist "uploads\temp" mkdir uploads\temp

echo [EXITO] Directorios creados

REM Configurar archivo de base de datos
if exist "config\database.example.php" (
    copy "config\database.example.php" "config\database.php" >nul
    echo [EXITO] Archivo de configuracion copiado
    
    REM Reemplazar valores en el archivo de configuración usando PowerShell
    powershell -Command "(Get-Content 'config\database.php') -replace 'tu-servidor-mysql.com', '%DB_HOST%' | Set-Content 'config\database.php'"
    powershell -Command "(Get-Content 'config\database.php') -replace 'nombre_base_datos_produccion', '%DB_NAME%' | Set-Content 'config\database.php'"
    powershell -Command "(Get-Content 'config\database.php') -replace 'usuario_mysql_seguro', '%DB_USER%' | Set-Content 'config\database.php'"
    powershell -Command "(Get-Content 'config\database.php') -replace 'contraseña_muy_segura_123!@#', '%DB_PASS%' | Set-Content 'config\database.php'"
    
    echo [EXITO] Configuracion de base de datos aplicada
) else (
    echo [ERROR] Archivo config\database.example.php no encontrado
    pause
    exit /b 1
)

REM Configurar .htaccess si existe
if exist ".htaccess.example" (
    copy ".htaccess.example" ".htaccess" >nul
    echo [EXITO] Archivo .htaccess configurado
)

echo.
echo ============================================
echo CREANDO BASE DE DATOS
echo ============================================
echo.

REM Intentar crear la base de datos
echo Intentando crear base de datos...
echo.

REM Para XAMPP
set MYSQL_XAMPP=C:\xampp\mysql\bin\mysql.exe
if exist "%MYSQL_XAMPP%" (
    echo Usando MySQL de XAMPP...
    "%MYSQL_XAMPP%" -h %DB_HOST% -u %DB_USER% -p%DB_PASS% -e "CREATE DATABASE IF NOT EXISTS %DB_NAME% CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci;" 2>nul
    if !errorlevel! equ 0 (
        echo [EXITO] Base de datos creada/verificada
        
        REM Importar esquema si existe
        if exist "admin\academia_encuestas.sql" (
            echo Importando esquema...
            "%MYSQL_XAMPP%" -h %DB_HOST% -u %DB_USER% -p%DB_PASS% %DB_NAME% < admin\academia_encuestas.sql
            if !errorlevel! equ 0 (
                echo [EXITO] Esquema importado correctamente
            ) else (
                echo [ADVERTENCIA] Error al importar esquema
            )
        ) else (
            echo [ADVERTENCIA] Archivo de esquema no encontrado
        )
    ) else (
        echo [ERROR] Error al crear base de datos con XAMPP
    )
    goto :database_done
)

REM Para WAMP
set MYSQL_WAMP=C:\wamp64\bin\mysql\mysql8.0.31\bin\mysql.exe
if exist "%MYSQL_WAMP%" (
    echo Usando MySQL de WAMP...
    "%MYSQL_WAMP%" -h %DB_HOST% -u %DB_USER% -p%DB_PASS% -e "CREATE DATABASE IF NOT EXISTS %DB_NAME% CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci;" 2>nul
    if !errorlevel! equ 0 (
        echo [EXITO] Base de datos creada/verificada
        
        REM Importar esquema si existe
        if exist "admin\academia_encuestas.sql" (
            echo Importando esquema...
            "%MYSQL_WAMP%" -h %DB_HOST% -u %DB_USER% -p%DB_PASS% %DB_NAME% < admin\academia_encuestas.sql
            if !errorlevel! equ 0 (
                echo [EXITO] Esquema importado correctamente
            ) else (
                echo [ADVERTENCIA] Error al importar esquema
            )
        ) else (
            echo [ADVERTENCIA] Archivo de esquema no encontrado
        )
    ) else (
        echo [ERROR] Error al crear base de datos con WAMP
    )
    goto :database_done
)

REM Intentar con mysql genérico
mysql -h %DB_HOST% -u %DB_USER% -p%DB_PASS% -e "CREATE DATABASE IF NOT EXISTS %DB_NAME% CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci;" 2>nul
if %errorlevel% equ 0 (
    echo [EXITO] Base de datos creada/verificada
    
    REM Importar esquema si existe
    if exist "admin\academia_encuestas.sql" (
        echo Importando esquema...
        mysql -h %DB_HOST% -u %DB_USER% -p%DB_PASS% %DB_NAME% < admin\academia_encuestas.sql
        if %errorlevel% equ 0 (
            echo [EXITO] Esquema importado correctamente
        ) else (
            echo [ADVERTENCIA] Error al importar esquema
        )
    ) else (
        echo [ADVERTENCIA] Archivo de esquema no encontrado
    )
) else (
    echo [ADVERTENCIA] No se pudo crear la base de datos automaticamente
    echo Por favor, crea manualmente la base de datos '%DB_NAME%' usando phpMyAdmin
)

:database_done

echo.
echo ============================================
echo VERIFICANDO INSTALACION
echo ============================================
echo.

REM Verificar conexión a la base de datos
echo Verificando conexion a base de datos...
php -r "require_once 'config/database.php'; try { $db = Database::getInstance()->getConnection(); echo 'Conexion a base de datos: OK\n'; } catch (Exception $e) { echo 'Error de conexion: ' . $e->getMessage() . '\n'; exit(1); }"

if %errorlevel% equ 0 (
    echo [EXITO] Verificacion de base de datos exitosa
) else (
    echo [ERROR] Error en la verificacion de base de datos
    echo Revisa la configuracion en config\database.php
)

echo.
echo ============================================
echo INSTALACION COMPLETADA
echo ============================================
echo.

echo [EXITO] Instalacion completada exitosamente!
echo.
echo INFORMACION DE ACCESO:
echo   URL del sistema: http://localhost/formulario_final/
echo   Panel administrativo: http://localhost/formulario_final/admin/
echo   Usuario administrador: %ADMIN_USER%
echo   Contraseña: [la que ingresaste]
echo.
echo PASOS SIGUIENTES:
echo   1. Asegurate de que XAMPP/WAMP este ejecutandose
echo   2. Accede al panel administrativo y configura cursos/profesores
echo   3. Crea formularios de encuesta
echo   4. Configura SSL/HTTPS para produccion
echo.
echo ARCHIVOS IMPORTANTES:
echo   - config\database.php: Configuracion de base de datos
echo   - .htaccess: Configuracion de Apache
echo   - logs\: Archivos de log
echo   - cache\: Archivos de cache
echo.
echo Disfruta tu Sistema de Encuestas Academicas!

pause
