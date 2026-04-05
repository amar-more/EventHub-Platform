<?php
session_start();

// Check if user is logged in, redirect to login if not
if (!isset($_SESSION['userid'])) {
    // Adjust your login page path as needed
    header("Location: login.php");
    exit();
}

// Include database connection
include 'database.php'; // Ensure this file establishes $conn

// --- PHP Logic for Theme Preference (Adjusted - Cannot fetch from usertable) ---
// Since usertable does not have a 'theme' column, we cannot fetch a user-specific theme from it.
// The theme (adding 'dark' class to body) must be handled by other means
// (e.g., a site-wide default, a different session variable, or client-side storage).
// We will assume the 'dark' class is added to the body tag by PHP based on an external mechanism.
// We keep the variable here just to dynamically set the body class if needed,
// but its value won't come from *this* table.
// Example: Assume a session variable or default.
$user_theme_preference = $_SESSION['theme_preference'] ?? 'light'; // Example: Read from session

// --- Fetch User Profile Data from usertable ---
$user_data = null;
$fetch_error = '';

// Check if database connection is valid before attempting query
if (isset($conn) && $conn instanceof mysqli) {
    $username = $_SESSION['userid']; // Assuming session stores the username

    // Fetch user data from usertable based on username
    // We fetch email, username, and college as per your schema
    $sql_fetch_profile = "SELECT email, username, college FROM usertable WHERE username = ?";
    $stmt_fetch = $conn->prepare($sql_fetch_profile);

    if ($stmt_fetch) {
        $stmt_fetch->bind_param("s", $username); // Assuming username is string
        $stmt_fetch->execute();
        $result_fetch = $stmt_fetch->get_result();

        if ($result_fetch && $result_fetch->num_rows > 0) {
            $user_data = $result_fetch->fetch_assoc();
        } else {
            $fetch_error = "Error: User data not found.";
            error_log("User data not found for username: " . $username);
        }
        $stmt_fetch->close();
    } else {
        $fetch_error = "Error: Failed to prepare fetch query.";
        error_log("Failed to prepare fetch profile query: " . $conn->error);
    }
} else {
    $fetch_error = "Error: Database connection not available.";
    error_log("Database connection not available in edit_profile.php.");
}


// --- Handle Form Submission ---
$update_message = '';
$error_message = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Check if user data was successfully fetched initially
    if ($user_data) {
        // Sanitize input data
        $new_college = htmlspecialchars($_POST["college"]);
        $new_password = $_POST["new_password"];
        $confirm_password = $_POST["confirm_password"];

        // Basic validation
        if (empty($new_college)) {
            $error_message = "College field cannot be empty.";
        } else if (!empty($new_password) && $new_password !== $confirm_password) {
            $error_message = "New password and confirm password do not match.";
        } else {
            // Proceed with update

            // Build the update query dynamically based on provided fields
            $update_fields = [];
            $update_params = [];
            $update_param_types = '';

            // Always update college
            $update_fields[] = "college = ?";
            $update_params[] = $new_college;
            $update_param_types .= "s";

            // Update password only if a new one was provided
            if (!empty($new_password)) {
                // --- Securely Hash the New Password ---
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT); // Use a strong hashing algorithm
                if ($hashed_password === false) {
                    $error_message = "Error hashing password.";
                    error_log("Password hashing failed in edit_profile.php.");
                } else {
                    $update_fields[] = "pass = ?";
                    $update_params[] = $hashed_password;
                    $update_param_types .= "s";
                }
            }

            // Only proceed if there are fields to update and no hashing error
            if (!empty($update_fields) && empty($error_message)) {
                // Identify the user to update - using username from session
                $username = $_SESSION['userid']; // Use username as identifier

                // Construct the final SQL update statement
                $sql_update_profile = "UPDATE usertable SET " . implode(", ", $update_fields) . " WHERE username = ?";
                $update_param_types .= "s"; // Add type for the username parameter
                $update_params[] = $username; // Add username to the parameters

                // Perform the update query
                if (isset($conn) && $conn instanceof mysqli) {
                    $stmt_update = $conn->prepare($sql_update_profile);

                    if ($stmt_update) {
                         // Use argument unpacking for binding parameters (requires PHP 5.6+)
                         $stmt_update->bind_param($update_param_types, ...$update_params);

                        if ($stmt_update->execute()) {
                            $update_message = "<p class='success-message'>Profile updated successfully!</p>";
                            // Optionally, refetch user data to display updated info immediately
                            // For college, you could just update $user_data['college'] = $new_college;
                            // For password, you wouldn't typically display it anyway.
                             $user_data['college'] = $new_college; // Update display college
                        } else {
                            $error_message = "<p class='error-message'>Error updating profile: " . $stmt_update->error . "</p>";
                            error_log("Profile update failed for username: " . $username . " Error: " . $stmt_update->error);
                        }
                        $stmt_update->close();
                    } else {
                        $error_message = "<p class='error-message'>Failed to prepare update query: " . $conn->error . "</p>";
                        error_log("Failed to prepare update query: " . $conn->error);
                    }
                } else {
                     $error_message = "<p class='error-message'>Database connection not available for update.</p>";
                }
            }
        }
    } else {
        $error_message = "Cannot update profile: User data not available.";
    }
}


// Close database connection here if header.php/footer.php don't need it.
// If they do, keep it open until after includes.
if (isset($conn) && $conn instanceof mysqli && $conn->thread_id) {
     $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profile - EventHub</title>
    <?php /* Link the combined style.css file */ ?>
    <link rel="stylesheet" href="profile.css">
    <?php /* Link Font Awesome */ ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <?php /* If header.css contains styles not merged into style.css, link it */ ?>
    <?php /* <link rel="stylesheet" href="header.css"> */ ?>
</head>
<?php /* Apply the theme class based on PHP variable (value comes from session or default) */ ?>
<body >
    
        <?php
            // Include header.php - Ensure this file has the HTML for the header
            // using the appropriate classes from style.css (e.g., .sticky-header, .logo-nav, etc.)
            include 'header.php';
        ?>
<div class="container">
        <main class="main-content">
            <section class="edit-profile-section">
                <h2 class="section-title">Edit Your Profile</h2></br>
                <?php echo $update_message; ?>
                <?php echo $error_message; ?>

                <?php if ($user_data): ?>
                    <form action="edit_profile.php" method="post" class="profile-form">
                        <div class="form-group">
                            <label for="email">Email Address</label>
                            <?php /* Email is PRIMARY KEY, usually not editable via simple form */ ?>
                            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user_data['email'] ?? ''); ?>" disabled>
                             <small class="form-text">Email cannot be changed.</small>
                        </div>

                         <div class="form-group">
                             <label for="username">Username</label>
                             <?php /* Username is UNIQUE, usually not editable via simple form */ ?>
                             <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($user_data['username'] ?? ''); ?>" disabled>
                             <small class="form-text">Username cannot be changed.</small>
                         </div>

                        <div class="form-group">
                            <label for="college">College</label>
                            <input type="text" id="college" name="college" value="<?php echo htmlspecialchars($user_data['college'] ?? ''); ?>" required>
                        </div>

                        <?php /* Password fields - only include if you allow password change */ ?>
                        <div class="form-group">
                            <label for="new_password">New Password</label>
                            <input type="password" id="new_password" name="new_password">
                            <small class="form-text">Leave blank to keep current password.</small>
                        </div>

                         <div class="form-group">
                            <label for="confirm_password">Confirm New Password</label>
                            <input type="password" id="confirm_password" name="confirm_password">
                         </div>


                        <button type="submit" class="main-btn primary-btn">Update Profile</button> <?php /* Using general button classes */ ?>
                    </form>
                <?php else: ?>
                    <p><?php echo $fetch_error ?: 'Could not retrieve your profile information.'; ?></p>
                <?php endif; ?>
            </section>
        </main>

       
    </div>
    <?php
            // Include footer.php - Ensure this file has the HTML for the footer
            // using the appropriate classes from style.css (e.g., .site-footer, .footer-content, etc.)
            include 'footer.php';
         ?>

    <script>
        // Any necessary page-specific JavaScript (e.g., password validation complexity check before submit)
        // The filter panel JS from search.php is NOT needed here.
    </script>
</body>
</html>