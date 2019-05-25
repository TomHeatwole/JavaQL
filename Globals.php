<?hh 

enum TokenType: int {
    INT_LITERAL = 0;
    FLOAT_LITERAL = 1;
    BOOLEAN_LITERAL = 2;
    STRING_LITERAL = 3;
    CHAR_LITERAL = 4;
    NULL_LITERAL = 5;
    ID = 6; // new ID and class variables
    FLOAT_ID = 7;
    BOOLEAN_ID = 8;
    STRING_ID = 9;
    INT_ID = 10;
    CLASS_ID = 11;
    CHAR_ID = 12;
    SYMBOL = 13;
    KEYWORD = 14;
    EOF = 15;
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
        TokenType::SYMBOL => "symbol",
        TokenType::KEYWORD => "keyword",
        TokenType::EOF => "end of file",
    ]),
    "ESCAPE_CHARS" => new Set(vec["b", "t", "0", "n", "r", "\"", "'", "\\"]),
    "KEYWORDS" => new Set(vec[
        "getClasses",
        "getClassNames",
        "getClass",
        "getAllObjects",
        "getObjects",
        "new",
    ]),
    "SYMBOLS" => new Set(vec[
        "<",
        ">",
        "<=",
        ">=",
        "=",
        "==",
        "&&",
        "||",
        "(",
        ")",
        "[",
        "]",
        "{",
        "}",
        ";",
        ".",
        ","
    ]),
    "PRIM" => new Set(vec["short", "byte", "int", "long", "float", "double", "char", "String"]),
    // JavaQL primitives, not Java

];

