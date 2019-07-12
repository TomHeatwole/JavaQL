<?hh 

echo "Testing database...\n";

$config_file = fopen('database.txt', 'r');
if (!$config_file) fail("Failed to connect\n"); 
if (!(($host = fgets($config_file)) && ($username = fgets($config_file))
    && ($password = fgets($config_file)) && $database = fgets($config_file))) {
    fail("Error: database.txt must contain 4 lines of your MySQL database credentials.\n");
} 
fclose($config_file);
$conn = mysqli_connect(trim($host), trim($username), trim($password), trim($database));
if (!$conn) fail("Failed to connect\n");
if ($conn->connect_error)
    fail("Error: connection failed - " . $conn->connect_error . "\n");


// Test database

// Ensure empty _list table
$result = mysqli_query($conn, "SELECT * FROM _list");
if (mysqli_num_rows($result) !== 0) {
    fail("Failure: _list table is not empty\n");
}

// Success
echo "Passsed database test.\n";



function fail(string $message) {
   echo $message; 
   die(1);
}
