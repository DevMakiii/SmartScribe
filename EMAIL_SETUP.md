# Gmail Email Setup for SmartScribe

This guide will help you configure Gmail SMTP to send password reset emails from your SmartScribe application.

## Prerequisites

1. A Gmail account
2. Google App Password (for secure access)

## Step 1: Enable 2-Factor Authentication

If you haven't already, enable 2-Factor Authentication on your Gmail account:

1. Go to [Google Account Settings](https://myaccount.google.com/)
2. Navigate to **Security** → **Signing in to Google**
3. Enable **2-Step Verification**

## Step 2: Generate App Password

1. Go to [Google App Passwords](https://myaccount.google.com/apppasswords)
2. Sign in with your Gmail account
3. Select **Mail** as the app
4. Select **Other (custom name)** as the device
5. Enter **SmartScribe** as the custom name
6. Click **Generate**
7. **Copy the 16-character password** (ignore spaces)

## Step 3: Configure Environment Variables

Update your `.env` file with your Gmail credentials:

```env
# Email Configuration (Gmail SMTP)
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_USERNAME=your-email@gmail.com
SMTP_PASSWORD=your-16-character-app-password
SMTP_FROM_EMAIL=noreply@smartscribe.com
SMTP_FROM_NAME=SmartScribe
```

Replace:
- `your-email@gmail.com` with your actual Gmail address
- `your-16-character-app-password` with the app password you generated

## Step 4: Test the Setup

1. Start your XAMPP server
2. Go to the Forgot Password page in your application
3. Enter your email address
4. Click "Send Reset Link"
5. Check your Gmail inbox for the password reset email

## Troubleshooting

### Common Issues:

1. **"Authentication failed" error**
   - Make sure you're using the App Password, not your regular Gmail password
   - Verify 2FA is enabled on your Google account

2. **"Less secure app" error**
   - Gmail no longer supports "less secure apps"
   - You MUST use an App Password with 2FA enabled

3. **Emails going to spam**
   - This is normal for development emails
   - Check your spam/junk folder
   - In production, consider using a service like SendGrid

4. **Connection timeout**
   - Check your internet connection
   - Verify XAMPP is running
   - Make sure port 587 is not blocked by firewall

### Alternative Email Services

If you prefer not to use Gmail, you can also configure:

- **SendGrid** (recommended for production)
- **Mailgun**
- **Amazon SES**
- **Postmark**

## Security Notes

- Never commit your `.env` file to version control
- Use different App Passwords for different applications
- Regularly rotate your App Passwords
- Consider using a dedicated email service for production

## Support

If you encounter issues:
1. Check the PHP error logs in XAMPP
2. Verify your `.env` file configuration
3. Test with a simple email script first
4. Contact support if needed

---

**Happy coding! 🚀**