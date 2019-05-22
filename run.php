<?hh  // strict

include("globals.php");
include("Lex.php");


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
echo "Connected successfully\n";

// Load Classes
echo "Loading classes...\n";
$class_map = dict[];
$result = mysqli_query($conn, "show tables");
while ($row = mysqli_fetch_row($result)) {
    $class = mysqli_query($conn, "describe " . $row[0]);
    $vars = dict[];
    while ($var = mysqli_fetch_assoc($class)) {
        $name = $var['Field'];
        if ($name == "_id") continue;
        if ($name[0] == "_") {
            $table_name_length = $name[1];
            for ($i = 2; is_numeric($name[$i]); $i++) $table_name_length .= $name[$i];
            $type = substr($name, 1 + strlen($table_name_length), $table_name_length);
            $name = substr($name, 1 + strlen($table_name_length) + $table_name_length);
        } else $type = $FROM_SQL_TYPE_MAP[$var['Type']];
        $vars[$name] = $type;
    }
    $class_map[$row[0]] = $vars;
}
echo "Classes loaded\n\n";

// Load Symbol table???


// Begin CLI 
while (true) {
    $input = trim(readline($PROJECT_NAME . "> "));
    if ($input == "q" || $input == "quit") break;
    $lex = lex_command($input);
    foreach ($lex as $l) {
        echo $l["value"],  " ",  $l["type"],  "\n";
    }
    // TODO: Assume error has been logged and do absolutely nothing on empty vec
}

// Parse input
//
// Execute related MySQL
//
// Tell user results

