package handlers

import (
	"bytes"
	"context"
	"crypto/md5"
	"encoding/hex"
	"fmt"
	"io"
	"mime/multipart"
	"net/http"
	"path/filepath"
	"strconv"
	"strings"
	"time"

	"github.com/4chan/v2/backend_go/internal/api/models"
	"github.com/4chan/v2/backend_go/internal/storage"
	"github.com/gin-gonic/gin"
	"github.com/h2non/filetype"
	"github.com/h2non/filetype/matchers"
	"github.com/google/uuid"
)

// FileHandler handles file operations
type FileHandler struct {
	storage *storage.MinioClient
}

// NewFileHandler creates a new file handler
func NewFileHandler(storage *storage.MinioClient) *FileHandler {
	return &FileHandler{
		storage: storage,
	}
}

// Upload godoc
// @Summary Upload a file
// @Description Upload a file to be attached to a post
// @Tags files
// @Accept multipart/form-data
// @Produce json
// @Param file formData file true "File to upload"
// @Param spoiler formData bool false "Mark file as spoiler"
// @Success 201 {object} models.FileUploadResponse
// @Failure 400 {object} models.Error
// @Failure 401 {object} models.Error
// @Failure 413 {object} models.Error
// @Failure 415 {object} models.Error
// @Failure 429 {object} models.Error
// @Failure 500 {object} models.Error
// @Router /files/upload [post]
func (h *FileHandler) Upload(c *gin.Context) {
	// Parse form with 16MB max memory
	if err := c.Request.ParseMultipartForm(16 << 20); err != nil {
		c.JSON(http.StatusBadRequest, models.Error{
			StatusCode: http.StatusBadRequest,
			Message:    "Invalid multipart form",
			Error:      err.Error(),
		})
		return
	}

	// Get file from form
	file, header, err := c.Request.FormFile("file")
	if err != nil {
		c.JSON(http.StatusBadRequest, models.Error{
			StatusCode: http.StatusBadRequest,
			Message:    "File is required",
			Error:      "No file was provided",
		})
		return
	}
	defer file.Close()

	// Check file size (10MB max)
	maxSize := int64(10 << 20) // 10MB
	if header.Size > maxSize {
		c.JSON(http.StatusRequestEntityTooLarge, models.Error{
			StatusCode: http.StatusRequestEntityTooLarge,
			Message:    "File too large",
			Error:      fmt.Sprintf("Maximum file size is %d bytes", maxSize),
		})
		return
	}

	// Read file data
	fileData, err := readFile(file, header.Size)
	if err != nil {
		c.JSON(http.StatusInternalServerError, models.Error{
			StatusCode: http.StatusInternalServerError,
			Message:    "Failed to read file",
			Error:      err.Error(),
		})
		return
	}

	// Validate file type
	kind, err := filetype.Match(fileData)
	if err != nil || kind == filetype.Unknown {
		c.JSON(http.StatusUnsupportedMediaType, models.Error{
			StatusCode: http.StatusUnsupportedMediaType,
			Message:    "Unsupported file type",
			Error:      "File type could not be determined or is not supported",
		})
		return
	}

	// Check if file type is allowed
	if !isAllowedFileType(kind) {
		c.JSON(http.StatusUnsupportedMediaType, models.Error{
			StatusCode: http.StatusUnsupportedMediaType,
			Message:    "Unsupported file type",
			Error:      fmt.Sprintf("File type %s is not allowed", kind.MIME.Value),
		})
		return
	}

	// Generate MD5 hash
	hash := md5.Sum(fileData)
	md5Hash := hex.EncodeToString(hash[:])

	// Check for duplicate file by hash (in a real app, you would check the database)
	// This is just a placeholder for demonstration
	// isDuplicate := false
	// if isDuplicate {
	//     // Return existing file info
	// }

	// Parse spoiler parameter
	spoiler := false
	spoilerStr := c.Request.FormValue("spoiler")
	if spoilerStr != "" {
		spoiler, _ = strconv.ParseBool(spoilerStr)
	}

	// Get file dimensions if it's an image
	var width, height int
	if isImage(kind) {
		width, height, err = getImageDimensions(fileData)
		if err != nil {
			// Log the error but continue
			fmt.Printf("Failed to get image dimensions: %v\n", err)
		}
	}

	// Generate a unique file ID
	fileID := uuid.New().String()

	// Generate a unique filename
	timestamp := time.Now().Unix()
	ext := filepath.Ext(header.Filename)
	uniqueFileName := fmt.Sprintf("%d_%s%s", timestamp, fileID, ext)

	// Upload the file
	uploadInfo, err := h.storage.UploadFile(c.Request.Context(), fileData, uniqueFileName, kind.MIME.Value)
	if err != nil {
		c.JSON(http.StatusInternalServerError, models.Error{
			StatusCode: http.StatusInternalServerError,
			Message:    "Failed to upload file",
			Error:      err.Error(),
		})
		return
	}

	// In a real application, you would generate and store a thumbnail
	// For now, we'll just use the same URL
	thumbnailURL := uploadInfo.ThumbnailURL

	// Build response
	response := models.FileUploadResponse{
		ID:           fileID,
		FileURL:      uploadInfo.URL,
		ThumbnailURL: thumbnailURL,
		Filename:     header.Filename,
		Filesize:     header.Size,
		Width:        width,
		Height:       height,
		MimeType:     kind.MIME.Value,
		MD5Hash:      md5Hash,
		IsSpoilered:  spoiler,
		UploadDuration: 0, // In a real app, you would measure this
	}

	c.JSON(http.StatusCreated, response)
}

// GetFile godoc
// @Summary Get file information
// @Description Get metadata for a specific file
// @Tags files
// @Produce json
// @Param fileId path string true "File ID"
// @Success 200 {object} models.File
// @Failure 404 {object} models.Error
// @Failure 500 {object} models.Error
// @Router /files/{fileId} [get]
func (h *FileHandler) GetFile(c *gin.Context) {
	fileID := c.Param("fileId")
	if fileID == "" {
		c.JSON(http.StatusBadRequest, models.Error{
			StatusCode: http.StatusBadRequest,
			Message:    "File ID is required",
			Error:      "No file ID provided",
		})
		return
	}

	// In a real application, you would look up the file in your database
	// For now, we'll return a not found error
	c.JSON(http.StatusNotFound, models.Error{
		StatusCode: http.StatusNotFound,
		Message:    "File not found",
		Error:      fmt.Sprintf("No file found with ID: %s", fileID),
	})
}

// DeleteFile godoc
// @Summary Delete a file
// @Description Delete a file from the system
// @Tags files
// @Security BearerAuth
// @Param fileId path string true "File ID"
// @Success 204 "No Content"
// @Failure 401 {object} models.Error
// @Failure 403 {object} models.Error
// @Failure 404 {object} models.Error
// @Failure 500 {object} models.Error
// @Router /files/{fileId} [delete]
func (h *FileHandler) DeleteFile(c *gin.Context) {
	fileID := c.Param("fileId")
	if fileID == "" {
		c.JSON(http.StatusBadRequest, models.Error{
			StatusCode: http.StatusBadRequest,
			Message:    "File ID is required",
			Error:      "No file ID provided",
		})
		return
	}

	// In a real application, you would look up the file in your database
	// Then delete it from storage and from the database
	// For now, we'll return a not found error
	c.JSON(http.StatusNotFound, models.Error{
		StatusCode: http.StatusNotFound,
		Message:    "File not found",
		Error:      fmt.Sprintf("No file found with ID: %s", fileID),
	})
}

// GetFileContent godoc
// @Summary Get file content
// @Description Download the content of a file
// @Tags files
// @Produce octet-stream
// @Param fileId path string true "File ID"
// @Param download query boolean false "Download file instead of viewing in browser"
// @Success 200 {file} binary "File content"
// @Failure 404 {object} models.Error
// @Failure 500 {object} models.Error
// @Router /files/{fileId}/content [get]
func (h *FileHandler) GetFileContent(c *gin.Context) {
	fileID := c.Param("fileId")
	if fileID == "" {
		c.JSON(http.StatusBadRequest, models.Error{
			StatusCode: http.StatusBadRequest,
			Message:    "File ID is required",
			Error:      "No file ID provided",
		})
		return
	}

	// Check download parameter
	download := false
	downloadStr := c.Query("download")
	if downloadStr != "" {
		download, _ = strconv.ParseBool(downloadStr)
	}

	// In a real application, you would look up the file in your database
	// Then retrieve it from storage
	// For now, we'll return a not found error
	c.JSON(http.StatusNotFound, models.Error{
		StatusCode: http.StatusNotFound,
		Message:    "File not found",
		Error:      fmt.Sprintf("No file found with ID: %s", fileID),
	})
}

// GetThumbnail godoc
// @Summary Get file thumbnail
// @Description Get the thumbnail for a file
// @Tags files
// @Produce image/*
// @Param fileId path string true "File ID"
// @Param size query string false "Thumbnail size (small, medium, large)" default(medium)
// @Success 200 {file} binary "Thumbnail image"
// @Failure 404 {object} models.Error
// @Failure 500 {object} models.Error
// @Router /files/{fileId}/thumbnail [get]
func (h *FileHandler) GetThumbnail(c *gin.Context) {
	fileID := c.Param("fileId")
	if fileID == "" {
		c.JSON(http.StatusBadRequest, models.Error{
			StatusCode: http.StatusBadRequest,
			Message:    "File ID is required",
			Error:      "No file ID provided",
		})
		return
	}

	// Get size parameter
	size := c.DefaultQuery("size", "medium")
	if size != "small" && size != "medium" && size != "large" {
		size = "medium"
	}

	// In a real application, you would look up the thumbnail in your database
	// Then retrieve it from storage with the appropriate size
	// For now, we'll return a not found error
	c.JSON(http.StatusNotFound, models.Error{
		StatusCode: http.StatusNotFound,
		Message:    "Thumbnail not found",
		Error:      fmt.Sprintf("No thumbnail found for file ID: %s", fileID),
	})
}

// CheckFile godoc
// @Summary Check if a file exists
// @Description Check if a file with a specific MD5 hash already exists
// @Tags files
// @Accept json
// @Produce json
// @Param request body models.FileCheckRequest true "MD5 hash to check"
// @Success 200 {object} models.FileCheckResponse
// @Failure 400 {object} models.Error
// @Failure 500 {object} models.Error
// @Router /files/check [post]
func (h *FileHandler) CheckFile(c *gin.Context) {
	var request models.FileCheckRequest
	if err := c.ShouldBindJSON(&request); err != nil {
		c.JSON(http.StatusBadRequest, models.Error{
			StatusCode: http.StatusBadRequest,
			Message:    "Invalid request",
			Error:      err.Error(),
		})
		return
	}

	if request.MD5Hash == "" {
		c.JSON(http.StatusBadRequest, models.Error{
			StatusCode: http.StatusBadRequest,
			Message:    "MD5 hash is required",
			Error:      "No MD5 hash provided",
		})
		return
	}

	// In a real application, you would check if a file with this hash exists in your database
	// For now, we'll always say it doesn't exist
	response := models.FileCheckResponse{
		Exists: false,
		File:   nil,
	}

	c.JSON(http.StatusOK, response)
}

// GetBannedHashes godoc
// @Summary Get banned file hashes
// @Description Get a list of MD5 hashes of banned files
// @Tags files
// @Produce json
// @Success 200 {object} models.BannedHashesResponse
// @Failure 500 {object} models.Error
// @Router /files/banned [get]
func (h *FileHandler) GetBannedHashes(c *gin.Context) {
	// In a real application, you would retrieve this from your database
	// For now, we'll return an empty list
	response := models.BannedHashesResponse{
		Data:      []string{},
		UpdatedAt: time.Now(),
	}

	c.JSON(http.StatusOK, response)
}

// GetFileStats godoc
// @Summary Get file statistics
// @Description Get statistics about stored files
// @Tags files
// @Security BearerAuth
// @Produce json
// @Success 200 {object} models.FileStats
// @Failure 401 {object} models.Error
// @Failure 403 {object} models.Error
// @Failure 500 {object} models.Error
// @Router /files/stats [get]
func (h *FileHandler) GetFileStats(c *gin.Context) {
	// In a real application, you would calculate these statistics from your database
	// For now, we'll return dummy data
	response := models.FileStats{
		TotalFiles:       1000000,
		TotalSize:        5368709120, // 5GB
		AverageFileSize:  5368709,    // ~5MB
		FilesLastDay:     10000,
		FilesLastWeek:    70000,
		FilesByType: map[string]int{
			"image/jpeg": 500000,
			"image/png":  300000,
			"image/gif":  150000,
			"video/mp4":  50000,
		},
	}

	c.JSON(http.StatusOK, response)
}

// PurgeFiles godoc
// @Summary Purge old files
// @Description Start a task to purge old files based on criteria
// @Tags files
// @Security BearerAuth
// @Accept json
// @Produce json
// @Param request body models.FilePurgeRequest true "Purge criteria"
// @Success 202 {object} models.FilePurgeResponse
// @Failure 400 {object} models.Error
// @Failure 401 {object} models.Error
// @Failure 403 {object} models.Error
// @Failure 500 {object} models.Error
// @Router /files/admin/purge [post]
func (h *FileHandler) PurgeFiles(c *gin.Context) {
	var request models.FilePurgeRequest
	if err := c.ShouldBindJSON(&request); err != nil {
		c.JSON(http.StatusBadRequest, models.Error{
			StatusCode: http.StatusBadRequest,
			Message:    "Invalid request",
			Error:      err.Error(),
		})
		return
	}

	if request.OlderThanDays < 30 {
		c.JSON(http.StatusBadRequest, models.Error{
			StatusCode: http.StatusBadRequest,
			Message:    "Invalid olderThanDays value",
			Error:      "olderThanDays must be at least 30",
		})
		return
	}

	// In a real application, you would start an async task to purge files
	// For now, we'll just return a dummy response
	response := models.FilePurgeResponse{
		TaskID:              uuid.New().String(),
		EstimatedFilesToPurge: 50000,
		EstimatedSpaceToFree:  268435456, // 256MB
	}

	c.JSON(http.StatusAccepted, response)
}

// Helper function to read file
func readFile(file multipart.File, size int64) ([]byte, error) {
	buffer := make([]byte, size)
	_, err := io.ReadFull(file, buffer)
	if err != nil {
		return nil, err
	}
	return buffer, nil
}

// Helper function to check if file type is allowed
func isAllowedFileType(kind filetype.Type) bool {
	// List of allowed MIME types
	allowedTypes := map[string]bool{
		"image/jpeg":             true,
		"image/png":              true,
		"image/gif":              true,
		"image/webp":             true,
		"video/mp4":              true,
		"video/webm":             true,
		"application/pdf":        true,
		"application/zip":        true,
		"application/x-7z-compressed": true,
	}

	return allowedTypes[kind.MIME.Value]
}

// Helper function to check if file is an image
func isImage(kind filetype.Type) bool {
	return kind.MIME.Type == "image"
}

// Helper function to get image dimensions
// In a real application, you would use an image processing library
func getImageDimensions(fileData []byte) (width, height int, err error) {
	// Dummy implementation - in a real app you would use an image library
	// This is just a placeholder
	return 800, 600, nil
}