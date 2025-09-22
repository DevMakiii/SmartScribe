#!/bin/bash

# SmartScribe Deployment Script for Vercel
# This script helps deploy the frontend to Vercel

echo "🚀 SmartScribe Vercel Deployment Script"
echo "======================================"

# Check if Vercel CLI is installed
if ! command -v vercel &> /dev/null; then
    echo "❌ Vercel CLI is not installed. Installing..."
    npm install -g vercel
fi

# Check if user is logged in to Vercel
echo "🔐 Checking Vercel authentication..."
if ! vercel whoami &> /dev/null; then
    echo "❌ You need to login to Vercel first."
    echo "Run: vercel login"
    exit 1
fi

echo "✅ Vercel authentication confirmed"

# Build the project
echo "🔨 Building the project..."
npm run build

if [ $? -ne 0 ]; then
    echo "❌ Build failed. Please fix the errors and try again."
    exit 1
fi

echo "✅ Build completed successfully"

# Check if vercel.json exists
if [ ! -f "vercel.json" ]; then
    echo "❌ vercel.json not found. Please ensure the configuration file exists."
    exit 1
fi

# Deploy to Vercel
echo "🚀 Deploying to Vercel..."
vercel --prod

if [ $? -eq 0 ]; then
    echo "✅ Deployment successful!"
    echo ""
    echo "📝 Next steps:"
    echo "1. Deploy your backend (PHP API) to a platform like Railway, Render, or Heroku"
    echo "2. Update the API routes in vercel.json to point to your backend URL"
    echo "3. Set up your production environment variables"
    echo "4. Run database migrations on your backend"
    echo ""
    echo "🔗 Your frontend is now live on Vercel!"
else
    echo "❌ Deployment failed. Please check the errors above."
    exit 1
fi