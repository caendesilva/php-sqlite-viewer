# PHP SQLite Viewer

![Latest Release](https://img.shields.io/github/v/release/caendesilva/php-sqlite-viewer?style=flat-square)
![PHP Version](https://img.shields.io/static/v1?label=Min%20PHP%20Version&message=7.0&color=blue&style=flat-square)
![File Size](https://img.shields.io/github/size/caendesilva/php-sqlite-viewer/sqlite_viewer.php?style=flat-square)

⚠️ Highly Experimental - Early Initial Development ⚠️

## Overview

A lightweight, single-file PHP SQLite database viewer with no dependencies. Run from the command line and view in your browser.

### Features

- 📁 **Single File**: No dependencies; everything is contained in a single PHP file.
- 🖥️ **Cross-Platform**: Runs on Windows, macOS, and Linux.
- 🏛️ **Run Anywhere** - Compatible with legacy and modern systems and PHP versions.
- ✅ **No Installation Required**: Nothing to install; just run the script.
- 🌐 **Web Interface**: View your SQLite database in your browser.
- 🛡️ **Secure**: Runs locally and offline. Data stays on your machine.
- 🔒 **Safe**: Database is readonly and never modified.
- 🚀 **Fast**: Lightweight and optimized for speed.


## Usage

To use the SQLite Viewer, execute it from the command line:

```bash
php sqlite_viewer.php ./path/to/your/database.sqlite
```

Access it in your browser at [http://localhost:9000](http://localhost:9000).

### Quick Access

For quicker access, it is recommended to alias the script. You can add the following line to your shell profile:

```bash
alias db="php /path/to/sqlite_viewer.php"
```

Then, run it using:

```bash
db ./path/to/your/database.sqlite
```

### Windows Users

For Windows users, a `.bat` file is provided, allowing you to run the viewer as an executable:

1. Place `sqlite_viewer.bat` in your system's PATH.
2. Use the command:
   ```cmd
   sqlite_viewer.bat .\path\to\your\database.sqlite
   ```
   
Make sure to either have the PHP file in the same directory, or hardcode the full path to it in the `.bat` file.

**Tip:** Rename it to `db.bat` for easier access. If it's in your PATH, you can run simply `db .\path\to\your\database.sqlite` from anywhere.

### Environment Variables and Command Line Options

You can customize the default behavior using environment variables or command line options:

- **Port**: Change the default port using `SQLITE_VIEWER_PORT` or the `--port <int>` option. Default is `9000`.
- **Auto Open**: Control automatic opening of the web page with `SQLITE_VIEWER_DEFAULT_OPEN_PAGE` or the `--open <bool>` option. Default is `true`.

## Requirements

- PHP 7.0 or higher
- SQLite3 extension enabled
- You need a browser that can run JavaScript for interactions like modals. If you're a developer, you probably have one.
- For the time being you need an internet connection to load the Tailwind and Alpine. This will probably be bundled in the future if enough people use this.

## Contributing

Contributions are welcome! Feel free to submit issues or pull requests.

## License

This project is licensed under the MIT License.

## Acknowledgments

This project is built on PHP and uses the SQLite3 extension for database access.
