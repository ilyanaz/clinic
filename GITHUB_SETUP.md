# How to Commit to GitHub

## Prerequisites
- Git installed on your system
- GitHub account created
- Laravel project ready in `c:\xampp\htdocs\clinic-laravel`

## Step-by-Step Instructions

### Step 1: Navigate to Project Directory
```bash
cd c:\xampp\htdocs\clinic-laravel
```

### Step 2: Initialize Git Repository
```bash
git init
```

### Step 3: Verify .gitignore File
The `.gitignore` file should already exist and exclude:
- `.env` file (contains sensitive data)
- `vendor/` directory (dependencies)
- `node_modules/` (if using npm)
- Log files
- Cache files

**Important:** Make sure your `.env` file is NOT committed (it's already in .gitignore).

### Step 4: Add All Files to Git
```bash
git add .
```

This will add all files except those in `.gitignore`.

### Step 5: Create Initial Commit
```bash
git commit -m "Initial commit: Laravel migration of clinic medical surveillance system"
```

Or with more details:
```bash
git commit -m "Initial commit: Laravel migration

- Migrated clinic system to Laravel framework
- Preserved all original UI/UX
- Updated all PHP files with correct paths
- Configured authentication and session management
- Added upload functionality for headers and signatures
- All PDF generators working
- All pages accessible in Laravel environment"
```

### Step 6: Create GitHub Repository

1. Go to [GitHub.com](https://github.com)
2. Click the **"+"** icon in the top right
3. Select **"New repository"**
4. Repository name: `clinic-laravel` (or your preferred name)
5. Description: "Medical Surveillance System - Laravel Migration"
6. Choose **Public** or **Private**
7. **DO NOT** check "Initialize with README" (we already have files)
8. Click **"Create repository"**

### Step 7: Connect Local Repository to GitHub

After creating the repository, GitHub will show you commands. Use these:

```bash
git remote add origin https://github.com/YOUR_USERNAME/clinic-laravel.git
```

Replace `YOUR_USERNAME` with your actual GitHub username.

### Step 8: Rename Branch to Main (if needed)
```bash
git branch -M main
```

### Step 9: Push to GitHub
```bash
git push -u origin main
```

You'll be prompted for your GitHub username and password (or personal access token).

## Authentication Options

### Option 1: Personal Access Token (Recommended)
1. Go to GitHub → Settings → Developer settings → Personal access tokens → Tokens (classic)
2. Generate new token
3. Select scopes: `repo` (full control of private repositories)
4. Copy the token
5. Use the token as password when pushing

### Option 2: SSH Key (More Secure)
1. Generate SSH key: `ssh-keygen -t ed25519 -C "your_email@example.com"`
2. Add SSH key to GitHub: Settings → SSH and GPG keys → New SSH key
3. Use SSH URL: `git remote add origin git@github.com:YOUR_USERNAME/clinic-laravel.git`

## Future Updates

After making changes, commit and push:

```bash
git add .
git commit -m "Description of changes"
git push
```

## Important Notes

⚠️ **Never commit:**
- `.env` file (contains database credentials)
- `vendor/` directory (can be regenerated with `composer install`)
- Uploaded files in `public/uploads/` (if they contain sensitive data)
- Log files

✅ **Safe to commit:**
- All PHP files
- Configuration files (except .env)
- Database migrations
- Views and templates
- Assets (CSS, JS, images)

## Troubleshooting

### If you get "fatal: not a git repository"
Run `git init` first.

### If you get authentication errors
- Use Personal Access Token instead of password
- Or set up SSH keys

### If you want to exclude uploads directory
Add to `.gitignore`:
```
/public/uploads/*
!/public/uploads/.gitkeep
```

### If you need to remove files from git but keep locally
```bash
git rm --cached filename
```

## Quick Reference

```bash
# Initialize
git init

# Add files
git add .

# Commit
git commit -m "Your message"

# Connect to GitHub (first time only)
git remote add origin https://github.com/YOUR_USERNAME/clinic-laravel.git
git branch -M main
git push -u origin main

# Future pushes
git add .
git commit -m "Update message"
git push
```
