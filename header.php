<?php
// Ensure session_start() is the very first thing in this file.
// It must be before any HTML output, even whitespace.
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="header.css">
<script src="script.js"></script>
<header class="sticky-header">
  <div class="container">
    <div class="header-content">
      <div class="logo-nav">
        <a href="/" class="logo">
          <div class="logo-icon">
            <div class="logo-text"><img src="icon/logo.png" alt="EventHub Logo"></div>
          </div>
          <span class="logo-name">EventHub</span>
        </a>
        <nav class="main-nav">
          <?php
            // Dynamically set the Home link based on login status
            // If user is logged in (userid session variable is set), Home goes to dashboard.php
            // Otherwise, Home goes to index.php
            $homeLink = isset($_SESSION['userid']) ? 'admin.php' : 'index.php';
          ?>
          <a href="<?php echo $homeLink; ?>" class="nav-link">Home</a>
          <a href="search.php" class="nav-link">Events</a>
          <a href="aboutus.php" class="nav-link">About</a>
          <a href="#" class="nav-link">Contact</a>
          <?php
            // No additional links in main-nav requested when logged in, based on this request.
          ?>
        </nav>
      </div>
      <div class="search-auth">
        <div class="search-container">
          <div class="search-box">
            <i class="fas fa-search search-icon"></i>
            <form action="search.php" method="get">
              <input type="text" name='search' placeholder="Search events..." class="search-input">
            </form>
          </div>
        </div>
        <nav class="auth-nav">
          <button class="theme-toggle" id="themeToggle">
            <i class="fas fa-sun light-icon"></i>
            <i class="fas fa-moon dark-icon"></i>
            <span class="sr-only">Toggle theme</span>
          </button>

          <?php
            // Check if the user is logged in by checking for the 'userid' session variable
            if (isset($_SESSION['userid'])) {
                // User is logged in, show "Create Event" and "Log out" links using nav-link class
          ?>
                <a href="create_event.php" style="text-decoration:none;" class="sign-in-btn">Create Event</a> <a href="logout.php" style="text-decoration:none;" class="sign-in-btn" style="margin-left: 0.5rem;">Log out</a> <?php
            } else {
                // User is not logged in, show "Sign In" button
          ?>
                <a href="login.php" style="text-decoration:none;">
                    <button class="sign-in-btn">Sign In</button>
                </a>
          <?php
            }
          ?>
        </nav>
      </div>
    </div>
  </div>
</header>