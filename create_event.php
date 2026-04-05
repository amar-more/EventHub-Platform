<?php
session_start(); // Start the session at the very beginning

// Check if the user is logged in. If not, redirect to login page.
if (!isset($_SESSION['userid'])) {
    header("Location: login.php"); // Redirect to your login page
    exit; // Stop script execution after redirection
}

include "database.php"; // Include database connection details

$error_message = "";
$success_message = "";

// --- Form Submission Logic ---
if (isset($_POST['createevent'])) {
    // No need to start session again, it's already started.
    $userid = $_SESSION['userid']; // Get userid (assuming it's the username string)

    // Fetch college name based on the logged-in user
    $sql_get_college = "SELECT college FROM usertable WHERE username = ?";
    $stmt_college = $conn->prepare($sql_get_college);
    if ($stmt_college) {
        $stmt_college->bind_param("s", $userid);
        $stmt_college->execute();
        $result_college = $stmt_college->get_result();

        if ($result_college && $result_college->num_rows > 0) {
            $row = $result_college->fetch_assoc();
            $collegename = $row['college'];
        } else {
            $error_message = "Error: Could not find college information for your user account.";
             // Log this error: error_log("College not found for user: " . $userid);
        }
        $stmt_college->close();
    } else {
         $error_message = "Database error while fetching college information.";
         // Log this error: error_log("Failed to prepare college query: " . $conn->error);
    }


    // --- Input Validation and Sanitization ---
    $eventname = trim($_POST['eventname']);
    $descrp = trim($_POST['descrp']);
    $date_str = trim($_POST['date']);
    $department = trim($_POST['department']);
    $referencelink = trim($_POST['referencelink']);
    $event_type = trim($_POST['event_type']);

    // Validate required fields are not empty
    if (empty($eventname) || empty($descrp) || empty($date_str) || empty($department) || empty($referencelink) || empty($event_type) || empty($collegename)) {
        $error_message = "Please fill in all required fields.";
    }

    // Validate date format and convert
    $event_date = null;
    if (!empty($date_str)) {
        $event_date = date('Y-m-d', strtotime($date_str));
        if (!$event_date || $event_date == '1970-01-01') { // Check if date conversion failed
            $error_message = "Invalid date format.";
        }
    }

    // Validate URL format for referencelink
    if (!empty($referencelink) && !filter_var($referencelink, FILTER_VALIDATE_URL)) {
         $error_message = "Invalid reference link format.";
    }

    // Sanitize inputs (basic sanitization, more depending on expected content)
    $eventname = htmlspecialchars($eventname, ENT_QUOTES, 'UTF-8');
    $descrp = htmlspecialchars($descrp, ENT_QUOTES, 'UTF-8');
    $department = htmlspecialchars($department, ENT_QUOTES, 'UTF-8');
    $referencelink = htmlspecialchars($referencelink, ENT_QUOTES, 'UTF-8');
    $event_type = htmlspecialchars($event_type, ENT_QUOTES, 'UTF-8');


    // --- Handle File Upload ---
    $file_name = ''; // Initialize file name
    $upload_dir = 'Event_image/'; // Define the target folder
    $allowed_types = ['jpg' => 'image/jpeg', 'png' => 'image/png', 'pdf' => 'application/pdf'];
    $max_file_size = 5 * 1024 * 1024; // 5MB max size

    if (isset($_FILES['image']) && $_FILES['image']['error'] == UPLOAD_ERR_OK) {
        // Get file details
        $file_info = $_FILES['image'];
        $file_name = basename($file_info['name']);
        $file_tmp_name = $file_info['tmp_name'];
        $file_size = $file_info['size'];
        $file_type = $file_info['type'];

        // Get file extension
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

        // Check file size
        if ($file_size > $max_file_size) {
            $error_message = "Error: File size exceeds the maximum allowed (" . ($max_file_size / 1024 / 1024) . "MB).";
        }

        // Check file type and extension
        if (!array_key_exists($file_ext, $allowed_types) || !in_array($file_type, $allowed_types)) {
            $error_message = "Error: Invalid file type. Only JPG, PNG, and PDF are allowed.";
        }

        // Generate a unique file name to prevent overwrites and potential issues
        $unique_file_name = uniqid('event_') . '.' . $file_ext;
        $target_file_path = $upload_dir . $unique_file_name;


        // If no validation errors so far, attempt to move the uploaded file
        if (empty($error_message)) {
            // Ensure the target directory exists
            if (!is_dir($upload_dir)) {
                if (!mkdir($upload_dir, 0777, true)) { // Create directory with recursive option
                     $error_message = "Error: Failed to create upload directory.";
                }
            }

            // Attempt to move the uploaded file
            if (empty($error_message)) { // Check again in case directory creation failed
                 if (move_uploaded_file($file_tmp_name, $target_file_path)) {
                     $file_name = $unique_file_name; // Store the unique name in the database
                 } else {
                     $error_message = "Error: Failed to upload image.";
                     // Log the move error: error_log("Failed to move uploaded file: " . $file_info['error']);
                 }
            }
        }

    } elseif (isset($_FILES['image']) && $_FILES['image']['error'] != UPLOAD_ERR_NO_FILE) {
        // Handle other upload errors (e.g., partial upload, ini size exceeds)
         $error_message = "File upload error: " . $_FILES['image']['error']; // You might want a more user-friendly message
    }
    // If no file was uploaded, $file_name remains empty, which is acceptable if image is not mandatory.
    // If image is mandatory, add an empty check here:
    // elseif (!isset($_FILES['image']) || $_FILES['image']['error'] == UPLOAD_ERR_NO_FILE) {
    //     $error_message = "Event poster image is required.";
    // }


    // --- Database Insertion ---
    // Only proceed with database insertion if there are no validation or upload errors
    if (empty($error_message)) {
        $sql_insert = "INSERT INTO events (title, description, event_date, image, college_name, department, referencelink, event_type, username) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql_insert);

        if ($stmt) {
            // Bind parameters
            $stmt->bind_param("sssssssss", $eventname, $descrp, $event_date, $file_name, $collegename, $department, $referencelink, $event_type, $userid);

            // Execute the statement
            if ($stmt->execute()) {
                 $success_message = "Event '" . htmlspecialchars($eventname) . "' created successfully!";
                 // Clear form fields after successful submission (optional)
                 $_POST = array();
            } else {
                $error_message = "Error inserting event record: " . $stmt->error;
                // Log the DB error: error_log("Event insert failed: " . $stmt->error);
            }
            $stmt->close(); // Close the statement
        } else {
            $error_message = "Database error preparing event insertion statement.";
            // Log the preparation error: error_log("Failed to prepare event insert statement: " . $conn->error);
        }
    }

} // End of POST request handling

// Close the database connection
if (isset($conn) && $conn instanceof mysqli && $conn->thread_id) {
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Event - EventHub</title>
    <link rel="stylesheet" href="createstyle.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <header>
        <?php
        // Include your header file
        if (file_exists('header.php')) { // Using adminheader as per your code
            include 'header.php';
        } else {
            // Fallback or error message if header file is missing
            echo "<p style='color: red; text-align: center;'>Admin header file not found.</p>";
        }
        ?>
    </header>

    <div class="main-content">
        <div class="card" style="max-width: 700px; margin: 40px auto;">
            <div class="card-content">
                <div class="header" style="text-align: center;">
                    <h1 class="title">Create New Event</h1>
                    <p class="subtitle">Fill out the details below to submit an event.</p>
                </div>

                <?php
                // Display messages
                if (!empty($error_message)) {
                    echo '<p style="color: red; text-align: center; margin-bottom: 15px;">' . htmlspecialchars($error_message) . '</p>';
                }
                if (!empty($success_message)) {
                    echo '<p style="color: green; text-align: center; margin-bottom: 15px;">' . htmlspecialchars($success_message) . '</p>';
                }
                ?>

                <form method="POST" enctype="multipart/form-data" class="form create-event-form" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="eventname" class="form-label">Event Title:</label>
                            <input type="text" id="eventname" name="eventname" class="form-input" placeholder="Enter event title" value="<?php echo isset($_POST['eventname']) ? htmlspecialchars($_POST['eventname']) : ''; ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="event_type" class="form-label">Event Type:</label>
                            <input type="text" id="event_type" name="event_type" class="form-input" placeholder="e.g., Workshop, Seminar" value="<?php echo isset($_POST['event_type']) ? htmlspecialchars($_POST['event_type']) : ''; ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="date" class="form-label">Event Date:</label>
                            <input type="date" id="date" name="date" class="form-input" value="<?php echo isset($_POST['date']) ? htmlspecialchars($_POST['date']) : ''; ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="department" class="form-label">Department:</label>
                            <input type="text" id="department" name="department" class="form-input" placeholder="e.g., Computer Science, Arts Club" value="<?php echo isset($_POST['department']) ? htmlspecialchars($_POST['department']) : ''; ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="referencelink" class="form-label">RegistrationLink:</label>
                            <input type="url" id="referencelink" name="referencelink" class="form-input" placeholder="https://example.com/register" value="<?php echo isset($_POST['referencelink']) ? htmlspecialchars($_POST['referencelink']) : ''; ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="image" class="form-label">Event Poster (JPG, PNG, PDF - Max 5MB):</label>
                            <input type="file" id="image" name="image" class="form-input" accept=".jpg, .jpeg, .png, .pdf">
                        </div>

                        <div class="form-group" style="grid-column: 1 / -1;">
                            <label for="descrp" class="form-label">Event Description:</label>
                            <textarea id="descrp" name="descrp" class="form-input" placeholder="Detailed description of the event" required rows="6"><?php echo isset($_POST['descrp']) ? htmlspecialchars($_POST['descrp']) : ''; ?></textarea>
                        </div>

                    </div> <button type="submit" name="createevent" class="btn btn-primary" style="width: 100%;">
                        <i class="fa-solid fa-calendar-plus"></i> Create Event
                    </button>

                    <div class="form-group" style="text-align: center; margin-top: 15px;">
                        <a href="admin.php" class="form-switch-link">Back to Dashboard</a>
                    </div>

                </form>
            </div>
        </div>
    </div> <?php
    // Include your footer file
    if (file_exists('footer.php')) {
        include 'footer.php';
    } else {
        // Fallback or error message if footer file is missing
        echo "<p style='color: red; text-align: center;'>Footer file not found.</p>";
    }
    ?>

    <script src="script.js" defer></script>

</body>
</html>