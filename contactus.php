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
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Contact Us - EventHub</title>
  <link rel="stylesheet" href="contact.css">
</head>
<body>

  <section class="hero">
    <div class="hero-bg"></div>
    <div class="hero-pattern"></div>
    <div class="hero-content">
      <h1 class="hero-title">Meet The Team</h1>
      <p class="hero-description">We're a group of college students passionate about connecting people through events. Have questions or want to collaborate? Reach out to us directly!</p>
    </div>
  </section>

  <section class="developers-section">
    <div class="container">
      <div class="section-header">
        <h2 class="gradient-title">Our Developers</h2>
      </div>
      
      <div class="developers-grid">
        <div class="developer-card">
          <div class="avatar-container">
            <div class="avatar">
              <img src="icon\image.png" alt="Tushar Lakeri" />
            </div>
          </div>
          <div class="card-content">
            <h3 class="developer-name">Tushar Lakeri</h3>
            <p class="developer-role">Developer</p>
            <p class="developer-bio">Computer Science student.</p>
            <div class="social-links">
              <a href="mailto:eventhub2k25@gmail.com" class="social-link">
                <svg class="social-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                  <polyline points="22,6 12,13 2,6"></polyline>
                </svg>
                <span class="sr-only">Email Tushar Lakeri</span>
              </a>
              <a href="https://github.com/TusharLakeri" target="_blank" rel="noopener noreferrer" class="social-link">
                <svg class="social-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M9 19c-5 1.5-5-2.5-7-3m14 6v-3.87a3.37 3.37 0 0 0-.94-2.61c3.14-.35 6.44-1.54 6.44-7A5.44 5.44 0 0 0 20 4.77 5.07 5.07 0 0 0 19.91 1S18.73.65 16 2.48a13.38 13.38 0 0 0-7 0C6.27.65 5.09 1 5.09 1A5.07 5.07 0 0 0 5 4.77a5.44 5.44 0 0 0-1.5 3.78c0 5.42 3.3 6.61 6.44 7A3.37 3.37 0 0 0 9 18.13V22"></path>
                </svg>
                <span class="sr-only">Github profile for Tushar Lakeri</span>
              </a>
            </div>
            <div class="card-footer">
              <a href="mailto:eventhub2k25@gmail.com" class="email-link">eventhub2k25@gmail.com</a>
            </div>
          </div>
        </div>

        <div class="developer-card">
          <div class="avatar-container">
            <div class="avatar">
              <img src="icon\image.png" alt="Rahul Manchare" />
            </div>
          </div>
          <div class="card-content">
            <h3 class="developer-name">Rahul Manchare</h3>
            <p class="developer-role">Backend Developer</p>
            <p class="developer-bio">Computer Science Student.</p>
            <div class="social-links">
              <a href="mailto:eventhub2k25@gmail.com" class="social-link">
                <svg class="social-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                  <polyline points="22,6 12,13 2,6"></polyline>
                </svg>
                <span class="sr-only">Email Rahul Manchare</span>
              </a>
              <a href="https://github.com/" target="_blank" rel="noopener noreferrer" class="social-link">
                <svg class="social-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M9 19c-5 1.5-5-2.5-7-3m14 6v-3.87a3.37 3.37 0 0 0-.94-2.61c3.14-.35 6.44-1.54 6.44-7A5.44 5.44 0 0 0 20 4.77 5.07 5.07 0 0 0 19.91 1S18.73.65 16 2.48a13.38 13.38 0 0 0-7 0C6.27.65 5.09 1 5.09 1A5.07 5.07 0 0 0 5 4.77a5.44 5.44 0 0 0-1.5 3.78c0 5.42 3.3 6.61 6.44 7A3.37 3.37 0 0 0 9 18.13V22"></path>
                </svg>
                <span class="sr-only">Github profile for Rahul Manchare</span>
              </a>
            </div>
            <div class="card-footer">
              <a href="mailto:eventhub2k25@gmail.com" class="email-link">eventhub2k25@gmail.com</a>
            </div>
          </div>
        </div>

        <div class="developer-card">
          <div class="avatar-container">
            <div class="avatar">
              <img src="icon\image.png" alt="Amar More" />
            </div>
          </div>
          <div class="card-content">
            <h3 class="developer-name">Amar More</h3>
            <p class="developer-role">Developer</p>
            <p class="developer-bio">Computer Science Student.</p>
            <div class="social-links">
              <a href="mailto:eventhub2k25@gmail.com" class="social-link">
                <svg class="social-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                  <polyline points="22,6 12,13 2,6"></polyline>
                </svg>
                <span class="sr-only">Email Amar More</span>
              </a>
              <a href="https://github.com/" target="_blank" rel="noopener noreferrer" class="social-link">
                <svg class="social-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M9 19c-5 1.5-5-2.5-7-3m14 6v-3.87a3.37 3.37 0 0 0-.94-2.61c3.14-.35 6.44-1.54 6.44-7A5.44 5.44 0 0 0 20 4.77 5.07 5.07 0 0 0 19.91 1S18.73.65 16 2.48a13.38 13.38 0 0 0-7 0C6.27.65 5.09 1 5.09 1A5.07 5.07 0 0 0 5 4.77a5.44 5.44 0 0 0-1.5 3.78c0 5.42 3.3 6.61 6.44 7A3.37 3.37 0 0 0 9 18.13V22"></path>
                </svg>
                <span class="sr-only">Github profile for Amar More</span>
              </a>
            </div>
            <div class="card-footer">
              <a href="mailto:eventhub2k25@gmail.com" class="email-link">eventhub2k25@gmail.com</a>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>
  
  <section class="project-section">
    <div class="container">
      <div class="section-header">
        <h2 class="gradient-title">About Our Project</h2>
        <p class="section-description">EventHub is a passion project created by students, for students</p>
      </div>
      
      <div class="project-grid">
        <div class="card-wrapper">
          <div class="card-gradient gradient-purple"></div>
          <div class="project-card">
            <h3 class="card-title">
              <span class="icon-circle icon-purple">✦</span>
              <div class="card-title-text">
              Our Mission</div>
            </h3>
            <p class="card-text">
              We set out to solve a common problem on campus - finding and managing events was too complicated and fragmented.
            </p>
            <ul class="feature-list">
              <li class="feature-item">
                <span class="feature-bullet">•</span>
                <span>Connect students with events they'll love</span>
              </li>
              <li class="feature-item">
                <span class="feature-bullet">•</span>
                <span>Help organizations reach their target audience</span>
              </li>
              <li class="feature-item">
                <span class="feature-bullet">•</span>
                <span>Create a more vibrant and connected campus</span>
              </li>
            </ul>
          </div>
        </div>
        
        <div class="card-wrapper">
          <div class="card-gradient gradient-pink"></div>
          <div class="project-card">
            <h3 class="card-title">
              <span class="icon-circle icon-pink">⚙️</span>
              <div class="card-title-text">
              Our Technology
</div>
            </h3>
            <p class="card-text">
              We've built this platform using modern technologies to ensure a smooth and responsive experience.
            </p>
            <div class="tech-grid">
              <div class="tech-item">
                <div class="tech-name">Frontend</div>
                <div class="tech-description">HTML + JavaScript</div>
              </div>
              <div class="tech-item">
                <div class="tech-name">Styling</div>
                <div class="tech-description">CSS</div>
              </div>
              <div class="tech-item">
                <div class="tech-name">Backend</div>
                <div class="tech-description">PHP</div>
              </div>
              <div class="tech-item">
                <div class="tech-name">Database</div>
                <div class="tech-description">MySQL</div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <section class="faq-section">
    <div class="container">
      <div class="section-header">
        <h2 class="gradient-title">Frequently Asked Questions</h2>
        <p class="section-description">Common questions asked</p>
      </div>
      
      <div class="accordion">
        <div class="accordion-item">
          <div class="accordion-header accordion-trigger" onclick="toggleAccordion(this)" aria-expanded="false" aria-controls="faq-1">
            <span class="accordion-title">What is EventHub?</span>
            <svg class="chevron" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <polyline points="6 9 12 15 18 9"></polyline>
            </svg>
          </div>
          <div class="accordion-content" id="faq-1" data-state="closed">
            <div class="accordion-body">
              <p class="accordion-text">
                EventHub is a student-developed platform designed to help college communities discover, 
                create, and manage campus events. Our goal is to increase student engagement and
                make it easier to find events that match your interests.
              </p>
            </div>
          </div>
        </div>
        
        <div class="accordion-item">
          <div class="accordion-header accordion-trigger" onclick="toggleAccordion(this)" aria-expanded="false" aria-controls="faq-2">
            <span class="accordion-title">Is EventHub free to use?</span>
            <svg class="chevron" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <polyline points="6 9 12 15 18 9"></polyline>
            </svg>
          </div>
          <div class="accordion-content" id="faq-2" data-state="closed">
            <div class="accordion-body">
              <p class="accordion-text">
              EventHub offers various features, and while basic event Browse and registration are typically free for users, there might be costs associated with creating or promoting events, depending on the platform's model. 

              </p>
            </div>
          </div>
        </div>
        
        <div class="accordion-item">
          <div class="accordion-header accordion-trigger" onclick="toggleAccordion(this)" aria-expanded="false" aria-controls="faq-3">
            <span class="accordion-title">How can I find events?</span>
            <svg class="chevron" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <polyline points="6 9 12 15 18 9"></polyline>
            </svg>
          </div>
          <div class="accordion-content" id="faq-3" data-state="closed">
            <div class="accordion-body">
              <p class="accordion-text">
              You can use the search bar to look for events by keywords (e.g., event name, topic), browse by categories, date, location, or use filters 
              </p>
            </div>
          </div>
        </div>
        
        <div class="accordion-item">
          <div class="accordion-header accordion-trigger" onclick="toggleAccordion(this)" aria-expanded="false" aria-controls="faq-4">
            <span class="accordion-title">How do I create an account?</span>
            <svg class="chevron" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <polyline points="6 9 12 15 18 9"></polyline>
            </svg>
          </div>
          <div class="accordion-content" id="faq-4" data-state="closed">
            <div class="accordion-body">
              <p class="accordion-text">
              Look for a "Sign Up" or "Register" button, typically in the top right corner of the website. You will likely need to provide basic information like your email address and create a password.
              </p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>
<script>
      function toggleAccordion(element) {
      const expanded = element.getAttribute('aria-expanded') === 'true';
      element.setAttribute('aria-expanded', !expanded);
      
      const contentId = element.getAttribute('aria-controls');
      const content = document.getElementById(contentId);
      
      if (expanded) {
        content.style.height = '0';
        content.setAttribute('data-state', 'closed');
      } else {
        content.setAttribute('data-state', 'open');
        const height = content.querySelector('.accordion-body').offsetHeight;
        content.style.height = height + 'px';
      }
    }
  </script>
    <?php include 'footer.php'?>
</body>
</html>