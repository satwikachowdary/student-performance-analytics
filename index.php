<?php
$active = 'home';
require __DIR__ . '/db.php';
require __DIR__ . '/header.php';

// 1. Total Students
$total = (int) $pdo->query("SELECT COUNT(*) FROM student_performance")->fetchColumn();

// 2. Sleep Deprived % (Students < 7 hours)
$deprivedCount = (int) $pdo->query("SELECT COUNT(*) FROM student_performance WHERE sleep_hours < 7")->fetchColumn();
$deprivedPct = $total ? round($deprivedCount / $total * 100) : 0;

// 3. Performance Gap: High Sleep (>= 7h) vs Low Sleep (< 5h)
$perf_high_sleep = (float) $pdo->query("SELECT AVG(performance_index) FROM student_performance WHERE sleep_hours >= 7")->fetchColumn();
$perf_low_sleep = (float) $pdo->query("SELECT AVG(performance_index) FROM student_performance WHERE sleep_hours < 5")->fetchColumn();
$perf_gap = round($perf_high_sleep - $perf_low_sleep, 1);

// 4. Study Benefit: High Study (> 6h) vs Low Study (< 3h)
$perf_high_study = (float) $pdo->query("SELECT AVG(performance_index) FROM student_performance WHERE hours_studied > 6")->fetchColumn();
$perf_low_study = (float) $pdo->query("SELECT AVG(performance_index) FROM student_performance WHERE hours_studied < 3")->fetchColumn();

// 5. Chart 1: Performance by Sleep Hour
$sleepStm = $pdo->query("
    SELECT sleep_hours, AVG(performance_index) as avg_perf 
    FROM student_performance 
    GROUP BY sleep_hours 
    ORDER BY sleep_hours
");
$sleepData = $sleepStm->fetchAll(PDO::FETCH_ASSOC);

// 6. Chart 2: Extracurriculars Impact - Compare Active vs Inactive
$perf_active = (float) $pdo->query("
    SELECT AVG(performance_index) 
    FROM student_performance 
    WHERE extracurricular_intensity >= 1
")->fetchColumn();

$perf_inactive = (float) $pdo->query("
    SELECT AVG(performance_index) 
    FROM student_performance 
    WHERE extracurricular_intensity = 0 OR extracurricular_activities = 'None'
")->fetchColumn();

$ec_gap = round($perf_active - $perf_inactive, 2);

// For the chart, group by active vs inactive
$ecData = [
  ['extracurricular_activities' => 'Active', 'avg_perf' => $perf_active],
  ['extracurricular_activities' => 'Inactive', 'avg_perf' => $perf_inactive]
];
?>
<section class="hero">
  <div class="hero-content container">
    <h1 class="hero-title">The Performance Puzzle</h1>
    <p class="hero-subtitle">Unlocking the secrets of student success: Sleep, Study, and Balance.</p>
    <div class="hero-stats">
      <div class="stat-box">
        <h2><?= $deprivedPct ?>%</h2>
        <p>sleep less than 7 hours</p>
      </div>
      <div class="stat-box">
        <h2>+<?= $perf_gap ?></h2>
        <p>points for well-rested students</p>
      </div>
      <div class="stat-box">
        <h2><?= number_format($total) ?></h2>
        <p>students analyzed</p>
      </div>
    </div>
    <a href="assessment.php" class="cta-button">Predict Your Performance</a>
  </div>
</section>

<section class="problem-statement">
  <div class="container">
    <h2>Beneath the Grades</h2>
    <p class="lead">Our analysis of <?= number_format($total) ?> students reveals that while studying hard matters,
      sleeping well provides the critical edge needed to outperform.</p>

    <!-- Bento Grid Layout -->
    <div class="bento-grid">

      <!-- Chart 1: Performance (Wide Feature) -->
      <div class="problem-card feature-card">
        <div class="card-header">
          <div class="icon">📈</div>
          <div>
            <h3>Sleep: The Silent Grade Booster</h3>
            <p>Average Performance Index by Daily Sleep Hours</p>
          </div>
        </div>
        <div style="height:300px; width:100%; position:relative">
          <canvas id="sleepChart"></canvas>
        </div>
      </div>

      <!-- Chart 2: Health (Square) -->
      <div class="problem-card">
        <div class="icon">🧠</div>
        <h3>The Cost of All-Nighters</h3>
        <p>Mental Health risk doubles as sleep drops below 5h.</p>
        <img src="sleep_vs_health.png" alt="Sleep vs Health"
          style="width:100%; border-radius:10px; margin-top:auto; opacity:0.9; border:1px solid rgba(255,255,255,0.1)">
      </div>

      <!-- Chart 3: Active Advantage (Square) -->
      <div class="problem-card">
        <div class="icon">⚽</div>
        <h3>Active Advantage</h3>
        <p>Extracurricular activities provide a hidden boost.</p>
        <div style="margin-top:auto; padding:1rem 0;">
          <div style="font-size:3.5rem; font-weight:800; color:var(--accent-2)">+<?= $ec_gap ?></div>
          <div style="font-size:0.9rem; color:var(--muted)">GPA points higher for active students</div>
        </div>
      </div>

      <!-- Chart 4: Activities Impact (Wide Feature) -->
      <div class="problem-card wide-card">
        <div class="card-header">
          <div class="icon">🏃</div>
          <div>
            <h3>The Balance Bonus</h3>
            <p>Do activities hurt grades? The data says <strong>no</strong>.</p>
          </div>
        </div>
        <div style="height:220px; width:100%; position:relative">
          <canvas id="ecChart"></canvas>
        </div>
      </div>

    </div>
  </div>
</section>

<!-- Removed separate Major Impact section -->

<section class="cta-section">
  <div class="container">
    <h2>What's Your Score?</h2>
    <p>Enter your habits into our prediction model to see your estimated Performance Index.</p>
    <a href="assessment.php" class="cta-button secondary">Start Assessment</a>
  </div>
</section>

<script>
  document.addEventListener('DOMContentLoaded', function () {
    // Sleep Chart
    const sleepCanvas = document.getElementById('sleepChart');
    if (!sleepCanvas) {
      console.error('Sleep chart canvas not found!');
      return;
    }

    const ctx1 = sleepCanvas.getContext('2d');

    const sleepLabels = <?= json_encode(array_column($sleepData, 'sleep_hours')) ?>;
    const sleepValues = <?= json_encode(array_map(fn($r) => (float) $r['avg_perf'], $sleepData)) ?>;

    console.log('Sleep Data:', sleepLabels, sleepValues);

    // Create Gradient
    let gradient = ctx1.createLinearGradient(0, 0, 0, 400);
    gradient.addColorStop(0, 'rgba(96, 165, 250, 0.8)');
    gradient.addColorStop(1, 'rgba(96, 165, 250, 0.2)');

    new Chart(ctx1, {
      type: 'bar',
      data: {
        labels: sleepLabels.map(h => h + ' hrs'),
        datasets: [{
          label: 'Avg Performance Index',
          data: sleepValues,
          backgroundColor: gradient,
          borderColor: 'rgba(96, 165, 250, 1)',
          borderWidth: 1,
          borderRadius: 6,
          hoverBackgroundColor: 'rgba(96, 165, 250, 1)'
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false },
          tooltip: {
            backgroundColor: '#1e293b',
            padding: 12,
            titleFont: { size: 14 },
            bodyFont: { size: 14 },
            cornerRadius: 8,
            displayColors: false
          }
        },
        scales: {
          y: {
            beginAtZero: true,
            min: 0,
            max: 4,
            grid: { color: 'rgba(148, 163, 184, 0.1)' },
            ticks: { color: '#94a3b8' }
          },
          x: {
            grid: { display: false },
            ticks: { color: '#94a3b8' }
          }
        }
      }
    });

    // Extracurriculars Chart
    const ecCanvas = document.getElementById('ecChart');
    if (!ecCanvas) {
      console.error('EC chart canvas not found!');
      return;
    }

    const ctx2 = ecCanvas.getContext('2d');

    const ecLabels = <?= json_encode(array_column($ecData, 'extracurricular_activities')) ?>;
    const ecValues = <?= json_encode(array_map(fn($r) => (float) $r['avg_perf'], $ecData)) ?>;

    console.log('EC Data:', ecLabels, ecValues);

    // EC Gradient
    let ecGradient = ctx2.createLinearGradient(0, 0, 400, 0); // Horizontal gradient
    ecGradient.addColorStop(0, 'rgba(52, 211, 153, 0.8)');
    ecGradient.addColorStop(1, 'rgba(251, 146, 60, 0.8)');

    new Chart(ctx2, {
      type: 'bar',
      data: {
        labels: ecLabels,
        datasets: [{
          label: 'Avg Performance Index',
          data: ecValues,
          backgroundColor: [
            'rgba(52, 211, 153, 0.8)', // Yes - Green
            'rgba(248, 113, 113, 0.8)'  // No - Redish/Orange
          ],
          borderColor: 'rgba(255,255,255,0.1)',
          borderWidth: 1,
          borderRadius: 6,
          barPercentage: 0.9,
          categoryPercentage: 0.9
        }]
      },
      options: {
        indexAxis: 'y',
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false },
          tooltip: {
            backgroundColor: '#1e293b',
            padding: 12,
            titleFont: { size: 14 },
            bodyFont: { size: 14 },
            cornerRadius: 8,
            displayColors: false
          }
        },
        scales: {
          x: {
            beginAtZero: true,
            min: 0,
            max: 4,
            grid: { color: 'rgba(148, 163, 184, 0.1)' },
            ticks: { color: '#94a3b8' }
          },
          y: {
            grid: { display: false },
            ticks: { color: '#e2e8f0', font: { size: 14, weight: 'bold' } }
          }
        }
      }
    });
  });
</script>

<?php require __DIR__ . '/footer.php'; ?>