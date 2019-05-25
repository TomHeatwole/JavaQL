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
    BOOLEAN_ID = 9;
    STRING_ID = 10;
    INT_ID = 11;
    CLASS_ID = 12;
    CHAR_ID = 13;
    OBJ_ID = 14;
    LT = 15;
    GT = 16;
    LTE = 17;
    GTE = 18;
    ASSIGN = 19;
    EQUAL = 20;
    AND_OP = 21;
    OR_OP = 22;
    L_PAREN = 23;
    R_PAREN = 24;
    L_BRACKIE = 25;
    R_BRACKIE = 26;
    L_CURLY = 27;
    R_CURLY = 28;
    SEMI = 29;
    DOT = 30;
    COMMA = 31;
    M_GET_CLASSES = 32;
    M_GET_CLASS_NAMES = 33;
    M_GET_CLASS = 34;
    M_GET_ALL_OBJECTS = 35;
    M_GET_OBJECTS = 36;
    EOF = 37;
}


$_GLOBALS = dict[
    "PROJECT_NAME" => "JavaQL",
    "PROJECT_URL" => "https://github.com/TomHeatwole/JavaQL/",
    // Map for 'Type' field 
    "FROM_SQL_TYPE_MAP" => dict[
        "varchar(255)" => "String",
        "int(4)" => "byte",
        "int(6)" => "short",
        "int(11)" => "int",
        "int(20)" => "long",
        "char(1)" => "char",
        "tinyint(1)" => "boolean",
        "float" => "float",
        "double" => "double",
    ],
    "TOKEN_NAME_MAP" => new Map(dict[
        TokenType::INT_LITERAL => "integer",
        TokenType::FLOAT_LITERAL => "float",
        TokenType::BOOLEAN_LITERAL => "boolean",
        TokenType::STRING_LITERAL => "string",
        TokenType::CHAR_LITERAL => "char",
        TokenType::CLASS_ID => "class name",
        TokenType::ID => "identifier",
        TokenType::INT_ID => "integer",
        TokenType::FLOAT_ID => "float",
        TokenType::BOOLEAN_ID => "boolean",
        TokenType::STRING_ID => "string",
        TokenType::CHAR_ID => "char",
        TokenType::EOF => "end of file",
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
        TokenType::COMMA => ","
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
        "," => TokenType::COMMA
    ]),
    "PRIM" => new Set(vec["short", "byte", "int", "long", "float", "double", "char", "String"]),
    // JavaQL primitives, not Java

];

