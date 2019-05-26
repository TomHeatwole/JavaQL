<?hh 

enum TokenType: int {
    INT_LITERAL = 0;
    FLOAT_LITERAL = 1;
    BOOLEAN_LITERAL = 2;
    STRING_LITERAL = 3;
    CHAR_LITERAL = 4;
    NULL_LITERAL = 5;
    NEW_LITERAL = 6;
    ID = 7;
    FLOAT_ID = 8;
    DOUBLE_ID = 9;
    BOOLEAN_ID = 10;
    STRING_ID = 11;
    BYTE_ID = 12;
    SHORT_ID = 13;
    INT_ID = 14;
    LONG_ID = 15;
    CLASS_ID = 16;
    CHAR_ID = 17;
    OBJ_ID = 18;
    J_SHORT = 19;
    J_BYTE = 20;
    J_INT = 21;
    J_LONG = 22;
    J_FLOAT = 23;
    J_DOUBLE = 24;
    J_CHAR = 25;
    J_BOOLEAN = 26;
    J_STRING = 27;
    LT = 28;
    GT = 29;
    LTE = 30;
    GTE = 31;
    ASSIGN = 32;
    EQUAL = 33;
    AND_OP = 34;
    OR_OP = 35;
    L_PAREN = 36;
    R_PAREN = 37;
    L_BRACKIE = 38;
    R_BRACKIE = 39;
    L_CURLY = 40;
    R_CURLY = 41;
    SEMI = 42;
    DOT = 43;
    COMMA = 44;
    M_GET_CLASSES = 45;
    M_GET_CLASS_NAMES = 46;
    M_GET_CLASS = 47;
    M_GET_ALL_OBJECTS = 48;
    M_GET_OBJECTS = 49;
    EOF = 50;
    EOL = 51;
}

$_GLOBALS = dict[
    "PROJECT_NAME" => "JavaQL",
    "PROJECT_URL" => "https://github.com/TomHeatwole/JavaQL/",
    // Map for 'Type' field 
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
    "TOKEN_NAME_MAP" => new Map(dict[
        TokenType::INT_LITERAL => "int",
        TokenType::FLOAT_LITERAL => "float",
        TokenType::BOOLEAN_LITERAL => "boolean",
        TokenType::STRING_LITERAL => "String",
        TokenType::CHAR_LITERAL => "char",
        TokenType::NULL_LITERAL => "null",
        TokenType::CLASS_ID => "class name",
        TokenType::ID => "unknown identifier",
        TokenType::BYTE_ID => "byte",
        TokenType::SHORT_ID => "short",
        TokenType::INT_ID => "int",
        TokenType::LONG_ID => "long",
        TokenType::FLOAT_ID => "float",
        TokenType::DOUBLE_ID => "double",
        TokenType::BOOLEAN_ID => "boolean",
        TokenType::STRING_ID => "String",
        TokenType::CHAR_ID => "char",
        TokenType::J_BYTE => "type name",
        TokenType::J_SHORT => "type name",
        TokenType::J_INT => "type name",
        TokenType::J_LONG => "type name",
        TokenType::J_FLOAT => "type name",
        TokenType::J_DOUBLE => "type name",
        TokenType::J_BOOLEAN => "type name",
        TokenType::J_STRING => "type name",
        TokenType::J_CHAR => "type name",
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
    ]),
    "DEFAULTS" => new Map(dict[
        "class" => null,
        "byte" => 0,
        "short" => 0,
        "int" => 0,
        "long" => 0,
        "float" => 0,
        "double" => 0,
        "boolean" => false,
        "String" => "",
        "char" => "\0",
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
    ]),
    "ESCAPE_CHARS" => new Set(vec["b", "t", "0", "n", "r", "\"", "'", "\\"]),
    "KEYWORDS" => new Map(dict[
        "getClasses" => TokenType::M_GET_CLASSES,
        "getClassNames" => TokenType::M_GET_CLASS_NAMES,
        "getClass" => TokenType::M_GET_CLASS, 
        "getAllObjects" => TokenType::M_GET_ALL_OBJECTS,
        "getObjects" => TokenType::M_GET_OBJECTS,
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
    "PRIM" => new Set(vec["short", "byte", "int", "long", "float", "double", "char", "String"]), // JavaQL primitives
    "INT_MAX" => new Map(dict[
        "byte" => 127,
        "short" => 32767,
        "int" => 2147483647,
        "long" => 9223372036854775807,
    ]),
    "FLOAT_MAX" => 3.402823466E38,
];

