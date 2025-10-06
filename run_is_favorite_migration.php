<?php
// Script to run the add_is_favorite_to_notes migration

require_once __DIR__ . '/api/config/database.php';

echo "=== Running add_is_favorite_to_notes migration ===\n\n";

try {
    $db = getDbConnection();
    echo "✅ Database connection established\n\n";

    $migrationFile = 'api/migrations/add_is_favorite_to_notes.sql';

    if (file_exists($migrationFile)) {
        echo "📄 Running migration: $migrationFile\n";

        $sql = file_get_contents($migrationFile);

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

        echo "✅ Migration completed: $migrationFile\n\n";
    } else {
        echo "❌ Migration file not found: $migrationFile\n\n";
    }

    echo "🎉 Migration completed successfully!\n";

} catch (Exception $e) {
    echo "❌ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
?>