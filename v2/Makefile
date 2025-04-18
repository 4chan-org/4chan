.PHONY: help build run test lint clean logs db-migrate db-seed k8s-apply k8s-delete

help: ## Muestra esta ayuda
	@awk 'BEGIN {FS = ":.*##"; printf "\nUso:\n  make \033[36m<target>\033[0m\n\nTargets:\n"} /^[a-zA-Z_-]+:.*?##/ { printf "  \033[36m%-20s\033[0m %s\n", $$1, $$2 }' $(MAKEFILE_LIST)

# Variables
DOCKER_COMPOSE = docker-compose -f infrastructure/docker-compose.yml

build: ## Construye todas las imágenes
	$(DOCKER_COMPOSE) build

run: ## Inicia todos los servicios en modo desarrollo
	$(DOCKER_COMPOSE) up -d

stop: ## Detiene todos los servicios
	$(DOCKER_COMPOSE) down

restart: ## Reinicia todos los servicios
	$(DOCKER_COMPOSE) restart

logs: ## Muestra los logs de todos los servicios
	$(DOCKER_COMPOSE) logs -f

test: ## Ejecuta todas las pruebas
	cd backend && npm run test
	cd frontend && npm run test

lint: ## Ejecuta lint en todos los proyectos
	cd backend && npm run lint
	cd frontend && npm run lint

clean: ## Limpia imágenes y volúmenes no utilizados
	docker system prune -af
	docker volume prune -f

db-migrate: ## Ejecuta migraciones de base de datos
	cd backend && npm run prisma:migrate

db-reset: ## Reinicia la base de datos (CUIDADO: borra todos los datos)
	$(DOCKER_COMPOSE) down -v postgres
	$(DOCKER_COMPOSE) up -d postgres

db-seed: ## Carga datos iniciales en la base de datos
	cd backend && npm run prisma:seed

frontend-dev: ## Inicia sólo el frontend en modo desarrollo
	cd frontend && npm run dev

backend-dev: ## Inicia sólo el backend en modo desarrollo
	cd backend && npm run dev

k8s-apply: ## Aplica la configuración de Kubernetes
	kubectl apply -k infrastructure/kubernetes/overlays/development

k8s-delete: ## Elimina la configuración de Kubernetes
	kubectl delete -k infrastructure/kubernetes/overlays/development

db-studio: ## Inicia Prisma Studio para gestionar la base de datos
	cd backend && npm run prisma:studio

security-scan: ## Ejecuta escaneo de seguridad en el código
	npm audit --prefix frontend
	npm audit --prefix backend

performance-test: ## Ejecuta pruebas de rendimiento
	npx autocannon http://localhost/api/v1/health -c 100 -d 30

all: build run ## Construye e inicia todos los servicios

.DEFAULT_GOAL := help