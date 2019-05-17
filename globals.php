<?hh 
    $PROJECT_NAME = "JavaQL";
    $PROJECT_URL = "https://github.com/TomHeatwole/JavaQL/";

    // Map for 'Type' field 
    $FROM_SQL_TYPE_MAP = dict[
        "varchar(255)" => "String",
        "int(4)" => "byte",
        "int(6)" => "short",
        "int(11)" => "int",
        "int(20)" => "long",
        "char(1)" => "char",
        "tinyint(1)" => "boolean",
        "float" => "float",
        "double" => "double",
    ];

