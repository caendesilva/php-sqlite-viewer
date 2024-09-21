#!/bin/bash

if [ $# -eq 0 ]; then
    echo "Error: Please provide the path to the SQLite database as an argument."
    echo "Usage: $0 /path/to/your/database.sqlite"
    exit 1
fi

if [ ! -f "$1" ]; then
    echo "Error: The specified database file does not exist."
    echo "Path: $1"
    exit 1
fi

export SQLITE_DB_PATH="$1"
php -S localhost:8000 "$(dirname "$0")/sqlite_viewer.php"