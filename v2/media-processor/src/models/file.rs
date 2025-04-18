use chrono::{DateTime, Utc};
use serde::{Deserialize, Serialize};
use uuid::Uuid;

/// Represents a file in the system
#[derive(Debug, Clone, Serialize, Deserialize)]
pub struct File {
    /// Unique identifier for the file
    pub id: Uuid,
    
    /// Original filename provided by the user
    pub filename: String,
    
    /// Name as stored in the storage system
    #[serde(skip_serializing_if = "Option::is_none")]
    pub stored_filename: Option<String>,
    
    /// Size of the file in bytes
    pub filesize: i64,
    
    /// Width in pixels (for images)
    #[serde(skip_serializing_if = "Option::is_none")]
    pub width: Option<i32>,
    
    /// Height in pixels (for images)
    #[serde(skip_serializing_if = "Option::is_none")]
    pub height: Option<i32>,
    
    /// Filename of the thumbnail
    #[serde(skip_serializing_if = "Option::is_none")]
    pub thumbnail_filename: Option<String>,
    
    /// MIME type of the file
    pub mime_type: String,
    
    /// MD5 hash of the file content
    pub md5_hash: String,
    
    /// SHA256 hash of the file content
    #[serde(skip_serializing_if = "Option::is_none")]
    pub sha256_hash: Option<String>,
    
    /// Whether the file is marked as a spoiler
    pub is_spoilered: bool,
    
    /// When the file was created
    pub created_at: DateTime<Utc>,
    
    /// ID of the post this file is attached to
    #[serde(skip_serializing_if = "Option::is_none")]
    pub post_id: Option<Uuid>,
    
    /// URL to access the file
    pub file_url: String,
    
    /// URL to access the thumbnail
    pub thumbnail_url: String,
}

/// Response after successfully uploading a file
#[derive(Debug, Serialize, Deserialize)]
pub struct FileUploadResponse {
    /// Unique identifier for the file
    pub id: Uuid,
    
    /// URL to access the file
    pub file_url: String,
    
    /// URL to access the thumbnail
    pub thumbnail_url: String,
    
    /// Original filename provided by the user
    pub filename: String,
    
    /// Size of the file in bytes
    pub filesize: i64,
    
    /// Width in pixels (for images)
    #[serde(skip_serializing_if = "Option::is_none")]
    pub width: Option<i32>,
    
    /// Height in pixels (for images)
    #[serde(skip_serializing_if = "Option::is_none")]
    pub height: Option<i32>,
    
    /// MIME type of the file
    pub mime_type: String,
    
    /// MD5 hash of the file content
    pub md5_hash: String,
    
    /// Whether the file is marked as a spoiler
    pub is_spoilered: bool,
    
    /// Time taken to upload in milliseconds
    #[serde(skip_serializing_if = "Option::is_none")]
    pub upload_duration: Option<i32>,
}

/// Request to check if a file exists by its MD5 hash
#[derive(Debug, Deserialize)]
pub struct FileCheckRequest {
    /// MD5 hash to check
    pub md5_hash: String,
}

/// Response to a file check request
#[derive(Debug, Serialize)]
pub struct FileCheckResponse {
    /// Whether a file with this hash exists
    pub exists: bool,
    
    /// The file details if it exists
    #[serde(skip_serializing_if = "Option::is_none")]
    pub file: Option<File>,
}

/// Response containing banned file hashes
#[derive(Debug, Serialize)]
pub struct BannedHashesResponse {
    /// List of banned MD5 hashes
    pub data: Vec<String>,
    
    /// When the list was last updated
    pub updated_at: DateTime<Utc>,
}

/// Statistics about files in the system
#[derive(Debug, Serialize)]
pub struct FileStats {
    /// Total number of files
    pub total_files: i32,
    
    /// Total size of all files in bytes
    pub total_size: i64,
    
    /// Files grouped by MIME type
    pub files_by_type: std::collections::HashMap<String, i32>,
    
    /// Average file size in bytes
    pub average_file_size: i64,
    
    /// Number of files uploaded in the last day
    pub files_last_day: i32,
    
    /// Number of files uploaded in the last week
    pub files_last_week: i32,
}

/// Request to purge old files
#[derive(Debug, Deserialize)]
pub struct FilePurgeRequest {
    /// Purge files older than this many days
    pub older_than_days: i32,
    
    /// Optional list of MIME types to target
    #[serde(skip_serializing_if = "Option::is_none")]
    pub mime_types: Option<Vec<String>>,
    
    /// Optional list of board IDs to exclude
    #[serde(skip_serializing_if = "Option::is_none")]
    pub except_board_ids: Option<Vec<String>>,
    
    /// Whether to do a dry run (no actual deletion)
    #[serde(default)]
    pub dry_run: bool,
}

/// Response to a file purge request
#[derive(Debug, Serialize)]
pub struct FilePurgeResponse {
    /// ID of the task
    pub task_id: Uuid,
    
    /// Estimated number of files to be purged
    pub estimated_files_to_purge: i32,
    
    /// Estimated space to be freed in bytes
    pub estimated_space_to_free: i64,
}