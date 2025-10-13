<?php
// setup_database.php - Complete database setup for SmartScribe
require_once __DIR__ . '/api/config/database.php';

echo "=== SmartScribe Database Setup ===\n\n";

try {
    $db = getDbConnection();
    echo "✅ Database connection established to 'smartscribe_new'\n\n";

    // List of all migration files to run in order
    $migrations = [
        'api/migrations/create_users_table.sql',
        'api/migrations/create_user_tokens_table.sql',
        'api/migrations/create_notes_table.sql',
        'api/migrations/add_keywords_to_notes.sql',
        'api/migrations/create_summaries_table.sql',
        'api/migrations/add_user_id_and_format_to_summaries.sql',
        'api/migrations/create_study_sessions_table.sql',
        'api/migrations/create_learning_goals_table.sql',
        'api/migrations/create_progress_metrics_table.sql',
        'api/migrations/create_achievements_table.sql',
        'api/migrations/create_quizzes_table.sql',
        'api/migrations/add_name_fields_to_users.sql',
        'api/migrations/add_profile_picture_to_users.sql',
        'api/migrations/cleanup_users_table.sql'
    ];

    $executedCount = 0;

    foreach ($migrations as $migration) {
        if (file_exists($migration)) {
            echo "📄 Running migration: $migration\n";

            $sql = file_get_contents($migration);

            // Split SQL into individual statements
            $statements = array_filter(array_map('trim', explode(';', $sql)));

            foreach ($statements as $statement) {
                if (!empty($statement) && !preg_match('/^--/', $statement)) {
                    try {
                        $db->exec($statement);
                        echo "  ✅ Executed: " . substr($statement, 0, 60) . "...\n";
                        $executedCount++;
                    } catch (Exception $e) {
                        // Check if it's a duplicate key error (which is OK for INSERT statements)
                        if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                            echo "  ⚠️  Skipped duplicate entry: " . substr($statement, 0, 60) . "...\n";
                        } else {
                            echo "  ❌ Error executing statement: " . $e->getMessage() . "\n";
                            echo "     Statement: " . substr($statement, 0, 100) . "...\n";
                        }
                    }
                }
            }

            echo "✅ Migration completed: $migration\n\n";
        } else {
            echo "❌ Migration file not found: $migration\n\n";
        }
    }

    echo "🎉 Database setup completed!\n";
    echo "📊 Total statements executed: $executedCount\n\n";

    // Verify tables were created
    echo "=== Verifying Tables ===\n";
    $tables = $db->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);

    $expectedTables = [
        'users', 'user_tokens', 'notes', 'summaries', 'study_sessions', 'learning_goals',
        'progress_metrics', 'achievements', 'quizzes'
    ];

    foreach ($expectedTables as $table) {
        if (in_array($table, $tables)) {
            echo "✅ $table - EXISTS\n";
        } else {
            echo "❌ $table - MISSING\n";
        }
    }

    // Verify users exist
    echo "\n=== Verifying Users ===\n";
    $stmt = $db->query('SELECT COUNT(*) as count FROM users');
    $result = $stmt->fetch();
    echo "Total users: " . $result['count'] . "\n";

    if ($result['count'] > 0) {
        $stmt = $db->prepare('SELECT id, name, email FROM users LIMIT 5');
        $stmt->execute();
        $users = $stmt->fetchAll();

        echo "Sample users:\n";
        foreach ($users as $user) {
            echo "  ID: {$user['id']}, Name: {$user['name']}, Email: {$user['email']}\n";
        }
    }

    echo "\n🚀 Your SmartScribe database is now ready!\n";
    echo "🔐 Test login credentials:\n";
    echo "   Email: test@example.com\n";
    echo "   Password: password123\n";
    echo "   Email: john@example.com\n";
    echo "   Password: password123\n";

} catch (Exception $e) {
    echo "❌ Database setup failed: " . $e->getMessage() . "\n";
    echo "💡 Make sure:\n";
    echo "   1. MySQL server is running\n";
    echo "   2. Database 'smartscribe_new' exists\n";
    echo "   3. MySQL user 'root' has access to the database\n";
    exit(1);
}

echo "\n=== Next Steps ===\n";
echo "1. Start your web server (Apache/XAMPP)\n";
echo "2. Open your browser and go to the login page\n";
echo "3. Try logging in with the test credentials above\n";
echo "4. If you encounter issues, check the PHP error logs\n";
?>