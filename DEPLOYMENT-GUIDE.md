# 🚀 Deployment Guide - New Secure Architecture

## Overview

This guide covers deploying the new secure architecture where:
- WordPress plugin handles all database operations
- API server only stores OpenAI keys (encrypted)
- No database credentials leave WordPress

## Prerequisites

- PHP 8.1+ on API server
- MySQL/MariaDB database for API server
- WordPress 5.0+ with WooCommerce (optional)
- Composer installed on API server
- SSH access to API server

## Part 1: Deploy API Server

### Step 1: Upload Files

Upload the API server files to your hosting:

```bash
# Upload to: /home/youruser/public_html/api.heytrisha.com/
```

Files to upload:
- `app/`
- `bootstrap/`
- `config/`
- `database/`
- `public/`
- `routes/`
- `storage/`
- `.env.example`
- `artisan`
- `composer.json`
- `composer.lock`

### Step 2: Set Document Root

**CRITICAL:** Point document root to `public/` folder:

```
Document Root: /home/youruser/public_html/api.heytrisha.com/public
```

### Step 3: Install Dependencies

```bash
cd /home/youruser/public_html/api.heytrisha.com
composer install --no-dev --optimize-autoloader
```

### Step 4: Configure .env

```bash
cp .env.example .env
nano .env
```

Configure these settings:

```env
APP_NAME=HeyTrisha
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=https://api.heytrisha.com

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=your_database_name
DB_USERNAME=your_database_user
DB_PASSWORD=your_database_password

LOG_CHANNEL=stack
LOG_LEVEL=error
```

### Step 5: Generate APP_KEY

```bash
php artisan key:generate
```

### Step 6: Run Migrations

```bash
php artisan migrate
```

This creates the `sites` table.

### Step 7: Set Permissions

```bash
chmod -R 755 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
```

### Step 8: Test API Server

```bash
curl https://api.heytrisha.com/api/health
```

Expected response:

```json
{
  "status": "ok",
  "php_version": "8.1.x",
  "laravel_version": "10.x.x",
  "database_connected": true
}
```

## Part 2: Deploy WordPress Plugin

### Step 1: Build Plugin

On your local machine:

```powershell
cd wp-content/plugins/heytrisha-woo
.\build-wp-plugin.ps1
```

This creates: `releases/heytrisha-woo.zip`

### Step 2: Install Plugin

1. Go to WordPress Admin → Plugins → Add New → Upload Plugin
2. Upload `heytrisha-woo.zip`
3. Click "Install Now"
4. Click "Activate"

### Step 3: Complete Onboarding

1. Go to **HeyTrisha → Settings**
2. You'll see the onboarding screen
3. Enter:
   - **OpenAI API Key:** Your sk-... key
   - **Site URL:** Auto-filled (verify it's correct)
   - **Email:** Your email (optional)
   - **API Server URL:** `https://api.heytrisha.com`
4. Click "Register & Activate HeyTrisha"

### Step 4: Verify Registration

After registration, you should see:
- ✅ Success message
- Your unique API key (displayed once)
- Configuration screen

## Part 3: Test End-to-End

### Test 1: WordPress Health Check

```bash
curl https://yoursite.com/wp-json/heytrisha/v1/health
```

Expected:

```json
{
  "success": true,
  "status": "ok",
  "onboarding_complete": true,
  "has_api_key": true,
  "database_connected": true
}
```

### Test 2: Schema Retrieval

```bash
curl -H "X-HeyTrisha-API-Key: ht_your_api_key_here" \
     https://yoursite.com/wp-json/heytrisha/v1/schema
```

### Test 3: SQL Execution

```bash
curl -X POST \
     -H "X-HeyTrisha-API-Key: ht_your_api_key_here" \
     -H "Content-Type: application/json" \
     -d '{"sql":"SELECT ID, post_title FROM wp_posts LIMIT 5"}' \
     https://yoursite.com/wp-json/heytrisha/v1/execute-sql
```

### Test 4: Full Query Flow

```bash
curl -X POST \
     -H "Authorization: Bearer ht_your_api_key_here" \
     -H "Content-Type: application/json" \
     -d '{"question":"Show me the latest 5 products"}' \
     https://api.heytrisha.com/api/query
```

## Troubleshooting

### API Server Issues

**500 Error on /api/health:**
- Check document root points to `public/`
- Run `php artisan config:clear`
- Check Laravel logs: `storage/logs/laravel.log`

**Database connection failed:**
- Verify database credentials in `.env`
- Test connection: `php artisan tinker` then `DB::connection()->getPdo();`

**Sites table doesn't exist:**
- Run migrations: `php artisan migrate`

### WordPress Plugin Issues

**Registration fails:**
- Check API server is accessible
- Verify OpenAI API key is valid
- Check WordPress error log

**SQL execution fails:**
- Check API key is correct
- Verify WordPress can access database
- Check SQL validation isn't too restrictive

## Security Checklist

- ✅ API server uses HTTPS
- ✅ WordPress site uses HTTPS
- ✅ OpenAI keys are encrypted in database
- ✅ API keys are hashed
- ✅ SQL validation blocks dangerous queries
- ✅ Only SELECT queries allowed
- ✅ Rate limiting enabled (optional)
- ✅ Error messages don't leak sensitive data

## Updating

### Update WordPress Plugin

1. Build new version
2. Upload to WordPress
3. Changes sync automatically with API server

### Update API Server

```bash
cd /home/youruser/public_html/api.heytrisha.com
git pull  # or upload new files
composer install --no-dev --optimize-autoloader
php artisan migrate
php artisan config:clear
php artisan cache:clear
```

## Monitoring

### Check API Usage

```bash
# In Laravel tinker
php artisan tinker

# Get site stats
$site = App\Models\Site::where('site_url', 'https://yoursite.com')->first();
echo "Queries: " . $site->query_count;
echo "Last query: " . $site->last_query_at;
```

### Check Logs

**API Server:**
```bash
tail -f storage/logs/laravel.log
```

**WordPress:**
- Go to Tools → Site Health
- Or check: `wp-content/debug.log` (if WP_DEBUG_LOG enabled)

## Backup

### API Server Database

```bash
mysqldump -u dbuser -p dbname sites > sites_backup.sql
```

### WordPress Database

Use your regular WordPress backup solution.

## Support

If you encounter issues:

1. Check logs (API server + WordPress)
2. Verify all endpoints are accessible
3. Test with simple queries first
4. Check API key is valid
5. Contact support with error logs

## Production Checklist

Before going live:

- [ ] SSL certificates installed (API + WordPress)
- [ ] .env file permissions set to 600
- [ ] APP_DEBUG=false in production
- [ ] Database backups configured
- [ ] Error logging enabled
- [ ] API rate limiting configured
- [ ] WordPress plugin tested with real queries
- [ ] API server health check returns "ok"
- [ ] WordPress health check returns "ok"


