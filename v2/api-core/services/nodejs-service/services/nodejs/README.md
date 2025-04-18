# 4chan Backend API

## Descripci�n

API backend moderna para 4chan, construida con NestJS, TypeScript, y Prisma ORM para proporcionar una base s�lida, segura y escalable.

## Caracter�sticas

- Arquitectura modular basada en NestJS
- ORM tipado con Prisma
- Autenticaci�n JWT con refreshing tokens
- Validaci�n y sanitizaci�n de entrada
- Documentaci�n autom�tica con Swagger
- Logs estructurados
- Tests unitarios y de integraci�n

## Tecnolog�as Utilizadas

- NestJS
- TypeScript
- Prisma ORM
- PostgreSQL
- JWT
- Winston (logging)
- Jest (testing)
- Swagger/OpenAPI

## Instalaci�n

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

# Lint y formateo de c�digo
npm run lint
npm run format
```

## Construcci�n

```bash
# Construir para producci�n
npm run build

# Iniciar en modo producci�n
npm run start:prod
```

## Docker

```bash
# Construir imagen
docker build -t 4chan-api-core .

# Ejecutar contenedor
docker run -p 3000:3000 --env-file .env 4chan-api-core
```

## Estructura de API

### Principales Endpoints

- `/api/v1/auth` - Autenticaci�n y gesti�n de usuarios
- `/api/v1/boards` - Gesti�n de tablones
- `/api/v1/threads` - Gesti�n de hilos
- `/api/v1/posts` - Gesti�n de posts
- `/api/v1/files` - Gesti�n de archivos
- `/api/v1/moderation` - Herramientas de moderaci�n

## Base de Datos

Utilizamos PostgreSQL con Prisma ORM para la gesti�n de datos. El schema completo se encuentra en `prisma/schema.prisma`.

## Seguridad

Este backend implementa m�ltiples capas de seguridad:

- Encriptaci�n de contrase�as con Argon2
- Protecci�n contra ataques de inyecci�n SQL (a trav�s de Prisma)
- Rate limiting para prevenir abusos
- Validaci�n estricta de entrada
- Headers de seguridad con Helmet
- Autenticaci�n mediante JWT con rotaci�n de tokens
- Logs detallados de actividad