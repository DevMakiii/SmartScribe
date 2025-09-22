// api/study-sessions.js
const { getDbConnection } = require('./_utils/database');
const AuthUtils = require('./_utils/auth');
const ResponseUtils = require('./_utils/response');

module.exports = async (req, res) => {
  const action = req.query.action || 'index';
  const db = await getDbConnection();

  try {
    switch (action) {
      case 'start':
        return await handleStartSession(req, res, db);
      case 'end':
        return await handleEndSession(req, res, db);
      case 'active':
        return await handleGetActiveSession(req, res, db);
      case 'update-activity':
        return await handleUpdateActivity(req, res, db);
      case 'index':
        return await handleGetUserSessions(req, res, db);
      case 'stats':
        return await handleGetStats(req, res, db);
      case 'daily-stats':
        return await handleGetDailyStats(req, res, db);
      case 'streak':
        return await handleGetStreak(req, res, db);
      case 'show':
        return await handleGetSession(req, res, db);
      case 'update':
        return await handleUpdateSession(req, res, db);
      case 'destroy':
        return await handleDeleteSession(req, res, db);
      default:
        return ResponseUtils.badRequest(res, 'Invalid action');
    }
  } catch (error) {
    console.error('Study sessions error:', error);
    return ResponseUtils.error(res, 'Study sessions operation failed: ' + error.message);
  }
};

async function handleStartSession(req, res, db) {
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
    const data = req.body || {};
    const activity = data.activity || 'general_study';

    // Check if user already has an active session
    const [activeSessions] = await db.execute(
      "SELECT id, start_time, activities FROM study_sessions WHERE user_id = ? AND end_time IS NULL",
      [userId]
    );

    if (activeSessions.length > 0) {
      return ResponseUtils.success(res, activeSessions[0], 'Resumed existing active session');
    }

    // Start new session
    const sessionDate = new Date().toISOString().split('T')[0];
    const startTime = new Date().toISOString();
    const activities = JSON.stringify([activity]);

    const [result] = await db.execute(`
      INSERT INTO study_sessions (
        user_id, session_date, start_time, activities, notes_studied, quizzes_taken, created_at
      ) VALUES (?, ?, ?, ?, 0, 0, NOW())
    `, [userId, sessionDate, startTime, activities]);

    const sessionId = result.insertId;

    if (sessionId) {
      const [session] = await db.execute(
        "SELECT * FROM study_sessions WHERE id = ? AND user_id = ?",
        [sessionId, userId]
      );

      return ResponseUtils.success(res, session[0], 'Study session started successfully');
    } else {
      return ResponseUtils.error(res, 'Failed to start study session');
    }

  } catch (error) {
    console.error('Start session error:', error);
    return ResponseUtils.error(res, 'Failed to start study session: ' + error.message);
  }
}

async function handleEndSession(req, res, db) {
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
    const data = req.body || {};

    let sessionId = data.session_id;
    const notesStudied = data.notes_studied || 0;
    const quizzesTaken = data.quizzes_taken || 0;
    const averageScore = data.average_score || null;
    const focusLevel = data.focus_level || 'medium';

    // If no session_id provided, try to find active session
    if (!sessionId) {
      const [activeSessions] = await db.execute(
        "SELECT id FROM study_sessions WHERE user_id = ? AND end_time IS NULL",
        [userId]
      );

      if (activeSessions.length > 0) {
        sessionId = activeSessions[0].id;
      } else {
        return ResponseUtils.error(res, 'No active session found', 404);
      }
    }

    // Get the active session
    const [sessions] = await db.execute(
      "SELECT id, start_time FROM study_sessions WHERE id = ? AND user_id = ? AND end_time IS NULL",
      [sessionId, userId]
    );

    const session = sessions[0];
    if (!session) {
      return ResponseUtils.notFound(res, 'Active study session not found');
    }

    // Calculate duration
    const startTime = new Date(session.start_time);
    const endTime = new Date();
    const durationMinutes = Math.floor((endTime - startTime) / (1000 * 60));

    // End the session
    const [result] = await db.execute(`
      UPDATE study_sessions
      SET end_time = ?, duration_minutes = ?, notes_studied = ?, quizzes_taken = ?,
          average_score = ?, focus_level = ?
      WHERE id = ? AND user_id = ?
    `, [
      endTime.toISOString(),
      durationMinutes,
      notesStudied,
      quizzesTaken,
      averageScore,
      focusLevel,
      sessionId,
      userId
    ]);

    if (result.affectedRows > 0) {
      // Update goal progress for study time and accuracy
      try {
        await updateGoalProgress(userId, db);
      } catch (error) {
        console.error('Error updating goals:', error.message);
        // Don't fail the session end if goal update fails
      }

      const [updatedSession] = await db.execute(
        "SELECT * FROM study_sessions WHERE id = ? AND user_id = ?",
        [sessionId, userId]
      );

      return ResponseUtils.success(res, updatedSession[0], 'Study session ended successfully');
    } else {
      return ResponseUtils.error(res, 'Failed to end study session');
    }

  } catch (error) {
    console.error('End session error:', error);
    return ResponseUtils.error(res, 'Failed to end study session: ' + error.message);
  }
}

async function handleGetActiveSession(req, res, db) {
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

    const [activeSessions] = await db.execute(
      "SELECT * FROM study_sessions WHERE user_id = ? AND end_time IS NULL ORDER BY start_time DESC LIMIT 1",
      [userId]
    );

    if (activeSessions.length > 0) {
      return ResponseUtils.success(res, activeSessions[0], 'Active session found');
    } else {
      return ResponseUtils.success(res, null, 'No active session');
    }

  } catch (error) {
    console.error('Get active session error:', error);
    return ResponseUtils.error(res, 'Failed to get active session: ' + error.message);
  }
}

async function handleUpdateActivity(req, res, db) {
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
    const data = req.body || {};

    // Validate required fields
    const validationErrors = AuthUtils.validateRequired(data, ['session_id', 'activity']);
    if (Object.keys(validationErrors).length > 0) {
      return ResponseUtils.validationError(res, validationErrors);
    }

    const sessionId = data.session_id;
    const activity = data.activity;

    // Get current activities
    const [sessions] = await db.execute(
      "SELECT activities FROM study_sessions WHERE id = ? AND user_id = ?",
      [sessionId, userId]
    );

    if (sessions.length === 0) {
      return ResponseUtils.notFound(res, 'Session not found');
    }

    const currentActivities = JSON.parse(sessions[0].activities || '[]');

    // Add new activity if not already present
    if (!currentActivities.includes(activity)) {
      currentActivities.push(activity);
    }

    const updatedActivities = JSON.stringify(currentActivities);

    // Update activities
    const [result] = await db.execute(
      "UPDATE study_sessions SET activities = ? WHERE id = ? AND user_id = ?",
      [updatedActivities, sessionId, userId]
    );

    if (result.affectedRows > 0) {
      const [updatedSession] = await db.execute(
        "SELECT * FROM study_sessions WHERE id = ? AND user_id = ?",
        [sessionId, userId]
      );

      return ResponseUtils.success(res, updatedSession[0], 'Activity updated successfully');
    } else {
      return ResponseUtils.error(res, 'Failed to update activity');
    }

  } catch (error) {
    console.error('Update activity error:', error);
    return ResponseUtils.error(res, 'Failed to update activity: ' + error.message);
  }
}

async function handleGetUserSessions(req, res, db) {
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

    // Get query parameters
    const startDate = req.query.start_date;
    const endDate = req.query.end_date;
    const limit = req.query.limit ? parseInt(req.query.limit) : null;

    let query = "SELECT * FROM study_sessions WHERE user_id = ?";
    const params = [userId];

    if (startDate) {
      query += " AND session_date >= ?";
      params.push(startDate);
    }

    if (endDate) {
      query += " AND session_date <= ?";
      params.push(endDate);
    }

    query += " ORDER BY session_date DESC, start_time DESC";

    if (limit) {
      query += " LIMIT ?";
      params.push(limit);
    }

    const [sessions] = await db.execute(query, params);

    // Parse JSON activities for each session
    const processedSessions = sessions.map(session => ({
      ...session,
      activities: JSON.parse(session.activities || '[]')
    }));

    return ResponseUtils.success(res, processedSessions, 'Study sessions retrieved successfully');

  } catch (error) {
    console.error('Get user sessions error:', error);
    return ResponseUtils.error(res, 'Failed to retrieve study sessions: ' + error.message);
  }
}

async function handleGetStats(req, res, db) {
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

    // Get query parameters
    const startDate = req.query.start_date;
    const endDate = req.query.end_date;

    let query = `
      SELECT
        COUNT(*) as total_sessions,
        SUM(duration_minutes) as total_minutes,
        AVG(duration_minutes) as avg_session_minutes,
        SUM(notes_studied) as total_notes_studied,
        SUM(quizzes_taken) as total_quizzes_taken,
        AVG(average_score) as avg_quiz_score,
        MAX(end_time) as last_session_date
      FROM study_sessions
      WHERE user_id = ?
    `;
    const params = [userId];

    if (startDate) {
      query += " AND session_date >= ?";
      params.push(startDate);
    }

    if (endDate) {
      query += " AND session_date <= ?";
      params.push(endDate);
    }

    const [stats] = await db.execute(query, params);
    const result = stats[0];

    const statsResponse = {
      total_sessions: parseInt(result.total_sessions) || 0,
      total_minutes: parseInt(result.total_minutes) || 0,
      total_hours: Math.round((parseInt(result.total_minutes) || 0) / 60 * 10) / 10,
      avg_session_minutes: Math.round((parseInt(result.avg_session_minutes) || 0) * 10) / 10,
      avg_session_hours: Math.round((parseInt(result.avg_session_minutes) || 0) / 60 * 10) / 10,
      total_notes_studied: parseInt(result.total_notes_studied) || 0,
      total_quizzes_taken: parseInt(result.total_quizzes_taken) || 0,
      avg_quiz_score: result.avg_quiz_score ? Math.round(parseFloat(result.avg_quiz_score) * 10) / 10 : null,
      last_session_date: result.last_session_date
    };

    return ResponseUtils.success(res, statsResponse, 'Study statistics retrieved successfully');

  } catch (error) {
    console.error('Get stats error:', error);
    return ResponseUtils.error(res, 'Failed to retrieve study statistics: ' + error.message);
  }
}

async function handleGetDailyStats(req, res, db) {
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

    // Get query parameters
    const startDate = req.query.start_date || new Date(Date.now() - 30 * 24 * 60 * 60 * 1000).toISOString().split('T')[0];
    const endDate = req.query.end_date || new Date().toISOString().split('T')[0];

    const [dailyStats] = await db.execute(`
      SELECT
        session_date,
        COUNT(*) as sessions_count,
        SUM(duration_minutes) as total_minutes,
        SUM(notes_studied) as notes_studied,
        SUM(quizzes_taken) as quizzes_taken,
        AVG(average_score) as avg_score
      FROM study_sessions
      WHERE user_id = ? AND session_date BETWEEN ? AND ?
      GROUP BY session_date
      ORDER BY session_date
    `, [userId, startDate, endDate]);

    return ResponseUtils.success(res, dailyStats, 'Daily study statistics retrieved successfully');

  } catch (error) {
    console.error('Get daily stats error:', error);
    return ResponseUtils.error(res, 'Failed to retrieve daily statistics: ' + error.message);
  }
}

async function handleGetStreak(req, res, db) {
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

    const [sessionDates] = await db.execute(`
      SELECT DISTINCT DATE(session_date) as session_date
      FROM study_sessions
      WHERE user_id = ?
      ORDER BY session_date DESC
    `, [userId]);

    if (sessionDates.length === 0) {
      return ResponseUtils.success(res, { current_streak: 0, longest_streak: 0 }, 'Study streak information retrieved successfully');
    }

    const today = new Date().toISOString().split('T')[0];
    const yesterday = new Date(Date.now() - 24 * 60 * 60 * 1000).toISOString().split('T')[0];

    let currentStreak = 0;
    let longestStreak = 0;
    let tempStreak = 1;
    let lastDate = null;

    for (let i = 0; i < sessionDates.length; i++) {
      const sessionDate = sessionDates[i].session_date;

      if (lastDate === null) {
        lastDate = sessionDate;
        currentStreak = 1;
      } else if (sessionDate === new Date(Date.parse(lastDate) - 24 * 60 * 60 * 1000).toISOString().split('T')[0]) {
        currentStreak++;
        lastDate = sessionDate;
      } else if (sessionDate !== lastDate) {
        longestStreak = Math.max(longestStreak, currentStreak);
        currentStreak = 1;
        lastDate = sessionDate;
      }
    }

    longestStreak = Math.max(longestStreak, currentStreak);

    // Check if today or yesterday has a session to continue the streak
    const mostRecentDate = sessionDates[0].session_date;

    if (mostRecentDate !== today && mostRecentDate !== yesterday) {
      currentStreak = 0;
    }

    return ResponseUtils.success(res, {
      current_streak: currentStreak,
      longest_streak: longestStreak
    }, 'Study streak information retrieved successfully');

  } catch (error) {
    console.error('Get streak error:', error);
    return ResponseUtils.error(res, 'Failed to retrieve study streak: ' + error.message);
  }
}

async function handleGetSession(req, res, db) {
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
    const sessionId = req.query.id;

    if (!sessionId) {
      return ResponseUtils.badRequest(res, 'Session ID is required');
    }

    const [sessions] = await db.execute(
      "SELECT * FROM study_sessions WHERE id = ? AND user_id = ?",
      [sessionId, userId]
    );

    const session = sessions[0];
    if (!session) {
      return ResponseUtils.notFound(res, 'Session not found');
    }

    // Parse JSON activities
    session.activities = JSON.parse(session.activities || '[]');

    return ResponseUtils.success(res, session, 'Session details retrieved successfully');

  } catch (error) {
    console.error('Get session error:', error);
    return ResponseUtils.error(res, 'Failed to retrieve session: ' + error.message);
  }
}

async function handleUpdateSession(req, res, db) {
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
    const sessionId = req.query.id;

    if (!sessionId) {
      return ResponseUtils.badRequest(res, 'Session ID is required');
    }

    const data = req.body || {};

    // Build update query dynamically
    const updateFields = [];
    const params = [];

    if (data.notes_studied !== undefined) {
      updateFields.push("notes_studied = ?");
      params.push(data.notes_studied);
    }

    if (data.quizzes_taken !== undefined) {
      updateFields.push("quizzes_taken = ?");
      params.push(data.quizzes_taken);
    }

    if (data.average_score !== undefined) {
      updateFields.push("average_score = ?");
      params.push(data.average_score);
    }

    if (data.focus_level !== undefined) {
      updateFields.push("focus_level = ?");
      params.push(data.focus_level);
    }

    if (updateFields.length === 0) {
      return ResponseUtils.badRequest(res, 'No valid fields to update');
    }

    params.push(sessionId, userId);
    const query = `UPDATE study_sessions SET ${updateFields.join(', ')} WHERE id = ? AND user_id = ?`;

    const [result] = await db.execute(query, params);

    if (result.affectedRows > 0) {
      const [updatedSession] = await db.execute(
        "SELECT * FROM study_sessions WHERE id = ? AND user_id = ?",
        [sessionId, userId]
      );

      return ResponseUtils.success(res, updatedSession[0], 'Session updated successfully');
    } else {
      return ResponseUtils.error(res, 'Failed to update session');
    }

  } catch (error) {
    console.error('Update session error:', error);
    return ResponseUtils.error(res, 'Failed to update session: ' + error.message);
  }
}

async function handleDeleteSession(req, res, db) {
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
    const sessionId = req.query.id;

    if (!sessionId) {
      return ResponseUtils.badRequest(res, 'Session ID is required');
    }

    const [result] = await db.execute(
      "DELETE FROM study_sessions WHERE id = ? AND user_id = ?",
      [sessionId, userId]
    );

    if (result.affectedRows > 0) {
      return ResponseUtils.success(res, null, 'Session deleted successfully');
    } else {
      return ResponseUtils.error(res, 'Failed to delete session');
    }

  } catch (error) {
    console.error('Delete session error:', error);
    return ResponseUtils.error(res, 'Failed to delete session: ' + error.message);
  }
}

async function updateGoalProgress(userId, db) {
  try {
    // Update goal progress for study time
    const [goals] = await db.execute(`
      SELECT id, title, target_value, current_value, status
      FROM learning_goals
      WHERE user_id = ? AND target_type = 'study_time' AND status = 'active'
    `, [userId]);

    for (const goal of goals) {
      const newCurrentValue = goal.current_value + 1;
      const newStatus = newCurrentValue >= goal.target_value ? 'completed' : 'active';

      await db.execute(
        "UPDATE learning_goals SET current_value = ?, status = ? WHERE id = ?",
        [newCurrentValue, newStatus, goal.id]
      );
    }

    return goals.length;

  } catch (error) {
    console.error('Update goal progress error:', error);
    throw error;
  }
}