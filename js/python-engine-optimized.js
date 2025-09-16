// js/python-engine-optimized.js
// Optimized version for faster loading

const pythonEngine = {
    initPromise: null,
    pyodideInitialized: false,
    pyodide: null,
    loadingStartTime: null,

    /**
     * Optimized Pyodide initialization with progress tracking
     */
    initPyodide: function() {
        if (this.initPromise) {
            return this.initPromise;
        }

        this.loadingStartTime = Date.now();
        this.updateStatus('Initializing Python engine...', 'loading');

        this.initPromise = new Promise(async (resolve, reject) => {
            if (this.pyodideInitialized) {
                return resolve();
            }

            try {
                if (typeof loadPyodide === 'undefined') {
                    reject(new Error('Pyodide library not loaded'));
                    return;
                }

                // Step 1: Load Pyodide core
                this.updateStatus('Loading Python runtime...', 'loading');
                this.pyodide = await loadPyodide({
                    indexURL: "https://cdn.jsdelivr.net/pyodide/v0.24.1/full/",
                    fullStdLib: false // Load only essential stdlib initially
                });

                // Step 2: Load only essential packages initially
                this.updateStatus('Loading essential packages...', 'loading');
                await this.pyodide.loadPackage(["micropip"]);

                // Step 3: Set up basic environment
                this.pyodide.runPython(`
import sys
import io
from contextlib import redirect_stdout, redirect_stderr

# Pre-create buffers for better performance
stdout_buffer = io.StringIO()
stderr_buffer = io.StringIO()
`);

                this.pyodideInitialized = true;
                const loadTime = ((Date.now() - this.loadingStartTime) / 1000).toFixed(1);
                this.updateStatus(`Python ready! (${loadTime}s)`, 'ready');
                console.log(`Pyodide initialized in ${loadTime} seconds`);
                resolve();

                // Load additional packages in background
                this.loadAdditionalPackages();

            } catch (error) {
                console.error("Error during Pyodide initialization:", error);
                this.updateStatus(`Failed to load Python: ${error.message}`, 'error');
                reject(error);
            }
        });
        return this.initPromise;
    },

    /**
     * Load additional packages in background after core initialization
     */
    loadAdditionalPackages: async function() {
        try {
            this.updateStatus('Loading scientific packages in background...', 'loading');
            await this.pyodide.loadPackage(["numpy"]);
            this.updateStatus('Python ready with NumPy!', 'ready');
        } catch (error) {
            console.warn("Failed to load additional packages:", error);
            this.updateStatus('Python ready (basic packages only)', 'ready');
        }
    },

    /**
     * Update status display
     */
    updateStatus: function(message, type) {
        // Update any status elements
        const statusElements = document.querySelectorAll('.python-status, #python-status');
        statusElements.forEach(el => {
            el.textContent = message;
            el.className = `python-status ${type}`;
        });

        // Also update run buttons
        const runButtons = document.querySelectorAll('#run-btn, #run-btn-advanced');
        runButtons.forEach(btn => {
            if (type === 'ready') {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-play"></i> Run Code';
                btn.classList.add('ready');
            } else if (type === 'loading') {
                btn.disabled = true;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading...';
                btn.classList.remove('ready');
            } else if (type === 'error') {
                btn.disabled = true;
                btn.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Error';
                btn.classList.remove('ready');
            }
        });
    },

    /**
     * Optimized Python code execution
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
            output.textContent = 'Executing...';

            // Use pre-created buffers for better performance
            this.pyodide.runPython(`
# Clear buffers
stdout_buffer.seek(0)
stdout_buffer.truncate(0)
stderr_buffer.seek(0)
stderr_buffer.truncate(0)
`);

            // Execute user code with output redirection
            const startTime = Date.now();
            try {
                this.pyodide.runPython(`
with redirect_stdout(stdout_buffer), redirect_stderr(stderr_buffer):
${code.split('\n').map(line => '    ' + line).join('\n')}
`);
            } catch (error) {
                // Errors will be captured in stderr
                console.log("Python execution completed with potential errors");
            }

            // Get output efficiently
            const stdout = this.pyodide.runPython('stdout_buffer.getvalue()');
            const stderr = this.pyodide.runPython('stderr_buffer.getvalue()');
            const executionTime = Date.now() - startTime;

            // Display results
            let outputText = '';
            if (stdout) outputText += stdout;
            if (stderr) outputText += stderr;
            
            if (!outputText.trim()) {
                outputText = `Code executed successfully in ${executionTime}ms (no output)`;
            }
            
            output.textContent = outputText;

        } catch (error) {
            const errorMsg = `Error: ${error.toString()}`;
            output.textContent = errorMsg;
            console.error("Pyodide execution error:", error);
        }
    },

    /**
     * Advanced mode execution (same as basic now)
     */
    runAdvancedPython: async function(code) {
        return this.runPythonCode(code, 'output-advanced');
    },

    /**
     * Install package on demand
     */
    installPackage: async function(packageName) {
        if (!this.pyodideInitialized) {
            throw new Error('Pyodide not initialized');
        }
        
        this.updateStatus(`Installing ${packageName}...`, 'loading');
        try {
            await this.pyodide.loadPackage([packageName]);
            this.updateStatus('Python ready!', 'ready');
            return true;
        } catch (error) {
            this.updateStatus('Python ready (package install failed)', 'ready');
            throw error;
        }
    }
};

// Make globally available
window.pythonEngine = pythonEngine;
window.runPythonCode = pythonEngine.runPythonCode.bind(pythonEngine);
window.runAdvancedPython = pythonEngine.runAdvancedPython.bind(pythonEngine);