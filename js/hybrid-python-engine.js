/**
 * Pyodide Python Engine
 * 
 * Strategy:
 * - Use Pyodide for all Python code execution.
 * - Provides a simple interface for running Python and managing the Pyodide instance.
 */

const hybridPythonEngine = {
    // Engine states
    pyodideReady: false,
    pyodideLoading: false,
    pyodide: null,
    pyodideInitPromise: null,

    /**
     * Initialize the Pyodide engine
     */
    async init() {
        console.log('Initializing Python engine...');
        // We will initialize Pyodide on page load to improve responsiveness.
        this.initPyodide();
        return true;
    },

    /**
     * Initialize Pyodide
     */
    async initPyodide() {
        if (this.pyodideReady || this.pyodideLoading) {
            return this.pyodideInitPromise;
        }

        this.pyodideLoading = true;
        this.updateStatus('Installing all the dependencies, please wait...', 'loading');

        this.pyodideInitPromise = new Promise(async (resolve, reject) => {
            try {
                // Dynamically load the Pyodide script if it's not already there
                if (typeof loadPyodide === 'undefined') {
                    await this.loadPyodideScript();
                }

                this.pyodide = await loadPyodide({
                    indexURL: "https://cdn.jsdelivr.net/pyodide/v0.24.1/full/",
                    fullStdLib: false // Load only essential stdlib initially
                });

                // Load essential packages
                this.updateStatus('Loading core libraries (numpy, pandas, matplotlib)...', 'loading');
                await this.pyodide.loadPackage(['numpy', 'pandas', 'matplotlib']);
                this.updateStatus('Core libraries loaded.', 'ready');


                // Set up the environment for capturing output and handling input
                this.pyodide.runPython(`
import sys, io, builtins
from js import prompt

_stdout_buffer = io.StringIO()
_stderr_buffer = io.StringIO()
sys.stdout = _stdout_buffer
sys.stderr = _stderr_buffer

def custom_input_function(prompt_text=""):
    # Use the browser's prompt for input
    return prompt(prompt_text)

builtins.input = custom_input_function

def get_output():
    stdout = _stdout_buffer.getvalue()
    stderr = _stderr_buffer.getvalue()
    _stdout_buffer.seek(0); _stdout_buffer.truncate(0)
    _stderr_buffer.seek(0); _stderr_buffer.truncate(0)
    return stdout, stderr
                `);

                this.pyodideReady = true;
                this.pyodideLoading = false;
                this.updateStatus('You may run the code now', 'ready');
                console.log('Pyodide initialized successfully');
                resolve(this.pyodide);

            } catch (error) {
                this.pyodideLoading = false;
                this.updateStatus('Failed to load Pyodide', 'error');
                console.error('Pyodide initialization failed:', error);
                reject(error);
            }
        });

        return this.pyodideInitPromise;
    },

    async loadPyodideScript() {
        return new Promise((resolve, reject) => {
            if (typeof loadPyodide !== 'undefined') return resolve();
            const script = document.createElement('script');
            script.src = 'https://cdn.jsdelivr.net/pyodide/v0.24.1/full/pyodide.js';
            script.onload = resolve;
            script.onerror = reject;
            document.head.appendChild(script);
        });
    },

    /**
     * Run Python code using Pyodide
     */
    async runPythonCode(code, outputElementId = 'output') {
        const outputElement = document.getElementById(outputElementId);
        if (!outputElement) {
            console.error(`Output element with ID '${outputElementId}' not found`);
            return;
        }

        try {
            // Ensure Pyodide is initialized
            if (!this.pyodideReady) {
                await this.initPyodide();
            }
            
            this.updateStatus('Running code...', 'running');
            if (outputElement) outputElement.innerHTML = '<div class="output-info">Running code...</div>';
            
            // Execute the code
            this.pyodide.runPython(code);
            
            // Get stdout and stderr
            const [stdout, stderr] = this.pyodide.runPython('get_output()');
            
            // Display the output
            let outputHtml = '';
            if (stdout) {
                outputHtml += `<div class="output-success">${this.escapeHtml(stdout)}</div>`;
            }
            if (stderr) {
                outputHtml += `<div class="output-error">${this.escapeHtml(stderr)}</div>`;
            }
            
            if (outputElement) {
                outputElement.innerHTML = outputHtml || '<div class="output-success">Code executed successfully (no output)</div>';
            }
            
            this.updateStatus('Execution finished.', 'ready');
            return stdout;
            
        } catch (error) {
            console.error('Pyodide execution error:', error);
            if (outputElement) {
                outputElement.innerHTML = `<div class="output-error">Error: ${this.escapeHtml(error.toString())}</div>`;
            }
            this.updateStatus('Error running code', 'error');
            throw error;
        }
    },


    /**
     * Install a package using Pyodide's micropip
     */
    async installPackage(packageName) {
        try {
            await this.initPyodide();
            this.updateStatus(`Installing ${packageName}...`, 'loading');
            
            await this.pyodide.loadPackage('micropip');
            await this.pyodide.runPythonAsync(`
                import micropip
                await micropip.install('${packageName}')
            `);
            
            this.updateStatus(`${packageName} installed successfully`, 'ready');
            return true;
        } catch (error) {
            console.error(`Error installing ${packageName}:`, error);
            this.updateStatus(`Error installing ${packageName}`, 'error');
            throw error;
        }
    },

    /**
     * Update the engine status in the UI
     */
    updateStatus(message, type = 'info') {
        console.log(`Engine status: ${message} (${type})`);
        
        const statusElement = document.getElementById('engine-status-text');
        if (statusElement) {
            statusElement.textContent = message;
        }
        
        const runButton = document.getElementById('run-btn'); 
        if (runButton) {
            if (type === 'loading') {
                runButton.disabled = true;
                runButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading...';
            } else if (type === 'running') {
                runButton.disabled = true;
                runButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Running...';
            } else {
                runButton.disabled = false;
                runButton.innerHTML = '<i class="fas fa-play"></i> Run Code';
            }
        }
    },

    /**
     * Escape HTML for safe display
     */
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
};

// Single, reliable initialization point for the engine.
document.addEventListener('DOMContentLoaded', function() {
    setTimeout(() => {
        hybridPythonEngine.init();
    }, 100); // A small delay to ensure other scripts have a chance to load.
});

// Make it globally available
window.hybridPythonEngine = hybridPythonEngine;

// Export functions for compatibility
window.runPythonCode = hybridPythonEngine.runPythonCode.bind(hybridPythonEngine);
