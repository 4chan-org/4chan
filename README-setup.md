# 4chan Modernization - Setup Guide

This guide will help you set up and run the modernized 4chan application locally using Docker.

## Prerequisites

- Docker and Docker Compose installed on your machine
- Git
- Make (optional, but recommended)

## Quick Start

If you have Make installed, you can use the following commands:

```bash
# Clone the repository (if you haven't already)
git clone https://github.com/yourusername/4chan.git
cd 4chan

# Start all services in development mode
make setup
```

This will build the Docker images, start all services, run migrations, and seed the database.

## Manual Setup

If you don't have Make installed, follow these steps:

1. Build the Docker images:
```bash
docker-compose build
```

2. Start all services:
```bash
docker-compose up -d
```

3. Run database migrations:
```bash
docker-compose exec api npm run prisma:migrate
```

4. Seed the database with sample data:
```bash
docker-compose exec api npm run seed
```

## Accessing the Application

Once all services are up and running, you can access:

- Main application: http://localhost
- MinIO Console (S3 storage): http://localhost:9001
  - Username: minioadmin
  - Password: minioadmin

## Services Architecture

The application consists of the following services:

- **Shell** - Main frontend application (React)
- **Microfrontends**:
  - Auth - Authentication component
  - Board Viewer - For viewing boards
  - Catalog Viewer - For viewing catalog
  - Post Creator - For creating posts
  - Shared - Shared components and utilities
- **Backend**:
  - API - Main backend API (NestJS)
  - Files - File service (Go)
- **Infrastructure**:
  - Nginx - API Gateway
  - PostgreSQL - Database
  - Redis - Caching and pub/sub
  - MinIO - S3-compatible object storage

## Development Workflow

### Viewing Logs

```bash
# View logs from all services
docker-compose logs -f

# View logs from a specific service
docker-compose logs -f shell
```

### Accessing Service Shells

```bash
# Access API shell
docker-compose exec api sh

# Access frontend shell
docker-compose exec shell sh
```

### Restarting Services

```bash
# Restart all services
docker-compose restart

# Restart a specific service
docker-compose restart shell
```

### Stopping the Application

```bash
# Stop all services
docker-compose down

# Stop and remove volumes (will delete database data)
docker-compose down -v
```

## Troubleshooting

1. **Service fails to start**: Check logs with `docker-compose logs [service_name]`
2. **Database connection issues**: Ensure PostgreSQL container is running with `docker-compose ps`
3. **Frontend not updating**: Try restarting the service with `docker-compose restart shell`

If you encounter any issues, please refer to the service-specific logs for more information.

## Additional Commands

The Makefile provides several useful commands:

```bash
# View available commands
make help

# Run linters
make lint

# Run tests
make test

# Reset the database
make reset-db
```

## Building for Production

```bash
# Build production images
make build-prod

# Start production services
make up-prod
```