<?php
// init_db.php — Imports the CSV data into SQLite
require __DIR__ . '/db.php';

// Increase memory limit and execution time for large import
ini_set('memory_limit', '1024M');
set_time_limit(0);

$csvFile = __DIR__ . '/Student_Performance.csv';
if (!file_exists($csvFile)) {
    die("Error: Student_Performance.csv not found. Run prepare_data.py first.");
}

echo "Setting up database...<br>\n";

try {
    // 1. Create Table
    $pdo->exec("DROP TABLE IF EXISTS daily_logs");
    $pdo->exec("DROP TABLE IF EXISTS student_performance");

    // Create new table matching Student Performance dataset
    $pdo->exec("CREATE TABLE student_performance (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        student_id INTEGER,
        age INTEGER,
        hours_studied INTEGER,
        study_efficiency INTEGER,
        previous_scores REAL,
        extracurricular_activities TEXT,
        extracurricular_intensity INTEGER,
        sleep_hours INTEGER,
        sleep_quality INTEGER,
        anxiety_score INTEGER,
        depression_score INTEGER,
        performance_index REAL,
        gender TEXT,
        major TEXT
    )");
    echo "Table 'student_performance' created successfully.<br>";

    // 2. Import CSV
    echo "Importing data from CSV (this may take a while)...<br>\n";

    $handle = fopen($csvFile, "r");
    // Skip header
    fgetcsv($handle);

    $sql = "INSERT INTO student_performance (student_id, age, hours_studied, study_efficiency, previous_scores, extracurricular_activities, extracurricular_intensity, sleep_hours, sleep_quality, anxiety_score, depression_score, performance_index, gender, major) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $pdo->prepare($sql);

    $batchSize = 1000;
    $count = 0;

    $pdo->beginTransaction();

    while (($row = fgetcsv($handle)) !== FALSE) {
        if (count($row) < 14) // Expecting 14 columns now with Age
            continue;
        $stmt->execute([
            $row[0], // Student_ID
            $row[1], // Age
            $row[2], // Hours Studied
            $row[3], // Study Efficiency
            $row[4], // Previous Scores
            $row[5], // Extracurricular Activities
            $row[6], // Extracurricular Intensity
            $row[7], // Sleep Hours
            $row[8], // Sleep Quality
            $row[9], // Anxiety
            $row[10], // Depression
            $row[11], // Performance Index
            $row[12], // Gender
            $row[13]  // Major
        ]);

        $count++;
        if ($count % $batchSize == 0) {
            $pdo->commit();
            $pdo->beginTransaction();
            if ($count % 10000 == 0)
                echo "Imported $count rows...<br>\n";
        }
    }

    $pdo->commit();
    fclose($handle);

    echo "Success! Imported $count rows into 'student_performance'.";

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    die("DB Error: " . $e->getMessage());
}
