# 🔧 SmartScribe Backend Deployment Options

This guide covers multiple deployment options for the SmartScribe PHP backend, from free tiers to production-ready solutions.

## 📊 Deployment Options Comparison

| Platform | Free Tier | Cost | Setup Difficulty | Best For |
|----------|-----------|------|------------------|----------|
| Railway | ✅ | $5+/mo | Easy | Development/Production |
| Render | ✅ | $7+/mo | Easy | Production |
| Heroku | ❌ | $7+/mo | Medium | Production |
| DigitalOcean | ❌ | $12+/mo | Medium | Production |
| AWS | ✅ (Limited) | $5+/mo | Hard | Enterprise |

## 🚀 Option 1: Railway (Recommended)

### Why Railway?
- Excellent free tier
- Easy Docker deployment
- Built-in MySQL database
- Great performance

### Step-by-Step Deployment

#### 1. Create Railway Account
1. Go to [railway.app](https://railway.app)
2. Sign up with GitHub
3. Create new project

#### 2. Add MySQL Database
```bash
# In Railway dashboard:
# 1. Click "New Service"
# 2. Select "Database"
# 3. Choose "MySQL"
# 4. Wait for provisioning
```

#### 3. Deploy Backend
```bash
# Railway will automatically detect your Dockerfile
# 1. Click "New Service" > "GitHub"
# 2. Connect your repository
# 3. Railway detects Dockerfile automatically
# 4. Add environment variables (see below)
# 5. Deploy
```

#### 4. Environment Variables for Railway
```env
DB_HOST=[Railway MySQL host]
DB_NAME=railway
DB_USER=postgres
DB_PASS=[Railway MySQL password]
GOOGLE_GEMINI_API_KEY=your_api_key
JWT_SECRET=your_64_char_secret
APP_ENV=production
APP_DEBUG=false
```

#### 5. Run Migrations
```bash
# Access your deployed app and run:
curl "https://your-app.railway.app/api/run_production_migration.php"
```

## 🚀 Option 2: Render

### Why Render?
- Reliable performance
- Good free tier
- Easy scaling
- Professional platform

### Step-by-Step Deployment

#### 1. Create Render Account
1. Go to [render.com](https://render.com)
2. Sign up (free)
3. Create new project

#### 2. Deploy Backend
1. Click "New" > "Web Service"
2. Connect GitHub repository
3. Configure service:
   - **Name**: smartscribe-backend
   - **Environment**: Docker
   - **Branch**: main
   - **Build Command**: (leave empty)
   - **Start Command**: (leave empty)

#### 3. Environment Variables for Render
```env
DB_HOST=your-db-host
DB_NAME=your_database
DB_USER=your_username
DB_PASS=your_password
GOOGLE_GEMINI_API_KEY=your_api_key
JWT_SECRET=your_64_char_secret
GOOGLE_OAUTH_CLIENT_ID=your_oauth_id
GOOGLE_CLIENT_SECRET=your_client_secret
VUE_APP_GOOGLE_OAUTH_CLIENT_ID=your_oauth_id
APP_ENV=production
APP_DEBUG=false
```

#### 4. Database Setup
- Use Railway for database (free MySQL)
- Or use Render PostgreSQL (paid)

## 🚀 Option 3: Heroku

### Why Heroku?
- Mature platform
- Easy deployment
- Good tooling

### Step-by-Step Deployment

#### 1. Install Heroku CLI
```bash
# Install Heroku CLI
curl https://cli-assets.heroku.com/install.sh | sh

# Login
heroku login
```

#### 2. Create Heroku App
```bash
# Create app
heroku create smartscribe-backend

# Add PHP buildpack
heroku buildpacks:add heroku/php

# Add Composer buildpack
heroku buildpacks:add heroku/nodejs
```

#### 3. Environment Variables
```bash
heroku config:set DB_HOST=your_db_host
heroku config:set DB_NAME=your_database
heroku config:set DB_USER=your_username
heroku config:set DB_PASS=your_password
heroku config:set GOOGLE_GEMINI_API_KEY=your_api_key
heroku config:set JWT_SECRET=your_64_char_secret
heroku config:set APP_ENV=production
heroku config:set APP_DEBUG=false
```

#### 4. Deploy
```bash
# Deploy to Heroku
git push heroku main
```

## 🚀 Option 4: DigitalOcean App Platform

### Why DigitalOcean?
- Predictable pricing
- Good performance
- Easy scaling

### Step-by-Step Deployment

#### 1. Create DigitalOcean Account
1. Go to [digitalocean.com](https://digitalocean.com)
2. Create account
3. Create new app

#### 2. Deploy from GitHub
1. Connect GitHub repository
2. Select "Dockerfile" as build method
3. Configure environment variables
4. Deploy

## 🚀 Option 5: AWS (Advanced)

### Why AWS?
- Scalable
- Professional
- Many services

### Step-by-Step Deployment

#### 1. Set Up AWS Account
1. Create AWS account
2. Set up billing (required)
3. Create RDS MySQL instance

#### 2. Deploy to Elastic Beanstalk
```bash
# Install AWS CLI
aws configure

# Create Elastic Beanstalk application
eb init smartscribe-backend
eb create production
```

## 📊 Cost Comparison

### Free Tier Limits
- **Railway**: 512MB RAM, 1GB storage, 500 hours/month
- **Render**: 750 hours/month, sleeps after inactivity
- **Heroku**: No free tier for production apps

### Production Pricing
- **Railway**: $5/month (512MB) to $25/month (8GB)
- **Render**: $7/month (512MB) to $25/month (2GB)
- **Heroku**: $7/month (512MB) to $25/month (1GB)
- **DigitalOcean**: $12/month (1GB) to $48/month (4GB)

## 🔧 Environment Variables Template

```env
# Database Configuration
DB_HOST=your_database_host
DB_NAME=smartscribe_prod
DB_USER=your_db_user
DB_PASS=your_strong_password

# AI Service Configuration
GOOGLE_GEMINI_API_KEY=your_google_gemini_api_key_here

# Application Configuration
APP_NAME=SmartScribe
APP_ENV=production
APP_DEBUG=false

# Security Configuration
JWT_SECRET=your_64_character_jwt_secret_key_here
SESSION_LIFETIME=7200

# Google OAuth Configuration
GOOGLE_OAUTH_CLIENT_ID=your_google_oauth_client_id_here
GOOGLE_CLIENT_ID=your_google_client_id_here
GOOGLE_CLIENT_SECRET=your_google_client_secret_here

# Frontend Google OAuth (must be prefixed with VUE_APP_)
VUE_APP_GOOGLE_OAUTH_CLIENT_ID=your_google_oauth_client_id_here

# CORS Configuration
ALLOWED_ORIGINS=https://your-frontend-domain.vercel.app

# Email Configuration (if using email features)
SMTP_HOST=your_smtp_host
SMTP_PORT=587
SMTP_USERNAME=your_smtp_username
SMTP_PASSWORD=your_smtp_password
FROM_EMAIL=noreply@yourdomain.com
FROM_NAME=SmartScribe
```

## 🧪 Testing Your Backend

### 1. Health Check
```bash
curl https://your-backend.com/api/index.php
```

### 2. Database Test
```bash
curl "https://your-backend.com/api/index.php?resource=dashboard&action=stats"
```

### 3. API Test
```bash
curl -X POST "https://your-backend.com/api/index.php?resource=auth&action=login" \
  -H "Content-Type: application/json" \
  -d '{"email":"test@example.com","password":"password"}'
```

## 🚨 Troubleshooting

### Common Issues

**Database Connection Failed**
```bash
# Check database credentials
# Verify security groups/firewall rules
# Test database connectivity from server
```

**Migration Errors**
```bash
# Check database permissions
# Verify migration file exists
# Check PHP error logs
```

**Memory Issues**
```bash
# Upgrade to larger instance
# Optimize PHP configuration
# Check for memory leaks
```

## 📈 Monitoring & Maintenance

### Railway Monitoring
- Built-in metrics dashboard
- Automatic error logging
- Performance monitoring

### Render Monitoring
- Real-time metrics
- Log streaming
- Alert configuration

### Custom Monitoring
```bash
# Add health check endpoint
curl https://your-backend.com/health

# Set up uptime monitoring
# Configure log aggregation
```

## 🎯 Recommendation

### For Development/Testing
- **Railway** - Best free tier, easy setup

### For Production
- **Render** - Reliable, good performance, easy scaling
- **Railway** - Cost-effective, good support

### For Enterprise
- **DigitalOcean** - Predictable pricing, good performance
- **AWS** - Full control, scalable

---

**Next Steps**: Choose your preferred platform and follow the step-by-step guide above. Remember to update your Vercel configuration with the correct backend URL after deployment.