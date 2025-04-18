use actix_web::{web, HttpResponse, HttpRequest, Error, error};
use actix_multipart::Multipart;
use chrono::Utc;
use futures::{StreamExt, TryStreamExt};
use serde_json::json;
use uuid::Uuid;
use std::collections::HashMap;
use std::io::Read;
use std::time::Instant;

use crate::config::Config;
use crate::models::{
    File, 
    FileUploadResponse,
    FileCheckRequest,
    FileCheckResponse,
    BannedHashesResponse,
    FileStats,
    FilePurgeRequest,
    FilePurgeResponse
};
use crate::repositories::s3_repository::S3Repository;
use crate::error::AppError;

const MAX_FILE_SIZE: usize = 10 * 1024 * 1024; // 10MB

/// Upload a file
/// 
/// This endpoint allows users to upload a file to be attached to a post.
pub async fn upload_file(
    req: HttpRequest,
    mut payload: Multipart,
    s3_repo: web::Data<S3Repository>,
    config: web::Data<Config>
) -> Result<HttpResponse, Error> {
    let start_time = Instant::now();
    
    // Fields to collect
    let mut file_data: Option<Vec<u8>> = None;
    let mut filename: Option<String> = None;
    let mut content_type: Option<String> = None;
    let mut is_spoiler = false;
    
    // Process multipart form
    while let Ok(Some(mut field)) = payload.try_next().await {
        let content_disposition = field.content_disposition().ok_or_else(|| {
            error::ErrorBadRequest("Content disposition is missing")
        })?;
        
        let field_name = content_disposition.get_name().ok_or_else(|| {
            error::ErrorBadRequest("Field name is missing")
        })?.to_string();
        
        match field_name.as_str() {
            "file" => {
                // Get filename
                filename = content_disposition.get_filename().map(|s| s.to_string());
                
                // Get content type
                content_type = field.content_type().map(|ct| ct.to_string());
                
                // Read file data
                let mut data = Vec::new();
                while let Some(chunk) = field.next().await {
                    let chunk = chunk?;
                    // Check size limit
                    if data.len() + chunk.len() > MAX_FILE_SIZE {
                        return Err(error::ErrorPayloadTooLarge("File too large").into());
                    }
                    data.extend_from_slice(&chunk);
                }
                
                if data.is_empty() {
                    return Err(error::ErrorBadRequest("Empty file").into());
                }
                
                file_data = Some(data);
            },
            "spoiler" => {
                let mut value = String::new();
                while let Some(chunk) = field.next().await {
                    let chunk = chunk?;
                    value.extend(std::str::from_utf8(&chunk).unwrap_or_default().chars());
                }
                is_spoiler = value.trim().to_lowercase() == "true";
            },
            _ => {
                // Skip other fields
                while let Some(_) = field.next().await {
                    // Consume field
                }
            }
        }
    }
    
    // Validate required fields
    let file_data = file_data.ok_or_else(|| error::ErrorBadRequest("File is required"))?;
    let filename = filename.ok_or_else(|| error::ErrorBadRequest("Filename is missing"))?;
    
    // Detect content type if not provided
    let content_type = content_type.unwrap_or_else(|| "application/octet-stream".to_string());
    
    // Generate a unique ID
    let file_id = Uuid::new_v4();
    
    // Calculate hashes
    let md5_hash = format!("{:x}", md5::compute(&file_data));
    
    // Check file type (use infer crate in a real implementation)
    // For now we'll just trust the content type from the request
    
    // Get image dimensions (use image crate in a real implementation)
    let (width, height) = (None, None);
    
    // Upload to S3
    let unique_filename = format!("{}_{}", Utc::now().timestamp(), filename);
    let (file_url, thumbnail_url) = match s3_repo.upload_file(&file_id, &file_data, &unique_filename, &content_type).await {
        Ok((file_url, thumb_url)) => (file_url, thumb_url),
        Err(e) => return Err(error::ErrorInternalServerError(format!("Failed to upload file: {}", e)).into()),
    };
    
    // Calculate upload duration
    let upload_duration = start_time.elapsed().as_millis() as i32;
    
    // Create response
    let response = FileUploadResponse {
        id: file_id,
        file_url,
        thumbnail_url,
        filename,
        filesize: file_data.len() as i64,
        width,
        height,
        mime_type: content_type,
        md5_hash,
        is_spoilered: is_spoiler,
        upload_duration: Some(upload_duration),
    };
    
    Ok(HttpResponse::Created().json(response))
}

/// Get file information
/// 
/// This endpoint retrieves metadata for a specific file.
pub async fn get_file_info(
    path: web::Path<String>,
) -> Result<HttpResponse, Error> {
    let file_id = path.into_inner();
    
    // Parse UUID
    let file_id = match Uuid::parse_str(&file_id) {
        Ok(id) => id,
        Err(_) => return Err(error::ErrorBadRequest("Invalid file ID").into()),
    };
    
    // In a real implementation, you would query the database
    // For now, return a not found error
    Err(error::ErrorNotFound("File not found").into())
}

/// Delete a file
/// 
/// This endpoint deletes a file from the system.
pub async fn delete_file(
    req: HttpRequest,
    path: web::Path<String>,
    s3_repo: web::Data<S3Repository>,
) -> Result<HttpResponse, Error> {
    let file_id = path.into_inner();
    
    // Parse UUID
    let file_id = match Uuid::parse_str(&file_id) {
        Ok(id) => id,
        Err(_) => return Err(error::ErrorBadRequest("Invalid file ID").into()),
    };
    
    // In a real implementation, you would delete from database and storage
    // For now, return a not found error
    Err(error::ErrorNotFound("File not found").into())
}

/// Get file content
/// 
/// This endpoint downloads the binary content of a file.
pub async fn get_file_content(
    req: HttpRequest,
    path: web::Path<String>,
    query: web::Query<HashMap<String, String>>,
    s3_repo: web::Data<S3Repository>,
) -> Result<HttpResponse, Error> {
    let file_id = path.into_inner();
    
    // Parse UUID
    let file_id = match Uuid::parse_str(&file_id) {
        Ok(id) => id,
        Err(_) => return Err(error::ErrorBadRequest("Invalid file ID").into()),
    };
    
    // Check if download parameter is present
    let download = query.get("download")
        .map(|v| v.to_lowercase() == "true")
        .unwrap_or(false);
    
    // In a real implementation, you would retrieve from database and storage
    // For now, return a not found error
    Err(error::ErrorNotFound("File not found").into())
}

/// Get file thumbnail
/// 
/// This endpoint retrieves the thumbnail for a file.
pub async fn get_thumbnail(
    req: HttpRequest,
    path: web::Path<String>,
    query: web::Query<HashMap<String, String>>,
    s3_repo: web::Data<S3Repository>,
) -> Result<HttpResponse, Error> {
    let file_id = path.into_inner();
    
    // Parse UUID
    let file_id = match Uuid::parse_str(&file_id) {
        Ok(id) => id,
        Err(_) => return Err(error::ErrorBadRequest("Invalid file ID").into()),
    };
    
    // Get size parameter
    let size = query.get("size")
        .map(|s| s.as_str())
        .unwrap_or("medium");
    
    // Validate size
    if !["small", "medium", "large"].contains(&size) {
        return Err(error::ErrorBadRequest("Invalid thumbnail size").into());
    }
    
    // In a real implementation, you would retrieve from database and storage
    // For now, return a not found error
    Err(error::ErrorNotFound("Thumbnail not found").into())
}

/// Check if a file exists
/// 
/// This endpoint checks if a file with a specific MD5 hash already exists in the system.
pub async fn check_file_exists(
    req: HttpRequest,
    request: web::Json<FileCheckRequest>,
) -> Result<HttpResponse, Error> {
    // Validate MD5 hash
    if request.md5_hash.is_empty() {
        return Err(error::ErrorBadRequest("MD5 hash is required").into());
    }
    
    // In a real implementation, you would check the database
    // For now, always return that it doesn't exist
    let response = FileCheckResponse {
        exists: false,
        file: None,
    };
    
    Ok(HttpResponse::Ok().json(response))
}

/// Get banned file hashes
/// 
/// This endpoint retrieves a list of MD5 hashes of files that are banned from being uploaded.
pub async fn get_banned_hashes(
    req: HttpRequest,
) -> Result<HttpResponse, Error> {
    // In a real implementation, you would retrieve from database
    // For now, return an empty list
    let response = BannedHashesResponse {
        data: Vec::new(),
        updated_at: Utc::now(),
    };
    
    Ok(HttpResponse::Ok().json(response))
}

/// Get file statistics
/// 
/// This endpoint retrieves statistics about files stored in the system.
pub async fn get_file_stats(
    req: HttpRequest,
) -> Result<HttpResponse, Error> {
    // In a real implementation, you would calculate from database
    // For now, return dummy data
    let mut files_by_type = HashMap::new();
    files_by_type.insert("image/jpeg".to_string(), 500000);
    files_by_type.insert("image/png".to_string(), 300000);
    files_by_type.insert("image/gif".to_string(), 150000);
    files_by_type.insert("video/mp4".to_string(), 50000);
    
    let response = FileStats {
        total_files: 1000000,
        total_size: 5368709120, // 5GB
        files_by_type,
        average_file_size: 5368709, // ~5MB
        files_last_day: 10000,
        files_last_week: 70000,
    };
    
    Ok(HttpResponse::Ok().json(response))
}

/// Purge old files
/// 
/// This endpoint starts a task to purge old files based on specified criteria.
pub async fn purge_files(
    req: HttpRequest,
    request: web::Json<FilePurgeRequest>,
) -> Result<HttpResponse, Error> {
    // Validate request
    if request.older_than_days < 30 {
        return Err(error::ErrorBadRequest("olderThanDays must be at least 30").into());
    }
    
    // In a real implementation, you would start an async job
    // For now, return a dummy response
    let response = FilePurgeResponse {
        task_id: Uuid::new_v4(),
        estimated_files_to_purge: 50000,
        estimated_space_to_free: 268435456, // 256MB
    };
    
    Ok(HttpResponse::Accepted().json(response))
}