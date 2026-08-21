<!DOCTYPE html>
<html>
<head>
    <title>User Registration</title>
    <link rel="stylesheet" href="bootstrap/bootstrap.min.css">
</head>
<body class="p-5">
    <div class="container">
        <h2>Register</h2>
        <form action="register_save.php" method="post">
            <div class="mb-3">
                <label class="form-label">Username:</label>
                <input type="text" name="username" class="form-control" required>
            </div>

            <div class="mb-3">
                <label class="form-label">Password:</label>
                <input type="password" name="password" class="form-control" required>
            </div>

            <input type="submit" value="Register" class="btn btn-primary">
            <a  href="login.html" class = "btn btn-success">Login In</a>
        </form>
    </div>
</body>
</html>

<?php
// 1. Connect to the database
$servername = "localhost";
$username = "root"; // default for XAMPP
$password = "";     // default for XAMPP
$database = "online_shop"; // change this to your DB name

// Create connection
$conn = mysqli_connect("localhost", "root", "", "online_shop");

// Check if connection works
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Check if a delete request is made via URL (GET)
if (isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']); // Sanitize the ID

    // Delete query
    $delete_sql = "DELETE FROM users WHERE id = $delete_id";

    // Run the delete query
    if (mysqli_query($conn, $delete_sql)) {
        // echo "<p style='color: green;'>User deleted successfully.</p>";
    } else {
        echo "<p style='color: red;'>Error deleting user: " . mysqli_error($conn) ."</p>";
  }
}

// 2. Run the query to fetch users
$sql = "SELECT id, username, password FROM users";
$result = mysqli_query($conn, $sql);

// 3. Display the result in a table
echo "<div style='text-align: center;'>";
echo '<table class="table table-bordered table-striped text-center w-75 mx-auto mt-5">';

echo "<tr><th>ID</th><th>Username</th><th>Password</th><th>Action</th></tr>";

if (mysqli_num_rows($result) > 0) {
    while ($row = mysqli_fetch_assoc($result)) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['id']) . "</td>";
        echo "<td>" . htmlspecialchars($row['username']) . "</td>";
        echo "<td>" . htmlspecialchars($row['password']) . "</td>";
        echo "<td>
        <a href='?delete_id=" . $row['id'] . "' class='btn btn-danger btn-sm' onclick='return confirm(\"Are you sure?\")'>Delete</a>
        <a href='edit_user.php?id=" . $row['id'] . "' class='btn btn-warning btn-sm'>Edit</a>
      </td>";
        echo "</tr>";
    }
} else {
    echo "<tr><td colspan='4'>No users found.</td></tr>";
}

echo "</table>";
echo "</div>";

// Close connection (optional)
mysqli_close($conn);
?>
