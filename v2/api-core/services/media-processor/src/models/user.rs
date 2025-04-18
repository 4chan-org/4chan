use chrono::{DateTime, Utc};
use serde::{Deserialize, Serialize};
use sqlx::postgres::PgPool;
use uuid::Uuid;
use secrecy::{Secret, ExposeSecret};

#[derive(Debug, Serialize, Deserialize, Clone, PartialEq, sqlx::Type)]
#[sqlx(type_name = "role", rename_all = "UPPERCASE")]
pub enum Role {
    User,
    Janitor,
    Moderator,
    Admin,
}

impl std::fmt::Display for Role {
    fn fmt(&self, f: &mut std::fmt::Formatter<'_>) -> std::fmt::Result {
        match self {
            Role::User => write!(f, "USER"),
            Role::Janitor => write!(f, "JANITOR"),
            Role::Moderator => write!(f, "MODERATOR"),
            Role::Admin => write!(f, "ADMIN"),
        }
    }
}

#[derive(Debug, Serialize, Deserialize, Clone)]
pub struct User {
    pub id: Uuid,
    pub username: String,
    pub email: String,
    #[serde(skip_serializing)]
    pub password_hash: String,
    pub role: Role,
    pub is_active: bool,
    pub is_banned: bool,
    pub two_factor_auth: bool,
    #[serde(skip_serializing)]
    pub two_factor_secret: Option<String>,
    pub created_at: DateTime<Utc>,
    pub updated_at: DateTime<Utc>,
    pub last_login_at: Option<DateTime<Utc>>,
}

#[derive(Debug, Serialize, Deserialize)]
pub struct CreateUserDto {
    pub username: String,
    pub email: String,
    pub password: String,
    pub role: Role,
}

#[derive(Debug, Serialize, Deserialize)]
pub struct UpdateUserDto {
    pub username: Option<String>,
    pub email: Option<String>,
    pub password: Option<String>,
    pub role: Option<Role>,
    pub is_active: Option<bool>,
    pub is_banned: Option<bool>,
    pub two_factor_auth: Option<bool>,
}

#[derive(Debug, Serialize, Deserialize)]
pub struct LoginUserDto {
    pub username: String,
    pub password: String,
}

#[derive(Debug, Serialize, Deserialize)]
pub struct LoginResponse {
    pub access_token: String,
    pub refresh_token: String,
    pub user: UserSafe,
}

#[derive(Debug, Serialize, Deserialize, Clone)]
pub struct UserSafe {
    pub id: Uuid,
    pub username: String,
    pub role: Role,
    pub is_active: bool,
    pub created_at: DateTime<Utc>,
    pub last_login_at: Option<DateTime<Utc>>,
}

impl From<User> for UserSafe {
    fn from(user: User) -> Self {
        Self {
            id: user.id,
            username: user.username,
            role: user.role,
            is_active: user.is_active,
            created_at: user.created_at,
            last_login_at: user.last_login_at,
        }
    }
}

#[derive(Debug, Serialize, Deserialize)]
pub struct RefreshTokenRequest {
    pub refresh_token: String,
}

#[derive(Debug, Serialize, Deserialize)]
pub struct RefreshToken {
    pub id: Uuid,
    pub token: String,
    pub user_id: Uuid,
    pub expires_at: DateTime<Utc>,
    pub created_at: DateTime<Utc>,
}

impl User {
    pub async fn find_by_id(pool: &PgPool, id: Uuid) -> Result<Option<Self>, sqlx::Error> {
        sqlx::query_as!(
            User,
            r#"
            SELECT 
                id,
                username,
                email,
                password_hash,
                role as "role: Role",
                is_active,
                is_banned,
                two_factor_auth,
                two_factor_secret,
                created_at,
                updated_at,
                last_login_at
            FROM users
            WHERE id = $1
            "#,
            id
        )
        .fetch_optional(pool)
        .await
    }

    pub async fn find_by_username(pool: &PgPool, username: &str) -> Result<Option<Self>, sqlx::Error> {
        sqlx::query_as!(
            User,
            r#"
            SELECT 
                id,
                username,
                email,
                password_hash,
                role as "role: Role",
                is_active,
                is_banned,
                two_factor_auth,
                two_factor_secret,
                created_at,
                updated_at,
                last_login_at
            FROM users
            WHERE username = $1
            "#,
            username
        )
        .fetch_optional(pool)
        .await
    }

    pub async fn create(pool: &PgPool, user: CreateUserDto, password_hash: String) -> Result<Self, sqlx::Error> {
        sqlx::query_as!(
            User,
            r#"
            INSERT INTO users (
                username,
                email,
                password_hash,
                role
            )
            VALUES ($1, $2, $3, $4)
            RETURNING 
                id,
                username,
                email,
                password_hash,
                role as "role: Role",
                is_active,
                is_banned,
                two_factor_auth,
                two_factor_secret,
                created_at,
                updated_at,
                last_login_at
            "#,
            user.username,
            user.email,
            password_hash,
            user.role as Role
        )
        .fetch_one(pool)
        .await
    }

    pub async fn update_last_login(pool: &PgPool, id: Uuid) -> Result<(), sqlx::Error> {
        sqlx::query!(
            r#"
            UPDATE users
            SET last_login_at = NOW(), updated_at = NOW()
            WHERE id = $1
            "#,
            id
        )
        .execute(pool)
        .await?;

        Ok(())
    }
}

impl RefreshToken {
    pub async fn create(pool: &PgPool, user_id: Uuid, token: String, expiry: DateTime<Utc>) -> Result<Self, sqlx::Error> {
        sqlx::query_as!(
            RefreshToken,
            r#"
            INSERT INTO refresh_tokens (
                user_id,
                token,
                expires_at
            )
            VALUES ($1, $2, $3)
            RETURNING id, token, user_id, expires_at, created_at
            "#,
            user_id,
            token,
            expiry
        )
        .fetch_one(pool)
        .await
    }

    pub async fn find_by_token(pool: &PgPool, token: &str) -> Result<Option<Self>, sqlx::Error> {
        sqlx::query_as!(
            RefreshToken,
            r#"
            SELECT id, token, user_id, expires_at, created_at
            FROM refresh_tokens
            WHERE token = $1 AND expires_at > NOW()
            "#,
            token
        )
        .fetch_optional(pool)
        .await
    }

    pub async fn delete(pool: &PgPool, id: Uuid) -> Result<(), sqlx::Error> {
        sqlx::query!(
            r#"
            DELETE FROM refresh_tokens
            WHERE id = $1
            "#,
            id
        )
        .execute(pool)
        .await?;

        Ok(())
    }

    pub async fn delete_all_for_user(pool: &PgPool, user_id: Uuid) -> Result<(), sqlx::Error> {
        sqlx::query!(
            r#"
            DELETE FROM refresh_tokens
            WHERE user_id = $1
            "#,
            user_id
        )
        .execute(pool)
        .await?;

        Ok(())
    }
}