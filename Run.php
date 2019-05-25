<?hh  // strict

include("Globals.php");
include("Lex.php");

echo stripslashes("\\n");
$config_file = fopen('database.txt', 'r');
if (!$config_file) {
    die($_GLOBALS["PROJECT_NAME"] .
        " Error: You must include a database.txt file with your MySQL database credentials.\n" .
        "Refer to the README: " . $_GLOBALS["PROJECT_URL"] . "\n");
}
if (!(($host = fgets($config_file)) && ($username = fgets($config_file))
    && ($password = fgets($config_file)) && $database = fgets($config_file))) {
    die($_GLOBALS["PROJECT_NAME"] . " Error: database.txt must contain 4 lines of your MySQL database credentials.\n"
        . "Refer to the README: " . $_GLOBALS["PROJECT_URL"] . "\n");
} 
fclose($config_file);

// Create connection
echo "Attempting to connect to database...\n";
$conn = mysqli_connect(trim($host), trim($username), trim($password), trim($database));

// Check connection
if (!$conn) { // In this case the connection attempt gave a warning
    die($_GLOBALS["PROJECT_NAME"] . "Error: Failed to connect due to warning above.\n");
}
if ($conn->connect_error) {
    die($_GLOBALS["PROJECT_NAME"] . "Error: Connection failed: " . $conn->connect_error . "\n");
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
        } else $type = $_GLOBALS["FROM_SQL_TYPE_MAP"][$var['Type']];
        $vars[$name] = $type;
    }
    $class_map[$row[0]] = new Map($vars);
}
echo "Classes loaded\n\n";

$_GLOBALS["CLASS_MAP"] = new Map($class_map);

$sym_table = new Map();

// Begin CLI 
while (true) {
    $line = trim(readline($_GLOBALS["PROJECT_NAME"] . "> "));
    if ($line == "q" || $line == "quit") break;
    $lex = lex_command($_GLOBALS, $line, $sym_table);
    if (count($lex) > 0) parse_and_execute($_GLOBALS, $lex, $line, $conn);
}

function parse_and_execute(dict $_GLOBALS, vec $lex, string $line, $conn) {
    $L_PAREN = shape("type" => TokenType::SYMBOL, "value" => "(");
    $R_PAREN = shape("type" => TokenType::SYMBOL, "value" => ")");
    $COMMA = shape("type" => TokenType::SYMBOL, "value" => ",");
    $class_map = $_GLOBALS["CLASS_MAP"];

    $i = 1;
    switch ($lex[0]["type"]) {
    case TokenType::EOF:
        return;
    case TokenType::KEYWORD:
        switch ($lex[0]["value"]) {
        case "getClasses":
            if (!match_exact($lex, $i, $line, $L_PAREN)) return;
            if (!match_exact($lex, ++$i, $line, $R_PAREN)) return;
            if (!semi_or_end($lex, ++$i, $line)) return; 
            echo json_encode($class_map, JSON_PRETTY_PRINT), "\n";
            return;
        case "getClass":
            if (!match_exact($lex, $i, $line, $L_PAREN)) return;
            if (!($class_name = match_type($_GLOBALS, $lex, ++$i, $line, TokenType::CLASS_ID))) return;
            if (!match_exact($lex, ++$i, $line, $R_PAREN)) return;
            if (!semi_or_end($lex, ++$i, $line)) return; 
            echo json_encode($class_map[$class_name], JSON_PRETTY_PRINT), "\n";
            return;
        case "getClassNames":
            if (!match_exact($lex, $i, $line, $L_PAREN)) return;
            if (!match_exact($lex, ++$i, $line, $R_PAREN)) return;
            if (!semi_or_end($lex, ++$i, $line)) return; 
            echo json_encode($class_map->toKeysArray(), JSON_PRETTY_PRINT), "\n";
            return;
        case "getAllObjects":
            if (!match_exact($lex, $i, $line, $L_PAREN)) return;
            if (!($class_name = match_type($_GLOBALS, $lex, ++$i, $line, TokenType::CLASS_ID))) return;
            if (!match_exact($lex, ++$i, $line, $R_PAREN)) return;
            if (!semi_or_end($lex, ++$i, $line)) return; 
            $result = mysqli_query($conn, "SELECT * FROM " . $class_name);
            print_query_result($_GLOBALS, $result, $class_name);
            return;
        case "new":
            if (!($class_name = match_type($_GLOBALS, $lex, $i, $line, TokenType::CLASS_ID))) return;
            if (!match_exact($lex, ++$i, $line, $L_PAREN)) return;
            $var_types = $class_map[$class_name]->toValuesArray();
            $var_values = vec[];
            $i++;
            for ($j = 0; $j < count($var_types); $j++) {
                if (!($var_values[] = parse_type($lex, &$i, $line, $var_types[$j]))) {
                    echo $class_name, " constructor espects the following parameters: (";
                    $var_names = $class_map[$class_name]->toKeysArray();
                    for ($j = 0; $j < count($var_names) - 1; $j++) echo $var_types[$j], " ", $var_names[$j], ", ";
                    echo $var_types[count($var_names) - 1], " ", $var_names[count($var_names) - 1], ")\n";
                    return;
                }
                if ($j + 1 < count($var_types) && !match_exact($lex, $i++, $line, $COMMA)) return;
            }
            if (!match_exact($lex, $i, $line, $R_PAREN)) return;
            if (!semi_or_end($lex, ++$i, $line)) return;
            // TODO: update MYSQL on valid case here
            return;
        }
    default:
        carrot_and_error("unexpected token: " . $lex[0]["value"], $line, 0);
        return;
    }
}

// return value if parsed correctly or false otherwise
function parse_type(vec $lex, int &$i, string $line, string $e) {
    // TODO: Implement default
    $token = $lex[$i++];
    switch($token["type"]) {
    case TokenType::CLASS_ID:
        // TODO: INT_ID, OBJ_ID.ID.ID...ID
        return false;
    case TokenType::INT_LITERAL:
        if ($ret = parse_type_int($token["value"], $e)) return $ret;
        return expected_but_found($token, $line, $e);
    case TokenType::FLOAT_LITERAL:
        if ($ret = parse_type_float($token["value"], $e)) return $ret;
        return expected_but_found($token, $line, $e);
    case TokenType::CHAR_LITERAL:
        if ($e == "char") return clean_literal($token["value"]);
        return expected_but_found($token, $line, $e);
    case TokenType::STRING_LITERAL:
        if ($e == "String") return clean_literal($token["value"]);
        return expected_but_found($token, $line, $e);
    case TokenType::BOOLEAN_LITERAL:
        if ($e == "boolean") return $token["value"];
        return expected_but_found($token, $line, $e);
    default: return expected_but_found($token, $line, $e);
        // TODO: OBJ_ID
    }
}

function expected_but_found($token, string $line, string $e): boolean {
    carrot_and_error("expected " . $e . " but found " . $token["value"], $line, $token["char_num"]);
    return false;
}

// return value if parsed correctly or false otherwise
function parse_type_int(string $val, string $e) {
    switch($e) {
    case "int": return $val;
    // TODO: these next 3 (implement overflow)
    case "byte":
    case "short":
    case "long":
    default: return false;
    }
}

function parse_type_float(string $val, string $e) {
    if ($e == "double" || $e == "float") return $val;
    // TODO: Figure out how overflow works for double --> float
    return false;
}

function print_query_result(dict $_GLOBALS, $result, string $class_name) {
    $print = vec[];
    $var_names = $_GLOBALS["CLASS_MAP"][$class_name]->toKeysArray();
    $var_types = $_GLOBALS["CLASS_MAP"][$class_name]->toValuesArray();
    while ($row = mysqli_fetch_row($result)) {
        $vars = dict[];
        for ($i = 1; $i < count($row); $i++) {
            $display_val = $row[$i];
            if ($var_types[$i - 1] == "boolean") $display_val = (boolean)$display_val;
            else if (!$_GLOBALS["PRIM"]->contains($var_types[$i - 1]))
                $display_val = $var_types[$i - 1] . "@" . $row[$i];
            $vars[$var_names[$i - 1]] = $display_val;
        }
        $print[] = $vars;
    }
    echo json_encode($print, JSON_PRETTY_PRINT), "\n";
}

// Returns value of matched type on success or false on failure
function match_type(dict $_GLOBALS, vec $lex, int $i, string $line, TokenType $e) {

    if ($lex[$i]["type"] != $e) {
        carrot_and_error("expected " . $_GLOBALS["TOKEN_NAME_MAP"][$e] .
            " but found " . $lex[$i]["value"], $line, $lex[$i]["char_num"]);
        return false;
    }
    return $lex[$i]["value"];
}

function match_exact(vec $lex, int $i, string $line, $e): boolean {
    if ($lex[$i]["type"] != $e["type"] || $lex[$i]["value"] != $e["value"]) {
        carrot_and_error("expected " . $e["value"] . " but found " . $lex[$i]["value"], $line, $lex[$i]["char_num"]);
        return false;
    }
    return true;
}

function semi_or_end(vec $lex, int $i, string $line): boolean {
    if ($lex[$i]["type"] == TokenType::EOF) return true;
    else if ($lex[$i]["type"] == TokenType::SYMBOL && $lex[$i]["value"] == ";" && $lex[++$i]["type"] == TokenType::EOF) return true;
    carrot_and_error("unexpected token: " . $lex[$i]["value"], $line, $lex[$i]["char_num"]);
    return false;
}

function clean_literal(string $lit): string {
    return substr($lit, 1, strlen($lit) - 2);
}

