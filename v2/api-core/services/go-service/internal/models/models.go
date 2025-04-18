package models

import (
	"time"
)

// User represents a user in the system
type User struct {
	ID             string     `json:"id" db:"id"`
	Username       string     `json:"username" db:"username"`
	Email          string     `json:"email" db:"email"`
	PasswordHash   string     `json:"-" db:"password_hash"`
	Role           string     `json:"role" db:"role"`
	IsActive       bool       `json:"isActive" db:"is_active"`
	IsBanned       bool       `json:"isBanned" db:"is_banned"`
	TwoFactorAuth  bool       `json:"twoFactorAuth" db:"two_factor_auth"`
	TwoFactorSecret *string    `json:"-" db:"two_factor_secret"`
	CreatedAt      time.Time  `json:"createdAt" db:"created_at"`
	UpdatedAt      time.Time  `json:"updatedAt" db:"updated_at"`
	LastLoginAt    *time.Time `json:"lastLoginAt" db:"last_login_at"`
}

// RefreshToken represents a refresh token for JWT authentication
type RefreshToken struct {
	ID        string    `json:"id" db:"id"`
	Token     string    `json:"token" db:"token"`
	UserID    string    `json:"userId" db:"user_id"`
	ExpiresAt time.Time `json:"expiresAt" db:"expires_at"`
	CreatedAt time.Time `json:"createdAt" db:"created_at"`
}

// Board represents a board
type Board struct {
	ID                string    `json:"id" db:"id"`
	Name              string    `json:"name" db:"name"`
	Description       *string   `json:"description" db:"description"`
	IsNsfw            bool      `json:"isNsfw" db:"is_nsfw"`
	IsArchived        bool      `json:"isArchived" db:"is_archived"`
	MaxThreads        int       `json:"maxThreads" db:"max_threads"`
	MaxReportsPerHour int       `json:"maxReportsPerHour" db:"max_reports_per_hour"`
	BumpLimit         int       `json:"bumpLimit" db:"bump_limit"`
	CooldownThreads   int       `json:"cooldownThreads" db:"cooldown_threads"`
	CooldownReplies   int       `json:"cooldownReplies" db:"cooldown_replies"`
	CreatedAt         time.Time `json:"createdAt" db:"created_at"`
	UpdatedAt         time.Time `json:"updatedAt" db:"updated_at"`
	CategoryID        string    `json:"categoryId" db:"category_id"`
}

// BoardWithCategory includes the category details
type BoardWithCategory struct {
	Board
	Category Category `json:"category"`
}

// Category represents a board category
type Category struct {
	ID          string    `json:"id" db:"id"`
	Name        string    `json:"name" db:"name"`
	Description *string   `json:"description" db:"description"`
	Order       int       `json:"order" db:"order"`
	CreatedAt   time.Time `json:"createdAt" db:"created_at"`
	UpdatedAt   time.Time `json:"updatedAt" db:"updated_at"`
}

// ModeratorBoard represents the assignment of a moderator to a board
type ModeratorBoard struct {
	ID        string    `json:"id" db:"id"`
	UserID    string    `json:"userId" db:"user_id"`
	BoardID   string    `json:"boardId" db:"board_id"`
	CreatedAt time.Time `json:"createdAt" db:"created_at"`
}

// Thread represents a thread
type Thread struct {
	ID         string     `json:"id" db:"id"`
	Subject    *string    `json:"subject" db:"subject"`
	IsSticky   bool       `json:"isSticky" db:"is_sticky"`
	IsLocked   bool       `json:"isLocked" db:"is_locked"`
	IsCyclic   bool       `json:"isCyclic" db:"is_cyclic"`
	CycleLimit *int       `json:"cycleLimit" db:"cycle_limit"`
	BumpedAt   time.Time  `json:"bumpedAt" db:"bumped_at"`
	CreatedAt  time.Time  `json:"createdAt" db:"created_at"`
	UpdatedAt  time.Time  `json:"updatedAt" db:"updated_at"`
	IPHash     *string    `json:"-" db:"ip_hash"`
	BoardID    string     `json:"boardId" db:"board_id"`
}

// ThreadWithStats includes additional statistics
type ThreadWithStats struct {
	Thread
	ReplyCount  int  `json:"replyCount"`
	FileCount   int  `json:"fileCount"`
	OP          Post `json:"op"`
	Board       Board `json:"board,omitempty"`
	LastReplies []Post `json:"lastReplies,omitempty"`
}

// Post represents a post in a thread
type Post struct {
	ID          string     `json:"id" db:"id"`
	PostNumber  int        `json:"postNumber" db:"post_number"`
	Name        *string    `json:"name" db:"name"`
	Tripcode    *string    `json:"tripcode" db:"tripcode"`
	Message     *string    `json:"message" db:"message"`
	IPHash      *string    `json:"-" db:"ip_hash"`
	IsDeleted   bool       `json:"isDeleted" db:"is_deleted"`
	IsSpoilered bool       `json:"isSpoilered" db:"is_spoilered"`
	CreatedAt   time.Time  `json:"createdAt" db:"created_at"`
	UpdatedAt   time.Time  `json:"updatedAt" db:"updated_at"`
	ThreadID    string     `json:"threadId" db:"thread_id"`
	Files       []File     `json:"files,omitempty"`
}

// File represents a file attached to a post
type File struct {
	ID               string    `json:"id" db:"id"`
	Filename         string    `json:"filename" db:"filename"`
	StoredFilename   string    `json:"storedFilename" db:"stored_filename"`
	Filesize         int       `json:"filesize" db:"filesize"`
	Width            *int      `json:"width" db:"width"`
	Height           *int      `json:"height" db:"height"`
	ThumbnailFilename string   `json:"thumbnailFilename" db:"thumbnail_filename"`
	MimeType         string    `json:"mimeType" db:"mime_type"`
	MD5Hash          string    `json:"md5Hash" db:"md5_hash"`
	SHA256Hash       string    `json:"sha256Hash" db:"sha256_hash"`
	IsSpoilered      bool      `json:"isSpoilered" db:"is_spoilered"`
	CreatedAt        time.Time `json:"createdAt" db:"created_at"`
	PostID           string    `json:"postId" db:"post_id"`
	URL              string    `json:"url,omitempty" db:"-"`
	ThumbnailURL     string    `json:"thumbnailUrl,omitempty" db:"-"`
}

// Report represents a report of a post or thread
type Report struct {
	ID            string     `json:"id" db:"id"`
	Reason        string     `json:"reason" db:"reason"`
	AdditionalInfo *string    `json:"additionalInfo" db:"additional_info"`
	IPHash        string     `json:"-" db:"ip_hash"`
	IsResolved    bool       `json:"isResolved" db:"is_resolved"`
	ResolvedBy    *string    `json:"resolvedBy" db:"resolved_by"`
	CreatedAt     time.Time  `json:"createdAt" db:"created_at"`
	ResolvedAt    *time.Time `json:"resolvedAt" db:"resolved_at"`
	PostID        *string    `json:"postId" db:"post_id"`
	ThreadID      *string    `json:"threadId" db:"thread_id"`
}

// ReportWithDetails includes additional details about the report
type ReportWithDetails struct {
	Report
	Post    *Post    `json:"post,omitempty"`
	Thread  *Thread  `json:"thread,omitempty"`
	BoardID string   `json:"boardId"`
}

// AuditLog represents an audit log entry
type AuditLog struct {
	ID         string    `json:"id" db:"id"`
	Action     string    `json:"action" db:"action"`
	EntityType string    `json:"entityType" db:"entity_type"`
	EntityID   string    `json:"entityId" db:"entity_id"`
	Details    []byte    `json:"details" db:"details"`
	IPAddress  *string   `json:"-" db:"ip_address"`
	CreatedAt  time.Time `json:"createdAt" db:"created_at"`
	UserID     *string   `json:"userId" db:"user_id"`
}

// Ban represents a ban
type Ban struct {
	ID           string     `json:"id" db:"id"`
	IPHash       string     `json:"-" db:"ip_hash"`
	Reason       *string    `json:"reason" db:"reason"`
	ExpiresAt    *time.Time `json:"expiresAt" db:"expires_at"`
	IsActive     bool       `json:"isActive" db:"is_active"`
	CreatedAt    time.Time  `json:"createdAt" db:"created_at"`
	CreatedBy    string     `json:"createdBy" db:"created_by"`
	BoardID      *string    `json:"boardId" db:"board_id"`
	AppealReason *string    `json:"appealReason" db:"appeal_reason"`
	AppealStatus string     `json:"appealStatus" db:"appeal_status"`
}

// WordFilter represents a word filter
type WordFilter struct {
	ID          string    `json:"id" db:"id"`
	Pattern     string    `json:"pattern" db:"pattern"`
	Replacement string    `json:"replacement" db:"replacement"`
	IsRegex     bool      `json:"isRegex" db:"is_regex"`
	IsActive    bool      `json:"isActive" db:"is_active"`
	CreatedAt   time.Time `json:"createdAt" db:"created_at"`
	UpdatedAt   time.Time `json:"updatedAt" db:"updated_at"`
	BoardID     *string   `json:"boardId" db:"board_id"`
}

// Captcha represents a CAPTCHA
type Captcha struct {
	ID        string    `json:"id" db:"id"`
	Solution  string    `json:"-" db:"solution"`
	ExpiresAt time.Time `json:"expiresAt" db:"expires_at"`
	CreatedAt time.Time `json:"createdAt" db:"created_at"`
	IPHash    string    `json:"-" db:"ip_hash"`
	IsUsed    bool      `json:"isUsed" db:"is_used"`
	ImageURL  string    `json:"imageUrl" db:"-"`
}