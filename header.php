<?php
// header.php
if (!isset($active)) {
  $active = '';
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Student Performance Study</title>
  <link rel="stylesheet" href="styles.css" />
  <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
</head>

<body>
  <nav class="navbar">
    <div class="container">
      <h1 class="logo">💤 Sleep Study</h1>
      <ul class="nav-links">
        <li><a href="index.php" class="<?= $active === 'home' ? 'active' : '' ?>">Home</a></li>
        <li><a href="explore.php" class="<?= $active === 'explore' ? 'active' : '' ?>">Explore Data</a></li>
        <li><a href="assessment.php" class="<?= $active === 'assessment' ? 'active' : '' ?>">Sleep Assessment</a></li>
        <li><a href="insights.php" class="<?= $active === 'insights' ? 'active' : '' ?>">Insights</a></li>
        <li><a href="about.php" class="<?= $active === 'about' ? 'active' : '' ?>">About</a></li>
      </ul>
    </div>
  </nav>