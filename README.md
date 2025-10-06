# SmartScribe

A modern, responsive note-taking application with AI-powered summarization and OCR capabilities.

## Features

- 📝 **Smart Note Taking** - Create and manage notes with rich text support
- 🤖 **AI Summarization** - Automatic summary generation using Google Gemini AI
- 📷 **OCR Integration** - Extract text from images using Tesseract.js
- 📱 **Fully Responsive** - Works perfectly on desktop, tablet, and mobile
- 🔒 **User Authentication** - Secure login and registration system
- 📊 **Progress Tracking** - Monitor your learning and note-taking progress
- 🎯 **Study Quizzes** - Generate quizzes from your notes
- 📄 **Export Options** - Export notes as PDF, DOCX, or plain text

## Tech Stack

- **Frontend**: Vue.js 3, Vue Router, Vuex, Tailwind CSS
- **Backend**: PHP 8, MySQL
- **AI Integration**: Google Gemini API
- **OCR**: Tesseract.js
- **Icons**: Font Awesome

## Quick Start

### Prerequisites
- Node.js (v14 or higher)
- PHP (v8.0 or higher)
- MySQL
- Composer

### Installation

1. **Clone the repository**
   ```bash
   git clone <repository-url>
   cd smartscribe
   ```

2. **Install PHP dependencies**
   ```bash
   composer install
   ```

3. **Install Node.js dependencies**
   ```bash
   npm install
   ```

4. **Database Setup**
   - Create a MySQL database
   - Copy `.env.example` to `.env` and configure your database settings
   - Run the migrations: `php api/migrations/`

5. **Development Server**
   ```bash
   npm run serve
   ```

6. **Production Build**
   ```bash
   npm run build
   ```

## Environment Variables

Create a `.env` file in the root directory:

```env
DB_HOST=localhost
DB_NAME=smartscribe
DB_USER=your_db_user
DB_PASS=your_db_password
GOOGLE_GEMINI_API_KEY=your_gemini_api_key
```

## Deployment

### Web Server Configuration

For Apache, ensure the following in your `.htaccess`:

```apache
RewriteEngine On
RewriteBase /
RewriteRule ^index\.html$ - [L]
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule . /index.html [L]
```

### Production Build

1. Build the frontend:
   ```bash
   npm run build
   ```

2. Copy the `dist/` contents to your web server

3. Ensure PHP files are accessible at the API endpoints

## API Endpoints

- `POST /api/auth/login` - User login
- `POST /api/auth/register` - User registration
- `GET /api/notes` - Get user notes
- `POST /api/notes` - Create new note
- `POST /api/summaries` - Generate AI summary
- `GET /api/export` - Export notes

## Contributing

1. Fork the repository
2. Create a feature branch
3. Commit your changes
4. Push to the branch
5. Create a Pull Request

## License

This project is licensed under the MIT License.
