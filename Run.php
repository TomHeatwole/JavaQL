<?hh 

error_reporting(E_ERROR | E_PARSE);

include("Globals.php");

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
$_GLOBALS["CONN"] = mysqli_connect(trim($host), trim($username), trim($password), trim($database));

// Check connection
if (!$_GLOBALS["CONN"]) { // In this case the connection attempt gave a warning
    die($_GLOBALS["PROJECT_NAME"] . "Error: Failed to connect due to warning above.\n");
}
if ($_GLOBALS["CONN"]->connect_error) {
    die($_GLOBALS["PROJECT_NAME"] . "Error: Connection failed: " . $_GLOBALS["CONN"]->connect_error . "\n");
}
echo "Connected successfully\n";

// Load Classes
echo "Loading classes...\n";
$class_map = dict[];
$result = mysqli_query($_GLOBALS["CONN"], "show tables");
while ($row = mysqli_fetch_row($result)) {
    $class = mysqli_query($_GLOBALS["CONN"], "describe " . $row[0]);
    $vars = dict[];
    while ($var = mysqli_fetch_assoc($class)) {
        $name = $var['Field'];
        if ($name === "_id") continue;
        if ($name[0] === "_") {
            $j_type = mysql_ref_to_java($name);
            $name = $j_type["name"];
            $type = $j_type["type"];
        } else $type = $_GLOBALS["FROM_SQL_TYPE_MAP"][$var["Type"]];
        $vars[$name] = $type;
    }
    $class_map[$row[0]] = new Map($vars);
}
echo "Classes loaded\n\n";

$_GLOBALS["CONN"] = $_GLOBALS["CONN"];
$_GLOBALS["CLASS_MAP"] = new Map($class_map);
$_GLOBALS["SYMBOL_TABLE"] = new Map();

// Begin CLI 
while (true) {
    $line = trim(readline($_GLOBALS["PROJECT_NAME"] . "> "));
    if ($line === "q" || $line === "quit") break;
    if (!$lex = lex_line($_GLOBALS, vec[], $line, 0, true, false)) continue;
    if (count($lex["tokens"]) > 0) parse_and_execute($_GLOBALS, $lex["tokens"], $line);
}

function parse_and_execute(dict $_GLOBALS, vec $lex, string $line) {
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
        return end_parse(json_encode($class_map->toKeysArray(), JSON_PRETTY_PRINT));
    case TokenType::M_GET_ALL_OBJECTS:
        if (!must_match($_GLOBALS, $lex, $i, $line, TokenType::L_PAREN)) return;
        if (!($class_name = must_match($_GLOBALS, $lex, ++$i, $line, TokenType::CLASS_ID))) return;
        if (!r_paren_semi($_GLOBALS, $lex, ++$i, $line)) return;
        $result = mysqli_query($_GLOBALS["CONN"], "SELECT * FROM " . $class_name);
        return end_parse(query_result_to_string($_GLOBALS, $result, $class_name));
    case TokenType::M_BUILD:
        if (!must_match($_GLOBALS, $lex, $i++, $line, TokenType::L_PAREN)) return;
        if ($lex[$i]["type"] !== TokenType::ID && $lex[$i][$type] !== TokenType::CLASS_ID)
            return expected_but_found($_GLOBALS, $lex[$i], $line, "class name");
        $class_name = $lex[$i]["value"];
        if (!r_paren_semi($_GLOBALS, $lex, ++$i, $line)) return;
        $rebuild = $lex[$i]["type"] === TokenType::CLASS_ID;
        if (!$lex = lex_file($_GLOBALS, $class_name)) return;
        $lex = $lex["tokens"];
        // TODO
        return;
    case TokenType::M_BUILD_ALL:
        if (!must_match($_GLOBALS, $lex, $i, $line, TokenType::L_PAREN)) return;
        if (!r_paren_semi($_GLOBALS, $lex, ++$i, $line)) return;
        // code for build all
        return;
    case TokenType::NEW_LITERAL:
        return new_object($_GLOBALS, $lex, $i, $line, "");
    }
    if ($_GLOBALS["JAVA_TYPES"]->containsKey($lex[0]["type"])) {
        $j_type = $_GLOBALS["JAVA_TYPES"][$lex[0]["type"]];
        $e = ($j_type === "class") ? $lex[0]["value"] : $j_type;
        if ($lex[$i]["type"] === TokenType::CLASS_ID)
            return carrot_and_error($_GLOBALS["PROJECT_NAME"] .
                " variables may not share names with classes", $line, $lex[$i]["char_num"]);
        else if ($_GLOBALS["VAR_IDS"]->contains($lex[$i]["type"]))
            return carrot_and_error("variable " . $lex[$i]["value"] .
                " is already defined", $line, $lex[$i]["char_num"]);
        if (!must_match_unexpected($lex, $i, $line, TokenType::ID)) return;
        $name = $lex[$i]["value"];
        if ($end = check_end($lex, ++$i, $line)) {
            if ($end === -1) return;
            $_GLOBALS["SYMBOL_TABLE"][$name] = shape("type" => $e, "value" => $_GLOBALS["DEFAULTS"][$j_type]);
            return;
        }
        if (!must_match_unexpected($lex, $i, $line, TokenType::ASSIGN)) return;
        if ($j_type === "class") {
            if ($lex[++$i]["type"] === TokenType::NEW_LITERAL) {
                if (!$new_val = new_object($_GLOBALS, $lex, ++$i, $line, $e)) return false;
                $_GLOBALS["SYMBOL_TABLE"][$name] = shape("value" => $new_val, "type" => $e);
                return;
            }
            $i--;
        }
        return assign($_GLOBALS, $lex, ++$i, $e, $line, $name);
    }
    if ($_GLOBALS["VAR_IDS"]->contains($lex[0]["type"])) {
        $sym = $_GLOBALS["SYMBOL_TABLE"][$lex[0]["value"]];
        if ($lex[0]["type"] === TokenType::OBJ_ID && $lex[$i]["type"] === TokenType::DOT) {
            $i++;
            if (!($d = dereference($_GLOBALS, $sym["type"], $sym["value"], $lex, &$i, $line))) return;
            if ($end = check_end($lex, $i, $line)) {
                if ($end === -1) return;
                return end_parse(get_display_val($_GLOBALS, $d["type"], $d["value"]));
            }
            if (!must_match_unexpected($lex, $i, $line, TokenType::ASSIGN)) return;
            if ($lex[++$i]["type"] === TokenType::NEW_LITERAL) {
                if (!($set_val = new_object($_GLOBALS, $lex, ++$i, $line, $d["type"]))) return;
            }
            else if (($set_val = parse_type($_GLOBALS, $lex, &$i, $line, $d["type"])) === false) return;
            else if (!must_end($lex, $i, $line)) return;
            if (!mysqli_query($_GLOBALS["CONN"], "UPDATE " . $d["parent"]["type"] . " SET " . $d["row_name"] . "=" .
                $set_val . " WHERE _id=" . $d["parent"]["value"])) return error("an unknown MySQL error occurred");
            return;
        }
        if ($lex[$i]["type"] === TokenType::ASSIGN)
            return assign($_GLOBALS, $lex, ++$i, $sym["type"], $line, $lex[0]["value"]);
        if (!must_end($lex, $i, $line)) return;
        return end_parse(get_display_val($_GLOBALS, $sym["type"], $sym["value"]));
    }
    return unexpected_token($lex[0], $line);
}

// return false or value of new object
function new_object(dict $_GLOBALS, vec $lex, int $i, string $line, string $e) {
    $class_map = $_GLOBALS["CLASS_MAP"];
    if (!($class_name = must_match($_GLOBALS, $lex, $i, $line, TokenType::CLASS_ID))) return false;
    if ($e != "" && $e != $class_name)
        return carrot_and_error("expected " . $e . " but found " . $class_name, $line, $lex[$i]["char_num"]);
    if (!must_match($_GLOBALS, $lex, ++$i, $line, TokenType::L_PAREN)) return false;
    $var_types = $class_map[$class_name]->toValuesArray();
    $var_values = vec[];
    $i++;
    for ($j = 0; $j < count($var_types); $j++) {
        // === false to check for "0"
        if (($var_values[] = parse_type($_GLOBALS, $lex, &$i, $line, $var_types[$j])) === false) {
            echo $class_name, " constructor expects the following parameters: (";
            $var_names = $class_map[$class_name]->toKeysArray();
            for ($j = 0; $j < count($var_names) - 1; $j++) echo $var_types[$j], " ", $var_names[$j], ", ";
            return end_parse($var_types[count($var_names) - 1] . " " . $var_names[count($var_names) - 1] . ")");
        }
        if ($j + 1 < count($var_types) && !must_match($_GLOBALS, $lex, $i++, $line, TokenType::COMMA)) return;
    }
    if (!r_paren_semi($_GLOBALS, $lex, $i, $line)) return false;
    $query = "INSERT INTO " . $class_name . " VALUES (default";
    for ($j = 0; $j < count($var_types); $j++) {
        $query .= ", " . (($var_values[$j] === null) ? "null" : $var_values[$j]);
    }
    if (!mysqli_query($_GLOBALS["CONN"], $query . ")"))
        return error("an unknown MySQL error occurred");
    return mysqli_fetch_row(mysqli_query($_GLOBALS["CONN"], "SELECT LAST_INSERT_ID()"))[0];
}

function assign(dict $_GLOBALS, vec $lex, int $i, string $e, string $line, string $name) {
    if (($val = parse_type($_GLOBALS, $lex, &$i, $line, $e)) === false) return;
    if (!must_end($lex, $i, $line)) return;
    $_GLOBALS["SYMBOL_TABLE"][$name] = shape("type" => $e, "value" => $val);
}

function dereference(dict $_GLOBALS, string $type, $value, vec $lex, int &$i, string $line) {
    for (;; $i++) {
        if (!$_GLOBALS["ALL_IDS"]->contains($lex[$i]["type"]))
            return unexpected_token($lex[$i], $line);
        if ($value === null) return carrot_and_error("null pointer exception", $line, $lex[$i - 1]["char_num"]);
        if (!$_GLOBALS["CLASS_MAP"][$type]->contains($lex[$i]["value"]))
            return carrot_and_error($lex[$i]["value"] . " does not exist in class " . $type, $line, $lex[$i]["char_num"]);
        $class_var_type = $_GLOBALS["CLASS_MAP"][$type][$lex[$i]["value"]];
        $is_primitive = $_GLOBALS["PRIM"]->contains($class_var_type);
        $row_name =  $is_primitive ? $lex[$i]["value"] : java_ref_to_mysql($class_var_type, $lex[$i]["value"]); 
        $result = mysqli_query($_GLOBALS["CONN"], "SELECT " . $row_name . " FROM " . $type . " WHERE _id=" . $value);
        if (!$result) return error("an unnown MySXQL error occurred");
        $parent = shape("type" => $type, "value" => $value);
        $value = mysqli_fetch_row($result)[0];
        $type = $class_var_type;
        if ($lex[++$i]["type"] != TokenType::DOT || $is_primitive) break;
    }
    return shape("parent" => $parent, "value" => $value, "type" => $type, "row_name" => $row_name);
}

// return value if parsed correctly or false otherwise
function parse_type(dict $_GLOBALS, vec $lex, int &$i, string $line, string $e) {
    // TODO: Implement default
    $token = $lex[$i++];
    switch($token["type"]) {
    case TokenType::OBJ_ID:
        $sym = $_GLOBALS["SYMBOL_TABLE"][$token["value"]];
        if ($lex[$i]["type"] === TokenType::DOT) {
            $i++;
            if (!$d = dereference($_GLOBALS, $sym["type"], $sym["value"], $lex, &$i, $line)) return false;
            return $d["value"];
        }
        if ($sym["type"] != $e) return expected_but_found($_GLOBALS, $token, $line, $e);
        return $sym["value"];
    case TokenType::INT_LITERAL:
        $int_val = (int)$token["value"];
        if ($e === "float") {
            if ($int_val <= $_GLOBALS["FLOAT_MAX"]) return $token["value"];
            return carrot_and_error("int literal is too large for type float", $line, $token["char_num"]);
        }
        if ($e === "double") {
            if ((double)$token["value"] != INF) return $token["value"];
            return carrot_and_error("int literal is too large for type double", $line, $token["char_num"]);
        }
        if ($_GLOBALS["INT_MAX"]->containsKey($e)) {
            $max = $_GLOBALS["INT_MAX"][$e];
            // TODO: Figure out how to check for longs on a 32 bit machine
            if ("" . $int_val != $token["value"])
                return carrot_and_error("integer value too large", $line, $token["char_num"]);
            if ($int_val > $max || -$int_val > $max + 1)
                return carrot_and_error("integer value " . $int_val .
                    " is too large for type " . $e, $line, $token["char_num"]); 
            return $token["value"];
        }
        return expected_but_found($_GLOBALS, $token, $line, $e);
    case TokenType::FLOAT_LITERAL:
        $float_val = (double)$token["value"];
        if ($float_val === INF)
            return carrot_and_error("decimal literal is too large", $line, $token["char_num"]);
        if ($e === "float") {
            if ($float_val > $_GLOBALS["FLOAT_MAX"])
                return carrot_and_error("decimal literal is too large for type float", $line, $token["char_num"]);
            return $token["value"];
        }
        if ($e === "double") return $token["value"];
        return expected_but_found($_GLOBALS, $token, $line, $e);
    case TokenType::CHAR_LITERAL:
        if ($e === "char") return $token["value"];
        return expected_but_found($_GLOBALS, $token, $line, $e);
    case TokenType::STRING_LITERAL:
        if ($e === "String") return $token["value"];
        return expected_but_found($_GLOBALS, $token, $line, $e);
    case TokenType::BOOLEAN_LITERAL:
        if ($e === "boolean") return $token["value"];
        return expected_but_found($_GLOBALS, $token, $line, $e);
    case TokenType::NULL_LITERAL:
        return $_GLOBALS["PRIM"]->contains($e) ? expected_but_found($_GLOBALS, $token, $line, $e) : null;
    case TokenType::BOOLEAN_ID: return parse_id($_GLOBALS, $token, $line, $e, new Set(vec["boolean"]));
    case TokenType::CHAR_ID: return parse_id($_GLOBALS, $token, $line, $e, new Set(vec["char", "String"]));
    case TokenType::STRING_ID: return parse_id($_GLOBALS, $token, $line, $e, new Set(vec["String"]));
    case TokenType::FLOAT_ID: return parse_id($_GLOBALS, $token, $line, $e, new Set(vec["float", "double"]));
    case TokenType::DOUBLE_ID: return parse_id($_GLOBALS, $token, $line, $e, new Set(vec["double"]));
    case TokenType::BYTE_ID:
        return parse_id($_GLOBALS, $token, $line, $e, new Set(vec["byte", "short", "int", "long", "float", "double"]));
    case TokenType::SHORT_ID:
        return parse_id($_GLOBALS, $token, $line, $e, new Set(vec["short", "int", "long", "float", "double"]));
    case TokenType::INT_ID:
        return parse_id($_GLOBALS, $token, $line, $e, new Set(vec["int", "long", "float", "double"]));
    case TokenType::LONG_ID:
        return parse_id($_GLOBALS, $token, $line, $e, new Set(vec["long", "float", "double"]));
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
    if ($type === "boolean") return $val && $val !== "false" ? "true" : "false";
    if ($type === "double" || $type === "float") {
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

function must_match_unexpected(vec $lex, int $i, string $line, TokenType $e) {
    if ($lex[$i]["type"] != $e)
        return unexpected_token($lex[$i], $line);
    return $lex[$i]["value"];
}

function unexpected_token($token, string $line): boolean {
    return carrot_and_error("unexpected token: " . $token["value"], $line, $token["char_num"]);
}

function expected_but_found(dict $_GLOBALS, $token, string $line, string $e): boolean {
    return carrot_and_error("expected " . $e . " but found " .
        $_GLOBALS["TOKEN_NAME_MAP"][$token["type"]], $line, $token["char_num"]);
}

function must_end(vec $lex, int $i, string $line): boolean {
    if ($lex[$i]["type"] === TokenType::EOL) return true;
    else if ($lex[$i]["type"] === TokenType::SEMI && $lex[++$i]["type"] == TokenType::EOL) return true;
    return unexpected_token($lex[$i], $line);
}

// -1 = error, 0 = not ending, 1 = ending
function check_end(vec $lex, int $i, string $line): int {
    if ($lex[$i]["type"] === TokenType::EOL) return 1;
    if ($lex[$i]["type"] === TokenType::SEMI) {
        if ($lex[++$i]["type"] === TokenType::EOL) return  1;
        unexpected_token($lex[$i], $line);
        return -1;
    } 
    return 0;
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

function end_parse($print) {
    echo $print, "\n";
    return false;
}

function carrot_and_error(string $message, string $line, int $index) {
    echo $line, "\n", str_repeat(" ", $index), "^\n";
    return error($message);
}

function error(string $message) {
    echo "Error: ", $message, "\n";
    return false;
}

function lex_file($_GLOBALS, $class_name) {
    $file_path = $_GLOBALS["CLASSES_DIR"] . $class_name . ".java";
    $class_file = fopen($file_path, "r");
    if (!$class_file)
        return error("file " . $file_path . " does not exist");
    $ret = vec[];
    $file_vec = vec[""];
    $comment = false;
    $i = 1;
    for (; $line = fgets($class_file); $i++) {
        $line = substr($line, 0, strlen($line) - 1);
        if (!$lex_result = lex_line($_GLOBALS, $ret, $line, $i, false, $comment))
            return error("\nFound at " . $file_path . ":" . $i);
        $ret = $lex_result["tokens"];
        $comment = $lex_result["comment"];
        $file_vec[] = $line;
    }
    $ret[] = shape("type" => TokenType::EOF, "value" => "end of file", "char_num" => 0);
    return shape("tokens" => $ret, "file_vec" => $file_vec, "file_path" => $file_path);
}

function lex_line(dict $_GLOBALS, vec $ret, string $line, int $line_num, boolean $command, boolean $comment) {
    // For loop starts on beginning of new token attempt
    for ($i = 0; $i < strlen($line); $i++) {
        if ($comment) {
            if ($line[$i] != "*" || $i + 1 >= strlen($line) || $line[$i + 1] != "/") continue;
            $i++;
            $comment = false;
            continue;
        }
        if ($line[$i] == " ") continue;

        // Begins with letter (keyword, boolean literal, ID)
        if (ctype_alpha($line[$i])) {
            $start = $i;
            for (; $i < strlen($line) - 1; $i++) {
                if (!ctype_alpha($line[$i + 1]) && !is_numeric($line[$i + 1]) && $line[$i + 1] != "_") break;
            }
            $value = substr($line, $start, $i - $start + 1);
            $type = TokenType::ID;
            if ($_GLOBALS["KEYWORDS"]->containsKey($value)) $type = $_GLOBALS["KEYWORDS"][$value];
            else if ($_GLOBALS["CLASS_MAP"]->containsKey($value)) $type = TokenType::CLASS_ID;
            else if ($_GLOBALS["SYMBOL_TABLE"]->containsKey($value)) {
                $j_type = $_GLOBALS["SYMBOL_TABLE"][$value]["type"];
                $type = $_GLOBALS["JAVA_TYPE_TO_ID"]->containsKey($j_type) ?
                    $_GLOBALS["JAVA_TYPE_TO_ID"][$j_type] :
                    TokenType::OBJ_ID;
            }
            $ret[] = shape("type" => $type, "value" => $value, "char_num" => $start, "line_num" => $line_num);

        // int or float literal
        } else if (is_numeric($line[$i])) {
            if (!$result = lex_number($line, &$i, false)) return false;
            $ret[] = $result;

        // String literal
        } else if ($line[$i] == "\"") {
            $start = $i;
            for ($i++; $i < strlen($line); $i++) {
                if ($line[$i] == "\"" && $line[$i - 1] != "\\") {
                    $ret[] = shape(
                        "type" => TokenType::STRING_LITERAL,
                        "value" => substr($line, $start, $i - $start + 1),
                        "char_num" => $start,
                         "line_num" => $line_num
                    );
                    break;
                }
            }
            if ($i == strlen($line)) return carrot_and_error("unclosed quotation", $line, $start);

        // Char literal
        } else if ($line[$i] == "'") {
            if ($i + 1 == strlen($line)) return carrot_and_error("unclosed char literal", $line, $start);
            $start = $i;
            $c = $line[++$i];
            if ($c == "'") return carrot_and_error("empty char literal", $line, $start);
            if ($c == "\\") { // TODO: Add support for unicode escape sequences
                if ($i + 2 >= strlen($line)) return carrot_and_error("unclosed char literal", $line, $start);
                if ($line[$i += 2] != "'" || !$_GLOBALS["ESCAPE_CHARS"]->contains($line[$i - 1]))
                    return carrot_and_error("invalid char literal", $line, $start);
            } else {
                if ($i + 1 >= strlen($line)) return carrot_and_error("unclosed char literal", $line, $start);
                if ($line[++$i] != "'") return carrot_and_error("invalid char literal", $line, $start);
            }
            $ret[] = shape(
                "type" => TokenType::CHAR_LITERAL,
                "value" => substr($line, $start, $i - $start + 1),
                "char_num" => $start,
                "line_num" => $line_num
            );

        // DOT or float
        } else if ($line[$i] == ".") {
            if ($i + 1 == strlen($line) || !(is_numeric($line[$i + 1]))) {
                $ret[] = shape("type" => TokenType::DOT, "value" => ".", "char_num" => $i, "line_num" => $line_num);
            } else {
                if (!$result = lex_number($line, &$i, true)) return false;
                $ret[] = $result;
            }

        // Negative number;
        } else if ($line[$i] == "-") {
            if ($i + 1 == strlen($line) || !is_numeric($line[$i + 1]))
                return carrot_and_error("unrecognized symbol: -", $line, $i);
            if (!$result = lex_number($line, &$i, false)) return false;
            $ret[] = $result;

        // Underscore error
        } else if ($line[$i] == "_") {
            return carrot_and_error("JavaQL identifiers may not begin with an underscore", $line, $i);

        // Comments
        } else if ($line[$i] == "/") {
            if ($i + 1 == strlen($line) || ($line[++$i] != "/" && $line[$i] != "*"))
               carrot_and_error("unrecognized symbol: " . $line[$i], $line, $i); 
            if ($line[$i] == "/") break; // Rest of line is comment
            $comment = true; // /* comment has begun

        // Symbols 
        } else {
            if ($i + 1 == strlen($line) || !$_GLOBALS["SYMBOLS"]->containsKey($line[$i] . $line[$i + 1])) {
                if ($_GLOBALS["SYMBOLS"]->containsKey($line[$i]))
                    $ret[] = shape(
                        "type" => $_GLOBALS["SYMBOLS"][$line[$i]],
                        "value" => $line[$i],
                        "char_num" => $i,
                        "line_num" => $line_num
                    );
                else return carrot_and_error("unrecognized symbol: " . $line[$i], $line, $i);
            } else $ret[] = shape(
                "type" => $_GLOBALS["SYMBOLS"][$line[$i] . $line[$i + 1]],
                "value" => $line[$i] . $line[++$i],
                "char_num" => $i - 1,
                "line_num" => $line_num
            );
        } 
    }
    if ($command) $ret[] = shape("type" => TokenType::EOL, "value" => "end of line", "char_num" => strlen($line));
    return shape("tokens" => $ret, "comment" => $comment);
}

function lex_number(string $line, int &$i, bool $decimal) {
    $start = $i;
    $e = false;
    $invalid = false;
    for (; $i < strlen($line) - 1; $i++) {
        if ($line[$i + 1] == ".") {
            if ($decimal || $e) $invalid = true;
            $decimal = true;
        } else if ($line[$i + 1] == 'e' || $line[$i + 1] == "E") {
            if ($e || ++$i + 1 == strlen($line)) $invalid = true;
            else if (!is_numeric($line[$i + 1])) {
                if (($line[$i + 1] != "-" && $line[$i + 1] != "+") ||
                    ++$i + 1 == strlen($line) || !is_numeric($line[$i + 1])) $invalid = true;
            }
            $e = true;
        } else if (!is_numeric($line[$i + 1])) break;
        if ($invalid) return carrot_and_error("malformed_number", $line, $start);
    }
    return shape(
        "type" => $decimal ? TokenType::FLOAT_LITERAL : TokenType::INT_LITERAL, 
        "value" => substr($line, $start, $i - $start + 1),
        "char_num" => $start
    );
}

