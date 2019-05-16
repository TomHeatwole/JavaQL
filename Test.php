<?hh

namespace HHVM\UserDocumentation\BasicUsage\Examples\CommandLine;

function main(array<string> $argv) {
    $host = "localhost";
    $username = "root";
    $password = "rootpassword";
    $database = "database_name";

    // Create connection
    echo "Attempting to connect to database...\n";
    $conn = mysqli_connect($host, $username, $password, $database);

    // Check connection
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error . "\n");
    }
    echo "Connected successfully\n";
}

main($argv);
