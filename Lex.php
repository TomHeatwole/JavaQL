<?hh

include("Globals.php");

// TODO: Decide whether to break up KEYWORD into separate tokens or combine SYMBOL into one token
enum TokenType: int {
    INT_LITERAL = 0;
    FLOAT_LITERAL = 1;
    BOOLEAN_LITERAL = 2;
    STRING_LITERAL = 3;
    CHAR_LITERAL = 4;
    ID = 5; // new ID and class variables
    INT_ID = 6;
    FLOAT_ID = 7;
    BOOLEAN_ID = 8;
    STRING_ID = 9;
    CHAR_ID = 10;
    CLASS_ID = 11;
    SYMBOL = 12;
    KEYWORD = 13;
    EOF = 14;
}

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
    string $line,
    Map $class_map
): vec<shape("type" => TokenType, "value" => string, "char_num" => int)> {
    $ESCAPE_CHARS = new Set(vec["b", "t", "0", "n", "r", "\"", "'", "\\"]);
    $KEYWORDS = new Set(vec[
       "viewClasses",
        "viewClass",
        "viewSymbol",
        "class",
        "new"
    ]);
    $SYMBOLS = new Set(vec["<", ">", "<=", ">=", "=", "==", "&&", "||", "(", ")", "[", "]", "{", "}", ";", "."]);

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
            if ($KEYWORDS->contains($value)) $type = TokenType::KEYWORD;
            else if ($value == "true" || $value == "false") $type = TokenType::BOOLEAN_LITERAL;
            else if ($class_map->containsKey($value)) $type = TokenType::CLASS_ID;
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
                if ($line[$i += 2] != "'" || !$ESCAPE_CHARS->contains($line[$i - 1]))
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
                $ret[] = shape("type" => TokenType::DOT, "value" => ".", "char_num" => $i);
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
            if ($i + 1 == strlen($line) || !$SYMBOLS->contains($line[$i] . $line[$i + 1])) {
                if ($SYMBOLS->contains($line[$i]))
                    $ret[] = shape("type" => TokenType::SYMBOL, "value" => $line[$i], "char_num" => $i);
                else return carrot_and_error("unrecognized symbol: " . $line[$i], $line, $i);
            } else $ret[] = shape("type" => TokenType::SYMBOL, "value" => $line[$i] . $line[++$i], "char_num" => $i - 1);
        } 
    }
    $ret[] = shape("type" => TokenType::EOF, "value" => null);
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

