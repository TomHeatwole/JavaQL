<?hh  // strict

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
echo "Attempting to connect to database...\n";
$conn = mysqli_connect(trim($host), trim($username), trim($password), trim($database));

// Check connection
if (!$conn) { // In this case the connection attempt gave a warning
    die($PROJECT_NAME . "Error: Failed to connect due to warning above.\n");
}
if ($conn->connect_error) {
    die($PROJECT_NAME . "Error: Connection failed: " . $conn->connect_error . "\n");
}
echo "Connected successfully\n\n";

// Load Classes
echo "Loading Classes...\n\n";
$class_map = dict[];
$result = mysqli_query($conn, "show tables");
while ($row = mysqli_fetch_row($result)) {
    $class = mysqli_query($conn, "describe " . $row[0]);
    $vars = vec[];
    while ($var = mysqli_fetch_assoc($class)) {
        if ($var['Field'] == "_id") continue;
        if ($var['Field'][0] == "_") {
            // TODO: prase references
        }
        $vars[] = shape('name' => $var['Field'], 'type' => $var['Type']);
    }
    $class_map[$row[0]] = $vars;
}
var_dump($class_map);

// Load Symbol table???


// Begin CLI 
while (true) {
    $input = trim(readline($PROJECT_NAME . "> "));
    if ($input == "q" || $input == "quit") break;
    $result = mysqli_query($conn, $input);
    if (!$result) echo "FAIL\n";
    /*
    echo get_class($result);
    echo get_class($result->fetch_row());
    echo get_class($result->fetch_assoc());
     */
    echo mysqli_fetch_row($result)[0];
}

// Parse input
//
// Execute related MySQL
//
// Tell user results

