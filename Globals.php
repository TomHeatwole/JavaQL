<?hh 

class QueryResult {
    public int $q_num;

    public function __construct(int $q_num) {
        $this->q_num = $q_num;
    }
}

class ListType {
    public string $subtype;
    public int $dim;

    public function __construct ($subtype, int $dim) {
        if ($dim === 0) {
            if ($subtype instanceof ListType) {
                $this->dim = $subtype->dim + 1;
                $this->subtype = $subtype->subtype;
            } else {
                $this->dim = 1;
                $this->subtype = $subtype;
            }
        } else {
            $this->dim = $dim;
            $this->subtype = $subtype;
        }
    }

    public function __toString() {
        return implode(vec[str_repeat("List<", $this->dim), $this->subtype, str_repeat(">", $this->dim)]);
    }

    public function inner() {
        return $this->dim > 1 ? new ListType($this->subtype, $this->dim - 1) : $this->subtype;
    }
}

enum TokenType: int {
    INT_LITERAL = 0;
    FLOAT_LITERAL = 1;
    BOOLEAN_LITERAL = 2;
    STRING_LITERAL = 3;
    CHAR_LITERAL = 4;
    NULL_LITERAL = 5;
    NEW_LITERAL = 6;
    CLASS_LITERAL = 7;
    ID = 8;
    FLOAT_ID = 9;
    DOUBLE_ID = 10;
    BOOLEAN_ID = 11;
    STRING_ID = 12;
    BYTE_ID = 13;
    SHORT_ID = 14;
    INT_ID = 15;
    LONG_ID = 16;
    CLASS_ID = 17;
    CHAR_ID = 18;
    OBJ_ID = 19;
    LIST_ID = 20;
    J_SHORT = 21;
    J_BYTE = 22;
    J_INT = 23;
    J_LONG = 24;
    J_FLOAT = 25;
    J_DOUBLE = 26;
    J_CHAR = 27;
    J_BOOLEAN = 28;
    J_STRING = 29;
    J_LIST = 30;
    LT = 31;
    GT = 32;
    LTE = 33;
    GTE = 34;
    ASSIGN = 35;
    EQUAL = 36;
    AND_OP = 37;
    OR_OP = 38;
    L_PAREN = 39;
    R_PAREN = 40;
    L_BRACKIE = 41;
    R_BRACKIE = 42;
    L_CURLY = 43;
    R_CURLY = 44;
    SEMI = 45;
    DOT = 46;
    COMMA = 47;
    EOL = 48;
    EOF = 49;
    M_GET_ALL_DESC = 50;
    M_GET_CLASS_NAMES = 51;
    M_GET_DESC = 52;
    M_GET_ALL_OBJECTS = 53;
    M_GET_OBJECTS = 54;
    M_BUILD = 55;
    M_BUILD_ALL = 56;
    M_RENAME = 57;
    M_GET_LOCAL_VARIABLES = 58;
    M_GET_VARIABLES = 59;
    M_GET_VARIABLES_RECURSIVE = 60;
    M_DELETE_ALL_OBJECTS = 61;
    M_DELETE_CLASS = 62;
    M_CLEAR_LOCAL_VARIABLES = 64;
}

$_GLOBALS = dict[
    "PROJECT_NAME" => "JavaQL",
    "PROJECT_URL" => "https://github.com/TomHeatwole/JavaQL/",
    "CLASSES_DIR" => "classes/",
    "MYSQL_ERROR" => "an unknown MySQL error occurred",
    // string "UNIT_TEST" must exist in GLOBALS for  unit tests to run properly
    "UNIT_TEST" => "qd082j3tn0g88an94uq103333333qt8aas",
    "FROM_SQL_TYPE_MAP" => dict[
        "varchar(255)" => "String",
        "tinyint(4)" => "byte",
        "smallint(6)" => "short",
        "int(11)" => "int",
        "bigint(20)" => "long",
        "char(1)" => "char",
        "tinyint(1)" => "boolean",
        "float" => "float",
        "double" => "double",
    ],
    "TO_SQL_TYPE_MAP" => dict[
        "String" => "varchar(255)",
        "byte" => "tinyint(4)",
        "short" => "smallint(6)",
        "int" => "int(11)",
        "long" => "bigint(20)",
        "char" => "char(1)",
        "boolean" => "tinyint(1)",
        "float" => "float",
        "double" => "double",
    ],
    "TOKEN_NAME_MAP" => new Map(dict[
        TokenType::INT_LITERAL => "int",
        TokenType::FLOAT_LITERAL => "float",
        TokenType::BOOLEAN_LITERAL => "boolean",
        TokenType::STRING_LITERAL => "String",
        TokenType::CHAR_LITERAL => "char",
        TokenType::NULL_LITERAL => "\"null\"",
        TokenType::NEW_LITERAL => "\"new\"",
        TokenType::CLASS_LITERAL => "\"class\"",
        TokenType::CLASS_ID => "class name",
        TokenType::ID => "unknown identifier",
        TokenType::BYTE_ID => "byte variable",
        TokenType::SHORT_ID => "short variable",
        TokenType::INT_ID => "int variable",
        TokenType::LONG_ID => "long variable",
        TokenType::FLOAT_ID => "float variable",
        TokenType::DOUBLE_ID => "double variable",
        TokenType::BOOLEAN_ID => "boolean variable",
        TokenType::STRING_ID => "String variable",
        TokenType::CHAR_ID => "char variable",
        TokenType::OBJ_ID => "Object variable",
        TokenType::LIST_ID => "List variable",
        TokenType::EOF => "end of file",
        TokenType::EOL => "end of line",
        TokenType::LT => "<",
        TokenType::GT => ">",
        TokenType::LTE => "<=",
        TokenType::GTE => ">=",
        TokenType::ASSIGN => "=",
        TokenType::EQUAL => "==",
        TokenType::AND_OP => "&&",
        TokenType::OR_OP => "||",
        TokenType::L_PAREN => "(",
        TokenType::R_PAREN => ")",
        TokenType::L_BRACKIE => "[",
        TokenType::R_BRACKIE => "]",
        TokenType::L_CURLY => "{",
        TokenType::R_CURLY => "}",
        TokenType::SEMI => ";",
        TokenType::DOT => ".",
        TokenType::COMMA => ",",
        TOkenType::M_CLEAR_LOCAL_VARIABLES => "method name",
        TokenType::M_DELETE_CLASS => "method name",
        TokenType::M_DELETE_ALL_OBJECTS => "method name",
        TokenType::M_GET_ALL_DESC => "method name",
        TokenType::M_GET_CLASS_NAMES => "method name",
        TokenType::M_GET_DESC => "method name",
        TokenType::M_GET_ALL_OBJECTS => "method name",
        TokenType::M_GET_OBJECTS => "method name",
        TokenType::M_BUILD => "method name",
        TokenType::M_BUILD_ALL => "method name",
        TokenType::M_RENAME => "method name",
        TokenType::J_BYTE => "byte",
        TokenType::J_SHORT => "short",
        TokenType::J_INT => "int",
        TokenType::J_LONG => "long",
        TokenType::J_FLOAT => "float",
        TokenType::J_DOUBLE => "double",
        TokenType::J_BOOLEAN => "boolean",
        TokenType::J_STRING => "String",
        TokenType::J_CHAR => "char",
        TokenType::J_LIST => "List",
    ]),
    "JAVA_TYPES" => new Map(dict[
        TokenType::CLASS_ID => "class",
        TokenType::J_BYTE => "byte",
        TokenType::J_SHORT => "short",
        TokenType::J_INT => "int",
        TokenType::J_LONG => "long",
        TokenType::J_FLOAT => "float",
        TokenType::J_DOUBLE => "double",
        TokenType::J_BOOLEAN => "boolean",
        TokenType::J_STRING => "String",
        TokenType::J_CHAR => "char",
        TokenType::J_LIST => "List",
    ]),
    "DEFAULTS" => new Map(dict[
        "class" => null,
        "List" => null,
        "byte" => 0,
        "short" => 0,
        "int" => 0,
        "long" => 0,
        "float" => 0,
        "double" => 0,
        "boolean" => false,
        "String" => "\"\"",
        "char" => "''",
    ]),
    "VAR_IDS" => new Set(vec[
        TokenType::FLOAT_ID,
        TokenType::DOUBLE_ID,
        TokenType::BOOLEAN_ID,
        TokenType::STRING_ID, 
        TokenType::BYTE_ID,
        TokenType::SHORT_ID,
        TokenType::INT_ID,
        TokenType::LONG_ID,
        TokenType::CHAR_ID,
        TokenType::OBJ_ID,
        TokenType::LIST_ID,
    ]),
    "ALL_IDS" => new Set(vec[
        TokenType::FLOAT_ID,
        TokenType::DOUBLE_ID,
        TokenType::BOOLEAN_ID,
        TokenType::STRING_ID,
        TokenType::BYTE_ID,
        TokenType::SHORT_ID,
        TokenType::INT_ID,
        TokenType::LONG_ID,
        TokenType::CHAR_ID,
        TokenType::OBJ_ID,
        TokenType::LIST_ID,
        TokenType::CLASS_ID,
        TokenType::ID,
    ]),
    "JAVA_TYPE_TO_ID" => new Map(dict[
        "float" => TokenType::FLOAT_ID,
        "double" => TokenType::DOUBLE_ID,
        "boolean" => TokenType::BOOLEAN_ID,
        "String" => TokenType::STRING_ID,
        "byte" => TokenType::BYTE_ID,
        "short" => TokenType::SHORT_ID,
        "int" => TokenType::INT_ID,
        "long" => TokenType::LONG_ID,
        "char" =>TokenType::CHAR_ID,
    ]),
    "ESCAPE_CHARS" => new Set(vec["b", "t", "0", "n", "r", "\"", "'", "\\"]),
    "KEYWORDS" => new Map(dict[
        "clearLocalVariables" => TokenType::M_CLEAR_LOCAL_VARIABLES,
        "deleteClass" => TokenTYpe::M_DELETE_CLASS,
        "deleteAllObjects" => TokenType::M_DELETE_ALL_OBJECTS,
        "getAllDesc" => TokenType::M_GET_ALL_DESC,
        "getClassNames" => TokenType::M_GET_CLASS_NAMES,
        "getDesc" => TokenType::M_GET_DESC,
        "getAllObjects" => TokenType::M_GET_ALL_OBJECTS,
        "getObjects" => TokenType::M_GET_OBJECTS,
        "rename" => TokenType::M_RENAME,
        "getLocalVariables" => TokenType::M_GET_LOCAL_VARIABLES,
        "getVariables" => TokenType::M_GET_VARIABLES,
        "getVariablesRecursive" => TokenType::M_GET_VARIABLES_RECURSIVE,
        "build" => TokenType::M_BUILD,
        "buildAll" => TokenType::M_BUILD_ALL,
        "class" => TokenType::CLASS_LITERAL,
        "new" => TokenType::NEW_LITERAL,
        "null" => TokenType::NULL_LITERAL,
        "true" => TokenType::BOOLEAN_LITERAL,
        "false" => TokenType::BOOLEAN_LITERAL,
        "byte" => TokenType::J_BYTE,
        "short" => TokenType::J_SHORT,
        "int" => TokenType::J_INT,
        "long" => TokenType::J_LONG,
        "char" => TokenType::J_CHAR,
        "boolean" => TokenType::J_BOOLEAN,
        "float" => TokenType::J_FLOAT,
        "double" => TokenType::J_DOUBLE,
        "String" => TokenType::J_STRING,
        "List" => TokenType::J_LIST,
    ]),
    "SYMBOLS" => new Map(dict[
        "<" => TokenType::LT,
        ">" => TokenType::GT,
        "<=" => TokenType::LTE,
        ">=" => TokenType::GTE,
        "=" => TokenType::ASSIGN,
        "==" => TokenType::EQUAL,
        "&&" => TokenType::AND_OP,
        "||" => TokenType::OR_OP,
        "(" => TokenType::L_PAREN,
        ")" => TokenType::R_PAREN,
        "[" => TokenType::L_BRACKIE,
        "]" => TokenType::R_BRACKIE,
        "{" => TokenType::L_CURLY,
        "}" => TokenType::R_CURLY,
        ";" => TokenType::SEMI,
        "." => TokenType::DOT,
        "," => TokenType::COMMA,
    ]),
    "ALL_LITERALS" => new Set(vec[
        TokenType::INT_LITERAL,
        TokenType::FLOAT_LITERAL,
        TokenType::BOOLEAN_LITERAL,
        TokenType::STRING_LITERAL,
        TokenType::CHAR_LITERAL,
        TokenType::NULL_LITERAL
    ]),
    "PRIM" => new Set(vec["short", "byte", "int", "long", "float", "double", "char", "boolean", "String"]), // JavaQL primitives
    "INT_MAX" => new Map(dict[
        "byte" => 127,
        "short" => 32767,
        "int" => 2147483647,
        "long" => 9223372036854775807,
    ]),
    "FLOAT_MAX" => 3.402823466E29,
];

