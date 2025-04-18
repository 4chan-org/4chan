use aws_sdk_s3::{Client as S3Client, config::Credentials};
use aws_config::meta::region::RegionProviderChain;
use aws_config::BehaviorVersion;
use chrono::Utc;
use std::io::Cursor;
use uuid::Uuid;
use std::sync::Arc;
use tokio::sync::Mutex;

/// Repository for handling file storage in S3-compatible storage
#[derive(Clone)]
pub struct S3Repository {
    client: Arc<S3Client>,
    bucket: String,
}

impl S3Repository {
    /// Create a new S3 repository
    pub async fn new(
        endpoint: &str,
        region: &str,
        access_key: &str, 
        secret_key: &str,
        bucket: &str
    ) -> Result<Self, Box<dyn std::error::Error>> {
        // Set up region provider
        let region_provider = RegionProviderChain::first_try(region.to_string())
            .or_default_provider();
            
        // Set up credentials
        let credentials_provider = Credentials::new(
            access_key,
            secret_key,
            None, 
            None,
            "static-credentials"
        );
        
        // Configure S3 client
        let config = aws_config::defaults(BehaviorVersion::latest())
            .region(region_provider)
            .endpoint_url(endpoint)
            .credentials_provider(credentials_provider)
            .load()
            .await;
            
        let client = S3Client::new(&config);
        
        let repo = Self {
            client: Arc::new(client),
            bucket: bucket.to_string(),
        };
        
        // Ensure bucket exists
        repo.ensure_bucket_exists().await?;
        
        Ok(repo)
    }
    
    /// Ensure the bucket exists, create it if it doesn't
    async fn ensure_bucket_exists(&self) -> Result<(), Box<dyn std::error::Error>> {
        // Check if bucket exists
        let buckets = self.client.list_buckets().send().await?;
        
        let bucket_exists = buckets.buckets()
            .unwrap_or_default()
            .iter()
            .any(|bucket| bucket.name().unwrap_or_default() == self.bucket);
            
        if !bucket_exists {
            // Create bucket
            self.client.create_bucket()
                .bucket(&self.bucket)
                .send()
                .await?;
                
            // Set public read access (in a production system you'd be more careful with this)
            // This is simplified for the example
            let policy = format!(r#"{{
                "Version": "2012-10-17",
                "Statement": [
                    {{
                        "Effect": "Allow",
                        "Principal": "*",
                        "Action": "s3:GetObject",
                        "Resource": "arn:aws:s3:::{}/*"
                    }}
                ]
            }}"#, self.bucket);
            
            self.client.put_bucket_policy()
                .bucket(&self.bucket)
                .policy(policy)
                .send()
                .await?;
        }
        
        Ok(())
    }
    
    /// Upload a file to S3
    pub async fn upload_file(
        &self,
        file_id: &Uuid,
        file_data: &[u8],
        filename: &str,
        content_type: &str
    ) -> Result<(String, String), Box<dyn std::error::Error>> {
        // Create unique filename
        let timestamp = Utc::now().timestamp();
        let unique_filename = format!("{}_{}", timestamp, filename);
        
        // Calculate MD5 (AWS SDK uses base64-encoded MD5 for etag)
        let md5 = md5::compute(file_data);
        let content_md5 = base64::encode(md5.as_ref());
        
        // Upload file
        self.client.put_object()
            .bucket(&self.bucket)
            .key(&unique_filename)
            .body(file_data.to_vec().into())
            .content_type(content_type)
            .content_md5(content_md5)
            .metadata("file-id", &file_id.to_string())
            .send()
            .await?;
            
        // Generate URLs
        // In a real app you'd use proper URL generation with signed URLs if needed
        let file_url = format!("https://{}.s3.amazonaws.com/{}", self.bucket, unique_filename);
        
        // In a real app, you'd generate an actual thumbnail and upload it separately
        // For this example, we'll just use the same URL
        let thumbnail_url = file_url.clone();
        
        Ok((file_url, thumbnail_url))
    }
    
    /// Delete a file from S3
    pub async fn delete_file(&self, filename: &str) -> Result<(), Box<dyn std::error::Error>> {
        // Delete object
        self.client.delete_object()
            .bucket(&self.bucket)
            .key(filename)
            .send()
            .await?;
            
        // Also delete thumbnail if it exists
        let thumbnail_name = format!("thumb_{}", filename);
        let _ = self.client.delete_object()
            .bucket(&self.bucket)
            .key(&thumbnail_name)
            .send()
            .await;
            
        Ok(())
    }
    
    /// Get a file from S3
    pub async fn get_file(&self, filename: &str) -> Result<Vec<u8>, Box<dyn std::error::Error>> {
        // Get object
        let response = self.client.get_object()
            .bucket(&self.bucket)
            .key(filename)
            .send()
            .await?;
            
        // Read the body
        let data = response.body.collect().await?;
        let bytes = data.into_bytes();
        
        Ok(bytes.to_vec())
    }
    
    /// Get a presigned URL for a file
    pub async fn get_presigned_url(
        &self,
        filename: &str,
        expiry_secs: u64
    ) -> Result<String, Box<dyn std::error::Error>> {
        // Create presigner
        let presigning_config = aws_sdk_s3::presigning::config::PresigningConfig::builder()
            .expires_in(std::time::Duration::from_secs(expiry_secs))
            .build()?;
            
        // Generate presigned URL
        let presigned_request = self.client.get_object()
            .bucket(&self.bucket)
            .key(filename)
            .presigned(presigning_config)
            .await?;
            
        Ok(presigned_request.uri().to_string())
    }
}