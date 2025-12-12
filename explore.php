<?php
$active = 'explore';
require __DIR__ . '/db.php';
require __DIR__ . '/header.php';

$extracurricular = $_GET['extracurricular'] ?? '';
$gender = $_GET['gender'] ?? '';
$major = $_GET['major'] ?? '';

// 1. CHART QUERY: Aggregate Performance by Sleep Hours
$q_chart = "
    SELECT sleep_hours, AVG(performance_index) as avg_perf
    FROM student_performance
    WHERE 1=1
";
$params_chart = [];

if ($extracurricular) {
  if ($extracurricular === 'Highly Active') {
    $q_chart .= " AND extracurricular_intensity >= 8";
  } elseif ($extracurricular === 'Semi Active') {
    $q_chart .= " AND extracurricular_intensity >= 4 AND extracurricular_intensity < 8";
  } elseif ($extracurricular === 'Inactive') {
    $q_chart .= " AND extracurricular_intensity < 4";
  }
}
if ($gender) {
  $q_chart .= " AND gender = :gn";
  $params_chart[':gn'] = $gender;
}
if ($major) {
  $q_chart .= " AND major = :mj";
  $params_chart[':mj'] = $major;
}

$q_chart .= " GROUP BY sleep_hours ORDER BY sleep_hours ASC";
$stmt_chart = $pdo->prepare($q_chart);
$stmt_chart->execute($params_chart);
$chart_data = $stmt_chart->fetchAll(PDO::FETCH_ASSOC);


// 2. TABLE QUERY: Raw Data
$q = "
    SELECT 
        id, 
        hours_studied,
        previous_scores,
        extracurricular_activities, 
        extracurricular_intensity,
        sleep_hours, 
        sleep_quality,
        anxiety_score,
        depression_score,
        performance_index,
        gender,
        major
    FROM student_performance
    WHERE 1=1
";
$params = [];

if ($extracurricular) {
  if ($extracurricular === 'Highly Active') {
    $q .= " AND extracurricular_intensity >= 8";
  } elseif ($extracurricular === 'Semi Active') {
    $q .= " AND extracurricular_intensity >= 4 AND extracurricular_intensity < 8";
  } elseif ($extracurricular === 'Inactive') {
    $q .= " AND extracurricular_intensity < 4";
  }
}
if ($gender) {
  $q .= " AND gender = :gn";
  $params[':gn'] = $gender;
}
if ($major) {
  $q .= " AND major = :mj";
  $params[':mj'] = $major;
}

$q .= " ORDER BY id DESC LIMIT 20";

$stmt = $pdo->prepare($q);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Remove specific activity query as we now use static categories
$majors = $pdo->query("SELECT DISTINCT major FROM student_performance ORDER BY major")->fetchAll(PDO::FETCH_COLUMN);
?>
<section class="hero">
  <div class="hero-content container">
    <h1 class="hero-title">Explore the Data</h1>
    <p class="hero-subtitle">Filter, browse, and visualize the student performance dataset.</p>
  </div>
</section>

<section class="problem-statement">
  <div class="container">
    <form method="get" class="problem-grid"
      style="grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap:1rem; align-items:end">

      <!-- Activity Filter -->
      <div class="problem-card" style="padding:0.8rem; margin:0">
        <label style="font-size:0.9rem"><strong>Activity Level</strong><br>
          <select name="extracurricular"
            style="width:100%;padding:.5rem;border-radius:8px;border:1px solid #cbd5e1;margin-top:0.4rem">
            <option value="">All</option>
            <option value="Highly Active" <?= $extracurricular === 'Highly Active' ? 'selected' : '' ?>>Highly Active
            </option>
            <option value="Semi Active" <?= $extracurricular === 'Semi Active' ? 'selected' : '' ?>>Semi Active</option>
            <option value="Inactive" <?= $extracurricular === 'Inactive' ? 'selected' : '' ?>>Inactive</option>
          </select>
        </label>
      </div>

      <!-- Gender Filter -->
      <div class="problem-card" style="padding:0.8rem; margin:0">
        <label style="font-size:0.9rem"><strong>Gender</strong><br>
          <select name="gender"
            style="width:100%;padding:.5rem;border-radius:8px;border:1px solid #cbd5e1;margin-top:0.4rem">
            <option value="">All</option>
            <option value="Male" <?= $gender === 'Male' ? 'selected' : '' ?>>Male</option>
            <option value="Female" <?= $gender === 'Female' ? 'selected' : '' ?>>Female</option>
          </select>
        </label>
      </div>

      <!-- Major Filter -->
      <div class="problem-card" style="padding:0.8rem; margin:0">
        <label style="font-size:0.9rem"><strong>Major</strong><br>
          <select name="major"
            style="width:100%;padding:.5rem;border-radius:8px;border:1px solid #cbd5e1;margin-top:0.4rem">
            <option value="">All</option>
            <?php foreach ($majors as $m): ?>
              <option value="<?= htmlspecialchars($m) ?>" <?= $major === $m ? 'selected' : '' ?>>
                <?= htmlspecialchars($m) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </label>
      </div>

      <div style="padding-bottom:1rem">
        <button class="cta-button" type="submit" style="width:100%">Filter Data</button>
      </div>
    </form>

    <div class="problem-card" style="grid-column:1/-1">
      <div class="chart-container">
        <canvas id="lineChart"></canvas>
      </div>
      <p class="chart-caption"><strong>Trend Analysis:</strong> Average Performance Index vs Sleep Hours (based on
        current filters)</p>
    </div>

    <div class="problem-card" style="grid-column:1/-1; overflow:auto">
      <table style="width:100%; border-collapse:collapse; white-space:nowrap">
        <thead>
          <tr style="background:#f1f5f9; color:#475569;">
            <th style="text-align:left;padding:1rem;">ID</th>
            <th style="padding:1rem;">Gender/Major</th>
            <th style="text-align:right;padding:1rem;">Hours Studied</th>
            <th style="text-align:left;padding:1rem;">Extracurriculars</th>
            <th style="text-align:right;padding:1rem;">Sleep (hrs)</th>
            <th style="text-align:right;padding:1rem;">Anxiety</th>
            <th style="text-align:right;padding:1rem;">Depression</th>
            <th style="text-align:right;padding:1rem;">Performance (GPA)</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $r): ?>
            <tr style="border-bottom:1px solid #e2e8f0;">
              <td style="padding:1rem;color:#64748b"><?= htmlspecialchars($r['id']) ?></td>
              <td style="padding:1rem;">
                <span style="font-weight:bold; color:var(--text)"><?= htmlspecialchars($r['major']) ?></span><br>
                <span style="font-size:0.85rem; color:#64748b"><?= htmlspecialchars($r['gender']) ?></span>
              </td>
              <td style="padding:1rem;text-align:right"><?= (int) $r['hours_studied'] ?></td>
              <td style="padding:1rem;">
                <?php
                $score = (int) $r['extracurricular_intensity'];
                if ($score >= 8) {
                  echo '<span style="color:#166534; font-weight:bold; background:#dcfce7; padding:4px 8px; border-radius:12px">Highly Active</span>';
                } elseif ($score >= 4) {
                  echo '<span style="color:#0369a1; font-weight:bold; background:#e0f2fe; padding:4px 8px; border-radius:12px">Semi Active</span>';
                } else {
                  echo '<span style="color:#64748b; background:#f1f5f9; padding:4px 8px; border-radius:12px">Inactive</span>';
                }
                ?>
              </td>
              <td style="padding:1rem;text-align:right"><?= (int) $r['sleep_hours'] ?></td>
              <td style="padding:1rem;text-align:right;color:#dc2626"><?= (int) $r['anxiety_score'] ?></td>
              <td style="padding:1rem;text-align:right;color:#dc2626"><?= (int) $r['depression_score'] ?></td>
              <td style="padding:1rem;text-align:right">
                <strong><?= number_format((float) $r['performance_index'], 2) ?></strong>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</section>

<script>
  const labels = [<?php foreach ($chart_data as $d)
    echo $d['sleep_hours'] . ','; ?>];
  const data = [<?php foreach ($chart_data as $d)
    echo $d['avg_perf'] . ','; ?>];

  new Chart(document.getElementById('lineChart').getContext('2d'), {
    type: 'line',
    data: {
      labels: labels,
      datasets: [{
        label: 'Average GPA (0-4.0)',
        data: data,
        borderColor: 'rgba(99, 102, 241, 1)',
        backgroundColor: 'rgba(99, 102, 241, 0.2)',
        tension: 0.4,
        fill: true,
        pointBackgroundColor: '#fff',
        pointBorderWidth: 2,
        pointRadius: 5
      }]
    },
    options: {
      plugins: {
        legend: { display: false },
        title: { display: true, text: 'Sleep vs Performance Trend (Higher is Better)' },
        tooltip: {
          callbacks: {
            label: function (context) {
              return 'Avg GPA: ' + context.parsed.y.toFixed(2);
            }
          }
        }
      },
      scales: {
        x: {
          title: { display: true, text: 'Sleep (Hours)' }
        },
        y: {
          title: { display: true, text: 'Avg GPA' },
          min: 0.0,
          max: 4.0
        }
      }
    }
  });
</script>

<?php require __DIR__ . '/footer.php'; ?>