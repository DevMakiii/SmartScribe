// api/auth.js
const { getDbConnection } = require('./_utils/database');
const AuthUtils = require('./_utils/auth');
const ResponseUtils = require('./_utils/response');

module.exports = async (req, res) => {
  // Handle CORS preflight requests
  if (req.method === 'OPTIONS') {
    const origin = req.headers.origin || '';
    const allowedOrigins = [
      'https://smartscribe-frontend.vercel.app',
      'https://your-custom-domain.com',
      'http://localhost:8080',
      'http://localhost:3000'
    ];

    const isOriginAllowed = allowedOrigins.includes(origin) || origin === '';

    if (isOriginAllowed) {
      res.setHeader('Access-Control-Allow-Origin', origin || 'https://smartscribe-frontend.vercel.app');
    } else {
      // For debugging, temporarily allow all origins (REMOVE IN PRODUCTION)
      res.setHeader('Access-Control-Allow-Origin', '*');
    }

    res.setHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
    res.setHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-User-ID, X-Requested-With, Cache-Control, Pragma');
    res.setHeader('Access-Control-Allow-Credentials', 'true');
    res.setHeader('Access-Control-Max-Age', '86400');

    return res.status(200).end();
  }

  const action = req.query.action || 'index';
  const db = await getDbConnection();

  // Add CORS headers for all requests (not just preflight)
  const origin = req.headers.origin || '';
  const allowedOrigins = [
    'https://smartscribe-frontend.vercel.app',
    'https://your-custom-domain.com',
    'http://localhost:8080',
    'http://localhost:3000'
  ];

  const isOriginAllowed = allowedOrigins.includes(origin) || origin === '';

  if (isOriginAllowed) {
    res.setHeader('Access-Control-Allow-Origin', origin || 'https://smartscribe-frontend.vercel.app');
  } else {
    // For debugging, temporarily allow all origins (REMOVE IN PRODUCTION)
    console.log('🚨 CORS DEBUG: Origin not in allowed list:', origin);
    console.log('🚨 CORS DEBUG: Allowed origins:', allowedOrigins);
    res.setHeader('Access-Control-Allow-Origin', '*');
  }

  res.setHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
  res.setHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-User-ID, X-Requested-With, Cache-Control, Pragma');
  res.setHeader('Access-Control-Allow-Credentials', 'true');
  res.setHeader('Access-Control-Max-Age', '86400');

  console.log('🔍 CORS DEBUG: Request details:');
  console.log('  - Method:', req.method);
  console.log('  - Origin:', origin);
  console.log('  - Action:', action);
  console.log('  - Headers set:', {
    'Access-Control-Allow-Origin': origin || 'https://smartscribe-frontend.vercel.app',
    'Access-Control-Allow-Methods': 'GET, POST, PUT, DELETE, OPTIONS',
    'Access-Control-Allow-Credentials': 'true'
  });

  try {
    switch (action) {
      case 'register':
        return await handleRegister(req, res, db);
      case 'login':
        return await handleLogin(req, res, db);
      case 'google':
        return await handleGoogleLogin(req, res, db);
      case 'profile':
        return await handleProfile(req, res, db);
      case 'updateProfile':
        return await handleUpdateProfile(req, res, db);
      case 'logout':
        return await handleLogout(req, res, db);
      case 'requestPasswordReset':
        return await handleRequestPasswordReset(req, res, db);
      case 'resetPassword':
        return await handleResetPassword(req, res, db);
      case 'updatePassword':
        return await handleUpdatePassword(req, res, db);
      case 'validateResetToken':
        return await handleValidateResetToken(req, res, db);
      case 'deleteAccount':
        return await handleDeleteAccount(req, res, db);
      default:
        return ResponseUtils.badRequest(res, 'Invalid action');
    }
  } catch (error) {
    console.error('Auth error:', error);
    return ResponseUtils.error(res, 'Authentication failed: ' + error.message);
  }
};

async function handleRegister(req, res, db) {
  try {
    const data = req.body;

    // Validate input
    const validationErrors = AuthUtils.validateRequired(data, ['first_name', 'last_name', 'email', 'password']);
    if (Object.keys(validationErrors).length > 0) {
      return ResponseUtils.validationError(res, validationErrors);
    }

    // Validate email format
    if (!AuthUtils.validateEmail(data.email)) {
      return ResponseUtils.validationError(res, { email: 'Invalid email format' });
    }

    // Validate password length
    const passwordError = AuthUtils.validatePassword(data.password);
    if (passwordError) {
      return ResponseUtils.validationError(res, { password: passwordError });
    }

    // Check if email already exists
    const [existingUsers] = await db.execute("SELECT id FROM users WHERE email = ?", [data.email]);
    if (existingUsers.length > 0) {
      return ResponseUtils.error(res, 'Email already exists', 409);
    }

    // Hash password
    const hashedPassword = await AuthUtils.hashPassword(data.password);

    // Insert user
    const fullName = `${data.first_name} ${data.last_name}`.trim();
    const [result] = await db.execute(
      "INSERT INTO users (first_name, last_name, name, email, password) VALUES (?, ?, ?, ?, ?)",
      [data.first_name, data.last_name, fullName, data.email, hashedPassword]
    );

    // Generate JWT token
    const token = AuthUtils.generateJWT({
      id: result.insertId,
      name: fullName,
      email: data.email
    });

    return ResponseUtils.success(res, {
      user: {
        id: result.insertId,
        name: fullName,
        email: data.email,
        google_id: null
      },
      token
    }, 'User registered successfully', 201);

  } catch (error) {
    console.error('Registration error:', error);
    return ResponseUtils.error(res, 'Registration failed: ' + error.message);
  }
}

async function handleLogin(req, res, db) {
  try {
    const data = req.body;

    // Validate input
    const validationErrors = AuthUtils.validateRequired(data, ['email', 'password']);
    if (Object.keys(validationErrors).length > 0) {
      return ResponseUtils.validationError(res, validationErrors);
    }

    // Validate email format
    if (!AuthUtils.validateEmail(data.email)) {
      return ResponseUtils.validationError(res, { email: 'Invalid email format' });
    }

    // Find user
    const [users] = await db.execute("SELECT id, name, email, password, google_id FROM users WHERE email = ?", [data.email]);
    const user = users[0];

    if (!user || !(await AuthUtils.verifyPassword(data.password, user.password))) {
      return ResponseUtils.unauthorized(res, 'Invalid credentials');
    }

    // Generate JWT token
    const token = AuthUtils.generateJWT({
      id: user.id,
      name: user.name,
      email: user.email,
      google_id: user.google_id
    });

    return ResponseUtils.success(res, {
      user: {
        id: user.id,
        name: user.name,
        email: user.email,
        google_id: user.google_id
      },
      token
    }, 'Login successful');

  } catch (error) {
    console.error('Login error:', error);
    return ResponseUtils.error(res, 'Login failed: ' + error.message);
  }
}

async function handleProfile(req, res, db) {
  try {
    // Get token from Authorization header
    const authHeader = req.headers.authorization;
    if (!authHeader || !authHeader.startsWith('Bearer ')) {
      return ResponseUtils.unauthorized(res);
    }

    const token = authHeader.substring(7);
    const decoded = AuthUtils.decodeJWT(token);

    if (!decoded) {
      return ResponseUtils.unauthorized(res);
    }

    // Get user data
    const [users] = await db.execute(
      "SELECT id, first_name, last_name, name, email, profile_picture, google_id, created_at FROM users WHERE id = ?",
      [decoded.user_id]
    );

    const user = users[0];
    if (!user) {
      return ResponseUtils.notFound(res, 'User not found');
    }

    return ResponseUtils.success(res, {
      user: {
        id: user.id,
        first_name: user.first_name || '',
        last_name: user.last_name || '',
        name: user.name || `${user.first_name || ''} ${user.last_name || ''}`.trim(),
        email: user.email,
        profile_picture: user.profile_picture || null,
        google_id: user.google_id || null,
        created_at: user.created_at
      }
    });

  } catch (error) {
    console.error('Profile error:', error);
    return ResponseUtils.error(res, 'Failed to retrieve profile: ' + error.message);
  }
}

async function handleUpdateProfile(req, res, db) {
  try {
    // Get token from Authorization header
    const authHeader = req.headers.authorization;
    if (!authHeader || !authHeader.startsWith('Bearer ')) {
      return ResponseUtils.unauthorized(res);
    }

    const token = authHeader.substring(7);
    const decoded = AuthUtils.decodeJWT(token);

    if (!decoded) {
      return ResponseUtils.unauthorized(res);
    }

    const data = req.body;
    if (!data) {
      return ResponseUtils.badRequest(res, 'No data provided');
    }

    // Build update query dynamically
    const updateFields = [];
    const params = [];

    if (data.first_name !== undefined) {
      updateFields.push("first_name = ?");
      params.push(AuthUtils.sanitizeInput(data.first_name));
    }

    if (data.last_name !== undefined) {
      updateFields.push("last_name = ?");
      params.push(AuthUtils.sanitizeInput(data.last_name));
    }

    // Update the combined name field when first_name or last_name is updated
    if (data.first_name !== undefined || data.last_name !== undefined) {
      const firstName = data.first_name || '';
      const lastName = data.last_name || '';
      const fullName = `${firstName} ${lastName}`.trim();
      updateFields.push("name = ?");
      params.push(fullName);
    }

    if (data.name !== undefined) {
      updateFields.push("name = ?");
      params.push(AuthUtils.sanitizeInput(data.name));
    }

    if (data.email !== undefined) {
      // Validate email format
      if (!AuthUtils.validateEmail(data.email)) {
        return ResponseUtils.validationError(res, { email: 'Invalid email format' });
      }

      // Check if email is already taken by another user
      const [existingUsers] = await db.execute("SELECT id FROM users WHERE email = ? AND id != ?", [data.email, decoded.user_id]);
      if (existingUsers.length > 0) {
        return ResponseUtils.error(res, 'Email already taken', 409);
      }
      updateFields.push("email = ?");
      params.push(data.email);
    }

    if (updateFields.length === 0) {
      return ResponseUtils.badRequest(res, 'No valid fields to update');
    }

    params.push(decoded.user_id);
    const query = `UPDATE users SET ${updateFields.join(', ')} WHERE id = ?`;
    const [result] = await db.execute(query, params);

    if (result.affectedRows > 0) {
      return ResponseUtils.success(res, null, 'Profile updated successfully');
    } else {
      return ResponseUtils.error(res, 'Failed to update profile');
    }

  } catch (error) {
    console.error('Update profile error:', error);
    return ResponseUtils.error(res, 'Failed to update profile: ' + error.message);
  }
}

async function handleLogout(req, res, db) {
  try {
    const authHeader = req.headers.authorization;
    if (authHeader && authHeader.startsWith('Bearer ')) {
      const token = authHeader.substring(7);
      // Remove token from database
      await db.execute("DELETE FROM user_tokens WHERE token = ?", [token]);
    }

    return ResponseUtils.success(res, null, 'Logged out successfully');

  } catch (error) {
    console.error('Logout error:', error);
    return ResponseUtils.error(res, 'Logout failed: ' + error.message);
  }
}

async function handleRequestPasswordReset(req, res, db) {
  try {
    const data = req.body;

    // Validate input
    const validationErrors = AuthUtils.validateRequired(data, ['email']);
    if (Object.keys(validationErrors).length > 0) {
      return ResponseUtils.validationError(res, validationErrors);
    }

    // Validate email format
    if (!AuthUtils.validateEmail(data.email)) {
      return ResponseUtils.validationError(res, { email: 'Invalid email format' });
    }

    // Check if user exists
    const [users] = await db.execute("SELECT id, name FROM users WHERE email = ?", [data.email]);
    const user = users[0];

    if (!user) {
      // Don't reveal if email exists or not for security
      return ResponseUtils.success(res, null, 'If an account with that email exists, a password reset link has been sent.');
    }

    // Generate reset token
    const crypto = require('crypto');
    const resetToken = crypto.randomBytes(32).toString('hex');
    const expiresAt = new Date(Date.now() + 60 * 60 * 1000); // 1 hour

    // Save reset token
    await db.execute(
      "INSERT INTO password_reset_tokens (user_id, token, email, expires_at) VALUES (?, ?, ?, ?)",
      [user.id, resetToken, data.email, expiresAt]
    );

    // TODO: Send reset email using your email service
    const frontendUrl = process.env.FRONTEND_URL || 'http://localhost:8080';
    const resetLink = `${frontendUrl}/reset-password?token=${resetToken}`;

    return ResponseUtils.success(res, {
      reset_link: resetLink,
      reset_token: resetToken,
      message: 'Password reset link has been sent to your email.'
    }, 'Password reset link has been sent to your email.');

  } catch (error) {
    console.error('Password reset request error:', error);
    return ResponseUtils.error(res, 'Failed to request password reset: ' + error.message);
  }
}

async function handleResetPassword(req, res, db) {
  try {
    const data = req.body;

    // Validate input
    const validationErrors = AuthUtils.validateRequired(data, ['token', 'password']);
    if (Object.keys(validationErrors).length > 0) {
      return ResponseUtils.validationError(res, validationErrors);
    }

    // Validate password length
    const passwordError = AuthUtils.validatePassword(data.password);
    if (passwordError) {
      return ResponseUtils.validationError(res, { password: passwordError });
    }

    // Find valid reset token
    const [tokens] = await db.execute(
      "SELECT user_id, email FROM password_reset_tokens WHERE token = ? AND expires_at > UTC_TIMESTAMP() AND used = 0",
      [data.token]
    );

    const resetToken = tokens[0];
    if (!resetToken) {
      return ResponseUtils.error(res, 'Invalid or expired reset token', 400);
    }

    // Hash new password
    const hashedPassword = await AuthUtils.hashPassword(data.password);

    // Update user password
    await db.execute("UPDATE users SET password = ? WHERE id = ?", [hashedPassword, resetToken.user_id]);

    // Mark token as used
    await db.execute("UPDATE password_reset_tokens SET used = 1 WHERE token = ?", [data.token]);

    // Clean up expired tokens
    await db.execute("DELETE FROM password_reset_tokens WHERE expires_at < UTC_TIMESTAMP()");

    return ResponseUtils.success(res, null, 'Password has been reset successfully. You can now log in with your new password.');

  } catch (error) {
    console.error('Password reset error:', error);
    return ResponseUtils.error(res, 'Failed to reset password: ' + error.message);
  }
}

async function handleUpdatePassword(req, res, db) {
  try {
    // Get token from Authorization header
    const authHeader = req.headers.authorization;
    if (!authHeader || !authHeader.startsWith('Bearer ')) {
      return ResponseUtils.unauthorized(res);
    }

    const token = authHeader.substring(7);
    const decoded = AuthUtils.decodeJWT(token);

    if (!decoded) {
      return ResponseUtils.unauthorized(res);
    }

    const data = req.body;

    // Validate input
    const validationErrors = AuthUtils.validateRequired(data, ['current_password', 'new_password']);
    if (Object.keys(validationErrors).length > 0) {
      return ResponseUtils.validationError(res, validationErrors);
    }

    // Validate new password length
    const passwordError = AuthUtils.validatePassword(data.new_password);
    if (passwordError) {
      return ResponseUtils.validationError(res, { new_password: passwordError });
    }

    // Get current user data
    const [users] = await db.execute("SELECT password FROM users WHERE id = ?", [decoded.user_id]);
    const user = users[0];

    if (!user) {
      return ResponseUtils.notFound(res, 'User not found');
    }

    // Verify current password
    if (!(await AuthUtils.verifyPassword(data.current_password, user.password))) {
      return ResponseUtils.error(res, 'Current password is incorrect', 400);
    }

    // Hash new password
    const hashedPassword = await AuthUtils.hashPassword(data.new_password);

    // Update password
    const [result] = await db.execute("UPDATE users SET password = ? WHERE id = ?", [hashedPassword, decoded.user_id]);

    if (result.affectedRows > 0) {
      return ResponseUtils.success(res, null, 'Password updated successfully');
    } else {
      return ResponseUtils.error(res, 'Failed to update password');
    }

  } catch (error) {
    console.error('Update password error:', error);
    return ResponseUtils.error(res, 'Failed to update password: ' + error.message);
  }
}

async function handleValidateResetToken(req, res, db) {
  try {
    const token = req.query.token;

    if (!token) {
      return ResponseUtils.error(res, 'Reset token is required', 400);
    }

    // Check if token is valid and not expired
    const [tokens] = await db.execute(
      "SELECT email FROM password_reset_tokens WHERE token = ? AND expires_at > UTC_TIMESTAMP() AND used = 0",
      [token]
    );

    const resetToken = tokens[0];
    if (!resetToken) {
      return ResponseUtils.error(res, 'Invalid or expired reset token', 400);
    }

    return ResponseUtils.success(res, {
      email: resetToken.email,
      valid: true
    }, 'Reset token is valid');

  } catch (error) {
    console.error('Validate reset token error:', error);
    return ResponseUtils.error(res, 'Failed to validate reset token: ' + error.message);
  }
}

async function handleDeleteAccount(req, res, db) {
  try {
    // Get token from Authorization header
    const authHeader = req.headers.authorization;
    if (!authHeader || !authHeader.startsWith('Bearer ')) {
      return ResponseUtils.unauthorized(res);
    }

    const token = authHeader.substring(7);
    const decoded = AuthUtils.decodeJWT(token);

    if (!decoded) {
      return ResponseUtils.unauthorized(res);
    }

    const userId = decoded.user_id;

    // Start transaction for data consistency
    const connection = await db.getConnection();
    await connection.beginTransaction();

    try {
      // Delete user-related data in correct order to avoid foreign key constraints
      await connection.execute("DELETE FROM password_reset_tokens WHERE user_id = ?", [userId]);
      await connection.execute("DELETE FROM user_tokens WHERE user_id = ?", [userId]);
      await connection.execute("DELETE FROM study_sessions WHERE user_id = ?", [userId]);
      await connection.execute("DELETE FROM learning_goals WHERE user_id = ?", [userId]);
      await connection.execute("DELETE FROM summaries WHERE user_id = ?", [userId]);
      await connection.execute("DELETE FROM quizzes WHERE user_id = ?", [userId]);
      await connection.execute("DELETE FROM notes WHERE user_id = ?", [userId]);

      // Finally, delete the user record
      const [result] = await connection.execute("DELETE FROM users WHERE id = ?", [userId]);

      if (result.affectedRows === 0) {
        throw new Error('Failed to delete user record');
      }

      // Commit transaction
      await connection.commit();
      connection.release();

      return ResponseUtils.success(res, null, 'Account deleted successfully');

    } catch (error) {
      // Rollback transaction on error
      await connection.rollback();
      connection.release();
      throw error;
    }

  } catch (error) {
    console.error('Delete account error:', error);
    return ResponseUtils.error(res, 'Failed to delete account: ' + error.message);
  }
}

async function handleGoogleLogin(req, res, db) {
  try {
    const data = req.body;

    // Validate input
    const validationErrors = AuthUtils.validateRequired(data, ['access_token']);
    if (Object.keys(validationErrors).length > 0) {
      return ResponseUtils.validationError(res, validationErrors);
    }

    // Verify Google access token
    const googleUser = await verifyGoogleAccessToken(data.access_token);
    if (!googleUser) {
      return ResponseUtils.unauthorized(res, 'Invalid Google access token');
    }

    // Check if user exists with this Google ID
    const [existingUsers] = await db.execute("SELECT id, name, email FROM users WHERE google_id = ?", [googleUser.sub]);
    let userId;

    if (existingUsers.length > 0) {
      // Existing Google user - update profile if needed
      await updateGoogleUserProfile(existingUsers[0].id, googleUser, db);
      userId = existingUsers[0].id;
    } else {
      // Check if email already exists (traditional signup)
      const [emailUsers] = await db.execute("SELECT id FROM users WHERE email = ?", [googleUser.email]);

      if (emailUsers.length > 0) {
        // Link Google account to existing user
        await linkGoogleAccount(emailUsers[0].id, googleUser, db);
        userId = emailUsers[0].id;
      } else {
        // Create new user from Google data
        userId = await createGoogleUser(googleUser, db);
      }
    }

    // Get updated user data
    const [users] = await db.execute(
      "SELECT id, name, email, profile_picture, google_id FROM users WHERE id = ?",
      [userId]
    );
    const userData = users[0];

    // Generate JWT token
    const token = AuthUtils.generateJWT({
      id: userData.id,
      name: userData.name,
      email: userData.email,
      profile_picture: userData.profile_picture,
      google_id: userData.google_id
    });

    return ResponseUtils.success(res, {
      user: {
        id: userData.id,
        name: userData.name,
        email: userData.email,
        profile_picture: userData.profile_picture,
        google_id: userData.google_id
      },
      token
    }, 'Google login successful');

  } catch (error) {
    console.error('Google login error:', error);
    return ResponseUtils.error(res, 'Google login failed: ' + error.message);
  }
}

async function verifyGoogleAccessToken(accessToken) {
  try {
    const response = await fetch('https://www.googleapis.com/oauth2/v2/userinfo', {
      headers: {
        'Authorization': `Bearer ${accessToken}`
      }
    });

    if (!response.ok) {
      console.error('Failed to get user info from Google');
      return false;
    }

    const userData = await response.json();

    if (!userData.id) {
      console.error('Invalid user data from Google - missing id');
      return false;
    }

    // Transform to match expected format
    return {
      sub: userData.id,
      email: userData.email || null,
      email_verified: userData.verified_email || false,
      name: userData.name || null,
      given_name: userData.given_name || null,
      family_name: userData.family_name || null,
      picture: userData.picture || null,
      locale: userData.locale || null
    };

  } catch (error) {
    console.error('Google access token verification error:', error.message);
    return false;
  }
}

async function createGoogleUser(googleUser, db) {
  try {
    // Generate a unique name from Google data
    const firstName = googleUser.given_name || '';
    const lastName = googleUser.family_name || '';
    const fullName = (googleUser.name || `${firstName} ${lastName}`.trim()) || 'Google User';

    // Insert new user
    const [result] = await db.execute(
      "INSERT INTO users (first_name, last_name, name, email, google_id, profile_picture, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())",
      [firstName, lastName, fullName, googleUser.email, googleUser.sub, googleUser.picture || null]
    );

    return result.insertId;

  } catch (error) {
    console.error('Create Google user error:', error.message);
    throw error;
  }
}

async function updateGoogleUserProfile(userId, googleUser, db) {
  try {
    // Update user profile with latest Google data
    await db.execute(
      "UPDATE users SET name = ?, profile_picture = ? WHERE id = ?",
      [googleUser.name || 'Google User', googleUser.picture || null, userId]
    );

  } catch (error) {
    console.error('Update Google user profile error:', error.message);
  }
}

async function linkGoogleAccount(userId, googleUser, db) {
  try {
    // Link Google account to existing user
    await db.execute(
      "UPDATE users SET google_id = ?, profile_picture = ? WHERE id = ?",
      [googleUser.sub, googleUser.picture || null, userId]
    );

  } catch (error) {
    console.error('Link Google account error:', error.message);
    throw error;
  }
}