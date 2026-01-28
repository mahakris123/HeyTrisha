# Build Instructions

This project has **two separate builds**:

1. **WordPress Plugin** (Thin Client) - For WordPress.org submission
2. **API Server** (Laravel Engine) - For external hosting

## 🚀 Quick Start

### Build WordPress Plugin Only

```powershell
.\build-wp-plugin.ps1
```

**Output:** `releases/heytrisha-woo-plugin-v1.0-YYYYMMDD-HHMM.zip`

**Contains:**
- ✅ WordPress plugin files (`heytrisha-woo.php`)
- ✅ Includes (database, credentials)
- ✅ Assets (CSS, JS, images)
- ✅ Chatbot UI
- ❌ NO Laravel API
- ❌ NO vendor dependencies

**Expected Size:** 1-3 MB

### Build API Server Only

```powershell
.\build-api-server.ps1
```

**Output:** `releases/heytrisha-api-server-v1.0-YYYYMMDD-HHMM.zip`

**Contains:**
- ✅ Laravel application (`app/`, `config/`, `routes/`)
- ✅ Laravel dependencies (`vendor/`)
- ✅ Database migrations
- ✅ Public files
- ❌ NO WordPress plugin files

**Expected Size:** 20-30 MB

## 📦 What Each Build Includes

### WordPress Plugin Build (`build-wp-plugin.ps1`)

```
heytrisha-woo/
├── heytrisha-woo.php          ✅ Main plugin file
├── readme.txt                 ✅ Plugin readme
├── LICENSE                    ✅ License file
├── includes/
│   ├── class-heytrisha-database.php
│   └── class-heytrisha-secure-credentials.php
├── assets/
│   ├── css/                   ✅ Stylesheets
│   ├── js/                    ✅ JavaScript files
│   └── img/                   ✅ Images
└── chatbot/                   ✅ React UI (if exists)
```

**Excludes:**
- ❌ `api/` directory (Laravel)
- ❌ `vendor/` directory
- ❌ `doc/` directory
- ❌ `releases/` directory
- ❌ `.git/` directory

### API Server Build (`build-api-server.ps1`)

```
heytrisha-api/
├── app/                       ✅ Laravel application
├── bootstrap/                 ✅ Laravel bootstrap
├── config/                    ✅ Laravel configuration
├── database/                  ✅ Migrations & seeders
├── public/                    ✅ Public files
├── routes/                    ✅ API routes
├── storage/                   ✅ Storage structure
├── vendor/                    ✅ Composer dependencies
├── artisan                    ✅ Laravel CLI
├── composer.json              ✅ Composer config
└── .env.example               ✅ Environment template
```

**Excludes:**
- ❌ WordPress plugin files
- ❌ `includes/` directory
- ❌ `assets/` directory
- ❌ `chatbot/` directory

## 🔧 Usage Examples

### Build Both Separately

```powershell
# Build WordPress plugin
.\build-wp-plugin.ps1

# Build API server
.\build-api-server.ps1
```

### Build for WordPress.org Submission

```powershell
# Only build WordPress plugin
.\build-wp-plugin.ps1

# Upload releases/heytrisha-woo-plugin-v1.0-*.zip to WordPress.org
```

### Build for API Deployment

```powershell
# Only build API server
.\build-api-server.ps1

# Upload releases/heytrisha-api-server-v1.0-*.zip to your server
# Then run: composer install
# Copy .env.example to .env and configure
```

## 📝 Notes

- **Original `build-plugin.ps1`** is kept as-is (not modified)
- Both builds create ZIP files in `releases/` directory
- Builds are timestamped for version tracking
- WordPress plugin build excludes all Laravel code
- API server build excludes all WordPress code

## ✅ Verification

After building, verify:

1. **WordPress Plugin:**
   - [ ] Size is 1-3 MB
   - [ ] No `api/` directory
   - [ ] No `vendor/` directory
   - [ ] Contains `heytrisha-woo.php`

2. **API Server:**
   - [ ] Size is 20-30 MB
   - [ ] Contains `app/` directory
   - [ ] Contains `vendor/` directory
   - [ ] Contains `artisan` file
   - [ ] No WordPress files

## 🐛 Troubleshooting

### Build Fails

- Check PowerShell execution policy: `Set-ExecutionPolicy RemoteSigned`
- Ensure you're in the plugin root directory
- Check that required directories exist

### WordPress Plugin Too Large

- Verify `api/` directory is excluded
- Check for unnecessary files in `assets/`
- Remove `chatbot-react-app/` source files (keep only `chatbot/` build)

### API Server Missing Dependencies

- Run `composer install` in `api/` directory before building
- Ensure `vendor/` directory exists


