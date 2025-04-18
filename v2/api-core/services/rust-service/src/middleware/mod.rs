use actix_web::{
    dev::{forward_ready, Service, ServiceRequest, ServiceResponse, Transform},
    error::ErrorUnauthorized,
    Error, HttpMessage,
};
use futures::future::{ok, LocalBoxFuture, Ready};
use std::future::Future;
use std::pin::Pin;
use std::rc::Rc;
use std::task::{Context, Poll};

// Define middleware for JWT authentication
pub fn jwt_auth() -> JwtAuth {
    JwtAuth::default()
}

// Define middleware for role-based authorization
pub fn require_role(roles: Vec<String>) -> RequireRole {
    RequireRole { roles }
}

#[derive(Default)]
pub struct JwtAuth;

impl<S, B> Transform<S, ServiceRequest> for JwtAuth
where
    S: Service<ServiceRequest, Response = ServiceResponse<B>, Error = Error> + 'static,
    S::Future: 'static,
    B: 'static,
{
    type Response = ServiceResponse<B>;
    type Error = Error;
    type Transform = JwtAuthMiddleware<S>;
    type InitError = ();
    type Future = Ready<Result<Self::Transform, Self::InitError>>;

    fn new_transform(&self, service: S) -> Self::Future {
        ok(JwtAuthMiddleware {
            service: Rc::new(service),
        })
    }
}

pub struct JwtAuthMiddleware<S> {
    service: Rc<S>,
}

impl<S, B> Service<ServiceRequest> for JwtAuthMiddleware<S>
where
    S: Service<ServiceRequest, Response = ServiceResponse<B>, Error = Error> + 'static,
    S::Future: 'static,
    B: 'static,
{
    type Response = ServiceResponse<B>;
    type Error = Error;
    type Future = LocalBoxFuture<'static, Result<Self::Response, Self::Error>>;

    forward_ready!(service);

    fn call(&self, req: ServiceRequest) -> Self::Future {
        let service = Rc::clone(&self.service);

        Box::pin(async move {
            // Extract JWT token from Authorization header
            let auth_header = req
                .headers()
                .get("Authorization")
                .map(|h| h.to_str().unwrap_or_default())
                .unwrap_or_default();

            // Verify token format
            if !auth_header.starts_with("Bearer ") {
                return Err(ErrorUnauthorized("No valid authorization token provided"));
            }

            // Extract the token part
            let token = &auth_header[7..];

            // In a real implementation, validate the token here
            // For this example, we'll just accept any non-empty token
            if token.is_empty() {
                return Err(ErrorUnauthorized("Invalid token"));
            }

            // Add user info to request extensions
            // In a real app, you'd decode the JWT and extract claims
            req.extensions_mut().insert(UserInfo {
                user_id: "dummy_user_id".to_string(),
                role: "USER".to_string(),
            });

            // Call the next service
            service.call(req).await
        })
    }
}

// Role-based authorization middleware
pub struct RequireRole {
    roles: Vec<String>,
}

impl<S, B> Transform<S, ServiceRequest> for RequireRole
where
    S: Service<ServiceRequest, Response = ServiceResponse<B>, Error = Error> + 'static,
    S::Future: 'static,
    B: 'static,
{
    type Response = ServiceResponse<B>;
    type Error = Error;
    type Transform = RequireRoleMiddleware<S>;
    type InitError = ();
    type Future = Ready<Result<Self::Transform, Self::InitError>>;

    fn new_transform(&self, service: S) -> Self::Future {
        ok(RequireRoleMiddleware {
            service: Rc::new(service),
            roles: self.roles.clone(),
        })
    }
}

pub struct RequireRoleMiddleware<S> {
    service: Rc<S>,
    roles: Vec<String>,
}

impl<S, B> Service<ServiceRequest> for RequireRoleMiddleware<S>
where
    S: Service<ServiceRequest, Response = ServiceResponse<B>, Error = Error> + 'static,
    S::Future: 'static,
    B: 'static,
{
    type Response = ServiceResponse<B>;
    type Error = Error;
    type Future = LocalBoxFuture<'static, Result<Self::Response, Self::Error>>;

    forward_ready!(service);

    fn call(&self, req: ServiceRequest) -> Self::Future {
        let service = Rc::clone(&self.service);
        let roles = self.roles.clone();

        Box::pin(async move {
            // Get user info from extensions (added by JWT middleware)
            let user_info = req
                .extensions()
                .get::<UserInfo>()
                .cloned()
                .ok_or_else(|| ErrorUnauthorized("Authentication required"))?;

            // Check if user has the required role
            if !roles.contains(&user_info.role) {
                return Err(ErrorUnauthorized("Insufficient permissions"));
            }

            // Continue with the request
            service.call(req).await
        })
    }
}

// User information extracted from JWT
#[derive(Clone)]
pub struct UserInfo {
    pub user_id: String,
    pub role: String,
}