# Hybrid Python Engine Implementation

## Overview

This implementation combines **Skulpt** and **Pyodide** to provide the best of both worlds:

- **Skulpt**: Fast loading, instant execution for basic Python code
- **Pyodide**: Full Python 3.11 with advanced features and scientific libraries

## How It Works

### Automatic Engine Detection

The hybrid engine automatically detects which Python engine to use based on the code content:

#### Skulpt (Fast Engine) - Used for:
- Basic Python syntax
- Simple loops and conditionals
- Basic functions and classes
- `input()` function (Skulpt handles this well)
- Most beginner-level Python code
- **Input + F-strings combination** (f-strings are automatically converted)

#### Pyodide (Advanced Engine) - Used for:
- **F-strings**: `f"Hello {name}"` (when not combined with input())
- **Scientific libraries**: NumPy, Matplotlib, Pandas, SciPy
- **Advanced Python features**: Walrus operator, match statements
- **Async/await**: Asynchronous programming
- **Complex decorators**: Advanced decorator usage

### Performance Benefits

1. **Instant Startup**: Skulpt is ready immediately when the page loads
2. **Fast Execution**: Basic Python code runs instantly with Skulpt
3. **On-Demand Loading**: Pyodide only loads when advanced features are needed
4. **Seamless Switching**: Users don't need to choose engines manually

### Code Examples

#### Basic Python (Uses Skulpt)
```python
print("Hello, World!")
for i in range(5):
    print("Count:", i)
numbers = [1, 2, 3, 4, 5]
print("Sum:", sum(numbers))
```

#### F-strings (Switches to Pyodide)
```python
name = "Alice"
age = 30
print(f"Hello, {name}! You are {age} years old.")
```

#### Input + F-strings (Uses Skulpt with conversion)
```python
name = input("Enter your name: ")
age = int(input("Enter your age: "))
print(f"Welcome {name}, your age is {age}")
# F-strings are automatically converted to .format() for Skulpt
```

#### NumPy (Uses Pyodide)
```python
import numpy as np
arr = np.array([1, 2, 3, 4, 5])
print("Mean:", np.mean(arr))
```

## Implementation Details

### Files Modified/Created

1. **`js/hybrid-python-engine.js`** - Main hybrid engine logic
2. **`index.html`** - Updated to include Skulpt and new engine
3. **`js/editor.js`** - Updated to work with hybrid engine
4. **`js/app-controller.js`** - Updated initialization
5. **`css/style.css`** - Added styles for engine status

### Special Case: Input + F-strings

When code contains both `input()` functions and f-strings, the hybrid engine:

1. **Detects the combination** and chooses Skulpt for better `input()` support
2. **Automatically converts f-strings** to `.format()` syntax for Skulpt compatibility
3. **Provides seamless execution** without I/O errors

**Example conversion:**
```python
# Original code:
name = input("Enter your name: ")
print(f"Hello {name}!")

# Automatically converted to:
name = input("Enter your name: ")
print("Hello {}!".format(name))
```

This solves the common issue where Pyodide fails with I/O operations while still supporting modern Python syntax.

### Engine Detection Patterns

The engine uses regex patterns to detect advanced features:

```javascript
advancedFeatures: [
    /f["']/,                    // f-strings
    /import\s+numpy/,           // NumPy import
    /import\s+matplotlib/,      // Matplotlib import
    /\bwalrus\s*:=/,           // Walrus operator
    /match\s+\w+:/,            // Match statement
    /\basync\s+def/,           // Async functions
    // ... more patterns
]
```

### Status Indicators

The UI shows which engine is being used:

- **Green**: Engine ready
- **Orange**: Loading Pyodide for advanced features
- **Red**: Error occurred

## Testing

Use `test-hybrid.html` to test the hybrid engine:

1. Open `test-hybrid.html` in a browser
2. Test basic Python (should use Skulpt)
3. Test f-strings (should switch to Pyodide)
4. Test NumPy (should use Pyodide)

## Benefits for Users

### For Beginners
- **Instant feedback**: No waiting for Python engine to load
- **Fast execution**: Basic code runs immediately
- **Smooth learning curve**: Start with simple code, progress to advanced

### For Advanced Users
- **Full Python support**: All Python 3.11 features available
- **Scientific computing**: NumPy, Matplotlib, Pandas support
- **No manual switching**: Engine selection is automatic

## Future Enhancements

1. **Smart Caching**: Cache Pyodide initialization for faster subsequent loads
2. **Progressive Loading**: Load Pyodide packages incrementally
3. **Code Analysis**: More sophisticated detection of required features
4. **User Preferences**: Allow manual engine selection for power users

## Troubleshooting

### Common Issues

1. **F-strings not working**: Ensure the code contains `f"` or `f'` patterns
2. **NumPy not loading**: Check browser console for Pyodide loading errors
3. **Slow first advanced run**: Pyodide loads on first advanced feature use

### Debug Mode

Enable debug logging by opening browser console and running:
```javascript
hybridPythonEngine.debugMode = true;
```

This will show detailed information about engine detection and switching.