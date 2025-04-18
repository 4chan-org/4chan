package storage

import (
	"bytes"
	"context"
	"crypto/md5"
	"encoding/hex"
	"fmt"
	"io"
	"net/url"
	"path/filepath"
	"time"

	"github.com/4chan/v2/backend_go/config"
	"github.com/minio/minio-go/v7"
	"github.com/minio/minio-go/v7/pkg/credentials"
)

// MinioClient represents a MinIO (S3) client
type MinioClient struct {
	client     *minio.Client
	bucketName string
}

// FileInfo contains information about a stored file
type FileInfo struct {
	FileName     string
	ContentType  string
	Size         int64
	MD5Hash      string
	URL          string
	ThumbnailURL string
}

// NewMinioClient creates a new MinIO client
func NewMinioClient(cfg config.MinioConfig) (*MinioClient, error) {
	// Create MinIO client
	client, err := minio.New(cfg.Endpoint, &minio.Options{
		Creds:  credentials.NewStaticV4(cfg.AccessKey, cfg.SecretKey, ""),
		Secure: cfg.UseSSL,
	})
	if err != nil {
		return nil, fmt.Errorf("failed to create MinIO client: %w", err)
	}

	// Check if bucket exists
	exists, err := client.BucketExists(context.Background(), cfg.Bucket)
	if err != nil {
		return nil, fmt.Errorf("failed to check if bucket exists: %w", err)
	}

	// Create bucket if it doesn't exist
	if !exists {
		if err := client.MakeBucket(context.Background(), cfg.Bucket, minio.MakeBucketOptions{}); err != nil {
			return nil, fmt.Errorf("failed to create bucket: %w", err)
		}

		// Set bucket policy to allow public read access
		policy := `{
			"Version": "2012-10-17",
			"Statement": [
				{
					"Effect": "Allow",
					"Principal": {"AWS": ["*"]},
					"Action": ["s3:GetObject"],
					"Resource": ["arn:aws:s3:::` + cfg.Bucket + `/*"]
				}
			]
		}`

		if err := client.SetBucketPolicy(context.Background(), cfg.Bucket, policy); err != nil {
			return nil, fmt.Errorf("failed to set bucket policy: %w", err)
		}
	}

	return &MinioClient{
		client:     client,
		bucketName: cfg.Bucket,
	}, nil
}

// UploadFile uploads a file to the storage
func (m *MinioClient) UploadFile(ctx context.Context, fileData []byte, fileName, contentType string) (*FileInfo, error) {
	// Generate unique file name
	timestamp := time.Now().Unix()
	uniqueFileName := fmt.Sprintf("%d_%s", timestamp, fileName)
	
	// Calculate MD5 hash
	hash := md5.Sum(fileData)
	md5Hash := hex.EncodeToString(hash[:])
	
	// Upload file
	_, err := m.client.PutObject(
		ctx,
		m.bucketName,
		uniqueFileName,
		bytes.NewReader(fileData),
		int64(len(fileData)),
		minio.PutObjectOptions{
			ContentType: contentType,
			UserMetadata: map[string]string{
				"md5sum": md5Hash,
			},
		},
	)
	if err != nil {
		return nil, fmt.Errorf("failed to upload file: %w", err)
	}
	
	// Generate URL
	presignedURL, err := m.client.PresignedGetObject(
		ctx,
		m.bucketName,
		uniqueFileName,
		time.Hour*24*7, // URL valid for 7 days
		url.Values{},
	)
	if err != nil {
		return nil, fmt.Errorf("failed to generate URL: %w", err)
	}
	
	// For this example, we'll assume thumbnails have the same URL
	// In a real application, you would generate a separate thumbnail
	
	return &FileInfo{
		FileName:     uniqueFileName,
		ContentType:  contentType,
		Size:         int64(len(fileData)),
		MD5Hash:      md5Hash,
		URL:          presignedURL.String(),
		ThumbnailURL: presignedURL.String(), // In reality, this would be different
	}, nil
}

// GenerateThumbnail creates a thumbnail for an image
// In a real application, you would implement actual thumbnail generation
func (m *MinioClient) GenerateThumbnail(ctx context.Context, fileData []byte, fileName, contentType string) ([]byte, error) {
	// In a real application, you would use an image processing library
	// to resize the image and create a thumbnail
	return fileData, nil
}

// DeleteFile deletes a file from storage
func (m *MinioClient) DeleteFile(ctx context.Context, fileName string) error {
	err := m.client.RemoveObject(ctx, m.bucketName, fileName, minio.RemoveObjectOptions{})
	if err != nil {
		return fmt.Errorf("failed to delete file: %w", err)
	}
	
	// Also delete thumbnail if it exists
	thumbnailName := "thumb_" + fileName
	_ = m.client.RemoveObject(ctx, m.bucketName, thumbnailName, minio.RemoveObjectOptions{})
	
	return nil
}

// GetFile gets a file from storage
func (m *MinioClient) GetFile(ctx context.Context, fileName string) ([]byte, error) {
	object, err := m.client.GetObject(ctx, m.bucketName, fileName, minio.GetObjectOptions{})
	if err != nil {
		return nil, fmt.Errorf("failed to get file: %w", err)
	}
	defer object.Close()
	
	return io.ReadAll(object)
}

// GetFileURL gets a presigned URL for a file
func (m *MinioClient) GetFileURL(ctx context.Context, fileName string, expiry time.Duration) (string, error) {
	presignedURL, err := m.client.PresignedGetObject(
		ctx,
		m.bucketName,
		fileName,
		expiry,
		url.Values{},
	)
	if err != nil {
		return "", fmt.Errorf("failed to generate URL: %w", err)
	}
	
	return presignedURL.String(), nil
}

// IsImage checks if the file is an image based on its extension
func IsImage(fileName string) bool {
	ext := filepath.Ext(fileName)
	switch ext {
	case ".jpg", ".jpeg", ".png", ".gif", ".webp":
		return true
	default:
		return false
	}
}