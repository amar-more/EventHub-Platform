<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
    <link rel="stylesheet" href="../cardstyle.css">
</head>
<body>
<?php include 'adminheader.php'; ?>
<div class="page-title">
        <h2>Events</h2>
    </div>
<div class="event-container">
    <?php
    include "database.php";
    session_start();
    $userid = $_SESSION['userid'];
    $sql = "";
    $params = [];

    if (isset($_GET['type'])) {
        $sql = "SELECT * FROM events WHERE event_type = ? and username='$userid' ORDER BY event_date";
        $params = [$_GET['type']];
    } elseif (isset($_GET['dept'])) {
        $sql = "SELECT * FROM events WHERE department = ? and username='$userid' ORDER BY event_date";
        $params = [$_GET['dept']];
    } elseif (isset($_GET['search'])) {
        $search = $_GET['search'];
        $sql = "SELECT * FROM events WHERE (title = ? OR department = ? OR event_date = ? ) and username='$userid' ORDER BY event_date";
        $params = [$search, $search, $search];
    }

    if ($sql) {
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param(str_repeat("s", count($params)), ...$params);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    echo "<div class='card'>";
                    echo "<div class='image-container'>";
                    echo "<span class='badge'>" . htmlspecialchars($row['event_type']) . "</span>";
                    echo "<img src='Event_image/" . htmlspecialchars($row['image']) . "' alt='Event Image'>";
                    echo "</div>";
                    echo "<div class='card-content'>";
                    echo "<h3 class='title'>Eventhub</h3>";
                    echo "<p class='description'>Description:<br>" . htmlspecialchars($row['description']) . "</p>";
                    echo "<p class='college-name'><h4>College Name:</h4> " . htmlspecialchars($row['college_name']) . "</p>";
                    echo "<p class='college-name'>Department: " . htmlspecialchars($row['department']) . "</p>";
                    echo "<span class='college-name'>Date: " . htmlspecialchars($row['event_date']) . "</span>";
                    echo "<br><a href='" . htmlspecialchars($row['referencelink']) . "' class='register-btn'>Register</a>";
                    echo "</div>";
                    echo "</div>";
                }
            } else {
                echo "<p>No events available.</p>";
            }
            $stmt->close();
        } else {

        }
    }

    $conn->close();
    ?>
</div>

<?php include 'footer.php'; ?>
</body>
</html>