<?hh  // strict

include("Globals.php");
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
    $class_map[$row[0]] = new Map($vars);
}
echo "Classes loaded\n\n";

$class_map = new Map($class_map);

$sym_table = new Map();

// Begin CLI 
while (true) {
    $line = trim(readline($PROJECT_NAME . "> "));
    if ($line == "q" || $line == "quit") break;
    $lex = lex_command($line, $class_map, $sym_table);
    if (count($lex) > 0) parse_and_execute($lex, $line, $class_map);
}

function parse_and_execute(vec$lex, string $line, Map $class_map) {
    switch ($lex[0]["type"]) {
        case TokenType::EOF:
            return;
        case TokenType::KEYWORD:
            switch ($lex[0]["value"]) {
                case "getClasses":
                    if (!match($lex, 1, $line, shape("type" => TokenType::SYMBOL, "value" => "("))) return;
                    if (!match($lex, 2, $line, shape("type" => TokenType::SYMBOL, "value" => ")"))) return;
                    if (!semi_or_end($lex, 3, $line)) return; 
                    echo json_encode($class_map, JSON_PRETTY_PRINT), "\n";
                    return;
                case "getClass":
                    if (!match($lex, 1, $line, shape("type" => TokenType::SYMBOL, "value" => "("))) return;
                    switch($lex[2]["type"]) {
                        case TokenType::CLASS_ID:
                            break;
                        case TokenType::ID:
                            carrot_and_error("Unrecognized class name: " . $lex[2]["value"], $line, $lex[2]["char_num"]);
                            echo "If a .java file exists for this class try running buildAll() or build("  
                                . $lex[2]["value"] . ")\n";
                            return;
                        default:
                            carrot_and_error("Expected class name but found " . $lex[2]["value"], $line, $lex[2]["char_num"]);
                            return;
                    }
                    if (!match($lex, 3, $line, shape("type" => TokenType::SYMBOL, "value" => ")"))) return;
                    if (!semi_or_end($lex, 4, $line)) return; 
                    echo json_encode($class_map[$lex[2]["value"]], JSON_PRETTY_PRINT), "\n";
                    return;
                case "getClassNames":
                    if (!match($lex, 1, $line, shape("type" => TokenType::SYMBOL, "value" => "("))) return;
                    if (!match($lex, 2, $line, shape("type" => TokenType::SYMBOL, "value" => ")"))) return;
                    if (!semi_or_end($lex, 3, $line)) return; 
                    echo json_encode($class_map->toKeysArray(), JSON_PRETTY_PRINT), "\n";
                    return;
            }
    }
}

function match(vec $lex, int $i, string $line, $e): boolean {
    if ($lex[$i]["type"] != $e["type"] || $lex[$i]["value"] != $e["value"]) {
        carrot_and_error("Expected " . $e["value"] . " but found " . $lex[$i]["value"], $line, $lex[$i]["char_num"]);
        return false;
    }
    return true;
}

function semi_or_end(vec $lex, int $i, string $line): boolean {
    if ($lex[$i]["type"] == TokenType::EOF) return true;
    else if ($lex[$i]["type"] == TokenType::SYMBOL && $lex[$i]["value"] == ";" && $lex[++$i]["type"] == TokenType::EOF) return true;
    carrot_and_error("Unexpected token: " . $lex[$i]["value"], $line, $lex[$i]["char_num"]);
    return false;
}
