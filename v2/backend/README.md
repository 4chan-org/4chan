# 4chan Backend API

## Descripción

API backend moderna para 4chan, construida con NestJS, TypeScript, y Prisma ORM para proporcionar una base sólida, segura y escalable.

## Características

- Arquitectura modular basada en NestJS
- ORM tipado con Prisma
- Autenticación JWT con refreshing tokens
- Validación y sanitización de entrada
- Documentación automática con Swagger
- Logs estructurados
- Tests unitarios y de integración

## Tecnologías Utilizadas

- NestJS
- TypeScript
- Prisma ORM
- PostgreSQL
- JWT
- Winston (logging)
- Jest (testing)
- Swagger/OpenAPI

## Instalación

```bash
# Instalar dependencias
npm install

# Configurar variables de entorno
cp .env.example .env

# Generar cliente Prisma
npm run prisma:generate

# Aplicar migraciones
npm run prisma:migrate
```

## Desarrollo

```bash
# Iniciar en modo desarrollo
npm run dev

# Ejecutar tests
npm run test

# Lint y formateo de código
npm run lint
npm run format
```

## Construcción

```bash
# Construir para producción
npm run build

# Iniciar en modo producción
npm run start:prod
```

## Docker

```bash
# Construir imagen
docker build -t 4chan-backend .

# Ejecutar contenedor
docker run -p 3000:3000 --env-file .env 4chan-backend
```

## Estructura de API

### Principales Endpoints

- `/api/v1/auth` - Autenticación y gestión de usuarios
- `/api/v1/boards` - Gestión de tablones
- `/api/v1/threads` - Gestión de hilos
- `/api/v1/posts` - Gestión de posts
- `/api/v1/files` - Gestión de archivos
- `/api/v1/moderation` - Herramientas de moderación

## Base de Datos

Utilizamos PostgreSQL con Prisma ORM para la gestión de datos. El schema completo se encuentra en `prisma/schema.prisma`.

## Seguridad

Este backend implementa múltiples capas de seguridad:

- Encriptación de contraseñas con Argon2
- Protección contra ataques de inyección SQL (a través de Prisma)
- Rate limiting para prevenir abusos
- Validación estricta de entrada
- Headers de seguridad con Helmet
- Autenticación mediante JWT con rotación de tokens
- Logs detallados de actividad