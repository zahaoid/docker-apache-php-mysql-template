<?php

// Database credentials
$sqlHostAddress = getenv("MYSQL_HOST_ADDRESS");
$superUsername = "root";
$superPassword = getenv("MYSQL_ROOT_PASSWORD");
$webServerUsername = getenv("MYSQL_USER");
$webServerPassword = getenv("MYSQL_PASSWORD");
$dbName = getenv("MYSQL_DATABASE");
$sqlFolderPath = __DIR__ . '/sql';
$migrationsFolderPath = DIRECTORY_SEPARATOR . $sqlFolderPath . '/migrations';
$migrationFiles = null;


$mysqli = null; // Declare the $mysqli connection variable

// Connect to the database
function connect()
{
    global $sqlHostAddress, $superUsername, $superPassword, $dbName, $mysqli;
    $maxRetries = 10; // Number of retries
    $retryDelay = 1; // Delay in seconds before retrying

    if (!$superUsername || !$superPassword || !$dbName) {
        throw new Exception("Missing environment variables for MySQL connection.");
    }

    $attempt = 0;
    while ($attempt < $maxRetries) {
        try {
            $mysqli = mysqli_connect($sqlHostAddress, $superUsername, $superPassword, $dbName);
            if (!$mysqli) {
                throw new Exception("Connection failed: " . mysqli_connect_error());
            }
            echo "Connected successfully to " . $dbName . "\n";
            return; // Exit the function if connection is successful
        } catch (Exception $e) {
            $attempt++;
            if ($attempt < $maxRetries) {
                echo "Connection failed. Retrying in {$retryDelay} seconds...\n";
                sleep($retryDelay); // Wait for 3 seconds before retrying
            } else {
                throw new Exception("Connection failed after {$maxRetries} attempts: " . $e->getMessage());
            }
        }
    }
}


// Get the current database version
function getDatabaseVersion()
{
    global $mysqli;
    $query = "SELECT MAX(version) AS currentversion FROM version_control; ";
    $result = $mysqli->query($query);

    if ($result) {
        $row = $result->fetch_assoc();
        return $row['currentversion'] ? $row['currentversion'] : 0;
    }
    return 0;
}

// Check if the database is version-controlled
function isVersionControlled()
{
    global $mysqli, $dbName;
    $query = "SELECT * FROM information_schema.tables WHERE table_schema = ? AND table_name = 'version_control' LIMIT 1";
    $stmt = $mysqli->prepare($query);
    $stmt->bind_param("s", $dbName);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->num_rows > 0;
}

// Create the version control table
function createVersionControlTable()
{
    global $mysqli;
    $query = "CREATE TABLE version_control (version VARCHAR(10) PRIMARY KEY, comment VARCHAR(255), dateapplied TIMESTAMP NOT NULL, sql_query TEXT NOT NULL);";
    if ($mysqli->query($query)) {
        echo "Version control table created successfully.\n";
    } else {
        echo "Error creating version control table: " . $mysqli->error . "\n";
    }
}

function createWebUser(){
    global $mysqli, $dbName, $webServerUsername, $webServerPassword;
    $queries = ["CREATE USER IF NOT EXISTS '$webServerUsername'@'%' IDENTIFIED BY '$webServerPassword';",
    "GRANT SELECT, INSERT, UPDATE ON `$dbName`.* TO 'webapp'@'%';",
    "FLUSH PRIVILEGES;"];
    try{
        foreach($queries as $query ){
            $mysqli->query($query);
        }
        echo "Users have been created succesfully.\n";
    } catch (mysqli_sql_exception $e) {
        echo "Error creating users: " . $mysqli->error . "\n";
        throw $e;
    }

}

// Nuke the database (drop and recreate it)
function nukeDatabase()
{
    global $mysqli, $dbName;
    $drop_query = "DROP DATABASE IF EXISTS `$dbName`;";
    $create_query = "CREATE DATABASE `$dbName`;";

    if ($mysqli->query($drop_query) && $mysqli->query($create_query)) {
        echo "Database nuked successfully.\n";
    } else {
        throw new Exception( "Failed to reset Database: " . $mysqli->error . "\n");
    }
}

// Initialize the database by checking version control and applying migrations if needed
function initializeDatabase()
{
    global $mysqli, $migrationFiles;
    try{
        connect();
        createWebUser();
        echo "Checking integrity with the database...\n";

        loadMigrationFiles();
        if (!isVersionControlled()) {
            echo "Database is not version controlled, attempting to nuke the database..\n";
            nukeDatabase();
            connect(); // Reconnect
            createVersionControlTable();
            echo "The database has been reset and is now configured properly.\n";
        } else {
            echo "Database is already version controlled.\n";
            if(getAppVersion() < getDatabaseVersion()){
                throw new Exception("This app version is behind the database version!");
            }
        }

        // Migrate the database if there are migration scripts
        migrate();                  
    }
    catch(Exception $e) {  
        echo "A fatal error has occured, attempting to shutdown the web server.. \n";
        echo("Error: " . $e);
        die(1); //IMPORTANT!!! this exists php with an error code 1 so that apache doesnt run!!!!
    }
}

function loadMigrationFiles(){
    global $migrationsFolderPath, $migrationFiles;
    echo "Checking for migration scripts...\n";

    // Get the list of migration files
    $migrationFiles = array_diff(scandir($migrationsFolderPath), array('.', '..'));

    // Sort the migrations by version
    usort($migrationFiles, function ($a, $b) {
        return version_compare(parseVersion($a) , parseVersion($b) );
    });
    echo "available migration script: \n" ;
    print_r($migrationFiles);

}

function getAppVersion(){
    global $migrationFiles;
    return parseVersion(end($migrationFiles));
}


// Apply migrations based on available migration files
function migrate()
{
    global $migrationFiles;
    $databaseVersion = getDatabaseVersion();

    $newMigrations = array_filter($migrationFiles, function ($file) use ($databaseVersion) {
        return version_compare(parseVersion($file) , $databaseVersion) > 0;
    });

    if (!empty($newMigrations)) {
        echo "new SQL migration scripts found. Current database version = " . $databaseVersion . " current webserver version = " . getAppVersion() . "\n";
        $migrationSuccessful = true;

        foreach ($newMigrations as $file) {
            try {
                applyMigration($file);
            } catch (Exception $e) {
                $migrationSuccessful = false;
                throw new Exception ("Migration failed for {$file}: " . $e->getMessage() . "\n");
            }
        }

        if ($migrationSuccessful) {
            echo "All migrations applied successfully!\n";
        }
    } else {
        echo "No new migrations found. Database is at the latest version.\n";
    }
}

function readSqlFile($path){
    return file_get_contents($path);

}
// Apply a specific migration file to the database
function applyMigration($file)
{
    global $migrationsFolderPath, $mysqli;
    $version = parseVersion(filename: $file);
    $migrationSql = readSqlFile($migrationsFolderPath . DIRECTORY_SEPARATOR . $file);

    echo "Applying " . $file . "\n";

    try {
        // Start a transaction
        $mysqli->begin_transaction();
        // Apply the migration SQL
        if ($mysqli->query($migrationSql) === FALSE) {
            throw new Exception("Applying migration failed: " . $mysqli->error);
        }

        // Log the migration in the schema_change_log table
        $logQuery = "INSERT INTO version_control (version, sql_query, dateapplied) VALUES (?, ?, ?)";
        $stmt = $mysqli->prepare($logQuery);
        $dateApplied = date('Y-m-d H:i:s');
        $stmt->bind_param('sss', $version, $migrationSql, $dateApplied);
        if (!$stmt->execute()) {
            throw new Exception("Logging migration failed: " . $stmt->error);
        }

        // Commit the transaction
        $mysqli->commit();
        echo "Migration version " . $version . " applied successfully\n";
    } catch (Exception $e) {
        // Rollback the transaction in case of error
        $mysqli->rollback();
        throw $e;
    }
}

// Parse version from the migration file name
function parseVersion($filename)
{
    // Assuming the filename is of the format 'version.sql' (e.g., '1.sql', '2.sql')
    $version = pathinfo($filename, PATHINFO_FILENAME);
    return $version;
}

// Close the database connection
function closeConnection()
{
    global $mysqli;
    if ($mysqli) {
        $mysqli->close();
    }
}

// Execute the database initialization and migration process
initializeDatabase();

// Close the connection at the end
closeConnection();

?>