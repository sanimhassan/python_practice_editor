// js/python-engine.js

// Encapsulate Python execution logic in a pythonEngine object
const pythonEngine = {
    initPromise: null,
    pyodideInitialized: false,
    pyodide: null,
    initializationStatus: 'not-started', // 'not-started', 'loading', 'ready', 'error'

    /**
     * Initializes Pyodide engine.
     * This function is idempotent and handles concurrent calls.
     */
    initPyodide: function() {
        if (this.initPromise) {
            return this.initPromise;
        }

        this.initPromise = new Promise(async (resolve, reject) => {
            if (this.pyodideInitialized) {
                return resolve();
            }

            try {
                if (typeof loadPyodide === 'undefined') {
                    reject(new Error('Pyodide library not loaded'));
                    return;
                }

                this.initializationStatus = 'loading';
                this.updateInitializationUI('Loading Pyodide...');
                console.log("Initializing Pyodide...");

                this.pyodide = await loadPyodide({
                    indexURL: "https://cdn.jsdelivr.net/pyodide/v0.24.1/full/",
                    fullStdLib: false // Load only essential stdlib initially
                });

                this.updateInitializationUI('Installing essential packages...');
                // Install only micropip initially
                await this.pyodide.loadPackage(["micropip"]);

                this.pyodideInitialized = true;
                this.initializationStatus = 'ready';
                this.updateInitializationUI('Pyodide ready! You can now run Python code.');
                console.log("Pyodide initialized successfully.");

                // Load additional packages in the background
                this.loadAdditionalPackagesInBackground();
                console.log("Pyodide initialized successfully.");
                resolve();

            } catch (error) {
                this.initializationStatus = 'error';
                this.updateInitializationUI(`Failed to initialize Pyodide: ${error.message}`);
                console.error("Error during Pyodide initialization:", error);
                reject(error);
            }
        });
        return this.initPromise;
    },

    /**
     * Updates the UI to show initialization status
     */
    updateInitializationUI: function(message) {
        // Update any status elements in the UI
        const statusElements = document.querySelectorAll('.python-status');
        statusElements.forEach(el => {
            el.textContent = message;
            el.className = `python-status ${this.initializationStatus}`;
        });

        // Update run buttons based on status
        const runButtons = document.querySelectorAll('#run-btn, #run-btn-advanced');
        runButtons.forEach(btn => {
            if (this.initializationStatus === 'ready') {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-play"></i> Run Code';
                btn.classList.add('ready');
            } else if (this.initializationStatus === 'loading') {
                btn.disabled = true;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading...';
                btn.classList.remove('ready');
            } else if (this.initializationStatus === 'error') {
                btn.disabled = true;
                btn.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Error';
                btn.classList.remove('ready');
            }
        });
    },

    /**
     * Load additional packages in the background after initialization
     */
    loadAdditionalPackagesInBackground: async function() {
        try {
            console.log("Loading additional packages in the background...");
            // Load numpy and matplotlib in the background
            await this.pyodide.loadPackage(["numpy", "matplotlib"]);
            console.log("Additional packages loaded successfully.");
        } catch (error) {
            console.warn("Failed to load additional packages:", error);
        }
    },

    /**
     * Detects which Python engine to use based on the current mode.
     * @param {string} mode - The current editor mode ('basic-mode' or 'advanced-mode').
     * @returns {string} - The detected engine ('pyodide').
     */
    detectEngine: function(mode) {
        console.log("Detecting Python engine: Pyodide for all modes");
        return 'pyodide';
    },

    /**
     * Runs Python code using the Pyodide engine.
     * @param {string} code - The Python code to execute.
     * @param {string} outputElementId - The ID of the output element.
     */
    runPythonCode: async function(code, outputElementId = 'output') {
        const output = document.getElementById(outputElementId);
        if (!output) {
            console.error(`Output element with id '${outputElementId}' not found!`);
            return;
        }

        try {
            // Ensure Pyodide is initialized
            await this.initPyodide();

            // Clear previous output
            output.textContent = '';

            // Show execution status
            output.textContent = 'Executing...';

            // Capture stdout and stderr
            this.pyodide.runPython(`
import sys
import io
from contextlib import redirect_stdout, redirect_stderr

# Create string buffers for stdout and stderr
stdout_buffer = io.StringIO()
stderr_buffer = io.StringIO()
`);

            // Run the user code with output redirection
            try {
                this.pyodide.runPython(`
with redirect_stdout(stdout_buffer), redirect_stderr(stderr_buffer):
${code.split('\n').map(line => '    ' + line).join('\n')}
`);
            } catch (error) {
                // If there's an error in user code, it will be captured in stderr
                console.log("Python execution completed with potential errors");
            }

            // Get the captured output
            const stdout = this.pyodide.runPython('stdout_buffer.getvalue()');
            const stderr = this.pyodide.runPython('stderr_buffer.getvalue()');

            // Display the output
            let outputText = '';
            if (stdout) outputText += stdout;
            if (stderr) outputText += stderr;
            
            output.textContent = outputText || 'Code executed successfully (no output)';

        } catch (error) {
            const errorMsg = `Error: ${error.toString()}`;
            output.textContent = errorMsg;
            console.error("Pyodide execution error:", error);
        }
    },

    /**
     * Runs Python code in advanced mode (same as basic mode now since we use Pyodide for both).
     * @param {string} code - The Python code to execute.
     */
    runAdvancedPython: async function(code) {
        return this.runPythonCode(code, 'output-advanced');
    },

    /**
     * Get example code snippets
     */
    getExamples: function() {
        return {
            hello: `print("Hello, World!")
print("Welcome to Python Practice!")`,
            
            math: `import math

# Basic math operations
a, b = 10, 3
print(f"{a} + {b} = {a + b}")
print(f"{a} - {b} = {a - b}")
print(f"{a} * {b} = {a * b}")
print(f"{a} / {b} = {a / b:.2f}")
print(f"{a} ** {b} = {a ** b}")

# Math module functions
print(f"sqrt(16) = {math.sqrt(16)}")
print(f"sin(π/2) = {math.sin(math.pi/2):.2f}")`,

            loops: `# List comprehension
numbers = list(range(1, 11))
print("Numbers:", numbers)

squares = [x**2 for x in numbers]
print("Squares:", squares)

# For loop
print("Even numbers:")
for num in numbers:
    if num % 2 == 0:
        print(f"  {num}")

# While loop
count = 0
factorial = 1
n = 5
while count < n:
    count += 1
    factorial *= count
print(f"{n}! = {factorial}")`,

            functions: `def greet(name, age=None):
    if age:
        return f"Hello {name}, you are {age} years old!"
    return f"Hello {name}!"

def calculate_area(shape, **kwargs):
    if shape == "rectangle":
        return kwargs["width"] * kwargs["height"]
    elif shape == "circle":
        import math
        return math.pi * kwargs["radius"] ** 2
    return 0

# Test functions
print(greet("Alice"))
print(greet("Bob", 25))

rect_area = calculate_area("rectangle", width=5, height=3)
circle_area = calculate_area("circle", radius=4)

print(f"Rectangle area: {rect_area}")
print(f"Circle area: {circle_area:.2f}")`,

            numpy: `import numpy as np

# Create arrays
arr1 = np.array([1, 2, 3, 4, 5])
arr2 = np.array([6, 7, 8, 9, 10])

print("Array 1:", arr1)
print("Array 2:", arr2)

# Basic operations
print("Sum:", arr1 + arr2)
print("Product:", arr1 * arr2)
print("Mean of arr1:", np.mean(arr1))

# 2D array
matrix = np.array([[1, 2, 3], [4, 5, 6], [7, 8, 9]])
print("Matrix:")
print(matrix)
print("Matrix transpose:")
print(matrix.T)`
        };
    },

    /**
     * Load an example into the editor
     */
    loadExample: function(exampleName, editorInstance) {
        const examples = this.getExamples();
        if (examples[exampleName] && editorInstance) {
            editorInstance.setValue(examples[exampleName]);
        }
    },

    /**
     * Get initialization status
     */
    getStatus: function() {
        if (this.pyodideInitialized) {
            return { ready: true, message: 'Pyodide ready! You can run Python code.' };
        } else if (this.initPromise) {
            return { ready: false, message: 'Initializing Pyodide... Please wait.' };
        } else {
            return { ready: false, message: 'Pyodide not initialized.' };
        }
    }
};

// Make the pythonEngine object globally available
window.pythonEngine = pythonEngine;

// Maintain backward compatibility for existing calls
window.runPythonCode = pythonEngine.runPythonCode.bind(pythonEngine);
window.runAdvancedPython = pythonEngine.runAdvancedPython.bind(pythonEngine);

// The auto-initialization is removed from here.
// The page using the engine should be responsible for calling initPyodide.