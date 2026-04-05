<?php
// register.php

include "database.php"; // Include database connection details

// Initialize variables to hold messages
$error_message = "";
$success_message = "";

if(isset($_POST['signup'])){
    // Get and trim input data
    $email = trim($_POST['email']);
    $pass = trim($_POST['password']);
    $username = trim($_POST['username']);
    $collegename = trim($_POST['collegename']);

    // --- Basic Validation ---
    if (empty($email) || empty($pass) || empty($username) || empty($collegename)) {
        $error_message = "Please fill in all required fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
         $error_message = "Invalid email format.";
    } elseif (strlen($pass) < 8) { // Example: Minimum password length
         $error_message = "Password must be at least 8 characters long.";
    }
    // Add more validation rules as needed (e.g., username format, college name length)


    // --- Process Registration if no validation errors ---
    if (empty($error_message)) {
        // --- Security Improvement: Hash the password ---
        // Use a strong hashing algorithm (PASSWORD_DEFAULT is recommended and uses bcrypt currently)
        $hashed_password = password_hash($pass, PASSWORD_DEFAULT);

        // --- Security Improvement: Use Prepared Statements for Insertion ---
        $sql = "INSERT INTO usertable (email, pass, college, username) VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);

        if ($stmt) {
            // Bind parameters (s = string, s, s, s)
            $stmt->bind_param("ssss", $email, $hashed_password, $collegename, $username);

            // Execute the statement
            if ($stmt->execute()) {
                // Registration successful
                $success_message = "Registration successful! You can now log in.";
                $stmt->close(); // Close statement
                $conn->close(); // Close connection

                // Redirect to login page after a short delay or directly
                // Using header redirect is generally preferred after successful actions
                header('Location: login.php?registration_success=1'); // Add a flag for login page
                exit; // Stop script execution after redirection

            } else {
                // Handle database errors (e.g., duplicate entry for unique email/username)
                if ($conn->errno == 1062) { // MySQL error code for duplicate entry
                    // Check if the duplicate is for email or username (requires knowing column indexes or checking error message)
                    // A simpler approach is a generic message or checking before insert
                    $error_message = "Username or email already exists.";
                } else {
                    $error_message = "Error during registration. Please try again.";
                    // Log the specific database error for debugging
                    // error_log("Registration DB error: " . $stmt->error);
                }
                $stmt->close(); // Close statement
            }
        } else {
            // Error preparing the SQL statement
            $error_message = "Database error preparing registration statement.";

        }
    }
}
if (isset($conn) && $conn instanceof mysqli && $conn->thread_id) {
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up - EventHub</title>
    <link rel="stylesheet" href="register.css"> <link rel="stylesheet" href="indexstyle.css"> <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <?php require 'header.php'; ?>
    </br>
    <div class="card" style="max-width: 400px; margin: 50px auto;"> <div class="card-content">
      <div class="header" style="text-align: center;">
        <h1 class="title">Create Account</h1>
        <p class="subtitle">Join the campus events community</p>
      </div>

        <?php
        // --- Display Error or Success Messages Here ---
        if (!empty($error_message)) {
            echo '<p style="color: red; text-align: center; margin-bottom: 15px;">' . htmlspecialchars($error_message) . '</p>';
        }
        if (!empty($success_message)) {
            echo '<p style="color: green; text-align: center; margin-bottom: 15px;">' . htmlspecialchars($success_message) . '</p>';
        }
        ?>

      <form class="form signup-form" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
        <div class="form-group">
          <label for="username" class="form-label">Username</label>
          <input type="text" id="username" name="username" required class="form-input" placeholder="Choose a username" value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
          </div>
          <div class="form-group">
          <label for="college-name" class="form-label">College Name</label>
          <input type="text" id="college-name" name="collegename" required class="form-input" placeholder="Enter your college name" value="<?php echo isset($_POST['collegename']) ? htmlspecialchars($_POST['collegename']) : ''; ?>">
        </div>
        <div class="form-group">
          <label for="email" class="form-label">Email Address</label>
          <input type="email" id="email" name="email" required class="form-input" placeholder="Enter your email address" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
        </div>
        <div class="form-group">
          <label for="password" class="form-label">Password</label>
          <div class="password-container">
            <input type="password" id="password" name="password" required class="form-input" placeholder="Create a password" minlength="8"> </div>
        </div>

        <button type="submit" name="signup" class="btn btn-primary">
          <i class="fa-solid fa-user-plus"></i>Sign Up
        </button>
      </form>

      <div class="login-link">
        <div class="form-switch">
        <span class="form-switch-text">Already have an account?</span>
        <a href="login.php" class="form-switch-link">Login</a>
      </div>
      </div>
    </div>
  </div>
    <?php require 'footer.php'; ?>

     

</body>
</html>