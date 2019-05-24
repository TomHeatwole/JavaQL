<?hh  // strict

include("Globals.php");
include("Lex.php");

echo stripslashes("\\n");
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
    if (count($lex) > 0) parse_and_execute($lex, $line, $class_map, $conn);
}

function parse_and_execute(vec $lex, string $line, Map $class_map, $conn) {
    $L_PAREN = shape("type" => TokenType::SYMBOL, "value" => "(");
    $R_PAREN = shape("type" => TokenType::SYMBOL, "value" => ")");

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
            if (!($class_name = match_type($lex, ++$i, $line, TokenType::CLASS_ID))) return;
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
            if (!($class_name = match_type($lex, ++$i, $line, TokenType::CLASS_ID))) return;
            if (!match_exact($lex, ++$i, $line, $R_PAREN)) return;
            if (!semi_or_end($lex, ++$i, $line)) return; 
            $result = mysqli_query($conn, "SELECT * FROM " . $class_name);
            print_query_result($result, $class_map, $class_name);
            return;
        case "new":
            if (!($class_name = match_type($lex, $i, $line, TokenType::CLASS_ID))) return;
            if (!match_exact($lex, ++$i, $line, $L_PAREN)) return;
            // TODO: use class_map[class_name] to get a list of results
            return;
        }
    default:
        carrot_and_error("unexpected token: " . $lex[0]["value"], $line, 0);
        return;
    }
}

// return value if parsed correctly or false otherwise
function parse_type(vec $lex, int &$i, string $line, string $e) {
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
        if ($e == "string") return clean_literal($token["value"]);
        return expected_but_found($token, $line, $e);
    case TokenType::BOOLEAN_LITERAL:
        if ($e == "boolean") return $token["value"];
        return expected_but_found($token, $line, $e);
    default: return expected_but_found($token, $line, $e);
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

function print_query_result($result, Map $class_map, string $class_name) {
    $PRIM = new Set(vec["short", "byte", "int", "long", "float", "double", "char", "doule", "String"]);
    // JavaQL primitives, not Java

    $print = vec[];
    $var_names = $class_map[$class_name]->toKeysArray();
    $var_types = $class_map[$class_name]->toValuesArray();
    while ($row = mysqli_fetch_row($result)) {
        $vars = dict[];
        for ($i = 1; $i < count($row); $i++) {
            $display_val = $row[$i];
            if ($var_types[$i - 1] == "boolean") $display_val = (boolean)$display_val;
            else if (!$PRIM->contains($var_types[$i - 1]))
                $display_val = $var_types[$i - 1] . "@" . $row[$i];
            $vars[$var_names[$i - 1]] = $display_val;
        }
        $print[] = $vars;
    }
    echo json_encode($print, JSON_PRETTY_PRINT), "\n";
}

// Returns value of matched type on success or false on failure
function match_type(vec $lex, int $i, string $line, TokenType $e) {
    $TOKEN_NAME_MAP = new Map(dict[
        /*
        TokenType::INT_LITERAL => "integer",
        TokenType::FLOAT_LITERAL => "float",
        TokenType::BOOLEAN_LITERAL => "boolean",
        TokenType::STRING_LITERAL => "string",
        TokenType::CHAR_LITERAL => "char",
         */
        TokenType::CLASS_ID => "class name",
        /*
        TokenType::ID => "identifier",
        TokenType::INT_ID => "integer",
        TokenType::FLOAT_ID => "float",
        TokenType::BOOLEAN_ID => "boolean",
        TokenType::STRING_ID => "string",
        TokenType::CHAR_ID => "char",
        TokenType::SYMBOL => "symbol",
        TokenType::KEYWORD => "keyword",
        TokenType::EOF => "end of file",
         */
    ]);

    if ($lex[$i]["type"] != $e) {
        carrot_and_error("expected " . $TOKEN_NAME_MAP[$e] . " but found " . $lex[$i]["value"], $line, $lex[$i]["char_num"]);
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
