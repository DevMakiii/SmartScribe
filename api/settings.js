// api/settings.js
const { getDbConnection } = require('./_utils/database');
const AuthUtils = require('./_utils/auth');
const ResponseUtils = require('./_utils/response');

module.exports = async (req, res) => {
  const action = req.query.action || 'get';
  const db = await getDbConnection();

  try {
    switch (action) {
      case 'get':
        return await handleGetSettings(req, res, db);
      case 'update':
        return await handleUpdateSettings(req, res, db);
      default:
        return ResponseUtils.badRequest(res, 'Invalid action');
    }
  } catch (error) {
    console.error('Settings error:', error);
    return ResponseUtils.error(res, 'Settings operation failed: ' + error.message);
  }
};

async function handleGetSettings(req, res, db) {
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

    // Ensure settings table exists
    await ensureSettingsTableExists(db);

    // Get user settings
    const [settings] = await db.execute(
      "SELECT settings FROM user_settings WHERE user_id = ?",
      [userId]
    );

    if (settings.length > 0 && settings[0].settings) {
      const userSettings = JSON.parse(settings[0].settings);
      return ResponseUtils.success(res, userSettings, 'Settings retrieved successfully');
    } else {
      // Return default settings
      const defaultSettings = {
        fontSize: 16,
        theme: 'dark',
        notifications: {
          weeklySummary: false,
          studyReminders: false,
          newFeatures: false,
          quizResults: false,
          goalProgress: false
        },
        api: {
          openaiKey: '',
          openaiModel: 'gpt-3.5-turbo',
          ocrEngine: 'tesseract',
          ocrKey: ''
        }
      };

      return ResponseUtils.success(res, defaultSettings, 'Default settings retrieved successfully');
    }

  } catch (error) {
    console.error('Get settings error:', error);
    return ResponseUtils.error(res, 'Failed to fetch settings: ' + error.message);
  }
}

async function handleUpdateSettings(req, res, db) {
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
    const data = req.body;

    if (!data) {
      return ResponseUtils.badRequest(res, 'Invalid JSON data');
    }

    // Ensure settings table exists
    await ensureSettingsTableExists(db);

    const settingsJson = JSON.stringify(data);

    // Insert or update settings
    const [result] = await db.execute(`
      INSERT INTO user_settings (user_id, settings, created_at, updated_at)
      VALUES (?, ?, NOW(), NOW())
      ON DUPLICATE KEY UPDATE
      settings = ?, updated_at = NOW()
    `, [userId, settingsJson, settingsJson]);

    if (result.affectedRows > 0) {
      return ResponseUtils.success(res, null, 'Settings updated successfully');
    } else {
      return ResponseUtils.error(res, 'Failed to update settings');
    }

  } catch (error) {
    console.error('Update settings error:', error);
    return ResponseUtils.error(res, 'Failed to update settings: ' + error.message);
  }
}

async function ensureSettingsTableExists(db) {
  try {
    await db.execute(`
      CREATE TABLE IF NOT EXISTS user_settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL UNIQUE,
        settings JSON,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
      )
    `);
  } catch (error) {
    console.error('Error creating settings table:', error);
    throw error;
  }
}