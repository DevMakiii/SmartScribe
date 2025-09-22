// api/dashboard.js
const { getDbConnection } = require('./_utils/database');
const AuthUtils = require('./_utils/auth');
const ResponseUtils = require('./_utils/response');

module.exports = async (req, res) => {
  const action = req.query.action || 'index';
  const db = await getDbConnection();

  try {
    switch (action) {
      case 'stats':
        return await handleStats(req, res, db);
      case 'recent':
        return await handleRecent(req, res, db);
      case 'progress':
        return await handleProgress(req, res, db);
      default:
        return ResponseUtils.badRequest(res, 'Invalid action');
    }
  } catch (error) {
    console.error('Dashboard error:', error);
    return ResponseUtils.error(res, 'Dashboard operation failed: ' + error.message);
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
      recent_activity: recentActivity,
      last_updated: new Date().toISOString()
    };

    return ResponseUtils.success(res, statsResponse, 'Dashboard stats retrieved successfully');

  } catch (error) {
    console.error('Dashboard stats error:', error);
    return ResponseUtils.error(res, 'Failed to retrieve dashboard stats: ' + error.message);
  }
}

async function handleRecent(req, res, db) {
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

    // Get recent notes
    const [recentNotes] = await db.execute(`
      SELECT id, title, original_text, created_at, is_favorite
      FROM notes
      WHERE user_id = ?
      ORDER BY created_at DESC
      LIMIT 5
    `, [userId]);

    // Get recent study sessions
    const [recentSessions] = await db.execute(`
      SELECT id, title, duration_minutes, created_at
      FROM study_sessions
      WHERE user_id = ?
      ORDER BY created_at DESC
      LIMIT 5
    `, [userId]);

    return ResponseUtils.success(res, {
      recent_notes: recentNotes,
      recent_sessions: recentSessions
    }, 'Recent activity retrieved successfully');

  } catch (error) {
    console.error('Dashboard recent error:', error);
    return ResponseUtils.error(res, 'Failed to retrieve recent activity: ' + error.message);
  }
}

async function handleProgress(req, res, db) {
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

    // Get progress data for charts
    const [progressData] = await db.execute(`
      SELECT
        DATE_FORMAT(created_at, '%Y-%m-%d') as date,
        COUNT(*) as notes_count,
        SUM(CASE WHEN is_favorite = 1 THEN 1 ELSE 0 END) as favorite_count
      FROM notes
      WHERE user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
      GROUP BY DATE_FORMAT(created_at, '%Y-%m-%d')
      ORDER BY date
    `, [userId]);

    // Get study session progress
    const [studyProgress] = await db.execute(`
      SELECT
        DATE_FORMAT(session_date, '%Y-%m-%d') as date,
        SUM(duration_minutes) as study_minutes,
        COUNT(*) as sessions_count
      FROM study_sessions
      WHERE user_id = ? AND session_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
      GROUP BY DATE_FORMAT(session_date, '%Y-%m-%d')
      ORDER BY date
    `, [userId]);

    return ResponseUtils.success(res, {
      notes_progress: progressData,
      study_progress: studyProgress
    }, 'Progress data retrieved successfully');

  } catch (error) {
    console.error('Dashboard progress error:', error);
    return ResponseUtils.error(res, 'Failed to retrieve progress data: ' + error.message);
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