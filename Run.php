<?hh 

error_reporting(E_ERROR | E_PARSE);

include("Globals.php");

$config_file = fopen('database.txt', 'r');
if (!$config_file)
    die($_GLOBALS["PROJECT_NAME"] .
    " Error: you must include a database.txt file with your MySQL database credentials.\n" .
    "Refer to the README: " . $_GLOBALS["PROJECT_URL"] . "\n");

if (!(($host = fgets($config_file)) && ($username = fgets($config_file))
    && ($password = fgets($config_file)) && $database = fgets($config_file))) {
    die($_GLOBALS["PROJECT_NAME"] . " Error: database.txt must contain 4 lines of your MySQL database credentials.\n"
        . "Refer to the README: " . $_GLOBALS["PROJECT_URL"] . "\n");
} 
fclose($config_file);

// Create connection
echo "Attempting to connect to database...\n";
$_GLOBALS["conn"] = mysqli_connect(trim($host), trim($username), trim($password), trim($database));

// Check connection
if (!$_GLOBALS["conn"]) die($_GLOBALS["PROJECT_NAME"] . "Error: connection failed..\n");
if ($_GLOBALS["conn"]->connect_error)
    die($_GLOBALS["PROJECT_NAME"] . "Error: connection failed - " . $_GLOBALS["conn"]->connect_error . "\n");
echo "Connected successfully\n";

// Load Classes
echo "Loading classes...\n";
$class_map = dict[];
$result = mysqli_query($_GLOBALS["conn"], "SHOW TABLES");
while ($row = mysqli_fetch_row($result)) {
    if ($row[0][0] === "_") continue;
    $class = mysqli_query($_GLOBALS["conn"], "DESCRIBE " . $row[0]);
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

// Load lists
$list_table_names = vec[];
// TODO: load lists

echo "Classes loaded\n\n";


$_GLOBALS["class_map"] = new Map($class_map);
$_GLOBALS["list_table_names"] = new Map($list_table_names);
$_GLOBALS["symbol_table"] = new Map();

collect_list_garbo($_GLOBALS);

// Begin CLI 
for (;;) {
    $_GLOBALS["query_queue"] = new Vector();
    $_GLOBALS["assign"] = new Map();
    $_GLOBALS["print_qr"] = new Map();
    $line = trim(readline($_GLOBALS["PROJECT_NAME"] . "> "));
    if ($line === "q" || $line === "quit") {
        collect_list_garbo($_GLOBALS);
        break;
    }
    if (!$lex = lex_line($_GLOBALS, vec[], $line, 0, true, false)) continue;
    if (!parse_and_execute(&$_GLOBALS, $lex["tokens"], $line)) continue;
    $query_results = new Vector();
    $quit = false;
    for ($i = 0; $i < count($_GLOBALS["query_queue"]); $i++) {
        $query_pieces = $_GLOBALS["query_queue"][$i];
        for ($j = 0; $j < count($query_pieces); $j++)
            if (is_int($query_pieces[$j])) $query_pieces[$j] = mysqli_fetch_row($query_results[$query_pieces[$j]])[0];
        if (!$result = mysqli_query($_GLOBALS["conn"], implode($query_pieces))) {
            error($_GLOBALS["MYSQL_ERROR"]);
            echo implode($query_pieces), "\n";
            $quit = true;
        }
        if ($quit) break;
        $query_results[] = $result;
    }
    if ($quit) continue;
    $assign = $_GLOBALS["assign"];
    if (count($assign) > 0)
        $_GLOBALS["symbol_table"][$assign["name"]] = shape(
            "type" => $assign["type"],
            "value" => mysqli_fetch_row($query_results[$assign["q_num"]])[0],
        );
    $print_qr = $_GLOBALS["print_qr"];
    if (count($print_qr) > 0)
        // TODO: Can we always keep this as single_ ?
        success(single_query_result_to_string($_GLOBALS, $query_results[$print_qr["qr_num"]], $print_qr["class_name"]));
}

function parse_and_execute(dict &$_GLOBALS, vec $lex, string $line) {
    if (count($lex) == 0) return false;
    $class_map = $_GLOBALS["class_map"];
    $i = 1;
    switch ($lex[0]["type"]) {
    case TokenType::EOL: return false;
    case TokenType::M_GET_ALL_DESC:
        if (!must_match($_GLOBALS, $lex, $i, $line, TokenType::L_PAREN)) return false;
        if (!r_paren_semi($_GLOBALS, $lex, ++$i, $line)) return false;
        return success(json_encode(class_map_print_format($class_map), JSON_PRETTY_PRINT));
    case TokenType::M_GET_DESC:
        if (!must_match($_GLOBALS, $lex, $i, $line, TokenType::L_PAREN)) return false;
        if (!($class_name = must_match($_GLOBALS, $lex, ++$i, $line, TokenType::CLASS_ID))) return false;
        if (!r_paren_semi($_GLOBALS, $lex, ++$i, $line)) return false;
        return success(json_encode(map_replace_list_types($class_map[$class_name]), JSON_PRETTY_PRINT));
    case TokenType::M_GET_CLASS_NAMES:
        if (!must_match($_GLOBALS, $lex, $i, $line, TokenType::L_PAREN)) return false;
        if (!r_paren_semi($_GLOBALS, $lex, ++$i, $line)) return false;
        return success(json_encode($class_map->toKeysArray(), JSON_PRETTY_PRINT));
    case TokenType::M_GET_LOCAL_VARIABLES:
        if (!must_match($_GLOBALS, $lex, $i, $line, TokenType::L_PAREN)) return false;
        if (!r_paren_semi($_GLOBALS, $lex, ++$i, $line)) return false;
        $print = dict[];
        $sym_table = $_GLOBALS["symbol_table"];
        foreach($sym_table->toKeysArray() as $key)
            $print[$key] = $sym_table[$key]["type"] === "String" ?
                remove_quotes($sym_table[$key]["value"]) :
                get_display_val($_GLOBALS, $sym_table[$key]["type"], $sym_table[$key]["value"]);
        return success(json_encode($print, JSON_PRETTY_PRINT));
    case TokenType::M_GET_VARIABLES:
        if (!must_match($_GLOBALS, $lex, $i, $line, TokenType::L_PAREN)) return false;
        $i++;
        if (!$param = parse_type($_GLOBALS, $lex, &$i, $line, "", false)) return false;
        if ($param["value"] === null)
            return carrot_and_error("getVariables() expects non-null parameter", $line, $lex[$i - 1]["char_num"]);
        if (is_primitive($_GLOBALS, $param["type"]))
            return carrot_and_error("getVariables() expects non-primitive parameter", $line, $lex[$i - 1]["char_num"]);
        if (!r_paren_semi($_GLOBALS, $lex, $i, $line)) return false;
        $query = "SELECT * FROM " . $param["type"] . " WHERE _ID=";
        if ($param["value"] instanceof QueryResult) {
            $query_pieces = vec[$query];
            $query_pieces[] = $param["value"]->q_num;
            $_GLOBALS["query_queue"][] = $query_pieces;
            $_GLOBALS["print_qr"]["qr_num"] = count($_GLOBALS["query_queue"]) - 1;
            $_GLOBALS["print_qr"]["class_name"] = $param["type"];
            return true;
        }
        if (!$result = mysqli_query($_GLOBALS["conn"], "SELECT * FROM "
            . $param["type"] . " WHERE _ID=" . $param["value"]))
            return error($_GLOBALS["MYSQL_ERROR"]);
        return success(single_query_result_to_string($_GLOBALS, $result, $param["type"]));
    case TokenType::M_GET_ALL_OBJECTS:
        if (!must_match($_GLOBALS, $lex, $i, $line, TokenType::L_PAREN)) return false;
        if (!($class_name = must_match($_GLOBALS, $lex, ++$i, $line, TokenType::CLASS_ID))) return false;
        if (!r_paren_semi($_GLOBALS, $lex, ++$i, $line)) return false;
        $result = mysqli_query($_GLOBALS["conn"], "SELECT * FROM " . $class_name);
        return success(query_result_to_string($_GLOBALS, $result, $class_name));
    case TokenType::M_DELETE_ALL_OBJECTS:
        if (!must_match($_GLOBALS, $lex, $i, $line, TokenType::L_PAREN)) return false;
        if (!($class_name = must_match($_GLOBALS, $lex, ++$i, $line, TokenType::CLASS_ID))) return false;
        if (!r_paren_semi($_GLOBALS, $lex, ++$i, $line)) return false;
        $confirm = readline("Are you sure you want to delete all objects of class " . $class_name . "? (y/n) ");
        if ($confirm !== "y" && $confirm !== "yes") return false;
        mysqli_query($_GLOBALS["conn"], "DELETE FROM " . $class_name);
        return true;
    case TokenType::M_DELETE_CLASS:
        if (!must_match($_GLOBALS, $lex, $i, $line, TokenType::L_PAREN)) return false;
        if (!($class_name = must_match($_GLOBALS, $lex, ++$i, $line, TokenType::CLASS_ID))) return false;
        if (!r_paren_semi($_GLOBALS, $lex, ++$i, $line)) return false;
        $ruined_classes = vec[];
        // TODO: do we need a deleteMultiple() if there's a codependency?
        // - Should we let the user know about this when they try to delete?
        foreach ($_GLOBALS["class_map"]->toKeysArray() as $key) {
            if ($key === $class_name) continue;
            // TODO: Also check for arrays and lists of this type later
            foreach ($_GLOBALS["class_map"][$key] as $type) {
                if ($type === $class_name || ($type instanceof ListType && $type->subtype === $class_name))
                    $ruined_classes[] = $key;
            }
        }
        if (count($ruined_classes) > 0)
            return error("cannot remove class because the following classes have objects or lists using type "
            . $class_name . ": " . implode(", ", $ruined_classes));
        $confirm = readline("Are you sure you want remove class " . $class_name . " and delete all of its objects? (y/n) ");
        if ($confirm !== "y" && $confirm !== "yes") return false;
        $bad_lists = mysqli_query($_GLOBALS["conn"], "SELECT location from _list WHERE generic=\""
            . $class_name . "\" OR generic REGEXP \"^_L[1-9]\d*" . $class_name . "$\"");
        if (!$bad_lists) return mysqli_error($_GLOBALS["MYSQL_ERROR"]);
        while ($list = mysqli_fetch_row($bad_lists))
            if (!delete_list($_GLOBALS, $list[0])) return false;
        if (!mysqli_query($_GLOBALS["conn"], "DROP TABLE " . $class_name)) return error($_GLOBALS["MYSQL_ERROR"]);
        $_GLOBALS["class_map"]->remove($class_name);
        // TODO: delete local variables of this type
        return true;
    case TokenType::M_BUILD:
        if (!must_match($_GLOBALS, $lex, $i++, $line, TokenType::L_PAREN)) return false;
        if ($lex[$i]["type"] !== TokenType::ID && $lex[$i]["type"] !== TokenType::CLASS_ID)
            return expected_but_found($_GLOBALS, $lex[$i], $line, "class name");
        $class_name = $lex[$i]["value"];
        $rebuild = ($lex[$i]["type"] === TokenType::CLASS_ID);
        if (!r_paren_semi($_GLOBALS, $lex, ++$i, $line)) return false;
        if (!$lex = lex_file($_GLOBALS, $class_name)) return false;
        $file_vec = $lex["file_vec"];
        $file_path = $lex["file_path"];
        $lex = $lex["tokens"];
        $i = 0;
        if (!must_match_f($_GLOBALS, $lex, $i, $file_vec, TokenType::CLASS_LITERAL))
            return found_location($file_path, $lex[$i]["line_num"]);
        if ($lex[++$i]["value"] !== $class_name) {
            expected_but_found_literal($lex[$i], $file_vec[$lex[$i]["line_num"]], $class_name);
            return found_location($file_path, $lex[$i]["line_num"]);
        }
        if (!must_match_f($_GLOBALS, $lex, ++$i, $file_vec, TokenType::L_CURLY))
            return found_location($file_path, $lex[$i]["line_num"]);
        $vars = vec[];
        $usedNames = new Set();
        while ($lex[++$i]["type"] !== TokenType::R_CURLY) {
            if (!$_GLOBALS["JAVA_TYPES"]->containsKey($lex[$i]["type"]) && $lex[$i]["value"] !== $class_name) {
                unexpected_token($lex[$i], $file_vec[$lex[$i]["line_num"]]);
                return found_location($file_path, $lex[$i]["line_num"]);
            }
            $type = $lex[$i++]["value"];
            if ($type === "List") {
                if (!($subtype = parse_list_subtype($_GLOBALS, $lex, &$i, "", $line))) return false;
                $type = new ListType($subtype, 0);
            }
            if (!$_GLOBALS["ALL_IDS"]->contains($lex[$i]["type"]))
                return unexpected_token($lex[$i], $file_vec[$lex[$i]["line_num"]]);
            $name = $lex[$i]["value"];
            if ($usedNames->contains($name)) {
                carrot_and_error("variable " . $name . " is defined twice",
                    $file_vec[$lex[$i]["line_num"]], $lex[$i]["char_num"]);
                return found_location($file_path, $lex[$i]["line_num"]);
            }
            $usedNames->add($name);
            // TODO: Right here is where we'd allow a default option ex. int i = 5;
            if (!must_match_f($_GLOBALS, $lex, ++$i, $file_vec, TokenType::SEMI))
                return found_location($file_path, $lex[$i]["line_num"]);
            $vars[] = shape("type" => $type, "name" => $name);
        }
        if (!must_match_f($_GLOBALS, $lex, ++$i, $file_vec, TokenType::EOF))
            return found_location($file_path, $lex[$i]["line_num"]);
        if (!$rebuild) {
            $var_map = dict[];
            $query = "CREATE TABLE " . $class_name . " (_id int NOT NULL AUTO_INCREMENT";
            foreach ($vars as $var) {
                $var_map[$var["name"]] = $var["type"];
                $sql_column = get_sql_column($_GLOBALS, $var["type"], $var["name"]);
                $query .= ", " . $sql_column["name"] . " " . $sql_column["type"];
            }
            if (!mysqli_query($_GLOBALS["conn"], $query . ", PRIMARY KEY(_id))")) {
                return error($_GLOBALS["MYSQL_ERROR"]);
            }
            $class_map = map_add_lexo($_GLOBALS["class_map"], $class_name, new Map($var_map));
            $_GLOBALS["class_map"] = $class_map;
            return true;
        }
        // TODO: Code for rebuild
        // - Prompt user for default value of new columns?
        return error("rebuild is not implemented");
    case TokenType::M_BUILD_ALL:
        if (!must_match($_GLOBALS, $lex, $i, $line, TokenType::L_PAREN)) return false;
        if (!r_paren_semi($_GLOBALS, $lex, ++$i, $line)) return false;
        // TODO: code for build all
        // - Start by asking if we want to delete any existing tables we don't find a .java for
        // - Accumulate list of classes based on what exists (and isn't getting deleted) and what's in the directory
        // - Attempt to parse each file using mostly the code from build and queue up queries
        // - write code for rebuild before attempting this
        return success("NOT IMPLEMENTED");
    case TokenType::M_RENAME:
        if (!must_match($_GLOBALS, $lex, $i, $line, TokenType::L_PAREN)) return false;
        if (!$class_name = must_match($_GLOBALS, $lex, ++$i, $line, TokenType::CLASS_ID)) return false;
        $is_class = true;
        if ($lex[++$i]["type"] === TokenType::DOT) {
            $is_class = false;
            if (!$_GLOBALS["ALL_IDS"]->contains($lex[++$i]["type"])) return unexpected_token($lex[$i], $line);
            $var_name = $lex[$i]["value"];
            if (!$_GLOBALS["class_map"][$class_name]->containsKey($var_name))
                return carrot_and_error($var_name . " does not exist in class "
                . $class_name, $line, $lex[$i]["char_num"]);
            $type = $_GLOBALS["class_map"][$class_name][$var_name];
            $i++;
        }
        if (!must_match_unexpected($lex, $i, $line, TokenType::COMMA)) return false;
        $i++;
        if (!($new_name = parse_type($_GLOBALS, $lex, &$i, $line, "String", false))) return false;
        $new_name = remove_quotes($new_name["value"]);
        if (strlen($new_name) === 0) return error("cannot rename variable to empty string");
        if ($is_class) {
            // TODO: make sure new class name is unique
            /*
            return carrot_and_error("new class name nust be unique - found "
            . $_GLOBALS["TOKEN_NAME_MAP"][$new_name_type], $line, $lex[$i]["char_num"]);
             */
        }
        if (!r_paren_semi($_GLOBALS, $lex, $i, $line)) return false;
        if ($is_class) return error("NOT IMPLEMENTED");  // TODO: return rename_class($_GLOBALS, $class_name, $new_name);
        /* class stuff:
            // check if there are any differences betweem classes/ and current database. Fail if there are.
            // TODO: Are you sure?
            $query = "RENAME TABLE " . $class_name . " TO " . $class_name;
            if (!mysqli_query($_GLOBALS["conn"], $query)) return error(!$_GLOBALS["/YSQL_ERROR"]);
            $_GLOBALS["class_map"][$new_name] = $_GLOBALS["class_map"][$class_name];
            $_GLOBALS["class_map"]->remove($class_name);
            // - rename in ALL files
            // - rename ALL over sym table
            // - rename ALL over class_map
            // - rename ALL columns of type $class_name
         */
        // TODO: Check if there are differences with class file.
        if ($new_name === $var_name) return true;
        foreach($_GLOBALS["class_map"][$class_name]->toKeysArray() as $key) {
            if ($key === $new_name) return error($class_name . " already has a variable named " . $new_name);
        }
        $old_row_name = $var_name;
        $new_row_name = $new_name;
        if (!is_primitive($_GLOBALS, $type)) {
            $old_row_name = java_ref_to_mysql($type, $old_row_name);
            $new_row_name = java_ref_to_mysql($type, $new_row_name);
        }
        if (!mysqli_query($_GLOBALS["conn"], "ALTER TABLE " .
            $class_name . " RENAME COLUMN " . $old_row_name. " TO " . $new_row_name))
            return error($_GLOBALS["MYSQL_ERROR"]);
        $_GLOBALS["class_map"][$class_name] = map_replace($_GLOBALS["class_map"][$class_name], $var_name, $new_name);
        // TODO: Edit file to reflect change?
        return true;
    case TokenType::NEW_LITERAL:
        if (!new_object($_GLOBALS, $lex, &$i, $line, "", false)) return false;
        return must_end($lex, $i, $line);
    case TokenType::ID:
        // This line must exist for unit tests to run correctly
        if ($lex[0]["value"] == $_GLOBALS["UNIT_TEST"]) return success($_GLOBALS["UNIT_TEST"]);
    }
    if ($_GLOBALS["JAVA_TYPES"]->containsKey($lex[0]["type"])) {
        $j_type = $_GLOBALS["JAVA_TYPES"][$lex[0]["type"]];
        if ($j_type === "List") {
            if (!($e = parse_list_subtype($_GLOBALS, $lex, &$i, "", $line))) return false;
            $e = new ListType($e, 0);
        }
        else $e = ($j_type === "class") ? $lex[0]["value"] : $j_type;
        if ($lex[$i]["type"] === TokenType::CLASS_ID)
            return carrot_and_error($_GLOBALS["PROJECT_NAME"] .
            " variables may not share names with classes", $line, $lex[$i]["char_num"]);
        else if ($_GLOBALS["VAR_IDS"]->contains($lex[$i]["type"]))
            return carrot_and_error("variable " . $lex[$i]["value"] .
            " is already defined", $line, $lex[$i]["char_num"]);
        if (!must_match_unexpected($lex, $i, $line, TokenType::ID)) return false;
        $name = $lex[$i]["value"];
        if ($end = check_end($lex, ++$i, $line)) {
            if ($end === -1) return false;
            $_GLOBALS["symbol_table"][$name] = shape("type" => $e, "value" => $_GLOBALS["DEFAULTS"][$j_type]);
            return true;
        }
        if (!must_match_unexpected($lex, $i, $line, TokenType::ASSIGN)) return false;
        return assign($_GLOBALS, $lex, ++$i, $e, $line, $name);
    }
    if ($_GLOBALS["VAR_IDS"]->contains($lex[0]["type"])) {
        $sym = $_GLOBALS["symbol_table"][$lex[0]["value"]];
        if ($lex[0]["type"] === TokenType::OBJ_ID && $lex[$i]["type"] === TokenType::DOT) {
            $i++;
            if (!($d = dereference($_GLOBALS, $sym["type"], $sym["value"], $lex, &$i, $line))) return false;
            if ($end = check_end($lex, $i, $line)) {
                if ($end === -1) return false;
                return success(get_display_val($_GLOBALS, $d["type"], $d["value"]));
            }
            if (!must_match_unexpected($lex, $i++, $line, TokenType::ASSIGN)) return false;
            if (!($set_val = parse_type($_GLOBALS, $lex, &$i, $line, $d["type"], true))) return false;
            else if (!must_end($lex, $i, $line)) return false;
            $set_val = $set_val["value"];
            $query = "UPDATE " . $d["parent"]["type"] . " SET " . $d["row_name"] . "=";
            if ($set_val instanceof QueryResult) {
                $query_pieces = vec[$query];
                $query_pieces[] = $set_val->q_num;
                $query_pieces[] = " WHERE _id=" . $d["parent"]["value"];
                $_GLOBALS["query_queue"][] = $query_pieces;
            } else if (!mysqli_query($_GLOBALS["conn"], $query . $set_val .
                " WHERE _id=" . $d["parent"]["value"])) return error($_GLOBALS["MYSQL_ERROR"]);
            return true;
        }
        if ($lex[$i]["type"] === TokenType::ASSIGN)
            return assign($_GLOBALS, $lex, ++$i, $sym["type"], $line, $lex[0]["value"]);
        if (!must_end($lex, $i, $line)) return false;
        return success(get_display_val($_GLOBALS, $sym["type"], $sym["value"]));
    }
    return unexpected_token($lex[0], $line);
}

// return false or type & value of new object
function new_object(dict $_GLOBALS, vec $lex, int &$i, string $line, $e, bool $ref) {
    $class_map = $_GLOBALS["class_map"];
    if ($lex[$i]["type"] === TokenType::J_LIST) {
        if (!($e instanceof ListType) && $e !== "") return expected_but_found_literal($lex[$i], $line, "List");
        $i++;
        return new_list($_GLOBALS, $lex, &$i, $line, $e, (int)$ref);
    }
    if (!($class_name = must_match($_GLOBALS, $lex, $i, $line, TokenType::CLASS_ID))) return false;
    if ($e !== "" && $e !== $class_name)
        return expected_but_found_literal($lex[$i], $line, $e);
    if (!must_match($_GLOBALS, $lex, ++$i, $line, TokenType::L_PAREN)) return false;
    $var_types = $class_map[$class_name]->toValuesArray();
    $var_values = vec[];
    $i++;
    for ($j = 0; $j < count($var_types); $j++) {
        if (!($var_val = parse_type($_GLOBALS, $lex, &$i, $line, $var_types[$j], true))) {
            echo $class_name, " constructor expects the following parameters: (";
            $var_names = $class_map[$class_name]->toKeysArray();
            for ($j = 0; $j < count($var_names) - 1; $j++) echo $var_types[$j], " ", $var_names[$j], ", ";
            return fail($var_types[count($var_names) - 1] . " " . $var_names[count($var_names) - 1] . ")");
        }
        $var_values[] = $var_val["value"];
        if ($j + 1 < count($var_types) && !must_match($_GLOBALS, $lex, $i++, $line, TokenType::COMMA)) return false;
    }
    if (!must_match($_GLOBALS, $lex, $i++, $line, TokenType::R_PAREN)) return false;
    $query_pieces = vec[];
    $query = "INSERT INTO " . $class_name . " VALUES (default";
    for ($j = 0; $j < count($var_types); $j++) {
        if ($var_values[$j] instanceof QueryResult) {
            $query_pieces[] = $query . ", ";
            $query_pieces[] = $var_values[$j]->q_num;
            $query = "";
        } else $query .= ", " . (($var_values[$j] === null) ? "null" : $var_values[$j]);
    }
    $query_pieces[] = $query . ")";
    $_GLOBALS["query_queue"][] = $query_pieces;
    $_GLOBALS["query_queue"][] = vec["SELECT LAST_INSERT_ID()"];
    $qr = new QueryResult(count($_GLOBALS["query_queue"]) - 1);
    return shape("value" => $qr, "type" => $class_name);
}

function new_list(dict $_GLOBALS, vec $lex, int &$i, string $line, $e, int $ref_count) {
    if (!($subtype = parse_list_subtype($_GLOBALS, $lex, &$i, $e, $line))) return false;
    // TODO: copy constructor
    if (!must_match($_GLOBALS, $lex, $i++, $line, TokenType::L_PAREN)) return false;
    $size = 0;
    if (!must_match($_GLOBALS, $lex, $i++, $line, TokenType::R_PAREN)) return false;
    // TODO: consider adding what list constructor expects ??
    $table_name = get_new_list_table_name($_GLOBALS);
    $subtype_table = $subtype;
    if ($subtype instanceof ListType) {
        $subtype_table = "_list";
        $sql_type = "int";
    } else $sql_type = $_GLOBALS["PRIM"]->contains($subtype) ? $_GLOBALS["TO_SQL_TYPE_MAP"][$subtype] : "int";
    $query = "CREATE TABLE " . $table_name . " (value " . $sql_type;
    $query .= ($sql_type === "int")
        ? ", FOREIGN KEY (value) REFERENCES " . $subtype_table . "(_id) ON DELETE SET NULL)"
        : ")";
    $_GLOBALS["query_queue"][] = vec[$query];
    $insert_values = vec["default", list_subtype_to_sql($subtype), $size, $ref_count, add_quotes($table_name)];
    $_GLOBALS["query_queue"][] = vec["INSERT INTO _list VALUES (" . implode(", ", $insert_values) . " )"];
    $_GLOBALS["query_queue"][] = vec["SELECT LAST_INSERT_ID()"];
    return shape(
        "type" => new ListType($subtype, 0),
        "value" => new QueryResult(count($_GLOBALS["query_queue"]) - 1)
    );
}

function list_subtype_to_sql($subtype): string {
    return add_quotes($subtype instanceof ListType ? "_L" . $subtype->dim . $subtype->subtype : $subtype);
}

function parse_list_subtype(dict $_GLOBALS, vec $lex, int &$i, $e, string $line) {
    if ($e instanceof ListType) $e = $e->inner(); // Allows e to remain ""
    if (!must_match($_GLOBALS, $lex, $i++, $line, TokenType::LT)) return false;
    if (!$_GLOBALS["JAVA_TYPES"]->containsKey($lex[$i]["type"]))
        return expected_but_found($_GLOBALS, $lex[$i], $line, "type");
    $subtype = $lex[$i++]["value"];
    if ($subtype === "List") {
        if ($e instanceof ListType || $e === "") {
            if (!($subtype = parse_list_subtype($_GLOBALS, $lex, &$i, $e, $line))) return false;
            $subtype = new ListType($subtype, 0);
        } else return expected_but_found($_GLOBALS, $lex[$i - 1], $line, $e);
    } else if ($subtype !== $e && $e !== "") return expected_but_found($_GLOBALS, $lex[$i - 1], $line, $e);
    if (!must_match($_GLOBALS, $lex, $i++, $line, TokenType::GT)) return false;
    return $subtype;
}

function get_new_list_table_name(dict $_GLOBALS) : string {
    $alpha = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz";
    for (;;) {
        $table_name_vec = vec["_"];
        for ($i = 0; $i < 20; $i++) $table_name_vec[] = $alpha[rand(0,51)];
        $table_name = implode($table_name_vec);
        if (!$_GLOBALS["list_table_names"]->contains($table_name)) return $table_name;
    }
}

function assign(dict $_GLOBALS, vec $lex, int $i, $e, string $line, string $name): boolean {
    if (!($val = parse_type($_GLOBALS, $lex, &$i, $line, $e, false))) return false;
    $val = $val["value"];
    if (!must_end($lex, $i, $line)) return false;
    if ($val instanceof QueryResult) {
        $_GLOBALS["assign"]["name"] = $name;
        $_GLOBALS["assign"]["q_num"] = $val->q_num;
        $_GLOBALS["assign"]["type"] = $e;
    }
    else $_GLOBALS["symbol_table"][$name] = shape("type" => $e, "value" => $val);
    return true;
}

function dereference(dict $_GLOBALS, string $type, $value, vec $lex, int &$i, string $line) {
    for (;; $i++) {
        if (!$_GLOBALS["ALL_IDS"]->contains($lex[$i]["type"]))
            return unexpected_token($lex[$i], $line);
        if ($value === null) return carrot_and_error("null pointer exception", $line, $lex[$i - 1]["char_num"]);
        if (!$_GLOBALS["class_map"][$type]->contains($lex[$i]["value"]))
            return carrot_and_error($lex[$i]["value"] .
            " does not exist in class " . $type, $line, $lex[$i]["char_num"]);
        $class_var_type = $_GLOBALS["class_map"][$type][$lex[$i]["value"]];
        $is_primitive = is_primitive($_GLOBALS, $class_var_type);
        $row_name = $is_primitive ? $lex[$i]["value"] : java_ref_to_mysql($class_var_type, $lex[$i]["value"]); 
        $result = mysqli_query($_GLOBALS["conn"], "SELECT " . $row_name . " FROM " . $type . " WHERE _id=" . $value);
        if (!$result) return error($_GLOBALS["MYSQL_ERROR"]);
        $parent = shape("type" => $type, "value" => $value);
        $value = mysqli_fetch_row($result)[0];
        $type = $class_var_type;
        if ($type === "String") $value = add_quotes($value);
        else if ($type === "char") $value = "'" . $value . "'";
        if ($lex[++$i]["type"] !== TokenType::DOT || $is_primitive) break;
    }
    return shape("parent" => $parent, "value" => $value, "type" => $type, "row_name" => $row_name);
}

// return sym if parsed correctly or false otherwise
function parse_type(dict $_GLOBALS, vec $lex, int &$i, string $line, $e, bool $ref) {
    // TODO: Implement default
    $token = $lex[$i++];
    switch($token["type"]) {
    case TokenType::NEW_LITERAL:
        return ($val = new_object($_GLOBALS, $lex, &$i, $line, $e, $ref)) ? $val : false;
    case TokenType::OBJ_ID:
        $sym = $_GLOBALS["symbol_table"][$token["value"]];
        if ($lex[$i]["type"] === TokenType::DOT) {
            $i++;
            if (!$sym = dereference($_GLOBALS, $sym["type"], $sym["value"], $lex, &$i, $line)) return false;
        }
        if ($sym["type"] === $e || $e === "") return $sym;
        return expected_but_found($_GLOBALS, $token, $line, $e);
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
    }
    if ($e === "") return unexpected_token($token, $line);
    switch($token["type"]) {
    case TokenType::INT_LITERAL:
        $int_val = (int)$token["value"];
        if ($e === "float") {
            if (doubleval($token["value"]) <= $_GLOBALS["FLOAT_MAX"]
                && doubleval($token["value"] >= -$_GLOBALS["FLOAT_MAX"]))
                return shape("value" => floatval($token["value"]), "type" => $e);
            return carrot_and_error("int literal is too large for type float", $line, $token["char_num"]);
        }
        if ($e === "double") {
            if ((double)$token["value"] !== INF && (double)$token["value"] !== -INF)
                return shape("value" => doubleval($token["value"]), "type" => $e);
            return carrot_and_error("int literal is too large for type double", $line, $token["char_num"]);
        }
        if ($_GLOBALS["INT_MAX"]->containsKey($e)) {
            $max = $_GLOBALS["INT_MAX"][$e];
            $too_long = false;
            if ($e === "long") {
                if ($int_val === $max && int_trim_zeros($token["value"]) !== "" . $max) $too_long = true;
                else if (-$int_val === $max + 1 && int_trim_zeros($token["value"]) !== "" . (-$max - 1)) $too_long = true;
            } else if ($int_val > $max || -$int_val > $max + 1) $too_long = true;
            return $too_long ? carrot_and_error("integer value " . $token["value"] .
                " is too large for type " . $e, $line, $token["char_num"]) 
                : shape("value" => intval($token["value"]), "type" => $e);
        }
        return expected_but_found($_GLOBALS, $token, $line, $e);
    case TokenType::FLOAT_LITERAL:
        $double_val = doubleval($token["value"]);
        if ($double_val === INF || $double_val === -INF)
            return carrot_and_error("decimal literal is too large", $line, $token["char_num"]);
        if ($e === "float") {
            if ($double_val > $_GLOBALS["FLOAT_MAX"] || $double_val < -$_GLOBALS["FLOAT_MAX"])
                return carrot_and_error("decimal literal is too large for type float", $line, $token["char_num"]);
            return shape("value" => floatval($token["value"]), "type" => $e);
        }
        if ($e === "double") return shape("value" => $double_val, "type" => $e);
        return expected_but_found($_GLOBALS, $token, $line, $e);
    case TokenType::CHAR_LITERAL:
        if ($e === "char") return shape("value" => $token["value"], "type" => $e);
        return expected_but_found($_GLOBALS, $token, $line, $e);
    case TokenType::STRING_LITERAL:
        if ($e === "String") return shape("value" => $token["value"], "type" => $e);
        return expected_but_found($_GLOBALS, $token, $line, $e);
    case TokenType::BOOLEAN_LITERAL:
        if ($e === "boolean") return shape("value" => $token["value"], "type" => $e);
        return expected_but_found($_GLOBALS, $token, $line, $e);
    case TokenType::NULL_LITERAL:
        return (is_primitive($_GLOBALS, $e))
            ? expected_but_found($_GLOBALS, $token, $line, $e)
            : shape("value" => null, "type" => $e);
    default: return expected_but_found($_GLOBALS, $token, $line, $e);
    }
}

function parse_id($_GLOBALS, $token, $line, $e, Set $accept) {
    if ($e === "") {
        return $_GLOBALS["symbol_table"][$token["value"]];
    }
    if ($accept->contains($e)) return shape(
        "value" => $_GLOBALS["symbol_table"][$token["value"]]["value"],
        "type" => $e,
    );
    return expected_but_found($_GLOBALS, $token, $line, $e);
}

function query_result_to_string(dict $_GLOBALS, $result, string $class_name): string {
    $print = vec[];
    $var_names = $_GLOBALS["class_map"][$class_name]->toKeysArray();
    $var_types = $_GLOBALS["class_map"][$class_name]->toValuesArray();
    while ($row = mysqli_fetch_row($result)) {
        $vars = dict[];
        for ($i = 1; $i < count($row); $i++)
            $vars[$var_names[$i - 1]] = get_display_val($_GLOBALS, $var_types[$i - 1], $row[$i]);
        $print[] = $vars;
    }
    return json_encode($print, JSON_PRETTY_PRINT);
}

function single_query_result_to_string(dict $_GLOBALS, $result, string $class_name): string {
    $var_names = $_GLOBALS["class_map"][$class_name]->toKeysArray();
    $var_types = $_GLOBALS["class_map"][$class_name]->toValuesArray();
    $row = mysqli_fetch_row($result);
    $vars = dict[];
    for ($i = 1; $i < count($row); $i++)
        $vars[$var_names[$i - 1]] = get_display_val($_GLOBALS, $var_types[$i - 1], $row[$i]);
    return json_encode($vars, JSON_PRETTY_PRINT);
}

function get_display_val(dict $_GLOBALS, $type, $val) {
    if ($type === "boolean") return $val && $val !== "false" ? "true" : "false";
    if ($type === "double" || $type === "float") {
        $val = str_replace("e", "E", $val);
        $val = str_replace("+", "", $val);
        return strpos($val, ".") ? $val : $val .= ".0";
    }
    if (!is_primitive($_GLOBALS, $type))
        return $val === null ? "null" : $type . "@" . $val;
    return $val;
}

function is_primitive(dict $_GLOBALS, $type) {
    if ($type instanceof ListType) return false;
    return $_GLOBALS["PRIM"]->contains($type);
}

function remove_quotes(string $val) : string {
    return substr($val, 1, strlen($val) - 2);
}

function add_quotes(string $val) : string {
    return "\"" . $val . "\"";
}

function r_paren_semi(dict $_GLOBALS, $lex, int $i, string $line): boolean {
    return must_match($_GLOBALS, $lex, $i, $line, TokenType::R_PAREN) && must_end($lex, ++$i, $line);
}

function must_match_f(dict $_GLOBALS, vec $lex, int $i, vec $file_vec, TokenType $e) {
    if ($lex[$i]["type"] !== $e)
        return expected_but_found($_GLOBALS, $lex[$i],
            $file_vec[$lex[$i]["line_num"]], $_GLOBALS["TOKEN_NAME_MAP"][$e]);
    return $lex[$i]["value"];
}

// Returns value of matched type on success or false on failure
function must_match(dict $_GLOBALS, vec $lex, int $i, string $line, TokenType $e) {
    if ($lex[$i]["type"] !== $e)
        return expected_but_found($_GLOBALS, $lex[$i], $line, $_GLOBALS["TOKEN_NAME_MAP"][$e]);
    return $lex[$i]["value"];
}

// Same as must match function with unexpected token error message instead of expected but found
function must_match_unexpected(vec $lex, int $i, string $line, TokenType $e) {
    if ($lex[$i]["type"] !== $e)
        return unexpected_token($lex[$i], $line);
    return $lex[$i]["value"];
}

function unexpected_token($token, string $line): boolean {
    return carrot_and_error("unexpected token: " . $token["value"], $line, $token["char_num"]);
}

function expected_but_found_literal($token, string $line, string $e): boolean {
    return carrot_and_error("expected " . add_quotes($e) . " but found "
        . add_quotes($token["value"]), $line, $token["char_num"]);
}

function expected_but_found(dict $_GLOBALS, $token, string $line, $e): boolean {
    if ($e instanceof ListType) $e = "List";
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

function get_sql_column(dict $_GLOBALS, $type, string $name): shape("type" => string, "name" => string) {
    return is_primitive($_GLOBALS, $type) 
        ? shape("type" => $_GLOBALS["TO_SQL_TYPE_MAP"][$type], "name" => $name)
        : shape("type" => "int", "name" => java_ref_to_mysql($type, $name));
}

function mysql_ref_to_java(string $name) {
    if ($name[1] === "L") {
        return mysql_list_to_java($name);
    }
    $table_name_length = $name[1];
    for ($i = 2; is_numeric($name[$i]); $i++) $table_name_length .= $name[$i];
    return shape(
        "type" => substr($name, 1 + strlen($table_name_length), $table_name_length),
        "name" => substr($name, 1 + strlen($table_name_length) + $table_name_length)
    );
}

function mysql_list_to_java(string $name) {
    $list_dim = $name[2];
    for ($i = 3; $name[$i] !== "_"; $i++) $list_dim .= $name[$i];
    $name_and_subtype = mysql_ref_to_java(substr($name, 2 + strlen($list_dim)));
    return shape(
        "type" => new ListType($name_and_subtype["type"], intval($list_dim)),
        "name" => $name_and_subtype["name"]
    );
}

function list_type_to_java(ListType $l): string {
    $ret = vec["List<"];
    for ($i = 1; $i < $l->dim; $i++) $ret[] = "List<";
    $ret[] = $l->subtype;
    $ret[] = str_repeat(">", $l->dim);
    return implode($ret);
}

function java_ref_to_mysql($type, string $name): string {
    return ($type instanceof ListType)
        ? "_L" . $type->dim . "_" . strlen($type->subtype) . $type->subtype . $name 
        : "_" . strlen($type) . $type . $name;
}


function success($print): boolean {
    echo $print, "\n";
    return true;
}

function fail(string $print): boolean {
    echo $print, "\n";
    return false;
}

function carrot_and_error(string $message, string $line, int $index): boolean {
    echo $line, "\n", str_repeat(" ", $index), "^\n";
    return error($message);
}

function error(string $message): boolean {
    return fail("Error: " . $message);
}

function found_location($file_path, $line_num): boolean {
    echo "Found at ", $file_path, ":", $line_num, "\n";
    return false;
}

// Doesn't seem like there's a way to do this better than O(n);
function map_replace(Map $old_map, string $old_key, string $new_key): Map {
    $new_map = new Map();
    foreach ($old_map->toKeysArray() as $key) {
        if ($key === $old_key) $new_map[$new_key] = $old_map[$key];
        else $new_map[$key] = $old_map[$key];
    }
    return $new_map;
}

function class_map_print_format(Map $old_map) {
    $new_map = new Map();
    foreach ($old_map->toKeysArray() as $key) $new_map[$key] = map_replace_list_types($old_map[$key]);
    return $new_map;
}

function map_replace_list_types(Map $old_map) {
    $new_map = new Map();
    foreach ($old_map->toKeysArray() as $key) {
        $val = $old_map[$key];
        if ($val instanceof ListType) $val = list_type_to_java($val);
        $new_map[$key] = $val;
    }
    return $new_map;
}

function map_add_lexo(Map $old_map, string $new_key, $new_value) {
    $new_map = new Map();
    $not_added = true;
    foreach ($old_map->toKeysArray() as $key) {
        if ($not_added && $key > $new_key) {
            $not_added = false;
            $new_map[$new_key] = $new_value;
        }
        $new_map[$key] = $old_map[$key];
    }
    if ($not_added) $new_map[$new_key] = $new_value;
    return $new_map;
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
        if (!$lex_result = lex_line($_GLOBALS, $ret, $line, $i, false, $comment)) return found_location($file_path, $i);
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
            if ($line[$i] !== "*" || $i + 1 >= strlen($line) || $line[$i + 1] !== "/") continue;
            $i++;
            $comment = false;
            continue;
        }
        if ($line[$i] === " ") continue;

        // Begins with letter (keyword, boolean literal, ID)
        if (ctype_alpha($line[$i])) {
            $start = $i;
            for (; $i < strlen($line) - 1; $i++) {
                if (!ctype_alpha($line[$i + 1]) && !is_numeric($line[$i + 1]) && $line[$i + 1] !== "_") break;
            }
            $value = substr($line, $start, $i - $start + 1);
            $type = TokenType::ID;
            if ($_GLOBALS["KEYWORDS"]->containsKey($value)) $type = $_GLOBALS["KEYWORDS"][$value];
            else if ($_GLOBALS["class_map"]->containsKey($value)) $type = TokenType::CLASS_ID;
            else if ($_GLOBALS["symbol_table"]->containsKey($value)) {
                $j_type = $_GLOBALS["symbol_table"][$value]["type"];
                if ($j_type instanceof ListType) $type = TokenType::LIST_ID;
                else $type = $_GLOBALS["JAVA_TYPE_TO_ID"]->containsKey($j_type) ?
                    $_GLOBALS["JAVA_TYPE_TO_ID"][$j_type] :
                    TokenType::OBJ_ID;
            }
            $ret[] = shape("type" => $type, "value" => $value, "char_num" => $start, "line_num" => $line_num);

        // int or float literal
        } else if (is_numeric($line[$i])) {
            if (!$result = lex_number($line, &$i, false)) return false;
            $ret[] = $result;

        // String literal
        } else if ($line[$i] === "\"") {
            $start = $i;
            for ($i++; $i < strlen($line); $i++) {
                if ($line[$i] === "\"" && $line[$i - 1] !== "\\") {
                    $ret[] = shape(
                        "type" => TokenType::STRING_LITERAL,
                        "value" => substr($line, $start, $i - $start + 1),
                        "char_num" => $start,
                        "line_num" => $line_num
                    );
                    break;
                }
            }
            if ($i === strlen($line)) return carrot_and_error("unclosed quotation", $line, $start);

        // Char literal
        } else if ($line[$i] === "'") {
            if ($i + 1 === strlen($line)) return carrot_and_error("unclosed char literal", $line, $i);
            $start = $i;
            $c = $line[++$i];
            if ($c === "'") return carrot_and_error("empty char literal", $line, $start);
            if ($c === "\\") { // TODO: Add support for unicode escape sequences
                if ($i + 2 >= strlen($line)) return carrot_and_error("unclosed char literal", $line, $start);
                if ($line[$i += 2] !== "'" || !$_GLOBALS["ESCAPE_CHARS"]->contains($line[$i - 1]))
                    return carrot_and_error("invalid char literal", $line, $start);
            } else {
                if ($i + 1 >= strlen($line)) return carrot_and_error("unclosed char literal", $line, $start);
                if ($line[++$i] !== "'") return carrot_and_error("invalid char literal", $line, $start);
            }
            $ret[] = shape(
                "type" => TokenType::CHAR_LITERAL,
                "value" => substr($line, $start, $i - $start + 1),
                "char_num" => $start,
                "line_num" => $line_num
            );

        // DOT or float
        } else if ($line[$i] === ".") {
            if ($i + 1 === strlen($line) || !(is_numeric($line[$i + 1]))) {
                $ret[] = shape("type" => TokenType::DOT, "value" => ".", "char_num" => $i, "line_num" => $line_num);
            } else {
                if (!$result = lex_number($line, &$i, true)) return false;
                $ret[] = $result;
            }

        // Negative number;
        } else if ($line[$i] === "-") {
            if ($i + 1 === strlen($line) || !is_numeric($line[$i + 1]))
                return carrot_and_error("unrecognized symbol: -", $line, $i);
            if (!$result = lex_number($line, &$i, false)) return false;
            $ret[] = $result;

        // Underscore error
        } else if ($line[$i] === "_") {
            return carrot_and_error($_GLOBALS["PROJECT_NAME"] . " identifiers may not begin with an underscore", $line, $i);

        // Comments
        } else if ($line[$i] === "/") {
            if ($i + 1 === strlen($line) || ($line[++$i] !== "/" && $line[$i] !== "*"))
               return carrot_and_error("unrecognized symbol: /", $line, $i - 1);
            if ($line[$i] === "/") break; // Rest of line is comment
            $comment = true; // /* comment has begun

        // Symbols 
        } else {
            if ($i + 1 === strlen($line) || !$_GLOBALS["SYMBOLS"]->containsKey($line[$i] . $line[$i + 1])) {
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
        if ($line[$i + 1] === ".") {
            if ($decimal || $e) $invalid = true;
            $decimal = true;
        } else if ($line[$i + 1] === 'e' || $line[$i + 1] === "E") {
            if ($e || ++$i + 1 === strlen($line)) $invalid = true;
            else if (!is_numeric($line[$i + 1])) {
                if (($line[$i + 1] !== "-" && $line[$i + 1] !== "+") ||
                    ++$i + 1 === strlen($line) || !is_numeric($line[$i + 1])) $invalid = true;
            }
            $e = true;
        } else if (!is_numeric($line[$i + 1])) break;
        if ($invalid) return carrot_and_error("malformed number", $line, $start);
    }
    return shape(
        "type" => $decimal ? TokenType::FLOAT_LITERAL : TokenType::INT_LITERAL, 
        "value" => substr($line, $start, $i - $start + 1),
        "char_num" => $start
    );
}

function int_trim_zeros(string $val): string {
    $i = 0;
    $prefix = "";
    if ($val[0] === "-") {
        $i = 1;
        $prefix = '-';
    }
    for (; $i < strlen($val) - 1; $i++)
        if ($val[$i] !== "0") break;
    return $prefix . substr($val, $i);
}

function collect_list_garbo(dict $_GLOBALS) {
    $result = mysqli_query($_GLOBALS["conn"], "SELECT ref_count, location FROM _list");
    if (!$result) return error($_GLOBALS["MYSQL_ERROR"]);
    while ($row = mysqli_fetch_assoc($result)) {
        if ($row["ref_count"] === 0) delete_list($_GLOBALS, $row["location"]);
    }
    return true;
}

function delete_list(dict $_GLOBALS, string $location) {
    if (!mysqli_query($_GLOBALS["conn"], "DELETE FROM _list WHERE location=\"" . $location . "\"")) {
        return error($_GLOBALS["MYSQL_ERROR"]);
    }
    if (!mysqli_query($_GLOBALS["conn"], "DROP TABLE " . $location)) return error($_GLOBALS["MYSQL_ERROR"]);
    return true;
}

// For debugging
function destroy_all_lists(dict $_GLOBALS) {
    $result = mysqli_query($_GLOBALS["conn"], "SHOW TABLES");
    while ($row = mysqli_fetch_row($result)) {
        if ($row[0][0] === "_" && $row[0] !== "_list") // TODO: store dict of admin tables starting with _
            mysqli_query($_GLOBALS["conn"], "DROP TABLE " . $row[0]);
    }
    mysqli_query($_GLOBALS["conn"], "DELETE FROM _list");
}

