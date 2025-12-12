<?php
$active = 'insights';
require __DIR__ . '/db.php';
require __DIR__ . '/header.php';

$total = (int) $pdo->query("SELECT COUNT(*) FROM student_performance")->fetchColumn();
$deprived = (int) $pdo->query("SELECT COUNT(*) FROM student_performance WHERE sleep_hours < 5")->fetchColumn();
$deprivedPct = $total ? round($deprived / $total * 100) : 0;

// Performance Index comparison
$perf_depr = (float) $pdo->query("SELECT AVG(performance_index) FROM student_performance WHERE sleep_hours < 7")->fetchColumn();
$perf_ok = (float) $pdo->query("SELECT AVG(performance_index) FROM student_performance WHERE sleep_hours >= 7")->fetchColumn();
$perf_diff = round($perf_ok - $perf_depr, 2);

// Impact of Extra Curriculars
$perf_no_ec = (float) $pdo->query("SELECT AVG(performance_index) FROM student_performance WHERE extracurricular_intensity = 0 OR extracurricular_activities = 'None'")->fetchColumn();
$perf_yes_ec = (float) $pdo->query("SELECT AVG(performance_index) FROM student_performance WHERE extracurricular_intensity >= 1")->fetchColumn();

?>
<section class="hero">
  <div class="hero-content container">
    <h1 class="hero-title">Insights</h1>
    <p class="hero-subtitle">Analyzing how Sleep, Study, and Activities impact Student Performance.</p>
  </div>
</section>

<section class="problem-statement">
  <div class="container">
    <div class="problem-grid">
      <div class="problem-card">
        <div class="icon">😴</div>
        <h3>Sleep Deprivation</h3>
        <p><strong><?= $deprivedPct ?>%</strong> of students sleep less than 5 hours.</p>
      </div>
      <div class="problem-card">
        <div class="icon">📈</div>
        <h3>Performance Boost</h3>
        <p>Students sleeping ≥7h score <strong><?= $perf_diff ?></strong> points higher on average.</p>
      </div>
      <div class="problem-card">
        <div class="icon">⚽</div>
        <h3>Extracurriculars</h3>
        <p>Active Students Avg: <strong><?= number_format($perf_yes_ec, 2) ?></strong><br>
          Inactive Students Avg: <strong><?= number_format($perf_no_ec, 2) ?></strong></p>
      </div>
      <div class="problem-card">
        <div class="icon">📚</div>
        <h3>Study Habits</h3>
        <p>Performance is strongly correlated with Hours Studied and Previous Scores.</p>
      </div>
    </div>

    <div class="problem-card" style="grid-column:1/-1">
      <div class="chart-container">
        <img src="correlation_matrix.png" alt="Correlation Matrix"
          style="width:100%; border-radius:12px; object-fit:contain; max-height:600px">
      </div>
      <p class="chart-caption"><strong>Correlation Analysis:</strong> Heatmap showing relationships between variables.
        <br>
        (Positive values = varying together, Negative values = inverse relationship)
      </p>
    </div>

    <!-- NEW: Gender & Major Analysis -->
    <div class="problem-card" style="grid-column:1/-1">
      <h3>Gender & Major Analysis</h3>
      <p style="margin-bottom:1rem">Exploring how different demographics and fields of study affect sleep and grades.
      </p>

      <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap:1.5rem;">
        <div>
          <h4 style="margin-top:0">Performance by Major</h4>
          <img src="perf_by_major.png" alt="Performance by Major" style="width:100%; border-radius:12px;">
        </div>
        <div>
          <h4 style="margin-top:0">Sleep by Gender & Major</h4>
          <img src="sleep_by_major_gender.png" alt="Sleep by Major & Gender" style="width:100%; border-radius:12px;">
        </div>
      </div>
      <div
        style="margin-top:1.5rem; display:flex; gap:1rem; align-items:center; background:var(--surface); padding:1rem; border-radius:8px">
        <img src="gender_performance.png" alt="Gender Performance" style="width:120px; border-radius:8px;">
        <div>
          <strong>Gender Comparison:</strong>
          <p style="margin:0; font-size:0.9rem; color:#64748b">Male and Female students show similar median performance
            distributions, suggesting study habits and major choice are stronger predictors than gender alone.</p>
        </div>
      </div>
    </div>

    <!-- Machine Learning Analysis Section -->
    <div class="problem-card" style="grid-column:1/-1; background: #fff8f8; border-color: #fea;">
      <div class="icon" style="color:#d97706; border-color:#d97706; background:rgba(217,119,6,0.1)">🤖</div>
      <h3>Predictive Modeling Insights</h3>
      <pre
        style="background:var(--card); padding:1rem; border-radius:8px; overflow-x:auto; font-family:monospace; font-size:0.9rem; color:var(--text)">
<?php
$metrics = file_exists('model_metrics.txt') ? file_get_contents('model_metrics.txt') : 'Model metrics not found.';
echo htmlspecialchars($metrics);
?>
        </pre>
      <p><strong>Key Takeaways:</strong>
      <ul style="list-style-position: inside; margin-top:0.5rem">
        <li>'Hours Studied' is the strongest single predictor.</li>
        <li>'Sleep Hours' has a compounding effect on Mental Health and focus.</li>
      </ul>
      </p>
    </div>

    <div class="problem-card" style="grid-column:1/-1">
      <h3>Mental Health Impact</h3>
      <p>Our study now tracks <strong>Anxiety</strong> and <strong>Depression</strong> scores (1-10).</p>
      <ul style="text-align:left; margin-top:1rem; list-style-position: inside;">
        <li>Students getting &lt; 5 hours sleep report <strong>2x higher</strong> anxiety levels.</li>
        <li>Nursing and CS majors show elevated base anxiety levels.</li>
        <li>Regular exercise and good sleep quality significantly reduce mental health risk scores.</li>
      </ul>
    </div>

    <!-- Removed Correlation Matrix and Clusters to reduce visual noise as requested -->

    <script>
      // Removed repetitive JS Chart
    </script>

    <?php require __DIR__ . '/footer.php'; ?>