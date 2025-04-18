package models

import (
	"time"
)

// File represents a file in the system
type File struct {
	ID           string    `json:"id"`
	Filename     string    `json:"filename"`
	StoredFilename string  `json:"storedFilename,omitempty"`
	Filesize     int64     `json:"filesize"`
	Width        int       `json:"width,omitempty"`
	Height       int       `json:"height,omitempty"`
	ThumbnailFilename string `json:"thumbnailFilename,omitempty"`
	MimeType     string    `json:"mimeType"`
	MD5Hash      string    `json:"md5Hash"`
	SHA256Hash   string    `json:"sha256Hash,omitempty"`
	IsSpoilered  bool      `json:"isSpoilered"`
	CreatedAt    time.Time `json:"createdAt"`
	PostID       string    `json:"postId,omitempty"`
	FileURL      string    `json:"fileUrl"`
	ThumbnailURL string    `json:"thumbnailUrl"`
}

// FileUploadResponse represents the response after a successful file upload
type FileUploadResponse struct {
	ID             string `json:"id"`
	FileURL        string `json:"fileUrl"`
	ThumbnailURL   string `json:"thumbnailUrl"`
	Filename       string `json:"filename"`
	Filesize       int64  `json:"filesize"`
	Width          int    `json:"width,omitempty"`
	Height         int    `json:"height,omitempty"`
	MimeType       string `json:"mimeType"`
	MD5Hash        string `json:"md5Hash"`
	IsSpoilered    bool   `json:"isSpoilered,omitempty"`
	UploadDuration int    `json:"uploadDuration,omitempty"`
}

// FileCheckRequest represents a request to check if a file exists
type FileCheckRequest struct {
	MD5Hash string `json:"md5Hash"`
}

// FileCheckResponse represents the response to a file check request
type FileCheckResponse struct {
	Exists bool  `json:"exists"`
	File   *File `json:"file,omitempty"`
}

// BannedHashesResponse represents the response to a banned hashes request
type BannedHashesResponse struct {
	Data      []string  `json:"data"`
	UpdatedAt time.Time `json:"updatedAt"`
}

// FileStats represents statistics about files in the system
type FileStats struct {
	TotalFiles      int            `json:"totalFiles"`
	TotalSize       int64          `json:"totalSize"`
	FilesByType     map[string]int `json:"filesByType"`
	AverageFileSize int64          `json:"averageFileSize"`
	FilesLastDay    int            `json:"filesLastDay"`
	FilesLastWeek   int            `json:"filesLastWeek"`
}

// FilePurgeRequest represents a request to purge old files
type FilePurgeRequest struct {
	OlderThanDays  int      `json:"olderThanDays"`
	MimeTypes      []string `json:"mimeTypes,omitempty"`
	ExceptBoardIds []string `json:"exceptBoardIds,omitempty"`
	DryRun         bool     `json:"dryRun,omitempty"`
}

// FilePurgeResponse represents the response to a file purge request
type FilePurgeResponse struct {
	TaskID                string `json:"taskId"`
	EstimatedFilesToPurge int    `json:"estimatedFilesToPurge"`
	EstimatedSpaceToFree  int64  `json:"estimatedSpaceToFree"`
}