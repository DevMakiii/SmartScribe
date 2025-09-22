// build-optimization.js
// This script optimizes the build for production deployment

const fs = require('fs');
const path = require('path');

console.log('🔧 Optimizing build for production...');

// Create optimized .env.production if it doesn't exist
const envProductionPath = path.join(__dirname, '.env.production');
if (!fs.existsSync(envProductionPath)) {
    console.log('📝 Creating optimized .env.production file...');
    const envTemplate = `# Production Environment Variables
# Database Configuration
DB_HOST=your-production-db-host
DB_NAME=smartscribe_prod
DB_USER=your_db_user
DB_PASS=your_strong_db_password

# AI Service Configuration
GOOGLE_GEMINI_API_KEY=your_google_gemini_api_key_here

# Application Configuration
APP_NAME=SmartScribe
APP_ENV=production
APP_DEBUG=false

# Security Configuration
JWT_SECRET=your_64_character_jwt_secret_key_here_make_it_long_and_random
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
`;

    fs.writeFileSync(envProductionPath, envTemplate);
    console.log('✅ Created .env.production template');
}

// Check if vercel.json exists and is properly configured
const vercelConfigPath = path.join(__dirname, 'vercel.json');
if (fs.existsSync(vercelConfigPath)) {
    console.log('✅ vercel.json configuration found');
} else {
    console.log('❌ vercel.json not found. Creating basic configuration...');
    const vercelConfig = {
        "version": 2,
        "builds": [
            {
                "src": "package.json",
                "use": "@vercel/static-build",
                "config": {
                    "distDir": "dist"
                }
            }
        ],
        "routes": [
            {
                "src": "/SmartScribe/api/(.*)",
                "dest": "https://your-backend-service.com/api/index.php?$1"
            },
            {
                "src": "/api/(.*)",
                "dest": "https://your-backend-service.com/api/index.php?$1"
            },
            {
                "src": "/(.*)",
                "dest": "/dist/$1"
            }
        ],
        "env": {
            "NODE_ENV": "production"
        },
        "build": {
            "env": {
                "NODE_ENV": "production"
            }
        }
    };

    fs.writeFileSync(vercelConfigPath, JSON.stringify(vercelConfig, null, 2));
    console.log('✅ Created vercel.json configuration');
}

// Check package.json for production optimizations
const packageJsonPath = path.join(__dirname, 'package.json');
if (fs.existsSync(packageJsonPath)) {
    const packageJson = JSON.parse(fs.readFileSync(packageJsonPath, 'utf8'));

    // Add production build optimizations if not present
    if (!packageJson.scripts.build) {
        packageJson.scripts.build = "vue-cli-service build";
    }

    if (!packageJson.scripts['build:production']) {
        packageJson.scripts['build:production'] = "vue-cli-service build --mode production";
    }

    fs.writeFileSync(packageJsonPath, JSON.stringify(packageJson, null, 2));
    console.log('✅ Updated package.json with production build scripts');
}

console.log('✅ Build optimization completed!');
console.log('');
console.log('📋 Next steps:');
console.log('1. Update .env.production with your actual values');
console.log('2. Update vercel.json with your backend URL');
console.log('3. Run: npm run build');
console.log('4. Deploy to Vercel');