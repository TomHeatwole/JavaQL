<?hh

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
    KEYWORD = 21;
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

/*
$KEYWORDS = keyset[
   "viewClasses",
    "viewClass",
    "viewSymbol",
    "class",
    "new"
];
 */

function carrot_pointer(string $line, int $index) { // TODO: figure out if this is the most efficient way
    echo $line, "\n", str_repeat(" ", $index), "^\n";
}

function lex_error(string $message): vec {
    echo "Error: ", $message, "\n";
    return vec[];
}

// TODO: carrot_and_error function

// For CLI commands
// No need to worry about comments here
function lex_command(string $line): vec<shape("type" => TokenType, "value" => string)> {
    $ESCAPE_CHARS = new Set(vec["b", "t", "0", "n", "r", "\"", "'", "\\"]);

    $ret = vec[];
    // For loop starts on beginning of new token attempt
    for ($i = 0; $i < strlen($line); $i++) {
        if ($line[$i] == " ") continue;
        if (ctype_alpha($line[$i])) {
            // TODO: if start with letter
        } else if ($line[$i] == "\"") {
            $start = $i;
            for ($i++; $i < strlen($line); $i++) {
                if ($line[$i] == "\"" && $line[$i - 1] != "\\") {
                    $ret[] = shape("type" => TokenType::STRING_LITERAL, "value" => substr($line, $start, $i - $start + 1));
                    break;
                }
            }
            if ($i == strlen($line)) {
                carrot_pointer($line, $start);
                return lex_error("unclosed quotation");
            }
        } else if ($line[$i] == "'") {
            if ($i + 1 == strlen($line)) {
                carrot_pointer($line, $i);
                return lex_error("unclosed char literal");
            }
            $start = $i;
            $c = $line[++$i];
            if ($c == "'") {
                carrot_pointer($line, $start);
                return lex_error("empry char literal");
            }
            if ($c == "\\") { // TODO: Add support for unicode escape sequences
                if ($i + 2 >= strlen($line)) {
                    carrot_pointer($line, $start);
                    return lex_error("unclosed char literal");
                }
                if ($line[$i += 2] != "'" || !$ESCAPE_CHARS->contains($line[$i - 1])) {
                    carrot_pointer($line, $start);
                    return lex_error("invalid char literal");
                }
            } else {
                if ($i + 1 >= strlen($line)) {
                    carrot_pointer($line, $start);
                    return lex_error("unclosed char literal");
                }
                if ($line[++$i] != "'") {
                    carrot_pointer($line, $start);
                    return lex_error("invalid char literal");
                }
            }
            $ret[] = shape("type" => TokenType::CHAR_LITERAL, "value" => substr($line, $start, $i - $start + 1));
        } else {
            carrot_pointer($line, $i);
            return lex_error("unrecognized symbol: " . $line[$i]);
        }
    }
    return $ret;
}

