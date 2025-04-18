package config

import (
	"fmt"
	"os"

	"github.com/joho/godotenv"
	"github.com/spf13/viper"
)

// Config holds all configuration for the application
type Config struct {
	Environment string         `mapstructure:"ENVIRONMENT"`
	LogLevel    string         `mapstructure:"LOG_LEVEL"`
	Server      ServerConfig   `mapstructure:",squash"`
	Database    DatabaseConfig `mapstructure:",squash"`
	Redis       RedisConfig    `mapstructure:",squash"`
	Minio       MinioConfig    `mapstructure:",squash"`
	JWT         JWTConfig      `mapstructure:",squash"`
	Captcha     CaptchaConfig  `mapstructure:",squash"`
	CORS        CORSConfig     `mapstructure:",squash"`
	RateLimit   RateLimitConfig `mapstructure:",squash"`
}

// ServerConfig holds server related configuration
type ServerConfig struct {
	Port               int    `mapstructure:"PORT"`
	ReadTimeoutSeconds int    `mapstructure:"READ_TIMEOUT_SECONDS"`
	WriteTimeoutSeconds int   `mapstructure:"WRITE_TIMEOUT_SECONDS"`
	IdleTimeoutSeconds int    `mapstructure:"IDLE_TIMEOUT_SECONDS"`
	APIPrefix          string `mapstructure:"API_PREFIX"`
	APIVersion         string `mapstructure:"API_VERSION"`
}

// DatabaseConfig holds database related configuration
type DatabaseConfig struct {
	ConnectionString string `mapstructure:"DATABASE_URL"`
	MaxOpenConns     int    `mapstructure:"DB_MAX_OPEN_CONNS"`
	MaxIdleConns     int    `mapstructure:"DB_MAX_IDLE_CONNS"`
	ConnMaxLifetime  int    `mapstructure:"DB_CONN_MAX_LIFETIME"`
}

// RedisConfig holds Redis related configuration
type RedisConfig struct {
	Host     string `mapstructure:"REDIS_HOST"`
	Port     int    `mapstructure:"REDIS_PORT"`
	Password string `mapstructure:"REDIS_PASSWORD"`
	DB       int    `mapstructure:"REDIS_DB"`
}

// MinioConfig holds MinIO (S3) related configuration
type MinioConfig struct {
	Endpoint  string `mapstructure:"MINIO_ENDPOINT"`
	AccessKey string `mapstructure:"MINIO_ACCESS_KEY"`
	SecretKey string `mapstructure:"MINIO_SECRET_KEY"`
	Bucket    string `mapstructure:"MINIO_BUCKET"`
	UseSSL    bool   `mapstructure:"MINIO_USE_SSL"`
}

// JWTConfig holds JWT related configuration
type JWTConfig struct {
	SecretKey       string `mapstructure:"JWT_SECRET"`
	ExpirationHours int    `mapstructure:"JWT_EXPIRATION_HOURS"`
	RefreshSecret   string `mapstructure:"JWT_REFRESH_SECRET"`
	RefreshHours    int    `mapstructure:"JWT_REFRESH_HOURS"`
	Issuer          string `mapstructure:"JWT_ISSUER"`
}

// CaptchaConfig holds CAPTCHA related configuration
type CaptchaConfig struct {
	SecretKey string `mapstructure:"CAPTCHA_SECRET_KEY"`
	SiteKey   string `mapstructure:"CAPTCHA_SITE_KEY"`
	VerifyURL string `mapstructure:"CAPTCHA_VERIFY_URL"`
}

// CORSConfig holds CORS related configuration
type CORSConfig struct {
	AllowOrigins string `mapstructure:"CORS_ALLOW_ORIGINS"`
	AllowMethods string `mapstructure:"CORS_ALLOW_METHODS"`
	AllowHeaders string `mapstructure:"CORS_ALLOW_HEADERS"`
	MaxAge       int    `mapstructure:"CORS_MAX_AGE"`
}

// RateLimitConfig holds rate limiting configuration
type RateLimitConfig struct {
	Enabled          bool  `mapstructure:"RATE_LIMIT_ENABLED"`
	Requests         int   `mapstructure:"RATE_LIMIT_REQUESTS"`
	WindowSeconds    int   `mapstructure:"RATE_LIMIT_WINDOW_SECONDS"`
	IPHeaderName     string `mapstructure:"RATE_LIMIT_IP_HEADER"`
}

// Load reads configuration from files and environment variables
func Load() (*Config, error) {
	// Load .env file if it exists
	godotenv.Load()

	// Set default configuration
	viper.SetDefault("ENVIRONMENT", "development")
	viper.SetDefault("LOG_LEVEL", "info")
	viper.SetDefault("PORT", 8080)
	viper.SetDefault("READ_TIMEOUT_SECONDS", 10)
	viper.SetDefault("WRITE_TIMEOUT_SECONDS", 30)
	viper.SetDefault("IDLE_TIMEOUT_SECONDS", 120)
	viper.SetDefault("API_PREFIX", "api")
	viper.SetDefault("API_VERSION", "v1")
	viper.SetDefault("DB_MAX_OPEN_CONNS", 25)
	viper.SetDefault("DB_MAX_IDLE_CONNS", 10)
	viper.SetDefault("DB_CONN_MAX_LIFETIME", 300)
	viper.SetDefault("REDIS_PORT", 6379)
	viper.SetDefault("REDIS_DB", 0)
	viper.SetDefault("MINIO_USE_SSL", true)
	viper.SetDefault("JWT_EXPIRATION_HOURS", 24)
	viper.SetDefault("JWT_REFRESH_HOURS", 168) // 7 days
	viper.SetDefault("JWT_ISSUER", "4chan-v2")
	viper.SetDefault("CORS_ALLOW_METHODS", "GET,POST,PUT,DELETE,OPTIONS")
	viper.SetDefault("CORS_ALLOW_HEADERS", "Authorization,Content-Type")
	viper.SetDefault("CORS_MAX_AGE", 86400)
	viper.SetDefault("RATE_LIMIT_ENABLED", true)
	viper.SetDefault("RATE_LIMIT_REQUESTS", 100)
	viper.SetDefault("RATE_LIMIT_WINDOW_SECONDS", 60)
	viper.SetDefault("RATE_LIMIT_IP_HEADER", "X-Real-IP")

	// Read configuration from environment variables
	viper.AutomaticEnv()

	// Parse Neon database URL if using a standard URL format
	if os.Getenv("DATABASE_URL") != "" {
		viper.Set("DATABASE_URL", os.Getenv("DATABASE_URL"))
	}

	var config Config
	if err := viper.Unmarshal(&config); err != nil {
		return nil, fmt.Errorf("failed to unmarshal config: %w", err)
	}

	return &config, nil
}