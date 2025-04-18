package database

import (
	"context"
	"fmt"
	"time"

	"github.com/4chan/v2/backend_go/config"
	"github.com/redis/go-redis/v9"
)

// RedisClient represents a Redis client connection
type RedisClient struct {
	client *redis.Client
}

// NewRedisClient creates a new Redis client
func NewRedisClient(cfg config.RedisConfig) (*RedisClient, error) {
	// Create Redis client
	client := redis.NewClient(&redis.Options{
		Addr:         fmt.Sprintf("%s:%d", cfg.Host, cfg.Port),
		Password:     cfg.Password,
		DB:           cfg.DB,
		DialTimeout:  5 * time.Second,
		ReadTimeout:  3 * time.Second,
		WriteTimeout: 3 * time.Second,
		PoolSize:     10,
		MinIdleConns: 5,
	})

	// Test connection
	ctx, cancel := context.WithTimeout(context.Background(), 5*time.Second)
	defer cancel()
	
	if _, err := client.Ping(ctx).Result(); err != nil {
		return nil, fmt.Errorf("failed to connect to Redis: %w", err)
	}

	return &RedisClient{client: client}, nil
}

// Close closes the Redis client
func (rc *RedisClient) Close() error {
	if rc.client != nil {
		return rc.client.Close()
	}
	return nil
}

// GetClient returns the Redis client
func (rc *RedisClient) GetClient() *redis.Client {
	return rc.client
}

// Set sets a key-value pair in Redis with expiration
func (rc *RedisClient) Set(ctx context.Context, key string, value interface{}, expiration time.Duration) error {
	return rc.client.Set(ctx, key, value, expiration).Err()
}

// Get gets a value from Redis by key
func (rc *RedisClient) Get(ctx context.Context, key string) (string, error) {
	return rc.client.Get(ctx, key).Result()
}

// Delete deletes a key from Redis
func (rc *RedisClient) Delete(ctx context.Context, key string) error {
	return rc.client.Del(ctx, key).Err()
}

// Exists checks if a key exists in Redis
func (rc *RedisClient) Exists(ctx context.Context, key string) (bool, error) {
	result, err := rc.client.Exists(ctx, key).Result()
	return result > 0, err
}

// Increment increments a counter in Redis
func (rc *RedisClient) Increment(ctx context.Context, key string) (int64, error) {
	return rc.client.Incr(ctx, key).Result()
}

// SetWithTTL sets a key-value pair with TTL
func (rc *RedisClient) SetWithTTL(ctx context.Context, key string, value interface{}, ttl time.Duration) error {
	return rc.client.Set(ctx, key, value, ttl).Err()
}

// RateLimiter increments a counter and returns whether the limit is exceeded
func (rc *RedisClient) RateLimiter(ctx context.Context, key string, limit int, window time.Duration) (bool, error) {
	// Get the current count
	count, err := rc.client.Get(ctx, key).Int()
	if err == redis.Nil {
		// Key doesn't exist, set it to 1 with TTL
		if err := rc.client.Set(ctx, key, 1, window).Err(); err != nil {
			return false, err
		}
		return false, nil // Not exceeded
	} else if err != nil {
		return false, err
	}

	// Increment the counter
	count++
	if err := rc.client.Set(ctx, key, count, window).Err(); err != nil {
		return false, err
	}

	// Check if limit is exceeded
	return count > limit, nil
}