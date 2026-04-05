<?php
session_start(); // Start the session

// Include database connection details
// Ensure database.php connects to your MySQL database and stores the connection in $conn
include 'database.php';

// Check if connection was successful
if ($conn->connect_error) {
    // Log the error instead of displaying it directly in production
    error_log("Database Connection failed: " . $conn->connect_error);
    // Display a user-friendly message or redirect
    die("An error occurred while connecting to the database.");
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $delete_id = intval($_POST['delete_id']);
    $sql = "DELETE FROM events WHERE id = $delete_id";
    if ($conn->query($sql) === TRUE) {
        header("Location: admin.php");
        exit();
    } else {
        echo "Error deleting record: " . $conn->error;
    }
}
// --- Constants for Pagination (Defined at the top level) ---
const EVENTS_PER_INITIAL_LOAD = 9; // Define how many events to load initially
const EVENTS_PER_LOAD_AJAX = 9; // Define how many events to load per AJAX request


// --- Assume user is logged in and user ID (username) is stored in session ---
$loggedInUsername = $_SESSION['userid'] ?? null;

// Check if this is an AJAX request for more events
// A common way to detect AJAX is via the HTTP_X_REQUESTED_WITH header
if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    // --- Handle AJAX Request for More Events ---

    // Ensure user is logged in for AJAX requests too (security check)
    if (!$loggedInUsername) {
        header('Content-Type: application/json');
        echo json_encode(['events' => [], 'error' => 'User not logged in']);
        exit(); // Stop script execution
    }

    // Get parameters from AJAX request
    $tab = $_GET['tab'] ?? 'created'; // Default to 'created' if tab is not provided
    $page = (int)($_GET['page'] ?? 2); // Default to page 2 for the first AJAX call

    // Calculate offset and limit for this page
    // Page 1 is loaded by the initial PHP script (12 events)
    // Page 2 starts after the first 12 events (offset 12, limit 12)
    // Page 3 starts after the first 24 events (offset 24, limit 12), and so on.
    $offset = ($page - 1) * EVENTS_PER_LOAD_AJAX;
    $limit = EVENTS_PER_LOAD_AJAX;

    $events = [];
    $sql = "";
    $stmt = null;

    // Determine the query based on the tab, joining with usertable to get college/department
    switch ($tab) {
        case 'upcoming':
            $sql = "SELECT events.*, usertable.college AS display_info
                    FROM events
                    JOIN usertable ON events.username = usertable.username
                    WHERE events.username = ? AND events.event_date >= CURDATE()
                    ORDER BY events.event_date ASC LIMIT ? OFFSET ?";
            break;
        case 'past':
            $sql = "SELECT events.*, usertable.college AS display_info
                    FROM events
                    JOIN usertable ON events.username = usertable.username
                    WHERE events.username = ? AND events.event_date < CURDATE()
                    ORDER BY events.event_date DESC LIMIT ? OFFSET ?";
            break;
        case 'created': // This tab shows all created events
        default: // Default to created if tab is unknown
            $sql = "SELECT events.*, usertable.college AS display_info
                    FROM events
                    JOIN usertable ON events.username = usertable.username
                    WHERE events.username = ?
                    ORDER BY events.event_date DESC LIMIT ? OFFSET ?";
            $tab = 'created'; // Ensure default is set
            break;
    }

    if (!empty($sql)) {
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("sii", $loggedInUsername, $limit, $offset); // s: string, i: integer, i: integer
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result) {
                while ($row = $result->fetch_assoc()) {
                     // Format data similarly to the main page load
                     $row['event_date_formatted'] = date('M d, Y', strtotime($row['event_date']));
                     $row['event_location'] = htmlspecialchars($row['location'] ?? 'N/A');
                     $imagePath = 'Event_image/' . $row['image'];
                     $row['image_exists_server_side'] = !empty($row['image']) && file_exists($imagePath);
                     // display_info is now fetched directly from the join
                     $row['display_info'] = htmlspecialchars($row['display_info'] ?? 'N/A');


                    $events[] = $row;
                }
            } else {
                 error_log("AJAX fetch more events query failed: " . $stmt->error);
                 // Provide a more informative error in dev/staging
                 // echo json_encode(['events' => [], 'error' => 'Database query failed: ' . $stmt->error]);
                 // In production, a generic error is safer
                 // echo json_encode(['events' => [], 'error' => 'Error fetching data']);
            }
            $stmt->close();
        } else {
            error_log("Failed to prepare AJAX fetch more events statement: " . $conn->error);
             // Provide a more informative error in dev/staging
             // echo json_encode(['events' => [], 'error' => 'Database statement preparation failed: ' . $conn->error]);
             // In production, a generic error is safer
             // echo json_encode(['events' => [], 'error' => 'Error processing request']);
        }
    } else {
         // This case should ideally not happen if tab is always one of the expected values
         error_log("AJAX fetch called with invalid tab: " . ($tab ?? 'NULL'));
         // echo json_encode(['events' => [], 'error' => 'Invalid tab specified']);
    }

    // Close the database connection for the AJAX request
    if (isset($conn) && $conn instanceof mysqli && $conn->thread_id) {
        $conn->close();
    }

    // Return events as JSON
    // Ensure JSON header is set even if there were errors logged
    header('Content-Type: application/json');
    echo json_encode(['events' => $events]);

    exit(); // Crucially stop here so the HTML part is not sent back in the AJAX response!
}

// --- Handle Initial Page Load (Non-AJAX Request) ---

// If user is not logged in, redirect to login page (redundant check after AJAX block, but safe)
if (!$loggedInUsername) {
    header("Location: login.php"); // Replace with your login page URL
    exit();
}

// --- Constants for Initial Page Load Pagination ---
// const EVENTS_PER_INITIAL_LOAD is already defined at the top


// --- Get current page for initial load (always 1 for the first page) ---
$currentPage = 1;
$offset = ($currentPage - 1) * EVENTS_PER_INITIAL_LOAD;
$limit = EVENTS_PER_INITIAL_LOAD;


// --- Fetch Event Lists for Tabs using Username with Pagination (Initial Load) ---
// Note: These initial queries *should* also join usertable to get college/department consistently
// with the AJAX load for the very first set of events displayed.

// Fetch Upcoming Events CREATED by the User
$upcomingEvents = [];
$sql_upcoming = "SELECT events.*, usertable.college AS display_info
                FROM events
                JOIN usertable ON events.username = usertable.username
                WHERE events.username = ? AND events.event_date >= CURDATE()
                ORDER BY events.event_date ASC LIMIT ? OFFSET ?";
$stmt_upcoming = $conn->prepare($sql_upcoming);
if ($stmt_upcoming) {
    $stmt_upcoming->bind_param("sii", $loggedInUsername, $limit, $offset); // s: string, i: integer, i: integer
    $stmt_upcoming->execute();
    $result_upcoming = $stmt_upcoming->get_result();
    if ($result_upcoming) {
        while ($row = $result_upcoming->fetch_assoc()) {
             $row['event_date_formatted'] = date('M d, Y', strtotime($row['event_date']));
             $row['event_location'] = htmlspecialchars($row['location'] ?? 'N/A');
             $imagePath = 'Event_image/' . $row['image'];
             $row['image_exists_server_side'] = !empty($row['image']) && file_exists($imagePath);
             $row['display_info'] = htmlspecialchars($row['display_info'] ?? 'N/A'); // Get display_info from the joined table
            $upcomingEvents[] = $row;
        }
    } else {
         error_log("Upcoming created events initial query failed: " . $stmt_upcoming->error);
    }
    $stmt_upcoming->close();
} else {
     error_log("Failed to prepare upcoming created events initial statement: " . $conn->error);
}

// Fetch Past Events CREATED by the User
$pastEvents = [];
$sql_past = "SELECT events.*, usertable.college AS display_info
            FROM events
            JOIN usertable ON events.username = usertable.username
            WHERE events.username = ? AND events.event_date < CURDATE()
            ORDER BY events.event_date DESC LIMIT ? OFFSET ?";
$stmt_past = $conn->prepare($sql_past);
if ($stmt_past) {
    $stmt_past->bind_param("sii", $loggedInUsername, $limit, $offset);
    $stmt_past->execute();
    $result_past = $stmt_past->get_result();
    if ($result_past) {
        while ($row = $result_past->fetch_assoc()) {
             $row['event_date_formatted'] = date('M d, Y', strtotime($row['event_date']));
             $row['event_location'] = htmlspecialchars($row['location'] ?? 'N/A');
             $imagePath = 'Event_image/' . $row['image'];
             $row['image_exists_server_side'] = !empty($row['image']) && file_exists($imagePath);
             $row['display_info'] = htmlspecialchars($row['display_info'] ?? 'N/A'); // Get display_info from the joined table
            $pastEvents[] = $row;
        }
    } else {
         error_log("Past created events initial query failed: " . $stmt_past->error);
    }
    $stmt_past->close();
} else {
     error_log("Failed to prepare past created events initial statement: " . $conn->error);
}

// Fetch All Events Created by the User
$createdEvents = [];
$sql_created = "SELECT events.*, usertable.college AS display_info
                FROM events
                JOIN usertable ON events.username = usertable.username
                WHERE events.username = ?
                ORDER BY events.event_date DESC LIMIT ? OFFSET ?";
$stmt_created = $conn->prepare($sql_created);
if ($stmt_created) {
    $stmt_created->bind_param("sii", $loggedInUsername, $limit, $offset);
    $stmt_created->execute();
    $result_created = $stmt_created->get_result();
    if ($result_created) {
        while ($row = $result_created->fetch_assoc()) {
             $row['event_date_formatted'] = date('M d, Y', strtotime($row['event_date']));
             $row['event_location'] = htmlspecialchars($row['location'] ?? 'N/A');
             $imagePath = 'Event_image/' . $row['image'];
             $row['image_exists_server_side'] = !empty($row['image']) && file_exists($imagePath);
             $row['display_info'] = htmlspecialchars($row['display_info'] ?? 'N/A'); // Get display_info from the joined table
            $createdEvents[] = $row;
        }
    } else {
         error_log("All created events initial query failed: " . $stmt_created->error);
    }
    $stmt_created->close();
} else {
     error_log("Failed to prepare all created events initial statement: " . $conn->error);
}

// --- Fetch User Data using Username (Initial Load) ---
// This block is needed for the profile header and event cards on the initial load
// We still need this for the user's own data (name, college for header)
$userData = null;
$stmt_user_initial = $conn->prepare("SELECT email, college, username FROM usertable WHERE username = ? LIMIT 1");
if ($stmt_user_initial) {
    $stmt_user_initial->bind_param("s", $loggedInUsername);
    $stmt_user_initial->execute();
    $result_user_initial = $stmt_user_initial->get_result();
    if ($result_user_initial && $row = $result_user_initial->fetch_assoc()) {
        $userData = $row;
        $userData['display_name'] = $userData['username'];
        $userData['display_info'] = $userData['college']; // Use college for display_info in header
    }
    $stmt_user_initial->close();
}


// --- Fetch Total Counts (without LIMIT/OFFSET) for the Stats Grid ---
// These counts are needed for the header statistics, not for pagination
$totalCreatedEventsCount = 0;
$stmt_total_created_count = $conn->prepare("SELECT COUNT(*) AS total FROM events WHERE username = ?");
if ($stmt_total_created_count) {
    $stmt_total_created_count->bind_param("s", $loggedInUsername);
    $stmt_total_created_count->execute();
    $result_total_created_count = $stmt_total_created_count->get_result();
    if ($result_total_created_count && $row = $result_total_created_count->fetch_assoc()) {
        $totalCreatedEventsCount = $row['total'];
    }
    $stmt_total_created_count->close();
} else {
     error_log("Failed to prepare total created events count statement: " . $conn->error);
}

$upcomingCreatedEventsCount = 0;
$stmt_upcoming_created_count_total = $conn->prepare("SELECT COUNT(*) AS total FROM events WHERE username = ? AND event_date >= CURDATE()");
if ($stmt_upcoming_created_count_total) {
    $stmt_upcoming_created_count_total->bind_param("s", $loggedInUsername);
    $stmt_upcoming_created_count_total->execute();
    $result_upcoming_created_count_total = $stmt_upcoming_created_count_total->get_result();
    if ($result_upcoming_created_count_total && $row = $result_upcoming_created_count_total->fetch_assoc()) {
        $upcomingCreatedEventsCount = $row['total'];
    }
    $stmt_upcoming_created_count_total->close();
} else {
     error_log("Failed to prepare upcoming created events total count statement: " . $conn->error);
}

$pastCreatedEventsCount = 0;
$stmt_past_created_count_total = $conn->prepare("SELECT COUNT(*) AS total FROM events WHERE username = ? AND event_date < CURDATE()");
if ($stmt_past_created_count_total) {
    $stmt_past_created_count_total->bind_param("s", $loggedInUsername);
    $stmt_past_created_count_total->execute();
    $result_past_created_count_total = $stmt_past_created_count_total->get_result();
    if ($result_past_created_count_total && $row = $result_past_created_count_total->fetch_assoc()) {
        $pastCreatedEventsCount = $row['total'];
    }
    $stmt_past_created_count_total->close();
} else {
     error_log("Failed to prepare past created events total count statement: " . $conn->error);
}


// --- Fetch Total Registered Events Count (Assuming a 'registrations' table with 'username') ---
// Keeping this count in case you want to display it elsewhere or add a "Registered Events" tab later
$totalRegisteredEventsCount = 0;
$stmt_total_registered_count = $conn->prepare("SELECT COUNT(*) AS total FROM registrations WHERE username = ?");
if ($stmt_total_registered_count) {
    $stmt_total_registered_count->bind_param("s", $loggedInUsername); // 's' for string username
    $stmt_total_registered_count->execute();
    $result_total_registered_count = $stmt_total_registered_count->get_result();
    if ($result_total_registered_count && $row = $result_total_registered_count->fetch_assoc()) {
        $totalRegisteredEventsCount = $row['total'];
    }
    $stmt_total_registered_count->close();
} else {
     error_log("Failed to prepare total registered events count statement: " . $conn->error);
}


// Close the database connection for the initial page load
if (isset($conn) && $conn instanceof mysqli && $conn->thread_id) {
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Dashboard - EventHub</title>
    <link rel="stylesheet" href="admin.css"> <?php /* Link to the profile/dashboard specific styles */ ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"> <?php /* Font Awesome for icons */ ?>
    <?php /* Link to your header/footer specific CSS if they are separate files */ ?>
</head>
<body> <?php // Removed class="eventhub-dark" ?>
    <?php /* Include your header here if it's a separate file */ ?>
    <?php include 'header.php'; ?>

    <div class="dashboard-container">
        <div class="dashboard-profile-header"> <?php // Renamed class ?>
            <div class="dashboard-profile-header-content"> <?php // Renamed class ?>
                <div class="dashboard-profile-avatar-info"> <?php // Renamed class ?>
                    <div class="dashboard-avatar-large"> <?php // Renamed class ?>
                        <?php // Display first letter of user's username (used as display name)
                        echo htmlspecialchars(substr($userData['display_name'] ?? 'U', 0, 1));
                        ?>
                    </div>
                    <div>
                        <h1 class="dashboard-profile-name"><?php echo htmlspecialchars($userData['display_name'] ?? 'User'); ?></h1> <?php // Renamed class ?>
                        <?php /* Display college or email based on what you prefer */ ?>
                        <p class="dashboard-profile-department"><?php echo htmlspecialchars($userData['display_info'] ?? 'N/A'); ?></p> <?php // Renamed class ?>
                    </div>
                </div>

                <button class="dashboard-edit-profile-btn"> <?php // Renamed class ?>
                    Edit Profile
                </button>
            </div>

            <div class="dashboard-stats-grid"> <?php // Renamed class ?>
                <div class="dashboard-stats-card"> <?php // Renamed class ?>
                    <div class="dashboard-stats-content"> <?php // Renamed class ?>
                        <p class="stats-label">Total Events Created</p> <?php // Updated label ?>
                        <p class="dashboard-stats-value"><?php echo $totalCreatedEventsCount; ?></p> <?php // Updated count variable ?>
                    </div>
                </div>

                <div class="dashboard-stats-card"> <?php // Renamed class ?>
                    <div class="dashboard-stats-content"> <?php // Renamed class ?>
                        <p class="stats-label">Upcoming Created Events</p> <?php // Updated label ?>
                        <p class="dashboard-stats-value"><?php echo $upcomingCreatedEventsCount; ?></p> <?php // Updated count variable ?>
                    </div>
                </div>

                <div class="dashboard-stats-card"> <?php // Renamed class ?>
                    <div class="dashboard-stats-content"> <?php // Renamed class ?>
                        <p class="stats-label">Past Created Events</p> <?php // Updated label ?>
                        <p class="dashboard-stats-value"><?php echo $pastCreatedEventsCount; ?></p> <?php // Updated count variable ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="dashboard-tabs-container"> <?php // Renamed class ?>
            <div class="dashboard-tabs-header"> <?php // Renamed class ?>
                <button class="dashboard-tab-button active" data-tab="upcoming">Upcoming Events</button> <?php // Renamed class ?>
                <button class="dashboard-tab-button" data-tab="past">Past Events</button> <?php // Renamed class ?>
                <button class="dashboard-tab-button" data-tab="created">Created Events</button> <?php // Renamed class ?>
            </div>

            <div class="dashboard-tab-content active" id="upcoming"> <?php // Renamed class ?>
                <div class="dashboard-events-grid"> <?php // Renamed class ?>
                    <?php if (!empty($upcomingEvents)): ?>
                        <?php foreach ($upcomingEvents as $event): ?>
                            <div class="dashboard-event-card"> <?php // Renamed class ?>
                                <div class="dashboard-event-image"> <?php // Renamed class ?>
                                    <?php if ($event['image_exists_server_side']): ?>
                                        <img src="Event_image/<?php echo htmlspecialchars($event['image']); ?>" alt="<?php echo htmlspecialchars($event['title']); ?>">
                                    <?php else: ?>
                                        <div class="dashboard-event-image-placeholder"><i class="fas fa-image"></i></div> <?php // Renamed class ?>
                                    <?php endif; ?>
                                    <span class="dashboard-event-badge"><?php echo htmlspecialchars($event['event_type']); ?></span> <?php // Renamed class ?>
                                </div>
                                <div class="dashboard-event-header"> <?php // Renamed class ?>
                                    <h3 class="dashboard-event-title"><?php echo htmlspecialchars($event['title']); ?></h3> <?php // Renamed class ?>
                                </div>
                                <div class="dashboard-event-content"> <?php // Renamed class ?>
                                    <div class="dashboard-event-detail"> <?php // Renamed class ?>
                                        <span class="dashboard-event-icon"><i class="fas fa-calendar-alt"></i></span> <?php // Renamed class ?>
                                        <span><?php echo $event['event_date_formatted']; ?></span>
                                    </div>
                                     <?php /* Removed time display */ ?>
                                     <?php if (!empty($event['display_info'])): // Display creator's department (college) ?>
                                     <div class="dashboard-event-detail"> <?php // Renamed class ?>
                                         <span class="dashboard-event-icon"><i class="fas fa-building"></i></span> <?php // Using building icon for department ?>
                                         <span><?php echo htmlspecialchars($event['display_info']); ?></span>
                                     </div>
                                     <?php endif; ?>

                                </div>
                                <div class="dashboard-event-footer"> <?php // Renamed class ?>
                                     <?php /* Buttons for created events (e.g., Edit, Delete, View Registrations) */ ?>
                                  
                                      
                                     <button class="dashboard-btn-edit" data-event-id="<?php echo $event['id']; ?>">Edit</button> <?php // Renamed class ?>
                                     <form method="POST">
                                     <input type='hidden' name='delete_id' value="<?php echo $event['id']; ?>">
                                     <button type='submit' name='delete'class="dashboard-btn-delete" >Delete</button>
                                     <form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="no-events-message">No upcoming events created by you found.</p>
                    <?php endif; ?>
                </div>
                 <?php if ($upcomingCreatedEventsCount > EVENTS_PER_INITIAL_LOAD): // Show Load More if there are more events than the initial load ?>
                     <button class="dashboard-load-more-btn" data-tab="upcoming" data-page="2">Load More</button> <?php // Renamed class, data-page starts at 2 ?>
                 <?php endif; ?>
            </div>

            <div class="dashboard-tab-content" id="past"> <?php // Renamed class ?>
                <div class="dashboard-events-grid"> <?php // Renamed class ?>
                    <?php if (!empty($pastEvents)): ?>
                        <?php foreach ($pastEvents as $event): ?>
                            <div class="dashboard-event-card"> <?php // Renamed class ?>
                                <div class="dashboard-event-image event-past-image"> <?php // Renamed class ?>
                                    <?php if ($event['image_exists_server_side']): ?>
                                        <img src="Event_image/<?php echo htmlspecialchars($event['image']); ?>" alt="<?php echo htmlspecialchars($event['title']); ?>">
                                    <?php else: ?>
                                        <div class="dashboard-event-image-placeholder"><i class="fas fa-image"></i></div> <?php // Renamed class ?>
                                    <?php endif; ?>
                                    <span class="dashboard-event-badge"><?php echo htmlspecialchars($event['event_type']); ?></span> <?php // Renamed class ?>
                                </div>
                                <div class="dashboard-event-header"> <?php // Renamed class ?>
                                    <h3 class="dashboard-event-title"><?php echo htmlspecialchars($event['title']); ?></h3> <?php // Renamed class ?>
                                </div>
                                <div class="dashboard-event-content"> <?php // Renamed class ?>
                                    <div class="dashboard-event-detail"> <?php // Renamed class ?>
                                        <span class="dashboard-event-icon"><i class="fas fa-calendar-alt"></i></span> <?php // Renamed class ?>
                                        <span><?php echo $event['event_date_formatted']; ?></span>
                                    </div>
                                     <?php /* Removed time display */ ?>
                                      <?php if (!empty($event['display_info'])): // Display creator's department (college) ?>
                                     <div class="dashboard-event-detail"> <?php // Renamed class ?>
                                         <span class="dashboard-event-icon"><i class="fas fa-building"></i></span> <?php // Using building icon for department ?>
                                         <span><?php echo htmlspecialchars($event['display_info']); ?></span>
                                     </div>
                                     <?php endif; ?>

                                </div>
                                <div class="dashboard-event-footer"> <?php // Renamed class ?>
                                     <?php /* Buttons for created events (e.g., Edit, Delete, View Registrations) */ ?>
                                     <button class="dashboard-btn-edit" data-event-id="<?php echo $event['id']; ?>">Edit</button> <?php // Renamed class ?>
                                     <form method="POST">
                                     <input type='hidden' name='delete_id' value="<?php echo $event['id']; ?>">
                                     <button type='submit' name='delete'class="dashboard-btn-delete" >Delete</button>
                                      </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="no-events-message">No past events created by you found.</p>
                    <?php endif; ?>
                </div>
                 <?php if ($pastCreatedEventsCount > EVENTS_PER_INITIAL_LOAD): // Show Load More if there are more events than the initial load ?>
                     <button class="dashboard-load-more-btn" data-tab="past" data-page="2">Load More</button> <?php // Renamed class, data-page starts at 2 ?>
                 <?php endif; ?>
            </div>

            <div class="dashboard-tab-content" id="created"> <?php // Renamed class ?>
                 <div class="dashboard-events-grid"> <?php // Renamed class ?>
                    <?php if (!empty($createdEvents)): ?>
                        <?php foreach ($createdEvents as $event): ?>
                            <div class="dashboard-event-card"> <?php // Renamed class ?>
                                <div class="dashboard-event-image"> <?php // Renamed class ?>
                                    <?php if ($event['image_exists_server_side']): ?>
                                        <img src="Event_image/<?php echo htmlspecialchars($event['image']); ?>" alt="<?php echo htmlspecialchars($event['title']); ?>">
                                    <?php else: ?>
                                        <div class="dashboard-event-image-placeholder"><i class="fas fa-image"></i></div> <?php // Renamed class ?>
                                    <?php endif; ?>
                                    <span class="dashboard-event-badge"><?php echo htmlspecialchars($event['event_type']); ?></span> <?php // Renamed class ?>
                                </div>
                                <div class="dashboard-event-header"> <?php // Renamed class ?>
                                    <h3 class="dashboard-event-title"><?php echo htmlspecialchars($event['title']); ?></h3> <?php // Renamed class ?>
                                </div>
                                <div class="dashboard-event-content"> <?php // Renamed class ?>
                                    <div class="dashboard-event-detail"> <?php // Renamed class ?>
                                        <span class="dashboard-event-icon"><i class="fas fa-calendar-alt"></i></span> <?php // Renamed class ?>
                                        <span><?php echo $event['event_date_formatted']; ?></span>
                                    </div>
                                     <?php /* Removed time display */ ?>
                                      <?php if (!empty($event['display_info'])): // Display creator's department (college) ?>
                                     <div class="dashboard-event-detail"> <?php // Renamed class ?>
                                         <span class="dashboard-event-icon"><i class="fas fa-building"></i></span> <?php // Using building icon for department ?>
                                         <span><?php echo htmlspecialchars($event['display_info']); ?></span>
                                     </div>
                                     <?php endif; ?>

                                </div>
                                <div class="dashboard-event-footer"> <?php // Renamed class ?>
                                     <?php /* Buttons for created events (e.g., Edit, Delete, View Registrations) */ ?>
                                     <button class="dashboard-btn-edit" data-event-id="<?php echo $event['id']; ?>">Edit</button> <?php // Renamed class ?>
                                     <form method="POST">
                                     <input type='hidden' name='delete_id' value="<?php echo $event['id']; ?>">
                                     <button type='submit' name='delete'class="dashboard-btn-delete" >Delete</button>
                                      </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="dashboard-empty-state"> <?php // Renamed class ?>
                            <div class="dashboard-empty-icon"><i class="fas fa-user"></i></div> <?php // Renamed class ?>
                            <h3 class="dashboard-empty-title">No created events</h3> <?php // Renamed class ?>
                            <p class="dashboard-empty-description"> <?php // Renamed class ?>
                                You haven't created any events yet. Create your first event to see it here.
                            </p>
                            <div class="dashboard-empty-actions"> <?php // Renamed class ?>
                                <a href="create_event.php" class="dashboard-btn-create">Create Event</a> <?php // Renamed class ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                 <?php if ($totalCreatedEventsCount > EVENTS_PER_INITIAL_LOAD): // Show Load More if there are more events than the initial load ?>
                     <button class="dashboard-load-more-btn" data-tab="created" data-page="2">Load More</button> <?php // Renamed class, data-page starts at 2 ?>
                 <?php endif; ?>
            </div>
        </div>
    </div>

    <?php /* Include your footer here if it's a separate file */ ?>
    <?php include 'footer.php'; ?>

    <script>
        // Constants for JavaScript (should match PHP initial load constant)
        const EVENTS_PER_LOAD_JS = <?php echo EVENTS_PER_INITIAL_LOAD; ?>;

        // Tab switching functionality
        document.addEventListener("DOMContentLoaded", () => {
            const tabButtons = document.querySelectorAll('.dashboard-tabs-container .dashboard-tabs-header .dashboard-tab-button'); // Updated selector
            const tabContents = document.querySelectorAll('.dashboard-tabs-container .dashboard-tab-content'); // Updated selector
            const loadMoreButtons = document.querySelectorAll('.dashboard-load-more-btn'); // Get all load more buttons

            // Initially hide all load more buttons except the one for the active tab
             // Find the initially active tab
             const activeTabContent = document.querySelector('.dashboard-tab-content.active');
             const activeLoadMoreButton = activeTabContent ? activeTabContent.querySelector('.dashboard-load-more-btn') : null;

             loadMoreButtons.forEach(btn => {
                 if (btn !== activeLoadMoreButton) {
                     btn.style.display = 'none';
                 }
             });


            tabButtons.forEach(button => {
                button.addEventListener('click', () => {
                    // Remove active class from all tabs and content
                    tabButtons.forEach(btn => btn.classList.remove('active'));
                    tabContents.forEach(content => content.classList.remove('active'));

                    // Add active class to clicked tab and corresponding content
                    button.classList.add('active');
                    const targetTabId = button.getAttribute('data-tab');
                    const targetTabContent = document.getElementById(targetTabId);

                    if (targetTabContent) {
                        targetTabContent.classList.add('active');
                        // Reset the 'data-page' attribute of the corresponding load more button
                        // when switching tabs, so the next load starts from page 2 for that tab.
                        const targetLoadMoreBtn = targetTabContent.querySelector('.dashboard-load-more-btn');

                         // Hide all load more buttons first
                         loadMoreButtons.forEach(btn => {
                             btn.style.display = 'none';
                         });

                        if(targetLoadMoreBtn) {
                            targetLoadMoreBtn.setAttribute('data-page', '2');
                             // Make sure button is visible if there are more events to load for this tab
                             // This requires knowing the total count, which we already fetched.
                             // We need to determine which total count variable corresponds to the target tab.
                             let totalCount = 0;
                             if (targetTabId === 'upcoming') {
                                 totalCount = <?php echo $upcomingCreatedEventsCount; ?>;
                             } else if (targetTabId === 'past') {
                                 totalCount = <?php echo $pastCreatedEventsCount; ?>;
                             } else if (targetTabId === 'created') {
                                 totalCount = <?php echo $totalCreatedEventsCount; ?>;
                             }

                             if (totalCount > EVENTS_PER_LOAD_JS) {
                                 targetLoadMoreBtn.style.display = ''; // Show button if more events exist
                                 targetLoadMoreBtn.disabled = false; // Ensure button is enabled
                                 targetLoadMoreBtn.textContent = 'Load More'; // Reset text
                             } else {
                                 targetLoadMoreBtn.style.display = 'none'; // Hide if no more events
                             }
                        }
                    }
                });
            });

            // --- Placeholder for Edit Profile Button ---
            const editProfileBtn = document.querySelector('.dashboard-edit-profile-btn'); // Updated selector
            if (editProfileBtn) {
                editProfileBtn.addEventListener('click', () => {
                    // alert('Edit Profile button clicked!'); // Replace with actual navigation or modal logic
                    window.location.href = 'edit_profile.php'; // Example redirection
                });
            }

            // --- Placeholder for Edit/Delete Event Buttons (Created Events) ---
            // You would typically add event listeners to these buttons
            // and use the data-event-id attribute to know which event to act on.
            // This would likely involve AJAX calls or redirects to other pages.
            document.querySelectorAll('.dashboard-btn-edit').forEach(button => { // Updated selector
                button.addEventListener('click', () => {
                    const eventId = button.getAttribute('data-event-id');
                    // alert('Edit button clicked for event ID: ' + eventId);

                    window.location.href = 'edit.php?id=' + eventId; // Example redirection
                });
            });

         
          

            // --- Load More Button Functionality ---
            loadMoreButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const tab = this.getAttribute('data-tab');
                    let page = parseInt(this.getAttribute('data-page'));
                    const loadMoreBtn = this; // Reference to the clicked button

                    // Disable button while loading
                    loadMoreBtn.disabled = true;
                    loadMoreBtn.textContent = 'Loading...'; // Change text to indicate loading

                    // Fetch more events via AJAX
                    // *** CORRECTED: AJAX request now goes to admin.php ***
                    fetch(`admin.php?tab=${tab}&page=${page}`, {
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest' // Add header to identify as AJAX
                        }
                    })
                    .then(response => {
                        if (!response.ok) {
                            // If the server responded with an error status code (e.g., 500),
                            // the promise is not rejected by default. We need to check manually.
                            console.error('HTTP error! Status:', response.status);
                            // Try to read response body even on error to get PHP error messages
                            return response.text().then(text => { throw new Error(`HTTP error! Status: ${response.status}, Response: ${text}`); });
                        }
                        return response.json(); // Parse the JSON response
                    })
                    .then(data => {
                        // Check if the JSON response indicates an error from the PHP side
                        if (data.error) {
                            console.error('Server-side error:', data.error);
                            loadMoreBtn.textContent = 'Error loading events'; // Update button text
                            loadMoreBtn.disabled = true; // Keep button disabled on server-side error
                            loadMoreBtn.style.display = 'none'; // Hide the button on error
                            return; // Stop processing
                        }

                        const eventsGrid = document.querySelector(`#${tab} .dashboard-events-grid`); // Get the correct grid

                        if (data.events.length > 0) {
                            // Append new events to the grid
                            data.events.forEach(event => {
                                const eventCardHTML = `
                                    <div class="dashboard-event-card">
                                        <div class="dashboard-event-image">
                                            ${event.image_exists_server_side ?
                                                `<img src="Event_image/${event.image}" alt="${event.title}">` :
                                                `<div class="dashboard-event-image-placeholder"><i class="fas fa-image"></i></div>`
                                            }
                                            <span class="dashboard-event-badge">${event.event_type}</span>
                                        </div>
                                        <div class="dashboard-event-header">
                                            <h3 class="dashboard-event-title">${event.title}</h3>
                                        </div>
                                        <div class="dashboard-event-content">
                                            <div class="dashboard-event-detail">
                                                <span class="dashboard-event-icon"><i class="fas fa-calendar-alt"></i></span>
                                                <span>${event.event_date_formatted}</span>
                                            </div>
                                            ${event.display_info ? // Check if department info exists
                                            `<div class="dashboard-event-detail">
                                                <span class="dashboard-event-icon"><i class="fas fa-building"></i></span>
                                                <span>${event.display_info}</span>
                                            </div>` : ''}

                                        </div>
                                        <div class="dashboard-event-footer">
                                            <button class="dashboard-btn-edit" data-event-id="${event.id}">Edit</button>
                                            <form method="POST">
                                      <input type='hidden' name='delete_id' value="<?php echo $event['id']; ?>">
                                     <button type='submit' name='delete'class="dashboard-btn-delete" >Delete</button>
                                     </form>
                                        </div>
                                    </div>
                                `;
                                eventsGrid.insertAdjacentHTML('beforeend', eventCardHTML);
                            });

                            // Update page number for the next load
                            loadMoreBtn.setAttribute('data-page', page + 1);
                            loadMoreBtn.textContent = 'Load More'; // Reset button text

                            // Hide button if fewer events than EVENTS_PER_LOAD_AJAX were returned
                            // This indicates no more events are available
                            if (data.events.length < EVENTS_PER_LOAD_AJAX) { // Use EVENTS_PER_LOAD_AJAX for comparison
                                loadMoreBtn.style.display = 'none';
                            } else {
                                 loadMoreBtn.disabled = false; // Re-enable button if more events might exist
                                 loadMoreBtn.style.display = ''; // Ensure button is visible if re-enabled
                            }

                        } else {
                            // No more events to load
                            loadMoreBtn.style.display = 'none';
                        }
                    })
                    .catch(error => {
                        console.error('Error loading more events:', error);
                        loadMoreBtn.textContent = 'Error loading events'; // Indicate error
                        loadMoreBtn.disabled = true; // Keep button disabled on error
                        loadMoreBtn.style.display = 'none'; // Hide the button on error
                    });
                });
            });
        });

        // Note: The theme toggle logic from your provided HTML snippet is not included here,
        // as it seems to be part of a broader theme implementation (style.css and a theme-toggle element)
        // that is outside the scope of this specific dashboard content block.
    </script>
</body>
</html>