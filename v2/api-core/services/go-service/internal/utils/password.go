package utils

import (
	"crypto/rand"
	"crypto/subtle"
	"encoding/base64"
	"errors"
	"fmt"
	"strings"

	"golang.org/x/crypto/argon2"
)

// Argon2 parameters
const (
	argon2Time    = 3
	argon2Memory  = 64 * 1024
	argon2Threads = 4
	argon2KeyLen  = 32
	argon2SaltLen = 16
)

// HashPassword hashes a password using Argon2id
func HashPassword(password string) (string, error) {
	// Generate a random salt
	salt := make([]byte, argon2SaltLen)
	if _, err := rand.Read(salt); err != nil {
		return "", fmt.Errorf("failed to generate salt: %w", err)
	}

	// Hash the password
	hash := argon2.IDKey([]byte(password), salt, argon2Time, argon2Memory, argon2Threads, argon2KeyLen)

	// Format the hash: $argon2id$v=19$m=65536,t=3,p=4$salt$hash
	encodedSalt := base64.RawStdEncoding.EncodeToString(salt)
	encodedHash := base64.RawStdEncoding.EncodeToString(hash)
	
	result := fmt.Sprintf("$argon2id$v=19$m=%d,t=%d,p=%d$%s$%s",
		argon2Memory, argon2Time, argon2Threads, encodedSalt, encodedHash)
	
	return result, nil
}

// VerifyPassword checks if a password matches a hash
func VerifyPassword(password, encodedHash string) (bool, error) {
	// Parse the hash
	params, salt, hash, err := parseEncodedHash(encodedHash)
	if err != nil {
		return false, fmt.Errorf("failed to parse hash: %w", err)
	}

	// Hash the password with the same parameters
	otherHash := argon2.IDKey([]byte(password), salt, params.time, params.memory, params.threads, params.keyLen)

	// Compare the hashes using a constant-time comparison
	if subtle.ConstantTimeCompare(hash, otherHash) == 1 {
		return true, nil
	}
	
	return false, nil
}

// params contains the parameters used for Argon2id hashing
type params struct {
	memory  uint32
	time    uint32
	threads uint8
	keyLen  uint32
}

// parseEncodedHash parses an Argon2id hash
func parseEncodedHash(encodedHash string) (params, []byte, []byte, error) {
	parts := strings.Split(encodedHash, "$")
	if len(parts) != 6 {
		return params{}, nil, nil, errors.New("invalid hash format")
	}

	var p params
	_, err := fmt.Sscanf(parts[3], "m=%d,t=%d,p=%d", &p.memory, &p.time, &p.threads)
	if err != nil {
		return params{}, nil, nil, fmt.Errorf("failed to parse hash parameters: %w", err)
	}
	p.keyLen = argon2KeyLen

	salt, err := base64.RawStdEncoding.DecodeString(parts[4])
	if err != nil {
		return params{}, nil, nil, fmt.Errorf("failed to decode salt: %w", err)
	}

	hash, err := base64.RawStdEncoding.DecodeString(parts[5])
	if err != nil {
		return params{}, nil, nil, fmt.Errorf("failed to decode hash: %w", err)
	}

	return p, salt, hash, nil
}