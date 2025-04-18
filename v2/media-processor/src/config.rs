use serde::Deserialize;
use std::env;
use config::{Config as ConfigLib, ConfigError, Environment, File};

#[derive(Debug, Clone, Deserialize)]
pub struct Config {
    pub environment: String,
    pub log_level: String,
    pub server: ServerConfig,
    pub database: DatabaseConfig,
    pub redis: RedisConfig,
    pub s3: S3Config,
    pub jwt: JwtConfig,
    pub captcha: CaptchaConfig,
    pub cors: CorsConfig,
    pub rate_limit: RateLimitConfig,
}

#[derive(Debug, Clone, Deserialize)]
pub struct ServerConfig {
    pub host: String,
    pub port: u16,
    pub api_prefix: String,
    pub api_version: String,
    pub read_timeout_seconds: u64,
    pub write_timeout_seconds: u64,
    pub keep_alive_seconds: u64,
}

#[derive(Debug, Clone, Deserialize)]
pub struct DatabaseConfig {
    pub connection_string: String,
    pub max_connections: u32,
    pub min_connections: u32,
    pub max_lifetime_seconds: u64,
    pub idle_timeout_seconds: u64,
}

#[derive(Debug, Clone, Deserialize)]
pub struct RedisConfig {
    pub url: String,
    pub pool_max_size: u32,
}

#[derive(Debug, Clone, Deserialize)]
pub struct S3Config {
    pub endpoint: String,
    pub region: String,
    pub access_key: String,
    pub secret_key: String,
    pub bucket: String,
    pub use_ssl: bool,
}

#[derive(Debug, Clone, Deserialize)]
pub struct JwtConfig {
    pub secret: String,
    pub expiration_minutes: i64,
    pub refresh_secret: String,
    pub refresh_expiration_days: i64,
    pub issuer: String,
}

#[derive(Debug, Clone, Deserialize)]
pub struct CaptchaConfig {
    pub secret_key: String,
    pub site_key: String,
    pub verify_url: String,
}

#[derive(Debug, Clone, Deserialize)]
pub struct CorsConfig {
    pub allowed_origins: String,
    pub allowed_methods: String,
    pub allowed_headers: String,
    pub max_age: u32,
}

#[derive(Debug, Clone, Deserialize)]
pub struct RateLimitConfig {
    pub enabled: bool,
    pub requests_per_second: u32,
    pub burst_size: u32,
    pub per_ip: bool,
    pub ip_header: String,
}

impl Config {
    pub fn from_env() -> Result<Self, ConfigError> {
        // Load .env file if it exists
        dotenv::dotenv().ok();

        // Set default configuration
        let mut s = ConfigLib::default();
        
        // Read default configuration
        s.merge(File::with_name("config/default")
            .required(false))?;

        // Read environment-specific config file if specified
        let env = env::var("RUN_ENV").unwrap_or_else(|_| "development".into());
        s.merge(File::with_name(&format!("config/{}", env))
            .required(false))?;

        // Add in settings from environment variables (with a prefix of APP)
        // E.g. APP_SERVER__PORT=8080 would set server.port
        s.merge(Environment::with_prefix("app")
            .separator("__"))?;

        // Parse Neon database URL if using a standard URL format
        if let Ok(database_url) = env::var("DATABASE_URL") {
            s.set("database.connection_string", database_url)?;
        }

        // Deserialize configuration
        s.try_into()
    }
}

// Default configuration values
impl Default for Config {
    fn default() -> Self {
        Self {
            environment: "development".to_string(),
            log_level: "info".to_string(),
            server: ServerConfig {
                host: "0.0.0.0".to_string(),
                port: 8080,
                api_prefix: "api".to_string(),
                api_version: "v1".to_string(),
                read_timeout_seconds: 10,
                write_timeout_seconds: 30,
                keep_alive_seconds: 60,
            },
            database: DatabaseConfig {
                connection_string: "postgres://postgres:postgres@localhost:5432/postgres".to_string(),
                max_connections: 25,
                min_connections: 5,
                max_lifetime_seconds: 1800,
                idle_timeout_seconds: 600,
            },
            redis: RedisConfig {
                url: "redis://localhost:6379".to_string(),
                pool_max_size: 10,
            },
            s3: S3Config {
                endpoint: "s3.amazonaws.com".to_string(),
                region: "us-east-1".to_string(),
                access_key: "minio".to_string(),
                secret_key: "minio123".to_string(),
                bucket: "4chan-v2".to_string(),
                use_ssl: true,
            },
            jwt: JwtConfig {
                secret: "super_secret_key_change_in_production".to_string(),
                expiration_minutes: 60,
                refresh_secret: "super_secret_refresh_key_change_in_production".to_string(),
                refresh_expiration_days: 7,
                issuer: "4chan-v2".to_string(),
            },
            captcha: CaptchaConfig {
                secret_key: "secret".to_string(),
                site_key: "site_key".to_string(),
                verify_url: "https://www.google.com/recaptcha/api/siteverify".to_string(),
            },
            cors: CorsConfig {
                allowed_origins: "http://localhost:3000".to_string(),
                allowed_methods: "GET,POST,PUT,DELETE,OPTIONS".to_string(),
                allowed_headers: "Authorization,Content-Type".to_string(),
                max_age: 86400,
            },
            rate_limit: RateLimitConfig {
                enabled: true,
                requests_per_second: 10,
                burst_size: 20,
                per_ip: true,
                ip_header: "X-Real-IP".to_string(),
            },
        }
    }
}