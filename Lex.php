<?hh

// TODO: Decide whether to break up KEYWORD into separate tokens or combine SYMBOL into one token
enum TokenType: int {
    ID = 0;
    INT_LITERAL = 1;
    FLOAT_LITERAL = 2;
    BOOLEAN_LITERAL = 3;
    STRING_LITERAL = 4;
    CHAR_LITERAL = 5;
    LT = 6;
    GT = 7;
    LTE = 8;
    GTE = 9;
    ASSIGN = 10;
    EQUAL = 11;
    AND = 12;
    OR = 13;
    LPAREN = 14;
    RPAREN = 15;
    LBRACKET = 16;
    RBRACKET = 17;
    LCURLY = 18;
    RCURLY = 19;
    SEMI = 20;
    DOT = 21;
    KEYWORD = 22;
}

/*
enum CommandName: int {
    VIEW_CLASSES = 0;
    VIEW_CLASS = 1;
    VIEW_SYMBOLS = 2;
}

$METHOD_NAME_MAP = dict[
    "viewClasses" => CommandName::VIEW_CLASSES,
    "viewClass" => CommandName::VIEW_CLASS,
    "viewSymbols" => CommandName::VIEW_SYMBOLS,
];
*/

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
function lex_command(string $line): vec<shape("type" => TokenType, "value" => string)> {
    $ESCAPE_CHARS = new Set(vec["b", "t", "0", "n", "r", "\"", "'", "\\"]);
    $KEYWORDS = new Set(vec[
       "viewClasses",
        "viewClass",
        "viewSymbol",
        "class",
        "new"
    ]);

    $ret = vec[];
    // For loop starts on beginning of new token attempt
    for ($i = 0; $i < strlen($line); $i++) {
        if ($line[$i] == " ") continue;

        // Begins with letter (keyword or ID)
        if (ctype_alpha($line[$i])) {
            $start = $i;
            for (; $i < strlen($line) - 1; $i++) {
                if (!ctype_alpha($line[$i + 1]) && !is_numeric($line[$i + 1]) && $line[$i + 1] != "_") break;
            }
            $value = substr($line, $start, $i - $start + 1);
            // TODO: Might need to change to lexing to specific keyword tokentype
            $type = ($KEYWORDS->contains($value)) ? TokenType::KEYWORD : TokenType::ID; 
            $ret[] = shape("type" => $type, "value" => $value);

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
                        "value" => substr($line, $start, $i - $start + 1)
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
            $ret[] = shape("type" => TokenType::CHAR_LITERAL, "value" => substr($line, $start, $i - $start + 1));

        // DOT or float
        } else if ($line[$i] == ".") {
            if ($i + 1 == strlen($line) || !(is_numeric($line[$i + 1]))) {
                $ret[] = shape("type" => TokenType::DOT, "value" => ".");
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

        // Unrecognized
        } else return carrot_and_error("unrecognized symbol: " . $line[$i], $line, $i); 
    }
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
        "value" => substr($line, $start, $i - $start + 1)
    );
}

