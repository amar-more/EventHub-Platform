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
$event_id = null;
$event_data = []; // Array to hold existing event data

// --- Get Event ID and Fetch Existing Data ---
// Check if event ID is provided in the URL
if (isset($_GET['id']) && !empty($_GET['id'])) {
    $event_id = (int)$_GET['id']; // Cast to integer for safety

    // Fetch existing event data from the database
    $sql_fetch_event = "SELECT * FROM events WHERE id = ?";
    $stmt_fetch = $conn->prepare($sql_fetch_event);

    if ($stmt_fetch) {
        $stmt_fetch->bind_param("i", $event_id);
        $stmt_fetch->execute();
        $result_fetch = $stmt_fetch->get_result();

        if ($result_fetch && $result_fetch->num_rows > 0) {
            $event_data = $result_fetch->fetch_assoc();

            // --- Authorization Check ---
            // Ensure the logged-in user is authorized to edit this event
            // Assuming 'username' in the events table stores the creator's username
            if ($event_data['username'] !== $_SESSION['userid']) {
                 // Maybe also check if they are an admin role if you have that
                 // For now, only the creator can edit
                 $error_message = "You are not authorized to edit this event.";
                 $event_data = []; // Clear data if not authorized
                 $event_id = null; // Invalidate ID if not authorized
            }

        } else {
            $error_message = "Event not found.";
            $event_id = null; // Invalidate ID if not found
        }
        $stmt_fetch->close();
    } else {
        $error_message = "Database error while fetching event data.";
         // Log this error: error_log("Failed to prepare fetch query: " . $conn->error);
         $event_id = null; // Invalidate ID on DB error
    }

} else {
    // No ID provided
    $error_message = "No event ID specified for editing.";
}

// --- Handle Form Submission (Update) ---
// Only process POST if an event ID was successfully fetched and authorized
if (isset($_POST['editevent']) && $event_id !== null && empty($error_message)) {

    // Fetch college name based on the logged-in user again (in case it changed?)
    // Or you might want to keep the original college name from $event_data
    // Keeping the original college name seems more appropriate for editing.
    // $collegename = $event_data['college_name']; // Use original college name

    // Let's re-fetch current user's college to be consistent with create, but be aware
    // this might change the event's college if the user moved colleges.
     $userid = $_SESSION['userid'];
     $collegename = '';
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
              // This shouldn't happen if user is logged in, but handle defensively
              $error_message = "Error: Could not find college information for your user account.";
              // Log this error: error_log("College not found for user during edit POST: " . $userid);
         }
         $stmt_college->close();
     } else {
         $error_message = "Database error while fetching current college information.";
         // Log this error: error_log("Failed to prepare college query (edit POST): " . $conn->error);
     }


    // --- Input Validation and Sanitization ---
    // Retrieve submitted data, using fetched data as fallback if POST values are missing (shouldn't happen with required fields)
    $eventname = trim($_POST['eventname'] ?? $event_data['title']);
    $descrp = trim($_POST['descrp'] ?? $event_data['description']);
    $date_str = trim($_POST['date'] ?? $event_data['event_date']);
    $department = trim($_POST['department'] ?? $event_data['department']);
    $referencelink = trim($_POST['referencelink'] ?? $event_data['referencelink']);
    $event_type = trim($_POST['event_type'] ?? $event_data['event_type']);

    // Retain existing image name by default
    $file_name = $event_data['image'];
    $existing_image_path = !empty($event_data['image']) ? 'Event_image/' . $event_data['image'] : null; // Path to old file

    // Validate required fields are not empty
    if (empty($eventname) || empty($descrp) || empty($date_str) || empty($department) || empty($referencelink) || empty($event_type) || empty($collegename)) {
        $error_message = "Please fill in all required fields.";
    }

    // Validate date format and convert
    $event_date = null;
    if (!empty($date_str)) {
        // Use DateTime object for safer date validation
        $date_object = DateTime::createFromFormat('Y-m-d', $date_str);
        if ($date_object && $date_object->format('Y-m-d') === $date_str) {
            $event_date = $date_str; // Date is valid and in Y-m-d format
        } else {
             // Try strtotime as a fallback if needed, but Y-m-d is expected from input type="date"
             $event_date_strtotime = date('Y-m-d', strtotime($date_str));
             if ($event_date_strtotime && $event_date_strtotime != '1970-01-01') {
                 $event_date = $event_date_strtotime;
             } else {
                 $error_message = "Invalid date format.";
             }
        }
    }


    // Validate URL format for referencelink
    if (!empty($referencelink) && !filter_var($referencelink, FILTER_VALIDATE_URL)) {
         $error_message = "Invalid reference link format.";
    }

    // Sanitize inputs
    $eventname = htmlspecialchars($eventname, ENT_QUOTES, 'UTF-8');
    $descrp = htmlspecialchars($descrp, ENT_QUOTES, 'UTF-8');
    $department = htmlspecialchars($department, ENT_QUOTES, 'UTF-8');
    $referencelink = htmlspecialchars($referencelink, ENT_QUOTES, 'UTF-8');
    $event_type = htmlspecialchars($event_type, ENT_QUOTES, 'UTF-8');


    // --- Handle File Upload (New Image) ---
    $upload_dir = 'Event_image/'; // Define the target folder
    $allowed_types = ['jpg' => 'image/jpeg', 'png' => 'image/png', 'pdf' => 'application/pdf'];
    $max_file_size = 5 * 1024 * 1024; // 5MB max size

    // Check if a new file was uploaded
    if (isset($_FILES['image']) && $_FILES['image']['error'] == UPLOAD_ERR_OK) {
        $file_info = $_FILES['image'];
        $file_tmp_name = $file_info['tmp_name'];
        $file_size = $file_info['size'];
        $file_type = $file_info['type'];
        $file_ext = strtolower(pathinfo($file_info['name'], PATHINFO_EXTENSION));

        // Check file size
        if ($file_size > $max_file_size) {
            $error_message = "Error: New file size exceeds the maximum allowed (" . ($max_file_size / 1024 / 1024) . "MB).";
        }

        // Check file type and extension
        if (!array_key_exists($file_ext, $allowed_types) || !in_array($file_type, $allowed_types)) {
            $error_message = "Error: Invalid new file type. Only JPG, PNG, and PDF are allowed.";
        }

        // Generate a unique file name
        $unique_file_name = uniqid('event_') . '.' . $file_ext;
        $target_file_path = $upload_dir . $unique_file_name;

        // If no validation errors so far, attempt to move the new uploaded file
        if (empty($error_message)) {
             // Ensure the target directory exists
            if (!is_dir($upload_dir)) {
                if (!mkdir($upload_dir, 0777, true)) {
                    $error_message = "Error: Failed to create upload directory.";
                }
            }

            if (empty($error_message)) { // Check again
                if (move_uploaded_file($file_tmp_name, $target_file_path)) {
                    // New file uploaded successfully
                    $file_name = $unique_file_name; // Update file_name to the new one

                    // Optional: Delete the old file if it exists and is different from the new one
                    if (!empty($event_data['image']) && $event_data['image'] !== $file_name) {
                         $old_file_path = $upload_dir . $event_data['image'];
                         if (file_exists($old_file_path)) {
                             if (!unlink($old_file_path)) {
                                  // Log failure to delete old file, but don't necessarily error out the update
                                  error_log("Failed to delete old event image: " . $old_file_path);
                             }
                         }
                    }

                } else {
                    $error_message = "Error: Failed to upload new image.";
                     // Log the move error: error_log("Failed to move new uploaded file: " . $file_info['error']);
                }
            }
        }

    } elseif (isset($_FILES['image']) && $_FILES['image']['error'] != UPLOAD_ERR_NO_FILE) {
        // Handle other upload errors for the new file
        $error_message = "File upload error: " . $_FILES['image']['error']; // More user-friendly message might be needed
    }
    // If $_FILES['image']['error'] == UPLOAD_ERR_NO_FILE, $file_name remains the existing image name, which is correct.

    // --- Database Update ---
    // Only proceed with database update if there are no validation or upload errors
    // And only if the event_id was successfully fetched and authorized initially
    if (empty($error_message) && $event_id !== null) {
        $sql_update = "UPDATE events SET title=?, description=?, event_date=?, image=?, college_name=?, department=?, referencelink=?, event_type=? WHERE id=? AND username=?"; // Add username check for safety

        $stmt_update = $conn->prepare($sql_update);

        if ($stmt_update) {
            // Bind parameters
            // sssssssssi : 9 strings + 1 integer
            $stmt_update->bind_param("ssssssssis", $eventname, $descrp, $event_date, $file_name, $collegename, $department, $referencelink, $event_type, $event_id, $_SESSION['userid']);

            // Execute the statement
            if ($stmt_update->execute()) {
                 // Check if any rows were affected
                 if ($stmt_update->affected_rows > 0) {
                    $success_message = "Event '" . htmlspecialchars($eventname) . "' updated successfully!";
                    // Optionally, redirect to dashboard or event list
                    // header("Location: admin.php?status=updated"); exit;
                 } else {
                     // This might happen if no changes were made, or if ID/username check failed unexpectedly
                     $error_message = "Event details are already up to date or no changes were made.";
                     // Log this: error_log("Update executed but affected_rows is 0 for event ID: " . $event_id . " User: " . $_SESSION['userid']);
                 }
            } else {
                $error_message = "Error updating event record: " . $stmt_update->error;
                 // Log the DB error: error_log("Event update failed: " . $stmt_update->error);
            }
            $stmt_update->close(); // Close the statement
        } else {
            $error_message = "Database error preparing event update statement.";
             // Log the preparation error: error_log("Failed to prepare event update statement: " . $conn->error);
        }

         // Re-fetch data after a successful update to show the latest in the form (optional, but good)
         // Or clear POST data and reload the original fetched data
         if(!empty($success_message)) {
             // Clear post data to show updated values from DB fetch
             // This requires re-fetching the data from the database
             $sql_re_fetch = "SELECT * FROM events WHERE id = ?";
             $stmt_re_fetch = $conn->prepare($sql_re_fetch);
             if($stmt_re_fetch) {
                 $stmt_re_fetch->bind_param("i", $event_id);
                 $stmt_re_fetch->execute();
                 $result_re_fetch = $stmt_re_fetch->get_result();
                 if($result_re_fetch && $result_re_fetch->num_rows > 0) {
                     $event_data = $result_re_fetch->fetch_assoc();
                 }
                 $stmt_re_fetch->close();
             }
             $_POST = array(); // Clear POST to use $event_data for form values
         } else if (!empty($error_message)) {
              // If update failed, retain submitted POST data in the form fields
             $event_data['title'] = $eventname;
             $event_data['description'] = $descrp;
             $event_data['event_date'] = $date_str;
             $event_data['department'] = $department;
             $event_data['referencelink'] = $referencelink;
             $event_data['event_type'] = $event_type;
             // $event_data['image'] will be the old one if no new file uploaded,
             // or the new one if upload failed after moving, or the uploaded one.
             // Let's rely on $file_name variable for the current image state
             $event_data['image'] = $file_name; // Reflect the image name that was attempted/kept
         }

    } // End of Database Update block

} else if (isset($_POST['editevent']) && $event_id === null) {
    // Form was submitted but event_id was not valid initially
    $error_message = $error_message ?: "Cannot process update: Invalid or missing event ID.";
}


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
    <title>Edit Event - EventHub</title>
    <link rel="stylesheet" href="createstyle.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
     <link rel="stylesheet" href="header.css">
</head>
<body>
    <header>
        <?php
        // Include your header file
        if (file_exists('header.php')) {
            include 'header.php';
        } else {
            // Fallback or error message if header file is missing
            echo "<p style='color: red; text-align: center;'>Header file not found.</p>";
        }
        ?>
    </header>

    <div class="main-content">
        <div class="card" style="max-width: 700px; margin: 40px auto;">
            <div class="card-content">
                <div class="header" style="text-align: center;">
                    <h1 class="title">Edit Event</h1>
                    <p class="subtitle">Modify the details of the event below.</p>
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

                <?php if ($event_id !== null && empty($error_message) || !empty($success_message)): // Only show form if event data is available or update was successful ?>
                    <form method="POST" enctype="multipart/form-data" class="form edit-event-form" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]) . '?id=' . $event_id; ?>">
                        <input type="hidden" name="event_id" value="<?php echo htmlspecialchars($event_id); ?>">

                        <div class="form-grid">
                            <div class="form-group">
                                <label for="eventname" class="form-label">Event Title:</label>
                                <input type="text" id="eventname" name="eventname" class="form-input" placeholder="Enter event title" value="<?php echo htmlspecialchars($event_data['title'] ?? ''); ?>" required>
                            </div>

                            <div class="form-group">
                                <label for="event_type" class="form-label">Event Type:</label>
                                <input type="text" id="event_type" name="event_type" class="form-input" placeholder="e.g., Workshop, Seminar" value="<?php echo htmlspecialchars($event_data['event_type'] ?? ''); ?>" required>
                            </div>

                            <div class="form-group">
                                <label for="date" class="form-label">Event Date:</label>
                                <input type="date" id="date" name="date" class="form-input" value="<?php echo htmlspecialchars($event_data['event_date'] ?? ''); ?>" required>
                            </div>

                            <div class="form-group">
                                <label for="department" class="form-label">Department:</label>
                                <input type="text" id="department" name="department" class="form-input" placeholder="e.g., Computer Science, Arts Club" value="<?php echo htmlspecialchars($event_data['department'] ?? ''); ?>" required>
                            </div>

                            <div class="form-group">
                                <label for="referencelink" class="form-label">RegistrationLink:</label>
                                <input type="url" id="referencelink" name="referencelink" class="form-input" placeholder="https://example.com/register" value="<?php echo htmlspecialchars($event_data['referencelink'] ?? ''); ?>" required>
                            </div>

                            <div class="form-group">
                                <label for="image" class="form-label">Event Poster (JPG, PNG, PDF - Max 5MB):</label>
                                <input type="file" id="image" name="image" class="form-input" accept=".jpg, .jpeg, .png, .pdf">
                                <?php if (!empty($event_data['image'])): ?>
                                     <p style="margin-top: 5px; font-size: 0.9em;">Current file: <a href="Event_image/<?php echo htmlspecialchars($event_data['image']); ?>" target="_blank"><?php echo htmlspecialchars($event_data['image']); ?></a></p>
                                <?php endif; ?>
                            </div>

                            <div class="form-group" style="grid-column: 1 / -1;">
                                <label for="descrp" class="form-label">Event Description:</label>
                                <textarea id="descrp" name="descrp" class="form-input" placeholder="Detailed description of the event" required rows="6"><?php echo htmlspecialchars($event_data['description'] ?? ''); ?></textarea>
                            </div>

                        </div>
                        <button type="submit" name="editevent" class="btn btn-primary" style="width: 100%;">
                            <i class="fa-solid fa-save"></i> Save Changes
                        </button>

                        <div class="form-group" style="text-align: center; margin-top: 15px;">
                            <a href="admin.php" class="form-switch-link">Back to Dashboard</a>
                        </div>

                    </form>
                 <?php elseif ($event_id !== null && !empty($error_message)): // Show error if ID was given but fetch/auth failed ?>
                     <div style="text-align: center;">
                         <p style="color: red; margin-bottom: 15px;"><?php echo htmlspecialchars($error_message); ?></p>
                         <a href="admin.php" class="form-switch-link">Back to Dashboard</a>
                     </div>
                 <?php else: // Show error if no ID was provided initially ?>
                    <div style="text-align: center;">
                        <p style="color: red; margin-bottom: 15px;"><?php echo htmlspecialchars($error_message); ?></p>
                        <a href="admin.php" class="form-switch-link">Back to Dashboard</a>
                    </div>
                <?php endif; ?>

            </div>
        </div>
    </div>

    <?php
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