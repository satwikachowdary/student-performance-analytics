.mode csv
.import Student_Performance.csv student_performance
-- Delete header row if imported
DELETE FROM student_performance WHERE hours_studied = 'Hours Studied';
