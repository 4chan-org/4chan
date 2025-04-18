# Makefile for 4chan modernization project

# Default environment
ENV ?= development

.PHONY: help
help:
	@echo "Available commands:"
	@echo "  make up               - Start all services in development mode"
	@echo "  make down             - Stop all services"
	@echo "  make build            - Build all containers"
	@echo "  make restart          - Restart all services"
	@echo "  make logs             - View logs from all services"
	@echo "  make shell-api        - Open a shell in the API container"
	@echo "  make shell-frontend   - Open a shell in the frontend shell container"
	@echo "  make clean            - Remove all containers, volumes, and networks"
	@echo "  make migrate          - Run database migrations"
	@echo "  make generate         - Generate Prisma client"
	@echo "  make test             - Run tests"
	@echo "  make lint             - Run linters"
	@echo "  make seed             - Seed the database with sample data"

.PHONY: up
up:
	docker-compose up -d

.PHONY: down
down:
	docker-compose down

.PHONY: build
build:
	docker-compose build

.PHONY: restart
restart:
	docker-compose restart

.PHONY: logs
logs:
	docker-compose logs -f

.PHONY: shell-api
shell-api:
	docker-compose exec api sh

.PHONY: shell-frontend
shell-frontend:
	docker-compose exec shell sh

.PHONY: clean
clean:
	docker-compose down -v --remove-orphans

.PHONY: migrate
migrate:
	docker-compose exec api npm run prisma:migrate

.PHONY: generate
generate:
	docker-compose exec api npm run prisma:generate

.PHONY: test
test:
	docker-compose exec api npm test

.PHONY: lint
lint:
	docker-compose exec api npm run lint
	docker-compose exec shell npm run lint

.PHONY: seed
seed:
	docker-compose exec api npm run seed

.PHONY: reset-db
reset-db:
	docker-compose exec api npm run prisma:reset

# Production-specific commands
.PHONY: up-prod
up-prod:
	ENV=production docker-compose -f docker-compose.prod.yml up -d

.PHONY: down-prod
down-prod:
	ENV=production docker-compose -f docker-compose.prod.yml down

.PHONY: build-prod
build-prod:
	ENV=production docker-compose -f docker-compose.prod.yml build

# Development utilities
.PHONY: install-all
install-all:
	cd v2/api-core && npm install
	cd v2/microfrontends/shell && npm install
	cd v2/microfrontends/auth && npm install
	cd v2/microfrontends/board-viewer && npm install
	cd v2/microfrontends/catalog-viewer && npm install
	cd v2/microfrontends/post-creator && npm install
	cd v2/microfrontends/shared && npm install

.PHONY: setup
setup: build up
	@echo "Setting up project..."
	make migrate
	make seed
	@echo "Setup complete. Access the application at http://localhost"