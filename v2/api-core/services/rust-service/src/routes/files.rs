use actix_web::{web, HttpResponse, Scope};
use actix_multipart::Multipart;
use uuid::Uuid;
use crate::handlers::files::{
    upload_file, 
    get_file_info, 
    delete_file, 
    get_file_content, 
    get_thumbnail, 
    check_file_exists, 
    get_banned_hashes, 
    get_file_stats, 
    purge_files
};
use crate::middleware::{jwt_auth, require_role};

pub fn configure(cfg: &mut web::ServiceConfig) {
    cfg.service(
        web::scope("/files")
            // Public endpoints
            .route("/upload", web::post().to(upload_file))
            .route("/{file_id}", web::get().to(get_file_info))
            .route("/{file_id}/content", web::get().to(get_file_content))
            .route("/{file_id}/thumbnail", web::get().to(get_thumbnail))
            .route("/check", web::post().to(check_file_exists))
            .route("/banned", web::get().to(get_banned_hashes))
            
            // Protected endpoints
            .service(
                web::scope("")
                    .wrap(jwt_auth())
                    .route("/{file_id}", web::delete().to(delete_file))
                    .route("/stats", web::get().to(get_file_stats))
                    .service(
                        web::scope("/admin")
                            .wrap(require_role(vec!["ADMIN".to_string()]))
                            .route("/purge", web::post().to(purge_files))
                    )
            )
    );
}