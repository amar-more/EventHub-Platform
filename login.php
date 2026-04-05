<?php
// login.php

// Start the session at the very beginning
// session_start(); // Moved this down below include for better practice if include does session_start

include "database.php"; // Include database connection details

// Initialize a variable to hold error messages
$error_message = "";
$success_message = ""; // Also good to have for potential success messages

// Check if session is already started before starting it
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}


if(isset($_POST['Submit'])){
    $email = trim($_POST['email']); // Trim whitespace
    $pass = trim($_POST['password']); // Trim whitespace

    // --- Basic Validation ---
    if (empty($email) || empty($pass)) {
        $error_message = "Please enter both email/username and password.";
    } else {
        // --- Security Improvement: Use Prepared Statements and Password Hashing ---
        // **IMPORTANT:** This assumes you have updated your usertable and registration/password reset
        // to store and handle HASHED passwords using password_hash() and password_verify().
        // The original query `...and pass='$pass'` is INSECURE if using hashed passwords.

        $stmt = $conn->prepare("SELECT username, pass FROM usertable WHERE email = ? OR username = ?");

        if ($stmt) {
            $stmt->bind_param("ss", $email, $email); // Bind email/username to both placeholders
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0){
                $row = $result->fetch_assoc();
                $stored_username = $row['username'];
                $stored_hashed_password = $row['pass']; // Get the stored HASHED password

                // Verify the entered plain text password against the stored hash
                if (password_verify($pass, $stored_hashed_password)) {
                    // Password is correct!
                    $_SESSION['userid'] = $stored_username; // Store username in session

                    $stmt->close(); // Close statement
                    $conn->close(); // Close connection
                    header('Location: admin.php'); // Redirect to admin page
                    exit; // Stop script execution after redirection
                } else {
                    // Password is incorrect
                    $error_message = "Invalid password."; // Set error message variable
                    $stmt->close(); // Close statement
                }
            }
            else{
                // Email or username not found
                $error_message = "Invalid username or email."; // Set error message variable
                $stmt->close(); // Close statement
            }
        } else {
             // Database error preparing statement
             $error_message = "Database error during login process.";
             // Log the error: error_log("Login prepare statement failed: " . $conn->error);
        }
    }
}

// Close the database connection if it's open and hasn't been closed yet
if (isset($conn) && $conn instanceof mysqli && $conn->thread_id) {
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - EventHub</title>
    <link rel="stylesheet" href="loginstyle.css">
     <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <?php require 'header.php';?>

    <div class="card" style="max-width: 400px; margin: 50px auto;">
         <div class="card-content">
            <div class="header" style="text-align: center;">
             <h1 class="title">Welcome Back</h1>
            <p class="subtitle">Sign in to continue to EventHub</p>
            </div>

        <?php
        // --- Display Error or Success Messages Here ---
        if (!empty($error_message)) {
            echo '<p style="color: red; text-align: center; margin-bottom: 15px;">' . htmlspecialchars($error_message) . '</p>';
        }
        if (!empty($success_message)) {
             // You could set a success message after password reset redirect if needed
             echo '<p style="color: green; text-align: center; margin-bottom: 15px;">' . htmlspecialchars($success_message) . '</p>';
        }

        // Check for a success flag from password reset page
        if (isset($_GET['reset_success']) && $_GET['reset_success'] == 1) {
             echo '<p style="color: green; text-align: center; margin-bottom: 15px;">Your password has been reset successfully. Please log in.</p>';
        }
        ?>

            <form class="form login-form" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                <div class="form-group">
                <label for="email" class="form-label">Username / Email</label>
                    <input type="text" id="email" name="email" class="form-input" placeholder="Enter your username or email" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required>
                </div>
                <div class="form-group">
                <div class="password-container">
                <label for="password" class="form-label">Password</label>
                    <input type="password" id="password" name="password" class="form-input" placeholder="Enter your password" required>
                </div>
                </div>
                <button type="Submit" name="Submit" class="btn btn-primary">
            <i class="fa-solid fa-arrow-right"></i>Sign In
        </button>
                <div class="form-group" style="text-align: center;">
                <a href="forgot_password.php" class="forgot-password">Forgot password?</a>
                </div>
                <div class="form-switch">
        <span class="form-switch-text">Don't have an account?</span>
        <a href="register.php" class="form-switch-link">Sign Up</a>
      </div>
            </form>
        </div>
    </div>


    <?php require 'footer.php';?>

    <script src="script.js" defer></script>

</body>
</html>