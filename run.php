<?hh 

include("globals.php");


$config_file = fopen('database.txt', 'r');
if (!$config_file) {
    die($PROJECT_NAME . " Error: You must include a database.txt file with your MySQL database credentials.\n"
        . "Refer to the README: " . $PROJECT_URL . "\n");
}
if (!(($host = fgets($config_file)) && ($username = fgets($config_file))
    && ($password = fgets($config_file)) && $database = fgets($config_file))) {
    die($PROJECT_NAME . " Error: database.txt must contain 4 lines of your MySQL database credentials.\n"
        . "Refer to the README: " . $PROJECT_URL . "\n");
} 
fclose($config_file);

// Create connection
$conn = mysqli_connect(trim($host), trim($username), trim($password), trim($database));

// Check connection
if (!$conn) { // In this case the connection attempt gave a warning
    die($PROJECT_NAME . "Error: Failed to connect due to warning above.\n");
}
if ($conn->connect_error) {
    die($PROJECT_NAME . "Error: Connection failed: " . $conn->connect_error . "\n");
}
echo "Connected successfully\n\n\n";

while (true) {
    $input = trim(readline($PROJECT_NAME . "> "));
    if ($input == "q" || $input == "quit") break;
    $result = mysqli_query($conn, $input);
    if (!$result) echo "FAIL\n";
    else echo $result;
}

// Parse input
//
// Execute related MySQL
//
// Tell user results

