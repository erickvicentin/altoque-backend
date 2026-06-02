# AlToque - Backend API 🚀

Este es el backend desarrollado en **Laravel** para el sistema de turnos multirubro **AlToque** (Programación III). Maneja la autenticación mediante tokens con Laravel Sanctum y la persistencia de datos en MySQL.

## 🛠️ Requisitos previos

Antes de empezar, asegúrate de tener instalado:

- **XAMPP** (con los módulos Apache y MySQL activos).
- **Composer** (manejador de dependencias de PHP).
- **PHP 8.2+** (instalado por defecto con las versiones actuales de XAMPP).

---

## 🚀 Pasos para levantar el proyecto localmente (Para el Equipo)

Si acabas de clonar el repositorio por primera vez, sigue estos pasos en la terminal de tu computadora:

### 1. Instalar las dependencias de PHP

Descarga todos los paquetes necesarios del framework ejecutando:

```bash
composer install
```
## 2. Configurar las Variables de Entorno
El archivo ```.env``` original no se sube al repositorio por seguridad. Debes crear una copia local basada en la plantilla:

```bash
cp .env.example .env
```

Ahora, abre el archivo ```.env``` recién creado en tu editor y configura el bloque de la base de datos con tus credenciales de XAMPP:

```bash
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=altoque_db
DB_USERNAME=root
DB_PASSWORD=
```

(Nota: Recuerda entrar a tu phpMyAdmin y crear una base de datos vacía llamada altoque_db antes de continuar.).

## 3. Generar la Clave de la Aplicación
Genera el token de seguridad único para tu entorno local:

```bash
php artisan key:generate
```

### 4. Instalar y configurar la API (Sanctum)
Este comando instala el sistema de tokens para que la app de React Native y Postman puedan autenticarse:

```bash
php artisan install:api
```

### 5. Ejecutar las Migraciones
Crea la estructura unificada de las tablas de usuarios, perfiles y direcciones en tu MySQL ejecutando:

```bash
php artisan migrate
```

### 6. Levantar el Servidor Local
Para que la aplicación móvil (tanto en emuladores como en dispositivos físicos) pueda conectarse al backend, es obligatorio levantar Laravel exponiendo el host:
```bash
php artisan serve --host=0.0.0.0
```
El servidor se iniciará en: http://0.0.0.0:8000 (accesible externamente mediante tu IP local).

# 📬 Endpoints Disponibles (Pruebas iniciales en Postman)
* Registro de Usuarios: ```POST /api/register``` (Soporta flujos independientes para roles ```client``` y ```professional```).
* Inicio de Sesión: ```POST /api/login``` (Retorna el ```access_token``` necesario para peticiones protegidas).

Asegúrate de agregar el Header ```Accept: application/json``` en todas tus peticiones en Postman.
