// api/progress.js
const { getDbConnection } = require('./_utils/database');
const AuthUtils = require('./_utils/auth');
const ResponseUtils = require('./_utils/response');

module.exports = async (req, res) => {
  const action = req.query.action || 'stats';
  const db = await getDbConnection();

  try {
    switch (action) {
      case 'stats':
        return await handleStats(req, res, db);
      case 'startSession':
        return await handleStartSession(req, res, db);
      case 'endSession':
        return await handleEndSession(req, res, db);
      case 'sessions':
        return await handleSessions(req, res, db);
      default:
        return ResponseUtils.badRequest(res, 'Invalid action');
    }
  } catch (error) {
    console.error('Progress error:', error);
    return ResponseUtils.error(res, 'Progress operation failed: ' + error.message);
  }
};

async function handleStats(req, res, db) {
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

    // Get comprehensive stats using a single query
    const [stats] = await db.execute(`
      SELECT
        (SELECT COUNT(*) FROM notes WHERE user_id = ?) as total_notes,
        (SELECT COUNT(*) FROM notes WHERE user_id = ? AND is_favorite = 1) as favorite_notes,
        (SELECT COUNT(*) FROM summaries WHERE user_id = ?) as total_summaries,
        (SELECT COUNT(*) FROM quizzes WHERE user_id = ?) as total_quizzes,
        (SELECT COUNT(*) FROM study_sessions WHERE user_id = ?) as total_sessions,
        (SELECT COUNT(*) FROM learning_goals WHERE user_id = ? AND status = 'completed') as completed_goals,
        (SELECT COUNT(*) FROM learning_goals WHERE user_id = ? AND status = 'active') as active_goals
    `, [userId, userId, userId, userId, userId, userId, userId]);

    const result = stats[0];

    // Get notes created this week
    const weekAgo = new Date();
    weekAgo.setDate(weekAgo.getDate() - 7);

    const [weeklyNotes] = await db.execute(
      "SELECT COUNT(*) as count FROM notes WHERE user_id = ? AND created_at >= ?",
      [userId, weekAgo]
    );

    // Get quiz statistics
    const [quizStats] = await db.execute(`
      SELECT
        COUNT(*) as total_quizzes,
        AVG(score) as average_score,
        COUNT(CASE WHEN score >= 80 THEN 1 END) as high_scores
      FROM quizzes q
      INNER JOIN notes n ON q.note_id = n.id
      WHERE n.user_id = ?
    `, [userId]);

    // Get study time statistics
    const [studyTime] = await db.execute(`
      SELECT
        SUM(duration_minutes) as total_minutes,
        COUNT(*) as total_sessions,
        AVG(duration_minutes) as avg_session_duration,
        MAX(duration_minutes) as longest_session,
        SUM(notes_studied) as total_notes_studied,
        SUM(quizzes_taken) as total_quizzes_taken
      FROM study_sessions
      WHERE user_id = ?
    `, [userId]);

    const [weeklyStudyTime] = await db.execute(`
      SELECT SUM(duration_minutes) as weekly_minutes
      FROM study_sessions
      WHERE user_id = ? AND session_date >= ?
    `, [userId, weekAgo.toISOString().split('T')[0]]);

    // Calculate study streak
    const studyStreak = await calculateStudyStreak(userId, db);

    // Get weekly activity data (last 7 days)
    const weeklyActivity = await getWeeklyActivity(userId, db);

    // Get subject distribution
    const subjects = await getSubjectDistribution(userId, db);

    // Get recent activity
    const recentActivity = await getRecentActivity(userId, db);

    const statsResponse = {
      total_notes: parseInt(result.total_notes) || 0,
      favorite_notes: parseInt(result.favorite_notes) || 0,
      total_summaries: parseInt(result.total_summaries) || 0,
      total_quizzes: parseInt(result.total_quizzes) || 0,
      total_sessions: parseInt(result.total_sessions) || 0,
      completed_goals: parseInt(result.completed_goals) || 0,
      active_goals: parseInt(result.active_goals) || 0,
      notes_this_week: parseInt(weeklyNotes[0].count) || 0,
      study_hours: Math.round((parseInt(studyTime[0].total_minutes) || 0) / 60 * 10) / 10,
      study_hours_this_week: Math.round((parseInt(weeklyStudyTime[0].weekly_minutes) || 0) / 60 * 10) / 10,
      quiz_average: Math.round((quizStats[0].average_score || 0) * 10) / 10,
      quizzes_completed: parseInt(quizStats[0].total_quizzes) || 0,
      study_streak: studyStreak,
      avg_session_duration: Math.round((parseInt(studyTime[0].avg_session_duration) || 0) * 10) / 10,
      longest_session: parseInt(studyTime[0].longest_session) || 0,
      total_notes_studied: parseInt(studyTime[0].total_notes_studied) || 0,
      total_quizzes_taken: parseInt(studyTime[0].total_quizzes_taken) || 0,
      weekly_activity: weeklyActivity,
      subjects: subjects,
      recent_activity: recentActivity,
      last_updated: new Date().toISOString()
    };

    return ResponseUtils.success(res, statsResponse, 'Progress stats retrieved successfully');

  } catch (error) {
    console.error('Progress stats error:', error);
    return ResponseUtils.error(res, 'Failed to retrieve progress stats: ' + error.message);
  }
}

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
    const data = req.body;

    // Check if user already has an active session
    const [activeSessions] = await db.execute(
      "SELECT id FROM study_sessions WHERE user_id = ? AND end_time IS NULL",
      [userId]
    );

    if (activeSessions.length > 0) {
      return ResponseUtils.badRequest(res, 'User already has an active study session');
    }

    // Create new study session
    const activities = JSON.stringify(data.activities || []);
    const startTime = data.startTime || new Date().toISOString();
    const sessionDate = new Date(startTime).toISOString().split('T')[0];

    const [result] = await db.execute(`
      INSERT INTO study_sessions (
        user_id, session_date, start_time, activities, notes_studied, quizzes_taken, created_at
      ) VALUES (?, ?, ?, ?, 0, 0, NOW())
    `, [userId, sessionDate, startTime, activities]);

    const sessionId = result.insertId;

    if (sessionId) {
      return ResponseUtils.success(res, {
        session_id: sessionId,
        start_time: startTime,
        message: 'Study session started successfully'
      }, 'Study session started successfully', 201);
    } else {
      return ResponseUtils.error(res, 'Failed to create study session');
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
    const data = req.body;

    if (!data.session_id) {
      return ResponseUtils.badRequest(res, 'Session ID is required');
    }

    // Get the active session
    const [sessions] = await db.execute(`
      SELECT id, start_time, activities, notes_studied, quizzes_taken
      FROM study_sessions
      WHERE id = ? AND user_id = ? AND end_time IS NULL
    `, [data.session_id, userId]);

    const session = sessions[0];
    if (!session) {
      return ResponseUtils.notFound(res, 'Active study session not found');
    }

    // Calculate duration
    const startTime = new Date(session.start_time);
    const endTime = new Date();
    const durationMinutes = Math.floor((endTime - startTime) / (1000 * 60));

    // Update the session with end time and duration
    const [result] = await db.execute(`
      UPDATE study_sessions
      SET end_time = ?, duration_minutes = ?, notes_studied = ?, quizzes_taken = ?
      WHERE id = ? AND user_id = ?
    `, [
      endTime.toISOString(),
      durationMinutes,
      session.notes_studied,
      session.quizzes_taken,
      data.session_id,
      userId
    ]);

    if (result.affectedRows > 0) {
      return ResponseUtils.success(res, {
        session_id: data.session_id,
        duration_minutes: durationMinutes,
        end_time: endTime.toISOString(),
        message: 'Study session ended successfully'
      }, 'Study session ended successfully');
    } else {
      return ResponseUtils.error(res, 'Failed to end study session');
    }

  } catch (error) {
    console.error('End session error:', error);
    return ResponseUtils.error(res, 'Failed to end study session: ' + error.message);
  }
}

async function handleSessions(req, res, db) {
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

    // Get study sessions for the user
    const [sessions] = await db.execute(`
      SELECT
        id, session_date, start_time, end_time, duration_minutes,
        activities, notes_studied, quizzes_taken, created_at
      FROM study_sessions
      WHERE user_id = ?
      ORDER BY session_date DESC, start_time DESC
    `, [userId]);

    // Parse JSON activities for each session
    const processedSessions = sessions.map(session => ({
      ...session,
      activities: JSON.parse(session.activities || '[]')
    }));

    return ResponseUtils.success(res, processedSessions, 'Study sessions retrieved successfully');

  } catch (error) {
    console.error('Sessions error:', error);
    return ResponseUtils.error(res, 'Failed to retrieve study sessions: ' + error.message);
  }
}

async function calculateStudyStreak(userId, db) {
  try {
    const [sessionDates] = await db.execute(`
      SELECT DISTINCT DATE(session_date) as session_date
      FROM study_sessions
      WHERE user_id = ?
      ORDER BY session_date DESC
    `, [userId]);

    if (sessionDates.length === 0) {
      return 0;
    }

    const today = new Date().toISOString().split('T')[0];
    const yesterday = new Date(Date.now() - 24 * 60 * 60 * 1000).toISOString().split('T')[0];

    let streak = 0;
    let currentDate = null;

    for (const session of sessionDates) {
      const sessionDate = session.session_date;

      if (currentDate === null) {
        // First date in the list
        streak = 1;
        currentDate = sessionDate;
      } else if (sessionDate === new Date(Date.parse(currentDate) - 24 * 60 * 60 * 1000).toISOString().split('T')[0]) {
        // Consecutive day
        streak++;
        currentDate = sessionDate;
      } else if (sessionDate !== currentDate) {
        // Gap in dates, streak broken
        break;
      }
    }

    // Check if today or yesterday has a session to continue the streak
    const mostRecentDate = sessionDates[0].session_date;

    if (mostRecentDate !== today && mostRecentDate !== yesterday) {
      return 0; // Streak broken
    }

    return streak;

  } catch (error) {
    console.error('Calculate study streak error:', error);
    return 0;
  }
}

async function getWeeklyActivity(userId, db) {
  try {
    const weeklyActivity = [];

    for (let i = 6; i >= 0; i--) {
      const date = new Date();
      date.setDate(date.getDate() - i);
      const dateStr = date.toISOString().split('T')[0];

      const [activity] = await db.execute(
        "SELECT COUNT(*) as count FROM notes WHERE user_id = ? AND DATE(created_at) = ?",
        [userId, dateStr]
      );

      const count = parseInt(activity[0].count) || 0;
      weeklyActivity.push({
        name: date.toLocaleDateString('en-US', { weekday: 'short' }),
        activity: count > 0 ? Math.min(count * 0.1, 1.0) : 0 // Normalize activity
      });
    }

    return weeklyActivity;

  } catch (error) {
    console.error('Get weekly activity error:', error);
    return [];
  }
}

async function getSubjectDistribution(userId, db) {
  try {
    const [subjectsData] = await db.execute(`
      SELECT
        COUNT(*) as count,
        CASE
          WHEN LOWER(title) LIKE '%biology%' OR LOWER(original_text) LIKE '%biology%' THEN 'Biology'
          WHEN LOWER(title) LIKE '%history%' OR LOWER(original_text) LIKE '%history%' THEN 'History'
          WHEN LOWER(title) LIKE '%math%' OR LOWER(original_text) LIKE '%math%' THEN 'Mathematics'
          WHEN LOWER(title) LIKE '%physics%' OR LOWER(original_text) LIKE '%physics%' THEN 'Physics'
          WHEN LOWER(title) LIKE '%literature%' OR LOWER(original_text) LIKE '%literature%' THEN 'Literature'
          ELSE 'Other'
        END as subject
      FROM notes
      WHERE user_id = ?
      GROUP BY subject
      ORDER BY count DESC
      LIMIT 5
    `, [userId]);

    const totalNotes = subjectsData.reduce((sum, subject) => sum + subject.count, 0);
    const subjects = [];
    const colors = ['bg-green-500', 'bg-blue-500', 'bg-yellow-500', 'bg-purple-500', 'bg-red-500'];

    for (let i = 0; i < subjectsData.length; i++) {
      const subject = subjectsData[i];
      const percentage = totalNotes > 0 ? Math.round((subject.count / totalNotes) * 100 * 10) / 10 : 0;

      subjects.push({
        name: subject.subject,
        percentage: percentage,
        color: colors[i % colors.length]
      });
    }

    return subjects;

  } catch (error) {
    console.error('Get subject distribution error:', error);
    return [];
  }
}

async function getRecentActivity(userId, db) {
  try {
    // Get recent notes
    const [recentNotes] = await db.execute(`
      SELECT
        title,
        created_at,
        'note_created' as activity_type
      FROM notes
      WHERE user_id = ?
      ORDER BY created_at DESC
      LIMIT 5
    `, [userId]);

    // Format recent activity
    const recentActivity = [];
    for (const note of recentNotes) {
      recentActivity.push({
        title: 'Created note: ' + (note.title.length > 30 ? note.title.substring(0, 30) + '...' : note.title),
        time: formatTimeAgo(note.created_at),
        icon: ['fas', 'file-alt'],
        iconColor: 'bg-blue-600'
      });
    }

    // If no recent activity, add a welcome message
    if (recentActivity.length === 0) {
      recentActivity.push({
        title: 'Welcome to SmartScribe!',
        time: 'Just now',
        icon: ['fas', 'rocket'],
        iconColor: 'bg-green-600'
      });
    }

    return recentActivity;

  } catch (error) {
    console.error('Get recent activity error:', error);
    return [];
  }
}

function formatTimeAgo(datetime) {
  const now = new Date();
  const created = new Date(datetime);
  const diffInSeconds = Math.floor((now - created) / 1000);

  if (diffInSeconds < 60) {
    return 'Just now';
  } else if (diffInSeconds < 3600) {
    const minutes = Math.floor(diffInSeconds / 60);
    return `${minutes} minute${minutes > 1 ? 's' : ''} ago`;
  } else if (diffInSeconds < 86400) {
    const hours = Math.floor(diffInSeconds / 3600);
    return `${hours} hour${hours > 1 ? 's' : ''} ago`;
  } else {
    const days = Math.floor(diffInSeconds / 86400);
    return `${days} day${days > 1 ? 's' : ''} ago`;
  }
}