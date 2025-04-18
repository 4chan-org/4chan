use anyhow::Result;
use sqlx::{Pool, Postgres, postgres::PgPoolOptions};
use std::time::Duration;
use tracing::{debug, error};

#[derive(Debug, Clone)]
pub struct PostgresRepository {
    pub pool: Pool<Postgres>,
}

impl PostgresRepository {
    pub async fn new(connection_string: &str) -> Result<Self> {
        debug!("Creating PostgreSQL connection pool");

        let pool = PgPoolOptions::new()
            .max_connections(25)
            .min_connections(5)
            .max_lifetime(Some(Duration::from_secs(1800)))
            .idle_timeout(Some(Duration::from_secs(600)))
            .connect(connection_string)
            .await
            .map_err(|e| {
                error!("Failed to create database connection pool: {}", e);
                e
            })?;

        // Test connection
        sqlx::query("SELECT 1")
            .execute(&pool)
            .await
            .map_err(|e| {
                error!("Failed to execute test query: {}", e);
                e
            })?;

        debug!("Successfully connected to PostgreSQL");

        Ok(Self { pool })
    }

    // Run migrations
    pub async fn run_migrations(&self) -> Result<()> {
        debug!("Running database migrations");

        sqlx::migrate!("./migrations")
            .run(&self.pool)
            .await
            .map_err(|e| {
                error!("Failed to run migrations: {}", e);
                e.into()
            })
    }

    // Health check
    pub async fn health_check(&self) -> Result<bool> {
        debug!("Performing health check on PostgreSQL");

        match sqlx::query("SELECT 1")
            .execute(&self.pool)
            .await
        {
            Ok(_) => Ok(true),
            Err(e) => {
                error!("Health check failed: {}", e);
                Ok(false)
            }
        }
    }
}