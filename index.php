<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EventHub</title>
    <link rel="stylesheet" href="indexstyle.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>

<?php include 'database.php'; ?>
<?php require 'header.php'; ?>

<?php
// Remove past events


// Fetch all upcoming events for the "Upcoming Events" section
$sqlAllUpcoming = "SELECT * FROM events WHERE event_date >= CURDATE() ORDER BY event_date";
$resultAllUpcoming = $conn->query($sqlAllUpcoming);
$allUpcomingEvents = [];

if ($resultAllUpcoming->num_rows > 0) {
    while ($row = $resultAllUpcoming->fetch_assoc()) {
        $allUpcomingEvents[] = $row;
    }
}

// Fetch events for the current week for "This Week's Highlights"
$sqlThisWeek = "SELECT * FROM events
WHERE event_date >= CURDATE()  AND event_date < CURDATE() + INTERVAL (7 - WEEKDAY(CURDATE())) DAY ORDER BY event_date";
$resultThisWeek = $conn->query($sqlThisWeek);
$thisWeekEvents = [];

if ($resultThisWeek->num_rows > 0) {
    while ($row = $resultThisWeek->fetch_assoc()) {
        $thisWeekEvents[] = $row;
    }
}
?>

<main class="container">
    <section class="hero-section">
        <div class="hero-content">
            <div class="live-badge">
                <span class="pulse-dot"></span> Live Events
            </div>
            <h1 class="hero-title">
                Discover Amazing <span class="gradient-text">Campus Events</span>
            </h1>
            <p class="hero-description">
                Find and join the best events happening around your campus. From workshops to technical, we've got you covered.
            </p>
            <div class="hero-buttons">
                <button class="primary-btn"><a href="search.php" style="text-decoration:none;color:white">Browse All Events</a></button>
                <button class="outline-btn"><a href="create_event.php" style="text-decoration:none;">Create Event</a></button>
            </div>
        </div>
    </section>

    <section class='weekly-section'>
        <div class='section-header'>
            <h2 class='section-title'>This Week's Highlights</h2>
            <a href="#upcomingEvents"><button class='view-all-btn'>View all</button></a>

        </div>
        <div class='weekly-events-container'>
            <div class='weekly-events' id='weeklyEvents'>
                <?php
                if (empty($thisWeekEvents)) {
                    echo "<p>No events scheduled for this week.</p>";
                } else {
                    foreach ($thisWeekEvents as $row) {
                        echo "<div class='weekly-card' data-type='" . strtolower($row['event_type']) . "'>";
                        echo "<div class='card-image'>";
                        echo "<img src='Event_image/" . $row['image'] . "' alt='Event Image'>";
                        echo "<div class='image-overlay'></div>";
                        echo "<div class='card-badge'>" . $row['event_type'] . "</div>";
                        echo "<div class='card-content-overlay'>";
                        echo "<h3 class='card-title'>" . $row['title'] . "</h3>";
                        echo "<p class='card-date'><span class='pulse-dot'></span> Date: " . $row['event_date'] . "</p>";
                        echo "</div></div>";
                        echo "<div class='card-content'>";
                        echo "<p><i class='far fa-map-marker-alt'></i> " . $row['department'] . "</p>";
                        echo "<p><i class='fas fa-map-marker-alt'></i> " . $row['college_name'] . "</p>";
                        echo "<a href='" . $row['referencelink'] . "' class='learn-more'>Register →</a>";
                        echo "</div></div>";
                    }
                    echo "<button class='scroll-btn scroll-left' id='scrollLeft'><i class='fas fa-chevron-left'></i></button>";
                    echo "<button class='scroll-btn scroll-right' id='scrollRight'><i class='fas fa-chevron-right'></i></button>";

                }
                ?>
            </div>
        </div>
    </section>

    <section class="upcoming-section" id="upcomingEvents">

        <div class="section-header">
            <h2 class="section-title">Upcoming Events</h2>
        </div>

        <div class="tabs">
            <div class="tab-list">
                <button class="tab-btn active" data-tab="all">All</button>
                <button class="tab-btn" data-tab="conference">Conference</button>
                <button class="tab-btn" data-tab="technical">Technical</button>
                <button class="tab-btn" data-tab="seminar">Seminar</button>
                <button class="tab-btn" data-tab="workshop">Workshop</button>
            </div>

            <div class='tab-content active' id='all'>
                 <div class='events-grid' id="allEventsGrid">
                 </div>
                 <button class="load-more-btn" data-tab="all" style="display: none;">Load More</button>
            </div>

            <div class="tab-content" id="conference">
                 <div class='events-grid' id="conferenceEventsGrid">
                 </div>
                 <button class="load-more-btn" data-tab="conference" style="display: none;">Load More</button>
            </div>
            <div class="tab-content" id="seminar">
                 <div class='events-grid' id="seminarEventsGrid">
                 </div>
                 <button class="load-more-btn" data-tab="seminar" style="display: none;">Load More</button>
            </div>
            <div class="tab-content" id="technical">
                 <div class='events-grid' id="technicalEventsGrid">
                 </div>
                 <button class="load-more-btn" data-tab="technical" style="display: none;">Load More</button>
            </div>
            <div class="tab-content" id="workshops">
                 <div class='events-grid' id="workshopEventsGrid">
                 </div>
                 <button class="load-more-btn" data-tab="workshop" style="display: none;">Load More</button>
            </div>
        </div>
    </section>

    <section class="cta-section">
        <div class="cta-content">
            <h2 class="cta-title">Ready to host your own event?</h2>
            <p class="cta-description">
                Create and manage your own events with our easy-to-use platform. Reach thousands of students on campus!
            </p>
            <div class="cta-buttons">
                <a href="create_event.php" style="text-decoration:none;"><button class="cta-primary-btn">Create Event</button></a>
                <a href="aboutus.php" style="text-decoration:none;"><button class="cta-outline-btn">Learn More</button></a>
            </div>
        </div>
    </section>
</main>

<?php require 'footer.php'; ?>

<script>
    const allUpcomingEventsData = <?php echo json_encode($allUpcomingEvents); ?>;
    const eventsPerLoad = 9; // Number of events to load each time
    let displayedEventsCount = {}; // To keep track of how many events are displayed for each tab

    document.addEventListener("DOMContentLoaded", () => {
        const tabButtons = document.querySelectorAll(".tab-btn");
        const tabContents = document.querySelectorAll(".tab-content");
        const loadMoreButtons = document.querySelectorAll(".load-more-btn");

        // Initialize displayedEventsCount for each tab
        tabContents.forEach(content => {
            const tabId = content.id;
            displayedEventsCount[tabId] = 0;
        });


        function renderEvents(filter, startIndex, append = false) {
            const targetTabContentId = filter === 'workshop' ? 'workshops' : filter;
            const targetTabContent = document.getElementById(targetTabContentId);
            const targetGrid = targetTabContent ? targetTabContent.querySelector('.events-grid') : null;
            const loadMoreBtn = targetTabContent ? targetTabContent.querySelector('.load-more-btn') : null;

            if (!targetTabContent || !targetGrid) {
                console.error("Target tab content or grid not found for filter:", filter);
                return;
            }

            // Filter events based on the selected tab
            const filteredEvents = filter === "all"
                ? allUpcomingEventsData
                : allUpcomingEventsData.filter(e => e.event_type.toLowerCase() === filter.toLowerCase());

            if (!append) {
                 // Clear the grid only if not appending
                targetGrid.innerHTML = "";
                displayedEventsCount[targetTabContentId] = 0; // Reset count for the new filter
            }


            const eventsToDisplay = filteredEvents.slice(startIndex, startIndex + eventsPerLoad);

            if (eventsToDisplay.length === 0 && startIndex === 0) {
                 targetGrid.innerHTML = "<p>No events available for this category.</p>";
                 if (loadMoreBtn) loadMoreBtn.style.display = 'none';
                 return;
             } else if (eventsToDisplay.length === 0 && startIndex > 0) {
                 // No more events to load
                 if (loadMoreBtn) loadMoreBtn.style.display = 'none';
                 return;
             }


            eventsToDisplay.forEach(row => {
                const card = document.createElement("div");
                card.className = "event-card";
                card.setAttribute("data-type", row.event_type);

                card.innerHTML = `
                    <div class="event-image">
                        <img src="Event_image/${row.image}" alt="Event Image">
                        <div class="image-overlay"></div>
                        <div class="card-badge">${row.event_type}</div>
                    </div>
                    <div class="event-details">
                        <h3 class="event-title">${row.title}</h3>
                        <p class="event-description">${row.description}</p>
                        <div class="event-info">
                            <p><i class="far fa-calendar"></i> ${row.event_date}</p>
                            <p><i class="far fa-map-marker-alt"></i> ${row.department}</p>
                            <p><i class="fas fa-map-marker-alt"></i> ${row.college_name}</p>
                        </div>
                        <a href="${row.referencelink}" class="register-btn">Register Now</a>
                    </div>
                `;
                targetGrid.appendChild(card);
            });

            displayedEventsCount[targetTabContentId] += eventsToDisplay.length;

            // Show/hide Load More button
            if (loadMoreBtn) {
                if (displayedEventsCount[targetTabContentId] < filteredEvents.length) {
                    loadMoreBtn.style.display = 'block';
                } else {
                    loadMoreBtn.style.display = 'none';
                }
            }
        }

        tabButtons.forEach(btn => {
            btn.addEventListener("click", () => {
                document.querySelector(".tab-btn.active").classList.remove("active");
                btn.classList.add("active");

                tabContents.forEach(content => {
                    content.classList.remove("active");
                });

                const filter = btn.getAttribute("data-tab");
                const targetTabContentId = filter === 'workshop' ? 'workshops' : filter;
                const targetTabContent = document.getElementById(targetTabContentId);

                if (targetTabContent) {
                     targetTabContent.classList.add("active");
                     renderEvents(filter, 0); // Render the first batch of events for the selected tab
                 } else {
                     console.error("Could not find tab content for filter:", filter);
                 }
            });
        });

        loadMoreButtons.forEach(btn => {
            btn.addEventListener("click", () => {
                const filter = btn.getAttribute("data-tab");
                const targetTabContentId = filter === 'workshop' ? 'workshops' : filter;
                const startIndex = displayedEventsCount[targetTabContentId];
                renderEvents(filter, startIndex, true); // Append the next batch of events
            });
        });


        // Initial render to show the first batch of 'All' upcoming events and activate the 'All' tab content
        renderEvents("all", 0);
        document.getElementById("all").classList.add("active");

    });
</script>
<script src="script.js"></script>
</body>
</html>