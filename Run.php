<?hh  // strict
include("Globals.php");
include("Lex.php");

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
            $j_type = mysql_ref_to_java($name);
            $name = $j_type["name"];
            $type = $j_type["type"];
        } else $type = $_GLOBALS["FROM_SQL_TYPE_MAP"][$var["Type"]];
        $vars[$name] = $type;
    }
    $class_map[$row[0]] = new Map($vars);
}
echo "Classes loaded\n\n";

$_GLOBALS["CLASS_MAP"] = new Map($class_map);
$_GLOBALS["SYMBOL_TABLE"] = new Map();

// Begin CLI 
while (true) {
    $line = trim(readline($_GLOBALS["PROJECT_NAME"] . "> "));
    if ($line == "q" || $line == "quit") break;
    $lex = lex_command($_GLOBALS, $line);
    if (count($lex) > 0) parse_and_execute($_GLOBALS, $lex, $line, $conn);
}

function parse_and_execute(dict $_GLOBALS, vec $lex, string $line, $conn) {
    $class_map = $_GLOBALS["CLASS_MAP"];
    $i = 1;
    switch ($lex[0]["type"]) {
    case TokenType::EOL:
        return;
    case TokenType::M_GET_CLASSES:
        if (!must_match($_GLOBALS, $lex, $i, $line, TokenType::L_PAREN)) return;
        if (!r_paren_semi($_GLOBALS, $lex, ++$i, $line)) return;
        return end_parse(json_encode($class_map, JSON_PRETTY_PRINT));
    case TokenType::M_GET_CLASS:
        if (!must_match($_GLOBALS, $lex, $i, $line, TokenType::L_PAREN)) return;
        if (!($class_name = must_match($_GLOBALS, $lex, ++$i, $line, TokenType::CLASS_ID))) return;
        if (!r_paren_semi($_GLOBALS, $lex, ++$i, $line)) return;
        return end_parse(json_encode($class_map[$class_name], JSON_PRETTY_PRINT));
    case TokenType::M_GET_CLASS_NAMES:
        if (!must_match($_GLOBALS, $lex, $i, $line, TokenType::L_PAREN)) return;
        if (!r_paren_semi($_GLOBALS, $lex, ++$i, $line)) return;
        return(json_encode($class_map->toKeysArray(), JSON_PRETTY_PRINT));
    case TokenType::M_GET_ALL_OBJECTS:
        if (!must_match($_GLOBALS, $lex, $i, $line, TokenType::L_PAREN)) return;
        if (!($class_name = must_match($_GLOBALS, $lex, ++$i, $line, TokenType::CLASS_ID))) return;
        if (!r_paren_semi($_GLOBALS, $lex, ++$i, $line)) return;
        $result = mysqli_query($conn, "SELECT * FROM " . $class_name);
        return end_parse(query_result_to_string($_GLOBALS, $result, $class_name));
    case TokenType::NEW_LITERAL:
        if (!($class_name = must_match($_GLOBALS, $lex, $i, $line, TokenType::CLASS_ID))) return;
        if (!must_match($_GLOBALS, $lex, ++$i, $line, TokenType::L_PAREN)) return;
        $var_types = $class_map[$class_name]->toValuesArray();
        $var_values = vec[];
        $i++;
        for ($j = 0; $j < count($var_types); $j++) {
            // === false to check for "0"
            if (($var_values[] = parse_type($_GLOBALS, $conn, $lex, &$i, $line, $var_types[$j])) === false) {
                echo "\n";
                echo $class_name, " constructor expects the following parameters: (";
                $var_names = $class_map[$class_name]->toKeysArray();
                for ($j = 0; $j < count($var_names) - 1; $j++) echo $var_types[$j], " ", $var_names[$j], ", ";
                return end_parse($var_types[count($var_names) - 1] . " " . $var_names[count($var_names) - 1] . ")");
            }
            if ($j + 1 < count($var_types) && !must_match($_GLOBALS, $lex, $i++, $line, TokenType::COMMA)) return;
        }
        if (!r_paren_semi($_GLOBALS, $lex, $i, $line)) return;
        $query = "INSERT INTO " . $class_name . " VALUES (default";
        for ($j = 0; $j < count($var_types); $j++) {
            $query .= ", " . (($var_values[$j] ==- null) ? "null" : $var_values[$j]);
        }
        if (!mysqli_query($conn, $query . ")")) echo "Error: an unknown MySQL error occurred";
        return;
    }
    if ($_GLOBALS["JAVA_TYPES"]->containsKey($lex[0]["type"])) {
        $j_type = $_GLOBALS["JAVA_TYPES"][$lex[0]["type"]];
        $e = ($j_type == "class") ? $lex[0]["value"] : $j_type;
        if ($lex[$i]["type"] == TokenType::CLASS_ID)
            return carrot_error_false($_GLOBALS["PROJECT_NAME"] .
                " variables may not share names with classes", $line, $lex[$i]["char_num"]);
        else if ($_GLOBALS["VAR_IDS"]->contains($lex[$i]["type"]))
            return carrot_error_false("variable " . $lex[$i]["value"] .
                " is already defined", $line, $lex[$i]["char_num"]);
        if (!must_match($_GLOBALS, $lex, $i, $line, TokenType::ID)) return; // TODO: bad error message
        $name = $lex[$i]["value"];
        if (check_end($lex, ++$i)) { // TODO: I don't love this error message
            $_GLOBALS["SYMBOL_TABLE"][$name] = shape("type" => $e, "value" => $_GLOBALS["DEFAULTS"][$j_type]);
            return;
        }
        if ($lex[$i]["type"] != TokenType::ASSIGN)
            return carrot_error_false("expected end of command or = but found "
                . $lex[$i]["value"], $line, $lex[$i]["char_num"]);
        $i++;
        if (($val = parse_type($_GLOBALS, $conn,$lex, &$i, $line, $e)) === false) return;
        if (!must_end($lex, $i, $line)) return;
        $_GLOBALS["SYMBOL_TABLE"][$name] = shape("type" => $e, "value" => $val);
        return;
    }
    if ($_GLOBALS["VAR_IDS"]->contains($lex[0]["type"])) {
        $sym = $_GLOBALS["SYMBOL_TABLE"][$lex[0]["value"]];
        if ($lex[0]["type"] == TokenType::OBJ_ID) {
            if ($lex[$i]["type"] == TokenType::DOT) {
                $i++;
                if (!($d = dereference($_GLOBALS, $conn, $sym["type"], $sym["value"], $lex, &$i, $line))) return;
                // TODO check for assign and end
                if (!must_end($lex, $i, $line)) return;
                return end_parse(get_display_val($_GLOBALS, $d["type"], $d["value"]));
            }
        }
        // TODO: check for ASSIGN here
        if (!must_end($lex, $i, $line)) return;
        return end_parse(get_display_val($_GLOBALS, $sym["type"], $sym["value"]));
    }
    return carrot_error_false("unexpected token: " . $lex[0]["value"], $line, 0);
}

function dereference(dict $_GLOBALS, $conn, string $type, $value, vec $lex, int &$i, string $line) {
    for (;;$i++) {
        if (!$_GLOBALS["ALL_IDS"]->contains($lex[$i]["type"]))
            return carrot_error_false("unexpected token: " . $lex[$i]["value"], $line, $lex[$i]["char_num"]);
        if ($value === null) return carrot_error_false("null pointer exception", $line, $lex[$i - 1]["char_num"]);
        if (!$_GLOBALS["CLASS_MAP"][$type]->contains($lex[$i]["value"]))
            return carrot_and_error($lex[$i]["value"] . " does not exist in class " . $type, $line, $lex[$i]["char_num"]);
        $class_var_type = $_GLOBALS["CLASS_MAP"][$type][$lex[$i]["value"]];
        $is_primitive = $_GLOBALS["PRIM"]->contains($class_var_type);
        $row_name =  $is_primitive ? $lex[$i]["value"] : java_ref_to_mysql($class_var_type, $lex[$i]["value"]); 
        $result = mysqli_query($conn, "SELECT " . $row_name . " FROM " . $type . " WHERE _id=" . $value);
        if (!$result) return parse_error("an unnown MySXQL error occurred");
        $parent = shape("type" => $type, "value" => $value);
        $value = mysqli_fetch_row($result)[0];
        $type = $class_var_type;
        if ($lex[++$i]["type"] != TokenType::DOT || $is_primitive) break;
    }
    return shape("parent" => $parent, "value" => $value, "type" => $type);
}

// return value if parsed correctly or false otherwise
function parse_type(dict $_GLOBALS, $conn, vec $lex, int &$i, string $line, string $e) {
    // TODO: Implement default
    $token = $lex[$i++];
    switch($token["type"]) {
    case TokenType::OBJ_ID:
        $sym = $_GLOBALS["SYMBOL_TABLE"][$token["value"]];
        if ($lex[$i]["type"] == TokenType::DOT) {
            $i++;
            if (!$d = dereference($_GLOBALS, $conn, $sym["type"], $sym["value"], $lex, &$i, $line)) return false;
            // TODO
            return;
        }
        if ($sym["type"] != $e) return expected_but_found($_GLOBALS, $token, $line, $e);
        return $sym["value"];
    case TokenType::INT_LITERAL:
        $int_val = (int)$token["value"];
        if ($e == "float") {
            if ($int_val <= $_GLOBALS["FLOAT_MAX"]) return $token["value"];
            return carrot_error_false("int literal is too large for type float", $line, $token["char_num"]);
        }
        if ($e == "double") {
            if ((double)$token["value"] != INF) return $token["value"];
            return carrot_error_false("int literal is too large for type double", $line, $token["char_num"]);
        }
        if ($_GLOBALS["INT_MAX"]->containsKey($e)) {
            $max = $_GLOBALS["INT_MAX"][$e];
            // TODO: Figure out how to check for longs on a 32 bit machine
            if ("" . $int_val != $token["value"])
                return carrot_error_false("integer value too large", $line, $token["char_num"]);
            if ($int_val > $max || -$int_val > $max + 1)
                return carrot_error_false("integer value " . $int_val .
                " is too large for type " . $e, $line, $token["char_num"]); 
            return $token["value"];
        }
        return expected_but_found($_GLOBALS, $token, $line, $e);
    case TokenType::FLOAT_LITERAL:
        $float_val = (double)$token["value"];
        if ($float_val == INF)
            return carrot_error_false("decimal literal is too large", $line, $token["char_num"]);
        if ($e == "float") {
            if ($float_val > $_GLOBALS["FLOAT_MAX"])
                return carrot_error_false("decimal literal is too large for type float", $line, $token["char_num"]);
            return $token["value"];
        }
        if ($e == "double") return $token["value"];
        return expected_but_found($_GLOBALS, $token, $line, $e);
    case TokenType::CHAR_LITERAL:
        if ($e == "char") return $token["value"];
        return expected_but_found($_GLOBALS, $token, $line, $e);
    case TokenType::STRING_LITERAL:
        if ($e == "String") return $token["value"];
        return expected_but_found($_GLOBALS, $token, $line, $e);
    case TokenType::BOOLEAN_LITERAL:
        if ($e == "boolean") return $token["value"];
        return expected_but_found($_GLOBALS, $token, $line, $e);
    case TokenType::BOOLEAN_ID: return parse_id($_GLOBALS, $token, $line, $e, new Set(vec["boolean"]));
    case TokenType::CHAR_ID: return parse_id($_GLOBALS, $token, $line, $e, new Set(vec["char", "String"]));
    case TokenType::STRING_ID: return parse_id($_GLOBALS, $token, $line, $e, new Set(vec["String"]));
    case TokenType::FLOAT_ID: return parse_id($_GLOBALS, $token, $line, $e, new Set(vec["float", "double"]));
    case TokenType::DOUBLE_ID: return parse_id($_GLOBALS, $token, $line, $e, new Set(vec["double"]));
    case TokenType::BYTE_ID: return parse_id($_GLOBALS, $token, $line, $e, new Set(vec["byte", "short", "int", "long"]));
    case TokenType::SHORT_ID: return parse_id($_GLOBALS, $token, $line, $e, new Set(vec["short", "int", "long"]));
    case TokenType::INT_ID: return parse_id($_GLOBALS, $token, $line, $e, new Set(vec["int", "long"]));
    case TokenType::LONG_ID: return parse_id($_GLOBALS, $token, $line, $e, new Set(vec["long"]));
    default: return expected_but_found($_GLOBALS, $token, $line, $e);
    }
}

function parse_id($_GLOBALS, $token, $line, $e, Set $accept) {
    if ($accept->contains($e)) return $_GLOBALS["SYMBOL_TABLE"][$token["value"]]["value"];
    return expected_but_found($_GLOBALS, $token, $line, $e);
}

function query_result_to_string(dict $_GLOBALS, $result, string $class_name): string {
    $print = vec[];
    $var_names = $_GLOBALS["CLASS_MAP"][$class_name]->toKeysArray();
    $var_types = $_GLOBALS["CLASS_MAP"][$class_name]->toValuesArray();
    while ($row = mysqli_fetch_row($result)) {
        $vars = dict[];
        for ($i = 1; $i < count($row); $i++)
            $vars[$var_names[$i - 1]] = get_display_val($_GLOBALS, $var_types[$i - 1], $row[$i]);
        $print[] = $vars;
    }
    return json_encode($print, JSON_PRETTY_PRINT);
}

function get_display_val(dict $_GLOBALS, string $type, $val) {
    if ($type == "boolean") return $val && $val !== "false" ? "true" : "false";
    if ($type == "double" || $type == "float") {
        $val = str_replace("e", "E", $val);
        $val = str_replace("+", "", $val);
        return strpos($val, ".") ? $val : $val .= ".0";
    }
    if (!$_GLOBALS["PRIM"]->contains($type))
        return $val === null ? "null" : $type . "@" . $val;
    return $val;
}

function r_paren_semi(dict $_GLOBALS, $lex, int $i, string $line): boolean {
    return (must_match($_GLOBALS, $lex, $i, $line, TokenType::R_PAREN) && must_end($lex, ++$i, $line));
}

// Returns value of matched type on success or false on failure
function must_match(dict $_GLOBALS, vec $lex, int $i, string $line, TokenType $e) {
    if ($lex[$i]["type"] != $e)
        return expected_but_found($_GLOBALS, $lex[$i], $line, $_GLOBALS["TOKEN_NAME_MAP"][$e]);
    return $lex[$i]["value"];
}

function expected_but_found(dict $_GLOBALS, $token, string $line, string $e): boolean {
    return carrot_error_false("expected " . $e . " but found " .
        $_GLOBALS["TOKEN_NAME_MAP"][$token["type"]], $line, $token["char_num"]);
}

function must_end(vec $lex, int $i, string $line): boolean {
    if ($lex[$i]["type"] == TokenType::EOL) return true;
    else if ($lex[$i]["type"] == TokenType::SEMI && $lex[++$i]["type"] == TokenType::EOL) return true;
    return carrot_error_false("unexpected token: " . $lex[$i]["value"], $line, $lex[$i]["char_num"]);
}

function check_end(vec $lex, int $i): boolean {
    if ($lex[$i]["type"] == TokenType::EOL) return true;
    else if ($lex[$i]["type"] == TokenType::SEMI && $lex[++$i]["type"] == TokenType::EOL) return true;
    return false;
}

function carrot_error_false(string $message, string $line, int $char_num) {
    carrot_and_error($message, $line, $char_num);
    return false;
}

function mysql_ref_to_java(string $name): shape("type" => string, "name" => string) {
    $table_name_length = $name[1];
    for ($i = 2; is_numeric($name[$i]); $i++) $table_name_length .= $name[$i];
    return shape(
        "type" => substr($name, 1 + strlen($table_name_length), $table_name_length),
        "name" => substr($name, 1 + strlen($table_name_length) + $table_name_length)
    );
}

function java_ref_to_mysql(string $type, string $name): string {
    return "_" . strlen($type) . $type . $name;
}

function parse_error(string $message) {
    return end_parse("Error" . $message);
}

function end_parse(string $print) {
    echo $print, "\n";
    return false;
}

/*
function clean_literal(string $lit): string {
    return substr($lit, 1, strlen($lit) - 2);
}
 */

