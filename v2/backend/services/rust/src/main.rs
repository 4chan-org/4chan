use actix_cors::Cors;
use actix_web::{middleware, web, App, HttpServer};
use actix_web_prometheus::PrometheusMetricsBuilder;
use prometheus::Registry;
use std::net::TcpListener;
use tracing::{info, Level};
use tracing_subscriber::FmtSubscriber;

mod config;
mod routes;
mod handlers;
mod middleware as app_middleware;
mod models;
mod repositories;
mod services;
mod utils;
mod error;

use config::Config;
use repositories::{
    postgres_repository::PostgresRepository, 
    redis_repository::RedisRepository,
    s3_repository::S3Repository,
};

#[actix_web::main]
async fn main() -> std::io::Result<()> {
    // Load configuration
    let config = Config::from_env().expect("Failed to load configuration");

    // Set up logging
    let subscriber = FmtSubscriber::builder()
        .with_max_level(match config.log_level.as_str() {
            "trace" => Level::TRACE,
            "debug" => Level::DEBUG,
            "info" => Level::INFO,
            "warn" => Level::WARN,
            "error" => Level::ERROR,
            _ => Level::INFO,
        })
        .finish();

    tracing::subscriber::set_global_default(subscriber)
        .expect("Failed to set up global tracing subscriber");

    info!("Starting server on {}:{}", config.server.host, config.server.port);

    // Create database connection pool
    let postgres_repo = PostgresRepository::new(&config.database.connection_string)
        .await
        .expect("Failed to create database connection pool");

    // Create Redis client
    let redis_repo = RedisRepository::new(&config.redis.url)
        .await
        .expect("Failed to create Redis client");

    // Create S3 client
    let s3_repo = S3Repository::new(
        &config.s3.endpoint,
        &config.s3.region,
        &config.s3.access_key,
        &config.s3.secret_key,
        &config.s3.bucket,
    )
    .await
    .expect("Failed to create S3 client");

    // Create a new registry for metrics
    let registry = Registry::new();
    let prometheus = PrometheusMetricsBuilder::new("api")
        .registry(registry)
        .endpoint("/metrics")
        .build()
        .unwrap();

    // Start HTTP server
    let bind_address = format!("{}:{}", config.server.host, config.server.port);
    let listener = TcpListener::bind(&bind_address)?;

    HttpServer::new(move || {
        // Configure CORS
        let cors = Cors::default()
            .allowed_origin(&config.cors.allowed_origins)
            .allowed_methods(vec!["GET", "POST", "PUT", "DELETE", "OPTIONS"])
            .allowed_headers(vec!["Authorization", "Content-Type"])
            .max_age(3600);

        // Create data access objects
        let postgres_repo = postgres_repo.clone();
        let redis_repo = redis_repo.clone();
        let s3_repo = s3_repo.clone();
        let app_config = config.clone();

        App::new()
            .wrap(prometheus.clone())
            .wrap(middleware::Logger::default())
            .wrap(middleware::Compress::default())
            .wrap(middleware::NormalizePath::trim())
            .wrap(cors)
            .app_data(web::Data::new(app_config.clone()))
            .app_data(web::Data::new(postgres_repo))
            .app_data(web::Data::new(redis_repo))
            .app_data(web::Data::new(s3_repo))
            .service(
                web::scope(&format!("{}/{}", app_config.server.api_prefix, app_config.server.api_version))
                    .configure(routes::health::configure)
                    .configure(routes::auth::configure)
                    .configure(routes::boards::configure)
                    .configure(routes::threads::configure)
                    .configure(routes::posts::configure)
                    .configure(routes::files::configure)
                    .configure(routes::captcha::configure)
                    .configure(routes::moderation::configure)
            )
    })
    .listen(listener)?
    .run()
    .await
}