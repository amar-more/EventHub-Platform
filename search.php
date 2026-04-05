<?php
session_start(); // Start the session if you need user session data (e.g., for header)

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

// --- Constants for Pagination ---
const EVENTS_PER_PAGE = 10; // Define how many events to load per request

// --- Get pagination parameters ---
// Get offset from GET request for subsequent AJAX calls, default to 0 for initial load
$offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
$limit = EVENTS_PER_PAGE; // Use the constant limit

// --- Variables to store filter selections (for retaining state and AJAX) ---
// Using null coalescing operator ?? requires PHP 7+
$search_term = trim($_GET['search'] ?? ''); // Added trim and default empty
$selected_department = $_GET['department'] ?? '';
// $_GET['event_type'] might be a single value, an array, or null/undefined
$selected_event_types = isset($_GET['event_type']) ? (is_array($_GET['event_type']) ? $_GET['event_type'] : [$_GET['event_type']]) : [];
$selected_date_range_option = $_GET['date_range_option'] ?? '';
$selected_date_from = $_GET['date_from'] ?? '';
$selected_date_to = $_GET['date_to'] ?? '';

// --- Fetch options for filters dynamically from the database ---
// Initialize arrays defensively
$departments = [];
$event_types = [];

// Fetch distinct departments
$sql_departments = "SELECT DISTINCT department FROM events ORDER BY department";
$result_departments = $conn->query($sql_departments);
if ($result_departments) { // Check if query was successful
    if ($result_departments->num_rows > 0) {
        while ($row = $result_departments->fetch_assoc()) {
            if (!empty($row['department'])) { // Avoid adding empty department
                 $departments[] = htmlspecialchars($row['department']);
            }
        }
    }
    $result_departments->free(); // Free result set
} else {
    error_log("Error fetching departments: " . $conn->error);
    // Fallback to a static list if database fetch fails (optional)
    // $departments = ["Computer Science", "Engineering", "Business", "Arts & Humanities", "Science"];
}

// Fetch distinct event types
$sql_event_types = "SELECT DISTINCT event_type FROM events ORDER BY event_type";
$result_event_types = $conn->query($sql_event_types);
if ($result_event_types) { // Check if query was successful
    if ($result_event_types->num_rows > 0) {
        while ($row = $result_event_types->fetch_assoc()) {
             if (!empty($row['event_type'])) { // Avoid adding empty event type
                 $event_types[] = htmlspecialchars($row['event_type']);
             }
        }
    }
    $result_event_types->free(); // Free result set
} else {
    error_log("Error fetching event types: " . $conn->error);
    // Fallback to a static list if database fetch fails (optional)
    // $event_types = ["Workshops", "Seminars", "Conferences", "Social Events", "Career Fairs"];
}

// --- Determine if any search term or filter is actively applied by the user ---
// This flag controls whether to show the initial "Start your search" state
$is_actively_filtered_or_searched = !empty($search_term)
                                    || !empty($selected_department)
                                    || !empty($selected_event_types)
                                    || !empty($selected_date_range_option)
                                    || (!empty($selected_date_from) || !empty($selected_date_to));


// --- Build Dynamic SQL Query based on Search and Filters ---
$sql_where_clauses = []; // Array to hold WHERE conditions
$sql_params = []; // Array to hold parameters for prepared statement
$sql_param_types = ''; // String to hold parameter types (s, i, d, etc.)

// Add condition to only show future or present events (this is a base filter, always applied)
$sql_where_clauses[] = "event_date >= CURDATE()";
// CURDATE() does not need a parameter binding


// Add user-applied filters ONLY if a search/filter is active
if ($is_actively_filtered_or_searched) {

    // 1. Basic Search Term
    if (!empty($search_term)) {
        $sql_where_clauses[] = "(title LIKE ? OR description LIKE ? OR college_name LIKE ? OR department LIKE ? OR event_type LIKE ?)";
        $like_term = "%" . $search_term . "%";
        $sql_params[] = $like_term;
        $sql_params[] = $like_term;
        $sql_params[] = $like_term;
        $sql_params[] = $like_term;
        $sql_params[] = $like_term;
        $sql_param_types .= "sssss";
    }

    // 2. Department Filter
    if (!empty($selected_department)) {
        $sql_where_clauses[] = "department = ?";
        $sql_params[] = $selected_department;
        $sql_param_types .= "s";
    }

    // 3. Event Type Filter (Handles multiple selected checkboxes)
    if (!empty($selected_event_types)) {
        $type_placeholders = implode(',', array_fill(0, count($selected_event_types), '?'));
        $sql_where_clauses[] = "event_type IN ($type_placeholders)";
        foreach ($selected_event_types as $type) {
            $sql_params[] = $type;
            $sql_param_types .= "s";
        }
    }

    // 4. Date Range Filter
    $current_date = date('Y-m-d');
    $date_condition_added = false;

    switch ($selected_date_range_option) {
        case 'today':
            $sql_where_clauses[] = "DATE(event_date) = ?";
            $sql_params[] = $current_date; $sql_param_types .= "s"; $date_condition_added = true; break;
        case 'tomorrow':
            $tomorrow = date('Y-m-d', strtotime('+1 day'));
            $sql_where_clauses[] = "DATE(event_date) = ?";
            $sql_params[] = $tomorrow; $sql_param_types .= "s"; $date_condition_added = true; break;
        case 'this_weekend':
             $saturday = date('Y-m-d', strtotime('this Saturday'));
             $sunday = date('Y-m-d', strtotime('this Sunday'));
             if ($saturday < $current_date) { $saturday = date('Y-m-d', strtotime('next Saturday')); $sunday = date('Y-m-d', strtotime('next Sunday')); } elseif ($saturday == $current_date) { $sunday = date('Y-m-d', strtotime('+1 day')); }
             if ($sunday >= $current_date) {
                 $sql_where_clauses[] = "DATE(event_date) BETWEEN ? AND ?";
                 $sql_params[] = $saturday; $sql_params[] = $sunday; $sql_param_types .= "ss"; $date_condition_added = true;
             }
            break;
        case 'this_week':
             $start_of_week = date('Y-m-d', strtotime('monday this week')); $end_of_week = date('Y-m-d', strtotime('sunday this week'));
             if ($start_of_week < $current_date && date('N') != 1) { $start_of_week = $current_date; }
             if ($end_of_week >= $current_date) {
                 $sql_where_clauses[] = "DATE(event_date) BETWEEN ? AND ?";
                 $sql_params[] = $start_of_week; $sql_params[] = $end_of_week; $sql_param_types .= "ss"; $date_condition_added = true;
             }
            break;
        case 'this_month':
            $start_of_month = date('Y-m-01'); $end_of_month = date('Y-m-t');
            if ($start_of_month < $current_date) { $start_of_month = $current_date; }
             if ($end_of_month >= $current_date) {
                 $sql_where_clauses[] = "DATE(event_date) BETWEEN ? AND ?";
                 $sql_params[] = $start_of_month; $sql_params[] = $end_of_month; $sql_param_types .= "ss"; $date_condition_added = true;
             }
            break;
    }

    if (!$date_condition_added && (!empty($selected_date_from) || !empty($selected_date_to))) {
        $valid_from_date = !empty($selected_date_from) && strtotime($selected_date_from) !== false;
        $valid_to_date = !empty($selected_date_to) && strtotime($selected_date_to) !== false;

        if ($valid_from_date && $valid_to_date) {
            if ($selected_date_from <= $selected_date_to && $selected_date_to >= $current_date) {
                 $start_date_for_query = ($selected_date_from < $current_date) ? $current_date : $selected_date_from;
                 $sql_where_clauses[] = "event_date BETWEEN ? AND ?";
                 $sql_params[] = $start_date_for_query; $sql_params[] = $selected_date_to; $sql_param_types .= "ss"; $date_condition_added = true;
            }
        } elseif ($valid_from_date && $selected_date_from >= $current_date) {
             $sql_where_clauses[] = "event_date >= ?";
             $sql_params[] = $selected_date_from; $sql_param_types .= "s"; $date_condition_added = true;
        } elseif ($valid_to_date && $selected_date_to >= $current_date) {
             $sql_where_clauses[] = "event_date <= ?";
             $sql_params[] = $selected_date_to; $sql_param_types .= "s"; $date_condition_added = true;
        }
    }
}


// Combine WHERE clauses into a base SQL query string
$base_sql = "SELECT id, title, description, event_date, image, college_name, department, referencelink, event_type FROM events";

if (!empty($sql_where_clauses)) {
    $base_sql .= " WHERE " . implode(' AND ', $sql_where_clauses);
}

$base_sql .= " ORDER BY event_date ASC"; // Order by date ascending for upcoming events


// --- Handle AJAX Request for More Events ---
// Check for a flag indicating it's an AJAX request for more data
if (isset($_GET['is_ajax']) && $_GET['is_ajax'] == '1') {

    $paged_sql = $base_sql . " LIMIT ? OFFSET ?"; // Add LIMIT and OFFSET for pagination

    $stmt_paged_search = $conn->prepare($paged_sql);

    if ($stmt_paged_search) {
        $paged_params = array_merge($sql_params, [$limit, $offset]);
        $paged_param_types = $sql_param_types . "ii"; // 'ii' for integer limit and offset

        if (strlen($paged_param_types) !== count($paged_params)) {
             error_log("AJAX Binding error: Parameter count mismatch. Types: " . strlen($paged_param_types) . ", Params: " . count($paged_params) . " SQL: " . $paged_sql . " SQL_params: " . print_r($sql_params, true));
             header('Content-Type: application/json');
             echo json_encode(['error' => 'Internal error: Parameter mismatch']);
             exit;
        }

        $stmt_paged_search->bind_param($paged_param_types, ...$paged_params);

        $stmt_paged_search->execute();
        $result_paged_search = $stmt_paged_search->get_result();

        $paged_results = [];
        if ($result_paged_search) {
            while ($row = $result_paged_search->fetch_assoc()) {
                $row['event_date_formatted'] = date('M d, Y', strtotime($row['event_date']));
                 $imagePath = 'Event_image/' . $row['image'];
                 $row['image_exists_server_side'] = !empty($row['image']) && file_exists($imagePath);
                $paged_results[] = $row;
            }
            $result_paged_search->free();
        } else {
             error_log("AJAX Search query failed: " . $stmt_paged_search->error);
             header('Content-Type: application/json');
             echo json_encode(['error' => 'Failed to fetch data']);
             exit;
        }
        $stmt_paged_search->close();

        header('Content-Type: application/json');
        echo json_encode($paged_results);
        exit;
    } else {
         error_log("Failed to prepare AJAX search statement: " . $conn->error);
         header('Content-Type: application/json');
         echo json_encode(['error' => 'Failed to prepare statement']);
         exit;
    }
}
// --- End of AJAX Handling ---


// --- Normal Page Load Handling (Fetch first chunk and total count) ---

$search_results = []; // Array for the FIRST CHUNK of results on page load
$search_results_count = 0; // Total count


// Only perform count and initial fetch if a search/filter is active
if ($is_actively_filtered_or_searched) {

    // First, get the total count without LIMIT/OFFSET
    $count_sql = "SELECT COUNT(*) as total FROM events";
    if (!empty($sql_where_clauses)) {
        $count_sql .= " WHERE " . implode(' AND ', $sql_where_clauses);
    }

    $stmt_count = $conn->prepare($count_sql);
    if ($stmt_count) {
        if (!empty($sql_param_types) && !empty($sql_params)) {
             $stmt_count->bind_param($sql_param_types, ...$sql_params);
        }

        $stmt_count->execute();
        $result_count = $stmt_count->get_result();
        if ($result_count && $row_count = $result_count->fetch_assoc()) {
            $search_results_count = $row_count['total'];
        } else {
             error_log("Count query failed: " . $stmt_count->error);
        }
        if ($result_count) $result_count->free();
        $stmt_count->close();
    } else {
        error_log("Failed to prepare count statement: " . $conn->error);
    }


    // Second, fetch the first chunk with LIMIT/OFFSET (offset is 0 for the initial load)
    if ($search_results_count > 0) {
        $initial_sql = $base_sql . " LIMIT ? OFFSET ?";
        $stmt_initial_search = $conn->prepare($initial_sql);

        if ($stmt_initial_search) {
            $initial_params = array_merge($sql_params, [$limit, 0]);
            $initial_param_types = $sql_param_types . "ii";

             if (strlen($initial_param_types) !== count($initial_params)) {
                  error_log("Initial Binding error: Parameter count mismatch. Types: " . strlen($initial_param_types) . ", Params: " . count($initial_params) . " SQL: " . $initial_sql . " SQL_params: " . print_r($sql_params, true));
             } else {
                 $stmt_initial_search->bind_param($initial_param_types, ...$initial_params);

                 $stmt_initial_search->execute();
                 $result_initial_search = $stmt_initial_search->get_result();

                 if ($result_initial_search) {
                      while ($row = $result_initial_search->fetch_assoc()) {
                          $row['event_date_formatted'] = date('M d, Y', strtotime($row['event_date']));
                           $imagePath = 'Event_image/' . $row['image'];
                           $row['image_exists_server_side'] = !empty($row['image']) && file_exists($imagePath);
                          $search_results[] = $row;
                      }
                 } else {
                      error_log("Initial Search query failed: " . $stmt_initial_search->error);
                 }
                 if ($result_initial_search) $result_initial_search->free();
             }
            $stmt_initial_search->close();
        } else {
            error_log("Failed to prepare initial search statement: " . $conn->error);
        }
    }
}
// Note: If !$is_actively_filtered_or_searched, $search_results and $search_results_count remain 0,
// which correctly triggers the empty state message in the HTML.


// --- Fetch Trending Events (Remains separate, not affected by search/filters unless you modify this query) ---
// Example: Most recent 3 events, regardless of filters, that are upcoming
$trending_events = [];
$sql_trending = "SELECT id, title, description, event_date, image, college_name, department, referencelink, event_type FROM events WHERE event_date >= CURDATE() ORDER BY event_date DESC LIMIT 3"; // Added CURDATE filter to trending

$stmt_trending = $conn->prepare($sql_trending);

if ($stmt_trending) {
    $stmt_trending->execute();
    $result_trending = $stmt_trending->get_result();

    if ($result_trending) {
        while ($row = $result_trending->fetch_assoc()) {
            $trending_events[] = $row;
        }
    } else {
        error_log("Trending query failed: " . $stmt_trending->error);
    }
    if ($result_trending) $result_trending->free();
    $stmt_trending->close();
} else {
    error_log("Failed to prepare trending statement: " . $conn->error);
}


// Close the database connection for the main page load
if (isset($conn) && $conn instanceof mysqli && $conn->thread_id) {
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EventHub - Search Events</title>
    <?php /* Link your main styles first */ ?>
    <?php /* <link rel="stylesheet" href="style.css"> */ ?>
    <?php /* Link your header styles */ ?>
    <?php /* Link your footer styles */ ?>
    <link rel="stylesheet" href="search.css"> <?php /* Link to the new search page specific styles */ ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <?php /* REMOVED the <style> block that defined the horizontal card layout - your search.css should handle this */ ?>
</head>
<body>
<?php
            // Include the header file - ensure this file uses the correct header class names if it contains search inputs
            include 'header.php';
        ?>

    <div class="search-page-container">
        
        <main>
</br>
            <section class="search-page-hero-section">
                <div class="search-page-hero-content">
                    <h1 class="search-page-hero-title">Find <span class="search-page-gradient-text">Campus Events</span></h1>
                    <p class="search-page-hero-description">Discover events happening around your campus</p>

                    <form action="search.php" method="get" id="searchForm"> <?php // Added ID ?>
                        <div class="search-page-search-container">
                            <div class="search-page-search-input-container">
                                <i class="fas fa-search search-page-search-icon"></i>
                                <input type="text" name="search" class="search-page-search-input" placeholder="Search for events, departments, or categories..." value="<?php echo htmlspecialchars($search_term); ?>">
                                <button type="submit" class="search-page-search-button">Search</button>
                            </div>

                            <div class="search-page-advanced-filters-container">
                                <?php /* This button will now toggle the panel via JS */ ?>
                                <button type="button" class="search-page-advanced-filters-btn">
                                    Advanced Filters
                                    <i class="fas fa-filter"></i>
                                </button>
                            </div>
                        </div>

                        <?php
                             // PHP to determine if the panel should be visible on load
                             // It should be visible if the user previously applied filters (is_actively_filtered_or_searched is true)
                        ?>
                       <div class="advanced-filters-panel" style="display: none;">
                            <div class="filters-header">
                                <h3>Advanced Filters</h3>
                                <?php /* This button will now close the panel via JS */ ?>
                                <button type="button" class="close-filters-btn"><i class="fas fa-times"></i></button>
                            </div>

                            <div class="filters-content">
                                <div class="filter-group">
                                    <h4>Event Type</h4>
                                    <div class="checkbox-group">
                                        <?php
                                             // Dynamically generate Event Type checkboxes
                                             // Add a check to ensure $event_types is an array and not empty
                                             if (is_array($event_types) && !empty($event_types)) {
                                                 foreach($event_types as $type_val) {
                                                     $type = htmlspecialchars($type_val);
                                                     $checked = in_array($type_val, $selected_event_types) ? 'checked' : '';
                                                     echo '<label><input type="checkbox" name="event_type[]" value="' . $type . '" ' . $checked . '> ' . $type . '</label>';
                                                 }
                                             } else {
                                                 // Display static text when no options are available
                                                 echo "<p>No event types available.</p>";
                                             }
                                        ?>
                                    </div>
                                </div>

                                <div class="filter-group">
                                    <h4>Date Range</h4>
                                     <?php /* Add names to date inputs, retain values */ ?>
                                    <div class="checkbox-group">
                                         <label><input type="checkbox" name="date_range_option" value="today" <?php echo ($selected_date_range_option === 'today') ? 'checked' : ''; ?>> Today</label>
                                         <label><input type="checkbox" name="date_range_option" value="tomorrow" <?php echo ($selected_date_range_option === 'tomorrow') ? 'checked' : ''; ?>> Tomorrow</label>
                                         <label><input type="checkbox" name="date_range_option" value="this_weekend" <?php echo ($selected_date_range_option === 'this_weekend') ? 'checked' : ''; ?>> This Weekend</label>
                                         <label><input type="checkbox" name="date_range_option" value="this_week" <?php echo ($selected_date_range_option === 'this_week') ? 'checked' : ''; ?>> This Week</label>
                                         <label><input type="checkbox" name="date_range_option" value="this_month" <?php echo ($selected_date_range_option === 'this_month') ? 'checked' : ''; ?>> This Month</label>
                                    </div>
                                    <div class="date-inputs">
                                         <div class="date-input-group">
                                             <label>From:</label>
                                             <input type="date" name="date_from" value="<?php echo htmlspecialchars($selected_date_from); ?>">
                                         </div>
                                         <div class="date-input-group">
                                             <label>To:</label>
                                             <input type="date" name="date_to" value="<?php echo htmlspecialchars($selected_date_to); ?>">
                                         </div>
                                    </div>
                                </div>

                                <div class="filter-group">
                                    <h4>Department</h4>
                                     <?php
                                        // Check if departments were loaded and not empty
                                        if (is_array($departments) && !empty($departments)) {
                                    ?>
                                        <select name="department">
                                            <option value="" <?php echo ($selected_department === '') ? 'selected' : ''; ?>>All Departments</option>
                                            <?php
                                                 // Dynamically generate Department options
                                                  foreach($departments as $dept_val) {
                                                      $dept = htmlspecialchars($dept_val);
                                                      $selected = ($selected_department === $dept_val) ? 'selected' : '';
                                                      echo '<option value="' . $dept . '" ' . $selected . '>' . $dept . '</option>';
                                                  }
                                            ?>
                                        </select>
                                    <?php
                                        } else {
                                            // Display static text when no options are available and disable select
                                            echo "<p>No departments found.</p>";
                                            echo '<select name="department" disabled>';
                                            echo '<option value="">All Departments</option>';
                                            echo '</select>';
                                        }
                                    ?>
                                </div>

                                <div class="filter-actions">
                                     <?php /* This button will perform a standard HTML form reset */ ?>
                                     <button type="button" class="outline-btn reset-filters-btn">Reset</button> <?php /* Changed back to type="button" for JS handling */ ?>
                                    <button type="submit" class="primary-btn">Apply Filters</button>
                                </div>
                            </div>
                        </div>
                    </form> <?php /* Closed form tag */ ?>
                </div>
            </section>

            <div class="search-page-search-results-container">
                <section class="search-page-search-results">
                    <h2 class="search-page-section-title">Search Results (<span id="resultsCount"><?php echo $search_results_count; ?></span>)</h2> <?php // Added ID ?>

                    <?php if (!$is_actively_filtered_or_searched): // Show initial empty state ONLY if no search/filters are active ?>
                         <div class="search-page-empty-search-state">
                             <div class="search-page-empty-search-icon">
                                 <i class="fas fa-search fa-4x"></i>
                             </div>
                             <h3>Start your search</h3>
                             <p>Enter keywords or apply filters above to find events</p>
                         </div>
                    <?php else: // Show results grid if a search/filter is active ?>
                         <div class="search-page-events-grid" id="searchResultsGrid"> <?php // Added ID ?>
                             <?php if (!empty($search_results)): ?>
                                 <?php foreach ($search_results as $event): // Loop through the FIRST CHUNK of search results ?>
                                      <div class="search-page-event-card search-page-event-card-horizontal">
                                          <?php
                                             $imagePath = 'Event_image/' . htmlspecialchars($event['image']);
                                             // Basic check if image file exists locally before linking
                                             $imageExists = !empty($event['image']) && file_exists($imagePath);
                                          ?>
                                          <?php if ($imageExists): ?>
                                               <img src="<?php echo $imagePath; ?>" alt="<?php echo htmlspecialchars($event['title']); ?>" class="search-page-event-image search-page-event-image-horizontal">
                                          <?php else: ?>
                                                <div class="search-page-event-image-placeholder search-page-event-image-horizontal-placeholder"><i class="fas fa-image"></i></div>
                                          <?php endif; ?>
                                          <div class="search-page-event-details search-page-event-details-horizontal">
                                               <h3 class="search-page-event-title"><?php echo htmlspecialchars($event['title']); ?></h3>
                                               <p class="search-page-event-category"><?php echo htmlspecialchars($event['department']); ?> - <?php echo htmlspecialchars($event['event_type']); ?></p>
                                               <div class="search-page-event-info">
                                                    <p><i class="fas fa-calendar"></i> Date: <?php echo $event['event_date_formatted']; ?></p> <?php // Use the formatted date from PHP ?>
                                                    <p><i class="fas fa-building"></i> College: <?php echo htmlspecialchars($event['college_name']); ?></p>
                                               </div>
                                               <p class="search-page-event-description"><?php echo nl2br(htmlspecialchars($event['description'])); // Use nl2br for line breaks ?></p>
                                               <?php if (!empty($event['referencelink'])): ?>
                                                    <a href="<?php echo htmlspecialchars($event['referencelink']); ?>" class="register-button" target="_blank"><i class="fas fa-external-link-alt"></i> Register / Learn More</a>
                                               <?php endif; ?>
                                          </div>
                                      </div>
                                 <?php endforeach; ?>
                             <?php else: // Show this if search/filter was active but returned no results ?>
                                  <p style="text-align: center;">No events found matching your criteria.</p>
                             <?php endif; ?>
                         </div>

                         <?php if ($search_results_count > EVENTS_PER_PAGE): // Show button only if there are potentially more results ?>
                             <button id="loadMoreBtn" class="primary-btn" style="display: block; margin: 20px auto;">Load More</button> <?php // Add basic styling ?>
                         <?php endif; ?>

                    <?php endif; ?>

                </section>

                <section class="search-page-trending-events">
                    <h2 class="search-page-section-title">
                        <i class="fas fa-chart-line"></i>
                        Trending Events
                    </h2>

                    <div class="search-page-trending-events-list">
                         <?php if (!empty($trending_events)): ?>
                              <?php foreach ($trending_events as $event): // Loop through trending events ?>
                                   <div class="search-page-trending-event-card">
                                        <div class="search-page-trending-event-details">
                                             <h3 class="search-page-trending-event-title"><?php echo htmlspecialchars($event['title']); ?></h3>
                                             <p class="search-page-trending-event-category"><?php echo htmlspecialchars($event['department']); ?></p>
                                             <div class="search-page-trending-event-info">
                                                 <p><i class="fas fa-calendar"></i> Date: <?php echo date('M d, Y', strtotime($event['event_date'])); ?></p>
                                                  <p><i class="fas fa-building"></i> College: <?php echo htmlspecialchars($event['college_name']); ?></p>
                                             </div>
                                        </div>
                                        <div class="search-page-trending-event-stats">
                                             <?php if (!empty($event['referencelink'])): ?>
                                                  <a href="<?php echo htmlspecialchars($event['referencelink']); ?>" class="outline-btn" target="_blank">Register</a>
                                             <?php endif; ?>
                                        </div>
                                   </div>
                              <?php endforeach; ?>
                         <?php else: ?>
                              <p style="text-align: center;">No trending events found.</p>
                         <?php endif; ?>
                    </div>
                </section>
            </div>

        </main>

        <section class="search-page-cta-section">
            <div class="search-page-cta-content">
                <h2 class="search-page-cta-title">Can't find what you're looking for?</h2>
                <p class="search-page-cta-description">Create your own event and invite others to join. It's easy and free to get started.</p>
                <div class="search-page-cta-buttons">
                    <a href="create_event.php" class="cta-primary-btn">Create Event</a>
                    <a href="contactus.php" class="cta-outline-btn">Contact Support</a> <?php /* Update link as needed */ ?>
                </div>
            </div>
        </section>

        

    </div>
    <?php include 'footer.php'; ?>
    <script>
        // PHP variables passed to JavaScript
        const eventsPerPage = <?php echo EVENTS_PER_PAGE; ?>;
        let currentOffset = eventsPerPage; // Start offset after the initially loaded events
        const totalResults = <?php echo $search_results_count; ?>; // Total matching results from the initial count query


        // Get elements
        const searchResultsGrid = document.getElementById('searchResultsGrid');
        const loadMoreBtn = document.getElementById('loadMoreBtn');
        const resultsCountSpan = document.getElementById('resultsCount'); // Span to update count
        const searchForm = document.getElementById('searchForm'); // Get the form
        const filtersPanel = document.querySelector('.advanced-filters-panel'); // Get filters panel


        // Function to create HTML for an event card (reusing logic)
        function createEventCardHtml(event) {
            // Escape HTML special characters for safety
            const title = escapeHTML(event.title);
            const description = escapeHTML(event.description);
            const department = escapeHTML(event.department);
            const eventType = escapeHTML(event.event_type);
            const collegeName = escapeHTML(event.college_name);
            const referenceLink = escapeHTML(event.referencelink);

            // Use the flag from the backend to decide between image or placeholder
            const imageUrl = event.image_exists_server_side ? `Event_image/${escapeHTML(event.image)}` : '';

            const formattedDate = event.event_date_formatted; // Use the formatted date from the backend JSON

            // Build the image HTML or placeholder
            const imageHtml = imageUrl ?
                `<img src="${imageUrl}" alt="${title}" class="search-page-event-image search-page-event-image-horizontal">` :
                `<div class="search-page-event-image-placeholder search-page-event-image-horizontal-placeholder"><i class="fas fa-image"></i></div>`;


            // Build the register button HTML
            const registerButtonHtml = referenceLink ?
                `<a href="${referenceLink}" class="register-button" target="_blank"><i class="fas fa-external-link-alt"></i> Register / Learn More</a>` :
                ''; // No button if no link

            return `
                <div class="search-page-event-card search-page-event-card-horizontal">
                    ${imageHtml}
                    <div class="search-page-event-details search-page-event-details-horizontal">
                        <h3 class="search-page-event-title">${title}</h3>
                        <p class="search-page-event-category">${department} - ${eventType}</p>
                        <div class="search-page-event-info">
                            <p><i class="fas fa-calendar"></i> Date: ${formattedDate}</p>
                            <p><i class="fas fa-building"></i> College: ${collegeName}</p>
                        </div>
                         <p class="search-page-event-description">${nl2br(description)}</p> <?php // Use JS nl2br ?>
                        ${registerButtonHtml}
                    </div>
                </div>
            `;
        }

         // Helper function for basic HTML escaping
        function escapeHTML(str) {
             if (typeof str !== 'string') return '';
             const div = document.createElement('div');
             div.appendChild(document.createTextNode(str));
             return div.innerHTML;
        }

        // Helper function to convert newlines to <br> (mimic nl2br)
        function nl2br(str) {
            if (typeof str !== 'string') return '';
            return str.replace(/(?:\r\n|\r|\n)/g, '<br>');
        }


        // Function to fetch and display more events
        async function loadMoreEvents() {
            // Disable button while loading
            if (loadMoreBtn) {
               loadMoreBtn.disabled = true;
               loadMoreBtn.textContent = 'Loading...'; // Optional feedback
            }

            // Construct the URL for the AJAX request
            // Get existing form data - THIS IS WHERE CURRENT FILTERS ARE CAPTURED
            const formData = new FormData(searchForm);
            const searchParams = new URLSearchParams();

            // Add existing form data
            // Using formData.entries() correctly handles multi-value inputs like checkboxes
            for (const pair of formData.entries()) {
                 // Only add parameters that have values (prevents sending empty filters)
                 // Also prevents sending checkbox names if none are checked
                 if (pair[1] !== '' && pair[1] !== null) {
                     searchParams.append(pair[0], pair[1]);
                 }
            }

            // Add pagination parameters
            searchParams.set('offset', currentOffset);
            searchParams.set('limit', eventsPerPage);
            searchParams.set('is_ajax', '1'); // Custom flag for PHP to detect AJAX

            // Get the base URL of the form action (e.g., "search.php")
            const baseUrl = searchForm.action.split('?')[0];
            const url = baseUrl + '?' + searchParams.toString(); // Request to the same page

            console.log("Fetching URL:", url); // Log the URL being fetched

            try {
                const response = await fetch(url, {
                    headers: {
                       'X-Requested-With': 'XMLHttpRequest' // Standard AJAX header
                    }
                });

                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                const events = await response.json();

                console.log("Received events:", events); // Log the received data

                if (events.error) {
                    console.error("Backend error:", events.error);
                    // Display an error message to the user if needed
                    if (loadMoreBtn) loadMoreBtn.textContent = 'Error loading events.';
                    return; // Stop processing
                }

                if (events.length > 0) {
                    events.forEach(event => {
                        const eventCardHTML = createEventCardHtml(event);
                        if (searchResultsGrid) {
                            searchResultsGrid.insertAdjacentHTML('beforeend', eventCardHTML); // Append new cards
                        }
                    });

                    // Update offset for the next load
                    currentOffset += events.length;

                    // Check if all total results have been loaded
                    // This check relies on totalResults being accurate.
                    // An alternative is checking if the returned 'events.length' is less than 'eventsPerPage'
                    if (currentOffset >= totalResults) {
                        if (loadMoreBtn) loadMoreBtn.style.display = 'none'; // Hide button
                    } else {
                        if (loadMoreBtn) loadMoreBtn.textContent = 'Load More'; // Reset button text
                    }

                } else {
                    // No new events returned (might happen if totalResults count was slightly off
                    // or if the last chunk was exactly the limit size, or no more matches)
                    if (loadMoreBtn) loadMoreBtn.style.display = 'none'; // Hide button
                }

            } catch (error) {
                console.error('Error fetching more events:', error);
                if (loadMoreBtn) loadMoreBtn.textContent = 'Failed to load events.'; // Error state text
            } finally {
                // Re-enable button if it's still visible
                if (loadMoreBtn && loadMoreBtn.style.display !== 'none') {
                    loadMoreBtn.disabled = false;
                }
            }
        }

        // Add event listener to the Load More button if it exists
        // The button's initial visibility is controlled by PHP based on totalResults > EVENTS_PER_PAGE
        if (loadMoreBtn) {
            loadMoreBtn.addEventListener('click', loadMoreEvents);
        }


        // --- Advanced Filter Panel Toggle Logic ---
        const advancedFiltersBtn = document.querySelector('.search-page-advanced-filters-btn');
        const closeFiltersBtn = document.querySelector('.close-filters-btn');
        const resetFiltersBtn = document.querySelector('.reset-filters-btn'); // Get reset button

        if (advancedFiltersBtn && filtersPanel && closeFiltersBtn) {
            advancedFiltersBtn.addEventListener('click', function() {
                filtersPanel.style.display = filtersPanel.style.display === 'none' || filtersPanel.style.display === '' ? 'block' : 'none';
            });
            closeFiltersBtn.addEventListener('click', function() {
                filtersPanel.style.display = 'none';
            });
        } else {
             console.error("One or more elements for advanced filters panel not found!");
        }

        // --- Close filter panel on form submit (Apply Filters / Main Search Button) ---
        if (searchForm && filtersPanel) {
            searchForm.addEventListener('submit', function(event) {
                // We do NOT prevent default here, as we want the form to submit and the page to reload
                // Just hide the panel immediately before the submission happens
                filtersPanel.style.display = 'none';
            });
        } else {
             console.warn("Search form or filters panel not found for submit listener.");
        }


        // Handle Reset Button
        if (resetFiltersBtn) {
             resetFiltersBtn.addEventListener('click', function(event) {
                 event.preventDefault(); // Prevent default action

                 const form = resetFiltersBtn.closest('form');
                 if (form) {
                     // Clear all form fields
                     form.querySelectorAll('input[type="text"]').forEach(input => input.value = '');
                     form.querySelectorAll('input[type="checkbox"]').forEach(checkbox => checkbox.checked = false);
                     form.querySelectorAll('input[type="date"]').forEach(dateInput => dateInput.value = '');
                     form.querySelectorAll('select').forEach(select => select.selectedIndex = 0);

                     // Navigate to the base search page URL (without any GET parameters)
                     // This reloads the page to the initial state
                     window.location.href = form.action.split('?')[0];
                 }
             });
        } else {
             console.warn("Reset Filters button not found.");
        }


        // **Date Range Checkbox Logic: Only allow one date range checkbox to be selected**
        const dateRangeCheckboxes = document.querySelectorAll('.filters-content input[name="date_range_option"]');
        const dateFromInput = document.querySelector('.filters-content input[name="date_from"]');
        const dateToInput = document.querySelector('.filters-content input[name="date_to"]');

        if (dateRangeCheckboxes.length > 0) {
            dateRangeCheckboxes.forEach(checkbox => {
                checkbox.addEventListener('change', function() {
                    if (this.checked) {
                        dateRangeCheckboxes.forEach(otherCheckbox => {
                            if (otherCheckbox !== this) {
                                otherCheckbox.checked = false;
                            }
                        });
                        // Clear custom date inputs if a preset is selected
                        if (dateFromInput) dateFromInput.value = '';
                        if (dateToInput) dateToInput.value = '';
                    }
                });
            });
        }

        // **Custom Date Input Logic: Uncheck preset date range if custom dates are entered**
        if (dateFromInput && dateToInput && dateRangeCheckboxes.length > 0) {
            const clearPresetDateRange = () => {
                dateRangeCheckboxes.forEach(checkbox => {
                    checkbox.checked = false;
                });
            };
            dateFromInput.addEventListener('input', clearPresetDateRange);
            dateToInput.addEventListener('input', clearPresetDateRange);
        }

    </script>
</body>
</html>