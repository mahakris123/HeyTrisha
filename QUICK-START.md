# 🚀 Quick Start Guide

## For API Server Admin (You)

### 1. Deploy API Server

```bash
# Upload heytrisha-api-server-v1.0-*.zip to your server
# Extract to: /var/www/api.heytrisha.com/

cd /var/www/api.heytrisha.com/
composer install --no-dev
cp .env.example .env
php artisan key:generate
```

### 2. Configure Database

```sql
CREATE TABLE api_keys (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    api_key VARCHAR(255) UNIQUE NOT NULL,
    site_url VARCHAR(255) NOT NULL,
    user_email VARCHAR(255),
    status ENUM('active', 'suspended') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_api_key (api_key)
);
```

### 3. Set Up API Key Generation

Users sign up → You generate API key → User gets key

**API Endpoint:** `POST /api-keys/generate`
```json
{
  "site_url": "https://example.com",
  "user_email": "user@example.com"
}
```

**Response:**
```json
{
  "success": true,
  "api_key": "ht_abc123xyz...",
  "message": "API key generated"
}
```

### 4. Protect Routes with API Key

All `/api/*` routes require `Authorization: Bearer {API_KEY}` header.

---

## For WordPress Users

### 1. Get API Key

- Visit `https://heytrisha.com/signup`
- Enter WordPress site URL
- Receive API key via email

### 2. Install Plugin

- Download `heytrisha-woo-plugin-v1.0-*.zip`
- WordPress Admin → Plugins → Add New → Upload
- Activate plugin

### 3. Configure Plugin

- Go to: **Hey Trisha → Settings**
- Enter:
  - **API URL:** `https://api.heytrisha.com`
  - **API Key:** `ht_abc123xyz...` (from Step 1)
- Click **Save Changes**

### 4. Start Using

- Open **Hey Trisha → New Chat**
- Type your question
- Get instant AI-powered responses!

---

## How It Works

```
User Query
    ↓
WordPress Plugin (sends to API server)
    ↓
API Server (validates API key, processes query)
    ↓
API Server (calls back to user's WordPress site)
    ↓
WordPress Site (executes query, returns data)
    ↓
API Server (formats response)
    ↓
WordPress Plugin (displays to user)
```

---

## Security

- ✅ Each WordPress site has unique API key
- ✅ API key authenticates all requests
- ✅ Site URL ensures correct callback
- ✅ No data mixing between users

---

## Troubleshooting

**Plugin says "API not configured"**
→ Check API URL and API key in settings

**"Invalid API key" error**
→ Verify API key is active in database

**"Failed to connect" error**
→ Check API server is running and accessible

**No response**
→ Check API server logs for errors


