<?php
$active = 'assessment';
require __DIR__ . '/db.php';
require __DIR__ . '/header.php';

$errors = [];
$success = false;
$predicted_index = null;
$predicted_anxiety = null;
$predicted_depression = null;
$sentiment_result = null;

// Peer Comparison Data
$peer_sleep_avg = (float) $pdo->query("SELECT AVG(sleep_hours) FROM student_performance WHERE performance_index >= 80")->fetchColumn();
$peer_quality_avg = (float) $pdo->query("SELECT AVG(sleep_quality) FROM student_performance WHERE performance_index >= 80")->fetchColumn();


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $hours_studied = (int) ($_POST['hours_studied'] ?? 0);
  $study_efficiency = (int) ($_POST['study_efficiency'] ?? 5);
  $age = (int) ($_POST['age'] ?? 20); // Default to 20 if missing
  $previous_scores = (float) ($_POST['previous_scores'] ?? 0.0);
  $extra_name = $_POST['extra_name'] ?? 'None';
  $extra_intensity = 0;

  if (empty(trim($extra_name))) {
    $extra_name = 'None';
  } else {
    // AI Classifier
    $escaped_activity = escapeshellarg($extra_name);
    $activity_output = shell_exec("python3 classify_activity.py $escaped_activity");
    $activity_json = json_decode($activity_output, true);
    $extra_intensity = $activity_json['intensity'] ?? 4; // Default to 4 if failed
  }

  $sleep_hours = (int) ($_POST['sleep_hours'] ?? 0);
  $sleep_quality = (int) ($_POST['sleep_quality'] ?? 5);
  $gender = $_POST['gender'] ?? 'Male';
  $major = $_POST['major'] ?? 'Other';

  // GAD-2 Inputs
  $gad1 = (int) ($_POST['gad1'] ?? 0);
  $gad2 = (int) ($_POST['gad2'] ?? 0);

  // PHQ-2 Inputs
  $phq1 = (int) ($_POST['phq1'] ?? 0);
  $phq2 = (int) ($_POST['phq2'] ?? 0);

  $screen_time = (float) ($_POST['screen_time'] ?? 0);
  $grogginess = $_POST['grogginess'] ?? 'No';
  $journal_entry = $_POST['journal'] ?? '';

  // New lifestyle questions
  $caffeine_intake = (int) ($_POST['caffeine_intake'] ?? 0);
  $exercise_minutes = (int) ($_POST['exercise_minutes'] ?? 0);
  $last_meal_time = $_POST['last_meal_time'] ?? 'Before 7 PM';
  $daily_stress = (int) ($_POST['daily_stress'] ?? 5);
  $social_interaction = $_POST['social_interaction'] ?? 'Moderate';


  // Validation
  if ($hours_studied < 0 || $hours_studied > 24)
    $errors[] = 'Hours studied must be 0-24.';
  if ($previous_scores < 0 || $previous_scores > 4.0)
    $errors[] = 'Previous GPA must be 0.0-4.0.';
  if ($sleep_hours < 0 || $sleep_hours > 24)
    $errors[] = 'Sleep hours must be 0-24.';
  if ($sleep_quality < 1 || $sleep_quality > 10)
    $errors[] = 'Sleep Quality must be 1-10.';

  if (!$errors) {
    // --- 1. NLP Sentiment Analysis ---
    $sentiment_score = 0;
    $sentiment_feedback = "";
    if ($journal_entry) {
      $escaped_entry = escapeshellarg($journal_entry);
      $output = shell_exec("python3 sentiment.py $escaped_entry");
      $json = json_decode($output, true);
      if ($json && isset($json['score'])) {
        $sentiment_score = $json['score']; // -1 to 1
        $sentiment_feedback = $sentiment_score < -0.2 ? "Negative (High Stress)" : ($sentiment_score > 0.2 ? "Positive (Resilient)" : "Neutral");
      }
    }

    // --- 2. Mental Health Model (Python) ---
    $mental_input = json_encode([
      'gad1' => $gad1,
      'gad2' => $gad2,
      'phq1' => $phq1,
      'phq2' => $phq2
    ]);
    $escaped_mental_input = escapeshellarg($mental_input);
    $mental_output = shell_exec("python3 mental_model.py $escaped_mental_input");
    $mental_json = json_decode($mental_output, true);

    $reported_anxiety = $mental_json['anxiety_score'] ?? 5;
    $reported_depression = $mental_json['depression_score'] ?? 5;

    // --- 3. Performance Prediction (GPA) ---
    $base = 0.5;
    $ec_val = ($extra_intensity * 0.01);
    $groggy_penalty = ($grogginess === 'Yes') ? 2 : 0;
    $adjusted_quality = max(1, $sleep_quality - $groggy_penalty);

    // Base Calculation for GPA
    $score = $base +
      (0.08 * $hours_studied) +
      (0.6 * $previous_scores) +
      $ec_val +
      (0.01 * $sleep_hours) +
      (0.005 * $adjusted_quality);

    // Penalties (scaled down)
    $screen_penalty = $screen_time * 0.02; // max ~0.24
    $mood_impact = $sentiment_score * 0.1; // +/- 0.1 GPA

    // Lifestyle factors
    $exercise_boost = ($exercise_minutes >= 30) ? 0.05 : 0; // Bonus for regular exercise
    $caffeine_penalty = ($caffeine_intake > 3) ? 0.03 : 0; // Excessive caffeine
    $meal_penalty = ($last_meal_time === 'After 9 PM') ? 0.04 : 0; // Late eating

    $score = $score - $screen_penalty + $mood_impact + $exercise_boost - $caffeine_penalty - $meal_penalty;

    $predicted_index = max(0.0, min(4.0, round($score, 2)));


    // --- 3. Mental Health Prediction ---
    $sleep_deficit = max(0, 7 - $sleep_hours);

    // Anxiety
    $anxiety_score = 2 + ($sleep_deficit * 1.5);
    $anxiety_score += ($screen_time > 4 ? 1 : 0);
    if ($sleep_quality < 5)
      $anxiety_score += 1.5;
    // NLP Impact: Negative sentiment increases anxiety
    if ($sentiment_score < -0.3)
      $anxiety_score += 2;
    elseif ($sentiment_score > 0.3)
      $anxiety_score -= 1;

    $predicted_anxiety = min(10, max(1, round($anxiety_score)));

    // Depression
    $depression_score = 2 + ($sleep_deficit * 1.2);
    if ($sleep_quality < 4)
      $depression_score += 1;
    // NLP Impact
    if ($sentiment_score < -0.5)
      $depression_score += 2;

    $predicted_depression = min(10, max(1, round($depression_score)));


    // --- 4. Suggestions Logic ---
    $suggestions = [];
    if ($sleep_hours < 7) {
      $suggestions[] = "😴 <strong>Prioritize Sleep:</strong> Your sleep is below the recommended 7-9 hours. Try setting a consistent bedtime to improve focus.";
    }
    if ($study_efficiency < 6) {
      $suggestions[] = "🍅 <strong>Boost Efficiency:</strong> Try the Pomodoro technique (25m work / 5m break) to improve your study quality.";
    }
    if ($predicted_anxiety > 5 || $predicted_depression > 5) {
      $suggestions[] = "🧘 <strong>Mindfulness:</strong> Your stress markers are high. Consider 10 minutes of daily meditation or deep breathing exercises.";
    }
    if ($hours_studied > 0 && $hours_studied < 3) {
      $suggestions[] = "📚 <strong>Study Routine:</strong> Try to increase your daily study time slightly to reinforce learning materials.";
    }
    if ($screen_time > 4) {
      $suggestions[] = "📱 <strong>Digital Detox:</strong> High screen time before bed can disrupt sleep. Try the 20-20-20 rule: every 20 mins, look 20 feet away for 20 seconds.";
    }
    if ($sleep_quality < 5) {
      $suggestions[] = "🛏️ <strong>Sleep Hygiene:</strong> Low sleep quality detected. Keep your room cool (60-67°F), dark, and avoid caffeine after 2 PM.";
    }
    if ($sentiment_score < -0.2) {
      $suggestions[] = "💭 <strong>Mental Reset:</strong> Your journal shows stress patterns. Try journaling gratitude or talking to a friend about what's bothering you.";
    }
    if ($caffeine_intake > 2) {
      $suggestions[] = "☕ <strong>Caffeine Control:</strong> High caffeine intake can affect sleep quality. Try switching to decaf after 2 PM.";
    }
    if ($exercise_minutes < 30) {
      $suggestions[] = "🏃 <strong>Get Moving:</strong> Regular exercise improves sleep quality. Aim for at least 30 minutes of moderate activity daily.";
    }
    if ($last_meal_time === 'After 9 PM') {
      $suggestions[] = "🍽️ <strong>Meal Timing:</strong> Late meals can disrupt sleep. Try eating dinner at least 3 hours before bedtime.";
    }
    if ($daily_stress > 7) {
      $suggestions[] = "🌿 <strong>Stress Management:</strong> Your stress levels are high. Consider time-blocking, delegation, or speaking with a counselor.";
    }
    if ($social_interaction === 'Very Low') {
      $suggestions[] = "👥 <strong>Social Connection:</strong> Low social interaction can impact mental health. Try joining a study group or campus club.";
    }
    if (empty($suggestions)) {
      $suggestions[] = "🌟 <strong>Keep it up!</strong> Your habits look balanced. Continue maintaining your healthy routine.";
    }

    if ($age < 20) {
      $suggestions[] = "🧠 <strong>Developing Brain:</strong> At age $age, your brain is still maturing. Prioritize consistency in sleep to support cognitive growth.";
    } elseif ($age > 22) {
      $suggestions[] = "📅 <strong>Adulting & Sleep:</strong> As you get older ($age), recovery takes longer. Don't skimp on rest after stressful days.";
    }

    // Insert into DB (Storing USER REPORTED values for Anxiety/Depression, and Gender)
    $stmt = $pdo->prepare("
            INSERT INTO student_performance (
                student_id, age,
                hours_studied, study_efficiency, previous_scores, extracurricular_activities, extracurricular_intensity,
                sleep_hours, sleep_quality, anxiety_score, depression_score, 
                performance_index, gender, major
            ) VALUES (:sid, :age, :hs, :se, :ps, :ec, :ei, :sh, :sq, :as, :ds, :pi, :gn, :mj)
        ");
    $stmt->execute([
      ':sid' => rand(5000, 9999), // Simulate ID
      ':age' => $age,
      ':hs' => $hours_studied,
      ':se' => $study_efficiency,
      ':ps' => $previous_scores,
      ':ec' => $extra_name,
      ':ei' => $extra_intensity,
      ':sh' => $sleep_hours,
      ':sq' => $sleep_quality,
      ':as' => $reported_anxiety, // Store User's Self-Report
      ':ds' => $reported_depression, // Store User's Self-Report
      ':pi' => $predicted_index,
      ':gn' => $gender,
      ':mj' => $major
    ]);
    $success = true;
  }
}
?>

<section class="hero">
  <div class="hero-content container">
    <h1 class="hero-title">Sleep & Health Assessment</h1>
    <p class="hero-subtitle">Check if your sleep is "Normal" compared to high-performing peers.</p>
  </div>
</section>

<section class="problem-statement">
  <div class="container">
    <?php if ($success): ?>
      <div class="problem-card"
        style="border-color: rgba(52,211,153,.5); background: linear-gradient(135deg, rgba(52,211,153,.1), rgba(52,211,153,.05));">
        <div class="icon" style="color: #059669;">⚖️</div>
        <h3 style="margin-bottom:1rem">Peer Comparison Results</h3>

        <!-- New Efficient Peer Comparison -->
        <div
          style="background:white; padding:1.5rem; border-radius:12px; box-shadow:0 2px 10px rgba(0,0,0,0.03); margin-bottom:1rem">
          <h4 style="margin-top:0; color:var(--accent); display:flex; align-items:center; gap:0.5rem">
            <span>📊</span> Peer Benchmark
          </h4>

          <div style="display:grid; grid-template-columns: 1fr 1fr; gap:2rem; margin-top:1rem">

            <!-- Sleep Hours Stat -->
            <div>
              <div style="font-size:0.85rem; color:#64748b; margin-bottom:0.2rem">Sleep Duration
              </div>
              <div style="display:flex; align-items:baseline; gap:0.5rem">
                <span style="font-size:1.4rem; font-weight:bold; color:var(--text)">
                  <?= $sleep_hours ?>h
                </span>
                <span
                  style="font-size:0.85rem; padding:2px 8px; border-radius:12px; font-weight:bold; <?= $sleep_hours >= $peer_sleep_avg ? 'background:#dcfce7; color:#166534' : 'background:#fee2e2; color:#991b1b' ?>">
                  <?= $sleep_hours >= $peer_sleep_avg ? 'Above Avg' : 'Below Avg' ?>
                </span>
              </div>
              <div style="font-size:0.8rem; color:#94a3b8; margin-top:0.2rem">
                Target: <strong>
                  <?= round($peer_sleep_avg, 1) ?>h
                </strong> (Top Students)
              </div>
            </div>

            <!-- Sleep Quality Stat -->
            <div>
              <div style="font-size:0.85rem; color:#64748b; margin-bottom:0.2rem">Sleep Quality
              </div>
              <div style="display:flex; align-items:baseline; gap:0.5rem">
                <span style="font-size:1.4rem; font-weight:bold; color:var(--text)">
                  <?= $sleep_quality ?>/10
                </span>
                <span
                  style="font-size:0.85rem; padding:2px 8px; border-radius:12px; font-weight:bold; <?= $sleep_quality >= $peer_quality_avg ? 'background:#dcfce7; color:#166534' : 'background:#fee2e2; color:#991b1b' ?>">
                  <?= $sleep_quality >= $peer_quality_avg ? 'Great' : 'Needs Work' ?>
                </span>
              </div>
              <div style="font-size:0.8rem; color:#94a3b8; margin-top:0.2rem">
                Avg: <strong>
                  <?= round($peer_quality_avg, 1) ?>/10
                </strong>
              </div>
            </div>

          </div>
        </div>
        <div class="result-grid"
          style="display:grid; grid-template-columns:repeat(auto-fit, minmax(150px, 1fr)); gap:1rem; text-align:center;">
          <!-- Mood NLP Analysis -->
          <?php if ($sentiment_feedback): ?>
            <div style="background:white; padding:1rem; border-radius:12px;">
              <div style="font-size:0.8rem; color:#64748b; margin-bottom:0.3rem">Journal Sentiment</div>
              <div style="font-size:1.1rem; font-weight:800; color:var(--accent)">
                <?= $sentiment_feedback ?>
              </div>
            </div>
          <?php endif; ?>

          <div style="background:white; padding:1rem; border-radius:12px;">
            <div style="font-size:0.8rem; color:#64748b; margin-bottom:0.3rem">Anxiety Risk</div>
            <div style="font-size:1.5rem; font-weight:800; color:<?= $predicted_anxiety > 6 ? '#ef4444' : '#f59e0b' ?>">
              <?= $predicted_anxiety ?>/10
            </div>
          </div>
          <div style="background:white; padding:1rem; border-radius:12px;">
            <div style="font-size:0.8rem; color:#64748b; margin-bottom:0.3rem">Est. GPA</div>
            <div style="font-size:1.5rem; font-weight:800; color:var(--text)">
              <?= $predicted_index ?>
            </div>
          </div>
        </div>

        <!-- Recommendations Section -->
        <div style="margin-top:2rem; background:white; padding:1.5rem; border-radius:12px; border-left:4px solid
    var(--accent)">
          <h4 style="margin-top:0; color:var(--text)">💡 Personalized Suggestions</h4>
          <ul style="padding-left:1.2rem; margin-bottom:0; color:#475569;">
            <?php foreach ($suggestions as $msg): ?>
              <li style="margin-bottom:0.5rem; font-size:0.95rem; line-height:1.5">
                <?= $msg ?>
              </li>
            <?php endforeach; ?>
          </ul>
        </div>
      </div>
    <?php endif; ?>

    <?php if ($errors): ?>
      <div class="problem-card" style="border-color: rgba(239,68,68,.5)">
        <div class="icon">⚠️</div>
        <h3>Please fix the following:</h3>
        <ul><?php foreach ($errors as $e): ?>
            <li>
              <?= htmlspecialchars($e) ?>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <form method="post" class="problem-grid" style="grid-template-columns: 1fr 1fr; margin-top:2rem">

      <!-- Section 1: Sleep Assessment -->
      <h3 style="grid-column:1/-1; margin-top:0.5rem; margin-bottom:0.5rem; color:var(--accent-2)">🌙 Sleep Analysis
      </h3>

      <!-- Sleep Hours -->
      <div class="problem-card">
        <label><strong>Sleep Duration (Hours)</strong><br>
          <input name="sleep_hours" type="number" min="0" max="24" required placeholder="e.g. 7"
            style="width:100%;padding:.6rem;border-radius:10px;border:1px solid rgba(148,163,184,.35);background:var(--surface);color:var(--text)">
        </label>
      </div>

      <!-- Sleep Quality -->
      <div class="problem-card">
        <label><strong>Sleep Quality (1-10)</strong><br>
          <input name="sleep_quality" type="range" min="1" max="10" value="5"
            oninput="this.nextElementSibling.value = this.value" style="width:100%">
          <output style="font-weight:bold; color:var(--accent)">5</output>
        </label>
      </div>

      <!-- Sleep Journal (NLP) -->
      <div class="problem-card" style="grid-column:1/-1">
        <label><strong>Sleep Journal</strong> (Required)<br>
          <span style="font-size:0.85rem; color:#64748b">Write briefly about your sleep (e.g., "I felt anxious and kept
            waking up.") — <strong>AI Sentiment Analysis</strong> will adjust your health score.</span>
          <textarea name="journal" rows="2" required placeholder="Describe your sleep experience..."
            style="width:100%;padding:.6rem;border-radius:10px;border:1px solid rgba(148,163,184,.35);background:var(--surface);color:var(--text); margin-top:0.5rem"></textarea>
        </label>
      </div>

      <!-- Screen Time -->
      <div class="problem-card">
        <label><strong>Screen Time Before Bed (Hours)</strong><br>
          <input name="screen_time" type="number" step="0.5" min="0" max="12" placeholder="e.g. 2.5"
            style="width:100%;padding:.6rem;border-radius:10px;border:1px solid rgba(148,163,184,.35);background:var(--surface);color:var(--text)">
        </label>
      </div>

      <!-- Morning Grogginess -->
      <div class="problem-card">
        <label><strong>Morning Grogginess?</strong><br>
          <div style="margin-top:0.5rem; display:flex; gap:1.5rem">
            <label><input type="radio" name="grogginess" value="Yes"> Yes</label>
            <label><input type="radio" name="grogginess" value="No"> No</label>
          </div>
        </label>
      </div>

      <!-- New Lifestyle Section -->
      <h3 style="grid-column:1/-1; margin-top:2rem; margin-bottom:0.5rem; color:var(--accent)">☀️ Daily Lifestyle</h3>

      <!-- Caffeine Intake -->
      <div class="problem-card">
        <label><strong>Caffeine Intake (Cups/Day)</strong><br>
          <span style="font-size:0.85rem; color:#64748b">Coffee, tea, energy drinks, etc.</span>
          <input name="caffeine_intake" type="number" min="0" max="10" placeholder="e.g. 2"
            style="width:100%;padding:.6rem;border-radius:10px;border:1px solid rgba(148,163,184,.35);background:var(--surface);color:var(--text); margin-top:0.3rem">
        </label>
      </div>

      <!-- Exercise -->
      <div class="problem-card">
        <label><strong>Daily Exercise (Minutes)</strong><br>
          <span style="font-size:0.85rem; color:#64748b">Walking, gym, sports, etc.</span>
          <input name="exercise_minutes" type="number" min="0" max="300" placeholder="e.g. 30"
            style="width:100%;padding:.6rem;border-radius:10px;border:1px solid rgba(148,163,184,.35);background:var(--surface);color:var(--text); margin-top:0.3rem">
        </label>
      </div>

      <!-- Last Meal Time -->
      <div class="problem-card">
        <label><strong>Last Meal Time</strong><br>
          <select name="last_meal_time" required
            style="width:100%;padding:.6rem;border-radius:10px;border:1px solid rgba(148,163,184,.35);background:var(--surface);color:var(--text); margin-top:0.3rem">
            <option value="" disabled selected>Select Time</option>
            <option value="Before 6 PM">Before 6 PM</option>
            <option value="6-7 PM">6-7 PM</option>
            <option value="7-8 PM">7-8 PM</option>
            <option value="8-9 PM">8-9 PM</option>
            <option value="After 9 PM">After 9 PM</option>
          </select>
        </label>
      </div>

      <!-- Daily Stress Level -->
      <div class="problem-card">
        <label><strong>Daily Stress Level (1-10)</strong><br>
          <input name="daily_stress" type="range" min="1" max="10" value="5"
            oninput="this.nextElementSibling.value = this.value" style="width:100%">
          <output style="font-weight:bold; color:var(--accent)">5</output>
        </label>
      </div>

      <!-- Social Interaction -->
      <div class="problem-card" style="grid-column:1/-1">
        <label><strong>Social Interaction Level</strong><br>
          <span style="font-size:0.85rem; color:#64748b">How much do you interact with friends/family daily?</span>
          <div style="margin-top:0.5rem; display:flex; flex-wrap:wrap; gap:1rem">
            <label><input type="radio" name="social_interaction" value="Very Low"> Very Low</label>
            <label><input type="radio" name="social_interaction" value="Low"> Low</label>
            <label><input type="radio" name="social_interaction" value="Moderate"> Moderate</label>
            <label><input type="radio" name="social_interaction" value="High"> High</label>
            <label><input type="radio" name="social_interaction" value="Very High"> Very High</label>
          </div>
        </label>
      </div>

      <!-- Age -->
      <div class="problem-card">
        <label><strong>Age</strong><br>
          <input name="age" type="number" min="16" max="99" required placeholder="e.g. 21"
            style="width:100%;padding:.6rem;border-radius:10px;border:1px solid rgba(148,163,184,.35);background:var(--surface);color:var(--text); margin-top:0.3rem">
        </label>
      </div>

      <!-- Gender -->
      <div class="problem-card">
        <label><strong>Gender</strong><br>
          <div style="margin-top:0.5rem; display:flex; gap:1.5rem">
            <label><input type="radio" name="gender" value="Male"> Male</label>
            <label><input type="radio" name="gender" value="Female"> Female</label>
          </div>
        </label>
      </div>

      <!-- Major -->
      <div class="problem-card">
        <label><strong>Major</strong><br>
          <select name="major" required
            style="width:100%;padding:.6rem;border-radius:10px;border:1px solid rgba(148,163,184,.35);background:var(--surface);color:var(--text); margin-top:0.5rem">
            <option value="" disabled selected>Select your major</option>
            <option value="Computer Science">Computer Science</option>
            <option value="Engineering">Engineering</option>
            <option value="Nursing">Nursing</option>
            <option value="Psychology">Psychology</option>
            <option value="Business">Business</option>
            <option value="Music">Music</option>
            <option value="Art">Art</option>
            <option value="Biology">Biology</option>
            <option value="Other">Other</option>
          </select>
        </label>
      </div>

      <!-- Mental Health Screening (GAD-2 & PHQ-2) -->
      <h3 style="grid-column:1/-1; margin-top:2rem; margin-bottom:0.5rem; color:var(--accent)">🧠 Mental Well-being
        Check</h3>
      <p style="grid-column:1/-1; font-size:0.9rem; color:#64748b; margin-bottom:1rem">Over the last 2 weeks, how often
        have you been bothered by the following problems?</p>

      <!-- GAD-1 -->
      <div class="problem-card" style="grid-column:1/-1">
        <label><strong>1. Feeling nervous, anxious, or on edge?</strong></label>
        <div style="margin-top:0.5rem; display:flex; flex-wrap:wrap; gap:1rem">
          <label><input type="radio" name="gad1" value="0"> Not at all</label>
          <label><input type="radio" name="gad1" value="1"> Several days</label>
          <label><input type="radio" name="gad1" value="2"> More than half the days</label>
          <label><input type="radio" name="gad1" value="3"> Nearly every day</label>
        </div>
      </div>

      <!-- GAD-2 -->
      <div class="problem-card" style="grid-column:1/-1">
        <label><strong>2. Not being able to stop or control worrying?</strong></label>
        <div style="margin-top:0.5rem; display:flex; flex-wrap:wrap; gap:1rem">
          <label><input type="radio" name="gad2" value="0"> Not at all</label>
          <label><input type="radio" name="gad2" value="1"> Several days</label>
          <label><input type="radio" name="gad2" value="2"> More than half the days</label>
          <label><input type="radio" name="gad2" value="3"> Nearly every day</label>
        </div>
      </div>

      <!-- PHQ-1 -->
      <div class="problem-card" style="grid-column:1/-1">
        <label><strong>3. Little interest or pleasure in doing things?</strong></label>
        <div style="margin-top:0.5rem; display:flex; flex-wrap:wrap; gap:1rem">
          <label><input type="radio" name="phq1" value="0"> Not at all</label>
          <label><input type="radio" name="phq1" value="1"> Several days</label>
          <label><input type="radio" name="phq1" value="2"> More than half the days</label>
          <label><input type="radio" name="phq1" value="3"> Nearly every day</label>
        </div>
      </div>

      <!-- PHQ-2 -->
      <div class="problem-card" style="grid-column:1/-1">
        <label><strong>4. Feeling down, depressed, or hopeless?</strong></label>
        <div style="margin-top:0.5rem; display:flex; flex-wrap:wrap; gap:1rem">
          <label><input type="radio" name="phq2" value="0"> Not at all</label>
          <label><input type="radio" name="phq2" value="1"> Several days</label>
          <label><input type="radio" name="phq2" value="2"> More than half the days</label>
          <label><input type="radio" name="phq2" value="3"> Nearly every day</label>
        </div>
      </div>


      <!-- Section 2: Study Habits -->
      <h3 style="grid-column:1/-1; margin-top:2rem; margin-bottom:0.5rem; color:var(--accent)">📚 Grades Context</h3>

      <!-- Combined Study Stats -->
      <div class="problem-card">
        <label><strong>Study Habits</strong></label>
        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:1rem; margin-top:0.5rem">

          <label style="font-size:0.9rem; color:#475569">Daily Hours<br>
            <input name="hours_studied" type="number" min="0" max="24" required placeholder="e.g. 4"
              style="width:100%;padding:.6rem;border-radius:10px;border:1px solid rgba(148,163,184,.35);background:var(--surface);color:var(--text); margin-top:0.2rem">
          </label>

          <label style="font-size:0.9rem; color:#475569">Efficiency (1-10)<br>
            <input name="study_efficiency" type="number" min="1" max="10" required placeholder="e.g. 7"
              style="width:100%;padding:.6rem;border-radius:10px;border:1px solid rgba(148,163,184,.35);background:var(--surface);color:var(--text); margin-top:0.2rem">
          </label>
        </div>
        <p style="font-size:0.8rem; color:#94a3b8; margin-top:0.5rem"><em>Tip:</em> "Efficiency" means how productive
          those hours actually were.</p>
      </div>

      <!-- Previous Scores -->
      <div class="problem-card">
        <label><strong>Current GPA (0.0 - 4.0)</strong><br>
          <span style="font-size:0.8rem; color:#64748b">Enter your current cumulative GPA (e.g. 3.4)</span>
          <input name="previous_scores" type="number" step="0.01" min="0" max="4.0" required placeholder="e.g. 3.5"
            style="width:100%;padding:.6rem;border-radius:10px;border:1px solid rgba(148,163,184,.35);background:var(--surface);color:var(--text)">
        </label>
      </div>

      <!-- Extracurriculars -->
      <div class="problem-card" style="grid-column:1/-1">
        <label><strong>Extracurricular Activity</strong><br>
          <span style="font-size:0.8rem; color:#64748b">What activity do you do? (Type 'None' if applicable)</span>
          <input name="extra_name" type="text" placeholder="e.g. Basketball (1 hr/day), Debate Club"
            style="width:100%;padding:.6rem;border-radius:10px;border:1px solid rgba(148,163,184,.35);background:var(--surface);color:var(--text); margin-top:0.3rem">
        </label>
        <p style="font-size:0.8rem; color:var(--accent); margin-top:0.5rem">✨ <strong
            style="font-size:0.8rem; color:var(--accent)">AI Analysis:</strong> We will automatically estimate the
          intensity of your activity.</p>
      </div>

      <div class="problem-card" style="grid-column:1/-1;">
        <button class="cta-button" type="submit">Compare & Predict</button>
      </div>
    </form>
  </div>
</section>

<?php require __DIR__ . '/footer.php'; ?>