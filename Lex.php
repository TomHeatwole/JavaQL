<?hh

include("Globals.php");

function carrot_pointer(string $line, int $index) {
    echo $line, "\n", str_repeat(" ", $index), "^\n";
}

function lex_error(string $message): vec {
    echo "Error: ", $message, "\n";
    return vec[];
}

function carrot_and_error(string $message, string $line, int $index): vec {
    carrot_pointer($line, $index);
    return lex_error($message);
}

// For CLI commands
// No need to worry about comments here
function lex_command(
    dict $_GLOBALS,
    string $line,
    Map $sym_table,
): vec<shape("type" => TokenType, "value" => string, "char_num" => int)> {
    $ret = vec[];
    // For loop starts on beginning of new token attempt
    for ($i = 0; $i < strlen($line); $i++) {
        if ($line[$i] == " ") continue;

        // Begins with letter (keyword, boolean literal, ID)
        if (ctype_alpha($line[$i])) {
            $start = $i;
            for (; $i < strlen($line) - 1; $i++) {
                if (!ctype_alpha($line[$i + 1]) && !is_numeric($line[$i + 1]) && $line[$i + 1] != "_") break;
            }
            $value = substr($line, $start, $i - $start + 1);
            // TODO: Might need to change to lexing to specific keyword tokentype
            $type = TokenType::ID;
            if ($_GLOBALS["KEYWORDS"]->containsKey($value)) $type = $_GLOBALS["KEYWORDS"][$value];
            else if ($_GLOBALS["CLASS_MAP"]->containsKey($value)) $type = TokenType::CLASS_ID;
            // else symbol table
            $ret[] = shape("type" => $type, "value" => $value, "char_num" => $start);

        // int or float literal
        } else if (is_numeric($line[$i])) {
            $result = lex_number($line, &$i, false);
            if ($i == -1) return vec[];
            $ret[] = $result;

        // String literal
        } else if ($line[$i] == "\"") {
            $start = $i;
            for ($i++; $i < strlen($line); $i++) {
                if ($line[$i] == "\"" && $line[$i - 1] != "\\") {
                    $ret[] = shape(
                        "type" => TokenType::STRING_LITERAL,
                        "value" => substr($line, $start, $i - $start + 1),
                        "char_num" => $start
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
                "char_num" => $start
            );

        // DOT or float
        } else if ($line[$i] == ".") {
            if ($i + 1 == strlen($line) || !(is_numeric($line[$i + 1]))) {
                $ret[] = shape("type" => TokenType::SYMBOL, "value" => ".", "char_num" => $i);
            } else {
                $result = lex_number($line, &$i, true);
                if ($i == -1) return vec[];
                $ret[] = $result;
            }

        // Negative number; TODO: Figure out if there are other interpretations of '-'
        } else if ($line[$i] == "-") {
            if ($i + 1 == strlen($line) || !is_numeric($line[$i + 1]))
                return carrot_and_error("unrecognized symbol: -", $line, $i);
            $result = lex_number($line, &$i, false);
            if ($i == -1) return vec[];
            $ret[] = $result;

        // Underscore error
        } else if ($line[$i] == "_") {
            return carrot_and_error("JavaQL identifiers may not begin with an unerscore", $line, $i);

        // Symbols 
        } else {
            if ($i + 1 == strlen($line) || !$_GLOBALS["SYMBOLS"]->containsKey($line[$i] . $line[$i + 1])) {
                if ($_GLOBALS["SYMBOLS"]->containsKey($line[$i]))
                    $ret[] = shape("type" => $_GLOBALS["SYMBOLS"][$line[$i]], "value" => $line[$i], "char_num" => $i);
                else return carrot_and_error("unrecognized symbol: " . $line[$i], $line, $i);
            } else $ret[] = shape(
                "type" => $_GLOBALS["SYMBOLS"][$line[$i] . $line[$i + 1]],
                "value" => $line[$i] . $line[++$i], "char_num" => $i - 1
            );
        } 
    }
    $ret[] = shape("type" => TokenType::EOF, "value" => "End of line", "char_num" => strlen($line));
    return $ret;
}

function lex_number(string $line, int &$i, bool $decimal): shape("type" => TokenType, "value" => string) {
    $start = $i;
    for (; $i < strlen($line) - 1; $i++) {
        if ($line[$i + 1] == ".") {
            if ($decimal) {
                carrot_and_error("invalid decimal", $line, $i + 1);
                $i = -1;
                return shape();
            }
            $decimal = true;
        } else if (!is_numeric($line[$i + 1])) break;
    }
    return shape(
        "type" => $decimal ? TokenType::FLOAT_LITERAL : TokenType::INT_LITERAL, 
        "value" => substr($line, $start, $i - $start + 1),
        "char_num" => $start
    );
}

