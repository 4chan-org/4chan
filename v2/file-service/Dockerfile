# Build stage
FROM golang:1.20-alpine AS build

# Set working directory
WORKDIR /app

# Install git and build-essential for dependencies
RUN apk --no-cache add git gcc musl-dev

# Copy go.mod and go.sum files
COPY go.mod go.sum ./

# Download dependencies
RUN go mod download

# Copy source code
COPY . .

# Build the application
RUN CGO_ENABLED=0 GOOS=linux go build -a -installsuffix cgo -o fileservice .

# Production stage
FROM alpine:3.18

# Set working directory
WORKDIR /app

# Install runtime dependencies
RUN apk --no-cache add ca-certificates tzdata

# Create a non-root user to run the application
RUN addgroup -S appgroup && adduser -S appuser -G appgroup

# Create uploads directory with proper permissions
RUN mkdir -p /app/uploads && chown -R appuser:appgroup /app

# Copy binary from build stage
COPY --from=build /app/fileservice /app/fileservice

# Set ownership
RUN chown -R appuser:appgroup /app

# Switch to non-root user
USER appuser

# Expose the application port
EXPOSE 8080

# Health check
HEALTHCHECK --interval=30s --timeout=5s --start-period=5s --retries=3 \
  CMD wget -qO- http://localhost:8080/health || exit 1

# Run the application
CMD ["/app/fileservice"]