<?php
// forgot_password.php - OTP Based

// Include your database connection file
include "database.php"; // This file should create $conn

// --- Email Configuration ---
// !!! IMPORTANT: Replace with your actual email sending logic.
// Using PHPMailer or a similar library is highly recommended for production.
// This uses the basic mail() function which may not be reliable.
$from_email = "eventhub2k25@gmail.com"; // Your sending email address
$subject = "Password Reset OTP for EventHub";

// --- OTP Configuration ---
$otp_expiry_minutes = 10; // OTP is valid for 10 minutes
$otp_length = 6; // Length of the OTP

// --- Security Note: Hashing Passwords ---
// As mentioned before, the provided usertable schema stores passwords in plain text.
// THIS IS EXTREMELY INSECURE. You MUST use password hashing.
// Ensure your 'pass' column in usertable is VARCHAR(255) or similar to store hashes.
// Use password_hash() when creating/updating passwords and password_verify() for login.

// --- Function to generate a numeric OTP ---
function generate_otp($length = 6) {
    $otp = "";
    for ($i = 0; $i < $length; $i++) {
        $otp .= mt_rand(0, 9); // Generate a random digit
    }
    return $otp;
}

$error_message = "";
$success_message = "";
$show_otp_form = false; // Flag to control which form to display

// Determine if we should show the OTP form
if (isset($_POST['send_otp_success']) || isset($_GET['email_for_otp'])) {
    $show_otp_form = true;
    // Get email, preferring POST if available, fallback to GET for direct access (less secure without checks)
    $email_for_otp_form = isset($_POST['email']) ? trim($_POST['email']) : (isset($_GET['email_for_otp']) ? trim($_GET['email_for_otp']) : '');

    // Sanitize email just in case it came from GET
    $email_for_otp_form = filter_var($email_for_otp_form, FILTER_SANITIZE_EMAIL);

    // Basic validation if email is missing
    if (empty($email_for_otp_form) || !filter_var($email_for_otp_form, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Invalid or missing email address for OTP verification.";
        $show_otp_form = false; // Don't show the OTP form if email is bad
    }
}


// --- Handle Forgot Password Request (Initial Email Submission) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['request_otp'])) {
    $email = trim($_POST['email']); // Use trim to remove leading/trailing whitespace

    // Basic email validation and sanitization
    $email = filter_var($email, FILTER_SANITIZE_EMAIL);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Invalid email format.";
    } else {
        // Check if email exists in usertable
        $stmt = $conn->prepare("SELECT email FROM usertable WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            // Email found, generate OTP and store it
            $otp = generate_otp($otp_length);
            $expires_at = date('Y-m-d H:i:s', strtotime("+$otp_expiry_minutes minutes"));

            // Delete any existing reset OTPs for this email
            $delete_stmt = $conn->prepare("DELETE FROM password_resets WHERE email = ?");
            $delete_stmt->bind_param("s", $email);
            $delete_stmt->execute();
            $delete_stmt->close(); // Close prepared statement

            // Insert the new OTP
            $insert_stmt = $conn->prepare("INSERT INTO password_resets (email, otp, expires_at) VALUES (?, ?, ?)");
            $insert_stmt->bind_param("sss", $email, $otp, $expires_at);

            if ($insert_stmt->execute()) {
                // Send OTP email
                $message = "Dear User,\n\nYour One-Time Password (OTP) for password reset is: " . $otp . "\n\nThis OTP is valid for the next " . $otp_expiry_minutes . " minutes.\n\nIf you did not request a password reset, please ignore this email.\n\nRegards,\nEventHub Team";
                $headers = "From: " . $from_email . "\r\n";
                $headers .= "Reply-To: " . $from_email . "\r\n";
                $headers .= "MIME-Version: 1.0\r\n";
                $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";


                // !!! Replace with your secure email sending code !!!
                // Using mail() - Less reliable
                if (mail($email, $subject, $message, $headers)) {
                     // Redirect to the same page with a flag and email to show OTP form
                     header("Location: " . $_SERVER['PHP_SELF'] . "?email_for_otp=" . urlencode($email) . "&otp_sent=1");
                     exit();
                     // We set show_otp_form based on the GET parameters upon redirect
                } else {
                    // Log the mail error, provide generic user message
                    error_log("Failed to send password reset OTP email to: " . $email);
                    $error_message = "Failed to send OTP email. Please try again later.";
                     // Stay on the initial form page if mail fails
                }
            } else {
                $error_message = "Failed to process your request. Please try again later.";
                 error_log("Database insert error for password reset OTP: " . $conn->error);
                 // Stay on the initial form page
            }
            $insert_stmt->close(); // Close prepared statement

        } else {
            // Email not found - provide a generic message to prevent enumeration
             $success_message = "If an account with that email exists, an OTP has been sent.";
             // Display the initial form again, but with a success message
        }
        $stmt->close(); // Close prepared statement
    }
}

// --- Handle OTP Verification and Password Reset ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['reset_password'])) {
    $email = trim($_POST['email']);
    $otp_entered = trim($_POST['otp']);
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    // Sanitize email
    $email = filter_var($email, FILTER_SANITIZE_EMAIL);

    if (empty($email) || empty($otp_entered) || empty($new_password) || empty($confirm_password)) {
        $error_message = "All fields are required.";
         $show_otp_form = true; // Stay on the OTP form
         $email_for_otp_form = $email; // Pass email back to the form
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Invalid email format.";
        $show_otp_form = false; // Go back to the initial form
    } elseif ($new_password !== $confirm_password) {
        $error_message = "New passwords do not match.";
        $show_otp_form = true; // Stay on the OTP form
        $email_for_otp_form = $email; // Pass email back to the form
    } elseif (strlen($new_password) < 8) { // Example password policy
         $error_message = "New password must be at least 8 characters long.";
         $show_otp_form = true; // Stay on the OTP form
         $email_for_otp_form = $email; // Pass email back to the form
    }
     else {
        // Validate the OTP against the password_resets table
        $stmt = $conn->prepare("SELECT email, expires_at FROM password_resets WHERE email = ? AND otp = ?");
        $stmt->bind_param("ss", $email, $otp_entered);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $expires_at = $row['expires_at'];
            $current_time = date('Y-m-d H:i:s');

            if ($current_time <= $expires_at) {
                // OTP is valid and not expired

                // Hash the new password (REQUIRED!)
               
                // Update the password in the usertable
                $update_stmt = $conn->prepare("UPDATE usertable SET pass = ? WHERE email = ?");
                 // Make sure 'pass' is the correct column name in your usertable for the HASHED password
                $update_stmt->bind_param("ss",  $new_password, $email);

                if ($update_stmt->execute()) {
                    // Password updated successfully, delete the OTP
                    $delete_otp_stmt = $conn->prepare("DELETE FROM password_resets WHERE email = ? AND otp = ?");
                    $delete_otp_stmt->bind_param("ss", $email, $otp_entered);
                    $delete_otp_stmt->execute();
                    $delete_otp_stmt->close(); // Close prepared statement

                    $success_message = "Your password has been reset successfully. You can now log in with your new password.";
                     // Redirect to login page after successful reset
                     header("Location: login.php?reset_success=1"); // Redirect with a success flag
                     exit();

                } else {
                    $error_message = "Failed to update password. Please try again.";
                     error_log("Database update error for password reset (OTP): " . $conn->error);
                     $show_otp_form = true; // Stay on the OTP form
                     $email_for_otp_form = $email; // Pass email back to the form
                }
                $update_stmt->close(); // Close prepared statement

            } else {
                // OTP expired
                $error_message = "The OTP has expired. Please request a new one.";
                 // Delete the expired OTP
                $delete_expired_stmt = $conn->prepare("DELETE FROM password_resets WHERE email = ? AND otp = ?");
                $delete_expired_stmt->bind_param("ss", $email, $otp_entered);
                $delete_expired_stmt->execute();
                $delete_expired_stmt->close(); // Close prepared statement
                 $show_otp_form = false; // Go back to the initial form to request a new OTP
            }
        } else {
            // Invalid OTP for this email
            $error_message = "Invalid OTP.";
             $show_otp_form = true; // Stay on the OTP form
             $email_for_otp_form = $email; // Pass email back to the form
        }
        $stmt->close(); // Close prepared statement
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - EventHub</title>
    <link rel="stylesheet" href="loginstyle.css"> <?php require 'header.php';?> </head>
<body>
</br>
    <div class="card">
    <div class="card-content">
      <div class="header">
        <h1 class="title">Forgot Your Password?</h1>
         <?php if (!$show_otp_form) { ?>
             <p class="subtitle">Enter your email address to receive an OTP for reset.</p>
         <?php } else { ?>
              <p class="subtitle">Enter the OTP sent to your email and your new password.</p>
         <?php } ?>
      </div>

    <?php
    if (!empty($error_message)) {
        echo '<p style="color: red; text-align: center;">' . htmlspecialchars($error_message) . '</p>';
    }
    if (!empty($success_message)) {
        echo '<p style="color: green; text-align: center;">' . htmlspecialchars($success_message) . '</p>';
    }

    // Display the appropriate form
    if ($show_otp_form) {
        // Display OTP and New Password form
        ?>
     
        <form method="post" action="" class="form login-form">
             <input type="hidden" name="email" value="<?php echo htmlspecialchars($email_for_otp_form); ?>">
             <input type="hidden" name="send_otp_success" value="1"> <div class="form-group">
                <label for="otp" class="form-label">One-Time Password (OTP):</label>
                <input type="text" id="otp" name="otp" class="form-input" required maxlength="<?php echo $otp_length; ?>">
            </div>

            <div class="form-group">
                <label for="new_password" class="form-label">New Password:</label>
                <input type="password" id="new_password" name="new_password" class="form-input" required minlength="8">
            </div>

             <div class="form-group">
                <label for="confirm_password" class="form-label">Confirm New Password:</label>
                <input type="password" id="confirm_password" name="confirm_password" class="form-input" required minlength="8">
            </div>
            <br>
            <button type="submit" name="reset_password" class="btn btn-primary">
                 <i class="fa-solid fa-check"></i> Reset Password
            </button>
            <br>
             <div class="form-group" style="text-align: center;">
                
                 <p>Didn't receive the OTP? <a href="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>?email_for_otp=<?php echo urlencode($email_for_otp_form); ?>">Resend OTP</a></p>
                  <a href="login.php" class="form-switch-link">Back to Login</a>
             </div>
        </form>
        <?php
    } else {
        // Display initial Email Request form
        ?>
        <form method="post" action="" class="form login-form">
            <div class="form-group">
            <label for="email" class="form-label">Email Address:</label>
            <input type="email" id="email" name="email" class="form-input" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required>
            </div>

            <button type="submit" name="request_otp" class="btn btn-primary">
                 <i class="fa-solid fa-envelope"></i> Send OTP
            </button>
            <br>
             <div class="form-group" style="text-align: center;">
                 <a href="login.php" class="form-switch-link">Back to Login</a>
             </div>
        </form>
        <?php
    }
    ?>
    </div>
    </div>

    <?php require 'footer.php';?> </body>
</html>
<?php
// Close the database connection
$conn->close();
?>