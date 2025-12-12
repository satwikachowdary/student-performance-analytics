<?php
$active = 'about';
require __DIR__ . '/header.php';
?>
<section class="hero">
  <div class="hero-content container">
    <h1 class="hero-title">About This Project</h1>
    <p class="hero-subtitle">Unlocking the secrets of student success through data.</p>
  </div>
</section>

<section class="problem-statement">
  <div class="container">
    <div class="problem-card" style="grid-column: 1 / -1; text-align: left; padding: 2rem;">
      <h3>Project Overview</h3>
      <p style="font-size: 1.1rem; line-height: 1.6; color: #475569; margin-top: 1rem;">
        <strong>The Performance Puzzle</strong> explores the critical but often overlooked connection between
        <span style="color: var(--accent); font-weight: bold;">Sleep</span>,
        <span style="color: var(--accent-2); font-weight: bold;">Mental Health</span>, and
        <span style="color: var(--text); font-weight: bold;">Academic Success</span>.
      </p>

      <p style="font-size: 1.1rem; line-height: 1.6; color: #475569; margin-top: 1rem;">
        Using a dataset of <strong>4,000 students</strong>, this website provides interactive tools to help you:
      </p>

      <ul style="margin-top: 1rem; margin-left: 1.5rem; color: #475569; font-size: 1.05rem;">
        <li style="margin-bottom: 0.5rem;"><strong>Visualize Data:</strong> See how sleep hours directly correlate with
          Performance Indices and Anxiety Scores.</li>
        <li style="margin-bottom: 0.5rem;"><strong>Benchmark Yourself:</strong> Compare your sleep habits against
          high-performing peers.</li>
        <li style="margin-bottom: 0.5rem;"><strong>Assess Health Risks:</strong> Use our AI-powered sentiment analysis
          to check your sleep health.</li>
      </ul>

      <p style="font-size: 1.1rem; line-height: 1.6; color: #475569; margin-top: 1rem;">
        Our goal is to prove that <strong>rest is not a waste of time</strong>—it is the ultimate study strategy.
      </p>
    </div>
  </div>
</section>
<?php require __DIR__ . '/footer.php'; ?>