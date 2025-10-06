<?php
// run_migrations.php - Script to run database migrations

require_once __DIR__ . '/api/config/database.php';

echo "=== SmartScribe Database Migration Runner ===\n\n";

try {
    $db = getDbConnection();
    echo "✅ Database connection established\n\n";

    // List of migration files to run
    $migrations = [
        'api/migrations/create_study_sessions_table.sql',
        'api/migrations/create_learning_goals_table.sql',
        'api/migrations/create_progress_metrics_table.sql',
        'api/migrations/create_achievements_table.sql',
        'api/migrations/add_google_id_to_users.sql',
        'api/migrations/add_password_reset_tokens.sql'
    ];

    foreach ($migrations as $migration) {
        if (file_exists($migration)) {
            echo "📄 Running migration: $migration\n";

            $sql = file_get_contents($migration);

            // Remove comments and split SQL into individual statements
            $sql = preg_replace('/--.*$/m', '', $sql); // Remove single-line comments
            $statements = array_filter(array_map('trim', explode(';', $sql)));

            foreach ($statements as $statement) {
                if (!empty($statement)) {
                    try {
                        $db->exec($statement);
                        echo "  ✅ Executed: " . substr($statement, 0, 50) . "...\n";
                    } catch (Exception $e) {
                        echo "  ❌ Error executing statement: " . $e->getMessage() . "\n";
                        echo "     Statement: " . substr($statement, 0, 100) . "...\n";
                    }
                }
            }

            echo "✅ Migration completed: $migration\n\n";
        } else {
            echo "❌ Migration file not found: $migration\n\n";
        }
    }

    echo "🎉 All migrations completed successfully!\n";
    echo "\n📊 New tables created:\n";
    echo "  - study_sessions\n";
    echo "  - learning_goals\n";
    echo "  - progress_metrics\n";
    echo "  - achievements\n";
    echo "  - user_tokens\n";

} catch (Exception $e) {
    echo "❌ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n🚀 Your SmartScribe progress tracking system is now ready!\n";
?>