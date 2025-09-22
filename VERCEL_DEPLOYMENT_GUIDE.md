# 🚀 SmartScribe Vercel Deployment Guide

This guide provides step-by-step instructions for deploying SmartScribe to Vercel, including both frontend and backend deployment strategies.

## 📋 Overview

SmartScribe consists of:
- **Frontend**: Vue.js SPA (deployed to Vercel)
- **Backend**: PHP API with MySQL database (deployed separately)

## ✅ Prerequisites

- GitHub account
- Vercel account (free tier available)
- Railway/Railway account for backend database (free tier available)
- Google Gemini API key
- Google OAuth credentials

## 🎯 Step 1: Prepare Your Project

### 1.1 Environment Setup
```bash
# Install dependencies
npm install

# Run build optimization
node build-optimization.js

# Build the project
npm run build
```

### 1.2 Configure Environment Variables
1. Copy `.env.example` to `.env.production`
2. Fill in your production values:
   ```env
   # Database (for backend deployment)
   DB_HOST=your-railway-db-host
   DB_NAME=smartscribe_prod
   DB_USER=your_db_user
   DB_PASS=your_strong_password

   # AI Services
   GOOGLE_GEMINI_API_KEY=your_api_key

   # Security
   JWT_SECRET=your_64_char_secret
   ```

## 🎯 Step 2: Deploy Frontend to Vercel

### 2.1 Install Vercel CLI
```bash
npm install -g vercel
```

### 2.2 Login to Vercel
```bash
vercel login
```

### 2.3 Deploy Frontend
```bash
# Deploy to production
vercel --prod

# Or use our deployment script
chmod +x deploy-to-vercel.sh
./deploy-to-vercel.sh
```

### 2.4 Configure Domain (Optional)
```bash
# Add custom domain
vercel domains add yourdomain.com

# Set up DNS records as instructed by Vercel
```

## 🎯 Step 3: Deploy Backend

### Option A: Railway (Recommended)
1. Go to [Railway.app](https://railway.app) and create account
2. Create new project
3. Add MySQL database
4. Note down connection details
5. Deploy using existing Dockerfile:
   ```bash
   # Railway will automatically detect Dockerfile
   # Set environment variables in Railway dashboard
   ```

### Option B: Render
1. Go to [Render.com](https://render.com) and create account
2. Create new Web Service
3. Connect your GitHub repository
4. Configure:
   - **Environment**: Docker
   - **Build Command**: (leave empty)
   - **Start Command**: (leave empty)

### Option C: Heroku
```bash
# Install Heroku CLI
heroku create your-app-name

# Set environment variables
heroku config:set DB_HOST=your_db_host
heroku config:set GOOGLE_GEMINI_API_KEY=your_key
# ... set all required variables

# Deploy
git push heroku main
```

## 🎯 Step 4: Configure API Routing

### 4.1 Update Vercel Configuration
Edit `vercel.json` and update the backend URL:
```json
{
  "routes": [
    {
      "src": "/SmartScribe/api/(.*)",
      "dest": "https://your-backend-service.com/api/index.php?$1"
    },
    {
      "src": "/api/(.*)",
      "dest": "https://your-backend-service.com/api/index.php?$1"
    }
  ]
}
```

### 4.2 Redeploy Frontend
```bash
vercel --prod
```

## 🎯 Step 5: Database Setup

### 5.1 Run Migrations
Once backend is deployed, run migrations:
```bash
# Access your backend URL and run migrations
curl "https://your-backend.com/api/run_production_migration.php"
```

### 5.2 Verify Database Connection
```bash
# Test database connection
curl "https://your-backend.com/api/index.php?resource=dashboard&action=stats"
```

## 🎯 Step 6: Testing

### 6.1 Frontend Tests
1. Access your Vercel URL
2. Test user registration
3. Test note creation
4. Verify API calls work

### 6.2 Backend Tests
```bash
# Test API endpoints
curl -X GET "https://your-backend.com/api/index.php?resource=auth&action=profile" \
  -H "Authorization: Bearer your_test_token"
```

## 🎯 Step 7: Production Optimization

### 7.1 Enable CDN
- Vercel automatically provides CDN
- Static assets are cached globally

### 7.2 Set Up Monitoring
```bash
# Add monitoring to your backend
# Railway/Render provide built-in monitoring
```

### 7.3 Configure SSL
- Vercel provides automatic SSL
- Ensure backend has SSL configured

## 💰 Cost Information

### Vercel (Frontend)
- **Free Tier**: 100GB bandwidth/month, unlimited requests
- **Pro**: $20/month for advanced features
- **Enterprise**: Custom pricing

### Railway (Backend + Database)
- **Free Tier**: 512MB RAM, 1GB storage
- **Paid**: Starting at $5/month

### Total Monthly Cost
- **Free**: $0 (with limitations)
- **Basic**: $5-10/month (comfortable usage)

## 🔧 Troubleshooting

### Common Issues

**API Connection Failed**
```bash
# Check if backend is running
curl https://your-backend.com/api/index.php

# Verify CORS headers
# Check browser console for errors
```

**Database Connection Issues**
```bash
# Test database connectivity
php -r "
require 'api/config/database.php';
try {
    \$db = getDbConnection();
    echo 'Database connected successfully';
} catch (Exception \$e) {
    echo 'Error: ' . \$e->getMessage();
}
"
```

**Build Failures**
```bash
# Clear cache and rebuild
rm -rf node_modules/.cache
npm run build
```

## 📞 Support

If you encounter issues:
1. Check Vercel deployment logs
2. Verify environment variables
3. Test API endpoints manually
4. Check database connectivity

## 🎉 Success Checklist

- [ ] Frontend deployed to Vercel
- [ ] Backend deployed successfully
- [ ] Database migrations completed
- [ ] API routing configured
- [ ] Environment variables set
- [ ] SSL certificates active
- [ ] User registration working
- [ ] Note creation functional

---

**Congratulations!** 🎊 Your SmartScribe application is now live on Vercel!

**Frontend URL**: https://your-project.vercel.app
**Backend URL**: https://your-backend-service.com