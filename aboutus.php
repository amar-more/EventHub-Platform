<?php
// Ensure session_start() is the very first thing in the file
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}


// Determine the correct URL for 'Explore Events' based on login status
$exploreEventsUrl = 'index.php'; // Default URL if not logged in

// CORRECTED CONDITION: Check if the 'userid' session variable is set
if (isset($_SESSION['userid'])) {
    $exploreEventsUrl = 'admin.php'; // URL if logged in (because userid is set)
}

require 'header.php'; // Your header include
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>About Us - EventHub</title>

  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

  <link rel="stylesheet" href="aboutus.css">

</head>
<body>
  

  <section class="main-header-section">
    <div class="container main-header-grid">
      <div class="main-header-content">
        <h1 class="main-header-title">About <span class="main-gradient-text">EventHub</span></h1>
        <p class="main-header-description">
          EventHub is the premier platform for discovering and creating campus events.
          We connect students, faculty, and staff to create a vibrant campus community.
        </p>
        <div class="main-header-buttons" >
          <a href="<?php echo $exploreEventsUrl; ?>" class="main-btn main-btn-primary">Explore Events</a>
          <a href="contactus.php" class="main-btn main-btn-outline">Contact Us</a>
        </div>
      </div>
      <div class="main-header-image">
        <img src="icon/logo.png" style="background-color: #e0e0e0;border-radius:200px" alt="Campus Events" style="max-width: 100%; border-radius: 1rem;">
      </div>
    </div>
  </section>

  <section class="main-mission-section">
    <div class="container">
      <div class="main-mission-content">
        <h2 class="main-mission-title">Our Mission</h2>
        <p class="main-mission-description">
          To create a vibrant campus community by connecting people through meaningful events and experiences.
        </p>
      </div>
    </div>
  </section>

  <section class="main-how-it-works-section">
    <div class="container">
      <h2 class="main-section-title">How EventHub Works</h2>
      <p class="text-center" style="margin-bottom: 2rem; color: var(--muted-foreground);">
        Our platform makes it easy to discover, create, and participate in campus events.
      </p>

      <div class="main-cards-container">
        <div class="main-card">
          <div class="main-card-icon">
            <i class="fas fa-search"></i>
          </div>
          <h3 class="main-card-title">Discover Events</h3>
          <p class="main-card-description">
            Browse through a wide range of events happening on campus
          </p>
          <p style="font-size: 0.875rem;">
            Use our powerful search and filtering tools to find events that match your interests,
            schedule, and location preferences.
          </p>
        </div>

        <div class="main-card">
          <div class="main-card-icon">
            <i class="fas fa-calendar-plus"></i>
          </div>
          <h3 class="main-card-title">Create Events</h3>
          <p class="main-card-description">
            Easily create and manage your own events
          </p>
          <p style="font-size: 0.875rem;">
            Our intuitive event creation tools make it simple to set up, promote,
            and manage registrations for your campus events.
          </p>
        </div>

        <div class="main-card">
          <div class="main-card-icon">
            <i class="fas fa-users"></i>
          </div>
          <h3 class="main-card-title">Connect</h3>
          <p class="main-card-description">
            Join events and connect with other attendees
          </p>
          <p style="font-size: 0.875rem;">
            Register for events, receive updates, and connect with other
            participants to build your campus network.
          </p>
        </div>
      </div>
    </div>
  </section>

  <section class="main-why-choose-section">
    <div class="container">
      <h2 class="main-section-title">Why Choose EventHub</h2>
      <div class="main-features-grid">
        <div class="main-feature">
          <div class="main-feature-icon">
            <i class="fas fa-compass"></i>
          </div>
          <h3 class="main-feature-title">All-in-One Platform</h3>
          <p class="main-feature-description">
            Everything you need to discover and create campus events in one place.
          </p>
        </div>

        <div class="main-feature">
          <div class="main-feature-icon">
            <i class="fas fa-users"></i>
          </div>
          <h3 class="main-feature-title">Community Focused</h3>
          <p class="main-feature-description">
            Built specifically for campus communities to foster connections.
          </p>
        </div>

        <div class="main-feature">
          <div class="main-feature-icon">
            <i class="fas fa-award"></i>
          </div>
          <h3 class="main-feature-title">Quality Events</h3>
          <p class="main-feature-description">
            Curated events that ensure meaningful and valuable experiences.
          </p>
        </div>

        <div class="main-feature">
          <div class="main-feature-icon">
            <i class="fas fa-lightbulb"></i>
          </div>
          <h3 class="main-feature-title">User-Friendly</h3>
          <p class="main-feature-description">
            Intuitive design that makes event discovery and creation simple.
          </p>
        </div>
      </div>
    </div>
  </section>

  <section class="main-cta-section">
    <div class="container">
      <h2 class="main-cta-title">Ready to Get Started?</h2>
       <a href="search.php" class="main-cta-button">Browse Events</a>
    </div>
  </section>

  <?php require 'footer.php'; ?>

</body>
</html>