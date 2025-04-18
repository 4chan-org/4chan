package api

import (
	"time"

	"github.com/4chan/v2/backend_go/config"
	"github.com/4chan/v2/backend_go/internal/api/handlers"
	"github.com/4chan/v2/backend_go/internal/api/middleware"
	"github.com/4chan/v2/backend_go/internal/database"
	"github.com/4chan/v2/backend_go/internal/storage"
	"github.com/gin-gonic/gin"
	"github.com/rs/zerolog"
	ginSwagger "github.com/swaggo/gin-swagger"
	"github.com/swaggo/gin-swagger/swaggerFiles"
)

// NewRouter creates a new router with all endpoints
func NewRouter(
	cfg *config.Config,
	logger zerolog.Logger,
	db *database.PostgresDB,
	redis *database.RedisClient,
	fileStorage *storage.MinioClient,
) *gin.Engine {
	router := gin.New()

	// Middleware
	router.Use(middleware.RequestLogger(logger))
	router.Use(middleware.Recovery(logger))
	router.Use(middleware.CORS(cfg.CORS))
	router.Use(middleware.Security())

	// Rate limiter
	if cfg.RateLimit.Enabled {
		router.Use(middleware.RateLimiter(redis, cfg.RateLimit))
	}

	// Create handlers
	healthHandler := handlers.NewHealthHandler(db, redis)
	authHandler := handlers.NewAuthHandler(db, redis, cfg.JWT)
	boardHandler := handlers.NewBoardHandler(db, redis)
	threadHandler := handlers.NewThreadHandler(db, redis)
	postHandler := handlers.NewPostHandler(db, redis, fileStorage)
	fileHandler := handlers.NewFileHandler(fileStorage)
	captchaHandler := handlers.NewCaptchaHandler(redis, cfg.Captcha)
	moderationHandler := handlers.NewModerationHandler(db, redis)

	// Metrics endpoint
	router.GET("/metrics", middleware.Metrics())

	// Health check endpoint
	router.GET("/health", healthHandler.Check)

	// API routes
	apiPrefix := cfg.Server.APIPrefix + "/" + cfg.Server.APIVersion
	api := router.Group(apiPrefix)

	// Swagger documentation
	if cfg.Environment != "production" {
		api.GET("/swagger/*any", ginSwagger.WrapHandler(swaggerFiles.Handler))
	}

	// Authentication routes
	auth := api.Group("/auth")
	{
		auth.POST("/register", authHandler.Register)
		auth.POST("/login", authHandler.Login)
		auth.POST("/refresh", authHandler.RefreshToken)
		auth.POST("/logout", middleware.JWTAuth(cfg.JWT), authHandler.Logout)
	}

	// Captcha routes
	api.GET("/captcha", captchaHandler.Generate)
	api.POST("/captcha/verify", captchaHandler.Verify)

	// Board routes
	boards := api.Group("/boards")
	{
		boards.GET("", boardHandler.ListBoards)
		boards.GET("/:boardId", boardHandler.GetBoard)
		
		// Thread routes
		boards.GET("/:boardId/threads", threadHandler.ListThreads)
		boards.POST("/:boardId/threads", middleware.ValidateCaptcha(captchaHandler), threadHandler.CreateThread)
		boards.GET("/:boardId/catalog", threadHandler.GetCatalog)
		
		// Thread detail routes
		boards.GET("/:boardId/threads/:threadId", threadHandler.GetThread)
		boards.POST("/:boardId/threads/:threadId/posts", middleware.ValidateCaptcha(captchaHandler), postHandler.CreatePost)
		
		// Report routes
		boards.POST("/:boardId/posts/:postId/report", middleware.ValidateCaptcha(captchaHandler), moderationHandler.ReportPost)
	}

	// File routes
	files := api.Group("/files")
	{
		files.POST("/upload", middleware.JWTAuth(cfg.JWT), fileHandler.Upload)
	}

	// Moderation routes (protected)
	moderation := api.Group("/moderation")
	moderation.Use(middleware.JWTAuth(cfg.JWT), middleware.Authorization([]string{"ADMIN", "MODERATOR"}))
	{
		moderation.GET("/reports", moderationHandler.ListReports)
		moderation.PUT("/reports/:reportId", moderationHandler.HandleReport)
		moderation.POST("/ban", moderationHandler.CreateBan)
		moderation.GET("/bans", moderationHandler.ListBans)
		moderation.DELETE("/bans/:banId", moderationHandler.RemoveBan)
	}

	// Admin routes (protected)
	admin := api.Group("/admin")
	admin.Use(middleware.JWTAuth(cfg.JWT), middleware.Authorization([]string{"ADMIN"}))
	{
		admin.POST("/boards", boardHandler.CreateBoard)
		admin.PUT("/boards/:boardId", boardHandler.UpdateBoard)
		admin.DELETE("/boards/:boardId", boardHandler.DeleteBoard)
		
		admin.GET("/users", authHandler.ListUsers)
		admin.POST("/users", authHandler.CreateUser)
		admin.PUT("/users/:userId", authHandler.UpdateUser)
		admin.DELETE("/users/:userId", authHandler.DeleteUser)
	}

	return router
}