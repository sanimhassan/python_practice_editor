# Python Practice Web Application

A web-based Python learning platform that allows students to practice Python programming directly in the browser.

## Features

### Basic Mode (Skulpt)
- Interactive Python code editor with syntax highlighting
- Real-time code execution using Skulpt
- Line numbers and proper code indentation
- Exercise library with examples for different Python concepts
- Hints and example code for learning assistance

### Advanced Mode (Pyodide) - Coming Soon
- Full Python 3 support with Pyodide
- Access to Python packages and libraries
- Advanced code execution capabilities

## UI/UX Improvements

The application features a modern, user-friendly interface with:

- Clean, responsive design that works on desktop and mobile devices
- Intuitive navigation with tabbed interface
- Exercise sidebar with categorized learning content
- Code editor with line numbers and proper indentation
- Syntax highlighting for better code readability
- Dark-themed output console for better visibility
- Toast notifications for user feedback
- Tooltips for improved usability
- Accessibility improvements for all users

## Technical Notes

### Skulpt Limitations
- Skulpt is a JavaScript implementation of Python that runs in the browser
- It supports most Python 2.x features but has limited Python 3 support
- F-strings and some other Python 3 features are not supported
- For full Python 3 support, the Advanced mode will use Pyodide (WebAssembly-based Python)

### Files Structure
- `index.html` - The main HTML file for the web application.
- `css/enhanced-styles.css` - CSS styles for the modern, user-friendly interface.
- `js/readme.js` - Manages the content and display of the README tab.
- `js/theme.js` - Handles light and dark theme toggling.
- `js/ui-helpers.js` - Provides utility functions for UI interactions like toast notifications and clearing output panels.
- `js/advanced-engine.js` - Manages the Pyodide (Advanced Mode) Python engine, including loading, executing Python code, and handling output/errors.
- `js/app-controller.js` - The main application controller, responsible for initializing modules, updating UI based on authentication and mode, and setting up event listeners.
- `js/auth.js` - Handles user authentication (login, registration, logout), manages user sessions, and provides functions for saving and loading user code.
- `js/basic-engine.js` - Manages the Skulpt (Basic Mode) Python interpreter, including code execution, output handling, and guest execution limits.
- `js/editor.js` - Manages the CodeMirror editor instances, handles tab switching between Basic, Advanced, and Readme modes, and integrates run/reset functionalities.
- `js/pyodide-setup.js` - (Not directly used in the current `advanced-engine.js` but likely a remnant or alternative setup for Pyodide)
- `js/enhanced-ui.js` - (Likely deprecated or integrated into `app-controller.js` and `ui-helpers.js` based on code review)
- `js/hostinger-ui.js` - (Likely deprecated or specific to a hosting environment, not directly integrated into core logic)
- `php/fixed_db_config.php` - PHP file for database configuration.
- `php/fixed_login.php` - PHP file for handling user login and session management.
- `php/fixed_register.php` - PHP file for handling new user registration.
- `php/save_progress.php` - PHP file for saving user code progress.
- `php/get_codes.php` - PHP file for retrieving user's saved codes.

## How to Use

1. Open `enhanced-index.html` in a web browser to use the enhanced version
2. Select an exercise from the sidebar
3. Write or modify Python code in the editor
4. Click "Run Code" to execute the Python code
5. View the output in the console below the editor
6. Use the toolbar for additional features:
   - Select examples from the dropdown
   - Get hints for the current exercise
   - Reset code to the default example
   - Clear the output console

## Future Improvements

- Implement the Advanced mode with Pyodide for full Python 3 support
- Add more exercises and examples
- Implement user authentication and progress tracking
- Add code sharing and export functionality
- Implement a more sophisticated code editor with full syntax highlighting
- Add unit tests for exercises to verify solutions
